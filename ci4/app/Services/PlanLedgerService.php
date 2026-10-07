<?php

namespace App\Services;

use Config\CareSync;

/**
 * The ONE place that decides how many months a plan has paid, what is
 * overdue, and whether the plan is Active, in its grace period, Lapsed,
 * Forfeited or Fully paid. Every screen (client details, the client's own
 * dashboard, Existing member records, reports, reminders) and the daily
 * check read their numbers from here - there is no second rule.
 *
 * HOW PAYMENTS ARE COUNTED
 *  - A plan has N contributions (the contract target / monthly fee, rounded
 *    up; the last one carries the remainder). Contribution 1 is due on the
 *    membership start date, then one more each month.
 *  - Money fills the OLDEST unpaid contribution first, in payment-date
 *    order. A skipped month leaves no hole: the next payment covers it.
 *  - A partial payment fills a contribution partly; it counts as paid only
 *    when the full amount is reached.
 *  - Live payments (paid/verified) and verified past records (legacy) are
 *    poured in together the same way. Pending and rejected payments are
 *    never counted.
 *
 * STATUS (from the due date of the oldest unpaid contribution, D)
 *  - active      nothing overdue
 *  - grace       something overdue, and today is within gracePeriodDays of D
 *  - lapsed      past the grace period (plan on hold, nothing is erased)
 *  - forfeited   only when forfeitAfterLapseDays is set and reached
 *  - fully_paid  every contribution paid
 *  A lapsed plan comes back to active only once everything overdue AND the
 *  reinstatement fee are paid (reinstatement).
 */
class PlanLedgerService
{
    public const NOT_STARTED = 'not_started';

    // =====================================================================
    //  Pure calculation (no database) - this is what the tests exercise
    // =====================================================================

