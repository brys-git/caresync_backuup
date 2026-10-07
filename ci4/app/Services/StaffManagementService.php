<?php

namespace App\Services;

class StaffManagementService
{
    public function getBranchStaff(int $branchId): array
    {
        return db_connect()->table('users')
            ->select('user_id, first_name, last_name, email, contact_number, status, branch_id')
            ->where('role_id', 3)
            ->groupStart()
                ->where('branch_id', $branchId)
                ->orWhere('branch_id IS NULL', null, false)
            ->groupEnd()
            ->orderBy('first_name', 'ASC')
            ->orderBy('last_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getBranchStaffById(int $branchId, int $staffId): ?array
    {
        $row = db_connect()->table('users')
            ->select('user_id, first_name, last_name, email, contact_number, status, branch_id, role_id')
            ->where('user_id', $staffId)
            ->where('role_id', 3)
            ->groupStart()
                ->where('branch_id', $branchId)
                ->orWhere('branch_id IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function updateBranchStaff(int $branchId, int $staffId, array $data): void
    {
        $updated = db_connect()->table('users')
            ->where('user_id', $staffId)
            ->where('role_id', 3)
            ->groupStart()
                ->where('branch_id', $branchId)
                ->orWhere('branch_id IS NULL', null, false)
            ->groupEnd()
            ->update([
                'email' => trim((string) $data['email']),
                'contact_number' => trim((string) ($data['contact_number'] ?? '')),
                'status' => (string) $data['status'],
                'branch_id' => $branchId,
            ]);

        if (! $updated) {
            throw new \RuntimeException('Failed to update staff details.');
        }
    }
}
