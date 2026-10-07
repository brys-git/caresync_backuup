<?php

namespace App\Services;

use App\Models\PlanHolderModel;
use App\Models\PlanModel;

/**
 * Prompt 7, Step D: the one canonical plan-holder registration routine,
 * extracted from Client\ClientRegistrationController::submitPlanRegistration()
 * (the most complete of the 4 near-duplicate copies this replaces) so
 * Staff/Branch Admin/System Admin's registration forms stop each carrying
 * their own partial copy of this logic - in particular, the 3 staff-side
 * forms never collected beneficiaries or an emergency contact at all
 * before this, because ClientService::registerPlanHolder() (which they
 * called directly) never had anywhere to put them.
 *
 * Two real, deliberate differences between 'self' and 'staff' mode are
 * KEPT, not unified, because they reflect real differences in how a plan
 * actually starts:
 *   - 'self' (client registering themselves): no user account is
 *     created/linked here (the session user already exists) - only the
 *     plan_holder + plan rows. The plan starts 'inactive' (membership_state
 *     'inactive', no next_due_date/payment_coverage_until) because the
 *     client still has to complete a separate Initial Payment step before
 *     anything is active - see ClientPaymentController's initial-payment flow.
 *   - 'staff' (Staff/Branch Admin/System Admin registering someone else):
 *     delegates user/plan_holder/plan creation to the existing, already-
 *     tested ClientService::registerPlanHolder() (creates a new account
 *     with a generated username + must_change_password, or links an
 *     existing plan-holder-eligible account by email) - that method
 *     already makes the plan 'active' immediately, since staff/branch
 *     admin registration typically happens alongside collecting the
 *     initial payment in person (PaymentTracking's own "Initial Payments"
 *     tab). This service adds emergency contact + registered_by on top via
 *     one follow-up update - not inside registerPlanHolder()'s own
 *     transaction, but the plan_holder row is guaranteed to exist by the
 *     time that call returns successfully (or it throws and nothing here runs).
 *
 * Plan selection: staff mode's $wizard config exposes a 'plans' slot for
 * a future multi-plan picker, but no such picker is wired up here - Prompt
 * 5B (registration reading a chosen plan instead of the single Damayan
 * default) was explicitly skipped in the earlier Fix Prompts project, so
 * every mode still creates the one default Damayan-entitlement plan,
 * exactly as both source methods already did.
 */
