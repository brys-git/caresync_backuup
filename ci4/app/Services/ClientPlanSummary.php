<?php

namespace App\Services;

/**
 * The numbers for a client's plan, in the shape every screen wants them.
 *
 * This is a thin layer over PlanLedgerService (which owns the overdue /
 * grace / lapse rule - see its header). It does no counting of its own: it
 * takes the ledger's contributions and answers "how much is the contract,
 * what is left, what is overdue, what is paid ahead, which months are partly
 * paid, what is the month-by-month picture" - the same way for the staff
 * client details page (all three roles), the client's own dashboard and
 * payments page, reports and notifications.
 *
 * Rules (all inherited from the ledger):
 *  - only paid/verified payments and verified past records count; pending
 *    and rejected payments never do
 *  - money fills the oldest unpaid contribution first
 *  - a month is "paid" only when its full amount is reached; a part-paid
 *    month is reported separately as partial (and is still not paid, so it
 *    is overdue once its due date has passed)
 *  - overdue = contributions whose due date has passed and are not fully paid
 *  - advance = contributions fully paid whose due date is still ahead
 */
class ClientPlanSummary
{
    public const LABELS = [
        'active'      => 'Active',
        'grace'       => 'In grace period',
        'lapsed'      => 'Lapsed (on hold)',
        'forfeited'   => 'Forfeited',
        'fully_paid'  => 'Fully paid',
        PlanLedgerService::NOT_STARTED => 'Not started',
    ];

    public function forPlan(int $planId, ?string $today = null): ?array
    {
        $ledger = (new PlanLedgerService())->summarize($planId, $today);
        if ($ledger === null) {
            return null;
        }

        return self::fromLedger($ledger, $today ?? date('Y-m-d'));
    }

    /** The plan holder's most relevant plan (active first, then newest), or null when they have none. */
    public function forPlanHolder(int $planHolderId, ?string $today = null): ?array
    {
        $ledger = (new PlanLedgerService())->summarizeForPlanHolder($planHolderId, $today);

        return $ledger === null ? null : self::fromLedger($ledger, $today ?? date('Y-m-d'));
    }

    /**
     * Pure: ledger summary in, page numbers out (no database - the tests use this).
     *
     * @param array<string,mixed> $ledger PlanLedgerService::summarize()/compute() output
     *
     * @return array<string,mixed>
     */
    public static function fromLedger(array $ledger, string $today): array
    {
        $contributions = $ledger['contributions'] ?? [];
        $total         = (int) ($ledger['total_contributions'] ?? count($contributions));
        $started       = ($ledger['lifecycle'] ?? '') !== PlanLedgerService::NOT_STARTED;

        $contract    = 0.0;
        $grid        = [];
        $partial     = [];
        $advanceAmt  = 0.0;
        $nextDue     = null;
        $nextAmount  = null;
        $overdueMonths = [];

        foreach ($contributions as $c) {
            $contract += (float) $c['amount'];
            $due = (string) $c['due'];

            if ($c['is_paid']) {
                $isAdvance = $started && strcmp($due, $today) > 0;
                $state = $isAdvance ? 'advance' : 'paid';
                if ($isAdvance) {
                    $advanceAmt += (float) $c['amount'];
                }
            } else {
                $isPart = (float) $c['paid'] > 0.004;
                $isLate = $started && strcmp($due, $today) < 0;

                if ($isPart) {
                    $state = 'partial';
                    $partial[] = ['number' => (int) $c['number'], 'due' => $due, 'paid' => (float) $c['paid'], 'amount' => (float) $c['amount'], 'remaining' => (float) $c['remaining']];
                } else {
                    $state = $isLate ? 'overdue' : 'upcoming';
                }

                if ($isLate) {
                    $overdueMonths[] = ['number' => (int) $c['number'], 'due' => $due, 'remaining' => (float) $c['remaining']];
                }

                if ($nextDue === null) {
                    $nextDue    = $due;
                    $nextAmount = (float) $c['remaining'];
                }
            }

            $grid[] = [
                'number'    => (int) $c['number'],
                'due'       => $due,
                'amount'    => (float) $c['amount'],
                'paid'      => (float) $c['paid'],
                'remaining' => (float) $c['remaining'],
                'state'     => $state,
            ];
        }

        $contract   = round($contract, 2);
        $totalPaid  = (float) ($ledger['total_paid'] ?? 0);
        $monthsPaid = (int) ($ledger['months_paid'] ?? 0);
        $lifecycle  = (string) ($ledger['lifecycle'] ?? PlanLedgerService::NOT_STARTED);
        $lastDue    = $contributions !== [] ? (string) end($contributions)['due'] : null;

        return [
            'plan_id'            => $ledger['plan_id'] ?? null,
            'started'            => $started,
            'contract_amount'    => $contract,
            'monthly_fee'        => $contributions !== [] ? (float) $contributions[0]['amount'] : 0.0,
            'total_months'       => $total,
            'months_paid'        => $monthsPaid,
            'total_paid'         => round($totalPaid, 2),
            'months_left'        => max(0, $total - $monthsPaid),
            'remaining_balance'  => max(0.0, round($contract - $totalPaid, 2)),
            'progress_pct'       => $total > 0 ? round($monthsPaid / $total * 100, 1) : 0.0,

            'overdue_count'      => (int) ($ledger['overdue_count'] ?? 0),
            'overdue_amount'     => (float) ($ledger['overdue_amount'] ?? 0),
            'overdue_months'     => $overdueMonths,
            'advance_count'      => (int) ($ledger['advance'] ?? 0),
            'advance_amount'     => round($advanceAmt, 2),
            'partial_months'     => $partial,

            'next_due_date'      => $nextDue,
            'next_due_amount'    => $nextAmount,
            'expected_completion' => $lastDue,

            'status'             => $lifecycle,
            'status_label'       => self::LABELS[$lifecycle] ?? ucwords(str_replace('_', ' ', $lifecycle)),
            'days_overdue'       => (int) ($ledger['days_overdue'] ?? 0),
            'grace_days_left'    => $ledger['grace_days_left'] ?? null,
            'grace_ends'         => $ledger['grace_ends'] ?? null,
            'lapsed_at'          => $ledger['lapsed_at'] ?? null,
            'service_access'     => $ledger['service_access'] ?? ['allowed' => false, 'reason' => null],

            'grid'               => $grid,
            'ledger'             => $ledger,
        ];
    }
}
