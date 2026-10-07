<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\NotificationModel;
use App\Models\PackageModel;
use App\Models\ServiceListModel;
use App\Services\ActivityLogService;
use App\Services\NotificationService;

class BranchManagementController extends BaseController
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
        if (! in_array($tab, ['availability', 'transactions', 'contribution', 'approval'], true)) {
            $tab = 'availability';
        }

        $approvalTab = (string) $this->request->getGet('approval_tab');
        if (! in_array($approvalTab, ['services', 'packages'], true)) {
            $approvalTab = 'services';
        }

        $branchId = (int) $this->request->getGet('branch_id');
        $dateFrom = trim((string) $this->request->getGet('date_from'));
        $dateTo = trim((string) $this->request->getGet('date_to'));

        $branches = $db->table('branches')
            ->select('branch_id, branch_name, status')
            ->orderBy('branch_name', 'ASC')
            ->get()
            ->getResultArray();

        $serviceHasAvailability = $this->tableHasColumn('service_list', 'is_available');
        $serviceHasStatus = $this->tableHasColumn('service_list', 'status');
        $packageHasAvailability = $this->tableHasColumn('packages', 'is_available');
        $packageHasStatus = $this->tableHasColumn('packages', 'status');

        $serviceSelect = 'service_list_id, service_name, description';
        if ($serviceHasAvailability) {
            $serviceSelect .= ', is_available';
        }
        if ($serviceHasStatus) {
            $serviceSelect .= ', status';
        }

        $packageSelect = 'package_id, package_name, description, is_customizable, base_price';
        if ($packageHasAvailability) {
            $packageSelect .= ', is_available';
        }
        if ($packageHasStatus) {
            $packageSelect .= ', status';
        }

        $serviceRows = (new ServiceListModel())
            ->select($serviceSelect)
            ->orderBy('service_name', 'ASC')
            ->findAll();

        $packageRows = (new PackageModel())
            ->select($packageSelect)
            ->orderBy('package_name', 'ASC')
            ->findAll();

        $availabilityRows = $this->buildAvailabilityRows(
            $branches,
            $serviceRows,
            $packageRows,
            $branchId,
            $serviceHasAvailability,
            $packageHasAvailability
        );
        $transactions = $this->buildTransactionRows($branchId, $dateFrom, $dateTo);
        $contributions = $this->buildContributionRows($branchId);

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

        return view('admin/branch_management/index', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'Branch Management',
            'page_sub' => 'Monitor branch availability, operations, contribution, and approval queues.',
            'tab' => $tab,
            'approval_tab' => $approvalTab,
            'branches' => $branches,
            'selected_branch_id' => $branchId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'availability_rows' => $availabilityRows,
            'transactions' => $transactions,
            'contributions' => $contributions,
            'pending_services' => $pendingServices,
            'pending_packages' => $pendingPackages,
        ]);
    }

    public function toggleAvailability()
    {
        $this->ensureAdminAccess();

        $rules = [
            'item_type' => 'required|in_list[service,package]',
            'item_id' => 'required|is_natural_no_zero',
            'is_available' => 'required|in_list[0,1]',
            'return_tab' => 'permit_empty|max_length[20]',
            'branch_id' => 'permit_empty|is_natural',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $itemType = (string) $this->request->getPost('item_type');
        $itemId = (int) $this->request->getPost('item_id');
        $isAvailable = (int) $this->request->getPost('is_available');

        $table = $itemType === 'service' ? 'service_list' : 'packages';
        $primaryKey = $itemType === 'service' ? 'service_list_id' : 'package_id';

        $db = db_connect();
        $payload = [];

        if ($this->tableHasColumn($table, 'is_available')) {
            $payload['is_available'] = $isAvailable;
        } elseif ($this->tableHasColumn($table, 'status')) {
            $payload['status'] = $isAvailable === 1 ? 'active' : 'inactive';
        } else {
            return redirect()->back()->with('error', 'Availability control is not supported by the current database schema.');
        }

        $updated = $db->table($table)
            ->where($primaryKey, $itemId)
            ->update($payload);

        if (! $updated) {
            return redirect()->back()->with('error', 'Failed to update availability.');
        }

        return redirect()->to($this->buildReturnUrl((string) $this->request->getPost('return_tab')))->with('success', 'Availability updated successfully.');
    }

    public function exportTransactions()
    {
        $this->ensureAdminAccess();

        $branchId = (int) $this->request->getGet('branch_id');
        $dateFrom = trim((string) $this->request->getGet('date_from'));
        $dateTo = trim((string) $this->request->getGet('date_to'));
        $rows = $this->buildTransactionRows($branchId, $dateFrom, $dateTo);

        $filename = 'branch-transactions-' . date('Ymd-His') . '.csv';
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, ['Branch Name', 'Plan Holder', 'Transaction Type', 'Amount', 'Date', 'Status']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['branch_name'] ?? '',
                $row['plan_holder'] ?? '',
                $row['transaction_type'] ?? '',
                $row['amount'] ?? '',
                $row['transaction_date'] ?? '',
                $row['status'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csv);
    }

    public function approveService(int $id)
    {
        $this->ensureAdminAccess();

        $pending = db_connect()->table('pending_services')
            ->where('pending_service_id', $id)
            ->get()
            ->getRowArray();

        if (! $pending || ($pending['status'] ?? '') !== 'pending') {
            return redirect()->back()->with('error', 'Pending service not found or already processed.');
        }

        $serviceModel = new ServiceListModel();
        $serviceData = [
            'service_name' => (string) $pending['service_name'],
            'description' => (string) ($pending['description'] ?? ''),
            'base_price' => (string) ($pending['base_price'] ?? '0.00'),
            'status' => (string) ($pending['requested_status'] ?? 'active'),
        ];

        if ($this->tableHasColumn('service_list', 'is_available')) {
            $serviceData['is_available'] = 1;
        }

        $serviceId = (int) $serviceModel->insert($serviceData, true);

        if ($serviceId <= 0) {
            return redirect()->back()->with('error', 'Failed to approve service.');
        }

        db_connect()->table('pending_services')
            ->where('pending_service_id', $id)
            ->update(['status' => 'approved']);

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

        return redirect()->to('/admin/branch-management?tab=approval&approval_tab=services')->with('success', 'Service approved successfully.');
    }

    public function rejectService(int $id)
    {
        $this->ensureAdminAccess();

        $pending = db_connect()->table('pending_services')
            ->where('pending_service_id', $id)
            ->get()
            ->getRowArray();

        if (! $pending || ($pending['status'] ?? '') !== 'pending') {
            return redirect()->back()->with('error', 'Pending service not found or already processed.');
        }

        db_connect()->table('pending_services')
            ->where('pending_service_id', $id)
            ->update(['status' => 'rejected']);

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

        return redirect()->to('/admin/branch-management?tab=approval&approval_tab=services')->with('success', 'Service rejected successfully.');
    }

    public function approvePackage(int $id)
    {
        $this->ensureAdminAccess();

        $db = db_connect();
        $pending = $db->table('pending_packages')
            ->where('pending_package_id', $id)
            ->get()
            ->getRowArray();

        if (! $pending || ($pending['status'] ?? '') !== 'pending') {
            return redirect()->back()->with('error', 'Pending package not found or already processed.');
        }

        $serviceIds = $this->decodeServiceListIds((string) ($pending['service_list_ids'] ?? ''));
        if (empty($serviceIds)) {
            return redirect()->back()->with('error', 'A package must include at least one approved service.');
        }

        $db->transBegin();

        try {
            $packageId = (int) $db->table('packages')->insert([
                'package_name' => (string) $pending['package_name'],
                'description' => (string) ($pending['description'] ?? ''),
                'base_price' => (string) ($pending['base_price'] ?? '0.00'),
                'is_customizable' => (int) ($pending['is_customizable'] ?? 1),
            ] + ($this->tableHasColumn('packages', 'is_available') ? ['is_available' => 1] : []), true);

            if ($packageId <= 0) {
                throw new \RuntimeException('Failed to create package.');
            }

            $db->table('package_versions')->insert([
                'package_id' => $packageId,
                'price' => (string) ($pending['base_price'] ?? '0.00'),
                'effective_date' => (string) ($pending['initial_effective_date'] ?: date('Y-m-d')),
                'status' => 'active',
            ]);

            foreach ($serviceIds as $serviceId) {
                $service = $db->table('service_list')
                    ->where('service_list_id', $serviceId)
                    ->get()
                    ->getRowArray();

                if (! $service) {
                    continue;
                }

                $db->table('package_items')->insert([
                    'package_id' => $packageId,
                    'item_name' => (string) ($service['service_name'] ?? ''),
                    'description' => (string) ($service['description'] ?? ''),
                ]);
            }

            $db->table('pending_packages')
                ->where('pending_package_id', $id)
                ->update(['status' => 'approved']);

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

        return redirect()->to('/admin/branch-management?tab=approval&approval_tab=packages')->with('success', 'Package approved successfully.');
    }

    public function rejectPackage(int $id)
    {
        $this->ensureAdminAccess();

        $pending = db_connect()->table('pending_packages')
            ->where('pending_package_id', $id)
            ->get()
            ->getRowArray();

        if (! $pending || ($pending['status'] ?? '') !== 'pending') {
            return redirect()->back()->with('error', 'Pending package not found or already processed.');
        }

        db_connect()->table('pending_packages')
            ->where('pending_package_id', $id)
            ->update(['status' => 'rejected']);

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

        return redirect()->to('/admin/branch-management?tab=approval&approval_tab=packages')->with('success', 'Package rejected successfully.');
    }

    private function buildAvailabilityRows(
        array $branches,
        array $services,
        array $packages,
        int $branchIdFilter,
        bool $serviceHasAvailability,
        bool $packageHasAvailability
    ): array
    {
        if ($branchIdFilter > 0) {
            $branches = array_values(array_filter($branches, static fn (array $branch): bool => (int) ($branch['branch_id'] ?? 0) === $branchIdFilter));
        }

        $rows = [];

        foreach ($branches as $branch) {
            foreach ($services as $service) {
                $serviceIsAvailable = $this->resolveAvailability($service, $serviceHasAvailability);

                $rows[] = [
                    'branch_name' => (string) ($branch['branch_name'] ?? '-'),
                    'item_name' => (string) ($service['service_name'] ?? '-'),
                    'item_type' => 'Service',
                    'item_id' => (int) ($service['service_list_id'] ?? 0),
                    'is_available' => $serviceIsAvailable,
                    'status_label' => $serviceIsAvailable === 1 ? 'Available' : 'Not Available',
                ];
            }

            foreach ($packages as $package) {
                $packageIsAvailable = $this->resolveAvailability($package, $packageHasAvailability);

                $rows[] = [
                    'branch_name' => (string) ($branch['branch_name'] ?? '-'),
                    'item_name' => (string) ($package['package_name'] ?? '-'),
                    'item_type' => 'Package',
                    'item_id' => (int) ($package['package_id'] ?? 0),
                    'is_available' => $packageIsAvailable,
                    'status_label' => $packageIsAvailable === 1 ? 'Available' : 'Not Available',
                ];
            }
        }

        return $rows;
    }

    private function buildTransactionRows(int $branchId, string $dateFrom, string $dateTo): array
    {
        $db = db_connect();

        $paymentQuery = $db->table('payments pay')
            ->select("b.branch_name, CONCAT(u.first_name, ' ', u.last_name) AS plan_holder, 'Payment' AS transaction_type, pay.amount, pay.payment_date AS transaction_date, pay.status", false)
            ->join('plans pl', 'pl.plan_id = pay.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = pl.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = pay.branch_id', 'inner');

        $serviceQuery = $db->table('services s')
            ->select("b.branch_name, CONCAT(u.first_name, ' ', u.last_name) AS plan_holder, 'Service' AS transaction_type, COALESCE(s.total_cost, 0) AS amount, s.service_date AS transaction_date, s.status", false)
            ->join('plan_holders ph', 'ph.plan_holder_id = s.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = s.branch_id', 'inner');

        if ($branchId > 0) {
            $paymentQuery->where('pay.branch_id', $branchId);
            $serviceQuery->where('s.branch_id', $branchId);
        }

        if ($dateFrom !== '') {
            $paymentQuery->where('pay.payment_date >=', $dateFrom);
            $serviceQuery->where('s.service_date >=', $dateFrom);
        }

        if ($dateTo !== '') {
            $paymentQuery->where('pay.payment_date <=', $dateTo);
            $serviceQuery->where('s.service_date <=', $dateTo);
        }

        $rows = array_merge($paymentQuery->get()->getResultArray(), $serviceQuery->get()->getResultArray());

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) ($right['transaction_date'] ?? ''), (string) ($left['transaction_date'] ?? ''));
        });

        return $rows;
    }

    private function buildContributionRows(int $branchId): array
    {
        $db = db_connect();

        $builder = $db->table('payments pay')
            ->select('b.branch_name, COALESCE(SUM(pay.amount), 0) AS total_collected', false)
            ->join('branches b', 'b.branch_id = pay.branch_id', 'inner')
            ->where('pay.status', 'paid')
            ->groupBy('b.branch_id, b.branch_name')
            ->orderBy('b.branch_name', 'ASC');

        if ($branchId > 0) {
            $builder->where('pay.branch_id', $branchId);
        }

        $rows = $builder->get()->getResultArray();

        foreach ($rows as &$row) {
            $totalCollected = (float) ($row['total_collected'] ?? 0);
            $commission = $totalCollected * 0.10;
            $row['staff_commission'] = number_format($commission, 2, '.', '');
            $row['total_remitted'] = number_format($totalCollected - $commission, 2, '.', '');
            $row['total_collected'] = number_format($totalCollected, 2, '.', '');
        }

        return $rows;
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

    private function buildReturnUrl(string $tab): string
    {
        if (! in_array($tab, ['availability', 'transactions', 'contribution', 'approval'], true)) {
            $tab = 'availability';
        }

        return '/admin/branch-management?tab=' . $tab;
    }

    private function resolveAvailability(array $row, bool $hasAvailabilityColumn): int
    {
        if ($hasAvailabilityColumn) {
            return (int) ($row['is_available'] ?? 1) === 1 ? 1 : 0;
        }

        $status = strtolower((string) ($row['status'] ?? 'active'));

        return $status === 'inactive' ? 0 : 1;
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