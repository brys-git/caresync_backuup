<?php

namespace App\Services;

use App\Models\PlanHolderModel;
use App\Models\UserModel;
use App\Services\MembershipService;

class ClientService
{
    /**
     * @param string $search optional search-box text: name, email, mobile
     *                       number or plan number (partial, any case)
     */
    public function getClientsByBranch(int $branchId, string $search = ''): array
    {
        $db = db_connect();

        $builder = $db->table('plan_holders ph')
            ->select('ph.plan_holder_id, ph.user_id, ph.unique_identifier, ph.status AS plan_holder_status, u.first_name, u.last_name, u.email, u.contact_number')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->where('ph.branch_id', $branchId);

        cs_apply_search($builder, $search, ['u.first_name', 'u.last_name', 'u.email', 'u.contact_number', 'ph.unique_identifier']);

        $clients = $builder
            ->orderBy('u.first_name', 'ASC')
            ->orderBy('u.last_name', 'ASC')
            ->get()
            ->getResultArray();

        // Add initial payment status for each client
        foreach ($clients as &$client) {
            $payment = $db->table('payments p')
                ->select('p.status AS initial_payment_status')
                ->join('plans pl', 'pl.plan_id = p.plan_id', 'inner')
                ->where('pl.plan_holder_id', (int) $client['plan_holder_id'])
                ->where('p.status !=', '')
                ->where('p.status IS NOT NULL', null, false)
                ->orderBy('p.payment_date', 'ASC')
                ->orderBy('p.payment_id', 'ASC')
                ->get()
                ->getRowArray();

            $client['initial_payment_status'] = $payment ? $payment['initial_payment_status'] : 'none';
        }

        return $clients;
    }

    public function getClientDetails(int $planHolderId): ?array
    {
        $db = db_connect();

        $client = $db->table('plan_holders ph')
            ->select('ph.plan_holder_id, ph.user_id, ph.branch_id, ph.unique_identifier, ph.address_no, ph.address_street, ph.address_barangay, ph.address_city, ph.barangay_code, ph.city_municipality_code, ph.date_of_birth, ph.place_of_birth, ph.age, ph.gender, ph.civil_status, ph.citizenship, ph.height, ph.weight, ph.spouse_name, ph.spouse_birthdate, ph.spouse_occupation, ph.senior_citizen_id, ph.organization_affiliation, ph.id_control_no, ph.coordinator, ph.application_date, ph.emergency_contact_name, ph.emergency_contact_number, ph.emergency_contact_address, ph.status AS plan_holder_status, u.first_name, u.middle_name, u.last_name, u.email, u.contact_number, b.branch_name')
            ->join('users u', 'u.user_id = ph.user_id', 'inner')
            ->join('branches b', 'b.branch_id = ph.branch_id', 'left')
            ->where('ph.plan_holder_id', $planHolderId)
            ->get()
            ->getRowArray();

        if (! $client) {
            return null;
        }

        $plan = $db->table('plans p')
            ->select('p.plan_id, p.plan_holder_id, p.package_id, p.monthly_fee, p.start_date, p.status AS plan_status, p.remaining_balance, p.months_paid')
            ->where('p.plan_holder_id', $planHolderId)
            ->orderBy("CASE WHEN p.status = 'active' THEN 1 ELSE 2 END", 'ASC', false)
            ->orderBy('p.plan_id', 'DESC')
            ->get()
            ->getRowArray();

        if ($plan) {
            // Panel brief, section 10: "ensure package/plan type is
            // clearly distinguished throughout the interface" - this used
            // to hardcode the generic legacy program name here even though
            // package_id (selected above) is the client's real, possibly
            // different package (see Packages::assignToPlan(), section 1).
            $resolvedName = (new MembershipService())->resolvePackageName((int) ($plan['package_id'] ?? 0));
            $plan['package_name'] = $resolvedName;
            $plan['program_name'] = $resolvedName;
        }

        $beneficiaries = [];
        if ($db->tableExists('beneficiaries')) {
            $beneficiaries = $db->table('beneficiaries')
                ->where('plan_holder_id', $planHolderId)
                ->orderBy('beneficiary_id', 'DESC')
                ->get()
                ->getResultArray();
        }

        $client['plan'] = $plan;
        $client['beneficiaries'] = $beneficiaries;

        return $client;
    }