class PlanHolderRegistrationService
{
    /**
     * @param array<string,mixed> $input   raw $_POST-shaped array (already
     *   passed the controller's own CI4 validate() rules for required/format
     *   checks - this method re-validates PSGC/ID verification/beneficiaries,
     *   which no per-role validate() rule set can express)
     * @param string $mode 'self' | 'staff'
     * @param array{user_id:int}|null $currentUser required for 'self' mode -
     *   the already-authenticated session user being registered
     * @param int|null $actingUserId the Staff/Branch Admin/Admin account
     *   performing a 'staff'-mode registration (plan_holders.registered_by)
     *
     * @throws \RuntimeException on any validation/verification failure -
     *   message is safe to show the user directly
     */
    public function register(array $input, int $branchId, string $mode, ?array $currentUser = null, ?int $actingUserId = null): int
    {
        if (! in_array($mode, ['self', 'staff'], true)) {
            throw new \RuntimeException('Invalid registration mode.');
        }

        if ($mode === 'self' && ! $currentUser) {
            throw new \RuntimeException('No signed-in user to register.');
        }

        $cityCode = trim((string) ($input['city_municipality_code'] ?? ''));
        $barangayCode = trim((string) ($input['barangay_code'] ?? ''));

        $psgc = new PsgcService();
        $cities = $psgc->getCities();
        if ($cities === null) {
            throw new \RuntimeException('Unable to verify the selected address right now. Please try again.');
        }

        $cityMatch = null;
        foreach ($cities as $city) {
            if ($city['code'] === $cityCode) {
                $cityMatch = $city;
                break;
            }
        }
        if ($cityMatch === null) {
            throw new \RuntimeException('Please choose a town/city and barangay in Oriental Mindoro.');
        }

        $barangays = $psgc->getBarangaysForCity($cityCode);
        if ($barangays === null) {
            throw new \RuntimeException('Unable to verify the selected address right now. Please try again.');
        }

        $barangayMatch = null;
        foreach ($barangays as $barangay) {
            if ($barangay['code'] === $barangayCode) {
                $barangayMatch = $barangay;
                break;
            }
        }
        if ($barangayMatch === null) {
            throw new \RuntimeException('Please choose a town/city and barangay in Oriental Mindoro.');
        }

        $dateOfBirth = trim((string) ($input['date_of_birth'] ?? ''));
        $dateOfBirth = $dateOfBirth !== '' ? $dateOfBirth : null;
        if ($dateOfBirth !== null && $dateOfBirth > date('Y-m-d')) {
            throw new \RuntimeException('Date of birth cannot be in the future.');
        }

        $identity = [
            'first_name'    => trim((string) ($input['first_name'] ?? '')),
            'middle_name'   => trim((string) ($input['middle_name'] ?? '')),
            'last_name'     => trim((string) ($input['last_name'] ?? '')),
            'date_of_birth' => (string) ($dateOfBirth ?? ''),
            'gender'        => trim((string) ($input['gender'] ?? '')),
        ];

        $this->enforceIdVerification($mode, $input, $identity, $currentUser);

        $beneficiaries = $this->parseBeneficiaries($input);

        $db = db_connect();
        $db->transStart();

        try {
            if ($mode === 'self') {
                $planHolderId = $this->registerSelf((int) $currentUser['user_id'], $branchId, $input, $dateOfBirth, $cityMatch, $barangayMatch);
            } else {
                $planHolderId = $this->registerStaff($branchId, $input, $dateOfBirth, $cityMatch, $barangayMatch, $actingUserId);
            }

            $this->saveBeneficiaries($planHolderId, $beneficiaries);
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            throw new \RuntimeException('Registration could not be saved. Please try again.');
        }

        // Staff mode's Government ID check above only peeked at the pending
        // verification (read-only - the account didn't exist yet at that
        // point). Now that the plan holder's user account is guaranteed to
        // exist, commit it for real under that user_id - the same
        // pending-token pattern Users::create() uses.
        if ($mode === 'staff') {
            $pendingToken = trim((string) ($input['government_id_pending_token'] ?? ''));
            $row = db_connect()->table('plan_holders')->select('user_id')->where('plan_holder_id', $planHolderId)->get()->getRowArray();
            $userId = (int) ($row['user_id'] ?? 0);
            if ($pendingToken !== '' && $userId > 0) {
                (new GovernmentIdVerificationService())->commitPending($pendingToken, $userId);
            }
        }

        // A client who registers themselves does it with nobody from the
        // branch present - unlike 'staff' mode, where the Staff/Branch
        // Admin doing the registering was right there. If their ID needs a
        // manual look (the normal outcome with no Anthropic API key
        // configured - see GovernmentIdVerificationService), the branch
        // would otherwise never know a review is waiting on them.
        if ($mode === 'self') {
            $verification = (new GovernmentIdVerificationService())->latestForUser((int) $currentUser['user_id']);
            if ($verification !== null && (string) ($verification['verification_status'] ?? '') === 'needs_review') {
                $this->notifyBranchOfPendingReview($branchId, (string) ($input['first_name'] ?? ''), (string) ($input['last_name'] ?? ''));
            }
        }

        return $planHolderId;
    }

