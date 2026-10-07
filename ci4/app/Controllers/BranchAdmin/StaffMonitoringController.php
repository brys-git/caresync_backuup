<?php

namespace App\Controllers\BranchAdmin;

use App\Controllers\BaseController;
use App\Services\StaffService;
use CodeIgniter\Exceptions\PageNotFoundException;

class StaffMonitoringController extends BaseController
{
    private StaffService $staffService;

    public function __construct()
    {
        $this->staffService = new StaffService();
    }

    public function index(): string
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        $staff = $this->staffService->getStaffByBranch($branchId);
        $search = cs_search_term($this->request->getGet('search'));

        $selectedStaff = null;
        $selectedId = (int) $this->request->getGet('staff_id');
        if ($selectedId > 0) {
            foreach ($staff as $member) {
                if ((int) $member['user_id'] === $selectedId) {
                    $selectedStaff = $member;
                    break;
                }
            }
        }

        return view('branch_admin/staff_monitoring/index', [
            'page_title' => 'Staff Monitoring',
            'active_tab' => 'staff-list',
            'search' => $search,
            'staff' => cs_filter_rows($staff, $search, ['first_name', 'last_name', 'email', 'contact_number', 'username']),
            'selected_staff' => $selectedStaff,
            'role_layout' => 'layouts/branch_admin',
        ]);
    }

    public function assign(): string
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('branch_admin/staff_monitoring/index', [
            'page_title' => 'Staff Monitoring',
            'active_tab' => 'assign',
            'staff' => $this->staffService->getStaffByBranch($branchId),
            'services' => $this->staffService->getServiceOptionsByBranch($branchId),
            'assignments' => $this->staffService->getStaffAssignments($branchId),
            'role_layout' => 'layouts/branch_admin',
        ]);
    }

    public function store()
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'service_id' => 'required|is_natural_no_zero',
            'staff_id' => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/branch-admin/staff-monitoring/assign')
                ->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $serviceId = (int) $this->request->getPost('service_id');
        $staffId = (int) $this->request->getPost('staff_id');

        if (! $this->staffService->isServiceInBranch($serviceId, $branchId)) {
            throw PageNotFoundException::forPageNotFound();
        }

        if (! $this->staffService->isStaffInBranch($staffId, $branchId)) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            $this->staffService->assignStaffToService([
                'service_id' => $serviceId,
                'staff_id' => $staffId,
            ]);
        } catch (\Throwable $e) {
            return redirect()->to('/branch-admin/staff-monitoring/assign')
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()->to('/branch-admin/staff-monitoring/assign')
            ->with('success', 'Staff assigned to service successfully.');
    }

    public function activities(): string
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        $staff = $this->staffService->getStaffByBranch($branchId);
        $activities = $this->staffService->getStaffAssignments($branchId);
        $performance = [];

        foreach ($staff as $member) {
            $memberId = (int) $member['user_id'];
            $performance[$memberId] = $this->staffService->getStaffPerformance($memberId);
        }

        return view('branch_admin/staff_monitoring/index', [
            'page_title' => 'Staff Monitoring',
            'active_tab' => 'activities',
            'activities' => $activities,
            'staff' => $staff,
            'performance' => $performance,
            'role_layout' => 'layouts/branch_admin',
        ]);
    }
}
