<?php

namespace App\Controllers;

use App\Models\PlanHolderModel;
use App\Models\UserModel;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Services\ClientRegistrationService;
use App\Services\GovernmentIdVerificationService;
use App\Services\SecurityEnhancementService;
use CodeIgniter\HTTP\ResponseInterface;

class PlanHolders extends BaseController
{
    /**
     * Prompt 7, Step B: this page's own registration FORM is gone - it now
     * only shows the "Plan Holder Verification Queue" (approvals), with a
     * link out to the signed-in role's own new register page
     * (branch-admin/client/register or admin/client/register, both built
     * on the same shared wizard client self-service uses). branches/
     * existingUsers/id_types are no longer needed here since there's no
     * form left on this page to feed.
     */
    public function register()
    {
        // Only Admin (role 1) and BranchAdmin (role 2) can review approvals here.
        $roleId = (int) session('role_id');
        if (!in_array($roleId, [1, 2], true)) {
            return redirect()->to('/unauthorized')->with('error', 'You do not have permission to view this page.');
        }

        $db = db_connect();

        $approvalRegistrations = [];
        $hasPendingTable = $this->pendingTableExists();
        if ($hasPendingTable) {
            $approvalRegistrations = $db->table('pending_plan_holder_registrations pr')
                ->select('pr.*, u.first_name, u.last_name, u.email, u.contact_number, b.branch_name, reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name')
                ->join('users u', 'u.user_id = pr.user_id', 'left')
                ->join('branches b', 'b.branch_id = pr.branch_id', 'left')
                ->join('users reviewer', 'reviewer.user_id = pr.reviewed_by', 'left')
                ->orderBy('pr.pending_registration_id', 'DESC')
                ->get()
                ->getResultArray();
        }

        $registerUrl = $roleId === 1 ? base_url('admin/client/register') : base_url('branch-admin/client/register');

        return view('plan_holders/register', [
            'has_pending_table' => $hasPendingTable,
            'approval_registrations' => $approvalRegistrations,
            'role_layout' => $this->resolveLayoutView(),
            'register_url' => $registerUrl,
            'page_title' => 'Plan holder verification queue',
            'page_sub' => 'Review and act on Government ID verification requests.',
            'page_actions' => '<a href="' . esc($registerUrl, 'attr') . '" class="btn btn-primary"><i class="ti ti-user-plus" aria-hidden="true"></i> Register a plan holder</a>',
        ]);
    }

