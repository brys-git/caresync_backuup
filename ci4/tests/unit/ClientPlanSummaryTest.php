<?php

namespace Tests\Unit;

use App\Services\ClientDetailService;
use App\Services\ClientPlanSummary;
use App\Services\PlanLedgerService;
use PHPUnit\Framework\TestCase;

/**
 * The numbers on the Client details page (ClientPlanSummary over the ledger).
 * No database: the ledger's compute() is fed payments directly, exactly as
 * the service feeds it verified payments and verified past records.
 */
final class ClientPlanSummaryTest extends TestCase
{
    private const FEE = 240.0;

    protected function setUp(): void
    {
        helper('caresync');
    }

    /** @param list<array{date:string, amount:float}> $payments */
    private function summary(array $payments, string $today, array $plan = [], array $opts = []): array
    {
        $ledger = PlanLedgerService::compute(
            $plan + ['monthly_fee' => self::FEE, 'anchor' => '2026-01-01'],
            $payments,
            $today,
            $opts + ['grace' => 60, 'fee' => 0.0, 'forfeit_after' => null, 'lapsed_can_claim' => false]
        );

        return ClientPlanSummary::fromLedger($ledger, $today);
    }

    /** @return list<array{date:string, amount:float}> n monthly payments starting January 2026 */
    private function monthly(int $n, float $amount = self::FEE): array
    {
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = ['date' => PlanLedgerService::addMonths('2026-01-01', $i), 'amount' => $amount];
        }

