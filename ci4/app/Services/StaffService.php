<?php

namespace App\Services;

class StaffService
{
    public function getStaffByBranch(int $branchId): array
    {
        return db_connect()->table('users')
            ->select('user_id, first_name, last_name, email, contact_number, status, account_status, branch_id')
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

    public function assignStaffToService(array $data): int
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $existing = $db->table('assignments')
                ->where('service_id', (int) $data['service_id'])
                ->where('staff_id', (int) $data['staff_id'])
                ->get()
                ->getRowArray();

            if ($existing) {
                throw new \RuntimeException('This staff member is already assigned to the selected service.');
            }

            $db->table('assignments')->insert([
                'service_id' => (int) $data['service_id'],
                'staff_id' => (int) $data['staff_id'],
                'assigned_date' => date('Y-m-d H:i:s'),
            ]);

            $assignmentId = (int) $db->insertID();

            $db->table('services')
                ->where('service_id', (int) $data['service_id'])
                ->update([
                    'assigned_staff' => (int) $data['staff_id'],
                ]);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to save assignment.');
            }

            $db->transCommit();

            return $assignmentId;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function getStaffAssignments(int $branchId): array
    {
        return db_connect()->table('assignments a')
            ->select("a.assignment_id, a.assigned_date, a.service_id, a.staff_id, u.first_name, u.last_name, COALESCE(sl.service_name, '-') AS service_type, s.service_date, s.status", false)
            ->join('users u', 'u.user_id = a.staff_id', 'inner')
            ->join('services s', 's.service_id = a.service_id', 'inner')
            ->join('service_list sl', 'sl.service_list_id = s.service_list_id', 'left')
            ->where('u.branch_id', $branchId)
            ->orderBy('a.assigned_date', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function getStaffPerformance(int $staffId): array
    {
        $db = db_connect();

        $rows = $db->table('assignments a')
            ->select('s.status, COUNT(*) AS total')
            ->join('services s', 's.service_id = a.service_id', 'inner')
            ->where('a.staff_id', $staffId)
            ->groupBy('s.status')
            ->get()
            ->getResultArray();

        $summary = [
            'total_assigned' => 0,
            'pending' => 0,
            'ongoing' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['total'] ?? 0);
            if (array_key_exists($status, $summary)) {
                $summary[$status] = $count;
            }
            $summary['total_assigned'] += $count;
        }

        return $summary;
    }

    public function getServiceOptionsByBranch(int $branchId): array
    {
        return db_connect()->table('services s')
            ->select("s.service_id, COALESCE(sl.service_name, '-') AS service_type, s.service_date, s.status, u.first_name, u.last_name", false)
            ->join('plan_holders ph', 'ph.plan_holder_id = s.plan_holder_id', 'left')
            ->join('users u', 'u.user_id = ph.user_id', 'left')
            ->join('service_list sl', 'sl.service_list_id = s.service_list_id', 'left')
            ->where('s.branch_id', $branchId)
            ->orderBy('s.service_date', 'DESC')
            ->orderBy('s.service_id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function isStaffInBranch(int $staffId, int $branchId): bool
    {
        $row = db_connect()->table('users')
            ->select('user_id')
            ->where('user_id', $staffId)
            ->where('role_id', 3)
            ->groupStart()
                ->where('branch_id', $branchId)
                ->orWhere('branch_id IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getRowArray();

        return (bool) $row;
    }

    public function isServiceInBranch(int $serviceId, int $branchId): bool
    {
        $row = db_connect()->table('services')
            ->select('service_id')
            ->where('service_id', $serviceId)
            ->where('branch_id', $branchId)
            ->get()
            ->getRowArray();

        return (bool) $row;
    }
}
