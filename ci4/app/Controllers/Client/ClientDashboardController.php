<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\MembershipService;
use App\Services\PaymentService;
/**
 * ClientDashboardController
 * 
 * Handles client dashboard and membership overview
 * Part of the refactored ClientPortal controller
 */
class ClientDashboardController extends BaseController
{
    use ClientPortalTrait;

    /**
     * Display client dashboard with membership overview
     */
    public function dashboard(): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        $user = $access['user'];
        $planHolder = $access['plan_holder'];
        $membershipService = new MembershipService();
        $paymentService = new PaymentService();
        $latestPlan = $planHolder ? $membershipService->getMembershipSummary((int) $planHolder['plan_holder_id']) : null;

        if (! empty($latestPlan['plan_id'])) {
            $totalContribution = (float) ($latestPlan['total_plan_amount'] ?? MembershipService::TOTAL_CONTRIBUTION);
            $paidAmount = $paymentService->getTotalPaidForPlan((int) $latestPlan['plan_id']);
            $latestPlan['paid_amount'] = $paidAmount;
            $latestPlan['total_paid'] = $paidAmount;
            $latestPlan['total_plan_amount'] = $totalContribution;
            $latestPlan['remaining_balance'] = $paymentService->getRemainingBalance((int) $latestPlan['plan_id'], $totalContribution);
        }

        $branchName = '';
        if (! empty($planHolder['branch_id'])) {
            $branch = db_connect()->table('branches')
                ->select('branch_name')
                ->where('branch_id', (int) $planHolder['branch_id'])
                ->get()
                ->getRowArray();
            $branchName = (string) ($branch['branch_name'] ?? '');
        }

        $recentPayments = [];
        if (! empty($latestPlan['plan_id'])) {
            $recentPayments = db_connect()->table('payments')
                ->select('payment_id, payment_date, amount, payment_method, reference_number, official_receipt_number, status, remarks, created_at')
                ->where('plan_id', (int) $latestPlan['plan_id'])
                ->orderBy('payment_id', 'DESC')
                ->limit(5)
                ->get()
                ->getResultArray();

            // Verified existing-member records, labelled "Recorded by branch".
            $recentPayments = array_merge($recentPayments, \App\Services\LegacyRecordService::verifiedPaymentsAsRows((int) $latestPlan['plan_id']));
            usort($recentPayments, static fn (array $a, array $b): int => strcmp((string) $b['payment_date'], (string) $a['payment_date']));
            $recentPayments = array_slice($recentPayments, 0, 5);
        }

        $membershipSince = '';
        if (! empty($planHolder['created_at'])) {
            $membershipSince = date('F Y', strtotime((string) $planHolder['created_at']));
        } elseif (! empty($user['created_at'])) {
            $membershipSince = date('F Y', strtotime((string) $user['created_at']));
        }

        $standing = ! empty($latestPlan['plan_id']) ? (new \App\Services\PlanLedgerService())->summarize((int) $latestPlan['plan_id']) : null;
        // The same figures the staff client page shows (one source: ClientPlanSummary).
        $summary = ! empty($latestPlan['plan_id']) ? (new \App\Services\ClientPlanSummary())->forPlan((int) $latestPlan['plan_id']) : null;

        return view('client/dashboard', [
            'standing' => $standing,
            'summary' => $summary,
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Overview',
            'page_sub' => 'Your membership, payments, and next steps.',
            'user' => $user,
            'plan_holder' => $planHolder,
            'plan' => $latestPlan,
            'access' => $access,
            'program' => MembershipService::getProgramInfo(),
            'branch_name' => $branchName,
            'recent_payments' => $recentPayments,
            'membership_since' => $membershipSince,
        ]);
    }

    /**
     * Display membership details and beneficiaries
     */
    public function membership(): string
    {
        $access = $this->resolveAccessState();
        $planHolder = $access['plan_holder'];
        $plan = $planHolder ? $this->latestPlan((int) $planHolder['plan_holder_id']) : null;
        $beneficiaries = [];

        if (! empty($planHolder['plan_holder_id'])) {
            $beneficiaries = db_connect()->table('beneficiaries')
                ->where('plan_holder_id', (int) $planHolder['plan_holder_id'])
                ->orderBy('beneficiary_id', 'ASC')
                ->get()
                ->getResultArray();
        }

        if (! empty($planHolder['branch_id'])) {
            $branch = db_connect()->table('branches')
                ->select('branch_name')
                ->where('branch_id', (int) $planHolder['branch_id'])
                ->get()
                ->getRowArray();
            $planHolder['branch_name'] = (string) ($branch['branch_name'] ?? '');
        }

        return view('client/membership', [
            'role_layout' => 'layouts/plan_holder',
            'access' => $access,
            'plan_holder' => $planHolder,
            'plan' => $plan,
            'program' => MembershipService::getProgramInfo(),
            'beneficiaries' => $beneficiaries,
        ]);
    }
}
