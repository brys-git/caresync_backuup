<?php

namespace App\Controllers;

use App\Helpers\QueryHelper;
use App\Services\ActivityLogService;
use App\Services\AnalyticsService;
use App\Services\PaymentService;
use Config\Database;

class Dashboard extends BaseController
{
    public function index()
    {
        $role = (int) session()->get('role_id');

        if ($role === 1) {
            return redirect()->to('/dashboard/admin');
        } elseif ($role === 2) {
            return redirect()->to('/dashboard/branch-admin');
        } elseif ($role === 3) {
            return redirect()->to('/dashboard/staff');
        } elseif ($role === 5) {
            return redirect()->to('/dashboard/collector');
        }

        return redirect()->to('/dashboard/plan-holder');
    }

    public function admin(): string
    {
        // Was a static "Coming Soon" placeholder with no data at all.
        // Reuses the exact same query set as Analytics::admin() (moved into
        // the shared AnalyticsService so both routes stay in sync) - found
        // during the 2026-09-09 system scan.
        $analytics = (new AnalyticsService())->getSystemWideAnalytics();
        $recentActivity = (new ActivityLogService())->getAllLogs(8);

        return view('dashboards/admin', [
            'role_layout' => $this->resolveLayoutView(),
            'page_title' => 'System Admin Dashboard',
            'page_sub' => 'Membership, collections, and approvals across every branch.',
            'breadcrumb' => ['Dashboard'],
            'analytics' => $analytics,
            'recent_activity' => $recentActivity,
        ]);
    }

    public function branchAdmin(): string
    {
        $branchId = $this->getBranchId();
        $branchInfo = QueryHelper::getBranchInfo($branchId);
        $branchName = $branchInfo['branch_name'] ?? 'Branch Operations Center';
        $operatorName = trim((string) session()->get('first_name') . ' ' . (string) session()->get('last_name'));
        $operatorName = $operatorName !== '' ? $operatorName : 'Branch Admin';

        $memberStats = QueryHelper::getMemberStats($branchId);
        $serviceRequestCounts = $this->getServiceRequestCounts($branchId);
        $collectionsThisMonth = QueryHelper::getTotalCollections($branchId, date('Y-m-01'), date('Y-m-t'));
        $todayOperations = $this->getTodayServiceCalendar($branchId);
        $upcomingServices = $this->countUpcomingServices($branchId);
        $ongoingServices = $this->countServiceCalendarEvents($branchId, ['scheduled', 'in-progress']);
        $staffOnDuty = $this->countStaffOnDuty($branchId);
        $pendingApprovals = QueryHelper::countPendingApprovals($branchId);
        $recentPayments = array_slice((new PaymentService())->getBranchMonitoringPayments($branchId), 0, 8);
        $paymentAlerts = $this->getPaymentAlerts($branchId, $memberStats, $serviceRequestCounts);
        $activityLogs = $this->getRecentBranchActivity($branchId, 8);
        $paymentAnalytics = $this->getPaymentAnalytics($branchId, 6);

        return view('dashboards/branch_admin', [
            'role_layout' => $this->resolveLayoutView(),
            'page_title' => 'Branch Operations Control Center',
            'page_sub' => 'Operational dashboard for branch performance, approvals, payments, and service readiness.',
            'breadcrumb' => ['Dashboard'],
            'branch_name' => $branchName,
            'operator_name' => $operatorName,
            'current_time' => (new \DateTime())->format('l, F j, Y H:i'),
            'member_stats' => $memberStats,
            'pending_approvals' => $pendingApprovals,
            'collections_this_month' => $collectionsThisMonth,
            'service_request_counts' => $serviceRequestCounts,
            'today_operations' => $todayOperations,
            'upcoming_services' => $upcomingServices,
            'ongoing_services' => $ongoingServices,
            'staff_on_duty' => $staffOnDuty,
            'recent_payments' => $recentPayments,
            'payment_alerts' => $paymentAlerts,
            'activity_logs' => $activityLogs,
            'payment_analytics' => $paymentAnalytics,
        ]);
    }

