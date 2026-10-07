<?php

namespace App\Controllers;

use App\Services\ReportPdfService;
use App\Services\ReportService;

class Reports extends BaseController
{
    public function index(): string
    {
        $roleId = (int) session('role_id');
        $branchId = (int) session('branch_id');
        $reportService = new ReportService();
        $filters = [
            'branch_id' => $roleId === 1 ? 0 : $branchId,
            'date_from' => date('Y-01-01'),
            'date_to' => date('Y-m-d'),
            'year' => (int) date('Y'),
        ];

        $payments = $reportService->getRemittanceReport($filters);
        $branches = $this->buildBranchSummary($branchId, $roleId);
        $services = $this->buildServiceSummary($branchId, $roleId);
        $clients = $this->buildClientSummary($branchId, $roleId);
        $staffActivity = $this->buildStaffSummary($branchId, $roleId);
        $summary = $reportService->getDashboardSummary($filters);

        if ($roleId === 4) {
            $payments = [];
            $branches = [];
            $services = [];
            $clients = [];
            $staffActivity = [];
            $summary = [
                'monthly_collections' => [],
                'payment_breakdown' => ['total_transactions' => 0, 'total_amount' => 0, 'cash_total' => 0, 'gcash_total' => 0],
                'member_status' => ['total_members' => 0, 'active_members' => 0, 'inactive_members' => 0],
                'delinquent_accounts' => [],
                'service_usage' => [],
                'payment_trends' => [],
            ];
        }

        return view('reports/index', [
            'role_layout' => $this->resolveLayoutView(),
            'page_title' => 'Reports',
            'page_sub' => 'Scope: ' . ($roleId === 1 ? 'System-wide' : ($roleId === 2 ? 'Branch' : 'Staff')),
            'payments' => $payments,
            'branches' => $branches,
            'services' => $services,
            'clients' => $clients,
            'staff_activity' => $staffActivity,
            'summary' => $summary,
            'report_scope' => $roleId === 1 ? 'System-wide' : ($roleId === 2 ? 'Branch' : 'Staff'),
            'reports_base_path' => '/admin/reports',
            'show_reports_nav' => $roleId === 1,
        ]);
    }