    /**
     * @param array{monthly_fee:float|string, anchor:string, lapsed_at?:?string, stored_lifecycle?:?string, fee_paid_since?:callable|float|null} $plan
     * @param list<array{date:string, amount:float|string}>                                                  $payments verified money, any order
     * @param array{grace?:int, fee?:float, forfeit_after?:?int, target?:float, lapsed_can_claim?:bool}      $opts
     *
     * @return array<string,mixed>
     */
    public static function compute(array $plan, array $payments, string $today, array $opts = []): array
    {
        $config = config(CareSync::class);
        $grace        = (int) ($opts['grace'] ?? $config->gracePeriodDays);
        $reinstateFee = (float) ($opts['fee'] ?? $config->reinstatementFee);
        $forfeitAfter = array_key_exists('forfeit_after', $opts) ? $opts['forfeit_after'] : $config->forfeitAfterLapseDays;
        $lapsedClaims = (bool) ($opts['lapsed_can_claim'] ?? $config->lapsedCanClaimServices);
        $target       = (float) ($opts['target'] ?? MembershipService::TOTAL_CONTRIBUTION);

        $monthlyFee = (float) $plan['monthly_fee'];
        if ($monthlyFee <= 0) {
            $monthlyFee = MembershipService::MONTHLY_FEE;
        }

        $total = max(1, (int) ceil($target / $monthlyFee));
        $anchor = (string) $plan['anchor'];

        // The contributions and what each one is worth.
        $contributions = [];
        for ($n = 1; $n <= $total; $n++) {
            $amount = $n < $total ? $monthlyFee : round($target - $monthlyFee * ($total - 1), 2);
            $contributions[] = [
                'number'    => $n,
                'due'       => self::addMonths($anchor, $n - 1),
                'amount'    => round($amount, 2),
                'paid'      => 0.0,
                'remaining' => round($amount, 2),
                'is_paid'   => false,
            ];
        }

        // Pour the money in, oldest payment into the oldest unpaid contribution.
        usort($payments, static fn (array $a, array $b): int => strcmp((string) $a['date'], (string) $b['date']));
        $pointer = 0;
        $totalPaid = 0.0;
        foreach ($payments as $payment) {
            $money = round((float) $payment['amount'], 2);
            if ($money <= 0) {
                continue;
            }
            $totalPaid += $money;

            while ($money > 0.004 && $pointer < $total) {
                $room = round($contributions[$pointer]['amount'] - $contributions[$pointer]['paid'], 2);
                $put = min($room, $money);
                $contributions[$pointer]['paid'] = round($contributions[$pointer]['paid'] + $put, 2);
                $money = round($money - $put, 2);
                if ($contributions[$pointer]['paid'] >= $contributions[$pointer]['amount'] - 0.004) {
                    $pointer++;
                }
            }
        }

        $monthsPaid = 0;
        $overdue = [];
        $overdueAmount = 0.0;
        $advance = 0;
        $oldestUnpaid = null;

        foreach ($contributions as &$c) {
            $c['remaining'] = max(0.0, round($c['amount'] - $c['paid'], 2));
            $c['is_paid']   = $c['remaining'] <= 0.004;

            if ($c['is_paid']) {
                $monthsPaid++;
                if (strcmp($c['due'], $today) > 0) {
                    $advance++;
                }
                continue;
            }

            $oldestUnpaid ??= $c['due'];

            if (strcmp($c['due'], $today) < 0) {
                $overdue[] = $c;
                $overdueAmount += $c['remaining'];
            }
        }
        unset($c);
        $overdueAmount = round($overdueAmount, 2);

        // ---- status ---------------------------------------------------
        $daysOverdue = $overdue !== [] ? self::daysBetween((string) $oldestUnpaid, $today) : 0;
        $graceLeft = null;

        if ($monthsPaid >= $total) {
            $lifecycle = 'fully_paid';
        } elseif ($overdue === []) {
            $lifecycle = 'active';
        } elseif ($daysOverdue <= $grace) {
            $lifecycle = 'grace';
            $graceLeft = $grace - $daysOverdue;
        } else {
            $lifecycle = 'lapsed';
        }

        $storedLapsedAt = ! empty($plan['lapsed_at']) ? (string) $plan['lapsed_at'] : null;
        $lapsedAt = null;
        $reinstated = false;
        $feeRemaining = 0.0;

        if (($plan['stored_lifecycle'] ?? null) === 'forfeited') {
            // Sticky once decided: nothing is paid back into a forfeited plan automatically.
            $lifecycle = 'forfeited';
            $lapsedAt = $storedLapsedAt;
        } else {
            if ($lifecycle === 'lapsed') {
                $lapsedAt = $storedLapsedAt ?? self::addDays((string) $oldestUnpaid, $grace + 1);
            } elseif ($storedLapsedAt !== null) {
                // The plan was on hold. It only returns once everything overdue is paid
                // AND the reinstatement fee is covered.
                $lapsedAt = $storedLapsedAt;
                $feePaid = (float) ($plan['fee_paid_since'] ?? 0);
                $feeRemaining = max(0.0, round($reinstateFee - $feePaid, 2));

                if ($overdue === [] && $feeRemaining <= 0.004) {
                    $reinstated = true;
                    $lapsedAt = null;
                } else {
                    $lifecycle = 'lapsed';
                    $graceLeft = null;
                }
            }

            if ($lifecycle === 'lapsed' && $forfeitAfter !== null && $lapsedAt !== null
                && self::daysBetween($lapsedAt, $today) >= (int) $forfeitAfter) {
                $lifecycle = 'forfeited';
            }
        }

        if ($lifecycle === 'lapsed' && $feeRemaining === 0.0) {
            $feePaid = (float) ($plan['fee_paid_since'] ?? 0);
            $feeRemaining = max(0.0, round($reinstateFee - $feePaid, 2));
        }

        $graceEnds = $oldestUnpaid !== null && $overdue !== [] ? self::addDays((string) $oldestUnpaid, $grace) : null;

        // ---- what the plan allows ------------------------------------------
        $reinstateTotal = round($overdueAmount + $feeRemaining, 2);
        $access = match ($lifecycle) {
            'active', 'grace', 'fully_paid' => ['allowed' => true, 'reason' => null],
            'lapsed' => $lapsedClaims
                ? ['allowed' => true, 'reason' => null]
                : ['allowed' => false, 'reason' => 'Your plan is on hold because contributions are more than ' . $grace . ' days overdue. Pay ' . cs_peso($reinstateTotal) . ' at your branch to reinstate it, then you can request services again.'],
            'forfeited' => ['allowed' => false, 'reason' => 'Your plan has been forfeited, so services and claims are no longer available on it. Please contact your branch.'],
            default => ['allowed' => false, 'reason' => 'Your plan is not active yet.'],
        };

        return [
            'lifecycle'          => $lifecycle,
            'total_contributions' => $total,
            'months_paid'        => $monthsPaid,
            'months_left'        => $total - $monthsPaid,
            'advance'            => $advance,
            'overdue_count'      => count($overdue),
            'overdue_amount'     => $overdueAmount,
            'overdue_dues'       => array_map(static fn (array $c) => $c['due'], $overdue),
            'oldest_unpaid_due'  => $oldestUnpaid,
            'days_overdue'       => $daysOverdue,
            'grace_days_left'    => $graceLeft,
            'grace_days'         => $grace,
            'grace_ends'         => $graceEnds,
            'lapsed_at'          => $lapsedAt,
            'reinstated'         => $reinstated,
            'total_paid'         => round($totalPaid, 2),
            'contributions'      => $contributions,
            'service_access'     => $access,
            'reinstatement'      => [
                'applies'        => $lifecycle === 'lapsed',
                'overdue_amount' => $overdueAmount,
                'fee'            => $reinstateFee,
                'fee_remaining'  => $feeRemaining,
                'total'          => $reinstateTotal,
            ],
            'paid_through'       => $oldestUnpaid !== null ? self::addDays($oldestUnpaid, -1) : self::addDays(self::addMonths($anchor, $total - 1), 30),
        ];
    }

