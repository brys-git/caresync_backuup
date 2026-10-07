<?php

namespace App\Controllers\Staff;

use App\Controllers\BaseController;
use App\Models\PaymentModel;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use CodeIgniter\Exceptions\PageNotFoundException;

class ReportsController extends BaseController
{
    protected PaymentModel $paymentModel;

    public function __construct()
    {
        $this->paymentModel = new PaymentModel();
    }

    public function index()
    {
        $this->ensureStaffAccess();

        $branchId = (int) session()->get('branch_id');
        if ($branchId <= 0) {
            throw PageNotFoundException::forPageNotFound();
        }

        $filters = $this->buildFilters([
            'date_from' => (string) ($this->request->getGet('date_from') ?? ''),
            'date_to' => (string) ($this->request->getGet('date_to') ?? ''),
            'payment_method' => (string) ($this->request->getGet('payment_method') ?? ''),
            'status' => (string) ($this->request->getGet('status') ?? ''),
            'received_by' => (string) ($this->request->getGet('received_by') ?? ''),
            'search' => (string) ($this->request->getGet('search') ?? ''),
        ], $branchId);

        $mode = (string) $this->request->getGet('mode');
        if ($mode === 'csv') {
            return $this->exportCsv($filters);
        }

        if ($mode === 'pdf' || $mode === 'print') {
            return $this->remittancePdf($filters, $mode === 'print');
        }

        return view('staff/reports/index', $this->buildViewData($filters, false));
    }

    private function buildViewData(array $filters, bool $printMode): array
    {
        $rows = $this->getRows($filters);
        $summary = $this->getSummary($filters);

        return [
            'role_layout' => 'layouts/staff',
            'page_title' => 'Remittance Report',
            'filters' => $filters,
            'report_rows' => $rows,
            'summary' => $summary,
            'staff_options' => $this->getStaffOptions((int) $filters['branch_id']),
            'print_mode' => $printMode,
        ];
    }

    private function getRows(array $filters): array
    {
        $builder = $this->baseQuery($filters)
            ->select('p.payment_id, p.amount, p.months_covered, p.payment_date, p.payment_method, p.reference_number, p.official_receipt_number, p.status, rb.first_name AS staff_first, rb.last_name AS staff_last, cu.first_name AS client_first, cu.last_name AS client_last, ph.unique_identifier, pl.start_date')
            ->orderBy('p.payment_date', 'DESC')
            ->orderBy('p.payment_id', 'DESC');

        return $builder->get()->getResultArray();
    }

    private function getSummary(array $filters): array
    {
        $row = $this->baseQuery($filters)
            ->select("COUNT(*) AS total_transactions, COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount ELSE 0 END), 0) AS total_collected, COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END), 0) AS total_pending, COALESCE(SUM(CASE WHEN p.status = 'cancelled' THEN p.amount ELSE 0 END), 0) AS total_cancelled", false)
            ->get()
            ->getRowArray();

        return [
            'total_transactions' => (int) ($row['total_transactions'] ?? 0),
            'total_collected' => (float) ($row['total_collected'] ?? 0),
            'total_pending' => (float) ($row['total_pending'] ?? 0),
            'total_cancelled' => (float) ($row['total_cancelled'] ?? 0),
        ];
    }

