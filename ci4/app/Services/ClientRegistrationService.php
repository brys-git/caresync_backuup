<?php

namespace App\Services;

use App\Models\UserModel;
use App\Models\PlanHolderModel;
use App\Models\PlanModel;

/**
 * ClientRegistrationService
 * 
 * PHASE: CLIENT REGISTRATION LOGIC IMPROVEMENTS & CORRECTIONS
 * 
 * Centralizes all client registration logic including:
 * - Standardized validation for both registration modes
 * - Transaction-safe plan holder & plan creation
 * - Automatic plan generation with Damayan Burial Program defaults
 * - Proper access state determination
 * - Comprehensive error handling & logging
 * 
 * Business Rules:
 * - One active membership per plan holder
 * - Only Damayan Burial Program (PHP 240/month)
 * - Automatic unique identifier generation
 * - Beneficiary validation
 * - Age validation from birthdate
 */
class ClientRegistrationService
{
    private UserModel $userModel;
    private PlanHolderModel $planHolderModel;
    private PlanModel $planModel;
    private NotificationService $notificationService;
    private ActivityLogService $activityLogService;
    private MembershipService $membershipService;
    private ErrorHandlingService $errorService;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->planHolderModel = new PlanHolderModel();
        $this->planModel = new PlanModel();
        $this->notificationService = new NotificationService();
        $this->activityLogService = new ActivityLogService();
        $this->membershipService = new MembershipService();
        $this->errorService = new ErrorHandlingService();
    }

    /**
     * REQUIREMENT #6: Determine user access state
     * 
     * States:
     * - 'new': is_plan_holder = 0 (account only)
     * - 'pending': is_plan_holder = 1, plan.status = 'inactive' (awaiting payment)
     * - 'approved': is_plan_holder = 1, plan.status = 'active' (active member)
     * 
     * @param int $userId
     * @return array ['state' => string, 'plan_holder' => array|null, 'plan' => array|null]
     */
    public function determineAccessState(int $userId): array
    {
        if ($userId <= 0) {
            return ['state' => 'new', 'plan_holder' => null, 'plan' => null];
        }

        $planHolder = $this->planHolderModel
            ->where('user_id', $userId)
            ->first();

        if (!$planHolder) {
            return ['state' => 'new', 'plan_holder' => null, 'plan' => null];
        }

        $planHolderId = (int) ($planHolder['plan_holder_id'] ?? 0);
        $plan = $this->membershipService->getActivePlan($planHolderId);

        if (!$plan) {
            return ['state' => 'pending', 'plan_holder' => $planHolder, 'plan' => null];
        }

        return ['state' => 'approved', 'plan_holder' => $planHolder, 'plan' => $plan];
    }

    /**
     * REQUIREMENT #2: Validate email format and uniqueness
     * 
     * @param string $email
     * @param int $excludeUserId (exclude this user from uniqueness check)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateEmail(string $email, int $excludeUserId = 0): array
    {
        $email = trim($email);

        if (empty($email)) {
            return ['valid' => false, 'error' => 'Email is required'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Email format is invalid'];
        }

        if (strlen($email) > 100) {
            return ['valid' => false, 'error' => 'Email is too long (max 100 characters)'];
        }

        $query = $this->userModel->where('email', $email);
        if ($excludeUserId > 0) {
            $query->where('user_id !=', $excludeUserId);
        }

        $existing = $query->first();
        if ($existing) {
            return ['valid' => false, 'error' => 'This email address is already registered in the system'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * REQUIREMENT #2: Validate username format and uniqueness
     * 
     * Rules:
     * - Min 4, max 50 characters
     * - Only letters, numbers, and underscore
     * - Must be unique
     * 
     * @param string $username
     * @param int $excludeUserId
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateUsername(string $username, int $excludeUserId = 0): array
    {
        $username = trim($username);

        if (empty($username)) {
            return ['valid' => false, 'error' => 'Username is required'];
        }

        if (strlen($username) < 4) {
            return ['valid' => false, 'error' => 'Username must be at least 4 characters'];
        }

        if (strlen($username) > 50) {
            return ['valid' => false, 'error' => 'Username must not exceed 50 characters'];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return ['valid' => false, 'error' => 'Username can only contain letters, numbers, and underscores'];
        }

        $query = $this->userModel->where('username', $username);
        if ($excludeUserId > 0) {
            $query->where('user_id !=', $excludeUserId);
        }

        $existing = $query->first();
        if ($existing) {
            return ['valid' => false, 'error' => 'This username is already taken'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * REQUIREMENT #2: Validate password strength
     * 
     * Rules:
     * - Minimum 8 characters
     * - Should ideally contain mix of: uppercase, lowercase, numbers, symbols
     * 
     * @param string $password
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validatePassword(string $password): array
    {
        if (empty($password)) {
            return ['valid' => false, 'error' => 'Password is required'];
        }

        if (strlen($password) < 8) {
            return ['valid' => false, 'error' => 'Password must be at least 8 characters'];
        }

        // Optional: Require complexity
        $hasUppercase = preg_match('/[A-Z]/', $password);
        $hasLowercase = preg_match('/[a-z]/', $password);
        $hasNumbers = preg_match('/\d/', $password);
        $hasSpecial = preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password);

        $complexityScore = ($hasUppercase ? 1 : 0) + ($hasLowercase ? 1 : 0) + ($hasNumbers ? 1 : 0) + ($hasSpecial ? 1 : 0);

        // At least 3 out of 4 complexity requirements
        if ($complexityScore < 3) {
            return ['valid' => false, 'error' => 'Password should contain uppercase, lowercase, numbers, and symbols'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * REQUIREMENT #2: Validate and auto-calculate age from birthdate
     * 
     * @param string $birthdate (YYYY-MM-DD format)
     * @return array ['valid' => bool, 'age' => int|null, 'error' => string|null]
     */
    public function validateAndCalculateAge(string $birthdate): array
    {
        if (empty($birthdate)) {
            return ['valid' => true, 'age' => null, 'error' => null];
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthdate)) {
            return ['valid' => false, 'age' => null, 'error' => 'Birthdate format must be YYYY-MM-DD'];
        }

        try {
            $dob = new \DateTime($birthdate);
            $today = new \DateTime();

            // Check date is not in future
            if ($dob > $today) {
                return ['valid' => false, 'age' => null, 'error' => 'Birthdate cannot be in the future'];
            }

            // Check reasonable age range (0-150 years old)
            $interval = $dob->diff($today);
            $age = (int) $interval->y;

            if ($age < 0 || $age > 150) {
                return ['valid' => false, 'age' => null, 'error' => 'Age must be between 0 and 150 years'];
            }

            return ['valid' => true, 'age' => $age, 'error' => null];
        } catch (\Throwable $e) {
            return ['valid' => false, 'age' => null, 'error' => 'Invalid birthdate: ' . $e->getMessage()];
        }
    }

    /**
     * REQUIREMENT #2: Validate beneficiary data
     * 
     * Rules:
     * - Requires first_name, last_name, relationship
     * - Relationship must be valid
     * - Birthdate optional but if provided must be valid
     * 
     * @param array $beneficiary
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validateBeneficiary(array $beneficiary): array
    {
        $firstName = trim((string) ($beneficiary['first_name'] ?? ''));
        $lastName = trim((string) ($beneficiary['last_name'] ?? ''));
        $relationship = trim((string) ($beneficiary['relationship'] ?? ''));
        $birthdate = trim((string) ($beneficiary['birthdate'] ?? ''));

        if (empty($firstName)) {
            return ['valid' => false, 'error' => 'Beneficiary first name is required'];
        }

        if (empty($lastName)) {
            return ['valid' => false, 'error' => 'Beneficiary last name is required'];
        }

        if (empty($relationship)) {
            return ['valid' => false, 'error' => 'Beneficiary relationship is required'];
        }

        // Fix Prompts Task 6: relationship is now a dropdown of real labels
        // (config('Beneficiary')), not a fixed spouse/child/parent/sibling/
        // other set - 'Other' still allows free text, validated by the
        // caller before it ever reaches here (see
        // ClientRegistrationController's beneficiary loop).
        $allowedRelationships = config(\Config\Beneficiary::class)->relationships;
        if (!array_key_exists($relationship, $allowedRelationships) && strtolower($relationship) !== 'other') {
            return ['valid' => false, 'error' => 'Invalid beneficiary relationship'];
        }

        if (!empty($birthdate)) {
            $dateValidation = $this->validateAndCalculateAge($birthdate);
            if (!$dateValidation['valid']) {
                return ['valid' => false, 'error' => 'Beneficiary birthdate: ' . $dateValidation['error']];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * REQUIREMENT #4: Auto-generate unique plan number
     * 
     * Format: PH-{branch_id}-{timestamp}-{random}
     * Example: PH-1-202605120000-ABC123
     * 
     * @param int $branchId
     * @return string
     */
    public function generateUniquePlanNumber(int $branchId): string
    {
        $prefix = 'PH-' . $branchId . '-' . date('YmdHi') . '-';
        $random = strtoupper(substr(md5(uniqid()), 0, 6));
        return $prefix . $random;
    }

    /**
     * REQUIREMENT #4: Create plan with Damayan Burial Program defaults
     * 
     * Single membership program only:
     * - program_id from MembershipService
     * - monthly_fee = 240.00
     * - membership_state = 'inactive' (awaiting payment)
     * 
     * @param int $planHolderId
     * @param int $branchId
     * @param int $createdBy (user_id of person creating plan)
     * @return array ['success' => bool, 'plan_id' => int|null, 'error' => string|null]
     */
    public function createMembershipPlan(int $planHolderId, int $branchId, int $createdBy): array
    {
        try {
            error_log("createMembershipPlan: Starting for planHolderId={$planHolderId}");
            
            $programInfo = MembershipService::getProgramInfo();
            error_log("createMembershipPlan: programInfo = " . json_encode($programInfo));

            MembershipService::ensureDefaultPackageVersion();
            MembershipService::ensureMembershipProgram();

            // Bug fix (found via Phase 3 live testing): package_id=1/
            // version_id=1 were hardcoded and no longer exist once the
            // catalog was rebuilt (package_versions.version_id has an FK
            // - inserting a non-existent version_id always failed).
            // Resolve the real Damayan entitlement package the same
            // robust way every other call site in the app does (prefer
            // the explicit flag, then MembershipService::
            // DEFAULT_PACKAGE_ID, then the lowest package_id) instead of
            // assuming ids that only held true at first-seed time.
            $packageAndVersion = $this->resolvePackageAndVersion();

            $planData = [
                'plan_holder_id' => $planHolderId,
                'package_id' => $packageAndVersion['package_id'],
                'program_id' => (int) ($programInfo['id'] ?? 1),
                'monthly_fee' => (float) ($programInfo['monthly_fee'] ?? 240.0),
                'months_paid' => 0,
                // Bug fix (found via Phase 3 live testing): plans.start_date
                // is NOT NULL - passing null here always failed the insert
                // (silently, since this runs inside a transaction; CI4
                // suppresses the query exception and just returns false).
                // Registration date, not activation date - matches how
                // every other plan-creation path in the app sets it.
                'start_date' => date('Y-m-d'),
                'status' => 'inactive',  // Awaiting initial payment
                'membership_state' => 'inactive',
                'overdue_months' => 0,
                'payment_coverage_until' => null,
                'next_due_date' => null,
                'legacy_remaining_balance' => (float) ($programInfo['monthly_fee'] ?? 240.0),
                'version_id' => $packageAndVersion['version_id'],
                'branch_id' => $branchId,
            ];

            error_log("createMembershipPlan: Inserting plan with data = " . json_encode($planData));
            
            $planId = (int) $this->planModel->insert($planData);
            error_log("createMembershipPlan: planId = {$planId}");
            
            if ($planId <= 0) {
                error_log("createMembershipPlan: ERROR - Unable to insert plan");
                throw new \RuntimeException('Unable to create membership plan');
            }

            error_log("createMembershipPlan: SUCCESS - plan_id = {$planId}");
            return ['success' => true, 'plan_id' => $planId, 'error' => null];
        } catch (\Throwable $e) {
            error_log("createMembershipPlan: EXCEPTION - " . $e->getMessage() . " | " . $e->getTraceAsString());
            return ['success' => false, 'plan_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Resolve the real Damayan entitlement package + an active version
     * for it - same robust pattern used by ClientPortalTrait::
     * resolvePackageAndVersion() and its siblings elsewhere in the app:
     * prefer the explicit is_damayan_entitlement flag, fall back to
     * MembershipService::DEFAULT_PACKAGE_ID, then the lowest package_id.
     * Creates an active package_versions row if none exists yet.
     *
     * @return array{package_id: int, version_id: int}
     */
    private function resolvePackageAndVersion(): array
    {
        $db = db_connect();

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

            return ['package_id' => $packageId, 'version_id' => (int) $db->insertID()];
        }

        return ['package_id' => $packageId, 'version_id' => (int) $version['version_id']];
    }

    /**
     * REQUIREMENT #3: Register existing user as plan holder
     * 
     * Transaction-safe process:
     * 1. Validate user exists with role_id=4
     * 2. Verify user not already linked
     * 3. Update user: is_plan_holder=1
     * 4. Create plan_holder record
     * 5. Create membership plan
     * 6. Commit transaction
     * 
     * @param int $userId
     * @param int $branchId
     * @param array $planHolderData
     * @param int $createdBy
     * @return array ['success' => bool, 'plan_holder_id' => int|null, 'error' => string|null]
     */
    public function registerExistingUser(int $userId, int $branchId, array $planHolderData, int $createdBy): array
    {
        error_log("registerExistingUser: Starting with userId={$userId}, branchId={$branchId}");
        error_log("registerExistingUser: planHolderData = " . json_encode($planHolderData));
        
        $db = db_connect();
        $db->transBegin();

        try {
            // Validate user exists and has correct role
            $user = $this->userModel->find($userId);
            error_log("registerExistingUser: User query result = " . json_encode($user));
            
            if (!$user || (int) ($user['role_id'] ?? 0) !== 4) {
                error_log("registerExistingUser: ERROR - User invalid or wrong role");
                throw new \RuntimeException('Selected user is invalid for plan holder registration (must have Plan Holder role)');
            }

            // Prevent duplicate plan holder registration
            $alreadyLinked = $this->planHolderModel->where('user_id', $userId)->first();
            if ($alreadyLinked) {
                error_log("registerExistingUser: ERROR - User already linked to plan holder");
                throw new \RuntimeException('Selected user is already registered as a plan holder');
            }

            // Update user to mark as plan holder
            $this->userModel->update($userId, [
                'is_plan_holder' => 1,
                'branch_id' => $branchId,
            ]);
            error_log("registerExistingUser: Updated user is_plan_holder=1");

            // Auto-generate unique plan number if not provided
            if (empty($planHolderData['unique_identifier'])) {
                $planHolderData['unique_identifier'] = $this->generateUniquePlanNumber($branchId);
                error_log("registerExistingUser: Generated plan number = {$planHolderData['unique_identifier']}");
            }

            // Verify unique_identifier uniqueness
            $existing = $this->planHolderModel->where('unique_identifier', $planHolderData['unique_identifier'])->first();
            if ($existing) {
                error_log("registerExistingUser: ERROR - Plan number already in use");
                throw new \RuntimeException('Plan number is already in use. Please use a different number.');
            }

            // Create plan holder record
            $planHolderData['user_id'] = $userId;
            $planHolderData['branch_id'] = $branchId;
            $planHolderData['status'] = 'inactive'; // Status should be inactive until payment verified
            $planHolderData['is_linked_account'] = 1;

            error_log("registerExistingUser: Inserting plan holder with data = " . json_encode($planHolderData));
            
            $planHolderId = (int) $this->planHolderModel->insert($planHolderData);
            error_log("registerExistingUser: planHolderId = {$planHolderId}");
            
            if ($planHolderId <= 0) {
                error_log("registerExistingUser: ERROR - Unable to insert plan holder, DB errors = " . json_encode($this->planHolderModel->errors()));
                throw new \RuntimeException('Unable to create plan holder record');
            }

            // Create membership plan
            error_log("registerExistingUser: Creating membership plan");
            $planResult = $this->createMembershipPlan($planHolderId, $branchId, $createdBy);
            error_log("registerExistingUser: planResult = " . json_encode($planResult));
            
            if (!$planResult['success']) {
                error_log("registerExistingUser: ERROR - Failed to create membership plan: " . $planResult['error']);
                throw new \RuntimeException('Unable to create membership plan: ' . $planResult['error']);
            }

            // Verify transaction status
            if ($db->transStatus() === false) {
                error_log("registerExistingUser: ERROR - Transaction status is false");
                throw new \RuntimeException('Database transaction error detected');
            }

            $db->transCommit();
            error_log("registerExistingUser: Transaction committed successfully");

            // Post-registration activities (outside transaction)
            $this->notificationService->notify(
                $userId,
                'Your plan holder profile has been registered successfully. Please proceed to complete your initial payment.',
                'registration_complete'
            );

            $this->activityLogService->log(
                $createdBy,
                'registered',
                'plan_holder',
                $planHolderId,
                'Registered existing user as plan holder',
                null,
                ['plan_holder_id' => $planHolderId, 'plan_id' => $planResult['plan_id']],
                ['user_role' => 'admin_or_branch_admin']
            );

            error_log("registerExistingUser: SUCCESS - Returning plan_holder_id={$planHolderId}");
            return ['success' => true, 'plan_holder_id' => $planHolderId, 'error' => null];
        } catch (\Throwable $e) {
            error_log("registerExistingUser: EXCEPTION - " . $e->getMessage() . " | " . $e->getTraceAsString());
            $db->transRollback();

            // Log the error
            error_log('ClientRegistrationService::registerExistingUser - Exception: ' . $e->getMessage());

            return ['success' => false, 'plan_holder_id' => null, 'error' => $e->getMessage()];
        }
        }

    /**
     * REQUIREMENT #3: Register new user and plan holder
     * 
     * Transaction-safe process:
     * 1. Validate all inputs
     * 2. Create user account
     * 3. Create plan_holder record
     * 4. Create membership plan
     * 5. Commit transaction
     * 
     * @param array $userData (username, email, password, first_name, last_name, contact_number)
     * @param int $branchId
     * @param array $planHolderData
     * @param int $createdBy
     * @return array ['success' => bool, 'user_id' => int|null, 'plan_holder_id' => int|null, 'error' => string|null]
     */
    public function registerNewUser(array $userData, int $branchId, array $planHolderData, int $createdBy): array
    {
        $db = db_connect();
        $db->transBegin();

        try {
            // Validate username
            $usernameValidation = $this->validateUsername($userData['username'] ?? '');
            if (!$usernameValidation['valid']) {
                throw new \RuntimeException($usernameValidation['error']);
            }

            // Validate email
            $emailValidation = $this->validateEmail($userData['email'] ?? '');
            if (!$emailValidation['valid']) {
                throw new \RuntimeException($emailValidation['error']);
            }

            // Validate password
            $passwordValidation = $this->validatePassword($userData['password'] ?? '');
            if (!$passwordValidation['valid']) {
                throw new \RuntimeException($passwordValidation['error']);
            }

            // Create user account
            $userId = (int) $this->userModel->insert([
                'username' => trim($userData['username']),
                'email' => trim($userData['email']),
                'password_hash' => password_hash($userData['password'], PASSWORD_DEFAULT),
                'first_name' => trim($userData['first_name'] ?? ''),
                'last_name' => trim($userData['last_name'] ?? ''),
                'contact_number' => trim($userData['contact_number'] ?? ''),
                'role_id' => 4,  // Plan Holder role
                'branch_id' => $branchId,
                'status' => 'active',
                'account_status' => 'verified',  // Auto-verified on registration
                'is_plan_holder' => 1,
                'must_change_password' => 0,
            ]);

            if ($userId <= 0) {
                throw new \RuntimeException('Unable to create user account');
            }

            // Auto-generate unique plan number if not provided
            if (empty($planHolderData['unique_identifier'])) {
                $planHolderData['unique_identifier'] = $this->generateUniquePlanNumber($branchId);
            }

            // Verify unique_identifier uniqueness
            $existing = $this->planHolderModel->where('unique_identifier', $planHolderData['unique_identifier'])->first();
            if ($existing) {
                throw new \RuntimeException('Plan number is already in use. Please use a different number.');
            }

            // Create plan holder record
            $planHolderData['user_id'] = $userId;
            $planHolderData['branch_id'] = $branchId;
            $planHolderData['status'] = 'inactive'; // Status should be inactive until payment verified
            $planHolderData['is_linked_account'] = 0;

            $planHolderId = (int) $this->planHolderModel->insert($planHolderData);
            if ($planHolderId <= 0) {
                throw new \RuntimeException('Unable to create plan holder record');
            }

            // Create membership plan
            $planResult = $this->createMembershipPlan($planHolderId, $branchId, $createdBy);
            if (!$planResult['success']) {
                throw new \RuntimeException('Unable to create membership plan: ' . $planResult['error']);
            }

            // Verify transaction status
            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction error detected');
            }

            $db->transCommit();

            // Post-registration activities (outside transaction)
            $this->notificationService->notify(
                $userId,
                'Welcome to the Damayan Burial Program! Please proceed to complete your initial payment to activate your membership.',
                'registration_complete'
            );

            $this->activityLogService->log(
                $createdBy,
                'created',
                'user',
                $userId,
                'Created new user and registered as plan holder',
                null,
                ['user_id' => $userId, 'plan_holder_id' => $planHolderId, 'plan_id' => $planResult['plan_id']],
                ['user_role' => 'admin_or_branch_admin']
            );

            return [
                'success' => true,
                'user_id' => $userId,
                'plan_holder_id' => $planHolderId,
                'error' => null
            ];
        } catch (\Throwable $e) {
            $db->transRollback();

            // Log the error
            error_log('ClientRegistrationService::registerNewUser - ' . $e->getMessage());

            return [
                'success' => false,
                'user_id' => null,
                'plan_holder_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }
}
