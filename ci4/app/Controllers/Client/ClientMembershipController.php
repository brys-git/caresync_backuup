<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\MembershipService;

/**
 * ClientMembershipController
 * 
 * Handles membership status and summary display
 * Part of the refactored ClientPortal controller
 */
class ClientMembershipController extends BaseController
{
    use ClientPortalTrait;

    /**
     * Display membership status and summary
     */
    public function membership(): string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        $planHolder = $access['plan_holder'] ?? null;
        $planHolderId = (int) ($planHolder['plan_holder_id'] ?? 0);
        $activePlan = $planHolderId > 0 ? $this->activePlan($planHolderId) : null;
        $latestPlan = $planHolderId > 0 ? $this->latestPlan($planHolderId) : null;

        $membershipService = new MembershipService();
        $membershipSummary = $planHolderId > 0
            ? $membershipService->getMembershipSummary($planHolderId)
            : null;

        $plan = $activePlan ?? $latestPlan ?? [];
        $planId = (int) ($plan['plan_id'] ?? 0);

        $currentContributions = $planId > 0
            ? db_connect()->table('payments p')
                ->select('p.payment_id, p.payment_date, p.amount, p.payment_method, p.status, p.reference_number')
                ->where('p.plan_id', $planId)
                ->orderBy('p.payment_date', 'DESC')
                ->limit(12)
                ->get()
                ->getResultArray()
            : [];

        // Verified existing-member records count as contributions too.
        if ($planId > 0) {
            $currentContributions = array_merge($currentContributions, \App\Services\LegacyRecordService::verifiedPaymentsAsRows($planId));
            usort($currentContributions, static fn (array $a, array $b): int => strcmp((string) $b['payment_date'], (string) $a['payment_date']));
            $currentContributions = array_slice($currentContributions, 0, 12);
        }

        $beneficiaries = $planHolderId > 0
            ? db_connect()->table('beneficiaries')
                ->select('beneficiary_id, first_name, middle_name, last_name, relationship, is_primary, name_extension, date_of_birth')
                ->where('plan_holder_id', $planHolderId)
                ->orderBy('is_primary', 'DESC')
                ->orderBy('first_name', 'ASC')
                ->get()
                ->getResultArray()
            : [];

        $branchInfo = $planHolderId > 0
            ? db_connect()->table('branches')
                ->select('branch_id, branch_name, contact_number, address_barangay, address_city, address_province')
                ->where('branch_id', (int) ($planHolder['branch_id'] ?? 0))
                ->get()
                ->getRowArray()
            : [];

        return view('client/membership', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'My plan',
            'show_page_head' => false,
            'page_sub' => 'Damayan program membership overview.',
            'access' => $access,
            'active_plan' => $activePlan,
            'membership_summary' => $membershipSummary,
            'current_contributions' => $currentContributions,
            'beneficiaries' => $beneficiaries,
            'branch_info' => $branchInfo,
            'plan' => $plan,
            'plan_holder' => $planHolder,
            'program' => MembershipService::getProgramInfo(),
        ]);
    }
}