    public function store()
    {
        // Only Admin (role 1) and BranchAdmin (role 2) can register plan holders
        $roleId = (int) session('role_id');
        if (!in_array($roleId, [1, 2], true)) {
            error_log("STORE() ERROR: Unauthorized role {$roleId}");
            return redirect()->to('/unauthorized')->with('error', 'You do not have permission to register plan holders.');
        }

        $mode = (string) $this->request->getPost('registration_mode');
        error_log("STORE() DEBUG: Received POST data = " . json_encode($this->request->getPost()));
        error_log("STORE() DEBUG: mode={$mode}");

        // Validate registration mode
        if (!in_array($mode, ['existing', 'new'], true)) {
            error_log("STORE() ERROR: Invalid mode '{$mode}', expected 'existing' or 'new'");
            return redirect()->back()->withInput()->with('error', 'Invalid registration mode selected.');
        }

        // Government ID Verification, applied here the same way it's
        // applied to every other CareSync registration flow - the form's
        // step-gating is client-side UX only, never trusted alone. The
        // subject of the ID (an existing OR brand-new plan holder) isn't
        // necessarily the logged-in user (that's the staff member doing
        // the registering), so this uses the same pending-token pattern
        // as Users::create() rather than session('user_id').
        $pendingVerificationToken = trim((string) $this->request->getPost('government_id_pending_token'));
        if ($pendingVerificationToken === '') {
            return redirect()->back()->withInput()->with('error', 'Please complete the Government ID Verification step before submitting.');
        }

        $idVerificationService = new GovernmentIdVerificationService();
        $pendingVerification = $idVerificationService->peekPending($pendingVerificationToken);
        if ($pendingVerification === null) {
            return redirect()->back()->withInput()->with('error', 'Your Government ID verification could not be found. Please verify the ID again.');
        }

        $branchId = (int) $this->request->getPost('branch_id');
        $createdBy = (int) session('user_id');
        error_log("STORE() DEBUG: branchId={$branchId}, createdBy={$createdBy}");

        if ($branchId <= 0) {
            error_log("STORE() ERROR: Invalid or missing branchId");
            return redirect()->back()->withInput()->with('error', 'Branch selection is required.');
        }

        // Add security checks
        $securityService = new SecurityEnhancementService();
        $userId = (int) session('user_id');

        // Check rate limiting
        $rateCheck = $securityService->checkRegistrationAttempts("user_{$userId}");
        if (!$rateCheck['allowed']) {
            error_log("STORE() ERROR: Rate limit exceeded");
            $securityService->logSecurityEvent('RATE_LIMIT_EXCEEDED', $userId, "Registration attempts exceeded");
            return redirect()->back()->withInput()->with('error', $rateCheck['message']);
        }

        $registrationService = new ClientRegistrationService();

        // Age is never trusted from the client - always derived server-side
        // from date_of_birth (Task 3, cs_age_from_dob()). Previously this
        // fell back to whatever was typed into the Age field when no
        // birthdate was given; now an absent/invalid birthdate just means
        // a null age, same as everywhere else.
        $dateOfBirth = $this->nullablePost('date_of_birth');
        if ($dateOfBirth !== null && $dateOfBirth > date('Y-m-d')) {
            error_log("STORE() ERROR: Date of birth is in the future");
            return redirect()->back()->withInput()->with('error', 'Date of birth cannot be in the future.');
        }

        // Collect common plan holder data
        $planHolderData = [
            'unique_identifier' => trim((string) $this->request->getPost('unique_identifier')),
            'address_no' => trim((string) $this->request->getPost('address_no')),
            'address_street' => trim((string) $this->request->getPost('address_street')),
            'address_barangay' => trim((string) $this->request->getPost('address_barangay')),
            'address_city' => trim((string) $this->request->getPost('address_city')),
            'date_of_birth' => $dateOfBirth,
            'place_of_birth' => trim((string) $this->request->getPost('place_of_birth')),
            'age' => cs_age_from_dob($dateOfBirth),
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
        ];
        error_log("STORE() DEBUG: Collected planHolderData = " . json_encode($planHolderData));

        if ($mode === 'existing') {
            error_log("STORE() DEBUG: Processing existing user registration");
            $userId = (int) $this->request->getPost('user_id');
            error_log("STORE() DEBUG: user_id from POST = {$userId}");

            if ($userId <= 0) {
                $emailLookup = strtolower(trim((string) $this->request->getPost('existing_user_email')));
                error_log("STORE() DEBUG: user_id missing, attempting email lookup for {$emailLookup}");

                if ($emailLookup !== '') {
                    $user = (new UserModel())
                        ->where('email', $emailLookup)
                        ->where('role_id', 4)
                        ->first();

                    if ($user) {
                        $linked = (new PlanHolderModel())
                            ->where('user_id', (int) $user['user_id'])
                            ->first();

                        if (! $linked) {
                            $userId = (int) $user['user_id'];
                            error_log("STORE() DEBUG: Resolved user_id via email = {$userId}");
                        }
                    }
                }
            }

            if ($userId <= 0) {
                error_log("STORE() ERROR: Invalid user_id {$userId} for existing mode");
                return redirect()->back()->withInput()->with('error', 'Please select an existing user account by entering their email address and matching the account that appears.');
            }

            $existingUserRecord = (new UserModel())->find($userId);
            $submittedIdentityForId = [
                'first_name' => trim((string) ($existingUserRecord['first_name'] ?? '')),
                'last_name'  => trim((string) ($existingUserRecord['last_name'] ?? '')),
            ];
            if (! $idVerificationService->isValidForSubmission($pendingVerification, $submittedIdentityForId)) {
                $message = (string) ($pendingVerification['verification_status'] ?? '') === 'failed'
                    ? 'The Government ID does not match the details entered. Please correct the details or upload the correct ID.'
                    : 'The name was changed after verifying the Government ID. Please verify the ID again.';

                return redirect()->back()->withInput()->with('error', $message);
            }

            error_log("STORE() DEBUG: Calling registerExistingUser with userId={$userId}");
            $result = $registrationService->registerExistingUser($userId, $branchId, $planHolderData, $createdBy);
            error_log("STORE() DEBUG: registerExistingUser result = " . json_encode($result));

            if (!$result['success']) {
                error_log("STORE() ERROR: Registration failed - " . $result['error']);
                $securityService->recordRegistrationAttempt("user_{$createdBy}");
                return redirect()->back()->withInput()->with('error', $result['error']);
            }

            // Clear rate limiting on successful registration
            $securityService->clearRegistrationAttempts("user_{$createdBy}");
            $securityService->logSecurityEvent('REGISTRATION_SUCCESS', $createdBy, "Registered existing user {$userId}");
            $idVerificationService->commitPending($pendingVerificationToken, $userId);

            $successRedirect = $roleId === 2 ? '/payment-tracking?tab=initial' : '/admin/payment-monitoring';
            error_log("STORE() SUCCESS: Redirecting to {$successRedirect}");
            return redirect()->to($successRedirect)->with('success', 'Plan holder registration completed successfully. You can now record the initial payment.');
        }

        if ($mode === 'new') {
            error_log("STORE() DEBUG: Processing new user registration");
            $userData = [
                'username' => trim((string) $this->request->getPost('username')),
                'email' => trim((string) $this->request->getPost('email')),
                'password' => (string) $this->request->getPost('password'),
                'first_name' => trim((string) $this->request->getPost('first_name')),
                'last_name' => trim((string) $this->request->getPost('last_name')),
                'contact_number' => trim((string) $this->request->getPost('contact_number')),
            ];
            error_log("STORE() DEBUG: userData = " . json_encode($userData));

            // Validate password confirmation
            $password_confirm = (string) $this->request->getPost('password_confirm');
            if ($userData['password'] !== $password_confirm) {
                error_log("STORE() ERROR: Password confirmation does not match");
                $securityService->recordRegistrationAttempt("user_{$createdBy}");
                return redirect()->back()->withInput()->with('error', 'Password confirmation does not match.');
            }

            $submittedIdentityForId = [
                'first_name' => $userData['first_name'],
                'last_name'  => $userData['last_name'],
            ];
            if (! $idVerificationService->isValidForSubmission($pendingVerification, $submittedIdentityForId)) {
                $message = (string) ($pendingVerification['verification_status'] ?? '') === 'failed'
                    ? 'The Government ID does not match the details entered. Please correct the details or upload the correct ID.'
                    : 'The name was changed after verifying the Government ID. Please verify the ID again.';

                return redirect()->back()->withInput()->with('error', $message);
            }

            error_log("STORE() DEBUG: Calling registerNewUser");
            $result = $registrationService->registerNewUser($userData, $branchId, $planHolderData, $createdBy);
            error_log("STORE() DEBUG: registerNewUser result = " . json_encode($result));

            if (!$result['success']) {
                error_log("STORE() ERROR: New user registration failed - " . $result['error']);
                $securityService->recordRegistrationAttempt("user_{$createdBy}");
                return redirect()->back()->withInput()->with('error', $result['error']);
            }

            // Clear rate limiting on successful registration
            $securityService->clearRegistrationAttempts("user_{$createdBy}");
            $securityService->logSecurityEvent('REGISTRATION_SUCCESS', $result['user_id'], "New account created by {$createdBy}");
            $idVerificationService->commitPending($pendingVerificationToken, (int) $result['user_id']);

            $successRedirect = $roleId === 2 ? '/payment-tracking?tab=initial' : '/admin/payment-monitoring';
            error_log("STORE() SUCCESS: Redirecting to {$successRedirect}");
            return redirect()->to($successRedirect)->with('success', 'Account created successfully! You can now record the initial payment.');
        }

        error_log("STORE() ERROR: Reached end without handling mode");
        return redirect()->back()->with('error', 'Registration mode is invalid.');
    }

