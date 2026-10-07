<?php

namespace App\Controllers;

use App\Models\BranchModel;
use App\Models\NotificationModel;
use App\Models\PaymentModel;
use App\Models\PlanHolderModel;
use App\Models\PlanModel;
use App\Models\UserModel;
use App\Services\ActivityLogService;
use App\Services\GovernmentIdVerificationService;
use App\Services\NotificationService;
use App\Services\MembershipService;
use App\Services\PaymentService;

class ClientPortal extends BaseController
{
    private ?bool $paymentsHasProofImage = null;

    public function registrationApprovals(): string
    {
        $roleId = (int) session('role_id');
        if (! in_array($roleId, [1, 2], true)) {
            return redirect()->to('/unauthorized');
        }

        $branchId = (int) session('branch_id');

        $builder = db_connect()->table('payments pay')
            ->select('pay.payment_id, pay.plan_id, pay.amount, pay.payment_date, pay.payment_method, pay.reference_number, pay.status, pay.branch_id, p.plan_holder_id, ph.status AS holder_status, u.user_id, u.first_name, u.last_name, u.email, b.branch_name')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = pay.branch_id', 'left')
            ->where('pay.status', 'pending')
            ->where('pay.payment_method', 'gcash')
            ->where('ph.status', 'inactive')
            ->orderBy('pay.payment_id', 'DESC');

        if ($roleId === 2) {
            $builder->where('pay.branch_id', $branchId);
        }

        $search = cs_search_term($this->request->getGet('search'));
        cs_apply_search($builder, $search, ['u.first_name', 'u.last_name', 'u.email', 'pay.reference_number']);

        $rows = $builder->get()->getResultArray();

        // Task 2: surface Government ID verifications that need a human
        // look (blurry image, provider outage, or a borderline name
        // match) right in this same queue - one lookup per row is fine
        // at this queue's size (pending initial-payment approvals only).
        $idVerificationService = new GovernmentIdVerificationService();
        foreach ($rows as &$row) {
            $row['id_verification'] = $idVerificationService->latestForUser((int) $row['user_id']);
        }
        unset($row);

        return view('approvals/registration_queue', [
            'role_layout' => $roleId === 1 ? 'layouts/admin' : 'layouts/branch_admin',
            'page_title' => 'Registration approvals',
            'page_sub' => 'Pending initial payments requiring verification before full access activation.',
            'rows' => $rows,
            'search' => $search,
            'search_action' => base_url($roleId === 1 ? 'admin/registration-approvals' : 'branch-admin/registration-approvals'),
            'can_verify' => $roleId === 2,
        ]);
    }

    public function dashboard(): string
    {
        $access = $this->resolveAccessState();
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
            $latestPlan['remaining_balance'] = max(0, round($totalContribution - $paidAmount, 2));
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

        $membershipSince = '';
        if (! empty($planHolder['created_at'])) {
            $membershipSince = date('F Y', strtotime((string) $planHolder['created_at']));
        } elseif (! empty($user['created_at'])) {
            $membershipSince = date('F Y', strtotime((string) $user['created_at']));
        }

        return view('client/dashboard', [
            'role_layout' => 'layouts/plan_holder',
            'page_title' => 'Overview',
            'page_sub' => 'Your membership, payments, and next steps.',
            'user' => $user,
            'plan_holder' => $planHolder,
            'plan' => $latestPlan,
            'access' => $access,
            'program' => MembershipService::getProgramInfo(),
            'branch_name' => $branchName,
            'membership_since' => $membershipSince,
        ]);
    }

    public function clientManagement(): string
    {
        $roleId = (int) session('role_id');
        if ($roleId !== 2) {
            return redirect()->to('/unauthorized');
        }

        $branchId = (int) session('branch_id');

        $holders = db_connect()->table('plan_holders ph')
            ->select('ph.plan_holder_id, ph.status AS plan_holder_status, ph.unique_identifier, u.user_id, u.first_name, u.last_name, u.email')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->where('ph.branch_id', $branchId)
            ->orderBy('ph.plan_holder_id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($holders as &$holder) {
            $latestInitialPayment = $this->latestInitialPayment((int) $holder['plan_holder_id']);
            $holder['initial_payment_status'] = strtolower((string) ($latestInitialPayment['status'] ?? 'none'));
            $holder['initial_payment_id'] = (int) ($latestInitialPayment['payment_id'] ?? 0);
        }
        unset($holder);

        return view('branch_admin/client_management/index', [
            'role_layout' => 'layouts/branch_admin',
            'holders' => $holders,
        ]);
    }

    public function clientManagementDetails(int $planHolderId)
    {
        $roleId = (int) session('role_id');
        if ($roleId !== 2) {
            return redirect()->to('/unauthorized');
        }

        $branchId = (int) session('branch_id');

        $holder = db_connect()->table('plan_holders ph')
            ->select('ph.plan_holder_id, ph.branch_id, ph.status AS plan_holder_status, ph.unique_identifier, ph.address_barangay, ph.address_city, u.user_id, u.first_name, u.last_name, u.email')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->where('ph.plan_holder_id', $planHolderId)
            ->where('ph.branch_id', $branchId)
            ->get()
            ->getRowArray();

        if (! $holder) {
            return redirect()->to('/branch-admin/client-management')->with('error', 'Plan holder record not found.');
        }

        $latestInitialPayment = $this->latestInitialPayment((int) $holder['plan_holder_id']);
        $paymentStatus = strtolower((string) ($latestInitialPayment['status'] ?? 'none'));
        $canApprove = $paymentStatus === 'paid' && strtolower((string) ($holder['plan_holder_status'] ?? '')) === 'inactive';

        return view('branch_admin/client_management/details', [
            'role_layout' => 'layouts/branch_admin',
            'holder' => $holder,
            'initial_payment' => $latestInitialPayment,
            'can_approve' => $canApprove,
            'approval_message' => $canApprove ? '' : 'Payment not yet verified',
        ]);
    }