    /**
     * Notifies every active Branch Admin and Staff account in this branch
     * that a client's Government ID needs a manual look (see
     * IdVerificationQueueController) - NotificationService::notify() only
     * ever targets one user_id at a time, so this resolves the recipient
     * list itself rather than adding a branch-wide method there for one caller.
     */
    private function notifyBranchOfPendingReview(int $branchId, string $firstName, string $lastName): void
    {
        if ($branchId <= 0) {
            return;
        }

        $recipients = db_connect()->table('users')
            ->select('user_id')
            ->where('branch_id', $branchId)
            ->where('status', 'active')
            ->groupStart()
                ->where('role_id', 2)
                ->orWhere('role_id', 3)
            ->groupEnd()
            ->get()
            ->getResultArray();

        if (empty($recipients)) {
            return;
        }

        $applicantName = trim($firstName . ' ' . $lastName);
        $message = $applicantName !== ''
            ? $applicantName . ' just registered and their Government ID needs a manual review.'
            : 'A new plan holder registration needs a manual Government ID review.';

        $notificationService = new NotificationService();
        foreach ($recipients as $recipient) {
            // NotificationService::notify() silently downgrades any type
            // outside its own hardcoded whitelist to 'general' (the
            // notifications.type column is a fixed MySQL ENUM) - passing
            // 'general' explicitly here instead of a made-up value that
            // would just get rewritten anyway.
            $notificationService->notify((int) $recipient['user_id'], $message, 'general');
        }
    }

    private function enforceIdVerification(string $mode, array $input, array $identity, ?array $currentUser): void
    {
        $idVerificationService = new GovernmentIdVerificationService();

        if ($mode === 'self') {
            $verification = $idVerificationService->latestForUser((int) $currentUser['user_id']);
            if ($verification === null) {
                throw new \RuntimeException('Please complete the Government ID Verification step before submitting.');
            }
        } else {
            $pendingToken = trim((string) ($input['government_id_pending_token'] ?? ''));
            if ($pendingToken === '') {
                throw new \RuntimeException('Please complete the Government ID Verification step before submitting.');
            }
            $verification = $idVerificationService->peekPending($pendingToken);
            if ($verification === null) {
                throw new \RuntimeException('The Government ID verification could not be found. Please verify the ID again.');
            }
        }

        if (! $idVerificationService->isValidForSubmission($verification, $identity)) {
            $message = (string) ($verification['verification_status'] ?? '') === 'failed'
                ? 'The Government ID does not match the details entered. Please correct the details or upload the correct ID.'
                : 'The details were changed after verifying the Government ID. Please verify the ID again.';
            throw new \RuntimeException($message);
        }
    }