    public function updateClient(int $planHolderId, array $data): bool
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $client = $db->table('plan_holders')
                ->select('plan_holder_id, user_id')
                ->where('plan_holder_id', $planHolderId)
                ->get()
                ->getRowArray();

            if (! $client) {
                throw new \RuntimeException('Client record was not found.');
            }

            $userModel = new UserModel();
            $planHolderModel = new PlanHolderModel();

            $updatedUser = $userModel->update((int) $client['user_id'], [
                'first_name' => (string) ($data['first_name'] ?? ''),
                'middle_name' => (string) ($data['middle_name'] ?? ''),
                'last_name' => (string) ($data['last_name'] ?? ''),
                'email' => (string) ($data['email'] ?? ''),
                'contact_number' => (string) ($data['contact_number'] ?? ''),
            ]);

            if (! $updatedUser) {
                throw new \RuntimeException('Failed to update user profile details.');
            }

            $updatedPlanHolder = $planHolderModel->update($planHolderId, [
                'id_control_no' => (string) ($data['id_control_no'] ?? ''),
                'coordinator' => (string) ($data['coordinator'] ?? ''),
                'application_date' => $this->nullable($data['application_date'] ?? null),
                'address_no' => (string) ($data['address_no'] ?? ''),
                'address_street' => (string) ($data['address_street'] ?? ''),
                'address_barangay' => (string) ($data['address_barangay'] ?? ''),
                'address_city' => (string) ($data['address_city'] ?? ''),
                'barangay_code' => (string) ($data['barangay_code'] ?? ''),
                'city_municipality_code' => (string) ($data['city_municipality_code'] ?? ''),
                'date_of_birth' => $this->nullable($data['date_of_birth'] ?? null),
                'place_of_birth' => (string) ($data['place_of_birth'] ?? ''),
                'age' => $this->nullableInt($data['age'] ?? null),
                'gender' => (string) ($data['gender'] ?? ''),
                'civil_status' => (string) ($data['civil_status'] ?? ''),
                'citizenship' => (string) ($data['citizenship'] ?? ''),
                'height' => $this->nullable($data['height'] ?? null),
                'weight' => $this->nullable($data['weight'] ?? null),
                'spouse_name' => (string) ($data['spouse_name'] ?? ''),
                'spouse_birthdate' => $this->nullable($data['spouse_birthdate'] ?? null),
                'spouse_occupation' => (string) ($data['spouse_occupation'] ?? ''),
                'senior_citizen_id' => (string) ($data['senior_citizen_id'] ?? ''),
                'organization_affiliation' => (string) ($data['organization_affiliation'] ?? ''),
                'status' => (string) ($data['status'] ?? 'active'),
            ]);

            // Emergency contact (the edit form always posted it; it was never saved).
            $emergency = [];
            foreach (['emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_number', 'emergency_contact_address'] as $field) {
                if (array_key_exists($field, $data) && $db->fieldExists($field, 'plan_holders')) {
                    $emergency[$field] = trim((string) $data[$field]);
                }
            }
            if ($emergency !== []) {
                $db->table('plan_holders')->where('plan_holder_id', $planHolderId)->update($emergency);
            }

            if (! $updatedPlanHolder) {
                throw new \RuntimeException('Failed to update plan holder details.');
            }

            // Beneficiaries: same parsing/validation as registration
            // (PlanHolderRegistrationService), reused rather than
            // duplicated - requires at least one valid beneficiary row,
            // same as the registration wizard's Beneficiaries step.
            if (array_key_exists('beneficiaries', $data) && is_array($data['beneficiaries'])) {
                $registrationService = new PlanHolderRegistrationService();
                $beneficiaries = $registrationService->parseBeneficiaries($data);
                $registrationService->saveBeneficiaries($planHolderId, $beneficiaries);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction failed while updating client.');
            }

            $db->transCommit();