    public function approvePlanHolder(int $planHolderId)
    {
        $roleId = (int) session('role_id');
        if ($roleId !== 2) {
            return redirect()->to('/unauthorized');
        }

        $branchId = (int) session('branch_id');
        $planHolderModel = new PlanHolderModel();
        $userModel = new UserModel();
        $planModel = new PlanModel();

        $holder = $planHolderModel->find($planHolderId);
        if (! $holder || (int) ($holder['branch_id'] ?? 0) !== $branchId) {
            return redirect()->to('/branch-admin/client-management')->with('error', 'Plan holder record not found in your branch.');
        }

        if (strtolower((string) ($holder['status'] ?? '')) !== 'inactive') {
            return redirect()->back()->with('error', 'Plan holder is already active.');
        }

        $initialPayment = $this->latestInitialPayment((int) $holder['plan_holder_id']);
        if (! $initialPayment || strtolower((string) ($initialPayment['status'] ?? '')) !== 'paid') {
            return redirect()->back()->with('error', 'Payment not yet verified.');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $packageData = $this->resolvePackageAndVersion();
            $existingPlan = $this->latestPlan((int) $holder['plan_holder_id']);

            if ($existingPlan) {
                $planModel->update((int) $existingPlan['plan_id'], [
                    'package_id' => $packageData['package_id'],
                    'monthly_fee' => MembershipService::MONTHLY_FEE,
                    'start_date' => date('Y-m-d'),
                    'status' => 'active',
                    'remaining_balance' => 0,
                    'version_id' => $packageData['version_id'],
                ]);
            } else {
                $planId = (int) $planModel->insert([
                    'plan_holder_id' => (int) $holder['plan_holder_id'],
                    'package_id' => $packageData['package_id'],
                    'monthly_fee' => MembershipService::MONTHLY_FEE,
                    'passbook_fee' => 50,
                    'start_date' => date('Y-m-d'),
                    'status' => 'active',
                    'months_paid' => 0,
                    'remaining_balance' => 0,
                    'version_id' => $packageData['version_id'],
                ], true);

                if ($planId <= 0) {
                    throw new \RuntimeException('Unable to create default plan.');
                }

                $existingPlan = $planModel->find($planId);
            }

            if (! $existingPlan) {
                throw new \RuntimeException('Active plan holder must have an active plan.');
            }

            $this->enforceSingleActivePlan((int) $holder['plan_holder_id'], (int) $existingPlan['plan_id']);

            $planHolderModel->update((int) $holder['plan_holder_id'], [
                'status' => 'active',
            ]);

            $userModel->update((int) $holder['user_id'], [
                'is_plan_holder' => 1,
                'account_status' => 'verified',
                'branch_id' => $branchId,
            ]);

            (new NotificationService())->notify((int) $holder['user_id'], 'Your registration has been approved. Your plan is now active.', 'registration_pending');
            (new ActivityLogService())->log(
                (int) session('user_id'),
                'approved',
                'plan_holder',
                (int) $holder['plan_holder_id'],
                'Approved plan holder registration from client portal flow',
                ['status' => 'inactive'],
                ['status' => 'active']
            );

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to complete approval.');
            }

            $db->transCommit();

            return redirect()->to('/branch-admin/client-management/' . (int) $holder['plan_holder_id'])
                ->with('success', 'Plan holder approved and activated successfully.');
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function profile(): string
    {
        $access = $this->resolveAccessState();
        $user = $access['user'];
        $planHolder = $access['plan_holder'];

        return view('client/profile', [
            'role_layout' => 'layouts/plan_holder',
            'user' => $user,
            'plan_holder' => $planHolder,
            'access' => $access,
        ]);
    }

    public function updateProfile()
    {
        $user = $this->currentUser();
        $rules = [
            'email' => 'required|valid_email|max_length[100]',
            'contact_number' => 'permit_empty|max_length[20]',
            'first_name' => 'required|max_length[50]',
            'last_name' => 'required|max_length[50]',
            'address_barangay' => 'permit_empty|max_length[100]',
            'address_city' => 'permit_empty|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userModel = new UserModel();
        $planHolderModel = new PlanHolderModel();
        $userId = (int) $user['user_id'];

        $existingEmail = $userModel
            ->where('email', trim((string) $this->request->getPost('email')))
            ->where('user_id !=', $userId)
            ->first();

        if ($existingEmail) {
            return redirect()->back()->withInput()->with('error', 'Email is already in use by another account.');
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $userModel->update($userId, [
                'email' => trim((string) $this->request->getPost('email')),
                'contact_number' => trim((string) $this->request->getPost('contact_number')),
                'first_name' => trim((string) $this->request->getPost('first_name')),
                'last_name' => trim((string) $this->request->getPost('last_name')),
            ]);

            $planHolder = $this->currentPlanHolder();
            if ($planHolder) {
                $planHolderModel->update((int) $planHolder['plan_holder_id'], [
                    'address_barangay' => trim((string) $this->request->getPost('address_barangay')),
                    'address_city' => trim((string) $this->request->getPost('address_city')),
                    'civil_status' => trim((string) $this->request->getPost('civil_status')),
                    'citizenship' => trim((string) $this->request->getPost('citizenship')),
                ]);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to save profile details.');
            }

            $db->transCommit();

            return redirect()->to('/client/profile')->with('success', 'Profile updated successfully.');
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function payment(): string
    {
        $access = $this->resolveAccessState();
        $planHolder = $access['plan_holder'];
        $program = MembershipService::getProgramInfo();

        $membershipPlans = [];
        $db = db_connect();
        if ($db->tableExists('membership_programs')) {
            $membershipPlans = $db->table('membership_programs')
                ->select('program_id, program_name')
                ->where('is_active', 1)
                ->orderBy('program_name', 'ASC')
                ->get()
                ->getResultArray();
        }

        if (($access['state'] ?? 'new') === 'new' || ! $planHolder) {
            return view('client/payment', [
                'role_layout' => 'layouts/plan_holder',
                'access' => $access,
                'plan' => null,
                'payments' => [],
                'membership_plans' => $membershipPlans,
                'program' => $program,
            ]);
        }

        $plan = $this->latestPlan((int) $planHolder['plan_holder_id']);
        $payments = [];
        if ($plan) {
            $payments = (new PaymentModel())
                ->where('plan_id', (int) $plan['plan_id'])
                // Panel brief, section 4: sort payment history ascending.
                ->orderBy('payment_date', 'ASC')
                ->orderBy('payment_id', 'ASC')
                ->findAll();
        }

        return view('client/payment', [
            'role_layout' => 'layouts/plan_holder',
            'access' => $access,
            'plan' => $plan,
            'payments' => $payments,
            'supports_proof_upload' => $this->supportsProofUpload(),
            'membership_plans' => $membershipPlans,
            'program' => $program,
        ]);
    }

    public function submitGcashPayment()
    {
        $access = $this->resolveAccessState();
        if (($access['state'] ?? 'new') !== 'approved') {
            return redirect()->back()->with('error', 'Access denied. Payment submission requires approved membership.');
        }

        $planHolder = $access['plan_holder'];
        if (! $planHolder) {
            return redirect()->back()->with('error', 'No plan holder profile found.');
        }

        $plan = $this->latestPlan((int) $planHolder['plan_holder_id']);
        if (! $plan) {
            return redirect()->back()->with('error', 'No active plan found for payment submission.');
        }

        $rules = [
            // Panel brief, section 3: advance payment must support 1-59 months.
            'months_covered' => 'required|is_natural_no_zero|less_than_equal_to[59]',
            'amount' => 'required|decimal',
            'payment_date' => 'required|valid_date[Y-m-d]',
            'payment_method' => 'required|in_list[gcash]',
            'reference_number' => 'required|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $monthsCovered = max(1, (int) $this->request->getPost('months_covered'));
        $amount = (float) $this->request->getPost('amount');
        $monthlyFee = (float) ($plan['monthly_fee'] ?? 0);
        $expectedAmount = round($monthlyFee * $monthsCovered, 2);
        if ($expectedAmount <= 0 || abs($expectedAmount - $amount) > 0.01) {
            return redirect()->back()->withInput()->with('error', 'Amount must match your monthly fee multiplied by months covered.');
        }

        if ($amount > (float) ($plan['remaining_balance'] ?? 0)) {
            return redirect()->back()->withInput()->with('error', 'Payment exceeds your remaining balance.');
        }

        $reference = trim((string) $this->request->getPost('reference_number'));
        $duplicate = (new PaymentModel())
            ->where('reference_number', $reference)
            ->first();

        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', 'Duplicate reference number detected. Please verify your reference number.');
        }

        $payload = [
            'plan_id' => (int) $plan['plan_id'],
            'amount' => $amount,
            'months_covered' => $monthsCovered,
            'payment_date' => (string) $this->request->getPost('payment_date'),
            'payment_method' => 'gcash',
            'reference_number' => $reference,
            'received_by' => null,
            'branch_id' => (int) ($planHolder['branch_id'] ?? 0),
            'status' => 'pending',
            'remarks' => 'Submitted by client, awaiting branch verification',
        ];

        $proof = $this->request->getFile('proof_image');
        if ($this->supportsProofUpload() && $monthsCovered > 1 && (! $proof || ! $proof->isValid())) {
            return redirect()->back()->withInput()->with('error', 'Proof image is required for advance payments.');
        }
        if ($this->supportsProofUpload() && $proof && $proof->isValid() && ! $proof->hasMoved()) {
            $uploadDir = WRITEPATH . 'uploads/payment-proofs';
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $proofName = $proof->getRandomName();
            $proof->move($uploadDir, $proofName);
            $payload['proof_image'] = $proofName;
        }

        (new PaymentModel())->insert($payload);

        return redirect()->to('/client/payment')->with('success', 'GCash payment submitted. Waiting for branch admin verification.');
    }

    public function services(): string
    {
        $access = $this->resolveAccessState();

        $activeTab = (string) $this->request->getGet('tab');
        if (! in_array($activeTab, ['services', 'packages'], true)) {
            $activeTab = 'services';
        }

        $services = db_connect()->table('service_list')
            ->select('service_list_id, service_name, description, base_price')
            ->where('is_available', 1)
            ->orderBy('service_name', 'ASC')
            ->get()
            ->getResultArray();

        $packages = db_connect()->table('packages')
            ->select('package_id, package_name, description, base_price')
            ->orderBy('package_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('client/services', [
            'role_layout' => 'layouts/plan_holder',
            'access' => $access,
            'active_tab' => $activeTab,
            'services' => $services,
            'packages' => $packages,
        ]);
    }

    public function serviceDetails(int $serviceListId): string
    {
        $access = $this->resolveAccessState();

        $service = db_connect()->table('service_list')
            ->select('service_list_id, service_name, description, base_price')
            ->where('service_list_id', $serviceListId)
            ->where('is_available', 1)
            ->get()
            ->getRowArray();

        if (! $service) {
            return redirect()->to('/client/service?tab=services')->with('error', 'Service not found.');
        }

        return view('client/service_details', [
            'role_layout' => 'layouts/plan_holder',
            'access' => $access,
            'service' => $service,
        ]);
    }

    public function packageDetails(int $packageId): string
    {
        $access = $this->resolveAccessState();

        $package = db_connect()->table('packages')
            ->select('package_id, package_name, description, base_price, is_customizable')
            ->where('package_id', $packageId)
            ->get()
            ->getRowArray();

        if (! $package) {
            return redirect()->to('/client/service?tab=packages')->with('error', 'Package not found.');
        }

        $packageServices = db_connect()->table('package_services ps')
            ->select('sl.service_list_id, sl.service_name, sl.description, sl.base_price')
            ->join('service_list sl', 'sl.service_list_id = ps.service_list_id', 'inner')
            ->where('ps.package_id', $packageId)
            ->orderBy('sl.service_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('client/package_details', [
            'role_layout' => 'layouts/plan_holder',
            'access' => $access,
            'package' => $package,
            'package_services' => $packageServices,
        ]);
    }

    public function applyServiceForm(int $serviceListId): string
    {
        $access = $this->resolveAccessState();

        $service = db_connect()->table('service_list')
            ->select('service_list_id, service_name, description, base_price')
            ->where('service_list_id', $serviceListId)
            ->where('is_available', 1)
            ->get()
            ->getRowArray();

        if (! $service) {
            return redirect()->to('/client/service?tab=services')->with('error', 'Service not found.');
        }

        return view('client/service_apply', [
            'role_layout' => 'layouts/plan_holder',
            'access' => $access,
            'service' => $service,
            'can_apply' => (($access['state'] ?? 'new') === 'approved'),
        ]);
    }

    public function applyPackageForm(int $packageId): string
    {
        $access = $this->resolveAccessState();

        $package = db_connect()->table('packages')
            ->select('package_id, package_name, description, base_price, is_customizable')
            ->where('package_id', $packageId)
            ->get()
            ->getRowArray();

        if (! $package) {
            return redirect()->to('/client/service?tab=packages')->with('error', 'Package not found.');
        }

        return view('client/package_apply', [
            'role_layout' => 'layouts/plan_holder',
            'access' => $access,
            'package' => $package,
            'can_apply' => (($access['state'] ?? 'new') === 'approved'),
        ]);
    }

    public function submitServiceApplication(int $serviceListId)
    {
        $access = $this->resolveAccessState();
        $user = $access['user'];

        if (($access['state'] ?? 'new') === 'new') {
            return redirect()->to('/plan-info')->with('error', 'You must register as a Plan Holder to apply.');
        }

        if (($access['state'] ?? 'new') !== 'approved') {
            return redirect()->back()->with('error', 'Access denied. Approval required before requesting services.');
        }

        $planHolder = $access['plan_holder'];
        if (! $planHolder) {
            return redirect()->to('/plan-info')->with('error', 'No plan holder profile found. Please complete registration first.');
        }

        // Check service eligibility based on membership state
        $membershipService = new MembershipService();
        $serviceAccess = $membershipService->serviceAccess((int) $planHolder['plan_holder_id']);
        if (! $serviceAccess['allowed']) {
            return redirect()->back()->with('error', $serviceAccess['reason'] ?: 'Your plan does not allow service requests at this time.');
        }

        $service = db_connect()->table('service_list')
            ->select('service_list_id, service_name')
            ->where('service_list_id', $serviceListId)
            ->where('is_available', 1)
            ->get()
            ->getRowArray();

        if (! $service) {
            return redirect()->back()->with('error', 'Selected service is unavailable.');
        }

        db_connect()->table('service_applications')->insert([
            'plan_holder_id' => (int) $planHolder['plan_holder_id'],
            'service_list_id' => (int) $serviceListId,
            'status' => 'pending',
        ]);

        (new NotificationService())->notify((int) $user['user_id'], 'Your application for ' . (string) $service['service_name'] . ' has been submitted.', 'registration_pending');

        return redirect()->to('/client/service?tab=services')->with('success', 'Service application submitted successfully.');
    }

    public function submitPackageApplication(int $packageId)
    {
        $access = $this->resolveAccessState();
        $user = $access['user'];

        if (($access['state'] ?? 'new') === 'new') {
            return redirect()->to('/plan-info')->with('error', 'You must register as a Plan Holder to apply.');
        }

        if (($access['state'] ?? 'new') !== 'approved') {
            return redirect()->back()->with('error', 'Access denied. Approval required before requesting services.');
        }

        $planHolder = $access['plan_holder'];
        if (! $planHolder) {
            return redirect()->to('/plan-info')->with('error', 'No plan holder profile found. Please complete registration first.');
        }

        // Check service eligibility based on membership state
        $membershipService = new MembershipService();
        $serviceAccess = $membershipService->serviceAccess((int) $planHolder['plan_holder_id']);
        if (! $serviceAccess['allowed']) {
            return redirect()->back()->with('error', $serviceAccess['reason'] ?: 'Your plan does not allow service requests at this time.');
        }

        $package = db_connect()->table('packages')
            ->select('package_id, package_name')
            ->where('package_id', $packageId)
            ->get()
            ->getRowArray();

        if (! $package) {
            return redirect()->back()->with('error', 'Selected package is unavailable.');
        }

        db_connect()->table('service_applications')->insert([
            'plan_holder_id' => (int) $planHolder['plan_holder_id'],
            'package_id' => (int) $packageId,
            'status' => 'pending',
        ]);

        (new NotificationService())->notify((int) $user['user_id'], 'Your application for ' . (string) $package['package_name'] . ' has been submitted.', 'registration_pending');

        return redirect()->to('/client/service?tab=packages')->with('success', 'Package application submitted successfully.');
    }

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

    public function planInfo(): string
    {
        return view('client/plan_info', [
            'role_layout' => 'layouts/plan_holder',
            'program' => MembershipService::getProgramInfo(),
        ]);
    }

    public function planRegistration(): string
    {
        $access = $this->resolveAccessState();
        $user = $access['user'];
        $branches = (new BranchModel())
            ->where('status', 'active')
            ->orderBy('branch_name', 'ASC')
            ->findAll();

        $beneficiaries = [];
        if (! empty($access['plan_holder']['plan_holder_id'])) {
            $beneficiaries = db_connect()->table('beneficiaries')
                ->where('plan_holder_id', (int) $access['plan_holder']['plan_holder_id'])
                ->orderBy('beneficiary_id', 'ASC')
                ->get()
                ->getResultArray();
        }

        return view('client/plan_registration', [
            'role_layout' => 'layouts/plan_holder',
            'user' => $user,
            'branches' => $branches,
            'plan_holder' => $access['plan_holder'],
            'access' => $access,
            'beneficiaries' => $beneficiaries,
        ]);
    }

    public function submitPlanRegistration()
    {
        $access = $this->resolveAccessState();
        $user = $access['user'];

        $rules = [
            'id_control_no' => 'permit_empty|max_length[50]',
            'coordinator' => 'permit_empty|max_length[100]',
            'application_date' => 'permit_empty|valid_date[Y-m-d]',
            'last_name' => 'required|max_length[50]',
            'first_name' => 'required|max_length[50]',
            'middle_name' => 'permit_empty|max_length[50]',
            'address_no' => 'permit_empty|max_length[20]',
            'address_street' => 'permit_empty|max_length[100]',
            'address_barangay' => 'required|max_length[100]',
            'address_city' => 'required|max_length[100]',
            'date_of_birth' => 'permit_empty|valid_date[Y-m-d]',
            'place_of_birth' => 'permit_empty|max_length[100]',
            'age' => 'permit_empty|is_natural',
            'gender' => 'permit_empty|in_list[Male,Female,Other]',
            'civil_status' => 'permit_empty|in_list[Single,Married,Divorced,Widowed,single,married,divorced,widowed]',
            'citizenship' => 'permit_empty|max_length[50]',
            'height' => 'permit_empty|decimal',
            'weight' => 'permit_empty|decimal',
            'spouse_name' => 'permit_empty|max_length[100]',
            'spouse_birthdate' => 'permit_empty|valid_date[Y-m-d]',
            'spouse_occupation' => 'permit_empty|max_length[100]',
            'contact_number' => 'permit_empty|max_length[30]',
            'email' => 'required|valid_email|max_length[100]|is_unique[users.email,user_id,' . (int) ($user['user_id'] ?? 0) . ']',
            'senior_citizen_id' => 'permit_empty|max_length[50]',
            'organization_affiliation' => 'permit_empty|max_length[100]',
            'emergency_contact_name' => 'permit_empty|max_length[100]',
            'emergency_contact_number' => 'permit_empty|max_length[30]',
            'emergency_contact_address' => 'permit_empty|max_length[150]',
            'branch_id' => 'required|is_natural_no_zero',
            'certify' => 'required|in_list[1]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $planHolderModel = new PlanHolderModel();
            $existing = $access['plan_holder'];
            $branchId = (int) $this->request->getPost('branch_id');
            $isNewRegistration = ($access['state'] ?? 'new') === 'new';

            (new UserModel())->update((int) $user['user_id'], [
                'first_name' => trim((string) $this->request->getPost('first_name')),
                'middle_name' => trim((string) $this->request->getPost('middle_name')),
                'last_name' => trim((string) $this->request->getPost('last_name')),
                'email' => trim((string) $this->request->getPost('email')),
                'contact_number' => trim((string) $this->request->getPost('contact_number')),
                'branch_id' => $branchId,
            ]);

            if ($existing) {
                $planHolderId = (int) $existing['plan_holder_id'];
                $planHolderModel->update($planHolderId, [
                    'branch_id' => $branchId,
                    'id_control_no' => trim((string) $this->request->getPost('id_control_no')),
                    'coordinator' => trim((string) $this->request->getPost('coordinator')),
                    'application_date' => $this->nullablePost('application_date'),
                    'address_no' => trim((string) $this->request->getPost('address_no')),
                    'address_street' => trim((string) $this->request->getPost('address_street')),
                    'address_barangay' => trim((string) $this->request->getPost('address_barangay')),
                    'address_city' => trim((string) $this->request->getPost('address_city')),
                    'date_of_birth' => $this->nullablePost('date_of_birth'),
                    'place_of_birth' => trim((string) $this->request->getPost('place_of_birth')),
                    'age' => $this->nullableIntPost('age'),
                    'gender' => trim((string) $this->request->getPost('gender')),
                    'civil_status' => trim((string) $this->request->getPost('civil_status')),
                    'citizenship' => trim((string) $this->request->getPost('citizenship')),
                    'height' => $this->nullableDecimalPost('height'),
                    'weight' => $this->nullableDecimalPost('weight'),
                    'spouse_name' => trim((string) $this->request->getPost('spouse_name')),
                    'spouse_birthdate' => $this->nullablePost('spouse_birthdate'),
                    'spouse_occupation' => trim((string) $this->request->getPost('spouse_occupation')),
                    'senior_citizen_id' => trim((string) $this->request->getPost('senior_citizen_id')),
                    'organization_affiliation' => trim((string) $this->request->getPost('organization_affiliation')),
                    'emergency_contact_name' => trim((string) $this->request->getPost('emergency_contact_name')),
                    'emergency_contact_number' => trim((string) $this->request->getPost('emergency_contact_number')),
                    'emergency_contact_address' => trim((string) $this->request->getPost('emergency_contact_address')),
                ]);
            } else {
                $planHolderId = (int) $planHolderModel->insert([
                    'user_id' => (int) $user['user_id'],
                    'branch_id' => $branchId,
                    'id_control_no' => trim((string) $this->request->getPost('id_control_no')),
                    'coordinator' => trim((string) $this->request->getPost('coordinator')),
                    'application_date' => $this->nullablePost('application_date'),
                    'address_no' => trim((string) $this->request->getPost('address_no')),
                    'address_street' => trim((string) $this->request->getPost('address_street')),
                    'address_barangay' => trim((string) $this->request->getPost('address_barangay')),
                    'address_city' => trim((string) $this->request->getPost('address_city')),
                    'date_of_birth' => $this->nullablePost('date_of_birth'),
                    'place_of_birth' => trim((string) $this->request->getPost('place_of_birth')),
                    'age' => $this->nullableIntPost('age'),
                    'gender' => trim((string) $this->request->getPost('gender')),
                    'civil_status' => trim((string) $this->request->getPost('civil_status')),
                    'citizenship' => trim((string) $this->request->getPost('citizenship')),
                    'height' => $this->nullableDecimalPost('height'),
                    'weight' => $this->nullableDecimalPost('weight'),
                    'spouse_name' => trim((string) $this->request->getPost('spouse_name')),
                    'spouse_birthdate' => $this->nullablePost('spouse_birthdate'),
                    'spouse_occupation' => trim((string) $this->request->getPost('spouse_occupation')),
                    'senior_citizen_id' => trim((string) $this->request->getPost('senior_citizen_id')),
                    'organization_affiliation' => trim((string) $this->request->getPost('organization_affiliation')),
                    'emergency_contact_name' => trim((string) $this->request->getPost('emergency_contact_name')),
                    'emergency_contact_number' => trim((string) $this->request->getPost('emergency_contact_number')),
                    'emergency_contact_address' => trim((string) $this->request->getPost('emergency_contact_address')),
                    'status' => 'inactive',
                ], true);
            }

            if ($planHolderId <= 0) {
                throw new \RuntimeException('Unable to save plan holder details.');
            }

            $beneficiariesInput = $this->request->getPost('beneficiaries');
            $beneficiariesInput = is_array($beneficiariesInput) ? $beneficiariesInput : [];
            $beneficiaries = [];

            foreach ($beneficiariesInput as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $birthday = trim((string) ($row['birthday'] ?? ''));
                $relationship = trim((string) ($row['relationship'] ?? ''));

                if ($name === '' && $birthday === '' && $relationship === '') {
                    continue;
                }

                $nameParts = $this->parseBeneficiaryName($name);

                $beneficiaries[] = [
                    'plan_holder_id' => $planHolderId,
                    'first_name' => $nameParts['first_name'],
                    'middle_name' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                    'name_extension' => $nameParts['name_extension'],
                    'date_of_birth' => $birthday !== '' ? $birthday : null,
                    'relationship' => $relationship !== '' ? $relationship : 'N/A',
                ];
            }

            if ($db->tableExists('beneficiaries')) {
                $db->table('beneficiaries')
                    ->where('plan_holder_id', $planHolderId)
                    ->delete();

                if (! empty($beneficiaries)) {
                    $db->table('beneficiaries')->insertBatch($beneficiaries);
                }
            }

            $packageData = $this->resolvePackageAndVersion();
            $plan = $this->latestPlan($planHolderId);

            if (! $plan) {
                $planId = (int) (new PlanModel())->insert([
                    'plan_holder_id' => $planHolderId,
                    'package_id' => $packageData['package_id'],
                    'monthly_fee' => MembershipService::MONTHLY_FEE,
                    'passbook_fee' => 50,
                    'start_date' => date('Y-m-d'),
                    'status' => 'inactive',
                    'months_paid' => 0,
                    'remaining_balance' => MembershipService::MONTHLY_FEE * 12,
                    'version_id' => $packageData['version_id'],
                ], true);

                if ($planId <= 0) {
                    throw new \RuntimeException('Unable to initialize your plan.');
                }
            }

            if ($isNewRegistration) {
                (new UserModel())->update((int) $user['user_id'], [
                    'branch_id' => $branchId,
                    'is_plan_holder' => 1,
                    'account_status' => 'pending',
                ]);
            } else {
                (new UserModel())->update((int) $user['user_id'], [
                    'branch_id' => $branchId,
                    'is_plan_holder' => 1,
                ]);
            }

            if ($isNewRegistration) {
                (new NotificationService())->notify((int) $user['user_id'], 'Plan registration details submitted. Proceed with your initial payment.', 'registration_pending');
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to save registration details.');
            }

            $db->transCommit();

            $nextPath = $isNewRegistration
                ? '/initial-payment'
                : '/client/membership';

            $message = $isNewRegistration
                ? 'Registration details saved. Complete your initial payment.'
                : 'Registration details updated.';

            return redirect()->to($nextPath)->with('success', $message);
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    private function parseBeneficiaryName(string $name): array
    {
        $cleaned = trim(preg_replace('/\s+/', ' ', $name));
        if ($cleaned === '') {
            return [
                'first_name' => '-',
                'middle_name' => '',
                'last_name' => '',
                'name_extension' => null,
            ];
        }

        $parts = explode(' ', $cleaned);
        $extension = null;
        $extensions = ['JR', 'SR', 'II', 'III', 'IV'];

        $last = strtoupper($parts[count($parts) - 1]);
        if (in_array($last, $extensions, true)) {
            $extension = array_pop($parts);
        }

        if (count($parts) === 1) {
            return [
                'first_name' => $parts[0],
                'middle_name' => '',
                'last_name' => '',
                'name_extension' => $extension,
            ];
        }

        $lastName = array_pop($parts);
        $firstName = implode(' ', $parts);

        return [
            'first_name' => $firstName,
            'middle_name' => '',
            'last_name' => $lastName,
            'name_extension' => $extension,
        ];
    }

    protected function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));

        return $value === '' ? null : $value;
    }

    private function nullableIntPost(string $key): ?int
    {
        $value = trim((string) $this->request->getPost($key));

        return $value === '' ? null : (int) $value;
    }

    protected function nullableDecimalPost(string $key): ?float
    {
        $value = trim((string) $this->request->getPost($key));

        return $value === '' ? null : (float) $value;
    }

    public function initialPayment(): string
    {
        $access = $this->resolveAccessState();
        $user = $access['user'];
        $planHolder = $access['plan_holder'];
        $plan = $planHolder ? $this->latestPlan((int) $planHolder['plan_holder_id']) : null;
        $latestInitialPayment = $planHolder ? $this->latestInitialPayment((int) $planHolder['plan_holder_id']) : null;

        return view('client/initial_payment', [
            'role_layout' => 'layouts/plan_holder',
            'plan_holder' => $planHolder,
            'plan' => $plan,
            'user' => $user,
            'access' => $access,
            'latest_initial_payment' => $latestInitialPayment,
        ]);
    }

    public function submitInitialPayment()
    {
        $access = $this->resolveAccessState();
        $user = $access['user'];
        $planHolder = $access['plan_holder'];

        if (! $planHolder) {
            return redirect()->to('/plan-registration')->with('error', 'Please complete plan registration first.');
        }

        $plan = $this->latestPlan((int) $planHolder['plan_holder_id']);
        if (! $plan) {
            return redirect()->to('/plan-registration')->with('error', 'Plan record not found. Please complete registration again.');
        }

        $latestInitialPayment = $this->latestInitialPayment((int) $planHolder['plan_holder_id']);
        $latestStatus = strtolower((string) ($latestInitialPayment['status'] ?? 'none'));
        if ($latestStatus === 'pending') {
            return redirect()->back()->with('error', 'Your initial payment is still pending verification.');
        }

        if ($latestStatus === 'paid' && strtolower((string) ($planHolder['status'] ?? 'inactive')) === 'inactive') {
            return redirect()->back()->with('error', 'Initial payment already verified. Please wait for branch approval.');
        }

        $rules = [
            'payment_method' => 'required|in_list[cash,gcash]',
            'reference_number' => 'permit_empty|max_length[100]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $method = trim((string) $this->request->getPost('payment_method'));
        $referenceNumber = trim((string) $this->request->getPost('reference_number'));

        if ($method === 'gcash' && $referenceNumber === '') {
            return redirect()->back()->withInput()->with('error', 'Reference number is required for GCash payment.');
        }

        $initialStatus = 'pending';
        $initialRemarks = $method === 'cash'
            ? 'Initial cash payment awaiting verification'
            : 'Initial payment awaiting verification';

        (new PaymentModel())->insert([
            'plan_id' => (int) $plan['plan_id'],
            'amount' => MembershipService::MONTHLY_FEE,
            'payment_date' => date('Y-m-d'),
            'payment_method' => $method,
            'reference_number' => $referenceNumber === '' ? null : $referenceNumber,
            'received_by' => null,
            'branch_id' => (int) ($planHolder['branch_id'] ?? 0),
            'remarks' => $initialRemarks,
            'status' => $initialStatus,
        ]);

        (new NotificationService())->notify((int) $user['user_id'], 'Initial payment submitted and pending verification.', 'registration_pending');

        $branchId = (int) ($planHolder['branch_id'] ?? 0);
        if ($branchId > 0) {
            $staffRows = db_connect()->table('users')
                ->select('user_id')
                ->whereIn('role_id', [2, 3])
                ->where('branch_id', $branchId)
                ->get()
                ->getResultArray();

            $linkMessage = 'Initial payment submitted. Review it here: ' . base_url('branch-admin/payment-tracking?tab=initial');
            foreach ($staffRows as $staffRow) {
                (new NotificationService())->notify(
                    (int) ($staffRow['user_id'] ?? 0),
                    $linkMessage,
                    'payment_pending'
                );
            }
        }

        return redirect()->to('/client/dashboard')->with('success', 'Initial payment submitted. Wait for branch verification.');
    }

    public function verifyInitialPayment(int $paymentId)
    {
        $roleId = (int) session('role_id');
        if ($roleId !== 2) {
            return redirect()->to('/unauthorized');
        }

        $paymentModel = new PaymentModel();
        $payment = $paymentModel->find($paymentId);

        if (! $payment) {
            return redirect()->back()->with('error', 'Payment record not found.');
        }

        if ((string) $payment['status'] !== 'pending') {
            return redirect()->back()->with('error', 'Only pending initial payments can be verified.');
        }

        if ((string) ($payment['payment_method'] ?? '') === 'gcash' && trim((string) ($payment['reference_number'] ?? '')) === '') {
            return redirect()->back()->with('error', 'GCash payment cannot be verified without a reference number.');
        }

        $planModel = new PlanModel();
        $plan = $planModel->find((int) $payment['plan_id']);
        if (! $plan) {
            return redirect()->back()->with('error', 'Plan record not found.');
        }

        $planHolder = (new PlanHolderModel())->find((int) $plan['plan_holder_id']);
        if (! $planHolder) {
            return redirect()->back()->with('error', 'Plan holder record not found.');
        }

        if (strtolower((string) ($planHolder['status'] ?? '')) !== 'inactive') {
            return redirect()->back()->with('error', 'Only registration payments for inactive plan holders can be verified here.');
        }

        if ((int) ($planHolder['branch_id'] ?? 0) !== (int) session('branch_id')) {
            return redirect()->back()->with('error', 'You can only verify payments within your branch.');
        }

        $paymentModel->update($paymentId, [
            'status' => 'paid',
            'received_by' => (int) session('user_id'),
            'remarks' => 'Initial GCash payment verified by branch admin',
        ]);

        (new NotificationService())->notify((int) $planHolder['user_id'], 'Your initial payment has been verified. Waiting for final approval.', 'payment_approved');

        return redirect()->back()->with('success', 'Initial payment verified. Plan holder is now ready for approval.');
    }

    private function currentUser(): array
    {
        $userId = (int) session('user_id');
        $roleId = (int) session('role_id');

        if ($userId <= 0 || $roleId !== 4) {
            throw new \RuntimeException('Unauthorized client session.');
        }

        $user = (new UserModel())->find($userId);
        if (! $user) {
            throw new \RuntimeException('Client account not found.');
        }

        return $user;
    }

    private function currentPlanHolder(): ?array
    {
        $userId = (int) session('user_id');

        return (new PlanHolderModel())
            ->where('user_id', $userId)
            ->orderBy('plan_holder_id', 'DESC')
            ->first();
    }

    private function latestPlan(int $planHolderId): ?array
    {
        return (new MembershipService())->getActivePlan($planHolderId)
            ?? (new MembershipService())->getPlans($planHolderId)[0] ?? null;
    }

    private function activePlan(int $planHolderId): ?array
    {
        return (new MembershipService())->getActivePlan($planHolderId);
    }

    private function resolvePackageAndVersion(): array
    {
        $db = db_connect();

        // Services & Packages redesign: prefer the explicit Damayan
        // entitlement flag over the assumed DEFAULT_PACKAGE_ID=1 - package
        // ids are no longer guaranteed to start at 1 once the catalog has
        // been re-seeded.
        $package = $db->table('packages')
            ->select('package_id')
            ->where('is_damayan_entitlement', 1)
            ->orderBy('package_id', 'ASC')
            ->get()
            ->getRowArray();

        if (! $package) {
            $package = $db->table('packages')
                ->select('package_id')
                ->where('package_id', MembershipService::DEFAULT_PACKAGE_ID)
                ->get()
                ->getRowArray();
        }

        if (! $package) {
            $package = $db->table('packages')
                ->select('package_id')
                ->orderBy('package_id', 'ASC')
                ->get()
                ->getRowArray();
        }

        if (! $package) {
            throw new \RuntimeException('No package is configured yet. Please ask admin to create a package first.');
        }

        $packageId = (int) $package['package_id'];
        $version = $db->table('package_versions')
            ->select('version_id')
            ->where('package_id', $packageId)
            ->where('status', 'active')
            ->orderBy('version_id', 'DESC')
            ->get()
            ->getRowArray();

        if (! $version) {
            $version = $db->table('package_versions')
                ->select('version_id')
                ->where('package_id', $packageId)
                ->orderBy('version_id', 'DESC')
                ->get()
                ->getRowArray();
        }

        if (! $version) {
            $db->table('package_versions')->insert([
                'package_id' => $packageId,
                'price' => MembershipService::MONTHLY_FEE,
                'effective_date' => date('Y-m-d'),
                'status' => 'active',
            ]);

            $versionId = (int) $db->insertID();
            if ($versionId <= 0) {
                throw new \RuntimeException('No package version is configured yet.');
            }

            return [
                'package_id' => $packageId,
                'version_id' => $versionId,
            ];
        }

        return [
            'package_id' => $packageId,
            'version_id' => (int) $version['version_id'],
        ];
    }

    private function resolveAccessState(): array
    {
        $user = $this->currentUser();
        $planHolder = $this->currentPlanHolder();
        $isPlanHolder = (int) ($user['is_plan_holder'] ?? 0) === 1;
        $holderStatus = strtolower(trim((string) ($planHolder['status'] ?? '')));
        $activePlan = $planHolder ? $this->activePlan((int) $planHolder['plan_holder_id']) : null;
        $latestPlan = $planHolder ? $this->latestPlan((int) $planHolder['plan_holder_id']) : null;
        $planStatus = strtolower(trim((string) ($activePlan['status'] ?? $latestPlan['status'] ?? '')));

        $state = 'new';
        if ($isPlanHolder && $planHolder) {
            if ($activePlan) {
                $state = 'approved';
            } elseif ($planStatus !== '' || $holderStatus === 'inactive') {
                $state = 'pending';
            }
        }

        $badgeClass = 'danger';
        $badgeLabel = 'Inactive';
        if ($state === 'pending') {
            $badgeClass = 'warning';
            $badgeLabel = 'Pending Approval';
        } elseif ($state === 'approved') {
            $badgeClass = 'success';
            $badgeLabel = 'Active';
        }

        $latestInitialPayment = null;
        if ($planHolder) {
            $latestInitialPayment = $this->latestInitialPayment((int) $planHolder['plan_holder_id']);
        }

        return [
            'user' => $user,
            'plan_holder' => $planHolder,
            'is_plan_holder' => $isPlanHolder,
            'state' => $state,
            'badge_class' => $badgeClass,
            'badge_label' => $badgeLabel,
            'account_status' => strtolower(trim((string) ($user['account_status'] ?? 'pending'))),
            'initial_payment_status' => strtolower((string) ($latestInitialPayment['status'] ?? 'none')),
        ];
    }

    private function latestInitialPayment(int $planHolderId): ?array
    {
        $row = db_connect()->table('payments pay')
            ->select('pay.payment_id, pay.plan_id, pay.amount, pay.payment_date, pay.payment_method, pay.reference_number, pay.status, pay.remarks')
            ->join('plans p', 'p.plan_id = pay.plan_id', 'inner')
            ->where('p.plan_holder_id', $planHolderId)
            ->orderBy('pay.payment_id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    private function supportsProofUpload(): bool
    {
        if ($this->paymentsHasProofImage !== null) {
            return $this->paymentsHasProofImage;
        }

        $this->paymentsHasProofImage = db_connect()->fieldExists('proof_image', 'payments');

        return $this->paymentsHasProofImage;
    }

    private function enforceSingleActivePlan(int $planHolderId, int $activePlanId): void
    {
        $planModel = new PlanModel();

        $planModel
            ->where('plan_holder_id', $planHolderId)
            ->set(['status' => 'inactive'])
            ->update();

        $planModel->update($activePlanId, ['status' => 'active']);
    }
}
