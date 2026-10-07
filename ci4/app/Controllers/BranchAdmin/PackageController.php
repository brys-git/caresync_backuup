<?php

namespace App\Controllers\BranchAdmin;

use App\Controllers\BaseController;
use App\Models\NotificationModel;
use App\Models\PackageItemModel;
use App\Models\PackageModel;
use App\Models\PackageVersionModel;
use App\Models\ServiceListModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class PackageController extends BaseController
{
    private PackageModel $packageModel;
    private PackageItemModel $packageItemModel;
    private PackageVersionModel $packageVersionModel;
    private ServiceListModel $serviceListModel;

    public function __construct()
    {
        $this->packageModel = new PackageModel();
        $this->packageItemModel = new PackageItemModel();
        $this->packageVersionModel = new PackageVersionModel();
        $this->serviceListModel = new ServiceListModel();
    }

    public function index(): string
    {
        $this->ensureBranchAdminAccess();

        return view('branch_admin/service_package/index', [
            'page_title' => 'Service / Package Management',
            'active_tab' => 'packages',
            'packages' => $this->packageModel->orderBy('package_name', 'ASC')->findAll(),
            'role_layout' => 'layouts/branch_admin',
        ]);
    }

    public function create(): string
    {
        $this->ensureBranchAdminAccess();

        return view('branch_admin/packages/create', [
            'service_list' => $this->serviceListModel->where('is_available', 1)->orderBy('service_name', 'ASC')->findAll(),
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Create Package',
            'page_sub' => 'Define a package offering with versioned pricing',
        ]);
    }

    public function store()
    {
        $this->ensureBranchAdminAccess();

        $rules = [
            'package_name' => 'required|max_length[100]',
            'description' => 'permit_empty|max_length[500]',
            'base_price' => 'required|decimal|greater_than[0]',
            'is_customizable' => 'required|in_list[0,1]',
            'initial_effective_date' => 'required|valid_date',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $serviceListIds = array_values(array_filter(array_map('intval', (array) $this->request->getPost('service_list_id')), static fn (int $serviceListId): bool => $serviceListId > 0));

            if (empty($serviceListIds)) {
                throw new \RuntimeException('At least one package service is required.');
            }

            $pendingSaved = $db->table('pending_packages')->insert([
                'package_name' => trim((string) $this->request->getPost('package_name')),
                'description' => trim((string) $this->request->getPost('description')),
                'base_price' => (string) $this->request->getPost('base_price'),
                'is_customizable' => (int) $this->request->getPost('is_customizable'),
                'initial_effective_date' => (string) $this->request->getPost('initial_effective_date'),
                'service_list_ids' => json_encode($serviceListIds),
                'created_by' => (int) session('user_id'),
            ]);

            if (! $pendingSaved) {
                throw new \RuntimeException('Failed to submit package request for approval.');
            }

            $db->transCommit();

            $this->notifySystemAdmins('New package pending approval from branch admin.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/branch-admin/service-package/packages')->with('success', 'Package request submitted for approval.');
    }

    public function view(int $id): string
    {
        $this->ensureBranchAdminAccess();

        $package = $this->packageModel->find($id);

        if (! $package) {
            throw PageNotFoundException::forPageNotFound();
        }

        $package['items'] = $this->packageItemModel->where('package_id', $id)->orderBy('item_id', 'ASC')->findAll();
        $package['versions'] = $this->packageVersionModel->where('package_id', $id)->orderBy('effective_date', 'DESC')->orderBy('version_id', 'DESC')->findAll();

        return view('branch_admin/service_package/packages_view', [
            'page_title' => 'Package Details',
            'package' => $package,
            'service_list' => $this->serviceListModel->where('is_available', 1)->orderBy('service_name', 'ASC')->findAll(),
            'role_layout' => 'layouts/branch_admin',
        ]);
    }

    public function edit(int $id): string
    {
        $this->ensureBranchAdminAccess();

        $package = $this->packageModel->find($id);

        if (! $package) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('branch_admin/service_package/packages_edit', [
            'page_title' => 'Edit Package',
            'package' => $package,
            'service_list' => $this->serviceListModel->where('is_available', 1)->orderBy('service_name', 'ASC')->findAll(),
            'role_layout' => 'layouts/branch_admin',
        ]);
    }

    public function update(int $id)
    {
        $this->ensureBranchAdminAccess();

        $package = $this->packageModel->find($id);

        if (! $package) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'package_name' => 'required|max_length[100]',
            'description' => 'permit_empty|max_length[500]',
            'is_customizable' => 'required|in_list[0,1]',
            'new_price' => 'permit_empty|decimal|greater_than[0]',
            'effective_date' => 'permit_empty|valid_date',
            'version_status' => 'permit_empty|in_list[active,inactive]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $this->packageModel->update($id, [
                'package_name' => trim((string) $this->request->getPost('package_name')),
                'description' => trim((string) $this->request->getPost('description')),
                'is_customizable' => (int) $this->request->getPost('is_customizable'),
            ]);

            $newPrice = trim((string) $this->request->getPost('new_price'));
            if ($newPrice !== '') {
                $this->packageVersionModel->insert([
                    'package_id' => $id,
                    'price' => $newPrice,
                    'effective_date' => (string) $this->request->getPost('effective_date'),
                    'status' => (string) ($this->request->getPost('version_status') ?: 'active'),
                ]);
            }

            $newServiceIds = array_map('intval', (array) $this->request->getPost('new_service_list_id'));

            foreach ($newServiceIds as $serviceListId) {
                if ($serviceListId <= 0) {
                    continue;
                }

                $service = $this->serviceListModel->find($serviceListId);
                if (! $service) {
                    continue;
                }

                $this->packageItemModel->insert([
                    'package_id' => $id,
                    'item_name' => (string) ($service['service_name'] ?? ''),
                    'description' => trim((string) ($service['description'] ?? '')),
                ]);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to update package.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/branch-admin/packages/view/' . $id)->with('success', 'Package updated successfully.');
    }

    public function addItem(int $id)
    {
        $this->ensureBranchAdminAccess();

        $package = $this->packageModel->find($id);

        if (! $package) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'service_list_id' => 'required|integer|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $serviceListId = (int) $this->request->getPost('service_list_id');
        $service = $this->serviceListModel->find($serviceListId);
        if (! $service) {
            return redirect()->back()->withInput()->with('error', 'Selected service is invalid.');
        }

        $saved = $this->packageItemModel->insert([
            'package_id' => $id,
            'item_name' => (string) ($service['service_name'] ?? ''),
            'description' => trim((string) ($service['description'] ?? '')),
        ]);

        if (! $saved) {
            return redirect()->back()->withInput()->with('error', 'Failed to add package service.');
        }

        return redirect()->to('/branch-admin/packages/view/' . $id)->with('success', 'Package service added successfully.');
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

    private function notifySystemAdmins(string $message): void
    {
        $admins = (new UserModel())
            ->select('user_id')
            ->where('role_id', 1)
            ->findAll();

        $notificationModel = new NotificationModel();

        foreach ($admins as $admin) {
            $notificationModel->insert([
                'user_id' => (int) ($admin['user_id'] ?? 0),
                'message' => $message,
            ]);
        }
    }
}
