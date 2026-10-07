<?php

namespace Tests\Unit;

use App\Services\PlanLedgerService;
use PHPUnit\Framework\TestCase;

/**
 * The one overdue rule (PlanLedgerService::compute): payments fill the oldest
 * unpaid contribution first, overdue is measured from the oldest unpaid due
 * date, 60 days of grace, then Lapsed, and reinstatement.
 *
 * compute() takes no database, so these run with plain PHPUnit. Grace period,
 * reinstatement fee and forfeit setting are passed in explicitly so a change
 * in Config\CareSync never changes what these tests mean.
 */
final class PlanLedgerServiceTest extends TestCase
{
    private const FEE = 240.0;

    protected function setUp(): void
    {
        helper('caresync');
    }

    private function plan(array $extra = []): array
    {
        return $extra + ['monthly_fee' => self::FEE, 'anchor' => '2026-01-01'];
    }

    /** @return list<array{date:string, amount:float}> */
    private function pay(string ...$datesAndAmounts): array
    {
        $rows = [];
        foreach ($datesAndAmounts as $item) {
            [$date, $amount] = explode('=', $item);
            $rows[] = ['date' => $date, 'amount' => (float) $amount];
        }

        return $rows;
    }

    private function compute(array $plan, array $payments, string $today, array $opts = []): array
    {
        return PlanLedgerService::compute($plan, $payments, $today, $opts + ['grace' => 60, 'fee' => 0.0, 'forfeit_after' => null, 'lapsed_can_claim' => false]);
    }

    public function testPaidOnTimeIsActiveWithNothingOverdue(): void
    {
        $s = $this->compute($this->plan(), $this->pay('2026-01-01=240', '2026-02-01=240', '2026-03-01=240'), '2026-03-15');

        $this->assertSame('active', $s['lifecycle']);
        $this->assertSame(3, $s['months_paid']);
        $this->assertSame(0, $s['overdue_count']);
        $this->assertSame(0.0, $s['overdue_amount']);
        $this->assertNull($s['grace_days_left']);
    }

    public function testSkippedFebruaryThenOnePaymentInMarchCoversFebruaryAndLeavesMarchOverdue(): void
    {
        // Jan paid on time, Feb skipped, ONE month paid on Mar 5.
        $s = $this->compute($this->plan(), $this->pay('2026-01-01=240', '2026-03-05=240'), '2026-03-20');

        $this->assertSame(2, $s['months_paid'], 'the March payment fills February');
        $this->assertSame(1, $s['overdue_count']);
        $this->assertSame(['2026-03-01'], $s['overdue_dues'], 'March is now the unpaid one');
        $this->assertSame('2026-03-01', $s['oldest_unpaid_due']);
        $this->assertSame('grace', $s['lifecycle']);
        $this->assertSame(60 - 19, $s['grace_days_left'], 'grace counts from the March due date');
    }

    public function testTwoMonthsMissedButBothPaidOnDay59StaysActive(): void
    {
        // Jan paid. Feb (due 2026-02-01) and Mar (due 2026-03-01) missed. Day 59 after Feb 1 = Apr 1.
        $before = $this->compute($this->plan(), $this->pay('2026-01-01=240'), '2026-04-01');
        $this->assertSame('grace', $before['lifecycle']);
        $this->assertSame(59, $before['days_overdue']);
        $this->assertSame(1, $before['grace_days_left']);

        $after = $this->compute($this->plan(), $this->pay('2026-01-01=240', '2026-04-01=480'), '2026-04-01');
        $this->assertSame('active', $after['lifecycle']);
        $this->assertSame(0, $after['overdue_count']);
        $this->assertSame(3, $after['months_paid']);
    }

