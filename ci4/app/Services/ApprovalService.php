<?php

namespace App\Services;

use App\Services\MembershipService;
use App\Services\NotificationService;

/**
 * ApprovalService
 * 
 * Handles all approval workflows for:
 * - Initial plan holder registration
 * - Service applications
 * - Package/service creation
 */
class ApprovalService
{
    /**
     * Approve initial payment and activate plan holder
     * 
     * Conditions:
     * - payment.status = 'paid'
     * - plan_holder.status = 'inactive'
     * 
     * @param int $paymentId
     * @return bool
     */
    public function approveInitialPayment(int $paymentId): bool
    {
        if ($paymentId <= 0) {
            return false;
        }

        // Select full payment row to avoid SQL errors if optional columns (e.g. months_covered) are missing
        $payment = db_connect()->table('payments pay')
            ->select('pay.*, p.plan_holder_id, ph.status AS holder_status, u.user_id')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->where('pay.payment_id', $paymentId)
            ->get()
            ->getRowArray();

        if (!$payment) {
            return false;
        }

        if ((string) ($payment['status'] ?? '') !== 'paid') {
            return false;
        }

        if ((string) ($payment['holder_status'] ?? '') !== 'inactive') {
            return false;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $planHolderId = (int) ($payment['plan_holder_id'] ?? 0);
            $planId = (int) ($payment['plan_id'] ?? 0);

            if ($planHolderId <= 0 || $planId <= 0) {
                throw new \RuntimeException('Invalid plan or plan holder');
            }

            // Determine months covered: prefer explicit months_covered, otherwise infer from amount
            $monthsCovered = 0;
            if (isset($payment['months_covered']) && (int) $payment['months_covered'] > 0) {
                $monthsCovered = (int) $payment['months_covered'];
            } else {
                // Infer months from amount / monthly fee
                $program = MembershipService::getProgramInfo();
                $monthlyFee = (float) ($program['monthly_fee'] ?? 0);
                $amount = (float) ($payment['amount'] ?? 0);
                if ($monthlyFee > 0 && $amount > 0) {
                    $monthsCovered = (int) max(1, round($amount / $monthlyFee));
                } else {
                    $monthsCovered = 1;
                }
            }
            $monthsCovered = max(1, $monthsCovered);

            // Mark plan as active and apply coverage
            db_connect()->table('plans')
                ->where('plan_id', $planId)
                ->update([
                    'status' => 'active',
                    'membership_state' => 'active',
                ]);

            (new MembershipService())->applyMembershipCoverage($planId, $monthsCovered);
            (new CycleService())->afterPaymentVerified($paymentId, $planId);

            // Mark plan holder as active
            db_connect()->table('plan_holders')
                ->where('plan_holder_id', $planHolderId)
                ->update(['status' => 'active']);

            // Update user access
            db_connect()->table('users')
                ->where('user_id', (int) ($payment['user_id'] ?? 0))
                ->update([
                    'is_plan_holder' => 1,
                    'account_status' => 'verified',
                ]);

            (new MembershipService())->enforceOneActivePlan($planHolderId);

            (new NotificationService())->notify(
                (int) ($payment['user_id'] ?? 0),
                'Your registration has been approved. Your plan is now active.',
                'registration_approved'
            );

            // Log the activity
            helper('activity_log');
            log_activity((int) session('user_id'), 'approved', 'payment', $paymentId, 'Approved initial payment and activated plan holder');

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to approve payment');
            }

            $db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $db->transRollback();

            return false;
        }
    }

    /**
     * Reject initial payment
     * 
     * @param int $paymentId
     * @param string $reason
     * @return bool
     */
    public function rejectInitialPayment(int $paymentId, string $reason = ''): bool
    {
        if ($paymentId <= 0) {
            return false;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            db_connect()->table('payments')
                ->where('payment_id', $paymentId)
                ->update(['status' => 'cancelled', 'remarks' => $reason]);

            // Log the activity
            helper('activity_log');
            log_activity((int) session('user_id'), 'rejected', 'payment', $paymentId, 'Rejected payment: ' . $reason);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to reject payment');
            }

            $db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $db->transRollback();

            return false;
        }
    }

    /**
     * Approve service application
     * 
     * @param int $applicationId
     * @return bool
     */
    public function approveServiceApplication(int $applicationId): bool
    {
        if ($applicationId <= 0) {
            return false;
        }

        $application = db_connect()->table('service_applications')
            ->where('application_id', $applicationId)
            ->get()
            ->getRowArray();

        if (! $application || (string) ($application['status'] ?? '') !== 'pending') {
            return false;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            db_connect()->table('service_applications')
                ->where('application_id', $applicationId)
                ->update(['status' => 'approved']);

            // Log the activity
            helper('activity_log');
            log_activity((int) session('user_id'), 'approved', 'service_application', $applicationId, 'Approved service application');

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to approve service application');
            }

            $db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $db->transRollback();

            return false;
        }
    }

    /**
     * Approve pending package/service from branch admin
     * 
     * @param string $type 'package' or 'service'
     * @param int $itemId
     * @return bool
     */
    public function approveServiceOffer(string $type, int $itemId): bool
    {
        if ($itemId <= 0 || !in_array($type, ['package', 'service'], true)) {
            return false;
        }

        $table = $type === 'package' ? 'packages' : 'service_list';

        $db = db_connect();
        $db->transBegin();

        try {
            db_connect()->table($table)
                ->where($type === 'package' ? 'package_id' : 'service_list_id', $itemId)
                ->update(['status' => 'approved']);

            // Log the activity
            helper('activity_log');
            log_activity((int) session('user_id'), 'approved', $type, $itemId, 'Approved pending ' . $type);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to approve ' . $type);
            }

            $db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $db->transRollback();

            return false;
        }
    }

    /**
     * Reject pending package/service from branch admin
     * 
     * @param string $type 'package' or 'service'
     * @param int $itemId
     * @param string $reason
     * @return bool
     */
    public function rejectServiceOffer(string $type, int $itemId, string $reason = ''): bool
    {
        if ($itemId <= 0 || !in_array($type, ['package', 'service'], true)) {
            return false;
        }

        $table = $type === 'package' ? 'packages' : 'service_list';

        $db = db_connect();
        $db->transBegin();

        try {
            db_connect()->table($table)
                ->where($type === 'package' ? 'package_id' : 'service_list_id', $itemId)
                ->update(['status' => 'rejected']);

            // Log the activity
            helper('activity_log');
            log_activity((int) session('user_id'), 'rejected', $type, $itemId, 'Rejected pending ' . $type . ': ' . $reason);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Failed to reject ' . $type);
            }

            $db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $db->transRollback();

            return false;
        }
    }
}