    public function approvals(): ResponseInterface|string
    {
        $roleId = (int) session('role_id');
        if (! in_array($roleId, [1, 2], true)) {
            return redirect()->to('/unauthorized');
        }

        if ($roleId === 1) {
            return redirect()->to('/plan-holders/register?tab=approvals');
        }

        if (! $this->pendingTableExists()) {
            return redirect()->back()->with('error', 'Plan holder registration queue is not available yet. Please run database migrations.');
        }

        $db = db_connect();
        $builder = $db->table('pending_plan_holder_registrations pr')
            ->select('pr.*, u.first_name, u.last_name, u.email, u.contact_number, b.branch_name, reviewer.first_name AS reviewer_first_name, reviewer.last_name AS reviewer_last_name')
            ->join('users u', 'u.user_id = pr.user_id', 'left')
            ->join('branches b', 'b.branch_id = pr.branch_id', 'left')
            ->join('users reviewer', 'reviewer.user_id = pr.reviewed_by', 'left')
            ->orderBy('pr.pending_registration_id', 'DESC');

        if ($roleId === 2) {
            $builder->where('pr.branch_id', (int) session('branch_id'));
        }

        $registrations = $builder->get()->getResultArray();

        return view('plan_holders/approvals', [
            'role_layout' => $this->resolveLayoutView(),
            'page_title' => 'Plan holder registration approvals',
            'page_sub' => 'Review pending account registration requests before granting plan holder access.',
            'registrations' => $registrations,
        ]);
    }

