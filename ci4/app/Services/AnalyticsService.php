<?php

namespace App\Services;

/**
 * AnalyticsService
 *
 * Shared analytics/summary queries for Admin, Branch Admin, Staff, and
 * Collector dashboards. Moved out of App\Controllers\Analytics (which used
 * to keep these as private methods) so App\Controllers\Dashboard can reuse
 * the exact same data instead of duplicating the queries - the 2026-09-09
 * system scan found Dashboard::admin()/staff()/collector() were rendering
 * static "Coming Soon" placeholders while this real, working data was only
 * ever wired into the separate /admin/analytics and /staff/analytics pages.
 */
class AnalyticsService
{
    /**
     * System-wide analytics. Used by both the Admin dashboard and the
     * Admin analytics page.
     */
    public function getSystemWideAnalytics(): array
    {
        $db = db_connect();

        $totalMembers = $db->table('plan_holders')
            ->countAllResults();

        $activeMembers = $db->table('plan_holders ph')
            ->join('plans p', 'p.plan_holder_id = ph.plan_holder_id', 'inner')
            ->where('p.status', 'active')
            ->where('ph.status', 'active')
            ->countAllResults();

        // The one status (PlanLedgerService): grace period / lapsed or forfeited.
        $delinquentMembers = $db->table('plans')
            ->where('lifecycle_status', 'grace')
            ->countAllResults();

        $suspendedMembers = $db->table('plans')
            ->whereIn('lifecycle_status', ['lapsed', 'forfeited'])
            ->countAllResults();

        $monthlyCollections = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $nextDate = date('Y-m-01', strtotime("-$i months +1 month"));
            $amount = $db->table('payments')
                ->selectSum('amount', 'total')
                ->where('payment_date >=', $date)
                ->where('payment_date <', $nextDate)
                ->where('status', 'paid')
                ->get()
                ->getRowArray();
            $monthlyCollections[] = [
                'month' => date('M', strtotime($date)),
                'amount' => (float) ($amount['total'] ?? 0),
            ];
        }

        $totalCollections = $db->table('payments')
            ->selectSum('amount', 'total')
            ->where('status', 'paid')
            ->get()
            ->getRowArray();

        $branches = $db->table('branches b')
            ->select('b.branch_id, b.branch_name, COUNT(DISTINCT ph.plan_holder_id) as member_count')
            ->join('plan_holders ph', 'ph.branch_id = b.branch_id', 'left')
            ->groupBy('b.branch_id')
            ->orderBy('member_count', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $pendingApprovals = $db->table('payments pay')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->where('pay.status', 'pending')
            ->where('pay.payment_method', 'gcash')
            ->where('ph.status', 'inactive')
            ->countAllResults();

        $pendingServiceRequests = $db->table('service_applications')
            ->where('status', 'pending')
            ->countAllResults();

        $totalBranches = $db->table('branches')->countAllResults();
        $totalStaff = $db->table('users')->where('role_id', 3)->countAllResults();
        $totalCollectors = $db->table('users')->where('role_id', 5)->countAllResults();

        return [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'delinquent_members' => $delinquentMembers,
            'suspended_members' => $suspendedMembers,
            'total_collections' => (float) ($totalCollections['total'] ?? 0),
            'monthly_collections' => $monthlyCollections,
            'top_branches' => $branches,
            'pending_approvals' => $pendingApprovals,
            'pending_service_requests' => $pendingServiceRequests,
            'total_branches' => $totalBranches,
            'total_staff' => $totalStaff,
            'total_collectors' => $totalCollectors,
        ];
    }

