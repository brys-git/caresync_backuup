<?php

namespace App\Controllers;

use App\Services\AnalyticsService;

/**
 * Analytics Controller
 *
 * Provides analytics and reporting data for dashboards
 * Centralizes analytics logic for Admin, Branch Admin, and Staff roles
 */
class Analytics extends BaseController
{
    private AnalyticsService $analyticsService;

    public function __construct()
    {
        $this->analyticsService = new AnalyticsService();
    }

    /**
     * Admin Analytics
     * System-wide analytics for super admin dashboard
     */
    public function admin(): string
    {
        $roleId = (int) session('role_id');
        if ($roleId !== 1) {
            return redirect()->to('/unauthorized');
        }

        $analytics = $this->analyticsService->getSystemWideAnalytics();

        // Was rendering dashboards/admin - a static "Coming Soon" placeholder
        // that ignored $analytics entirely, even though this data was real
        // and fully computed. Found during the 2026-09-09 system scan; fixed
        // the same way Branch Admin's analytics already was (see below).
        return view('dashboards/admin_analytics', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'System analytics',
            'page_sub' => 'Membership, collections, and branch performance system-wide.',
            'analytics' => $analytics,
        ]);
    }

    /**
     * Branch Admin Analytics
     * Branch-specific analytics for branch admin dashboard
     */
    public function branchAdmin(): string
    {
        $roleId = (int) session('role_id');
        if ($roleId !== 2) {
            return redirect()->to('/unauthorized');
        }

        $branchId = (int) session('branch_id');
        $analytics = $this->analyticsService->getBranchAnalytics($branchId);

        // dashboards/branch_admin.php is the full operations dashboard - it
        // needs a much larger data contract (branch_name, member_stats,
        // payment_alerts, etc.) than this controller builds, and crashed
        // with "Undefined variable $branch_name" on every visit. Analytics
        // has always had its own self-contained $analytics shape; it just
        // needed a view sized to match it.
        return view('dashboards/branch_admin_analytics', [
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Branch analytics',
            'page_sub' => 'Membership, collections, and service activity for your branch.',
            'analytics' => $analytics,
        ]);
    }

    /**
     * Staff Analytics
     * Staff-specific analytics for staff dashboard
     */
    public function staff(): string
    {
        $roleId = (int) session('role_id');
        if ($roleId !== 3) {
            return redirect()->to('/unauthorized');
        }

        $branchId = (int) session('branch_id');
        $analytics = $this->analyticsService->getStaffAnalytics($branchId);

        // Same fix as admin() above - was rendering the placeholder
        // dashboards/staff view instead of a view sized to this data.
        return view('dashboards/staff_analytics', [
            'role_layout' => 'layouts/staff',
            'page_title' => 'My analytics',
            'page_sub' => 'Your service and cash-handling activity.',
            'analytics' => $analytics,
        ]);
    }

    /**
     * Get analytics data as JSON (for AJAX requests)
     */
    public function getJson(string $type = 'system'): string
    {
        $roleId = (int) session('role_id');
        $branchId = (int) session('branch_id');

        $analytics = [];

        if ($type === 'system' && $roleId === 1) {
            $analytics = $this->analyticsService->getSystemWideAnalytics();
        } elseif ($type === 'branch' && in_array($roleId, [2, 3], true)) {
            $analytics = $this->analyticsService->getBranchAnalytics($branchId);
        } elseif ($type === 'staff' && $roleId === 3) {
            $analytics = $this->analyticsService->getStaffAnalytics($branchId);
        } else {
            return $this->response->setJSON(['error' => 'Unauthorized'], 403);
        }

        return $this->response->setJSON($analytics);
    }
}