    public function staff(): string
    {
        // Was a static "Coming Soon" placeholder with no data at all.
        // Found during the 2026-09-09 system scan.
        $branchId = (int) session('branch_id');
        $analytics = (new AnalyticsService())->getStaffAnalytics($branchId);

        return view('dashboards/staff', [
            'role_layout' => $this->resolveLayoutView(),
            'page_title' => 'Staff Dashboard',
            'page_sub' => 'Your branch\'s membership, payments, and service activity.',
            'breadcrumb' => ['Dashboard'],
            'analytics' => $analytics,
        ]);
    }

    public function collector(): string
    {
        // Was a static "Coming Soon" placeholder with no data at all.
        // The Collector role has no dedicated collection-entry feature
        // built yet, so this reuses payments.received_by - the same
        // "who actually recorded this payment" column the Reports suite's
        // Collections/Commission reports already rely on - to show what
        // this collector has actually recorded. Found during the
        // 2026-09-09 system scan.
        $userId = (int) session('user_id');
        $summary = (new AnalyticsService())->getCollectorSummary($userId);

        return view('dashboards/collector', [
            'role_layout' => $this->resolveLayoutView(),
            'page_title' => 'Collector Dashboard',
            'page_sub' => 'What you\'ve collected and what\'s still due.',
            'breadcrumb' => ['Dashboard'],
            'summary' => $summary,
        ]);
    }

    private function resolveLayoutView(): string
    {
        $role = (int) session()->get('role_id');

        if ($role === 1) {
            return 'layouts/admin';
        } elseif ($role === 2) {
            return 'layouts/branch_admin';
        } elseif ($role === 3) {
            return 'layouts/staff';
        } elseif ($role === 5) {
            return 'layouts/collector';
        }

        return 'layouts/plan_holder';
    }

    private function getBranchId(): int
    {
        return (int) session()->get('branch_id');
    }