    /**
     * Branch-specific analytics. Used by Branch Admin's analytics page
     * (Branch Admin's dashboard has its own, richer, separately-built
     * operations-center query set in Dashboard::branchAdmin()).
     */
    public function getBranchAnalytics(int $branchId): array
    {
        $db = db_connect();

        $branchMembers = $db->table('plan_holders')
            ->where('branch_id', $branchId)
            ->countAllResults();

        $activeBranchMembers = $db->table('plan_holders ph')
            ->join('plans p', 'p.plan_holder_id = ph.plan_holder_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->where('p.status', 'active')
            ->where('ph.status', 'active')
            ->countAllResults();

        $overdueAccounts = $db->table('plans p')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->whereIn('p.lifecycle_status', ['grace', 'lapsed'])
            ->countAllResults();

        $currentMonth = date('Y-m');
        $dailyCollections = $db->table('payments')
            ->select('DATE(payment_date) as date, COUNT(*) as count, SUM(amount) as total')
            ->where('branch_id', $branchId)
            ->where('status', 'paid')
            ->where("DATE_FORMAT(payment_date, '%Y-%m') =", $currentMonth)
            ->groupBy('DATE(payment_date)')
            ->orderBy('date', 'ASC')
            ->get()
            ->getResultArray();

        $monthlyBranchTotal = $db->table('payments')
            ->selectSum('amount', 'total')
            ->where('branch_id', $branchId)
            ->where('status', 'paid')
            ->where("DATE_FORMAT(payment_date, '%Y-%m') =", $currentMonth)
            ->get()
            ->getRowArray();

        $pendingInitialPayments = $db->table('payments pay')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->where('pay.status', 'pending')
            ->where('pay.branch_id', $branchId)
            ->where('ph.status', 'inactive')
            ->countAllResults();

        $pendingServiceApps = $db->table('service_applications sa')
            ->join('plan_holders ph', 'ph.plan_holder_id = sa.plan_holder_id', 'inner')
            ->where('sa.status', 'pending')
            ->where('ph.branch_id', $branchId)
            ->countAllResults();

        $ongoingServices = $db->table('service_schedules')
            ->where('branch_id', $branchId)
            ->where('status', 'in-progress')
            ->countAllResults();

        $staffCount = $db->table('users')
            ->where('branch_id', $branchId)
            ->where('role_id', 3)
            ->where('account_status', 'active')
            ->countAllResults();

        return [
            'branch_members' => $branchMembers,
            'active_members' => $activeBranchMembers,
            'overdue_accounts' => $overdueAccounts,
            'daily_collections' => $dailyCollections,
            'monthly_total' => (float) ($monthlyBranchTotal['total'] ?? 0),
            'pending_initial_payments' => $pendingInitialPayments,
            'pending_service_requests' => $pendingServiceApps,
            'ongoing_services' => $ongoingServices,
            'staff_count' => $staffCount,
        ];
    }

    /**
     * Staff-specific analytics. Used by both the Staff dashboard and the
     * Staff analytics page.
     */
    public function getStaffAnalytics(int $branchId): array
    {
        $db = db_connect();

        $staffMembers = $db->table('plan_holders')
            ->where('branch_id', $branchId)
            ->countAllResults();

        $assignedServices = $db->table('service_applications')
            ->where('status', 'approved')
            ->countAllResults();

        $pendingRequests = $db->table('service_applications')
            ->where('status', 'pending')
            ->countAllResults();

        $todayPayments = $db->table('payments')
            ->where('payment_method', 'cash')
            ->where('DATE(payment_date)', date('Y-m-d'))
            ->where('branch_id', $branchId)
            ->countAllResults();

        $todayTotal = $db->table('payments')
            ->selectSum('amount', 'total')
            ->where('payment_method', 'cash')
            ->where('DATE(payment_date)', date('Y-m-d'))
            ->where('branch_id', $branchId)
            ->where('status', 'pending')
            ->get()
            ->getRowArray();

        return [
            'staff_members' => $staffMembers,
            'assigned_services' => $assignedServices,
            'pending_requests' => $pendingRequests,
            'cash_payments_today' => $todayPayments,
            'cash_collected_today' => (float) ($todayTotal['total'] ?? 0),
        ];
    }

    /**
     * Collector summary: what this collector role has actually recorded so
     * far, and their estimated commission. The Collector role has no
     * dedicated collection-entry feature built yet (login/routing/layout
     * only) - this reuses `payments.received_by` (already the "who
     * actually recorded this payment" column relied on by the Reports
     * suite's Collections/Commission reports) and
     * ReportService::COMMISSION_RATE so the number shown here always
     * matches what those reports would say about this collector.
     */
    public function getCollectorSummary(int $userId): array
    {
        $db = db_connect();

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $totalCollected = $db->table('payments')
            ->selectSum('amount', 'total')
            ->where('received_by', $userId)
            ->where('status', 'paid')
            ->get()
            ->getRowArray();

        $monthCollected = $db->table('payments')
            ->selectSum('amount', 'total')
            ->where('received_by', $userId)
            ->where('status', 'paid')
            ->where('payment_date >=', $monthStart)
            ->get()
            ->getRowArray();

        $todayCollected = $db->table('payments')
            ->selectSum('amount', 'total')
            ->where('received_by', $userId)
            ->where('status', 'paid')
            ->where('DATE(payment_date)', $today)
            ->get()
            ->getRowArray();

        $transactionCount = $db->table('payments')
            ->where('received_by', $userId)
            ->where('status', 'paid')
            ->countAllResults();

        $recentPayments = $db->table('payments pay')
            ->select('pay.payment_id, pay.amount, pay.payment_method, pay.payment_date, pay.status, u.first_name, u.last_name')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'left')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'left')
            ->join('users u', 'u.user_id = ph.user_id', 'left')
            ->where('pay.received_by', $userId)
            ->orderBy('pay.payment_id', 'DESC')
            ->limit(10)
            ->get()
            ->getResultArray();

        $monthTotal = (float) ($monthCollected['total'] ?? 0);

        // Fix Prompts Task 4: commission is 10% of verified CASH collected
        // by this collector, not every payment method - a client's own
        // GCash payment (received_by often null anyway) or desk GCash/cash
        // someone else recorded never earns this account commission. Delegates
        // to CommissionService so this dashboard card and the admin Commission
        // Report can never quietly disagree on what counts.
        $commissionTotals = (new CommissionService())->totals($userId, date('Y-m-01'), date('Y-m-d'));

        return [
            'total_collected' => (float) ($totalCollected['total'] ?? 0),
            'month_collected' => $monthTotal,
            'today_collected' => (float) ($todayCollected['total'] ?? 0),
            'transaction_count' => $transactionCount,
            'estimated_commission' => $commissionTotals['commission'],
            'recent_payments' => $recentPayments,
        ];
    }
}
