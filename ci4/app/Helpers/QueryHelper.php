<?php

namespace App\Helpers;

use App\Models\UserModel;

/**
 * Query Helper Class
 * 
 * Centralizes common database queries and checks
 * to reduce duplication across controllers and services
 */
class QueryHelper
{
    /**
     * Check if email exists in users table
     * 
     * @param string $email - Email to check
     * @param int $excludeUserId - User ID to exclude from check (for updates)
     * @return bool - True if email exists, false otherwise
     */
    public static function emailExists(string $email, int $excludeUserId = 0): bool
    {
        $userModel = new UserModel();
        $query = $userModel->where('email', trim($email));
        
        if ($excludeUserId > 0) {
            $query = $query->where('user_id !=', $excludeUserId);
        }
        
        return (bool) $query->first();
    }

    /**
     * Get user with basic information
     * 
     * @param int $userId - User ID to fetch
     * @return array|null - User data or null if not found
     */
    public static function getUserInfo(int $userId): ?array
    {
        $userModel = new UserModel();
        return $userModel->find($userId);
    }

    /**
     * Get plan holder with user information
     * 
     * @param int $planHolderId - Plan holder ID
     * @return array|null - Plan holder + user data
     */
    public static function getPlanHolderWithUser(int $planHolderId): ?array
    {
        $db = \Config\Database::connect();
        
        return $db->table('plan_holders ph')
            ->join('users u', 'ph.user_id = u.user_id', 'left')
            ->where('ph.plan_holder_id', $planHolderId)
            ->select('ph.*, u.user_id, u.first_name, u.last_name, u.email')
            ->get()
            ->getRowArray();
    }

    /**
     * Get active plan for plan holder
     * 
     * @param int $planHolderId - Plan holder ID
     * @return array|null - Active plan data
     */
    public static function getActivePlan(int $planHolderId): ?array
    {
        $db = \Config\Database::connect();
        
        return $db->table('plans p')
            ->join('plan_packages pp', 'p.package_id = pp.package_id', 'left')
            ->join('plan_package_versions ppv', 'pp.package_id = ppv.package_id AND ppv.is_current = 1', 'left')
            ->where('p.plan_holder_id', $planHolderId)
            ->where('p.status', 'active')
            ->select('p.*, pp.package_name, pp.monthly_fee, ppv.*')
            ->first();
    }

    /**
     * Get all user payments
     * 
     * @param int $userId - User ID
     * @param int $limit - Number of records to return (0 = all)
     * @return array - Payment records
     */
    public static function getUserPayments(int $userId, int $limit = 0): array
    {
        $db = \Config\Database::connect();
        
        $query = $db->table('payments pay')
            ->join('plans p', 'pay.plan_id = p.plan_id', 'left')
            ->join('plan_holders ph', 'p.plan_holder_id = ph.plan_holder_id', 'left')
            ->where('ph.user_id', $userId)
            ->select('pay.*, p.plan_id, ph.plan_holder_id')
            ->orderBy('pay.payment_date', 'DESC');
        
        if ($limit > 0) {
            $query = $query->limit($limit);
        }
        
        return $query->get()->getResultArray();
    }

    /**
     * Get branch information
     * 
     * @param int $branchId - Branch ID
     * @return array|null - Branch data
     */
    public static function getBranchInfo(int $branchId): ?array
    {
        $db = \Config\Database::connect();
        
        return $db->table('branches')
            ->where('branch_id', $branchId)
            ->get()
            ->getRowArray();
    }

    /**
     * Get all available service categories
     * 
     * @return array - Categories data
     */
    public static function getServiceCategories(): array
    {
        $db = \Config\Database::connect();
        
        return $db->table('service_categories')
            ->where('is_active', 1)
            ->orderBy('category_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Check if service is available for plan holder
     * 
     * @param int $planHolderId - Plan holder ID
     * @param int $serviceId - Service ID to check
     * @return bool - True if available
     */
    public static function isServiceAvailable(int $planHolderId, int $serviceId): bool
    {
        $db = \Config\Database::connect();
        
        $result = $db->table('plan_inclusions')
            ->join('plans', 'plan_inclusions.package_id = plans.package_id', 'left')
            ->where('plans.plan_holder_id', $planHolderId)
            ->where('plan_inclusions.service_id', $serviceId)
            ->where('plans.status', 'active')
            ->first();
        
        return (bool) $result;
    }

    /**
     * Count pending approvals for a branch
     * 
     * @param int $branchId - Branch ID
     * @return int - Number of pending approvals
     */
    public static function countPendingApprovals(int $branchId): int
    {
        $db = \Config\Database::connect();
        
        return $db->table('plan_holders')
            ->where('branch_id', $branchId)
            ->where('status', 'pending')
            ->countAllResults();
    }

    /**
     * Get member statistics for a branch
     * 
     * @param int $branchId - Branch ID
     * @return array - Statistics
     */
    public static function getMemberStats(int $branchId): array
    {
        $db = \Config\Database::connect();
        
        return [
            'total_active' => $db->table('plan_holders')
                ->where('branch_id', $branchId)
                ->where('status', 'active')
                ->countAllResults(),
            
            'total_pending' => $db->table('plan_holders')
                ->where('branch_id', $branchId)
                ->where('status', 'pending')
                ->countAllResults(),
            
            'total_delinquent' => $db->table('plans p')
                ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
                ->where('ph.branch_id', $branchId)
                ->where('p.lifecycle_status', 'grace')
                ->countAllResults(),
            
            'total_suspended' => $db->table('plans p')
                ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
                ->where('ph.branch_id', $branchId)
                ->whereIn('p.lifecycle_status', ['lapsed', 'forfeited'])
                ->countAllResults(),
        ];
    }

    /**
     * Get total collection amount for date range
     * 
     * @param int $branchId - Branch ID
     * @param string $startDate - Start date (YYYY-MM-DD)
     * @param string $endDate - End date (YYYY-MM-DD)
     * @return float - Total collections
     */
    public static function getTotalCollections(int $branchId, string $startDate, string $endDate): float
    {
        $db = \Config\Database::connect();
        
        $result = $db->table('payments pay')
            ->selectSum('pay.amount', 'total')
            ->join('plans p', 'pay.plan_id = p.plan_id', 'left')
            ->join('plan_holders ph', 'p.plan_holder_id = ph.plan_holder_id', 'left')
            ->where('ph.branch_id', $branchId)
            ->where('pay.status', 'approved')
            ->where('DATE(pay.payment_date) >=', $startDate)
            ->where('DATE(pay.payment_date) <=', $endDate)
            ->get()
            ->getRowArray();
        
        return (float) ($result['total'] ?? 0);
    }
}