    /**
     * Public (not just used internally by register()) so ClientService::
     * updateClient() can reuse the exact same validation for the plan
     * holder edit pages' Beneficiaries section (partials/registration/
     * _edit_form.php) instead of a second, drifting copy of these rules.
     *
     * @return array<int,array<string,mixed>>
     */
    public function parseBeneficiaries(array $input): array
    {
        $rowsInput = $input['beneficiaries'] ?? [];
        $rowsInput = is_array($rowsInput) ? $rowsInput : [];
        $allowedRelationships = config(\Config\Beneficiary::class)->relationships;
        // Letters, spaces, and the punctuation real Filipino names use
        // (Dela Cruz, D'Souza, Añonuevo).
        $namePattern = '/^[\p{L}\s.\'-]+$/u';

        $beneficiaries = [];
        $isPrimary = true;

        foreach ($rowsInput as $row) {
            $firstName = trim((string) ($row['first_name'] ?? ''));
            $middleName = trim((string) ($row['middle_name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $birthday = trim((string) ($row['birthday'] ?? ''));
            $relationship = trim((string) ($row['relationship'] ?? ''));
            $relationshipOther = trim((string) ($row['relationship_other'] ?? ''));

            if ($firstName === '' && $middleName === '' && $lastName === '' && $birthday === '' && $relationship === '' && $relationshipOther === '') {
                continue;
            }

            if ($firstName === '' || $lastName === '' || $relationship === '') {
                throw new \RuntimeException('Each beneficiary needs a first name, last name, and relationship.');
            }

            if (mb_strlen($firstName) > 100 || ! preg_match($namePattern, $firstName)) {
                throw new \RuntimeException('Beneficiary first name must be 100 characters or fewer and contain only letters.');
            }
            if ($middleName !== '' && (mb_strlen($middleName) > 100 || ! preg_match($namePattern, $middleName))) {
                throw new \RuntimeException('Beneficiary middle name must be 100 characters or fewer and contain only letters.');
            }
            if (mb_strlen($lastName) > 100 || ! preg_match($namePattern, $lastName)) {
                throw new \RuntimeException('Beneficiary last name must be 100 characters or fewer and contain only letters.');
            }

            if (! array_key_exists($relationship, $allowedRelationships)) {
                throw new \RuntimeException('Please select a valid beneficiary relationship.');
            }
            if ($relationship === 'Other') {
                if ($relationshipOther === '' || mb_strlen($relationshipOther) > 50) {
                    throw new \RuntimeException('Please specify the beneficiary relationship (50 characters or fewer).');
                }
                $relationship = $relationshipOther;
            }

            if ($birthday !== '' && $birthday > date('Y-m-d')) {
                throw new \RuntimeException('Beneficiary birthday cannot be in the future.');
            }

            $beneficiaries[] = [
                'first_name' => $firstName,
                'middle_name' => $middleName !== '' ? $middleName : null,
                'last_name' => $lastName,
                'name_extension' => null,
                'date_of_birth' => $birthday !== '' ? $birthday : null,
                'relationship' => $relationship,
                'is_primary' => $isPrimary ? 1 : 0,
            ];
            $isPrimary = false;
        }

        if (empty($beneficiaries)) {
            throw new \RuntimeException('At least one beneficiary is required. Please provide a name and relationship for the first beneficiary.');
        }

        return $beneficiaries;
    }

    /** Public for the same reason as parseBeneficiaries() above. */
    public function saveBeneficiaries(int $planHolderId, array $beneficiaries): void
    {
        $db = db_connect();
        foreach ($beneficiaries as &$row) {
            $row['plan_holder_id'] = $planHolderId;
        }
        unset($row);

        $db->table('beneficiaries')->where('plan_holder_id', $planHolderId)->delete();
        $db->table('beneficiaries')->insertBatch($beneficiaries);
    }

    /** 'self' mode: upsert the plan_holder row for the already-signed-in user, plan starts inactive. */
    private function registerSelf(int $userId, int $branchId, array $input, ?string $dateOfBirth, array $cityMatch, array $barangayMatch): int
    {
        $planHolderModel = new PlanHolderModel();

        $planHolderData = $this->filterTableData('plan_holders', [
            'user_id' => $userId,
            'id_control_no' => trim((string) ($input['id_control_no'] ?? '')),
            'coordinator' => trim((string) ($input['coordinator'] ?? '')),
            'application_date' => $this->nullable($input['application_date'] ?? null),
            'address_no' => trim((string) ($input['address_no'] ?? '')),
            'address_street' => trim((string) ($input['address_street'] ?? '')),
            'address_barangay' => $barangayMatch['name'],
            'barangay_code' => $barangayMatch['code'],
            'address_city' => $cityMatch['name'],
            'city_municipality_code' => $cityMatch['code'],
            'date_of_birth' => $dateOfBirth,
            'place_of_birth' => trim((string) ($input['place_of_birth'] ?? '')),
            'age' => cs_age_from_dob($dateOfBirth),
            'gender' => trim((string) ($input['gender'] ?? '')),
            'civil_status' => trim((string) ($input['civil_status'] ?? '')),
            'citizenship' => trim((string) ($input['citizenship'] ?? '')),
            'height' => $this->nullable($input['height'] ?? null),
            'weight' => $this->nullable($input['weight'] ?? null),
            'spouse_name' => trim((string) ($input['spouse_name'] ?? '')),
            'spouse_birthdate' => $this->nullable($input['spouse_birthdate'] ?? null),
            'spouse_occupation' => trim((string) ($input['spouse_occupation'] ?? '')),
            'senior_citizen_id' => trim((string) ($input['senior_citizen_id'] ?? '')),
            'organization_affiliation' => trim((string) ($input['organization_affiliation'] ?? '')),
            'emergency_contact_name' => trim((string) ($input['emergency_contact_name'] ?? '')),
            'emergency_contact_relationship' => trim((string) ($input['emergency_contact_relationship'] ?? '')),
            'emergency_contact_number' => trim((string) ($input['emergency_contact_number'] ?? '')),
            'emergency_contact_address' => trim((string) ($input['emergency_contact_address'] ?? '')),
            'branch_id' => $branchId,
            'status' => 'inactive',
        ]);

        $existingHolder = $planHolderModel->where('user_id', $userId)->orderBy('plan_holder_id', 'DESC')->first();

        if ($existingHolder) {
            $planHolderId = (int) $existingHolder['plan_holder_id'];
            if (! $planHolderModel->update($planHolderId, $planHolderData)) {
                throw new \RuntimeException('Unable to update plan holder details.');
            }
        } else {
            $planHolderId = (int) $planHolderModel->insert($planHolderData, true);
            if ($planHolderId <= 0) {
                $row = $planHolderModel->where('user_id', $userId)->orderBy('plan_holder_id', 'DESC')->first();
                $planHolderId = (int) ($row['plan_holder_id'] ?? 0);
            }
            if ($planHolderId <= 0) {
                throw new \RuntimeException('Unable to save plan holder details.');
            }
        }

        (new \App\Models\UserModel())->update($userId, [
            'is_plan_holder' => 1,
            'branch_id' => $branchId,
        ]);
        session()->set('is_plan_holder', 1);
        session()->set('access_state', 'awaiting_activation');

        $planModel = new PlanModel();
        $existingPlan = $planModel->where('plan_holder_id', $planHolderId)->orderBy('plan_id', 'DESC')->first();
        if (! $existingPlan) {
            $chosenProgram = $this->resolveChosenProgram($input);
            $packageData = $this->resolvePackageAndVersion($chosenProgram['package_id'] ?? null);
            $monthlyFee = $chosenProgram['monthly_fee'] ?? MembershipService::MONTHLY_FEE;

            $planModel->insert($this->filterTableData('plans', [
                'plan_holder_id' => $planHolderId,
                'package_id' => $packageData['package_id'],
                'program_id' => $chosenProgram['program_id'] ?? null,
                'monthly_fee' => $monthlyFee,
                'passbook_fee' => 50,
                'start_date' => date('Y-m-d'),
                'status' => 'inactive',
                'months_paid' => 0,
                'remaining_balance' => $monthlyFee * 12,
                'next_due_date' => null,
                'payment_coverage_until' => null,
                'membership_state' => 'inactive',
                'overdue_months' => 0,
                'version_id' => $packageData['version_id'],
            ]));
        }

        return $planHolderId;
    }

    /** 'staff' mode: delegates account/plan_holder/plan creation to the existing ClientService, then layers on emergency contact + registered_by. */
    private function registerStaff(int $branchId, array $input, ?string $dateOfBirth, array $cityMatch, array $barangayMatch, ?int $actingUserId): int
    {
        $clientService = new ClientService();
        $chosenProgram = $this->resolveChosenProgram($input);

        $planHolderId = $clientService->registerPlanHolder([
            'program_id' => $chosenProgram['program_id'] ?? null,
            'package_id' => $chosenProgram['package_id'] ?? null,
            'monthly_fee' => $chosenProgram['monthly_fee'] ?? null,
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'middle_name' => trim((string) ($input['middle_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'contact_number' => trim((string) ($input['contact_number'] ?? '')),
            'date_of_birth' => $dateOfBirth,
            'place_of_birth' => trim((string) ($input['place_of_birth'] ?? '')),
            'age' => cs_age_from_dob($dateOfBirth),
            'gender' => trim((string) ($input['gender'] ?? '')),
            'civil_status' => trim((string) ($input['civil_status'] ?? '')),
            'citizenship' => trim((string) ($input['citizenship'] ?? '')),
            'height' => $input['height'] ?? null,
            'weight' => $input['weight'] ?? null,
            'address_no' => trim((string) ($input['address_no'] ?? '')),
            'address_street' => trim((string) ($input['address_street'] ?? '')),
            'address_barangay' => $barangayMatch['name'],
            'address_city' => $cityMatch['name'],
            'spouse_name' => trim((string) ($input['spouse_name'] ?? '')),
            'spouse_birthdate' => $input['spouse_birthdate'] ?? null,
            'spouse_occupation' => trim((string) ($input['spouse_occupation'] ?? '')),
            'senior_citizen_id' => trim((string) ($input['senior_citizen_id'] ?? '')),
            'organization_affiliation' => trim((string) ($input['organization_affiliation'] ?? '')),
        ], $branchId);

        $planHolderModel = new PlanHolderModel();
        $planHolderModel->update($planHolderId, $this->filterTableData('plan_holders', [
            'barangay_code' => $barangayMatch['code'],
            'city_municipality_code' => $cityMatch['code'],
            'emergency_contact_name' => trim((string) ($input['emergency_contact_name'] ?? '')),
            'emergency_contact_relationship' => trim((string) ($input['emergency_contact_relationship'] ?? '')),
            'emergency_contact_number' => trim((string) ($input['emergency_contact_number'] ?? '')),
            'emergency_contact_address' => trim((string) ($input['emergency_contact_address'] ?? '')),
            'registered_by' => $actingUserId,
        ]));

        return $planHolderId;
    }

    /**
     * The plan chosen on the registration wizard's "Choose a Plan" step
     * (program_id, only rendered/required when 2+ approved+active plans
     * exist - see registration_wizard_config()/_step_applicant.php), or
     * null when not submitted/not a valid approved+active plan. Falling
     * back to null (not throwing) matters: with 0 or 1 plans the form never
     * renders the picker at all, so program_id legitimately won't be in
     * $input, and registration must keep working exactly as before.
     */
    private function resolveChosenProgram(array $input): ?array
    {
        $programId = (int) ($input['program_id'] ?? 0);
        if ($programId <= 0) {
            return null;
        }

        return db_connect()->table('membership_programs')
            ->select('program_id, package_id, monthly_fee, plan_price, term_months')
            ->where('program_id', $programId)
            ->where('approval_status', 'approved')
            ->where('is_active', 1)
            ->get()
            ->getRowArray() ?: null;
    }

    /**
     * Verbatim from Client\ClientPortalTrait::resolvePackageAndVersion() -
     * kept as an exact copy rather than a shared extraction, since that
     * trait is Client-controller-specific plumbing this service shouldn't
     * depend on. Prefers the explicit Damayan entitlement flag, falls back
     * to DEFAULT_PACKAGE_ID then to any package; for the version, prefers
     * an active package_versions row, falls back to the newest one, and
     * creates one if the package has none yet at all.
     *
     * $forcedPackageId short-circuits the above lookup entirely - used when
     * a specific plan was chosen at registration, so its own entitled
     * package is resolved instead of whichever package happens to carry
     * the legacy Damayan flag.
     */
    private function resolvePackageAndVersion(?int $forcedPackageId = null): array
    {
        $db = db_connect();

        if ($forcedPackageId !== null) {
            $package = $db->table('packages')->select('package_id')->where('package_id', $forcedPackageId)->get()->getRowArray();
            if (! $package) {
                throw new \RuntimeException('The selected plan\'s package could not be found.');
            }
        } else {
            $package = $db->table('packages')->select('package_id')->where('is_damayan_entitlement', 1)->orderBy('package_id', 'ASC')->get()->getRowArray();
        }
        if (! $package) {
            $package = $db->table('packages')->select('package_id')->where('package_id', MembershipService::DEFAULT_PACKAGE_ID)->get()->getRowArray();
        }
        if (! $package) {
            $package = $db->table('packages')->select('package_id')->orderBy('package_id', 'ASC')->get()->getRowArray();
        }
        if (! $package) {
            throw new \RuntimeException('No package is configured yet. Please ask admin to create a package first.');
        }

        $packageId = (int) $package['package_id'];
        $version = $db->table('package_versions')->select('version_id')->where('package_id', $packageId)->where('status', 'active')->orderBy('version_id', 'DESC')->get()->getRowArray();
        if (! $version) {
            $version = $db->table('package_versions')->select('version_id')->where('package_id', $packageId)->orderBy('version_id', 'DESC')->get()->getRowArray();
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

            return ['package_id' => $packageId, 'version_id' => $versionId];
        }

        return ['package_id' => $packageId, 'version_id' => (int) $version['version_id']];
    }

    private function nullable($value)
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === '' || $value === null) ? null : $value;
    }

    private function filterTableData(string $table, array $data): array
    {
        $db = db_connect();
        $fields = $db->getFieldNames($table);

        if (empty($fields)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($fields));
    }
}