    /**
     * Panel brief, section 7: Overdue Report.
     */
    public function overdue()
    {
        $filters = ['branch_id' => $this->reportBranchFilter(), 'search' => cs_search_term($this->request->getGet('search'))];
        $reportService = new ReportService();
        $rows = $reportService->getOverdueReport($filters);
        $mode = (string) $this->request->getGet('mode');

        if ($mode === 'csv') {
            return $this->csvResponse(
                'overdue_report',
                ['Plan Holder', 'Unique ID', 'Branch', 'Contact', 'Remaining Balance', 'Months Paid', 'Overdue Months', 'Days Overdue', 'Next Due Date', 'Status'],
                $rows,
                static fn (array $row): array => [
                    trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                    (string) ($row['unique_identifier'] ?? ''),
                    (string) ($row['branch_name'] ?? ''),
                    (string) ($row['contact_number'] ?? ''),
                    number_format((float) ($row['remaining_balance'] ?? 0), 2, '.', ''),
                    (string) ($row['months_paid'] ?? 0),
                    (string) ($row['overdue_months'] ?? 0),
                    (string) ($row['days_overdue'] ?? 0),
                    (string) ($row['next_due_date'] ?? ''),
                    (string) ($row['status_text'] ?? ''),
                ]
            );
        }

        if ($mode === 'pdf' || $mode === 'print') {
            return $this->pdfResponse('overdue', ['rows' => $rows], $filters, $mode === 'print');
        }

        return view('admin/reports/overdue', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'Overdue Report',
            'page_sub' => 'Plan holders behind on their contribution, oldest due date first.',
            'active_report' => 'overdue',
            'filters' => $filters,
            'branches' => $this->branchOptions(),
            'rows' => $rows,
        ]);
    }

    /**
     * Panel brief, section 7: Ledger of Plan Holder.
     */
    public function ledger()
    {
        $reportService = new ReportService();
        $query = cs_search_term($this->request->getGet('q'));
        $planHolderId = (int) $this->request->getGet('plan_holder_id');

        $matches = $query !== '' ? $reportService->searchPlanHolders($query, $this->reportBranchFilter()) : [];
        $ledger = $planHolderId > 0 ? $reportService->getPlanHolderLedger($planHolderId) : null;

        return view('admin/reports/ledger', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'Ledger of Plan Holder',
            'page_sub' => 'Full payment/transaction history for a single plan holder.',
            'active_report' => 'ledger',
            'query' => $query,
            'matches' => $matches,
            'ledger' => $ledger,
        ]);
    }

    /**
     * Panel brief, section 7: Collections/Collector Report.
     */
    public function collections()
    {
        $filters = $this->reportDateFilters() + ['branch_id' => $this->reportBranchFilter(), 'search' => cs_search_term($this->request->getGet('search'))];
        $reportService = new ReportService();
        $rows = $reportService->getCollectionsByCollector($filters);

        if ((string) $this->request->getGet('mode') === 'csv') {
            return $this->csvResponse(
                'collections_report',
                ['Collector', 'Role', 'Transactions', 'Total Collected'],
                $rows,
                static fn (array $row): array => [
                    trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                    (string) ($row['role_name'] ?? ''),
                    (string) ($row['transaction_count'] ?? 0),
                    number_format((float) ($row['total_collected'] ?? 0), 2, '.', ''),
                ]
            );
        }

        return view('admin/reports/collections', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'Collections Report',
            'page_sub' => 'Amounts collected per collector (whoever recorded the payment - Staff, Branch Admin, or Collector).',
            'active_report' => 'collections',
            'filters' => $filters,
            'rows' => $rows,
        ]);
    }

    /**
     * Panel brief, section 7/8: Commission Report (10% of amount collected).
     */
    public function commission()
    {
        $filters = $this->reportDateFilters() + ['branch_id' => $this->reportBranchFilter(), 'search' => cs_search_term($this->request->getGet('search'))];
        $reportService = new ReportService();
        $rows = $reportService->getCommissionReport($filters);
        $mode = (string) $this->request->getGet('mode');

        if ($mode === 'csv') {
            return $this->csvResponse(
                'commission_report',
                ['Collector', 'Role', 'Transactions', 'Total Collected', 'Commission (10%)', 'Net Remitted'],
                $rows,
                static fn (array $row): array => [
                    trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                    (string) ($row['role_name'] ?? ''),
                    (string) ($row['transaction_count'] ?? 0),
                    number_format((float) ($row['total_collected'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['commission'] ?? 0), 2, '.', ''),
                    number_format((float) ($row['net_remitted'] ?? 0), 2, '.', ''),
                ]
            );
        }

        if ($mode === 'pdf' || $mode === 'print') {
            return $this->pdfResponse('commission', ['rows' => $rows], $filters, $mode === 'print');
        }

        return view('admin/reports/commission', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'Commission Report',
            'page_sub' => 'Commission earned per collector - 10% of the amount collected, computed automatically.',
            'active_report' => 'commission',
            'filters' => $filters,
            'branches' => $this->branchOptions(),
            'rows' => $rows,
        ]);
    }

    /**
     * Panel brief, section 7: Remittance Report - system-wide for Admin
     * (Branch Admin and Staff already have their own branch-scoped
     * version with CSV/PDF/print export).
     */
    public function remittance()
    {
        $reportService = new ReportService();
        $filters = $this->reportDateFilters() + [
            'branch_id' => $this->reportBranchFilter(),
            'payment_method' => (string) $this->request->getGet('payment_method'),
            'received_by' => (int) $this->request->getGet('received_by'),
            'search' => cs_search_term($this->request->getGet('search')),
        ];

        $rows = $reportService->getRemittanceReport($filters);

        if ((string) $this->request->getGet('mode') === 'csv') {
            return $this->csvResponse(
                'admin_remittance_report',
                ['Date', 'Client Name', 'Branch', 'Amount', 'Method', 'Reference', 'Received By'],
                $rows,
                static fn (array $row): array => [
                    (string) ($row['payment_date'] ?? ''),
                    trim((string) ($row['client_first'] ?? '') . ' ' . (string) ($row['client_last'] ?? '')),
                    (string) ($row['unique_identifier'] ?? ''),
                    number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                    strtoupper((string) ($row['payment_method'] ?? '')),
                    (string) ($row['reference_number'] ?? ($row['official_receipt_number'] ?? '')),
                    trim((string) ($row['staff_first'] ?? '') . ' ' . (string) ($row['staff_last'] ?? '')),
                ]
            );
        }

        $mode = (string) $this->request->getGet('mode');
        if ($mode === 'pdf' || $mode === 'print') {
            $breakdown = $reportService->getPaymentBreakdown($filters);
            $totals = [
                ['label' => 'Cash Total', 'amount' => $breakdown['cash_total']],
                ['label' => 'GCash Total', 'amount' => $breakdown['gcash_total']],
                ['label' => 'Total Remittance', 'amount' => $reportService->getTotalRemittance($filters), 'strong' => true],
            ];

            return $this->pdfResponse('remittance', ['rows' => $rows, 'totals' => $totals], $filters, $mode === 'print');
        }

        return view('admin/reports/remittance', [
            'role_layout' => 'layouts/admin',
            'page_title' => 'Remittance Report',
            'page_sub' => 'System-wide payment remittance across all branches. Branch Admin and Staff have their own branch-scoped version with print/PDF export for cash reconciliation.',
            'active_report' => 'remittance',
            'filters' => $filters,
            'rows' => $rows,
            'total_remittance' => $reportService->getTotalRemittance($filters),
            'branches' => $this->branchOptions(),
        ]);
    }

    private function branchOptions(): array
    {
        return db_connect()->table('branches')->select('branch_id, branch_name')->orderBy('branch_name', 'ASC')->get()->getResultArray();
    }

    /**
     * Every System Admin report PDF goes through here so the header, layout and
     * disposition (inline for Print, attachment for Export PDF) match the Branch
     * Admin and Staff versions exactly. $filters carries whatever the on-screen
     * filter form applied (branch, dates, method, ...).
     */
    private function pdfResponse(string $type, array $data, array $filters, bool $inline)
    {
        $branchId = (int) ($filters['branch_id'] ?? 0);
        $branch = $branchId > 0 ? (new ReportService())->getBranchInfo($branchId) : null;

        $filterLines = [];
        if (! empty($filters['payment_method'])) {
            $filterLines[] = 'Method: ' . strtoupper((string) $filters['payment_method']);
        }
        if (! empty($filters['received_by'])) {
            $user = db_connect()->table('users')->select('first_name, last_name')->where('user_id', (int) $filters['received_by'])->get()->getRowArray();
            if ($user) {
                $filterLines[] = 'Recorded by: ' . trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
            }
        }

        $context = [
            'branch_name'  => (string) ($branch['branch_name'] ?? ''),
            'date_from'    => $filters['date_from'] ?? null,
            'date_to'      => $filters['date_to'] ?? null,
            'filter_lines' => $filterLines,
        ];

        return (new ReportPdfService())->respond($this->response, $type, $data, $context, $inline);
    }

    /**
     * Admin sees system-wide (branch_id 0) unless a specific branch is
     * chosen via ?branch_id=; Branch Admin/Staff sessions are locked to
     * their own branch (defensive - these actions are role:1-filtered).
     */
    private function reportBranchFilter(): int
    {
        $roleId = (int) session('role_id');
        if ($roleId !== 1) {
            return (int) session('branch_id');
        }

        return (int) ($this->request->getGet('branch_id') ?? 0);
    }

    private function reportDateFilters(): array
    {
        $dateFrom = trim((string) $this->request->getGet('date_from'));
        $dateTo = trim((string) $this->request->getGet('date_to'));

        return [
            'date_from' => $dateFrom !== '' ? $dateFrom : date('Y-01-01'),
            'date_to' => $dateTo !== '' ? $dateTo : date('Y-m-d'),
        ];
    }

    private function csvResponse(string $baseFilename, array $headers, array $rows, callable $rowMapper)
    {
        $filename = $baseFilename . '_' . date('Ymd_His') . '.csv';

        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, $rowMapper($row));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody((string) $csv);
    }

    private function buildBranchSummary(int $branchId, int $roleId): array
    {
        $db = db_connect();
        $sql = "SELECT b.branch_id, b.branch_name, COUNT(DISTINCT u.user_id) AS staff_count, COUNT(DISTINCT ph.plan_holder_id) AS client_count, COUNT(DISTINCT s.service_id) AS service_count, COALESCE(SUM(CASE WHEN pay.status = 'paid' THEN pay.amount ELSE 0 END), 0) AS revenue
             FROM branches b
             LEFT JOIN users u ON u.branch_id = b.branch_id
             LEFT JOIN plan_holders ph ON ph.branch_id = b.branch_id
             LEFT JOIN services s ON s.branch_id = b.branch_id
             LEFT JOIN payments pay ON pay.branch_id = b.branch_id";

        if (in_array($roleId, [2, 3], true) && $branchId > 0) {
            $sql .= ' WHERE b.branch_id = ' . $db->escape($branchId);
        }

        $sql .= ' GROUP BY b.branch_id, b.branch_name ORDER BY revenue DESC, b.branch_name ASC';

        return $db->query($sql)->getResultArray();
    }

    private function buildServiceSummary(int $branchId, int $roleId): array
    {
        $db = db_connect();
        $sql = "SELECT s.service_id, COALESCE(sl.service_name, '-') AS service_type, s.status, s.total_cost, s.service_date, b.branch_name, u.first_name, u.last_name
             FROM services s
             LEFT JOIN service_list sl ON sl.service_list_id = s.service_list_id
             INNER JOIN branches b ON b.branch_id = s.branch_id
             INNER JOIN plan_holders ph ON ph.plan_holder_id = s.plan_holder_id
             INNER JOIN users u ON u.user_id = ph.user_id";

        if (in_array($roleId, [2, 3], true) && $branchId > 0) {
            $sql .= ' WHERE s.branch_id = ' . $db->escape($branchId);
        }

        $sql .= ' ORDER BY s.service_id DESC LIMIT 25';

        return $db->query($sql)->getResultArray();
    }

    private function buildClientSummary(int $branchId, int $roleId): array
    {
        $db = db_connect();
        $sql = "SELECT ph.plan_holder_id, ph.unique_identifier, ph.status, b.branch_name, u.first_name, u.last_name
             FROM plan_holders ph
             INNER JOIN users u ON u.user_id = ph.user_id
             INNER JOIN branches b ON b.branch_id = ph.branch_id";

        if (in_array($roleId, [2, 3], true) && $branchId > 0) {
            $sql .= ' WHERE ph.branch_id = ' . $db->escape($branchId);
        }

        $sql .= ' ORDER BY ph.plan_holder_id DESC LIMIT 25';

        return $db->query($sql)->getResultArray();
    }

    private function buildStaffSummary(int $branchId, int $roleId): array
    {
        $db = db_connect();
        $sql = "SELECT u.user_id, u.first_name, u.last_name, COUNT(DISTINCT a.assignment_id) AS tasks_assigned, COUNT(DISTINCT pay.payment_id) AS payments_handled, COUNT(DISTINCT s.service_id) AS services_handled
             FROM users u
             LEFT JOIN assignments a ON a.staff_id = u.user_id
             LEFT JOIN payments pay ON pay.received_by = u.user_id
             LEFT JOIN services s ON s.assigned_staff = u.user_id
             WHERE u.role_id = 3";

        if (in_array($roleId, [2, 3], true) && $branchId > 0) {
            $sql .= ' AND u.branch_id = ' . $db->escape($branchId);
        }

        $sql .= ' GROUP BY u.user_id, u.first_name, u.last_name ORDER BY tasks_assigned DESC, payments_handled DESC';

        return $db->query($sql)->getResultArray();
    }

    private function resolveLayoutView(): string
    {
        $role = (int) session()->get('role_id');

        if ($role === 1) {
            return 'layouts/admin';
        }

        if ($role === 2) {
            return 'layouts/branch_admin';
        }

        if ($role === 3) {
            return 'layouts/staff';
        }

        return 'layouts/plan_holder';
    }
}