        return $rows;
    }

    public function testContractFigures(): void
    {
        $s = $this->summary([], '2026-01-01');

        $this->assertSame(14500.0, $s['contract_amount']);
        $this->assertSame(61, $s['total_months']);           // 60 x 240 + the last 100
        $this->assertSame(240.0, $s['monthly_fee']);
        $this->assertSame(0, $s['months_paid']);
        $this->assertSame(14500.0, $s['remaining_balance']);
        $this->assertSame(0.0, $s['progress_pct']);
        $this->assertSame('2031-01-01', $s['expected_completion']);
    }

    public function testOnTime(): void
    {
        $s = $this->summary($this->monthly(3), '2026-03-15');

        $this->assertSame('active', $s['status']);
        $this->assertSame(3, $s['months_paid']);
        $this->assertSame(720.0, $s['total_paid']);
        $this->assertSame(58, $s['months_left']);
        $this->assertSame(13780.0, $s['remaining_balance']);
        $this->assertSame(0, $s['overdue_count']);
        $this->assertSame(0, $s['advance_count']);
        $this->assertSame('2026-04-01', $s['next_due_date']);
        $this->assertSame(240.0, $s['next_due_amount']);
        $this->assertSame(4.9, $s['progress_pct']);          // 3 / 61
    }

    public function testOverdueListsTheMonthsAndTheAmount(): void
    {
        // Paid January only; on 20 March, February and March are overdue.
        $s = $this->summary($this->monthly(1), '2026-03-20');

        $this->assertSame('grace', $s['status']);
        $this->assertSame(2, $s['overdue_count']);
        $this->assertSame(480.0, $s['overdue_amount']);
        $this->assertSame([2, 3], array_column($s['overdue_months'], 'number'));
        $this->assertSame(['2026-02-01', '2026-03-01'], array_column($s['overdue_months'], 'due'));
        $this->assertSame('2026-02-01', $s['next_due_date']);   // the oldest unpaid
        $this->assertSame(['paid', 'overdue', 'overdue', 'upcoming'], array_slice(array_column($s['grid'], 'state'), 0, 4));
        $this->assertSame(0, $s['advance_count']);
    }

    public function testPaidInAdvance(): void
    {
        // Six payments by 1 Jan: Jan is current, Feb-Jun are paid ahead.
        $payments = array_fill(0, 6, ['date' => '2026-01-01', 'amount' => self::FEE]);
        $s = $this->summary($payments, '2026-01-10');

        $this->assertSame('active', $s['status']);
        $this->assertSame(6, $s['months_paid']);
        $this->assertSame(5, $s['advance_count']);
        $this->assertSame(1200.0, $s['advance_amount']);
        $this->assertSame(0, $s['overdue_count']);
        $this->assertSame('2026-07-01', $s['next_due_date']);
        $this->assertSame(['paid', 'advance', 'advance', 'advance', 'advance', 'advance', 'upcoming'], array_slice(array_column($s['grid'], 'state'), 0, 7));
    }

    public function testPartialPaymentCountsTowardTheMonthButIsNotPaid(): void
    {
        $s = $this->summary([['date' => '2026-01-01', 'amount' => 240.0], ['date' => '2026-02-01', 'amount' => 100.0]], '2026-02-10');

        $this->assertSame(1, $s['months_paid']);                // February is not paid yet
        $this->assertSame(340.0, $s['total_paid']);             // but the 100 is real money
        $this->assertSame(14160.0, $s['remaining_balance']);
        $this->assertCount(1, $s['partial_months']);
        $this->assertSame(2, $s['partial_months'][0]['number']);
        $this->assertSame(100.0, $s['partial_months'][0]['paid']);
        $this->assertSame(140.0, $s['partial_months'][0]['remaining']);
        $this->assertSame('partial', $s['grid'][1]['state']);
        $this->assertSame(1, $s['overdue_count']);              // due 1 Feb, still short
        $this->assertSame(140.0, $s['overdue_amount']);
        $this->assertSame(140.0, $s['next_due_amount']);
    }

    public function testTheTopUpCompletesThePartialMonth(): void
    {
        $s = $this->summary([['date' => '2026-01-01', 'amount' => 240.0], ['date' => '2026-02-01', 'amount' => 100.0], ['date' => '2026-02-15', 'amount' => 140.0]], '2026-02-20');

        $this->assertSame(2, $s['months_paid']);
        $this->assertSame([], $s['partial_months']);
        $this->assertSame(0, $s['overdue_count']);
    }

    public function testFullyPaid(): void
    {
        $payments = [];
        for ($i = 0; $i < 60; $i++) {
            $payments[] = ['date' => PlanLedgerService::addMonths('2026-01-01', $i), 'amount' => self::FEE];
        }
        $payments[] = ['date' => '2030-12-15', 'amount' => 100.0];
        $s = $this->summary($payments, '2031-02-01');

        $this->assertSame('fully_paid', $s['status']);
        $this->assertSame(61, $s['months_paid']);
        $this->assertSame(0, $s['months_left']);
        $this->assertSame(0.0, $s['remaining_balance']);
        $this->assertSame(100.0, $s['progress_pct']);
        $this->assertSame(14500.0, $s['total_paid']);
        $this->assertNull($s['next_due_date']);
        $this->assertSame(0, $s['overdue_count']);
    }

    public function testForfeitedKeepsItsHistory(): void
    {
        // Two payments, then silence. With forfeiture switched on (90 days after lapse) the plan is forfeited.
        $s = $this->summary($this->monthly(2), '2027-06-01', ['stored_lifecycle' => 'forfeited', 'lapsed_at' => '2026-05-01']);

        $this->assertSame('forfeited', $s['status']);
        $this->assertSame('Forfeited', $s['status_label']);
        $this->assertSame(2, $s['months_paid']);                // nothing erased
        $this->assertSame(480.0, $s['total_paid']);
        $this->assertFalse($s['service_access']['allowed']);
        $this->assertGreaterThan(0, $s['overdue_count']);
    }

    public function testLapsedStatusAndTheMonthGrid(): void
    {
        $s = $this->summary($this->monthly(1), '2026-06-01');   // oldest unpaid 1 Feb: 120 days overdue

        $this->assertSame('lapsed', $s['status']);
        $this->assertSame(4, $s['overdue_count']);              // Feb, Mar, Apr, May
        $this->assertSame(960.0, $s['overdue_amount']);
        $this->assertCount(61, $s['grid']);
    }

    public function testNotStartedPlanShowsNothingOverdue(): void
    {
        $ledger = PlanLedgerService::compute(['monthly_fee' => self::FEE, 'anchor' => '2025-01-01'], [], '2026-06-01', ['grace' => 60, 'fee' => 0.0, 'forfeit_after' => null, 'lapsed_can_claim' => false]);
        $ledger['lifecycle'] = PlanLedgerService::NOT_STARTED;
        $ledger['overdue_count'] = 0;
        $ledger['overdue_amount'] = 0.0;
        $s = ClientPlanSummary::fromLedger($ledger, '2026-06-01');

        $this->assertSame('Not started', $s['status_label']);
        $this->assertSame([], $s['overdue_months']);
        $this->assertNotContains('overdue', array_column($s['grid'], 'state'));
    }

    public function testPaymentFiltersAndTotalsLeavePendingAndRejectedOut(): void
    {
        $rows = [
            ['payment_id' => 3, 'date' => '2026-03-01', 'amount' => 240.0, 'or_number' => 'OR-3', 'method' => 'gcash', 'status' => 'pending'],
            ['payment_id' => 2, 'date' => '2026-02-01', 'amount' => 240.0, 'or_number' => 'OR-2', 'method' => 'branch', 'status' => 'verified'],
            ['payment_id' => 1, 'date' => '2026-01-01', 'amount' => 240.0, 'or_number' => 'OR-1', 'method' => 'branch', 'status' => 'rejected'],
        ];
        $service = new ClientDetailService();

        $all = $service->filterPayments($rows, []);
        $this->assertSame(3, $all['count']);
        $this->assertSame(240.0, $all['totals']['verified']);
        $this->assertSame(240.0, $all['totals']['pending']);
        $this->assertSame(240.0, $all['totals']['rejected']);

        $this->assertSame(1, $service->filterPayments($rows, ['status' => 'pending'])['count']);
        $this->assertSame(2, $service->filterPayments($rows, ['method' => 'branch'])['count']);
        $this->assertSame(1, $service->filterPayments($rows, ['or_no' => 'or-2'])['count']);
        $this->assertSame(2, $service->filterPayments($rows, ['date_from' => '2026-02-01'])['count']);
        $this->assertSame(1, $service->filterPayments($rows, ['date_from' => '2026-02-01', 'date_to' => '2026-02-28'])['count']);
    }

    public function testMemberFor(): void
    {
        $this->assertSame('Member for 2 years 3 months', ClientDetailService::memberFor('2024-01-15', '2026-04-20'));
        $this->assertSame('Member for 1 year', ClientDetailService::memberFor('2025-04-20', '2026-04-20'));
        $this->assertSame('Member for 5 months', ClientDetailService::memberFor('2025-11-01', '2026-04-20'));
        $this->assertSame('Member for less than a month', ClientDetailService::memberFor('2026-04-10', '2026-04-20'));
    }
}
