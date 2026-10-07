<?php

namespace App\Controllers\BranchAdmin;

use App\Controllers\BaseController;
use App\Models\ServiceModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class ServiceController extends BaseController
{
    private ServiceModel $serviceModel;

    public function __construct()
    {
        $this->serviceModel = new ServiceModel();
    }

    public function index(): string
    {
        $this->ensureBranchAdminAccess();

        $branchId = (int) session('branch_id');

        $ongoingServices = $branchId > 0
            ? db_connect()->table('services s')
                ->select('s.service_id, s.plan_holder_id, s.service_list_id, s.package_id, s.service_date, s.status, s.assigned_staff, u.first_name, u.last_name, p.package_name, sl.service_name, st.first_name AS staff_first_name, st.last_name AS staff_last_name')
                ->join('plan_holders ph', 'ph.plan_holder_id = s.plan_holder_id', 'inner')
                ->join('users u', 'u.user_id = ph.user_id', 'inner')
                ->join('packages p', 'p.package_id = s.package_id', 'left')
                ->join('service_list sl', 'sl.service_list_id = s.service_list_id', 'left')
                ->join('users st', 'st.user_id = s.assigned_staff', 'left')
                ->where('s.branch_id', $branchId)
                ->orderBy('s.service_date', 'DESC')
                ->orderBy('s.service_id', 'DESC')
                ->get()
                ->getResultArray()
            : [];

        $staff = $branchId > 0
            ? db_connect()->table('users')
                ->select('user_id, first_name, last_name')
                ->where('role_id', 3)
                ->where('branch_id', $branchId)
                ->orderBy('first_name', 'ASC')
                ->orderBy('last_name', 'ASC')
                ->get()
                ->getResultArray()
            : [];

        return view('branch_admin/service_package/index', [
            'page_title' => 'Service / Package Management',
            'active_tab' => 'ongoing',
            'ongoing_services' => $ongoingServices,
            'staff' => $staff,
            'role_layout' => 'layouts/branch_admin',
        ]);
    }

    public function create(): string
    {
        $this->ensureBranchAdminAccess();

        $branchId = (int) session('branch_id');

        $planHolders = $branchId > 0
            ? db_connect()->table('plan_holders ph')
                ->select('ph.plan_holder_id, u.first_name, u.last_name')
                ->join('users u', 'u.user_id = ph.user_id', 'inner')
                ->where('ph.branch_id', $branchId)
                ->where('ph.status', 'active')
                ->orderBy('u.first_name', 'ASC')
                ->orderBy('u.last_name', 'ASC')
                ->get()
                ->getResultArray()
            : [];

        $packages = db_connect()->table('packages')
            ->select('package_id, package_name, base_price')
            ->where('is_available', 1)
            ->orderBy('package_name', 'ASC')
            ->get()
            ->getResultArray();

        $serviceList = db_connect()->table('service_list')
            ->select('service_list_id, service_name, base_price, status')
            ->where('is_available', 1)
            ->where('status', 'active')
            ->orderBy('service_name', 'ASC')
            ->get()
            ->getResultArray();

        $packageServiceRows = [];
        $db = db_connect();
        if ($db->tableExists('package_services') && $db->tableExists('service_list')) {
            $packageServiceFields = $db->getFieldNames('package_services');
            if (in_array('service_list_id', $packageServiceFields, true)) {
                $packageServiceRows = $db->table('package_services ps')
                    ->select('ps.package_id, sl.service_list_id, sl.service_name, sl.base_price')
                    ->join('service_list sl', 'sl.service_list_id = ps.service_list_id', 'inner')
                    ->where('sl.is_available', 1)
                    ->where('sl.status', 'active')
                    ->orderBy('sl.service_name', 'ASC')
                    ->get()
                    ->getResultArray();
            } elseif (in_array('service_id', $packageServiceFields, true)) {
                $packageServiceRows = $db->table('package_services ps')
                    ->select('ps.package_id, sl.service_list_id, sl.service_name, sl.base_price')
                    ->join('service_list sl', 'sl.service_list_id = ps.service_id', 'inner')
                    ->where('sl.is_available', 1)
                    ->where('sl.status', 'active')
                    ->orderBy('sl.service_name', 'ASC')
                    ->get()
                    ->getResultArray();
            }
        }

        $packageServiceMap = [];
        foreach ($packageServiceRows as $row) {
            $packageId = (int) ($row['package_id'] ?? 0);
            if ($packageId <= 0) {
                continue;
            }

            if (! isset($packageServiceMap[$packageId])) {
                $packageServiceMap[$packageId] = [];
            }

            $packageServiceMap[$packageId][] = [
                'service_list_id' => (int) ($row['service_list_id'] ?? 0),
                'service_name' => (string) ($row['service_name'] ?? ''),
                'base_price' => (float) ($row['base_price'] ?? 0),
            ];
        }

        return view('branch_admin/service_package/index', [
            'page_title' => 'Service / Package Management',
            'active_tab' => 'schedule',
            'plan_holders' => $planHolders,
            'packages' => $packages,
            'service_list' => $serviceList,
            'package_service_map' => $packageServiceMap,
            'role_layout' => 'layouts/branch_admin',
        ]);
    }

    public function store()
    {
        $this->ensureBranchAdminAccess();

        $branchId = (int) session('branch_id');

        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'plan_holder_id' => 'required|is_natural_no_zero',
            'package_id' => 'required|is_natural_no_zero',
            'service_list_id' => 'permit_empty|is_natural_no_zero',
            'service_date' => 'required|valid_date',
            'service_time' => 'permit_empty',
            'burial_location' => 'permit_empty|max_length[255]',
            'notes' => 'permit_empty|max_length[500]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $planHolder = db_connect()->table('plan_holders')->where('plan_holder_id', (int) $this->request->getPost('plan_holder_id'))->get()->getRowArray();
        $package = db_connect()->table('packages')->where('package_id', (int) $this->request->getPost('package_id'))->where('is_available', 1)->get()->getRowArray();

        if (! $planHolder || (int) ($planHolder['branch_id'] ?? 0) !== $branchId || ! $package) {
            return redirect()->back()->withInput()->with('error', 'Invalid plan holder or package selection.');
        }

        $saved = $this->serviceModel->insert([
            'plan_holder_id' => (int) $this->request->getPost('plan_holder_id'),
            'branch_id' => $branchId,
            'service_list_id' => $this->request->getPost('service_list_id') ? (int) $this->request->getPost('service_list_id') : null,
            'package_id' => (int) $this->request->getPost('package_id'),
            'total_cost' => (string) ($package['base_price'] ?? '0.00'),
            'service_date' => (string) $this->request->getPost('service_date'),
            'service_time' => trim((string) $this->request->getPost('service_time')) ?: null,
            'burial_location' => trim((string) $this->request->getPost('burial_location')) ?: null,
            'assigned_staff' => null,
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'status' => 'pending',
        ]);

        if (! $saved) {
            return redirect()->back()->withInput()->with('error', 'Failed to schedule service.');
        }

        return redirect()->to('/branch-admin/service-package/ongoing')->with('success', 'Service scheduled successfully.');
    }

    public function updateStatus(int $id)
    {
        $this->ensureBranchAdminAccess();

        $branchId = (int) session('branch_id');
        $service = $this->serviceModel->find($id);

        if (! $service || (int) ($service['branch_id'] ?? 0) !== $branchId) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'status' => 'required|in_list[pending,ongoing,completed,cancelled]',
            'assigned_staff' => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $assignedStaff = trim((string) $this->request->getPost('assigned_staff'));
        if ($assignedStaff !== '') {
            $staff = db_connect()->table('users')
                ->where('user_id', (int) $assignedStaff)
                ->where('role_id', 3)
                ->where('branch_id', $branchId)
                ->get()
                ->getRowArray();

            if (! $staff) {
                return redirect()->back()->with('error', 'Assigned staff is invalid for this branch.');
            }
        }

        $updated = $this->serviceModel->update($id, [
            'status' => (string) $this->request->getPost('status'),
            'assigned_staff' => $assignedStaff === '' ? null : (int) $assignedStaff,
        ]);

        if (! $updated) {
            return redirect()->back()->with('error', 'Failed to update service status.');
        }

        return redirect()->to('/branch-admin/service-package/ongoing')->with('success', 'Service status updated successfully.');
    }

    private function ensureBranchAdminAccess(): void
    {
        $roleId = (int) session()->get('role_id');
        $roleName = strtolower((string) session()->get('role'));

        if ($roleId !== 2 && $roleName !== 'branch admin') {
            redirect()->to('/unauthorized')->send();
            exit;
        }
    }
}
