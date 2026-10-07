<?php

namespace App\Controllers\Client;

use App\Controllers\BaseController;
use App\Services\NotificationService;
use App\Config\ValidationRules;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\BranchModel;
use App\Services\MembershipService;
use App\Services\GovernmentIdVerificationService;
use App\Services\PlanHolderRegistrationService;

/**
 * ClientRegistrationController
 * 
 * Handles plan holder registration flow
 * Part of the refactored ClientPortal controller
 * 
 * Uses centralized validation rules to reduce code duplication
 */
class ClientRegistrationController extends BaseController
{
    use ClientPortalTrait;

    /**
     * Display plan information before registration
     */
    public function planInfo(): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }

        if (($access['state'] ?? 'unregistered') === 'active') {
            return redirect()->to('/client/dashboard');
        }

        if (($access['state'] ?? 'unregistered') === 'awaiting_activation') {
            return redirect()->to('/initial-payment');
        }

        $program = MembershipService::getProgramInfo();

        $db = db_connect();

        // The Regular Wood Casket (Damayan entitlement) and its inclusions -
        // same data the Services & Packages page uses, reused here so a
        // prospective plan holder sees accurate figures before registering
        // instead of the old generic/static benefits list.
        $entitlementPackage = $db->table('packages')
            ->select('package_id, package_name, base_price')
            ->where('is_damayan_entitlement', 1)
            ->orderBy('package_id', 'ASC')
            ->get()
            ->getRowArray();

        $inclusions = [];
        if ($entitlementPackage) {
            $inclusions = $db->table('package_items')
                ->select('item_id, item_name, description')
                ->where('package_id', (int) $entitlementPackage['package_id'])
                ->orderBy('item_id', 'ASC')
                ->get()
                ->getResultArray();
        }

        $otherPackages = $db->table('packages')
            ->select('package_id, package_name, base_price')
            ->where('is_damayan_entitlement', 0)
            ->where('is_available', 1)
            ->orderBy('base_price', 'ASC')
            ->get()
            ->getResultArray();

        $services = $db->table('service_list')
            ->select('service_list_id, service_name, description')
            ->where('is_available', 1)
            ->orderBy('service_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('client/plan_info', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Plan Information',
            'page_sub' => 'Review the ' . (string) ($program['name'] ?? 'Damayan Burial Program') . ' details before proceeding.',
            'access' => $access,
            'program' => $program,
            'entitlement_package' => $entitlementPackage,
            'inclusions' => $inclusions,
            'other_packages' => $otherPackages,
            'services' => $services,
            'monthly_fee' => MembershipService::MONTHLY_FEE,
            'total_contribution' => MembershipService::TOTAL_CONTRIBUTION,
        ]);
    }

    /**
     * Display plan registration form
     */
    public function planRegistration(int $planId = 0): ResponseInterface|string
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        $user = $access['user'];

        if (! $user) {
            return redirect()->to('/signin')->with('error', 'You must be logged in to register.');
        }

        if (($access['state'] ?? 'unregistered') === 'active') {
            return redirect()->to('/client/dashboard');
        }

        if (($access['state'] ?? 'unregistered') === 'awaiting_activation') {
            return redirect()->to('/id-verification-status');
        }

        $program = MembershipService::getProgramInfo();
        $planId = $planId > 0 ? $planId : (int) ($program['package_id'] ?? 0);

        $branches = (new BranchModel())
            ->orderBy('branch_name', 'ASC')
            ->findAll();

        $currentUser = $access['user'];
        $planHolder = $access['plan_holder'] ?? [];

        $idVerificationService = new GovernmentIdVerificationService();

        return view('client/plan_registration', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Plan registration',
            'page_sub' => 'Complete each step, then review before submitting.',
            'crumbs' => [['label' => 'Plan registration']],
            'access' => $access,
            'program' => $program,
            'plans' => (new \App\Services\PlanBuilderService())->listApprovedActive(),
            'plan_id' => $planId,
            'branches' => $branches,
            'plan_holder' => $planHolder,
            'user' => $currentUser,
            'user_email' => $currentUser['email'] ?? '',
            'user_phone' => $currentUser['contact_number'] ?? '',
            'id_types' => $idVerificationService->idTypes(),
            'latest_verification' => $idVerificationService->latestForUser((int) $currentUser['user_id']),
        ]);
    }

    /**
     * Submit plan registration - thin now (Prompt 7, Step D): validates
     * required-field/format rules with the usual CI4 validator, then
     * delegates everything else (PSGC re-verification, Government ID
     * enforcement, beneficiary parsing, plan_holder/plan creation) to
     * PlanHolderRegistrationService::register(), the one canonical routine
     * Staff/Branch Admin/System Admin registration now share too.
     */
    public function submitPlanRegistration(int $planId = 0)
    {
        try {
            $access = $this->resolveAccessState();
        } catch (\RuntimeException $e) {
            return redirect()->to('/signin')->with('error', 'Session expired. Please log in again.');
        }
        $user = $access['user'];

        if (! $user) {
            return redirect()->to('/signin')->with('error', 'You must be logged in to register.');
        }

        if (($access['state'] ?? 'unregistered') === 'active') {
            return redirect()->to('/client/dashboard')->with('info', 'You are already registered.');
        }

        if (($access['state'] ?? 'unregistered') === 'awaiting_activation') {
            return redirect()->to('/id-verification-status')->with('info', 'You are already registered.');
        }

        $branchId = (int) $this->request->getPost('branch_id');
        if ($branchId <= 0) {
            return redirect()->back()->withInput()->with('error', 'Please select a Branch Office.');
        }

        $rules = ValidationRules::getPlanRegistrationRules();
        $messages = ValidationRules::getValidationMessages();

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        try {
            $planHolderId = (new PlanHolderRegistrationService())->register(
                $this->request->getPost() ?? [],
                $branchId,
                'self',
                $user
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', 'Plan registration failed: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Registration failed. Please try again.');
        }

        return redirect()->to('/id-verification-status')
            ->with('success', 'Plan registration submitted successfully. A branch staff member will verify your Government ID before you can make your initial payment.');
    }
}
