<?php

namespace App\Services;

/**
 * Collector area assignments (collector_assignments: user_id, branch_id,
 * barangay_code NULL = branch-wide). Extracted from CollectionListController
 * (which only ever read this table) because PaymentTracking's GCash
 * approval now also needs to know "is this collector allowed to act on a
 * payment from this barangay" - two controllers doing their own area-scope
 * check is exactly how that logic drifts apart over time.
 */
class CollectorAssignmentService
{
    /** @return string[] barangay_codes this user is assigned to in this branch. */
    public function assignedBarangays(int $userId, int $branchId): array
    {
        $rows = db_connect()->table('collector_assignments')
            ->select('barangay_code')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('barangay_code IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        return array_column($rows, 'barangay_code');
    }

    public function hasBranchWideAssignment(int $userId, int $branchId): bool
    {
        return db_connect()->table('collector_assignments')
            ->where('user_id', $userId)
            ->where('branch_id', $branchId)
            ->where('barangay_code IS NULL', null, false)
            ->countAllResults() > 0;
    }

    /**
     * Whether $userId (a collector-capable account) may act on a client in
     * $barangayCode within $branchId - branch-wide assignment always
     * qualifies; otherwise the barangay must be one of their assigned ones.
     * A collector with no assignment rows at all never qualifies (there is
     * nothing to be scoped to yet) - this is deliberately NOT "open by
     * default", so an unassigned collector can't act on any client until a
     * branch admin actually assigns them somewhere.
     */
    public function canActOnBarangay(int $userId, int $branchId, ?string $barangayCode): bool
    {
        if ($this->hasBranchWideAssignment($userId, $branchId)) {
            return true;
        }

        $assigned = $this->assignedBarangays($userId, $branchId);

        return $assigned !== [] && in_array((string) $barangayCode, $assigned, true);
    }

    /** Every collector-capable Staff/Collector account in this branch, assigned or not. */
    public function branchCollectorCandidates(int $branchId): array
    {
        return db_connect()->table('users')
            ->select('user_id, first_name, last_name, role_id, is_collector')
            ->where('branch_id', $branchId)
            ->where('(role_id = 5 OR is_collector = 1)', null, false)
            ->where('account_status', 'verified')
            ->orderBy('first_name', 'ASC')
            ->orderBy('last_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** Collector-capable accounts that already have at least one assignment row in this branch. */
    public function branchCollectorsWithAssignments(int $branchId): array
    {
        return db_connect()->table('users u')
            ->select('u.user_id, u.first_name, u.last_name')
            ->join('collector_assignments ca', 'ca.user_id = u.user_id', 'inner')
            ->where('ca.branch_id', $branchId)
            ->where('(u.role_id = 5 OR u.is_collector = 1)', null, false)
            ->groupBy('u.user_id')
            ->get()
            ->getResultArray();
    }

    /** All assignment rows for this branch, grouped by user_id, for the assignment management screen. */
    public function assignmentsByUser(int $branchId): array
    {
        $rows = db_connect()->table('collector_assignments')
            ->select('assignment_id, user_id, barangay_code, assigned_at')
            ->where('branch_id', $branchId)
            ->orderBy('assigned_at', 'DESC')
            ->get()
            ->getResultArray();

        $byUser = [];
        foreach ($rows as $row) {
            $byUser[(int) $row['user_id']][] = $row;
        }

        return $byUser;
    }
}