            return true;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    public function getClientPayments(?int $planId): array
    {
        if ($planId === null || $planId <= 0) {
            return [];
        }

        $live = db_connect()->table('payments pay')
            ->select('pay.payment_date, pay.amount, pay.payment_method, pay.status, rb.first_name AS receiver_first_name, rb.last_name AS receiver_last_name')
            ->join('users rb', 'rb.user_id = pay.received_by', 'left')
            ->where('pay.plan_id', $planId)
            ->orderBy('pay.payment_date', 'DESC')
            ->orderBy('pay.payment_id', 'DESC')
            ->get()
            ->getResultArray();

        // Verified past records are part of the member's history too.
        $past = array_map(static fn (array $r): array => [
            'payment_date'        => $r['payment_date'],
            'amount'              => $r['amount'],
            'payment_method'      => $r['payment_method'],
            'status'              => 'paid',
            'receiver_first_name' => 'Recorded by branch',
            'receiver_last_name'  => '',
        ], LegacyRecordService::verifiedPaymentsAsRows($planId));

        $all = array_merge($live, $past);
        usort($all, static fn (array $a, array $b): int => strcmp((string) $b['payment_date'], (string) $a['payment_date']));

        return $all;
    }

    public function getClientServices(int $planHolderId): array
    {
        return db_connect()->table('services s')
            ->select("s.service_id, COALESCE(sl.service_name, '-') AS service_type, s.service_date, s.status, s.total_cost, COALESCE(sc.cost_entries, 0) AS cost_entries, COALESCE(ao.add_on_entries, 0) AS add_on_entries", false)
            ->join('service_list sl', 'sl.service_list_id = s.service_list_id', 'left')
            ->join('(SELECT service_id, COUNT(*) AS cost_entries FROM service_costs GROUP BY service_id) sc', 'sc.service_id = s.service_id', 'left', false)
            ->join('(SELECT service_id, COUNT(*) AS add_on_entries FROM add_ons GROUP BY service_id) ao', 'ao.service_id = s.service_id', 'left', false)
            ->where('s.plan_holder_id', $planHolderId)
            ->orderBy('s.service_date', 'DESC')
            ->orderBy('s.service_id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Search for an existing user by email
     */
    public function findUserByEmail(string $email): ?array
    {
        return db_connect()->table('users')
            ->where('email', $email)
            ->get()
            ->getRowArray();
    }

    /**
     * Search for an existing user by name
     */
    public function findUserByName(string $firstName, string $lastName): ?array
    {
        return db_connect()->table('users')
            ->where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->get()
            ->getRowArray();
    }

    /**
     * Register a new plan holder
     * Handles two cases:
     * 1. Client has no account - Create user + plan holder + plan
     * 2. Client already has account - Link existing user to plan holder + create plan
     */
    public function registerPlanHolder(array $data, int $branchId): int
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $userModel = new UserModel();
            $planHolderModel = new PlanHolderModel();

            // CASE 1: Check if user already exists
            $existingUser = null;
            if (! empty($data['email'])) {
                $existingUser = $this->findUserByEmail((string) $data['email']);
            }

            if ($existingUser) {
                // CASE 2: User already has account - just create plan holder
                $userId = (int) $existingUser['user_id'];

                // Update user to mark as plan holder
                $userModel->update($userId, [
                    'is_plan_holder' => 1,
                ]);
            } else {
                // CASE 1: Create new user account
                //
                // Bug fix (found via Phase 3 live testing): this never set
                // username/password_hash, both NOT NULL + UNIQUE on users.
                // In this environment's non-strict SQL mode the *first*
                // such registration silently succeeded with username=''/
                // password_hash='' (a permanently unusable account - can
                // never sign in), and every registration after that hard-
                // failed on the duplicate-'' unique constraint. Generate
                // real, unique login credentials instead - this form has
                // no username/password fields by design, so the plan
                // holder is expected to sign in later (e.g. via a
                // password reset) rather than being told the temp
                // password directly here.
                $newUser = [
                    'username' => $this->generateUniqueUsername(
                        (string) ($data['email'] ?? ''),
                        (string) ($data['first_name'] ?? ''),
                        (string) ($data['last_name'] ?? '')
                    ),
                    'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                    'must_change_password' => 1,
                    'first_name' => (string) ($data['first_name'] ?? ''),
                    'middle_name' => (string) ($data['middle_name'] ?? ''),
                    'last_name' => (string) ($data['last_name'] ?? ''),
                    'email' => (string) ($data['email'] ?? ''),
                    'contact_number' => (string) ($data['contact_number'] ?? ''),
                    'role_id' => 4, // Plan holder role
                    'is_plan_holder' => 1,
                    'status' => 'active',
                    'account_status' => 'verified',
                ];

                $userId = $userModel->insert($newUser);
                if (! $userId) {
                    throw new \RuntimeException('Failed to create user account.');
                }
            }

            $planHolderData = [
                'user_id' => $userId,
                'branch_id' => $branchId,
                'address_no' => (string) ($data['address_no'] ?? ''),
                'address_street' => (string) ($data['address_street'] ?? ''),
                'address_barangay' => (string) ($data['address_barangay'] ?? ''),
                'address_city' => (string) ($data['address_city'] ?? ''),
                'date_of_birth' => $this->nullable($data['date_of_birth'] ?? null),
                'place_of_birth' => (string) ($data['place_of_birth'] ?? ''),
                'age' => $this->nullableInt($data['age'] ?? null),
                'gender' => (string) ($data['gender'] ?? ''),
                'civil_status' => (string) ($data['civil_status'] ?? ''),
                'citizenship' => (string) ($data['citizenship'] ?? ''),
                'height' => $this->nullable($data['height'] ?? null),
                'weight' => $this->nullable($data['weight'] ?? null),
                'spouse_name' => (string) ($data['spouse_name'] ?? ''),
                'spouse_birthdate' => $this->nullable($data['spouse_birthdate'] ?? null),
                'spouse_occupation' => (string) ($data['spouse_occupation'] ?? ''),
                'senior_citizen_id' => (string) ($data['senior_citizen_id'] ?? ''),
                'organization_affiliation' => (string) ($data['organization_affiliation'] ?? ''),
                'status' => 'active',
                'is_linked_account' => $existingUser ? 1 : 0,
            ];

            $existingPlanHolder = $planHolderModel
                ->where('user_id', $userId)
                ->orderBy('plan_holder_id', 'DESC')
                ->first();

            if ($existingPlanHolder) {
                $planHolderId = (int) $existingPlanHolder['plan_holder_id'];

                $updateData = $planHolderData;
                if (! empty($existingPlanHolder['unique_identifier'])) {
                    $updateData['unique_identifier'] = (string) $existingPlanHolder['unique_identifier'];
                } else {
                    $updateData['unique_identifier'] = $this->generateUniqueIdentifier(
                        (string) ($data['first_name'] ?? ''),
                        (string) ($data['last_name'] ?? '')
                    );
                }

                $updated = $planHolderModel->update($planHolderId, $updateData);
                if (! $updated) {
                    throw new \RuntimeException('Failed to update existing plan holder record.');
                }
            } else {
                $planHolderData['unique_identifier'] = $this->generateUniqueIdentifier(
                    (string) ($data['first_name'] ?? ''),
                    (string) ($data['last_name'] ?? '')
                );

                $planHolderId = $planHolderModel->insert($planHolderData);
                if (! $planHolderId) {
                    throw new \RuntimeException('Failed to create plan holder record.');
                }
            }

            // Create plan only when missing for this plan holder profile.
            // program_id/package_id/monthly_fee, when present, come from a
            // plan chosen on the registration wizard's plan-picker step
            // (PlanHolderRegistrationService::resolveChosenProgram()) - null
            // when not offered (0 or 1 approved+active plans), which keeps
            // this call behaving exactly as it always did.
            $this->createPlanForPlanHolder(
                $planHolderId,
                isset($data['program_id']) ? (int) $data['program_id'] : null,
                isset($data['package_id']) ? (int) $data['package_id'] : null,
                isset($data['monthly_fee']) ? (float) $data['monthly_fee'] : null
            );

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database transaction failed during registration.');
            }

            $db->transCommit();

            return $planHolderId;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Create a plan for a registered plan holder. $programId/$forcedPackageId/
     * $forcedMonthlyFee come from a plan chosen on the registration wizard's
     * plan-picker step - null (the normal case, with 0 or 1 approved+active
     * plans and no picker shown) falls back to the original Damayan-flag
     * resolution and MembershipService::MONTHLY_FEE exactly as before.
     */
    private function createPlanForPlanHolder(int $planHolderId, ?int $programId = null, ?int $forcedPackageId = null, ?float $forcedMonthlyFee = null): void
    {
        $db = db_connect();

        $existingPlan = $db->table('plans')
            ->select('plan_id')
            ->where('plan_holder_id', $planHolderId)
            ->orderBy('plan_id', 'DESC')
            ->get()
            ->getRowArray();

        if ($existingPlan) {
            return;
        }

        $packageData = $this->resolveMembershipPackage($forcedPackageId);
        $packageId = (int) $packageData['package_id'];
        $effectiveMonthlyFee = $forcedMonthlyFee ?? MembershipService::MONTHLY_FEE;
        $initialBalance = $effectiveMonthlyFee * 12;
        $versionId = (int) $packageData['version_id'];

        $planData = [
            'plan_holder_id' => $planHolderId,
            'package_id' => $packageId,
            'program_id' => $programId,
            'monthly_fee' => $effectiveMonthlyFee,
            'start_date' => date('Y-m-d'),
            'status' => 'active',
            'months_paid' => 0,
            'remaining_balance' => number_format($initialBalance, 2, '.', ''),
            'version_id' => $versionId,
        ];

        $inserted = $db->table('plans')->insert($planData);
        if (! $inserted) {
            throw new \RuntimeException('Failed to create plan record.');
        }
    }

    private function resolveMembershipPackage(?int $forcedPackageId = null): array
    {
        $db = db_connect();

        if ($forcedPackageId !== null) {
            $package = $db->table('packages')->select('package_id')->where('package_id', $forcedPackageId)->get()->getRowArray();
            if ($package) {
                return $this->resolvePackageVersion($forcedPackageId);
            }
            // Falls through to the default resolution below if the chosen
            // package somehow no longer exists.
        }

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

        return $this->resolvePackageVersion((int) $package['package_id']);
    }

    /**
     * Prefers an active package_versions row for $packageId, falls back to
     * the newest one, and creates an active version (at MembershipService::
     * MONTHLY_FEE) if the package has none yet at all. Extracted from
     * resolveMembershipPackage() so a forced/chosen package_id (a specific
     * plan picked at registration) can resolve its version the same way
     * the default-lookup path always did.
     */
    private function resolvePackageVersion(int $packageId): array
    {
        $db = db_connect();

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

    /**
     * Generate a unique identifier for plan holder
     * Format: LASTNAME-FIRSTNAME-TIMESTAMP
     */
    private function generateUniqueIdentifier(string $firstName, string $lastName): string
    {
        $base = strtoupper(str_replace(' ', '', $lastName)) . '-' . strtoupper(str_replace(' ', '', $firstName));
        $timestamp = substr((string) time(), -6);

        return $base . '-' . $timestamp;
    }

    /**
     * Generate a unique, non-empty username for a plan holder account
     * created without one (see registerPlanHolder()) - prefers the
     * email's local part, falls back to name, always disambiguated
     * against the real users.username unique constraint.
     */
    private function generateUniqueUsername(string $email, string $firstName, string $lastName): string
    {
        $base = strtolower(trim((string) strstr($email, '@', true)));
        if ($base === '' || $base === false) {
            $base = strtolower(preg_replace('/[^a-z0-9]/i', '', $firstName . $lastName) ?: 'client');
        }
        $base = preg_replace('/[^a-z0-9_]/', '', $base) ?: 'client';
        $base = substr($base, 0, 40);

        $userModel = new UserModel();
        $candidate = $base;
        $suffix = 0;

        while ($userModel->where('username', $candidate)->first()) {
            $suffix++;
            $candidate = $base . $suffix;
        }

        return $candidate;
    }

    /**
     * Get available packages for plan holder selection
     */
    public function getAvailablePackages(): array
    {
        return db_connect()->table('packages')
            ->select('package_id, package_name, base_price')
            ->where('is_available', 1)
            ->orderBy('package_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function nullable($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt($value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : (int) $value;
    }
}
