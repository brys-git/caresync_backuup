<?php

namespace App\Controllers;

use App\Services\CollectorAssignmentService;
use App\Services\CommissionService;
use App\Services\MembershipService;
use App\Services\PaymentService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Collection List: who has paid, who hasn't, for whoever can_collect() (a
 * dedicated Collector account, or a Staff account marked as a collector) and
 * for Branch Admins. One controller, two entry points that share the same
 * query and view - Branch Admin sees every collector in their branch (with a
 * collector filter), a collector sees only their own assigned area.
 *
 * STATUS is derived entirely from existing plans/payments columns, never a
 * new status column that could drift out of sync with them:
 *
 *   no_balance_due        plans.next_due_date IS NULL. Only happens when
 *                         CycleService::afterPaymentVerified() explicitly
 *                         clears it on entering the 'paid_unclaimed' cycle
 *                         state (see that class) - every other path
 *                         (including initial activation, MembershipService::
 *                         applyMembershipCoverage()) always sets a real
 *                         date. So NULL reliably means "cycle fully paid,
 *                         waiting on a claim", not "never started".
 *   awaiting_verification a payments row for this plan is still 'pending'.
 *                         Checked before overdue/due on purpose: a payment
 *                         already in the pipe means a visit is exactly the
 *                         wasted trip this page exists to prevent, even if
 *                         the plan also looks overdue by day-count.
 *   overdue               plans.overdue_months > 0 (maintained by
 *                         OverduePolicyService's daily sweep).
 *   due                   plans.next_due_date has arrived, by the "as of"
 *                         date this page is viewing (see PERIOD note below).
 *   not_due                everything else - paid ahead of schedule.
 *
 * PERIOD: due dates in this app are per-plan and continuous
 * (payment_coverage_until + 1 day - see MembershipService::
 * applyMembershipCoverage()'s own "not +1 month" note), not a shared
 * calendar-month cutoff every client owes on. So "period" here means
 * "evaluate status as of this date" (default: today, i.e. the current
 * month), not "everyone due in month X" - there's no shared due-day to
 * group by. The period selector still lets a collector/branch admin look
 * at a past or future as-of date if useful, e.g. planning next month's run.
 */
class CollectionListController extends BaseController
{
    /**
     * Branch Admin: every collector's rows in their branch, with a
     * collector/barangay/status filter.
     */
    public function branchAdmin(): ResponseInterface|string
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            return redirect()->to('/unauthorized');
        }

        $filters = $this->readFilters();
        $rows = $this->queryRows($branchId, $filters);
        $collectors = $this->branchCollectors($branchId);

        return $this->render('layouts/branch_admin', $rows, $filters, [
            'collectors' => $collectors,
            'show_collector_filter' => true,
            // Branch Admin browses the whole branch's status here but
            // records cash through the existing Payment Tracking flow, not
            // from this list - recording is a collector-in-the-field action.
            'can_record_payment' => false,
            'print_url' => base_url('branch-admin/collection-list/print') . '?' . http_build_query($filters),
            'remarks_route_base' => 'branch-admin/collection-list/save-remarks',
        ]);
    }

    /**
     * Collector (role_id 5) or a Staff account with is_collector: their own
     * assigned area only. A collector with no rows in collector_assignments
     * yet sees an empty state explaining why, not their whole branch by
     * accident.
     */
    public function collector(): ResponseInterface|string
    {
        $userId = (int) session('user_id');
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            $branchId = (int) (db_connect()->table('users')->select('branch_id')->where('user_id', $userId)->get()->getRowArray()['branch_id'] ?? 0);
        }

        $assignedBarangays = $this->assignedBarangays($userId, $branchId);
        $branchWide = $this->hasBranchWideAssignment($userId, $branchId);
        if ($assignedBarangays === [] && ! $branchWide) {
            return $this->render($this->collectorLayout(), [], $this->readFilters(), [
                'no_assignment' => true,
            ]);
        }

        $filters = $this->readFilters();
        // A collector can't filter by another collector or escape their own area.
        unset($filters['collector_id']);
        // null = no area restriction (branch-wide assignment); otherwise the
        // specific barangay_codes this collector is assigned to.
        $rows = $this->queryRows($branchId, $filters, $branchWide ? null : $assignedBarangays);

        return $this->render($this->collectorLayout(), $rows, $filters, [
            'show_collector_filter' => false,
            'can_record_payment' => true,
            'print_url' => base_url('collector/collection-list/print') . '?' . http_build_query($filters),
            'remarks_route_base' => 'collector/collection-list/save-remarks',
            'gcash_rows' => $this->queryPendingGcash($branchId, $branchWide ? null : $assignedBarangays),
            'gcash_action_base' => base_url('collector/collection-list'),
        ]);
    }

    /**
     * Record Payment (Prompt C), step 1: pre-filled form from a visitable
     * row. GET only - the actual write happens in submitRecordPayment().
     */
    public function recordPaymentForm(int $planId): ResponseInterface|string
    {
        $plan = $this->loadScopedPlan($planId);
        if ($plan === null) {
            return redirect()->to('/unauthorized')->with('error', 'That plan is not in your collection area.');
        }

        [, , , $remainingMonths] = (new PaymentService())->remainingPayableMonths($plan);

        return view('collection_list/record_payment', [
            'role_layout' => $this->collectorLayout(),
            'page_title' => 'Record Payment',
            'page_sub' => 'Cash collected in the field - a branch admin still verifies it before it counts.',
            'plan' => $plan,
            'remaining_months' => $remainingMonths,
        ]);
    }

    /**
     * Record Payment, step 2: the actual write. Mirrors PaymentTracking::
     * recordCash()'s Staff (non-Branch-Admin) path deliberately - same
     * validation, same 'pending' status, same coverage projection - because
     * a collector recording cash is functionally that same case: someone
     * who is not a Branch Admin taking money in the field, who must not be
     * able to verify their own entry. The only real differences are the
     * area-scope check (loadScopedPlan()) and that months_covered is capped
     * to the current cycle's remaining months (PaymentService::
     * remainingPayableMonths()) rather than a flat 59.
     *
     * status is 'pending', not a distinct "awaiting_verification" enum
     * value - the live payments.status column never actually gained that
     * value (see the investigation in this project's own notes: a migration
     * claims the rename happened but never widened the ENUM), and 'pending'
     * already means exactly "awaiting verification, cannot self-verify"
     * everywhere else this codebase uses it (PaymentTracking::recordCash()'s
     * own Staff path). Introducing a second spelling for the same state
     * would be the drift this whole feature is trying to avoid.
     */
    public function submitRecordPayment(int $planId): ResponseInterface
    {
        $plan = $this->loadScopedPlan($planId);
        if ($plan === null) {
            return redirect()->to('/unauthorized')->with('error', 'That plan is not in your collection area.');
        }

        $rules = [
            'months_covered' => 'required|is_natural_no_zero',
            'official_receipt_number' => 'required|max_length[100]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        [, , , $remainingMonths] = (new PaymentService())->remainingPayableMonths($plan);
        $monthsCovered = (int) $this->request->getPost('months_covered');
        if ($monthsCovered < 1 || $monthsCovered > $remainingMonths) {
            return redirect()->back()->withInput()->with('error', 'Months covered must be between 1 and ' . $remainingMonths . ' - this plan\'s current cycle cannot accept more than that.');
        }

        $receiptNumber = trim((string) $this->request->getPost('official_receipt_number'));
        $duplicate = db_connect()->table('payments')
            ->where('official_receipt_number', $receiptNumber)
            ->where('payment_method', 'cash')
            ->countAllResults();
        if ($duplicate > 0) {
            return redirect()->back()->withInput()->with('error', 'This receipt number has already been recorded. Check for a duplicate entry.');
        }

        $monthlyFee = (float) ($plan['monthly_fee'] ?? MembershipService::MONTHLY_FEE);
        $amount = round($monthlyFee * $monthsCovered, 2);
        $coverage = (new PaymentService())->projectCoverage($plan, $monthsCovered);

        $db = db_connect();
        $inserted = $db->table('payments')->insert([
            'plan_id' => $planId,
            'amount' => $amount,
            'months_covered' => $monthsCovered,
            'payment_date' => date('Y-m-d'),
            'payment_method' => 'cash',
            'official_receipt_number' => $receiptNumber,
            'received_by' => (int) session('user_id'),
            'branch_id' => (int) $plan['branch_id'],
            'status' => 'pending',
            'coverage_start' => $coverage['start'],
            'coverage_end' => $coverage['end'],
            'remarks' => 'Recorded in the field by collector, pending branch admin verification',
        ]);

        if (! $inserted) {
            return redirect()->back()->withInput()->with('error', 'Failed to record payment. Please try again.');
        }

        $projectedCommission = (new CommissionService())->forPayment($amount);

        // Flash messages are flattened to plain text (caresync.js reads
        // .textContent when promoting them to a toast), so this can't use
        // cs_money() - its peso sign is a CSS ::before on the <span> it
        // returns, which would vanish along with the stripped markup.
        $commissionText = '₱' . number_format($projectedCommission, 2);

        return redirect()->to(base_url('collector/collection-list'))
            ->with('success', 'Payment recorded for ' . $plan['client_name'] . '. It now shows as Awaiting Verification. Your ' . $commissionText . ' commission will count once your branch admin verifies it.');
    }

    /**
     * Loads a plan for the Record Payment flow, enforcing the same area
     * scope the Collection List itself is filtered by - a collector can't
     * record a payment for a plan outside their branch/assigned barangay
     * just by guessing a plan_id in the URL. Returns a flat array merging
     * plans + plan_holders + users fields (branch_id, monthly_fee,
     * client_name, barangay_code, total_plan_amount via plan_id for
     * PaymentService::remainingPayableMonths()), or null if not found/out
     * of scope.
     */
    private function loadScopedPlan(int $planId): ?array
    {
        $userId = (int) session('user_id');
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            $branchId = (int) (db_connect()->table('users')->select('branch_id')->where('user_id', $userId)->get()->getRowArray()['branch_id'] ?? 0);
        }

        $row = db_connect()->table('plans pl')
            ->select('pl.plan_id, pl.plan_holder_id, pl.monthly_fee, pl.payment_coverage_until, pl.start_date, ph.branch_id, ph.barangay_code, u.first_name, u.last_name, u.contact_number')
            ->join('plan_holders ph', 'ph.plan_holder_id = pl.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->where('pl.plan_id', $planId)
            ->where('pl.status', 'active')
            ->where('ph.status', 'active')
            ->get()
            ->getRowArray();

        if (! $row || (int) $row['branch_id'] !== $branchId) {
            return null;
        }

        // Branch Admin (role_id 2) never reaches this - the route only
        // exposes it under 'collector', gated by can_collect(). Only check
        // area scope for someone whose collector standing comes from an
        // area assignment at all - a branch-wide assignment (or role_id 5
        // with no barangay rows yet) means no further narrowing.
        $branchWide = $this->hasBranchWideAssignment($userId, $branchId);
        if (! $branchWide) {
            $assigned = $this->assignedBarangays($userId, $branchId);
            if ($assigned !== [] && ! in_array((string) $row['barangay_code'], $assigned, true)) {
                return null;
            }
        }

        $row['client_name'] = trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);

        return $row;
    }

    /**
     * Saves a plan holder's persistent Remarks note from the Collection
     * List table (Prompt D) - one free-text field, overwritten on each
     * save, reachable by both entry points (collector/save-remarks and
     * branch-admin/save-remarks routes share this method). Area-scoped the
     * same way loadScopedPlan() scopes Record Payment: branch always,
     * barangay only for a collector without a branch-wide assignment - a
     * Branch Admin (role_id 2) is branch-scoped only, same as the rest of
     * this controller's branchAdmin() path.
     */
    public function saveRemarks(int $planHolderId): ResponseInterface
    {
        $userId = (int) session('user_id');
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            $branchId = (int) (db_connect()->table('users')->select('branch_id')->where('user_id', $userId)->get()->getRowArray()['branch_id'] ?? 0);
        }

        $planHolder = db_connect()->table('plan_holders')
            ->select('plan_holder_id, branch_id, barangay_code')
            ->where('plan_holder_id', $planHolderId)
            ->where('status', 'active')
            ->get()
            ->getRowArray();

        if (! $planHolder || (int) $planHolder['branch_id'] !== $branchId) {
            return redirect()->to('/unauthorized')->with('error', 'That client is not in your area.');
        }

        if ((int) session('role_id') !== 2) {
            $branchWide = $this->hasBranchWideAssignment($userId, $branchId);
            if (! $branchWide) {
                $assigned = $this->assignedBarangays($userId, $branchId);
                if ($assigned !== [] && ! in_array((string) $planHolder['barangay_code'], $assigned, true)) {
                    return redirect()->to('/unauthorized')->with('error', 'That client is not in your collection area.');
                }
            }
        }

        $remarks = trim((string) $this->request->getPost('remarks'));
        db_connect()->table('plan_holders')
            ->where('plan_holder_id', $planHolderId)
            ->update(['remarks' => $remarks !== '' ? $remarks : null]);

        return redirect()->back()->with('success', 'Remarks saved.');
    }

    /**
     * Fix Prompts Task 4: a collector's own 10% commission, per client, for
     * a date range (default: first day of this month through today). Uses
     * CommissionService - the exact same cash+verified eligibility rule
     * the admin/branch Commission Report uses, so the two always agree for
     * the same range. Security: always session('user_id') - there is no
     * request parameter that could name a different collector.
     */
    public function commission(): ResponseInterface|string
    {
        $userId = (int) session('user_id');
        $range = $this->readCommissionRange();

        $commissionService = new CommissionService();

        return view('collection_list/commission', [
            'role_layout' => $this->collectorLayout(),
            'page_title' => 'My Commission',
            'page_sub' => 'Your 10% commission on verified field collections.',
            'per_client' => $commissionService->perClient($userId, $range['from'], $range['to']),
            'pending' => $commissionService->pendingPayments($userId, $range['from'], $range['to']),
            'totals' => $commissionService->totals($userId, $range['from'], $range['to']),
            'range' => $range,
        ]);
    }

    /**
     * date_from/date_to query params, or a named 'quick' range (this_month/
     * last_month/all_time) - defaults to first day of the current month
     * through today when nothing is given.
     */
    private function readCommissionRange(): array
    {
        $request = service('request');
        $quick = (string) ($request->getGet('quick') ?? '');
        $dateFrom = trim((string) ($request->getGet('date_from') ?? ''));
        $dateTo = trim((string) ($request->getGet('date_to') ?? ''));

        if ($quick === 'last_month') {
            $dateFrom = date('Y-m-01', strtotime('first day of last month'));
            $dateTo = date('Y-m-t', strtotime('last day of last month'));
        } elseif ($quick === 'all_time') {
            $dateFrom = '';
            $dateTo = '';
        } elseif ($quick === 'this_month' || ($dateFrom === '' && $dateTo === '')) {
            $quick = 'this_month';
            $dateFrom = date('Y-m-01');
            $dateTo = date('Y-m-d');
        }

        return [
            'quick' => $quick,
            'from' => $dateFrom !== '' ? $dateFrom : null,
            'to' => $dateTo !== '' ? $dateTo : null,
        ];
    }

    public function printBranchAdmin(): string
    {
        $branchId = (int) session('branch_id');
        $filters = $this->readFilters();
        $rows = $this->queryRows($branchId, $filters);

        return view('collection_list/print', [
            'rows' => $rows,
            'as_of' => $filters['as_of'],
            'generated_at' => date('Y-m-d H:i'),
        ]);
    }

    public function printCollector(): string
    {
        $userId = (int) session('user_id');
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            $branchId = (int) (db_connect()->table('users')->select('branch_id')->where('user_id', $userId)->get()->getRowArray()['branch_id'] ?? 0);
        }
        $assignedBarangays = $this->assignedBarangays($userId, $branchId);
        $branchWide = $this->hasBranchWideAssignment($userId, $branchId);
        $filters = $this->readFilters();
        unset($filters['collector_id']);
        $rows = $this->queryRows($branchId, $filters, $branchWide ? null : $assignedBarangays);

        return view('collection_list/print', [
            'rows' => $rows,
            'as_of' => $filters['as_of'],
            'generated_at' => date('Y-m-d H:i'),
        ]);
    }

    // ------------------------------------------------------------------
    // Shared plumbing
    // ------------------------------------------------------------------

    private function render(string $layout, array $rows, array $filters, array $extra): string
    {
        $visit = array_values(array_filter($rows, static fn (array $r): bool => in_array($r['collection_status'], ['overdue', 'due'], true)));
        $noVisit = array_values(array_filter($rows, static fn (array $r): bool => ! in_array($r['collection_status'], ['overdue', 'due'], true)));

        $summary = [
            'total' => count($rows),
            'overdue' => count(array_filter($rows, static fn ($r) => $r['collection_status'] === 'overdue')),
            'due' => count(array_filter($rows, static fn ($r) => $r['collection_status'] === 'due')),
            'awaiting_verification' => count(array_filter($rows, static fn ($r) => $r['collection_status'] === 'awaiting_verification')),
            'not_due' => count(array_filter($rows, static fn ($r) => $r['collection_status'] === 'not_due')),
            'no_balance_due' => count(array_filter($rows, static fn ($r) => $r['collection_status'] === 'no_balance_due')),
            'expected_collection' => array_sum(array_column($rows, 'amount_due')),
        ];

        return view('collection_list/index', array_merge([
            'role_layout' => $layout,
            'page_title' => 'Collection List',
            'page_sub' => 'Who has paid, who hasn\'t - for the selected period.',
            'visit_rows' => $visit,
            'no_visit_rows' => $noVisit,
            'summary' => $summary,
            'filters' => $filters,
        ], $extra));
    }

    private function readFilters(): array
    {
        $period = trim((string) (service('request')->getGet('period') ?? ''));
        $asOf = date('Y-m-t'); // last day of current month, if period left default
        if ($period !== '' && preg_match('/^\d{4}-\d{2}$/', $period)) {
            $asOf = date('Y-m-t', strtotime($period . '-01'));
        } else {
            $period = date('Y-m');
        }

        return [
            'period' => $period,
            'as_of' => $asOf,
            'status' => (string) (service('request')->getGet('status') ?? ''),
            'barangay_code' => (string) (service('request')->getGet('barangay_code') ?? ''),
            'collector_id' => (int) (service('request')->getGet('collector_id') ?? 0),
            'search' => cs_search_term(service('request')->getGet('search')),
        ];
    }

    /**
     * @param string[]|null $limitToBarangays null = no area restriction
     *   (Branch Admin, or a collector with a branch-wide assignment);
     *   otherwise restrict to exactly these barangay_codes.
     */
    private function queryRows(int $branchId, array $filters, ?array $limitToBarangays = null): array
    {
        $sql = "SELECT
                pl.plan_id, pl.monthly_fee, pl.next_due_date, pl.overdue_months, pl.payment_coverage_until,
                ph.plan_holder_id, ph.unique_identifier, ph.address_barangay, ph.barangay_code, ph.address_city, ph.remarks,
                u.first_name, u.last_name, u.contact_number,
                CASE
                    WHEN pl.next_due_date IS NULL THEN 'no_balance_due'
                    WHEN EXISTS (SELECT 1 FROM payments pay WHERE pay.plan_id = pl.plan_id AND pay.status = 'pending') THEN 'awaiting_verification'
                    WHEN pl.overdue_months > 0 THEN 'overdue'
                    WHEN pl.next_due_date <= ? THEN 'due'
                    ELSE 'not_due'
                END AS collection_status
            FROM plans pl
            INNER JOIN plan_holders ph ON ph.plan_holder_id = pl.plan_holder_id
            INNER JOIN users u ON u.user_id = ph.user_id
            WHERE pl.status = 'active' AND ph.status = 'active' AND ph.branch_id = ?";
        $params = [$filters['as_of'], $branchId];

        if ($limitToBarangays !== null) {
            if ($limitToBarangays === []) {
                // Collector has area assignments but none matched (shouldn't
                // normally happen if hasBranchWideAssignment() was checked
                // first) - return nothing rather than accidentally the whole branch.
                return [];
            }
            $sql .= ' AND ph.barangay_code IN (' . implode(',', array_fill(0, count($limitToBarangays), '?')) . ')';
            $params = array_merge($params, $limitToBarangays);
        }

        if ($filters['barangay_code'] !== '') {
            $sql .= ' AND ph.barangay_code = ?';
            $params[] = $filters['barangay_code'];
        }

        if (($filters['collector_id'] ?? 0) > 0) {
            $sql .= ' AND ph.barangay_code IN (SELECT barangay_code FROM collector_assignments WHERE user_id = ? AND branch_id = ? AND barangay_code IS NOT NULL)';
            $params[] = $filters['collector_id'];
            $params[] = $branchId;
        }

        $sql .= cs_search_sql($filters['search'] ?? '', [
            'u.first_name', 'u.last_name', 'u.contact_number', 'ph.unique_identifier', 'ph.address_barangay', 'ph.address_city',
        ], $params);

        // collection_status is a SELECT-list alias, so it can't be used in
        // WHERE (MySQL resolves aliases after WHERE) - filtering by status
        // needs HAVING instead. This runs after the CASE above has already
        // been computed once per row, so it's not a second pass over the
        // EXISTS subquery - just a cheap string comparison on the result.
        if ($filters['status'] !== '') {
            $sql .= ' HAVING collection_status = ?';
            $params[] = $filters['status'];
        }

        // Reuses the collection_status alias rather than re-deriving it -
        // one CASE evaluation (with its EXISTS subquery) per row, not three.
        $sql .= " ORDER BY
                CASE collection_status WHEN 'overdue' THEN 0 WHEN 'due' THEN 1 ELSE 2 END ASC,
                pl.overdue_months DESC,
                ph.address_barangay ASC,
                u.last_name ASC";

        $rows = db_connect()->query($sql, $params)->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $monthlyFee = (float) $row['monthly_fee'];
            $overdueMonths = (int) $row['overdue_months'];
            $status = $row['collection_status'];

            $monthsBehind = $status === 'overdue' ? max(1, $overdueMonths) : 0;
            $amountDue = in_array($status, ['overdue', 'due'], true) ? $monthlyFee * max(1, $monthsBehind) : 0.0;

            $result[] = [
                'plan_id' => (int) $row['plan_id'],
                'plan_holder_id' => (int) $row['plan_holder_id'],
                'plan_number' => (string) ($row['unique_identifier'] ?: '-'),
                'client_name' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
                'barangay' => (string) ($row['address_barangay'] ?: '-'),
                'city' => (string) ($row['address_city'] ?: '-'),
                'contact_number' => (string) ($row['contact_number'] ?: '-'),
                'monthly_fee' => $monthlyFee,
                'months_behind' => $monthsBehind,
                'amount_due' => $amountDue,
                'collection_status' => $status,
                'can_visit' => in_array($status, ['overdue', 'due'], true),
                'remarks' => (string) ($row['remarks'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * GCash payments awaiting this collector's verification: pending GCash
     * rows for plan holders inside their assigned area, shaped exactly like
     * partials/advance_payment_table.php expects (same columns
     * PaymentTracking's paymentRows() selects) so that partial can render
     * this table too, Approve/Reject buttons and all - see collector()
     * above and PaymentTracking::reviewGcash()'s area-scope check, which
     * this query's WHERE clause mirrors.
     *
     * @param string[]|null $limitToBarangays null = no area restriction (branch-wide assignment)
     */
    private function queryPendingGcash(int $branchId, ?array $limitToBarangays): array
    {
        if ($limitToBarangays === []) {
            return [];
        }

        $sql = "SELECT payments.*, users.first_name, users.last_name, plan_holders.unique_identifier, plan_holders.plan_holder_id,
                staff_u.first_name AS staff_first_name, staff_u.last_name AS staff_last_name, plans.payment_coverage_until
            FROM payments
            INNER JOIN plans ON plans.plan_id = payments.plan_id
            INNER JOIN plan_holders ON plan_holders.plan_holder_id = plans.plan_holder_id
            INNER JOIN users ON users.user_id = plan_holders.user_id
            LEFT JOIN users staff_u ON staff_u.user_id = payments.received_by
            WHERE payments.branch_id = ? AND payments.status = 'pending' AND payments.payment_method = 'gcash'";
        $params = [$branchId];

        if ($limitToBarangays !== null) {
            $sql .= ' AND plan_holders.barangay_code IN (' . implode(',', array_fill(0, count($limitToBarangays), '?')) . ')';
            $params = array_merge($params, $limitToBarangays);
        }

        $sql .= ' ORDER BY payments.payment_date DESC, payments.payment_id DESC';

        return db_connect()->query($sql, $params)->getResultArray();
    }

    /**
     * These three delegate to CollectorAssignmentService, which
     * PaymentTracking's collector-approval scope check also uses - kept as
     * private wrappers here since every call site in this file already
     * calls them by this name.
     */
    private function branchCollectors(int $branchId): array
    {
        return (new CollectorAssignmentService())->branchCollectorsWithAssignments($branchId);
    }

    /** @return string[] barangay_codes this user is assigned to in this branch. */
    private function assignedBarangays(int $userId, int $branchId): array
    {
        return (new CollectorAssignmentService())->assignedBarangays($userId, $branchId);
    }

    private function hasBranchWideAssignment(int $userId, int $branchId): bool
    {
        return (new CollectorAssignmentService())->hasBranchWideAssignment($userId, $branchId);
    }

    /**
     * A Staff account using their is_collector capability stays inside
     * their own Staff-branded shell (layouts/staff) rather than switching
     * into the dedicated Collector layout - only a real role_id 5 account
     * sees layouts/collector. Same idea as Dashboard::resolveLayoutView().
     */
    private function collectorLayout(): string
    {
        return (int) session('role_id') === 3 ? 'layouts/staff' : 'layouts/collector';
    }
}