    public function testNothingPaidByDay61IsLapsedAndDay60IsStillGrace(): void
    {
        $day60 = $this->compute($this->plan(), $this->pay('2026-01-01=240'), '2026-04-02');
        $this->assertSame(60, $day60['days_overdue']);
        $this->assertSame('grace', $day60['lifecycle']);
        $this->assertSame(0, $day60['grace_days_left']);

        $day61 = $this->compute($this->plan(), $this->pay('2026-01-01=240'), '2026-04-03');
        $this->assertSame(61, $day61['days_overdue']);
        $this->assertSame('lapsed', $day61['lifecycle']);
        $this->assertSame('2026-04-03', $day61['lapsed_at']);
        $this->assertSame(1, $day61['months_paid'], 'nothing is erased on lapse');
        $this->assertFalse($day61['service_access']['allowed']);
    }

    public function testLapsedPlanIsReinstatedOnlyWhenOverdueAndFeeArePaid(): void
    {
        $opts = ['fee' => 100.0];
        $lapsedPlan = $this->plan(['lapsed_at' => '2026-04-03']);

        // Day 61: Feb, Mar, Apr are overdue (3 x 240). Pays the overdue but NOT the fee yet.
        $paidOverdue = $this->compute($lapsedPlan + ['fee_paid_since' => 0.0], $this->pay('2026-01-01=240', '2026-04-05=720'), '2026-04-05', $opts);
        $this->assertSame('lapsed', $paidOverdue['lifecycle'], 'overdue paid but fee still owed');
        $this->assertFalse($paidOverdue['reinstated']);
        $this->assertSame(100.0, $paidOverdue['reinstatement']['fee_remaining']);

        // Fee paid too -> back to Active.
        $reinstated = $this->compute($lapsedPlan + ['fee_paid_since' => 100.0], $this->pay('2026-01-01=240', '2026-04-05=720'), '2026-04-05', $opts);
        $this->assertSame('active', $reinstated['lifecycle']);
        $this->assertTrue($reinstated['reinstated']);
        $this->assertNull($reinstated['lapsed_at']);
        $this->assertSame(4, $reinstated['months_paid']);
    }

    public function testReinstatementAmountIsOverduePlusFee(): void
    {
        $s = $this->compute($this->plan(['lapsed_at' => '2026-04-03', 'fee_paid_since' => 0.0]), $this->pay('2026-01-01=240'), '2026-04-10', ['fee' => 150.0]);

        $this->assertSame('lapsed', $s['lifecycle']);
        $this->assertSame(720.0, $s['reinstatement']['overdue_amount']);
        $this->assertSame(150.0, $s['reinstatement']['fee_remaining']);
        $this->assertSame(870.0, $s['reinstatement']['total']);
    }

    public function testPartialPaymentOfOverdueWhileLapsedDoesNotReinstate(): void
    {
        // Pays 2 of the 3 overdue months after the plan lapsed: oldest unpaid moves forward, but it stays on hold.
        $s = $this->compute($this->plan(['lapsed_at' => '2026-04-03', 'fee_paid_since' => 0.0]), $this->pay('2026-01-01=240', '2026-04-05=480'), '2026-04-05');

        $this->assertSame('lapsed', $s['lifecycle']);
        $this->assertSame(1, $s['overdue_count']);
    }

    public function testPaidThreeMonthsAheadCountsAsAdvance(): void
    {
        $s = $this->compute($this->plan(), $this->pay('2026-01-01=960'), '2026-01-10'); // Jan + 3 ahead

        $this->assertSame(4, $s['months_paid']);
        $this->assertSame(3, $s['advance']);
        $this->assertSame('active', $s['lifecycle']);
        $this->assertSame(0, $s['overdue_count']);
    }

    public function testPartialPaymentIsNotCountedUntilTheFullAmountIsReached(): void
    {
        $partial = $this->compute($this->plan(), $this->pay('2026-01-02=100'), '2026-01-20');
        $this->assertSame(0, $partial['months_paid']);
    }

    public function testPartialPaymentLeavesTheContributionOverdueUntilCompleted(): void
    {
        $partial = $this->compute($this->plan(), $this->pay('2026-01-02=100'), '2026-01-20');
        // Jan 1 is before Jan 20 and not fully paid -> overdue (1 contribution, 140 left).
        $this->assertSame(1, $partial['overdue_count']);
        $this->assertSame(140.0, $partial['overdue_amount']);
        $this->assertSame('grace', $partial['lifecycle']);

        $complete = $this->compute($this->plan(), $this->pay('2026-01-02=100', '2026-01-15=140'), '2026-01-20');
        $this->assertSame(1, $complete['months_paid']);
        $this->assertSame(0, $complete['overdue_count']);
        $this->assertSame('active', $complete['lifecycle']);
    }