    public function approve(int $pendingRegistrationId)
    {
        return $this->handleApprovalDecision($pendingRegistrationId, 'approved');
    }

    public function reject(int $pendingRegistrationId)
    {
        return $this->handleApprovalDecision($pendingRegistrationId, 'rejected');
    }

    private function resolveLayoutView(): string
    {
        $role = (int) session()->get('role_id');

        if ($role === 1) {
            return 'layouts/admin';
        }

        if ($role === 2) {
            return 'layouts/branch_admin';
        }

        if ($role === 3) {
            return 'layouts/staff';
        }

        return 'layouts/plan_holder';
    }

    private function hasExistingPlanHolder(int $userId): bool
    {
        return (new PlanHolderModel())->where('user_id', $userId)->first() !== null;
    }

    private function hasExistingPlanForUser(int $userId): bool
    {
        return db_connect()->table('plans p')
            ->join('plan_holders ph', 'ph.plan_holder_id = p.plan_holder_id', 'inner')
            ->where('ph.user_id', $userId)
            ->countAllResults() > 0;
    }

    private function notifyReviewersForPendingRegistration(int $branchId, int $requesterUserId): void
    {
        if ($branchId <= 0 || $requesterUserId <= 0) {
            return;
        }

        $db = db_connect();

        $requester = $db->table('users')
            ->select('first_name, last_name')
            ->where('user_id', $requesterUserId)
            ->get()
            ->getRowArray();

        $requesterName = trim((string) ($requester['first_name'] ?? '') . ' ' . (string) ($requester['last_name'] ?? ''));
        if ($requesterName === '') {
            $requesterName = 'A client';
        }

        $reviewers = $db->table('users')
            ->select('user_id')
            ->groupStart()
                ->where('role_id', 1)
                ->orGroupStart()
                    ->where('role_id', 2)
                    ->where('branch_id', $branchId)
                ->groupEnd()
            ->groupEnd()
            ->where('status', 'active')
            ->get()
            ->getResultArray();

        foreach ($reviewers as $reviewer) {
            $reviewerId = (int) ($reviewer['user_id'] ?? 0);
            if ($reviewerId <= 0) {
                continue;
            }

            (new NotificationService())->notify($reviewerId, $requesterName . ' submitted a plan holder registration request for approval.', 'registration_pending');
        }
    }

