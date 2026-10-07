<?php

namespace App\Controllers\BranchAdmin;

use App\Controllers\BaseController;
use App\Models\NotificationModel;
use App\Models\ServiceOfferModel;
use App\Models\UserModel;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use CodeIgniter\Exceptions\PageNotFoundException;

class ServiceOfferController extends BaseController
{
    private ServiceOfferModel $serviceOfferModel;
    private NotificationService $notificationService;
    private ActivityLogService $activityLogService;

    public function __construct()
    {
        $this->serviceOfferModel = new ServiceOfferModel();
        $this->notificationService = new NotificationService();
        $this->activityLogService = new ActivityLogService();
    }

    public function index(): string
    {
        $this->ensureBranchAdminAccess();

        $services = $this->serviceOfferModel
            ->select('service_list_id, service_name, description, base_price, status, is_available')
            ->orderBy('service_list_id', 'DESC')
            ->findAll();

        return view('branch_admin/service_package/index', [
            'page_title' => 'Service / Package Management',
            'active_tab' => 'services',
            'services' => $services,
            'role_layout' => 'layouts/branch_admin',
        ]);
    }

    public function create(): string
    {
        $this->ensureBranchAdminAccess();

        return view('branch_admin/services/create', [
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Create Service',
            'page_sub' => 'Define a service offering for this branch',
        ]);
    }

    public function store()
    {
        $this->ensureBranchAdminAccess();

        $rules = [
            'service_name' => 'required|max_length[120]',
            'description' => 'permit_empty|max_length[500]',
            'base_price' => 'required|decimal|greater_than_equal_to[0]',
            'status' => 'required|in_list[active,inactive]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $pendingSaved = db_connect()->table('pending_services')->insert([
            'service_name' => trim((string) $this->request->getPost('service_name')),
            'description' => trim((string) $this->request->getPost('description')),
            'base_price' => (string) $this->request->getPost('base_price'),
            'requested_status' => (string) $this->request->getPost('status'),
            'created_by' => (int) session('user_id'),
        ]);

        if (! $pendingSaved) {
            return redirect()->back()->withInput()->with('error', 'Failed to submit service request for approval.');
        }

        $this->notifySystemAdmins('New service pending approval from branch admin.');
        $this->activityLogService->log(
            (int) session('user_id'),
            'created',
            'service_offer',
            (int) db_connect()->insertID(),
            'Submitted new service request for approval',
            null,
            [
                'service_name' => trim((string) $this->request->getPost('service_name')),
                'status' => 'pending',
            ]
        );

        return redirect()->to('/branch-admin/service-package/services')->with('success', 'Service request submitted for approval.');
    }

    public function view(int $id): string
    {
        $this->ensureBranchAdminAccess();

        $service = $this->serviceOfferModel->find($id);

        if (! $service) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('branch_admin/service_offers/view', [
            'service' => $service,
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Service Details',
        ]);
    }

    public function edit(int $id): string
    {
        $this->ensureBranchAdminAccess();

        $service = $this->serviceOfferModel->find($id);

        if (! $service) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('branch_admin/service_offers/edit', [
            'service' => $service,
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Edit Service',
        ]);
    }

    public function update(int $id)
    {
        $this->ensureBranchAdminAccess();

        $service = $this->serviceOfferModel->find($id);

        if (! $service) {
            throw PageNotFoundException::forPageNotFound();
        }

        $rules = [
            'service_name' => 'required|max_length[120]',
            'description' => 'permit_empty|max_length[500]',
            'base_price' => 'required|decimal|greater_than_equal_to[0]',
            'status' => 'required|in_list[active,inactive]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $updated = $this->serviceOfferModel->update($id, [
            'service_name' => trim((string) $this->request->getPost('service_name')),
            'description' => trim((string) $this->request->getPost('description')),
            'base_price' => (string) $this->request->getPost('base_price'),
            'status' => (string) $this->request->getPost('status'),
        ]);

        if (! $updated) {
            return redirect()->back()->withInput()->with('error', 'Failed to update service offer.');
        }

        return redirect()->to('/branch-admin/services/view/' . $id)->with('success', 'Service offer updated successfully.');
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

        foreach ($admins as $admin) {
            $this->notificationService->notify((int) ($admin['user_id'] ?? 0), $message, 'service_approved');
        }
    }
}