    public function testMoneyIsPouredInPaymentDateOrderRegardlessOfInputOrder(): void
    {
        $a = $this->compute($this->plan(), $this->pay('2026-03-01=240', '2026-01-01=240'), '2026-03-15');
        $b = $this->compute($this->plan(), $this->pay('2026-01-01=240', '2026-03-01=240'), '2026-03-15');

        $this->assertSame($a['months_paid'], $b['months_paid']);
        $this->assertSame($a['overdue_dues'], $b['overdue_dues']);
    }

    public function testForfeitOnlyHappensWhenConfiguredAndNeverByDefault(): void
    {
        $plan = $this->plan(['lapsed_at' => '2026-04-03']);
        $payments = $this->pay('2026-01-01=240');

        $never = $this->compute($plan, $payments, '2027-04-03');
        $this->assertSame('lapsed', $never['lifecycle'], 'null = never forfeit automatically');
        $this->assertSame(1, $never['months_paid'], 'history is kept');

        $after30 = $this->compute($plan, $payments, '2026-05-03', ['forfeit_after' => 30]);
        $this->assertSame('forfeited', $after30['lifecycle']);
        $this->assertFalse($after30['service_access']['allowed']);

        $before30 = $this->compute($plan, $payments, '2026-05-02', ['forfeit_after' => 30]);
        $this->assertSame('lapsed', $before30['lifecycle']);
    }

    public function testLapsedPlanMayClaimOnlyWhenConfiguredToAndForfeitedNever(): void
    {
        $plan = $this->plan(['lapsed_at' => '2026-04-03']);
        $payments = $this->pay('2026-01-01=240');

        $this->assertFalse($this->compute($plan, $payments, '2026-04-10')['service_access']['allowed']);
        $this->assertTrue($this->compute($plan, $payments, '2026-04-10', ['lapsed_can_claim' => true])['service_access']['allowed']);
        $this->assertFalse($this->compute($plan, $payments, '2026-05-10', ['forfeit_after' => 5, 'lapsed_can_claim' => true])['service_access']['allowed']);
        $this->assertNotEmpty($this->compute($plan, $payments, '2026-04-10')['service_access']['reason'], 'the client is told why');
    }

    public function testGracePlanCanStillRequestServices(): void
    {
        $s = $this->compute($this->plan(), $this->pay('2026-01-01=240'), '2026-02-20');

        $this->assertSame('grace', $s['lifecycle']);
        $this->assertTrue($s['service_access']['allowed']);
    }

    public function testFullyPaidAndMonthsLeft(): void
    {
        $everything = [['date' => '2026-01-01', 'amount' => 14500.0]];
        $s = $this->compute($this->plan(), $everything, '2026-01-02');

        $this->assertSame('fully_paid', $s['lifecycle']);
        $this->assertSame(61, $s['months_paid']);
        $this->assertSame(0, $s['months_left']);
        $this->assertSame(0, $s['overdue_count']);
    }

    public function testMonthsLeftIsContractTotalMinusMonthsPaid(): void
    {
        $s = $this->compute($this->plan(), $this->pay('2026-01-01=240', '2026-02-01=240'), '2026-02-02');

        $this->assertSame(61, $s['total_contributions']);
        $this->assertSame(59, $s['months_left']);
    }

    public function testDueDatesFollowTheStartDateDayWithMonthEndClamping(): void
    {
        $s = $this->compute($this->plan(['anchor' => '2026-01-31']), [], '2026-01-01');

        $this->assertSame('2026-01-31', $s['contributions'][0]['due']);
        $this->assertSame('2026-02-28', $s['contributions'][1]['due']);
        $this->assertSame('2026-03-31', $s['contributions'][2]['due']);
    }
}
