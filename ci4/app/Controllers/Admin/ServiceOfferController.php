<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\NotificationModel;
use App\Models\PackageModel;
use App\Models\PackageVersionModel;
use App\Models\ServiceListModel;
use App\Models\UserModel;
use App\Services\ActivityLogService;
use App\Services\NotificationService;

class ServiceOfferController extends BaseController
{
    /**
     * @var array<string, bool>
     */
    private array $columnExistsCache = [];
    private NotificationService $notificationService;
    private ActivityLogService $activityLogService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
        $this->activityLogService = new ActivityLogService();
    }

    public function index(): string
    {
        $this->ensureAdminAccess();

        $db = db_connect();
        $tab = (string) $this->request->getGet('tab');
        $approvalTab = (string) $this->request->getGet('approval_tab');

        if (! in_array($tab, ['packages', 'services', 'approval'], true)) {
            $tab = 'packages';
        }

        if (! in_array($approvalTab, ['services', 'packages'], true)) {
            $approvalTab = 'services';
        }

        $serviceHasAvailability = $this->tableHasColumn('service_list', 'is_available');
        $serviceHasStatus = $this->tableHasColumn('service_list', 'status');
        $packageHasAvailability = $this->tableHasColumn('packages', 'is_available');
        $packageHasStatus = $this->tableHasColumn('packages', 'status');

        $serviceSelect = 'service_list_id, service_name, description, base_price';
        if ($serviceHasStatus) {
            $serviceSelect .= ', status';
        }
        if ($serviceHasAvailability) {
            $serviceSelect .= ', is_available';
        }

        $packageSelect = 'package_id, package_name, description, base_price, is_customizable';
        if ($packageHasStatus) {
            $packageSelect .= ', status';
        }
        if ($packageHasAvailability) {
            $packageSelect .= ', is_available';
        }

        $services = (new ServiceListModel())
            ->select($serviceSelect)
            ->orderBy('service_name', 'ASC')
            ->findAll();

        $packages = (new PackageModel())
            ->select($packageSelect)
            ->orderBy('package_name', 'ASC')
            ->findAll();

        $search = cs_search_term($this->request->getGet('search'));
        $services = cs_filter_rows($services, $search, ['service_name', 'description']);
        $packages = cs_filter_rows($packages, $search, ['package_name', 'description']);

        $packageVersions = $db->table('package_versions pv')
            ->select('pv.version_id, pv.package_id, pv.price, pv.effective_date, pv.status, p.package_name')
            ->join('packages p', 'p.package_id = pv.package_id', 'inner')
            ->orderBy('pv.version_id', 'DESC')
            ->get()
            ->getResultArray();

        $pendingServices = $db->table('pending_services ps')
            ->select('ps.pending_service_id, ps.service_name, ps.description, ps.base_price, ps.requested_status, ps.status, ps.created_at, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = ps.created_by', 'left')
            ->orderBy('ps.created_at', 'DESC')
            ->get()
            ->getResultArray();

        $pendingPackages = $db->table('pending_packages pp')
            ->select('pp.pending_package_id, pp.package_name, pp.description, pp.base_price, pp.is_customizable, pp.initial_effective_date, pp.service_list_ids, pp.status, pp.created_at, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = pp.created_by', 'left')
            ->orderBy('pp.created_at', 'DESC')
            ->get()
            ->getResultArray();

        return view('admin/service_offer/index', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'Service Offer',
            'page_sub' => 'Create live packages and services, then review branch-admin requests in one place.',
            'tab' => $tab,
            'search' => $search,
            'approval_tab' => $approvalTab,
            'services' => $services,
            'packages' => $packages,
            'package_versions' => $packageVersions,
            'pending_services' => $pendingServices,
            'pending_packages' => $pendingPackages,
        ]);
    }

    public function storePackage()
    {
        $this->ensureAdminAccess();

        $rules = [
            'package_name' => 'required|max_length[100]',
            'base_price' => 'required|decimal',
            'is_customizable' => 'required|in_list[0,1]',
            'initial_effective_date' => 'required|valid_date',
            'initial_version_status' => 'required|in_list[active,inactive]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $packageData = [
                'package_name' => trim((string) $this->request->getPost('package_name')),
                'description' => trim((string) $this->request->getPost('description')),
                'base_price' => (string) $this->request->getPost('base_price'),
                'is_customizable' => (int) $this->request->getPost('is_customizable'),
            ];

            if ($this->tableHasColumn('packages', 'is_available')) {
                $packageData['is_available'] = 1;
            }

            $packageId = (int) (new PackageModel())->insert($packageData, true);

            if ($packageId <= 0) {
                throw new \RuntimeException('Failed to create package.');
            }

            $versionId = (int) (new PackageVersionModel())->insert([
                'package_id' => $packageId,
                'price' => (string) $this->request->getPost('base_price'),
                'effective_date' => (string) $this->request->getPost('initial_effective_date'),
                'status' => (string) $this->request->getPost('initial_version_status'),
            ], true);

            if ($versionId <= 0) {
                throw new \RuntimeException('Failed to create initial price version.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/admin/service-offer?tab=packages')->with('success', 'Package created successfully.');
    }

    public function storeService()
    {
        $this->ensureAdminAccess();

        $rules = [
            'service_name' => 'required|max_length[120]',
            'description' => 'permit_empty|max_length[500]',
            'base_price' => 'required|decimal|greater_than_equal_to[0]',
            'status' => 'required|in_list[active,inactive]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $serviceData = [
            'service_name' => trim((string) $this->request->getPost('service_name')),
            'description' => trim((string) $this->request->getPost('description')),
            'base_price' => (string) $this->request->getPost('base_price'),
            'status' => (string) $this->request->getPost('status'),
        ];

        if ($this->tableHasColumn('service_list', 'is_available')) {
            $serviceData['is_available'] = 1;
        }

        $serviceId = (int) (new ServiceListModel())->insert($serviceData, true);

        if ($serviceId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Failed to create service.');
        }

        return redirect()->to('/admin/service-offer?tab=services')->with('success', 'Service created successfully.');
    }

    public function approveService(int $id)
    {
        $this->ensureAdminAccess();

        $db = db_connect();
        $pending = $db->table('pending_services')->where('pending_service_id', $id)->get()->getRowArray();

        if (! $pending || ($pending['status'] ?? '') !== 'pending') {
            return redirect()->back()->with('error', 'Pending service not found or already processed.');
        }

        $serviceData = [
            'service_name' => (string) $pending['service_name'],
            'description' => (string) ($pending['description'] ?? ''),
            'base_price' => (string) ($pending['base_price'] ?? '0.00'),
            'status' => (string) ($pending['requested_status'] ?? 'active'),
        ];

        if ($this->tableHasColumn('service_list', 'is_available')) {
            $serviceData['is_available'] = 1;
        }

        $serviceId = (int) (new ServiceListModel())->insert($serviceData, true);

        if ($serviceId <= 0) {
            return redirect()->back()->with('error', 'Failed to approve service.');
        }

        $db->table('pending_services')->where('pending_service_id', $id)->update(['status' => 'approved']);
        $this->notificationService->notify((int) $pending['created_by'], 'Your service request has been approved.', 'service_approved');
        $this->activityLogService->log(
            (int) session('user_id'),
            'approved',
            'service_offer',
            $serviceId,
            'Approved pending service request',
            ['status' => 'pending'],
            ['status' => 'approved']
        );

        return redirect()->to('/admin/service-offer?tab=approval')->with('success', 'Service approved successfully.');
    }

    public function rejectService(int $id)
    {
        $this->ensureAdminAccess();

        $pending = db_connect()->table('pending_services')->where('pending_service_id', $id)->get()->getRowArray();

        if (! $pending || ($pending['status'] ?? '') !== 'pending') {
            return redirect()->back()->with('error', 'Pending service not found or already processed.');
        }

        db_connect()->table('pending_services')->where('pending_service_id', $id)->update(['status' => 'rejected']);
        $this->notificationService->notify((int) $pending['created_by'], 'Your service request has been rejected.', 'service_rejected');
        $this->activityLogService->log(
            (int) session('user_id'),
            'rejected',
            'service_offer',
            $id,
            'Rejected pending service request',
            ['status' => 'pending'],
            ['status' => 'rejected']
        );

        return redirect()->to('/admin/service-offer?tab=approval')->with('success', 'Service rejected successfully.');
    }

    public function approvePackage(int $id)
    {
        $this->ensureAdminAccess();

        $db = db_connect();
        $pending = $db->table('pending_packages')->where('pending_package_id', $id)->get()->getRowArray();

        if (! $pending || ($pending['status'] ?? '') !== 'pending') {
            return redirect()->back()->with('error', 'Pending package not found or already processed.');
        }

        $serviceIds = $this->decodeServiceListIds((string) ($pending['service_list_ids'] ?? ''));
        if (empty($serviceIds)) {
            return redirect()->back()->with('error', 'A package must include at least one approved service.');
        }

        $db->transBegin();

        try {
            $packageData = [
                'package_name' => (string) $pending['package_name'],
                'description' => (string) ($pending['description'] ?? ''),
                'base_price' => (string) ($pending['base_price'] ?? '0.00'),
                'is_customizable' => (int) ($pending['is_customizable'] ?? 1),
            ];

            if ($this->tableHasColumn('packages', 'is_available')) {
                $packageData['is_available'] = 1;
            }

            $packageId = (int) (new PackageModel())->insert($packageData, true);

            if ($packageId <= 0) {
                throw new \RuntimeException('Failed to create package.');
            }

            (new PackageVersionModel())->insert([
                'package_id' => $packageId,
                'price' => (string) ($pending['base_price'] ?? '0.00'),
                'effective_date' => (string) ($pending['initial_effective_date'] ?: date('Y-m-d')),
                'status' => 'active',
            ]);

            foreach ($serviceIds as $serviceId) {
                $service = $db->table('service_list')->where('service_list_id', $serviceId)->get()->getRowArray();
                if (! $service) {
                    continue;
                }

                $db->table('package_items')->insert([
                    'package_id' => $packageId,
                    'item_name' => (string) ($service['service_name'] ?? ''),
                    'description' => (string) ($service['description'] ?? ''),
                ]);
            }

            $db->table('pending_packages')->where('pending_package_id', $id)->update(['status' => 'approved']);
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->notificationService->notify((int) $pending['created_by'], 'Your package request has been approved.', 'service_approved');
        $this->activityLogService->log(
            (int) session('user_id'),
            'approved',
            'package',
            $packageId,
            'Approved pending package request',
            ['status' => 'pending'],
            ['status' => 'approved']
        );

        return redirect()->to('/admin/service-offer?tab=approval')->with('success', 'Package approved successfully.');
    }

    public function rejectPackage(int $id)
    {
        $this->ensureAdminAccess();

        $pending = db_connect()->table('pending_packages')->where('pending_package_id', $id)->get()->getRowArray();

        if (! $pending || ($pending['status'] ?? '') !== 'pending') {
            return redirect()->back()->with('error', 'Pending package not found or already processed.');
        }

        db_connect()->table('pending_packages')->where('pending_package_id', $id)->update(['status' => 'rejected']);
        $this->notificationService->notify((int) $pending['created_by'], 'Your package request has been rejected.', 'service_rejected');
        $this->activityLogService->log(
            (int) session('user_id'),
            'rejected',
            'package',
            $id,
            'Rejected pending package request',
            ['status' => 'pending'],
            ['status' => 'rejected']
        );

        return redirect()->to('/admin/service-offer?tab=approval')->with('success', 'Package rejected successfully.');
    }

    private function decodeServiceListIds(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('intval', $decoded), static fn (int $serviceId): bool => $serviceId > 0));
        }

        $parts = array_map('trim', explode(',', $value));

        return array_values(array_filter(array_map('intval', $parts), static fn (int $serviceId): bool => $serviceId > 0));
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . ':' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $exists = db_connect()->fieldExists($column, $table);
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    private function ensureAdminAccess(): void
    {
        $roleId = (int) session()->get('role_id');
        $roleName = strtolower((string) session()->get('role'));

        if ($roleId !== 1 && $roleName !== 'admin') {
            redirect()->to('/unauthorized')->send();
            exit;
        }
    }
}