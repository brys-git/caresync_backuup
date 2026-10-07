<?php

namespace App\Controllers\BranchAdmin;

use App\Controllers\BaseController;
use App\Services\StaffManagementService;
use CodeIgniter\Exceptions\PageNotFoundException;

class StaffManagementController extends BaseController
{
    private StaffManagementService $staffManagementService;

    public function __construct()
    {
        $this->staffManagementService = new StaffManagementService();
    }

    public function index(): string
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        $search = cs_search_term($this->request->getGet('search'));

        return view('branch_admin/staff_management/index', [
            'search' => $search,
            'staff' => cs_filter_rows($this->staffManagementService->getBranchStaff($branchId), $search, ['first_name', 'last_name', 'email', 'contact_number', 'username']),
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Staff Management',
        ]);
    }

    public function edit(int $id): string
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        $staff = $this->staffManagementService->getBranchStaffById($branchId, $id);
        if (! $staff) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('branch_admin/staff_management/edit', [
            'staff' => $staff,
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Edit Staff',
        ]);
    }

    public function update(int $id)
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        $staff = $this->staffManagementService->getBranchStaffById($branchId, $id);
        if (! $staff) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'email' => 'required|valid_email|max_length[100]',
            'contact_number' => 'permit_empty|max_length[30]',
            'status' => 'required|in_list[active,inactive]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        try {
            $this->staffManagementService->updateBranchStaff($branchId, $id, [
                'email' => (string) $this->request->getPost('email'),
                'contact_number' => (string) $this->request->getPost('contact_number'),
                'status' => (string) $this->request->getPost('status'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/branch-admin/staff-management')->with('success', 'Staff details updated successfully.');
    }
}