    private function handleApprovalDecision(int $pendingRegistrationId, string $decision)
    {
        $roleId = (int) session('role_id');
        if (! in_array($roleId, [1, 2], true)) {
            return redirect()->to('/unauthorized');
        }

        if (! $this->pendingTableExists()) {
            return redirect()->back()->with('error', 'Plan holder registration queue is not available yet. Please run database migrations.');
        }

        $db = db_connect();
        $query = $db->table('pending_plan_holder_registrations')
            ->where('pending_registration_id', $pendingRegistrationId);

        if ($roleId === 2) {
            $query->where('branch_id', (int) session('branch_id'));
        }

        $pending = $query->get()->getRowArray();
        if (! $pending) {
            return redirect()->back()->with('error', 'Registration request was not found.');
        }

        if (($pending['status'] ?? 'pending') !== 'pending') {
            return redirect()->back()->with('error', 'This registration request has already been reviewed.');
        }

        $db->transBegin();

        try {
            if ($decision === 'approved') {
                $userId = (int) ($pending['user_id'] ?? 0);

                if (! $this->hasExistingPlanHolder($userId)) {
                    $saved = (new PlanHolderModel())->insert([
                        'user_id' => $userId,
                        'branch_id' => (int) ($pending['branch_id'] ?? 0),
                        'unique_identifier' => $this->generateUniqueIdentifier($userId),
                        'address_no' => (string) ($pending['address_no'] ?? ''),
                        'address_street' => (string) ($pending['address_street'] ?? ''),
                        'address_barangay' => (string) ($pending['address_barangay'] ?? ''),
                        'address_city' => (string) ($pending['address_city'] ?? ''),
                        'date_of_birth' => $pending['date_of_birth'] ?? null,
                        'place_of_birth' => (string) ($pending['place_of_birth'] ?? ''),
                        'age' => $pending['age'] === null ? null : (int) $pending['age'],
                        'gender' => (string) ($pending['gender'] ?? ''),
                        'civil_status' => (string) ($pending['civil_status'] ?? ''),
                        'citizenship' => (string) ($pending['citizenship'] ?? ''),
                        'height' => $pending['height'] ?? null,
                        'weight' => $pending['weight'] ?? null,
                        'spouse_name' => (string) ($pending['spouse_name'] ?? ''),
                        'spouse_birthdate' => $pending['spouse_birthdate'] ?? null,
                        'spouse_occupation' => (string) ($pending['spouse_occupation'] ?? ''),
                        'senior_citizen_id' => (string) ($pending['senior_citizen_id'] ?? ''),
                        'organization_affiliation' => (string) ($pending['organization_affiliation'] ?? ''),
                        'status' => 'active',
                        'is_linked_account' => 1,
                    ]);

                    if (! $saved) {
                        throw new \RuntimeException('Failed to create plan holder record during approval.');
                    }
                }

                (new UserModel())->update($userId, [
                    'is_plan_holder' => 1,
                    'branch_id' => (int) ($pending['branch_id'] ?? 0),
                    'account_status' => 'verified',
                ]);

                (new NotificationService())->notify($userId, 'Your plan holder registration was approved. You can now apply for services and manage payments.', 'registration_pending');
                (new ActivityLogService())->log((int) session('user_id'), 'approved', 'plan_holder', $pendingRegistrationId, 'Approved plan holder registration', ['status' => 'pending'], ['status' => 'approved']);
            } else {
                $rejectionNotes = trim((string) $this->request->getPost('rejection_notes'));
                (new NotificationService())->notify((int) ($pending['user_id'] ?? 0), 'Your plan holder registration was rejected. ' . ($rejectionNotes !== '' ? 'Reason: ' . $rejectionNotes : 'Please update your details and submit again.'), 'registration_pending');
                (new ActivityLogService())->log((int) session('user_id'), 'rejected', 'plan_holder', $pendingRegistrationId, 'Rejected plan holder registration', ['status' => 'pending'], ['status' => 'rejected']);
            }

            $updatePayload = [
                'status' => $decision,
                'reviewed_by' => (int) session('user_id'),
                'reviewed_at' => date('Y-m-d H:i:s'),
            ];

            if ($decision === 'rejected') {
                $updatePayload['rejection_notes'] = trim((string) $this->request->getPost('rejection_notes'));
            }

            $db->table('pending_plan_holder_registrations')
                ->where('pending_registration_id', $pendingRegistrationId)
                ->update($updatePayload);

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction failed.');
            }

            $db->transCommit();

            return redirect()->back()->with('success', 'Registration request ' . $decision . ' successfully.');
        } catch (\Throwable $e) {
            $db->transRollback();

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function pendingTableExists(): bool
    {
        return db_connect()->tableExists('pending_plan_holder_registrations');
    }

    private function generateUniqueIdentifier(int $userId): string
    {
        $planHolderModel = new PlanHolderModel();
        $seed = 'PH-' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT) . '-' . date('Ymd');
        $candidate = $seed;
        $counter = 1;

        while ($planHolderModel->where('unique_identifier', $candidate)->first() !== null) {
            $candidate = $seed . '-' . str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $candidate;
    }
}
