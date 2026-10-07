<?php

namespace App\Services;

/**
 * ActivityLogService
 * 
 * Handles all audit logging for system activities.
 * Tracks who did what, when, and where.
 */
class ActivityLogService
{
    /**
     * Log an activity
     * 
     * PHASE REQUIREMENT: Enhanced logging with additional fields
     * Captures user_role, old_status, new_status, IP address, device info, and full change tracking.
     * 
     * @param int $userId - User ID performing the action
     * @param string $action - Action name (approved, rejected, created, updated, deleted)
     * @param string $module - Module affected (payment, service, package, plan_holder)
     * @param int $targetId - ID of the record affected
     * @param string $description - Detailed description
     * @param array|null $oldValues - Previous values (for updates)
     * @param array|null $newValues - New values (for updates)
     * @param array|null $metadata - Additional metadata (old_status, new_status, user_role, device)
     * @return bool
     */
    public function log(
        int $userId,
        string $action,
        string $module,
        int $targetId,
        string $description = '',
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): bool {
        if ($userId <= 0 || $targetId <= 0 || empty($module)) {
            return false;
        }

        try {
            $logData = [
                'user_id' => $userId,
                'action' => $action,
                'module' => $module,
                'target_id' => $targetId,
                'description' => $description,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => $this->getClientIp(),
                'created_at' => date('Y-m-d H:i:s'),
            ];
            
            // PHASE REQUIREMENT: Add enhanced metadata
            if ($metadata) {
                if (isset($metadata['old_status'])) {
                    $logData['old_status'] = (string) $metadata['old_status'];
                }
                if (isset($metadata['new_status'])) {
                    $logData['new_status'] = (string) $metadata['new_status'];
                }
                if (isset($metadata['user_role'])) {
                    $logData['user_role'] = (string) $metadata['user_role'];
                }
                if (isset($metadata['device'])) {
                    $logData['device'] = (string) $metadata['device'];
                }
            }

            return (bool) db_connect()->table('activity_logs')
                ->insert($logData);
        } catch (\Throwable $e) {
            log_message('error', 'ActivityLogService::log - ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get activity logs for a user
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserLogs(int $userId, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }

        return db_connect()->table('activity_logs')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Get activity logs for a module
     * 
     * @param string $module
     * @param int $limit
     * @return array
     */
    public function getModuleLogs(string $module, int $limit = 100): array
    {
        if (empty($module)) {
            return [];
        }

        return db_connect()->table('activity_logs')
            ->where('module', $module)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Get activity logs for a specific record
     * 
     * @param string $module
     * @param int $targetId
     * @return array
     */
    public function getRecordLogs(string $module, int $targetId): array
    {
        if (empty($module) || $targetId <= 0) {
            return [];
        }

        // Same missing-alias bug as getAllLogs() above - fixed alongside it.
        return db_connect()->table('activity_logs al')
            ->select('al.*, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = al.user_id', 'left')
            ->where('al.module', $module)
            ->where('al.target_id', $targetId)
            ->orderBy('al.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get all activity logs (admin only)
     * 
     * @param int $limit
     * @return array
     */
    public function getAllLogs(int $limit = 500): array
    {
        // table('activity_logs') without the 'al' alias left every 'al.'
        // reference in select()/join()/orderBy() pointing at a table that
        // didn't exist ("Unknown table 'al'") - this method had no caller
        // until the 2026-09-09 system scan wired up the Admin dashboard,
        // so the bug was never actually triggered before.
        return db_connect()->table('activity_logs al')
            ->select('al.*, u.first_name, u.last_name')
            ->join('users u', 'u.user_id = al.user_id', 'left')
            ->orderBy('al.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Get client IP address
     * 
     * @return string
     */
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