    private function getServiceRequestCounts(int $branchId): array
    {
        if ($branchId <= 0) {
            return [];
        }

        $rows = db_connect()->table('service_applications sa')
            ->select('sa.status, COUNT(sa.application_id) AS total')
            ->join('plan_holders ph', 'ph.plan_holder_id = sa.plan_holder_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->groupBy('sa.status')
            ->get()
            ->getResultArray();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    private function getTodayServiceCalendar(int $branchId): array
    {
        if ($branchId <= 0 || ! $this->serviceCalendarExists()) {
            return [];
        }

        return db_connect()->table('service_calendar sc')
            ->select('sc.calendar_id, sc.event_type, sc.event_date, sc.event_time, sc.location, sc.status, sc.assigned_staff_ids, ph.unique_identifier, u.first_name, u.last_name, COALESCE(sl.service_name, sc.event_type) AS event_name')
            ->join('plan_holders ph', 'ph.plan_holder_id = sc.plan_holder_id', 'left')
            ->join('users u', 'u.user_id = ph.user_id', 'left')
            ->join('service_list sl', 'sl.service_list_id = sc.service_id', 'left')
            ->where('sc.branch_id', $branchId)
            ->where('sc.event_date', date('Y-m-d'))
            ->whereIn('sc.status', ['scheduled', 'in-progress'])
            ->orderBy('sc.event_time', 'ASC')
            ->limit(10)
            ->get()
            ->getResultArray();
    }

    private function countUpcomingServices(int $branchId): int
    {
        if ($branchId <= 0 || ! $this->serviceCalendarExists()) {
            return 0;
        }

        return (int) db_connect()->table('service_calendar')
            ->where('branch_id', $branchId)
            ->where('event_date >=', date('Y-m-d'))
            ->where('event_date <=', date('Y-m-d', strtotime('+7 days')))
            ->where('status', 'scheduled')
            ->countAllResults();
    }

    private function countServiceCalendarEvents(int $branchId, array $statuses): int
    {
        if ($branchId <= 0 || empty($statuses) || ! $this->serviceCalendarExists()) {
            return 0;
        }

        return (int) db_connect()->table('service_calendar')
            ->where('branch_id', $branchId)
            ->whereIn('status', $statuses)
            ->countAllResults();
    }

    private function serviceCalendarExists(): bool
    {
        return db_connect()->tableExists('service_calendar');
    }

    private function staffSchedulesExists(): bool
    {
        return db_connect()->tableExists('staff_schedules');
    }

    private function countStaffOnDuty(int $branchId): int
    {
        if ($branchId <= 0 || ! $this->staffSchedulesExists()) {
            return 0;
        }

        return (int) db_connect()->table('staff_schedules')
            ->where('branch_id', $branchId)
            ->where('schedule_date', date('Y-m-d'))
            ->whereIn('status', ['scheduled', 'assigned'])
            ->countAllResults();
    }

    private function getPaymentAlerts(int $branchId, array $memberStats, array $serviceRequestCounts): array
    {
        if ($branchId <= 0) {
            return [];
        }

        $db = db_connect();
        $pendingPayments = (int) $db->table('payments pay')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->where('pay.status', 'pending')
            ->countAllResults();

        $unassignedServices = 0;
        if ($this->serviceCalendarExists()) {
            $unassignedServices = (int) $db->table('service_calendar')
                ->where('branch_id', $branchId)
                ->where('event_date', date('Y-m-d'))
                ->where('status', 'scheduled')
                ->groupStart()
                    ->where('assigned_staff_ids', null)
                    ->orWhere('assigned_staff_ids', '[]')
                ->groupEnd()
                ->countAllResults();
        }

        $alerts = [];

        if ($pendingPayments > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Payments Awaiting Verification',
                'detail' => sprintf('%d cash / digital payments are pending branch verification.', $pendingPayments),
            ];
        }

        if (($serviceRequestCounts['pending'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Service Application Reviews',
                'detail' => sprintf('%d service applications are pending review.', $serviceRequestCounts['pending']),
            ];
        }

        if ($unassignedServices > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Unassigned Service Events',
                'detail' => sprintf('%d scheduled events are missing crew assignments.', $unassignedServices),
            ];
        }

        if (($memberStats['total_delinquent'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'secondary',
                'title' => 'Delinquent Accounts',
                'detail' => sprintf('%d members are delinquent and require collection follow-up.', $memberStats['total_delinquent']),
            ];
        }

        return $alerts;
    }

    private function getRecentBranchActivity(int $branchId, int $limit = 8): array
    {
        if ($branchId <= 0) {
            return [];
        }

        return db_connect()->table('activity_logs al')
            ->select('al.*, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = al.user_id', 'left')
            ->where('u.branch_id', $branchId)
            ->orderBy('al.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    private function getPaymentAnalytics(int $branchId, int $months = 6): array
    {
        if ($branchId <= 0) {
            return [
                'months' => [],
                'totals' => [],
                'method_breakdown' => [],
            ];
        }

        $db = db_connect();
        $startDate = (new \DateTime(sprintf('first day of -%d months', $months - 1)))->format('Y-m-01');
        $labels = [];
        $totals = [];

        for ($monthOffset = 0; $monthOffset < $months; $monthOffset++) {
            $date = (new \DateTime($startDate))->modify(sprintf('+%d months', $monthOffset));
            $label = $date->format('Y-m');
            $labels[$label] = $date->format('M Y');
            $totals[$label] = 0.0;
        }

        $result = $db->table('payments pay')
            ->select("DATE_FORMAT(pay.payment_date, '%Y-%m') AS month, SUM(pay.amount) AS total")
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->where('pay.status', 'approved')
            ->where('pay.payment_date >=', $startDate)
            ->groupBy('month')
            ->get()
            ->getResultArray();

        foreach ($result as $row) {
            if (array_key_exists($row['month'], $totals)) {
                $totals[$row['month']] = (float) $row['total'];
            }
        }

        $methodRows = $db->table('payments pay')
            ->select('pay.payment_method, SUM(pay.amount) AS total')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->where('pay.status', 'approved')
            ->where('DATE_FORMAT(pay.payment_date, "%Y-%m")', date('Y-m'))
            ->groupBy('pay.payment_method')
            ->get()
            ->getResultArray();

        $methodBreakdown = [];
        $monthlyTotal = array_sum($totals);
        foreach ($methodRows as $row) {
            $methodBreakdown[] = [
                'method' => $row['payment_method'] ?: 'Other',
                'total' => (float) $row['total'],
                'share' => $monthlyTotal > 0 ? round(((float) $row['total'] / $monthlyTotal) * 100, 0) : 0,
            ];
        }

        return [
            'months' => $labels,
            'totals' => $totals,
            'method_breakdown' => $methodBreakdown,
        ];
    }
}