    private function getStaffOptions(int $branchId): array
    {
        return db_connect()->table('users u')
            ->select('u.user_id, u.first_name, u.last_name, u.contact_number')
            ->join('payments p', 'p.received_by = u.user_id', 'inner')
            ->where('u.branch_id', $branchId)
            ->groupBy('u.user_id, u.first_name, u.last_name, u.contact_number')
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function baseQuery(array $filters)
    {
        $builder = db_connect()->table('payments p')
            ->join('users rb', 'rb.user_id = p.received_by', 'inner')
            ->join('plans pl', 'pl.plan_id = p.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = pl.plan_holder_id', 'inner')
            ->join('users cu', 'cu.user_id = ph.user_id', 'inner')
            ->where('p.branch_id', (int) $filters['branch_id'])
            ->where('p.payment_date >=', (string) $filters['date_from'])
            ->where('p.payment_date <=', (string) $filters['date_to']);

        if (! empty($filters['payment_method'])) {
            $builder->where('p.payment_method', (string) $filters['payment_method']);
        }

        if (! empty($filters['status'])) {
            $builder->where('p.status', (string) $filters['status']);
        }

        if (! empty($filters['received_by'])) {
            $builder->where('p.received_by', (int) $filters['received_by']);
        }

        // Was a CONCAT(...) LIKE with escaping switched off, so the raw search
        // text went straight into the SQL. Every word is now matched with the
        // builder's escaped LIKE, which also finds "Juan Cruz" across both name columns.
        cs_apply_search($builder, (string) ($filters['search'] ?? ''), [
            'cu.first_name', 'cu.last_name', 'cu.contact_number', 'ph.unique_identifier', 'p.reference_number', 'p.official_receipt_number',
        ]);

        return $builder;
    }

    private function buildFilters(array $input, int $branchId): array
    {
        $today = date('Y-m-d');
        $startOfMonth = date('Y-m-01');

        $dateFrom = trim((string) ($input['date_from'] ?? ''));
        $dateTo = trim((string) ($input['date_to'] ?? ''));
        $method = strtolower(trim((string) ($input['payment_method'] ?? '')));
        $status = strtolower(trim((string) ($input['status'] ?? '')));
        $receivedBy = (int) ($input['received_by'] ?? 0);
        $search = cs_search_term($input['search'] ?? '');

        if ($dateFrom === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = $startOfMonth;
        }

        if ($dateTo === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = $today;
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        if (! in_array($method, ['', 'cash', 'gcash'], true)) {
            $method = '';
        }

        if (! in_array($status, ['', 'paid', 'pending', 'cancelled'], true)) {
            $status = '';
        }

        if ($receivedBy > 0) {
            $allowed = array_map(static fn ($row) => (int) $row['user_id'], $this->getStaffOptions($branchId));
            if (! in_array($receivedBy, $allowed, true)) {
                $receivedBy = 0;
            }
        }

        if (strlen($search) > 100) {
            $search = substr($search, 0, 100);
        }

        return [
            'branch_id' => $branchId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'payment_method' => $method,
            'status' => $status,
            'received_by' => $receivedBy,
            'search' => $search,
        ];
    }

    private function remittancePdf(array $filters, bool $inline)
    {
        $rows = $this->getRows($filters);
        $summary = $this->getSummary($filters);

        // Same figures and labels as the on-screen summary cards.
        $totals = [
            ['label' => 'Total Pending', 'amount' => $summary['total_pending']],
            ['label' => 'Total Cancelled', 'amount' => $summary['total_cancelled']],
            ['label' => 'Total Collected', 'amount' => $summary['total_collected'], 'strong' => true],
        ];

        $filterLines = [];
        if ($filters['payment_method'] !== '') {
            $filterLines[] = 'Method: ' . strtoupper($filters['payment_method']);
        }
        if ($filters['status'] !== '') {
            $filterLines[] = 'Status: ' . ucfirst($filters['status']);
        }
        if ((int) $filters['received_by'] > 0) {
            foreach ($this->getStaffOptions((int) $filters['branch_id']) as $staff) {
                if ((int) $staff['user_id'] === (int) $filters['received_by']) {
                    $filterLines[] = 'Received by: ' . trim((string) $staff['first_name'] . ' ' . (string) $staff['last_name']);
                    break;
                }
            }
        }
        if ($filters['search'] !== '') {
            $filterLines[] = 'Search: "' . $filters['search'] . '"';
        }

        $branch = (new ReportService())->getBranchInfo((int) $filters['branch_id']);

        return (new ReportPdfService())->respond($this->response, 'remittance', ['rows' => $rows, 'totals' => $totals], [
            'branch_name'  => (string) ($branch['branch_name'] ?? ''),
            'date_from'    => $filters['date_from'],
            'date_to'      => $filters['date_to'],
            'filter_lines' => $filterLines,
        ], $inline);
    }

    private function exportCsv(array $filters)
    {
        $rows = $this->getRows($filters);
        $filename = 'staff_remittance_report_' . date('Ymd_His') . '.csv';

        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['Plan Holder Name', 'Amount', 'Payment Date', 'Payment Method', 'Reference Number', 'Received By', 'Status']);

        foreach ($rows as $row) {
            fputcsv($stream, [
                trim(((string) ($row['client_first'] ?? '')) . ' ' . ((string) ($row['client_last'] ?? ''))),
                number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                (string) ($row['payment_date'] ?? ''),
                (string) ($row['payment_method'] ?? ''),
                (string) ($row['reference_number'] ?? ''),
                trim(((string) ($row['staff_first'] ?? '')) . ' ' . ((string) ($row['staff_last'] ?? ''))),
                (string) ($row['status'] ?? ''),
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody((string) $csv);
    }

    private function ensureStaffAccess(): void
    {
        $roleId = (int) session()->get('role_id');
        $roleName = strtolower((string) session()->get('role'));

        if ($roleId !== 3 && $roleName !== 'staff') {
            redirect()->to('/unauthorized')->send();
            exit;
        }
    }
}
