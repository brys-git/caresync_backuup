<?php

namespace App\Controllers;

use App\Models\ServiceBalanceModel;
use App\Services\ServiceBalanceService;
use CodeIgniter\HTTP\ResponseInterface;

class ServiceBalances extends BaseController
{
    private ServiceBalanceModel $balanceModel;
    private ServiceBalanceService $balanceService;

    public function __construct()
    {
        $this->balanceModel = new ServiceBalanceModel();
        $this->balanceService = new ServiceBalanceService();
    }

    public function index(): ResponseInterface|string
    {
        $roleId = (int) session('role_id');
        $branchId = (int) session('branch_id');
        $routePrefix = $roleId === 2 ? 'branch-admin/service-balances' : 'client/service-balances';

        $builder = db_connect()->table('service_balances sb')
            ->select('sb.*, ph.unique_identifier, ph.branch_id, u.first_name, u.last_name, COALESCE(SUM(sbp.amount), 0) AS paid_total')
            ->join('plan_holders ph', 'ph.plan_holder_id = sb.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('service_balance_payments sbp', 'sbp.service_balance_id = sb.service_balance_id AND sbp.status = "paid"', 'left')
            ->groupBy('sb.service_balance_id')
            ->orderBy('sb.created_at', 'DESC');

        if ($roleId === 4) {
            $builder->where('ph.user_id', (int) session('user_id'));
        } elseif ($roleId === 2 && $branchId > 0) {
            $builder->where('ph.branch_id', $branchId);
        } else {
            return redirect()->to('/unauthorized');
        }

        // Plan holders only ever see their own balances; the search box is for Branch Admin.
        $search = $roleId === 2 ? cs_search_term($this->request->getGet('search')) : '';
        cs_apply_search($builder, $search, ['u.first_name', 'u.last_name', 'ph.unique_identifier', 'sb.service_name', 'sb.package_name', 'sb.beneficiary_name']);

        $balances = $builder->get()->getResultArray();

        return view('service_balances/index', [
            'search' => $search,
            'show_search' => $roleId === 2,
            'role_layout' => $roleId === 2 ? 'layouts/branch_admin' : 'layouts/plan_holder',
            'page_title' => 'Service balances',
            'page_sub' => 'Funeral package balances, tracked separately from membership contributions.',
            'balances' => $balances,
            'route_prefix' => $routePrefix,
        ]);
    }

    public function show(int $id): ResponseInterface|string
    {
        $balance = $this->fetchBalance($id);
        if (! $balance) {
            return redirect()->back()->with('error', 'Service balance not found.');
        }

        if (! $this->canAccessBalance($balance)) {
            return redirect()->to('/unauthorized');
        }

        $payments = db_connect()->table('service_balance_payments')
            ->where('service_balance_id', $id)
            ->orderBy('paid_at', 'DESC')
            ->get()
            ->getResultArray();

        $application = null;
        $documents = [];
        if ((int) ($balance['application_id'] ?? 0) > 0) {
            $application = db_connect()->table('service_applications')
                ->where('application_id', (int) $balance['application_id'])
                ->get()
                ->getRowArray();

            $documents = db_connect()->table('service_application_documents')
                ->where('application_id', (int) $balance['application_id'])
                ->orderBy('created_at', 'DESC')
                ->get()
                ->getResultArray();
        }

        $routePrefix = (int) session('role_id') === 2 ? 'branch-admin/service-balances' : 'client/service-balances';

        return view('service_balances/show', [
            'role_layout' => (int) session('role_id') === 2 ? 'layouts/branch_admin' : 'layouts/plan_holder',
            'page_title' => 'Service balance',
            'page_sub' => 'Separate balance continuation for the approved funeral service.',
            'page_actions' => '<a class="btn btn-ghost" href="' . esc(site_url($routePrefix), 'attr') . '">Back</a>',
            'balance' => $balance,
            'payments' => $payments,
            'application' => $application,
            'documents' => $documents,
            'route_prefix' => $routePrefix,
        ]);
    }

    public function acknowledge(int $id)
    {
        $balance = $this->fetchBalance($id);
        if (! $balance || (int) session('role_id') !== 4 || ! $this->canAccessBalance($balance)) {
            return redirect()->back()->with('error', 'You are not allowed to acknowledge this balance.');
        }

        $ok = $this->balanceService->acknowledgeBalance($id, (int) session('user_id'), trim((string) $this->request->getPost('notes')) ?: null);

        return $ok
            ? redirect()->back()->with('success', 'Balance acknowledged successfully.')
            : redirect()->back()->with('error', 'Unable to acknowledge balance.');
    }

    public function pay(int $id)
    {
        $balance = $this->fetchBalance($id);
        if (! $balance || ! $this->canAccessBalance($balance)) {
            return redirect()->back()->with('error', 'You are not allowed to pay this balance.');
        }

        $amount = (float) $this->request->getPost('amount');
        $ok = $this->balanceService->recordPayment($id, [
            'paid_by_user_id' => (int) session('user_id'),
            'amount' => $amount,
            'reference_number' => trim((string) $this->request->getPost('reference_number')),
            'payment_method' => trim((string) $this->request->getPost('payment_method')),
            'due_date' => $this->request->getPost('due_date') ?: null,
            'notes' => trim((string) $this->request->getPost('notes')),
        ]);

        return $ok
            ? redirect()->back()->with('success', 'Balance payment recorded successfully.')
            : redirect()->back()->with('error', 'Unable to record balance payment.');
    }

    private function fetchBalance(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return db_connect()->table('service_balances sb')
            ->select('sb.*, ph.user_id, ph.branch_id, ph.unique_identifier, u.first_name, u.last_name')
            ->join('plan_holders ph', 'ph.plan_holder_id = sb.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->where('sb.service_balance_id', $id)
            ->get()
            ->getRowArray() ?: null;
    }

    private function canAccessBalance(array $balance): bool
    {
        $roleId = (int) session('role_id');
        $userId = (int) session('user_id');
        $branchId = (int) session('branch_id');

        if ($roleId === 2) {
            return $branchId > 0 && (int) ($balance['branch_id'] ?? 0) === $branchId;
        }

        if ($roleId === 4) {
            return (int) ($balance['user_id'] ?? 0) === $userId;
        }

        return false;
    }
}