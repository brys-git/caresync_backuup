<?php

namespace App\Services;

/**
 * A collector's GCash receiving account (collector_gcash_accounts) and the
 * lookup that decides which collector's account a given client should pay to.
 */
class CollectorGcashService
{
    public function forCollector(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return db_connect()->table('collector_gcash_accounts')
            ->where('user_id', $userId)
            ->get()
            ->getRowArray() ?: null;
    }

    /**
     * Creates or updates the collector's account. $qrPath: a WRITEPATH-
     * relative path to store, or null to leave the existing QR untouched;
     * pass $removeQr = true to clear it.
     */
    public function save(int $userId, string $gcashNumber, ?string $accountName, ?string $qrPath, bool $removeQr = false): void
    {
        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $existing = $this->forCollector($userId);

        $data = [
            'gcash_number' => $gcashNumber,
            'account_name' => $accountName !== null && $accountName !== '' ? $accountName : null,
            'updated_at'   => $now,
        ];

        if ($qrPath !== null) {
            $data['qr_path'] = $qrPath;
        } elseif ($removeQr) {
            $data['qr_path'] = null;
        }

        if ($existing) {
            $db->table('collector_gcash_accounts')->where('user_id', $userId)->update($data);
            return;
        }

        $db->table('collector_gcash_accounts')->insert($data + [
            'user_id'    => $userId,
            'created_at' => $now,
        ]);
    }

    /**
     * The collector whose GCash account a client in $branchId / $barangayCode
     * should pay to: a collector assigned to that exact barangay wins over a
     * branch-wide one (barangay_code NULL), earliest assignment first, and
     * only collectors who have actually saved a GCash number are considered.
     * Mirrors CollectorAssignmentService::canActOnBarangay() - whoever this
     * returns is someone who is also allowed to approve that client's GCash
     * payment. Null when nobody qualifies yet.
     *
     * @return array{user_id:int, collector_name:string, gcash_number:string, account_name:?string, qr_path:?string, scope:string}|null
     */
    public function resolveForClient(int $branchId, ?string $barangayCode): ?array
    {
        if ($branchId <= 0) {
            return null;
        }

        $barangayCode = trim((string) $barangayCode);

        $builder = db_connect()->table('collector_assignments ca')
            ->select('u.user_id, u.first_name, u.last_name, g.gcash_number, g.account_name, g.qr_path, ca.barangay_code')
            ->join('users u', 'u.user_id = ca.user_id', 'inner')
            ->join('collector_gcash_accounts g', 'g.user_id = ca.user_id', 'inner')
            ->where('ca.branch_id', $branchId)
            ->where('(u.role_id = 5 OR u.is_collector = 1)', null, false)
            ->where('u.account_status', 'verified');

        if ($barangayCode !== '') {
            $builder->groupStart()
                ->where('ca.barangay_code', $barangayCode)
                ->orWhere('ca.barangay_code IS NULL', null, false)
                ->groupEnd();
        } else {
            $builder->where('ca.barangay_code IS NULL', null, false);
        }

        $row = $builder
            ->orderBy('(ca.barangay_code IS NULL)', 'ASC', false)
            ->orderBy('ca.assigned_at', 'ASC')
            ->get(1)
            ->getRowArray();

        if (! $row) {
            return null;
        }

        return [
            'user_id'        => (int) $row['user_id'],
            'collector_name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'gcash_number'   => (string) $row['gcash_number'],
            'account_name'   => $row['account_name'] !== null ? (string) $row['account_name'] : null,
            'qr_path'        => $row['qr_path'] !== null ? (string) $row['qr_path'] : null,
            'scope'          => $row['barangay_code'] === null ? 'branch' : 'barangay',
        ];
    }
}
