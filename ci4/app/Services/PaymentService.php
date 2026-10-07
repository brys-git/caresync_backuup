<?php

namespace App\Services;

use App\Services\ApprovalService;
use App\Services\MembershipService;

class PaymentService
{
    public function getBranchPlanHolderChoices(int $branchId): array
    {
        if ($branchId <= 0) {
            return [];
        }

        $rows = db_connect()->table('plans p')
            ->select('ph.plan_holder_id, ph.unique_identifier, u.first_name, u.last_name, p.plan_id, p.status AS plan_status')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->where('ph.status', 'active')
            ->where('p.status', 'active')
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC')
            ->orderBy('p.plan_id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $name = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
            $memberId = trim((string) ($row['unique_identifier'] ?? ''));
            $planId = (int) ($row['plan_id'] ?? 0);

            $row['client_name'] = $name !== '' ? $name : 'N/A';
            $row['label'] = $row['client_name']
                . ($memberId !== '' ? ' (' . $memberId . ')' : '')
                . ($planId > 0 ? ' - Plan #' . $planId : ' - No Plan Yet');
        }
        unset($row);

        return $rows;
    }

    public function getBranchMonitoringPayments(int $branchId): array
    {
        if ($branchId <= 0) {
            return [];
        }

        return db_connect()->table('payments pay')
            ->select('pay.payment_id, pay.plan_id, pay.amount, pay.months_covered, pay.payment_date, pay.payment_method, pay.reference_number, pay.official_receipt_number, pay.remarks, pay.status, pay.branch_id, b.branch_name, p.monthly_fee, p.months_paid, p.remaining_balance, p.next_due_date, ph.plan_holder_id, ph.unique_identifier, u.first_name, u.last_name')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = pay.branch_id', 'left')
            ->where('pay.branch_id', $branchId)
            ->orderBy('pay.payment_date', 'DESC')
            ->orderBy('pay.payment_id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function getBranchPlanOptions(int $branchId): array
    {
        if ($branchId <= 0) {
            return [];
        }

        return db_connect()->table('plans p')
            ->select('p.plan_id, p.monthly_fee, p.months_paid, p.remaining_balance, p.status AS plan_status, ph.plan_holder_id, ph.unique_identifier, u.first_name, u.last_name')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->where('ph.status', 'active')
            ->where('p.status', 'active')
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getBranchPlans(int $branchId): array
    {
        $db = db_connect();
        $columns = array_map(static fn ($field) => $field->name, $db->getFieldData('plans'));
        $hasTotalPlanAmount = in_array('total_plan_amount', $columns, true);

        $rows = $db->table('plans p')
            ->select('p.plan_id, p.monthly_fee, p.months_paid, p.remaining_balance, p.status AS plan_status, ph.plan_holder_id, ph.branch_id, u.first_name, u.last_name')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $planId = (int) ($row['plan_id'] ?? 0);
            $monthlyFee = (float) ($row['monthly_fee'] ?? 0);
            $monthsPaid = (int) ($row['months_paid'] ?? 0);
            $remaining = (float) ($row['remaining_balance'] ?? 0);

            $totalAmount = $monthlyFee;
            if ($hasTotalPlanAmount && $planId > 0) {
                $rawTotal = (float) ($db->table('plans')
                    ->select('total_plan_amount')
                    ->where('plan_id', $planId)
                    ->get()
                    ->getRowArray()['total_plan_amount'] ?? 0);

                if ($rawTotal > 0) {
                    $totalAmount = $rawTotal;
                }
            }

            if ($totalAmount < $remaining) {
                $totalAmount = $remaining;
            }

            $paidAmount = max(0, round($totalAmount - $remaining, 2));
            if ($monthlyFee > 0 && $monthsPaid > 0 && $paidAmount <= 0) {
                $paidAmount = round($monthlyFee * $monthsPaid, 2);
                if ($totalAmount < $paidAmount) {
                    $totalAmount = $paidAmount;
                }
            }

            $row['client_name'] = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
            $row['total_amount'] = $totalAmount;
            $row['paid_amount'] = $paidAmount;
        }
        unset($row);

        return $rows;
    }

    public function getPaymentHistory(int $planId): array
    {
        return db_connect()->table('payments pay')
            ->select('pay.payment_id, pay.plan_id, pay.amount, pay.months_covered, pay.payment_date, pay.payment_method, pay.reference_number, pay.official_receipt_number, pay.remarks, pay.status, pay.created_at, rb.first_name AS receiver_first_name, rb.last_name AS receiver_last_name')
            ->join('users rb', 'rb.user_id = pay.received_by', 'left')
            ->where('pay.plan_id', $planId)
            // Panel brief, section 4: sort payment history ascending.
            ->orderBy('pay.payment_date', 'ASC')
            ->orderBy('pay.payment_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function recordPayment(array $data): int
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $plan = $this->getPlanWithBranch((int) $data['plan_id']);
            if (! $plan) {
                throw new \RuntimeException('Selected plan does not exist.');
            }

            $amount = (float) $data['amount'];
            $monthsCovered = max(1, (int) ($data['months_covered'] ?? 1));

            $saved = $db->table('payments')->insert([
                'plan_id' => (int) $data['plan_id'],
                'amount' => number_format($amount, 2, '.', ''),
                'months_covered' => $monthsCovered,
                'payment_date' => (string) $data['payment_date'],
                'payment_method' => (string) $data['payment_method'],
                'reference_number' => $this->nullable($data['reference_number'] ?? null),
                'official_receipt_number' => $this->nullable($data['official_receipt_number'] ?? null),
                'received_by' => (int) $data['received_by'],
                'branch_id' => (int) $plan['branch_id'],
                'remarks' => $this->nullable($data['remarks'] ?? null),
                'status' => (string) $data['status'],
            ]);

            if (! $saved) {
                throw new \RuntimeException('Failed to record payment.');
            }

            $paymentId = (int) $db->insertID();
            $this->recomputePlan((int) $data['plan_id']);
            // NEW STATUS: 'verified' (was 'paid')
            if ((string) ($data['status'] ?? '') === 'verified') {
                $this->updateNextDueDate((int) $data['plan_id'], $monthsCovered);
                (new MembershipService())->applyMembershipCoverage((int) $data['plan_id'], $monthsCovered);
                (new ApprovalService())->approveInitialPayment($paymentId);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to complete payment transaction.');
            }

            $db->transCommit();

            return $paymentId;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function updatePayment(int $paymentId, array $data): void
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $payment = $this->getPaymentById($paymentId);
            if (! $payment) {
                throw new \RuntimeException('Payment record was not found.');
            }

            $payload = [
                'amount' => number_format((float) $data['amount'], 2, '.', ''),
                'payment_date' => (string) $data['payment_date'],
                'payment_method' => (string) $data['payment_method'],
                'reference_number' => $this->nullable($data['reference_number'] ?? null),
                'official_receipt_number' => $this->nullable($data['official_receipt_number'] ?? null),
                'remarks' => $this->nullable($data['remarks'] ?? null),
                'status' => (string) $data['status'],
            ];

            if (isset($data['months_covered'])) {
                $payload['months_covered'] = max(1, (int) $data['months_covered']);
            }

            $updated = $db->table('payments')
                ->where('payment_id', $paymentId)
                ->update($payload);

            if (! $updated) {
                throw new \RuntimeException('Failed to update payment record.');
            }

            $this->recomputePlan((int) $payment['plan_id']);

            // NEW STATUS: 'verified' (was 'paid')
            if ((string) ($data['status'] ?? '') === 'verified') {
                $monthsCovered = max(1, (int) ($data['months_covered'] ?? (int) ($payment['months_covered'] ?? 1)));
                $this->updateNextDueDate((int) $payment['plan_id'], $monthsCovered);
                (new MembershipService())->applyMembershipCoverage((int) $payment['plan_id'], $monthsCovered);
                (new ApprovalService())->approveInitialPayment($paymentId);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to complete payment update transaction.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function recomputePlan(int $planId): void
    {
        $db = db_connect();

        $plan = $db->table('plans')
            ->select('plan_id, monthly_fee, remaining_balance')
            ->where('plan_id', $planId)
            ->get()
            ->getRowArray();

        if (! $plan) {
            throw new \RuntimeException('Plan record was not found for recomputation.');
        }

        $totalPaid = (float) ($db->table('payments')
            ->select('COALESCE(SUM(amount), 0) AS total_paid', false)
            ->where('plan_id', $planId)
            ->whereIn('status', ['paid', 'verified'])
            ->get()
            ->getRowArray()['total_paid'] ?? 0);

        $monthlyFee = (float) ($plan['monthly_fee'] ?? 0);
        $totalPlanAmount = $this->getTotalPlanAmount($plan);

        $remainingBalance = max(0, round($totalPlanAmount - $totalPaid, 2));
        // Client dashboard rebuild, Phase 0: months_paid now comes from
        // the single shared recalculation method (SUM of months_covered,
        // forfeiture-cycle-aware) rather than this method's own
        // floor(totalPaid / monthlyFee) - which could disagree with it if
        // a payment's amount and months_covered were ever entered
        // inconsistently.
        $monthsPaid = (new MembershipService())->recalculateMonthsPaid($planId);

        // A forfeited plan stays inactive (PlanLedgerService decided that).
        $forfeited = (string) ($db->table('plans')->select('lifecycle_status')->where('plan_id', $planId)->get()->getRowArray()['lifecycle_status'] ?? '') === 'forfeited';
        $status = ($monthsPaid > 0 && ! $forfeited) ? 'active' : 'inactive';

        $db->table('plans')
            ->where('plan_id', $planId)
            ->update([
                'remaining_balance' => number_format($remainingBalance, 2, '.', ''),
                'status' => $status,
                // A payment just came in: clear the old overdue-notice flag.
                // (Reminders are now tracked per overdue episode in
                // plan_notices by PlanLedgerService.)
                'overdue_notified_at' => null,
            ]);
    }

    public function getPlanWithBranch(int $planId): ?array
    {
        $row = db_connect()->table('plans p')
            ->select('p.plan_id, p.plan_holder_id, p.monthly_fee, p.remaining_balance, p.status, ph.branch_id')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->where('p.plan_id', $planId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function getTotalPaidForPlan(int $planId): float
    {
        if ($planId <= 0) {
            return 0.0;
        }

        // One answer for every screen: the ledger's total (verified payments of
        // the current contract plus verified past records; reinstatement fees
        // are not contributions and are left out). See ClientPlanSummary.
        $summary = (new PlanLedgerService())->summarize($planId);

        return $summary ? (float) $summary['total_paid'] : 0.0;
    }

    /**
     * Live "Remaining Balance": total contribution target minus every
     * paid/verified payment recorded against this plan, floored at 0 -
     * never the plans.remaining_balance column itself, which is only ever
     * set once at plan creation (monthly_fee * 12) and nothing in the real
     * payment-approval paths (PaymentTracking::reviewGcash()/recordCash(),
     * ApprovalService::approveInitialPayment()) ever updates it afterward,
     * so it silently drifts stale the moment a single payment is verified.
     * client/dashboard.php computed this inline before
     * (ClientDashboardController::dashboard()); extracted here so
     * client/payment.php's "Remaining Balance" stat can show the exact
     * same live number instead of that stale column - the mismatch this
     * was built to fix.
     */
    public function getRemainingBalance(int $planId, ?float $totalPlanAmount = null): float
    {
        $target = $totalPlanAmount ?? MembershipService::TOTAL_CONTRIBUTION;

        return max(0.0, round($target - $this->getTotalPaidForPlan($planId), 2));
    }

    public function getPaymentById(int $paymentId): ?array
    {
        $row = db_connect()->table('payments')
            ->where('payment_id', $paymentId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    private function getTotalPlanAmount(array $plan): float
    {
        $db = db_connect();
        $columns = array_map(static fn ($field) => $field->name, $db->getFieldData('plans'));

        if (in_array('total_plan_amount', $columns, true)) {
            $value = (float) ($db->table('plans')
                ->select('total_plan_amount')
                ->where('plan_id', (int) $plan['plan_id'])
                ->get()
                ->getRowArray()['total_plan_amount'] ?? 0);

            if ($value > 0) {
                return $value;
            }
        }

        return (float) ($plan['monthly_fee'] ?? 0);
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Auto-generates an official receipt number for a just-inserted payment.
     * Panel brief, section 3: "Auto-generate the Receipt Number (no manual
     * entry)." Built from the payment's own auto-increment id, so it's
     * guaranteed unique with no separate sequence/counter table to maintain.
     */
    public function generateReceiptNumber(int $paymentId, string $paymentDate): string
    {
        $datePart = $paymentDate !== '' ? str_replace('-', '', $paymentDate) : date('Ymd');

        return sprintf('OR-%s-%06d', $datePart, $paymentId);
    }

    /**
     * Turns a coverage start date + a number of months into a real calendar
     * label instead of a raw count. Panel brief, section 3/4: show actual
     * month(s) paid ("July 2026", "August-October 2026") instead of "1", "3".
     */
    public function describeCoverage(string $coverageStart, int $monthsCovered): string
    {
        $monthsCovered = max(1, $monthsCovered);
        $start = strtotime($coverageStart) ?: strtotime('today');

        $startLabel = date('F Y', $start);
        if ($monthsCovered === 1) {
            return $startLabel;
        }

        $endTimestamp = strtotime('+' . ($monthsCovered - 1) . ' months', $start);
        $endLabel = date('F Y', $endTimestamp);

        if (date('Y', $start) === date('Y', $endTimestamp)) {
            // Same year: "August-October 2026" (no need to repeat the year twice).
            return date('F', $start) . '-' . $endLabel;
        }

        // Crosses a year boundary: "December 2026 - February 2027".
        return $startLabel . ' - ' . $endLabel;
    }

    /**
     * Projects the calendar period a new payment will cover, based on the
     * plan's current coverage end date (or its start date / today if it has
     * none yet) — same base-date rule applyMembershipCoverage() (below) uses
     * when it actually extends coverage. Stored on the payment row so the UI
     * can show a real "August-October 2026" label instead of a raw count,
     * and it survives even if the plan's own coverage date later moves on.
     *
     * @param array{payment_coverage_until?: ?string, start_date?: ?string} $plan
     * @return array{start: string, end: string}
     */
    public function projectCoverage(array $plan, int $monthsCovered): array
    {
        $baseDate = (string) ($plan['payment_coverage_until'] ?? $plan['start_date'] ?? date('Y-m-d'));
        if ($baseDate === '' || strtotime($baseDate) === false) {
            $baseDate = date('Y-m-d');
        }

        // If the plan already has coverage, a new payment picks up the day after
        // it ends; otherwise it starts from that base date itself.
        $hasExistingCoverage = ! empty($plan['payment_coverage_until']);
        $start = $hasExistingCoverage
            ? date('Y-m-d', strtotime('+1 day', strtotime($baseDate)))
            : $baseDate;

        $end = date('Y-m-d', strtotime('+' . (max(1, $monthsCovered) - 1) . ' months', strtotime($start)));

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Whole months of advance coverage remaining from today. Panel brief,
     * section 3: "Show remaining months left on the plan."
     */
    public function remainingMonths(?string $paymentCoverageUntil): int
    {
        if (! $paymentCoverageUntil) {
            return 0;
        }

        $coverageUntil = strtotime($paymentCoverageUntil);
        if ($coverageUntil === false) {
            return 0;
        }

        $today = new \DateTimeImmutable('today');
        $until = (new \DateTimeImmutable('@' . $coverageUntil))->setTime(0, 0);

        if ($until <= $today) {
            return 0;
        }

        $diff = $today->diff($until);

        return max(0, ($diff->y * 12) + $diff->m + ($diff->d > 0 ? 1 : 0));
    }

    /**
     * How many more months this plan's CURRENT entitlement cycle can accept
     * a payment for - not the plan's lifetime term. Extracted verbatim from
     * Client\ClientPaymentController::paymentCapacity() (Client dashboard
     * rebuild, Phase 3, later updated to be cycle-based - see that method's
     * own doc comment on why a payment must never be allowed to span a
     * cycle boundary) so the collector Record Payment form
     * (CollectionListController) enforces the exact same cap instead of a
     * second, potentially-drifting copy of it. That controller method stays
     * as a private wrapper delegating here, since its name/array-shape are
     * already load-bearing for its own view.
     *
     * Not to be confused with remainingMonths() above, which answers a
     * different question ("how many months is this plan currently paid
     * ahead of today").
     *
     * @param array{plan_id: int|string, monthly_fee?: float|string|null} $plan
     * @return array{0: int, 1: int, 2: int, 3: int} [cycleTargetMonths, verifiedMonths, pendingMonths, remainingMonths]
     */
    public function remainingPayableMonths(array $plan): array
    {
        $planId = (int) $plan['plan_id'];
        $cycle = (new CycleService())->currentCycle($planId);

        $monthlyFee = (float) ($plan['monthly_fee'] ?? MembershipService::MONTHLY_FEE);
        if ($monthlyFee <= 0) {
            $monthlyFee = MembershipService::MONTHLY_FEE;
        }

        $cycleTargetMonths = (int) ceil($cycle['target'] / $monthlyFee);
        $verifiedMonths = $cycle['months_paid'];

        $pendingMonths = (int) (db_connect()->table('payments')
            ->selectSum('months_covered')
            ->where('plan_id', $planId)
            ->where('status', 'pending')
            ->get()
            ->getRowArray()['months_covered'] ?? 0);

        $cycleRemainingMonths = (int) ceil(max(0.0, $cycle['remaining']) / $monthlyFee);
        $remainingMonths = max(0, $cycleRemainingMonths - $pendingMonths);

        return [$cycleTargetMonths, $verifiedMonths, $pendingMonths, $remainingMonths];
    }

    /**
     * The month-by-month schedule for the plan's current cycle, the single
     * source for the Payments page, the Make payment calendar, and the
     * submit handler: payments always fill the next unpaid months in order,
     * starting from the plan's first month plus every month already paid or
     * awaiting verification.
     *
     * Amounts are capped at the cycle's ₱14,500 target, so the final month of
     * a 61-month cycle is charged its ₱100 remainder, not a full ₱240.
     *
     * @return array{total_months:int, verified_months:int, pending_months:int, remaining_months:int, monthly_fee:float, target:float, paid_amount:float, remaining_amount:float, payable_amount:float, anchor:string, offset:int}
     */
    public function paymentSchedule(array $plan): array
    {
        [$totalMonths, $verifiedMonths, $pendingMonths, $remainingMonths] = $this->remainingPayableMonths($plan);

        $monthlyFee = (float) ($plan['monthly_fee'] ?? MembershipService::MONTHLY_FEE);
        if ($monthlyFee <= 0) {
            $monthlyFee = MembershipService::MONTHLY_FEE;
        }
        $target = (float) (new CycleService())->currentCycle((int) $plan['plan_id'])['target'];

        $paidAmount = min($target, round($verifiedMonths * $monthlyFee, 2));
        $remainingAmount = max(0.0, round($target - $paidAmount, 2));

        return [
            'total_months' => $totalMonths,
            'verified_months' => $verifiedMonths,
            'pending_months' => $pendingMonths,
            'remaining_months' => $remainingMonths,
            'monthly_fee' => $monthlyFee,
            'target' => $target,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payable_amount' => max(0.0, round($remainingAmount - $pendingMonths * $monthlyFee, 2)),
            'anchor' => self::coverageAnchor($plan),
            'offset' => $verifiedMonths + $pendingMonths,
        ];
    }

    /**
     * First day of the month the plan's cycle begins covering: the plan's own
     * start month (activation sets start_date to the approval date). Later
     * cycles start from the cycle-start marker, which the cycle close stamps.
     */
    public static function coverageAnchor(array $plan): string
    {
        $cycleNumber = (int) ($plan['current_cycle_number'] ?? 1);
        $source = $cycleNumber > 1 && ! empty($plan['contribution_cycle_started_at'])
            ? (string) $plan['contribution_cycle_started_at']
            : (string) ($plan['start_date'] ?? '');

        $ts = $source !== '' ? strtotime($source) : false;

        return date('Y-m-01', $ts !== false ? $ts : strtotime('today'));
    }

    /** Month-start date ($anchor is a first-of-month) shifted by $addMonths. */
    public static function monthStart(string $anchor, int $addMonths): string
    {
        return date('Y-m-d', strtotime('+' . $addMonths . ' months', strtotime($anchor)));
    }

    /** Last day of the month $addMonths after $anchor. */
    public static function monthEnd(string $anchor, int $addMonths): string
    {
        return date('Y-m-t', strtotime('+' . $addMonths . ' months', strtotime($anchor)));
    }

    /**
     * Coverage implied by $verifiedMonths paid from $anchor, in order:
     * covered through the last day of the final paid month, next due the day
     * after that (the 1st of the next unpaid month).
     *
     * @return array{covered_until:string, next_due:string}|null  null when nothing is paid yet
     */
    public static function coverageFromMonths(string $anchor, int $verifiedMonths): ?array
    {
        if ($verifiedMonths <= 0) {
            return null;
        }

        return [
            'covered_until' => self::monthEnd($anchor, $verifiedMonths - 1),
            'next_due' => self::monthStart($anchor, $verifiedMonths),
        ];
    }

    private function updateNextDueDate(int $planId, int $monthsCovered): void
    {
        $plan = db_connect()->table('plans')
            ->select('plan_id, start_date, next_due_date')
            ->where('plan_id', $planId)
            ->get()
            ->getRowArray();

        if (! $plan) {
            return;
        }

        $baseDate = (string) ($plan['next_due_date'] ?? ($plan['start_date'] ?? date('Y-m-d')));
        if ($baseDate === '') {
            return;
        }

        $newDueDate = date('Y-m-d', strtotime('+' . max(1, $monthsCovered) . ' months', strtotime($baseDate)));

        db_connect()->table('plans')
            ->where('plan_id', $planId)
            ->update(['next_due_date' => $newDueDate]);
    }
}
