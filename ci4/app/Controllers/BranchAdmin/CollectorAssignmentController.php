<?php

namespace App\Controllers\BranchAdmin;

use App\Controllers\BaseController;
use App\Services\CollectorAssignmentService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The missing half of the Collector capability feature: Collection List
 * and GCash approval both READ collector_assignments (which barangay a
 * collector covers, or "the whole branch"), but nothing ever WROTE to that
 * table - there was no screen for a branch admin to actually assign a
 * collector anywhere. Without this, a newly is_collector-flagged Staff
 * account (or a role_id 5 Collector account) shows up nowhere: not in the
 * Collection List's own collector filter, not with any visible rows on
 * their own Collection List page, and not as a GCash approver.
 */
class CollectorAssignmentController extends BaseController
{
    private CollectorAssignmentService $service;

    public function __construct()
    {
        $this->service = new CollectorAssignmentService();
    }

    public function index(): ResponseInterface|string
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            return redirect()->to('/unauthorized');
        }

        return view('branch_admin/collector_assignments/index', [
            'role_layout' => 'layouts/branch_admin',
            'page_title' => 'Collector Assignments',
            'page_sub' => 'Assign a barangay (or the whole branch) to each collector - Collection List and GCash approval both depend on this.',
            'collectors' => $this->service->branchCollectorCandidates($branchId),
            'assignments_by_user' => $this->service->assignmentsByUser($branchId),
            'barangay_options' => $this->branchBarangayOptions($branchId),
        ]);
    }

    public function store(): ResponseInterface
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            return redirect()->to('/unauthorized');
        }

        $rules = [
            'user_id' => 'required|is_natural_no_zero',
            'scope' => 'required|in_list[barangay,branch_wide]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userId = (int) $this->request->getPost('user_id');
        $scope = (string) $this->request->getPost('scope');
        $barangayCode = trim((string) $this->request->getPost('barangay_code'));

        $collector = db_connect()->table('users')
            ->select('user_id')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('(role_id = 5 OR is_collector = 1)', null, false)
            ->get()
            ->getRowArray();

        if (! $collector) {
            return redirect()->back()->with('error', 'That account is not a collector in your branch.');
        }

        if ($scope === 'branch_wide') {
            $barangayCode = null;
        } elseif ($barangayCode === '') {
            return redirect()->back()->with('error', 'Choose a barangay, or assign the whole branch instead.');
        }

        $db = db_connect();
        $exists = $db->table('collector_assignments')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where($barangayCode === null ? 'barangay_code IS NULL' : 'barangay_code', $barangayCode === null ? null : $barangayCode, $barangayCode !== null)
            ->countAllResults();

        if ($exists > 0) {
            return redirect()->back()->with('error', 'That assignment already exists.');
        }

        $db->table('collector_assignments')->insert([
            'user_id' => $userId,
            'branch_id' => $branchId,
            'barangay_code' => $barangayCode,
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/branch-admin/collector-assignments')->with('success', 'Assignment added.');
    }

    public function destroy(int $assignmentId): ResponseInterface
    {
        $branchId = (int) session('branch_id');
        if ($branchId <= 0) {
            return redirect()->to('/unauthorized');
        }

        db_connect()->table('collector_assignments')
            ->where('assignment_id', $assignmentId)
            ->where('branch_id', $branchId)
            ->delete();

        return redirect()->to('/branch-admin/collector-assignments')->with('success', 'Assignment removed.');
    }

    /**
     * Barangays worth offering in the picker: ones that actually have
     * active plan holders in this branch, since that's the only thing a
     * collector assignment can ever be scoped against. Pulling from PSGC's
     * full list would offer hundreds of barangays with nobody to collect
     * from.
     */
    private function branchBarangayOptions(int $branchId): array
    {
        return db_connect()->table('plan_holders')
            ->select('barangay_code, address_barangay')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->where('barangay_code IS NOT NULL', null, false)
            ->groupBy('barangay_code, address_barangay')
            ->orderBy('address_barangay', 'ASC')
            ->get()
            ->getResultArray();
    }
}
