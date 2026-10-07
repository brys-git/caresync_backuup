<?php

namespace App\Services;

class ReportService
{
    /**
     * Panel brief, section 8: "Collector/agent commission = 10% of the
     * amount collected, computed automatically." Defined once here so the
     * Commission Report and any later overdue/commission policy work read
     * the same rate rather than each hardcoding their own 0.10.
     */
    public const COMMISSION_RATE = 0.10;

    public function getDashboardSummary(array $filters): array
    {
        return [
            'monthly_collections' => $this->getMonthlyCollections($filters),
            'payment_breakdown' => $this->getPaymentBreakdown($filters),
            'member_status' => $this->getMemberStatusSummary($filters),
            'delinquent_accounts' => $this->getDelinquentAccounts($filters),
            'service_usage' => $this->getServiceUsageStatistics($filters),
            'payment_trends' => $this->getPaymentTrends($filters),
        ];
    }

    public function getRemittanceReport(array $filters): array
    {
        $builder = $this->baseQuery($filters)
            ->select('p.payment_id, p.payment_date, p.amount, p.months_covered, p.payment_method, p.reference_number, p.official_receipt_number, p.status, rb.first_name AS staff_first, rb.last_name AS staff_last, cu.first_name AS client_first, cu.last_name AS client_last, ph.plan_holder_id, ph.unique_identifier, pl.start_date, b.branch_name');

        return $builder
            ->orderBy('p.payment_date', 'DESC')
            ->orderBy('p.payment_id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function getTotalRemittance(array $filters): float
    {
        $row = $this->baseQuery($filters)
            ->select('COALESCE(SUM(p.amount), 0) AS total_remittance', false)
            ->get()
            ->getRowArray();

        return (float) ($row['total_remittance'] ?? 0);
    }

    public function getPaymentBreakdown(array $filters): array
    {
        $row = $this->baseQuery($filters)
            ->select("COUNT(*) AS total_transactions, COALESCE(SUM(p.amount), 0) AS total_amount, COALESCE(SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END), 0) AS cash_total, COALESCE(SUM(CASE WHEN p.payment_method = 'gcash' THEN p.amount ELSE 0 END), 0) AS gcash_total", false)
            ->get()
            ->getRowArray();

        return [
            'total_transactions' => (int) ($row['total_transactions'] ?? 0),
            'total_amount' => (float) ($row['total_amount'] ?? 0),
            'cash_total' => (float) ($row['cash_total'] ?? 0),
            'gcash_total' => (float) ($row['gcash_total'] ?? 0),
        ];
    }

    public function getBranchPaymentStaff(int $branchId): array
    {
        return db_connect()->table('users u')
            ->select('u.user_id, u.first_name, u.last_name, u.contact_number')
            ->join('payments p', 'p.received_by = u.user_id', 'inner')
            ->where('u.branch_id', $branchId)
            ->groupBy('u.user_id, u.first_name, u.last_name, u.contact_number')
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getBranchInfo(int $branchId): ?array
    {
        $row = db_connect()->table('branches')
            ->select('branch_id, branch_name, address_street, address_barangay, address_city, address_province, contact_number')
            ->where('branch_id', $branchId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function getMonthlyCollections(array $filters): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $branchId = (int) ($filters['branch_id'] ?? 0);

        $builder = db_connect()->table('payments p')
            ->select("DATE_FORMAT(p.payment_date, '%Y-%m') AS month_key, DATE_FORMAT(p.payment_date, '%b %Y') AS month_label, COUNT(*) AS total_transactions, COALESCE(SUM(p.amount), 0) AS total_amount", false)
            ->where('YEAR(p.payment_date)', $year, false)
            // Single-quoted PHP string: \" is not an escape sequence here, it
            // stays as a literal backslash-plus-quote and corrupts the SQL
            // (MySQL sees a stray backslash where it expects the GROUP BY
            // expression to just end) - use a real double quote instead.
            ->groupBy('DATE_FORMAT(p.payment_date, "%Y-%m")', false)
            ->orderBy('month_key', 'ASC');

        if ($branchId > 0) {
            $builder->where('p.branch_id', $branchId);
        }

        return $builder->get()->getResultArray();
    }

    public function getMemberStatusSummary(array $filters): array
    {
        $branchId = (int) ($filters['branch_id'] ?? 0);

        $builder = db_connect()->table('plan_holders ph')
            ->select("COUNT(*) AS total_members, SUM(CASE WHEN ph.status = 'active' THEN 1 ELSE 0 END) AS active_members, SUM(CASE WHEN ph.status <> 'active' THEN 1 ELSE 0 END) AS inactive_members", false);

        if ($branchId > 0) {
            $builder->where('ph.branch_id', $branchId);
        }

        $row = $builder->get()->getRowArray();

        return [
            'total_members' => (int) ($row['total_members'] ?? 0),
            'active_members' => (int) ($row['active_members'] ?? 0),
            'inactive_members' => (int) ($row['inactive_members'] ?? 0),
        ];
    }

    public function getDelinquentAccounts(array $filters): array
    {
        $branchId = (int) ($filters['branch_id'] ?? 0);
        $today = date('Y-m-d');

        $builder = db_connect()->table('plans pl')
            ->select('pl.plan_id, pl.remaining_balance, pl.months_paid, pl.status AS plan_status, pl.next_due_date, ph.unique_identifier, u.first_name, u.last_name, b.branch_name, pl.start_date')
            ->join('plan_holders ph', 'ph.plan_holder_id = pl.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = ph.branch_id', 'left')
            // overdue_months is kept in step with PlanLedgerService::sync(); the
            // old remaining_balance column is not maintained and is not used.
            ->where('pl.overdue_months >', 0)
            ->where('pl.status !=', 'completed')
            ->orderBy('pl.overdue_months', 'DESC')
            ->limit(25);

        if ($branchId > 0) {
            $builder->where('ph.branch_id', $branchId);
        }

        $rows = $builder->get()->getResultArray();

        // Balance and months paid from the one shared calculation.
        $summaries = new ClientPlanSummary();
        foreach ($rows as &$row) {
            $s = $summaries->forPlan((int) $row['plan_id'], $today);
            if ($s !== null) {
                $row['remaining_balance'] = $s['remaining_balance'];
                $row['months_paid'] = $s['months_paid'];
                $row['overdue_amount'] = $s['overdue_amount'];
            }
        }
        unset($row);

        return $rows;
    }

    public function getServiceUsageStatistics(array $filters): array
    {
        $branchId = (int) ($filters['branch_id'] ?? 0);

        $builder = db_connect()->table('services s')
            // Same stray-backslash issue as getMonthlyCollections() above: \"
            // inside a single-quoted PHP string isn't an escape sequence.
            ->select('COALESCE(sl.service_name, "Unknown") AS service_name, COUNT(*) AS total_services, SUM(CASE WHEN s.status = "completed" THEN 1 ELSE 0 END) AS completed_services, SUM(CASE WHEN s.status = "cancelled" THEN 1 ELSE 0 END) AS cancelled_services', false)
            ->join('service_list sl', 'sl.service_list_id = s.service_list_id', 'left')
            ->groupBy('COALESCE(sl.service_name, "Unknown")', false)
            ->orderBy('total_services', 'DESC')
            ->limit(10);

        if ($branchId > 0) {
            $builder->where('s.branch_id', $branchId);
        }

        return $builder->get()->getResultArray();
    }

    public function getPaymentTrends(array $filters): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $branchId = (int) ($filters['branch_id'] ?? 0);

        $builder = db_connect()->table('payments p')
            ->select("DATE_FORMAT(p.payment_date, '%Y-%m') AS month_key, DATE_FORMAT(p.payment_date, '%b %Y') AS month_label, SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END) AS cash_total, SUM(CASE WHEN p.payment_method = 'gcash' THEN p.amount ELSE 0 END) AS gcash_total", false)
            ->where('YEAR(p.payment_date)', $year, false)
            ->groupBy('DATE_FORMAT(p.payment_date, "%Y-%m")', false)
            ->orderBy('month_key', 'ASC');

        if ($branchId > 0) {
            $builder->where('p.branch_id', $branchId);
        }

        return $builder->get()->getResultArray();
    }

    public function getOverdueReport(array $filters): array
    {
        $branchId = (int) ($filters['branch_id'] ?? 0);
        $today = date('Y-m-d');

        // Candidate plans only (cheap SQL); the real numbers come from the
        // one rule, PlanLedgerService, so this report always agrees with the
        // client details page and the client's own dashboard.
        $builder = db_connect()->table('plans pl')
            ->select('pl.plan_id, ph.plan_holder_id, ph.unique_identifier, u.first_name, u.last_name, u.contact_number, b.branch_name')
            ->join('plan_holders ph', 'ph.plan_holder_id = pl.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = ph.branch_id', 'left')
            ->groupStart()
                ->where('pl.next_due_date <', $today)
                ->orWhereIn('pl.lifecycle_status', ['grace', 'lapsed', 'forfeited'])
            ->groupEnd();

        if ($branchId > 0) {
            $builder->where('ph.branch_id', $branchId);
        }

        cs_apply_search($builder, (string) ($filters['search'] ?? ''), ['u.first_name', 'u.last_name', 'u.contact_number', 'ph.unique_identifier']);

        $ledger = new PlanLedgerService();
        $rows = [];

        foreach ($builder->get()->getResultArray() as $row) {
            $s = $ledger->summarize((int) $row['plan_id'], $today);
            if (! $s || ! in_array($s['lifecycle'], ['grace', 'lapsed', 'forfeited'], true)) {
                continue;
            }

            $rows[] = $row + [
                'remaining_balance'      => max(0.0, round(MembershipService::TOTAL_CONTRIBUTION - $s['total_paid'], 2)),
                'months_paid'            => $s['months_paid'],
                'overdue_months'         => $s['overdue_count'],
                'overdue_amount'         => $s['overdue_amount'],
                'overdue_dues'           => $s['overdue_dues'],
                'next_due_date'          => $s['oldest_unpaid_due'],
                'days_overdue'           => $s['days_overdue'],
                'lifecycle'              => $s['lifecycle'],
                // Kept under its old key for the CSV/PDF callers: now the
                // days left in the grace period (null once it has passed).
                'days_until_forfeiture'  => $s['grace_days_left'],
                'grace_days_left'        => $s['grace_days_left'],
                'status_text'            => match ($s['lifecycle']) {
                    'grace'  => $s['grace_days_left'] . ' day(s) left to pay',
                    'lapsed' => 'Lapsed - on hold',
                    default  => 'Forfeited',
                },
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['next_due_date'], (string) $b['next_due_date']));

        return $rows;
    }

    /**
     * Plan-holder search for the Ledger report (by name or unique
     * identifier).
     */
    public function searchPlanHolders(string $query, int $branchId = 0): array
    {
        $query = cs_search_term($query);
        if ($query === '') {
            return [];
        }

        $builder = db_connect()->table('plan_holders ph')
            ->select('ph.plan_holder_id, ph.unique_identifier, u.first_name, u.last_name, b.branch_name')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = ph.branch_id', 'left');

        cs_apply_search($builder, $query, ['u.first_name', 'u.last_name', 'u.contact_number', 'u.email', 'ph.unique_identifier']);

        $builder
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC')
            ->limit(20);

        if ($branchId > 0) {
            $builder->where('ph.branch_id', $branchId);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Panel brief, section 7: "Ledger of Plan Holder - full payment/
     * transaction history per plan holder." Returns the plan holder's
     * profile/plan header plus every payment on their current plan, in
     * ascending order (same convention as the client-facing payment
     * history from Phase 2), with a running total of confirmed payments.
     */
    public function getPlanHolderLedger(int $planHolderId): ?array
    {
        if ($planHolderId <= 0) {
            return null;
        }

        $holder = db_connect()->table('plan_holders ph')
            ->select('ph.plan_holder_id, ph.unique_identifier, ph.status AS holder_status, u.first_name, u.last_name, u.contact_number, u.email, b.branch_name')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = ph.branch_id', 'left')
            ->where('ph.plan_holder_id', $planHolderId)
            ->get()
            ->getRowArray();

        if (! $holder) {
            return null;
        }

        $plan = db_connect()->table('plans')
            ->where('plan_holder_id', $planHolderId)
            ->orderBy('plan_id', 'DESC')
            ->get()
            ->getRowArray();

        $payments = [];
        if ($plan) {
            $payments = db_connect()->table('payments pay')
                ->select('pay.payment_id, pay.payment_date, pay.amount, pay.months_covered, pay.payment_method, pay.reference_number, pay.official_receipt_number, pay.status, pay.coverage_start, pay.coverage_end, rb.first_name AS staff_first, rb.last_name AS staff_last')
                ->join('users rb', 'rb.user_id = pay.received_by', 'left')
                ->where('pay.plan_id', (int) $plan['plan_id'])
                ->orderBy('pay.payment_date', 'ASC')
                ->orderBy('pay.payment_id', 'ASC')
                ->get()
                ->getResultArray();

            $runningTotal = 0.0;
            foreach ($payments as &$payment) {
                if (in_array((string) $payment['status'], ['paid', 'verified'], true)) {
                    $runningTotal += (float) $payment['amount'];
                }
                $payment['running_total'] = round($runningTotal, 2);
            }
            unset($payment);
        }

        return [
            'holder' => $holder,
            'plan' => $plan,
            'payments' => $payments,
        ];
    }

    /**
     * Panel brief, section 7: "Collections/Collector Report - summary of
     * amounts collected per collector." "Collector" here is whoever
     * recorded the payment (received_by), not narrowly the Collector role -
     * Staff and Branch Admin already record payments today, and a Collector
     * -only filter would show nothing until that role has real activity.
     * Each row's role_name lets Admin see who's who.
     */
    public function getCollectionsByCollector(array $filters): array
    {
        $builder = $this->collectorBaseQuery($filters)
            ->select('u.user_id, u.first_name, u.last_name, r.role_name, COUNT(p.payment_id) AS transaction_count, COALESCE(SUM(p.amount), 0) AS total_collected', false)
            ->groupBy('u.user_id, u.first_name, u.last_name, r.role_name')
            ->orderBy('total_collected', 'DESC');

        $rows = $builder->get()->getResultArray();

        foreach ($rows as &$row) {
            $row['total_collected'] = (float) ($row['total_collected'] ?? 0);
            $row['transaction_count'] = (int) ($row['transaction_count'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /**
     * Panel brief, section 7/8: "Commission Report - commission earned per
     * agent/collector," at the section 8 rate (10% of amount collected).
     *
     * Fix Prompts Task 4: this used to run off getCollectionsByCollector(),
     * which counts every verified payment regardless of method - including
     * desk cash/GCash a Branch Admin recorded through Payment Tracking, not
     * just cash a collector actually collected in the field. Delegates to
     * CommissionService::reportRows() instead (same cash-only eligibility
     * rule the collector's own commission page uses), so this report and
     * that page always agree for the same date range. Return shape is
     * unchanged: user_id/first_name/last_name/role_name/transaction_count/
     * total_collected/commission/net_remitted.
     */
    public function getCommissionReport(array $filters): array
    {
        $rows = (new CommissionService())->reportRows(
            (int) ($filters['branch_id'] ?? 0) ?: null,
            (string) ($filters['date_from'] ?? '') ?: null,
            (string) ($filters['date_to'] ?? '') ?: null
        );

        // The commission rows come out of CommissionService already
        // aggregated, so the collector-name search is applied to them here
        // (in PHP - no SQL involved): every word must be in the full name.
        $term = cs_search_term($filters['search'] ?? '');
        if ($term === '') {
            return $rows;
        }

        $words = array_map('mb_strtolower', explode(' ', $term));

        return array_values(array_filter($rows, static function (array $row) use ($words): bool {
            $name = mb_strtolower(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')));
            foreach ($words as $word) {
                if (! str_contains($name, $word)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function collectorBaseQuery(array $filters)
    {
        $branchId = (int) ($filters['branch_id'] ?? 0);
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');

        $builder = db_connect()->table('payments p')
            ->join('users u', 'u.user_id = p.received_by', 'inner')
            ->join('roles r', 'r.role_id = u.role_id', 'left')
            ->where('p.status', 'paid');

        if ($branchId > 0) {
            $builder->where('p.branch_id', $branchId);
        }

        if ($dateFrom !== '') {
            $builder->where('p.payment_date >=', $dateFrom);
        }

        if ($dateTo !== '') {
            $builder->where('p.payment_date <=', $dateTo);
        }

        cs_apply_search($builder, (string) ($filters['search'] ?? ''), ['u.first_name', 'u.last_name']);

        return $builder;
    }

    private function baseQuery(array $filters)
    {
        $builder = db_connect()->table('payments p')
            ->join('users rb', 'rb.user_id = p.received_by', 'inner')
            ->join('plans pl', 'pl.plan_id = p.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = pl.plan_holder_id', 'inner')
            ->join('users cu', 'cu.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = ph.branch_id', 'left')
            ->where('p.payment_date >=', (string) $filters['date_from'])
            ->where('p.payment_date <=', (string) $filters['date_to']);

        // branch_id 0 means system-wide (Admin with no branch filter chosen) -
        // Branch Admin/Staff always pass their own real branch_id, so this
        // was never an issue for them, but it silently zeroed out every
        // Admin-facing report built on this query (the existing "Payments
        // Report" dashboard table included) until now.
        if ((int) $filters['branch_id'] > 0) {
            $builder->where('p.branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['payment_method'])) {
            $builder->where('p.payment_method', (string) $filters['payment_method']);
        }

        if (! empty($filters['received_by'])) {
            $builder->where('p.received_by', (int) $filters['received_by']);
        }

        cs_apply_search($builder, (string) ($filters['search'] ?? ''), [
            'cu.first_name', 'cu.last_name', 'rb.first_name', 'rb.last_name',
            'ph.unique_identifier', 'p.reference_number', 'p.official_receipt_number',
        ]);

        return $builder;
    }
}