    public static function addMonths(string $date, int $months): string
    {
        $d = new \DateTimeImmutable($date);
        $day = (int) $d->format('j');
        $first = $d->modify('first day of this month')->modify(($months >= 0 ? '+' : '') . $months . ' months');

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, (int) $first->format('t')))->format('Y-m-d');
    }

    public static function addDays(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }

    public static function daysBetween(string $from, string $to): int
    {
        return (int) (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->format('%r%a');
    }

    // =====================================================================
    //  Database side
    // =====================================================================

    /** Cycle anchor: the cycle-start marker when there is one, else the membership start date. */
    private function anchorFor(array $plan): string
    {
        $marker = (string) ($plan['contribution_cycle_started_at'] ?? '');
        $source = $marker !== '' ? $marker : (string) ($plan['start_date'] ?? date('Y-m-d'));

        return date('Y-m-d', strtotime($source));
    }

    /**
     * Verified money for the plan's current cycle - same filters the old
     * months-paid count used (current cycle, on/after the cycle start).
     *
     * @return list<array{date:string, amount:float}>
     */
    private function paymentsFor(array $plan, string $anchor): array
    {
        $db = db_connect();
        $cycle = (int) ($plan['current_cycle_number'] ?? 1);

        $live = $db->table('payments')
            ->select('payment_date, amount')
            ->where('plan_id', (int) $plan['plan_id'])
            ->whereIn('status', ['paid', 'verified'])
            ->where('payment_type !=', 'reinstatement_fee')
            ->where('payment_date >=', $anchor)
            ->where('cycle_number', $cycle)
            ->get()
            ->getResultArray();

        $rows = array_map(static fn (array $r): array => ['date' => (string) $r['payment_date'], 'amount' => (float) $r['amount']], $live);

        if (LegacyRecordService::ready()) {
            $past = $db->table('legacy_payments')
                ->select('payment_date, amount')
                ->where('plan_id', (int) $plan['plan_id'])
                ->where('record_status', 'verified')
                ->where('cycle_number', $cycle)
                ->where('payment_date >=', $anchor)
                ->get()
                ->getResultArray();

            foreach ($past as $r) {
                $rows[] = ['date' => (string) $r['payment_date'], 'amount' => (float) $r['amount']];
            }
        }

        return $rows;
    }

    private function feePaidSince(int $planId, ?string $since): float
    {
        if ($since === null) {
            return 0.0;
        }

        return (float) (db_connect()->table('payments')
            ->selectSum('amount')
            ->where('plan_id', $planId)
            ->whereIn('status', ['paid', 'verified'])
            ->where('payment_type', 'reinstatement_fee')
            ->where('payment_date >=', $since)
            ->get()
            ->getRowArray()['amount'] ?? 0);
    }

    /** A plan is managed here once it has been activated or has verified money behind it. */
    private function isManaged(array $plan, array $payments): bool
    {
        if (($plan['status'] ?? '') === 'active' || ! empty($plan['lifecycle_status']) || $payments !== []) {
            return true;
        }

        return LegacyRecordService::ready()
            && db_connect()->table('legacy_plan_records')->where('plan_id', (int) $plan['plan_id'])->where('record_status', 'verified')->countAllResults() > 0;
    }

    /**
     * The summary for a plan right now (read only).
     *
     * @return array<string,mixed>|null null when the plan does not exist
     */
    public function summarize(int $planId, ?string $today = null): ?array
    {
        $plan = db_connect()->table('plans')->where('plan_id', $planId)->get()->getRowArray();
        if (! $plan) {
            return null;
        }

        $today ??= date('Y-m-d');
        $anchor = $this->anchorFor($plan);
        $payments = $this->paymentsFor($plan, $anchor);

        if (! $this->isManaged($plan, $payments)) {
            $summary = self::compute($this->planInput($plan, $anchor), [], $today);
            $summary['lifecycle'] = self::NOT_STARTED;
            $summary['overdue_count'] = 0;
            $summary['overdue_amount'] = 0.0;
            $summary['overdue_dues'] = [];
            $summary['grace_days_left'] = null;
            $summary['oldest_unpaid_due'] = null;
            $summary['reinstatement'] = ['applies' => false, 'overdue_amount' => 0.0, 'fee' => 0.0, 'fee_remaining' => 0.0, 'total' => 0.0];
            $summary['service_access'] = ['allowed' => false, 'reason' => 'Your plan is not active yet.'];
            $summary['plan_id'] = $planId;

            return $summary;
        }

        $summary = self::compute($this->planInput($plan, $anchor), $payments, $today);
        $summary['plan_id'] = $planId;

        return $summary;
    }

    private function planInput(array $plan, string $anchor): array
    {
        return [
            'monthly_fee'      => (float) ($plan['monthly_fee'] ?? 0),
            'anchor'           => $anchor,
            'lapsed_at'        => $plan['lapsed_at'] ?? null,
            'stored_lifecycle' => $plan['lifecycle_status'] ?? null,
            'fee_paid_since'   => $this->feePaidSince((int) $plan['plan_id'], $plan['lapsed_at'] ?? null),
        ];
    }

    /** Summary for a plan holder's most relevant plan (active first, then newest). */
    public function summarizeForPlanHolder(int $planHolderId, ?string $today = null): ?array
    {
        $plan = db_connect()->table('plans')
            ->select('plan_id')
            ->where('plan_holder_id', $planHolderId)
            ->orderBy("CASE WHEN status = 'active' THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy('plan_id', 'DESC')
            ->get()
            ->getRowArray();

        return $plan ? $this->summarize((int) $plan['plan_id'], $today) : null;
    }

    /**
     * Recompute a plan and write the derived columns the rest of the app
     * (SQL filters, collection list, old screens) still reads, so they can
     * never disagree with summarize(). History is never touched.
     *
     * @param bool $quiet true when this is being called because past records
     *                    were encoded: reminders for the overdue episode that
     *                    already exists are marked as sent so nobody is told
     *                    about history.
     *
     * @return array<string,mixed>|null
     */
    public function sync(int $planId, bool $quiet = false, ?int $actorUserId = null): ?array
    {
        $db = db_connect();
        $plan = $db->table('plans')->where('plan_id', $planId)->get()->getRowArray();
        if (! $plan) {
            return null;
        }

        $today = date('Y-m-d');
        $anchor = $this->anchorFor($plan);
        $payments = $this->paymentsFor($plan, $anchor);

        if (! $this->isManaged($plan, $payments)) {
            return $this->summarize($planId, $today);
        }

        $input = $this->planInput($plan, $anchor);
        if ($quiet) {
            // Past records are being (re)entered: the history is the authority.
            // While it is typed in piece by piece the plan can look lapsed for a
            // moment (plan details saved before the payments); that must not
            // stick. So derive the status fresh from the recorded payments.
            $input['lapsed_at'] = null;
            $input['stored_lifecycle'] = null;
            $input['fee_paid_since'] = 0.0;
        }

        $s = self::compute($input, $payments, $today);
        $s['plan_id'] = $planId;

        $update = [
            'months_paid'            => $s['months_paid'],
            'overdue_months'         => $s['overdue_count'],
            'next_due_date'          => $s['lifecycle'] === 'fully_paid' ? null : $s['oldest_unpaid_due'],
            'payment_coverage_until' => $s['months_paid'] > 0 ? $s['paid_through'] : null,
            'lifecycle_status'       => $s['lifecycle'],
            'lapsed_at'              => $s['lapsed_at'],
            'membership_state'       => match ($s['lifecycle']) {
                'lapsed', 'forfeited' => 'suspended',
                default => 'active',
            },
            'status'                 => $s['lifecycle'] === 'forfeited' ? 'inactive' : 'active',
        ];

        if ($s['reinstated']) {
            $update['reinstated_at'] = $today;
        }

        $db->table('plans')->where('plan_id', $planId)->update($update);

        if ($s['reinstated']) {
            $holder = $db->table('plan_holders')->select('user_id')->where('plan_holder_id', (int) $plan['plan_holder_id'])->get()->getRowArray();
            (new ActivityLogService())->log(
                $actorUserId ?: (int) (session('user_id') ?: ($holder['user_id'] ?? 0)),
                'updated',
                'plan',
                $planId,
                'Plan reinstated: overdue contributions and reinstatement fee are paid',
                null,
                ['lifecycle' => $s['lifecycle'], 'reinstated_at' => $today]
            );
        }

        if ($quiet) {
            $this->stampNotices($s, true);
        }

        return $s;
    }

    // =====================================================================
    //  Daily check + reminders
    // =====================================================================

    /**
     * Run once a day: refresh every plan, move plans between grace, lapsed
     * and forfeited, and send the reminders. Nothing is erased.
     *
     * @return array{checked:int, grace:int, lapsed:int, forfeited:int, notified:int}
     */
    public function runDailyCheck(): array
    {
        $result = ['checked' => 0, 'grace' => 0, 'lapsed' => 0, 'forfeited' => 0, 'notified' => 0];

        $plans = db_connect()->table('plans')->select('plan_id')->get()->getResultArray();

        foreach ($plans as $row) {
            $s = $this->sync((int) $row['plan_id']);
            if (! $s || $s['lifecycle'] === self::NOT_STARTED) {
                continue;
            }

            $result['checked']++;
            if (in_array($s['lifecycle'], ['grace', 'lapsed', 'forfeited'], true)) {
                $result[$s['lifecycle']]++;
            }

            $result['notified'] += $this->sendReminders($s);
        }

        return $result;
    }

    /** @return list<string> the reminder stages that currently apply, oldest first. */
    private function stagesFor(array $s): array
    {
        $stages = [];

        if ($s['lifecycle'] === 'grace') {
            $stages[] = 'overdue';
            if ($s['grace_days_left'] <= 30) {
                $stages[] = 'grace_30';
            }
            if ($s['grace_days_left'] <= 7) {
                $stages[] = 'grace_7';
            }
        } elseif ($s['lifecycle'] === 'lapsed') {
            $stages = ['overdue', 'grace_30', 'grace_7', 'lapsed'];
        }

        return $stages;
    }

    private function episodeKey(array $s, string $stage): ?string
    {
        if ($stage === 'lapsed') {
            return $s['lapsed_at'];
        }

        return $s['oldest_unpaid_due'];
    }

    /**
     * Record that the reminders for the episode already in progress were
     * "sent" (without sending), so past records never produce notifications.
     */
    private function stampNotices(array $s, bool $silent): void
    {
        foreach ($this->stagesFor($s) as $stage) {
            $episode = $this->episodeKey($s, $stage);
            if ($episode !== null) {
                $this->logNotice((int) $s['plan_id'], $episode, $stage, ! $silent);
            }
        }
    }

    private function logNotice(int $planId, string $episode, string $stage, bool $delivered): bool
    {
        $db = db_connect();
        $exists = $db->table('plan_notices')->where(['plan_id' => $planId, 'episode_date' => $episode, 'stage' => $stage])->countAllResults();
        if ($exists) {
            return false;
        }

        $db->table('plan_notices')->insert([
            'plan_id' => $planId, 'episode_date' => $episode, 'stage' => $stage,
            'delivered' => $delivered ? 1 : 0, 'sent_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /** Send the single most relevant reminder that has not gone out yet. */
    private function sendReminders(array $s): int
    {
        $due = [];
        foreach ($this->stagesFor($s) as $stage) {
            $episode = $this->episodeKey($s, $stage);
            if ($episode === null) {
                continue;
            }
            $sent = db_connect()->table('plan_notices')->where(['plan_id' => $s['plan_id'], 'episode_date' => $episode, 'stage' => $stage])->countAllResults();
            if (! $sent) {
                $due[] = [$stage, $episode];
            }
        }

        if ($due === []) {
            return 0;
        }

        // If several came due at once (a backlog), tell them about the latest
        // only and mark the earlier ones as passed.
        [$stage, $episode] = array_pop($due);
        foreach ($due as [$skipStage, $skipEpisode]) {
            $this->logNotice((int) $s['plan_id'], $skipEpisode, $skipStage, false);
        }
        $this->logNotice((int) $s['plan_id'], $episode, $stage, true);

        $holder = db_connect()->table('plans p')
            ->select('ph.user_id')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->where('p.plan_id', $s['plan_id'])
            ->get()
            ->getRowArray();

        if (! $holder) {
            return 0;
        }

        // planId is deliberately NOT passed: that flag makes the notification
        // list rewrite the text from the plan's current due date (old behaviour).
        (new NotificationService())->notify((int) $holder['user_id'], $this->reminderText($stage, $s), 'overdue', true, true);

        return 1;
    }

    public function reminderText(string $stage, array $s): string
    {
        $date = static fn (?string $d): string => $d ? date('F j, Y', strtotime($d)) : '';
        $amount = cs_peso($s['overdue_amount']);

        return match ($stage) {
            'overdue' => 'Your contribution due on ' . $date($s['oldest_unpaid_due']) . ' is overdue. Pay the ' . $amount . ' you owe within ' . $s['grace_days'] . ' days of that date (by ' . $date($s['grace_ends']) . ') to keep your plan active.',
            'grace_30', 'grace_7' => 'Reminder: you have ' . (int) $s['grace_days_left'] . ' day(s) left (until ' . $date($s['grace_ends']) . ') to pay your overdue ' . $amount . ' and keep your plan active. After that your plan is put on hold.',
            'lapsed' => 'Your plan is now on hold (lapsed) because contributions were not paid within ' . $s['grace_days'] . ' days. Nothing has been erased. To reinstate it, pay ' . cs_peso($s['reinstatement']['total']) . ' (overdue contributions' . ($s['reinstatement']['fee'] > 0 ? ' plus the reinstatement fee' : '') . ') at your branch.',
            default => 'Please check your plan with your branch.',
        };
    }
}
