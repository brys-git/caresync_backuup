<?php

namespace App\Services;

use App\Models\PlanHolderModel;
use App\Models\UserModel;

/**
 * Single place that knows how a sign-up becomes a users row, so the public
 * sign-up page and the System Admin "Create Account" page cannot drift apart
 * on validation, password rules, or the Plan Holder auto-link behavior.
 */
class AccountRegistrationService
{
    public const ROLE_ADMIN       = 1;
    public const ROLE_BRANCH_ADMIN = 2;
    public const ROLE_STAFF       = 3;
    public const ROLE_CLIENT      = 4;

    /** Roles a System Admin may create from the Create Account page. */
    public const ADMIN_CREATABLE_ROLES = [
        self::ROLE_BRANCH_ADMIN => 'Branch Admin',
        self::ROLE_STAFF        => 'Staff',
        self::ROLE_CLIENT       => 'Client (Plan Holder)',
    ];

    /** Roles that belong to a branch and so require one. */
    public const BRANCH_ROLES = [self::ROLE_BRANCH_ADMIN, self::ROLE_STAFF];

    private const FIRST_ACCOUNT_LOCK = 'caresync_first_account';

    /**
     * Validation rules shared by sign-up and Create Account.
     */
    /**
     * @param bool $temporaryPassword true when a staff-side creator lets the
     *        system generate the password: the password fields are not posted.
     */
    public function rules(bool $temporaryPassword = false): array
    {
        $rules = [
            'username'          => 'required|min_length[4]|max_length[50]|is_unique[users.username]',
            'email'             => 'required|valid_email|max_length[100]|is_unique[users.email]',
            'first_name'        => 'required|max_length[50]',
            'last_name'         => 'required|max_length[50]',
            'unique_identifier' => 'permit_empty|max_length[100]',
            'contact_number'    => 'permit_empty|max_length[20]',
            'password'          => 'required|min_length[8]',
            'password_confirm'  => 'required|matches[password]',
        ];

        if ($temporaryPassword) {
            unset($rules['password'], $rules['password_confirm']);
        }

        return $rules;
    }

    public function hasAnyAccount(): bool
    {
        return db_connect()->table('users')->countAllResults() > 0;
    }

    /**
     * Public sign-up. The role is decided here and only here: the very first
     * account in an empty users table becomes System Admin, everyone else is
     * a Client. Nothing from the request can influence it.
     *
     * The "is this the first account?" check and the insert happen under a
     * named DB lock inside one transaction, so two simultaneous sign-ups on
     * an empty database cannot both become System Admin.
     *
     * @return array{user_id:int, role_id:int, first_admin:bool, linked_existing:bool}
     */
    public function registerPublic(array $input): array
    {
        $db = db_connect();
        $lockTaken = $this->acquireLock($db);

        $db->transBegin();

        try {
            $firstAdmin = $db->table('users')->countAllResults() === 0;

            if ($firstAdmin) {
                $this->ensureCoreRoles($db);
                $result = $this->insertAccount($input, self::ROLE_ADMIN, null, false);
            } else {
                $result = $this->insertAccount($input, self::ROLE_CLIENT, null, true);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to complete account registration.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        } finally {
            if ($lockTaken) {
                $this->releaseLock($db);
            }
        }

        $result['first_admin'] = $firstAdmin;

        return $result;
    }

    /**
     * Account created by a System Admin or a Branch Admin. $roleId must be
     * one of $allowedRoles (role_id => label); branch handling is enforced
     * here as well so the rule holds no matter which caller reaches this.
     *
     * Branch Admin / Staff always need a branch. A Client only keeps one when
     * $clientGetsBranch is true (a Branch Admin adding a client to their own
     * branch); the System Admin page leaves clients unassigned, like sign-up.
     *
     * Options: 'clientGetsBranch' (bool) and 'temporaryPassword' (bool) - when
     * true the system generates the password, flags the account to change it
     * at first sign-in, and sets an expiry; the plain text comes back in
     * 'temp_password' / 'temp_expires_at' for the one-time confirmation.
     *
     * @return array{user_id:int, role_id:int, linked_existing:bool, temp_password?:string, temp_expires_at?:string}
     */
    public function createByAdmin(
        array $input,
        int $roleId,
        ?int $branchId,
        array $allowedRoles = self::ADMIN_CREATABLE_ROLES,
        array $options = []
    ): array {
        $clientGetsBranch = (bool) ($options['clientGetsBranch'] ?? false);
        $temporary        = (bool) ($options['temporaryPassword'] ?? false);

        if (! array_key_exists($roleId, $allowedRoles)) {
            throw new \InvalidArgumentException('That role cannot be created here.');
        }

        $branchRole = in_array($roleId, self::BRANCH_ROLES, true);

        if ($branchRole && ($branchId === null || $branchId <= 0)) {
            throw new \InvalidArgumentException('A branch is required for this role.');
        }

        $keepBranch = $branchRole || ($clientGetsBranch && $roleId === self::ROLE_CLIENT);

        $extra = [];
        if ($temporary) {
            $loginDetails = new LoginDetailsService();
            $input['password'] = $loginDetails->generatePassword();
            $extra = [
                'must_change_password'     => 1,
                'temp_password_expires_at' => $loginDetails->expiryTimestamp(),
            ];
        }

        $db = db_connect();
        $db->transBegin();

        try {
            $result = $this->insertAccount(
                $input,
                $roleId,
                $keepBranch ? $branchId : null,
                $roleId === self::ROLE_CLIENT,
                $extra
            );

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Unable to create the account.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        if ($temporary) {
            $result['temp_password']   = (string) $input['password'];
            $result['temp_expires_at'] = $extra['temp_password_expires_at'];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $extra extra users columns (temporary-password flags)
     * @return array{user_id:int, role_id:int, linked_existing:bool}
     */
    private function insertAccount(array $input, int $roleId, ?int $branchId, bool $isClient, array $extra = []): array
    {
        $userModel = new UserModel();

        $saved = $userModel->insert($extra + [
            'username'             => trim((string) ($input['username'] ?? '')),
            'email'                => trim((string) ($input['email'] ?? '')),
            'first_name'           => trim((string) ($input['first_name'] ?? '')),
            'last_name'            => trim((string) ($input['last_name'] ?? '')),
            'contact_number'       => trim((string) ($input['contact_number'] ?? '')),
            'password_hash'        => password_hash((string) ($input['password'] ?? ''), PASSWORD_DEFAULT),
            'role_id'              => $roleId,
            'branch_id'            => $branchId,
            'status'               => 'active',
            // Clients stay 'pending' until plan holder registration is done;
            // staff-side accounts have nothing further to complete.
            'account_status'       => $isClient ? 'pending' : 'verified',
            'is_plan_holder'       => 0,
            'must_change_password' => 0,
        ]);

        if (! $saved) {
            throw new \RuntimeException('Unable to register right now.');
        }

        $newUserId       = (int) $userModel->getInsertID();
        $linkedExisting  = false;
        $uniqueIdentifier = trim((string) ($input['unique_identifier'] ?? ''));

        // Only ever auto-link on an exact unique_identifier match - never by
        // first_name/last_name alone (see the registration name-autolink bug).
        if ($isClient && $uniqueIdentifier !== '') {
            $existing = db_connect()->table('plan_holders ph')
                ->select('ph.plan_holder_id')
                ->where('ph.user_id !=', $newUserId)
                ->where('ph.unique_identifier', $uniqueIdentifier)
                ->orderBy('ph.plan_holder_id', 'DESC')
                ->get()
                ->getRowArray();

            if ($existing) {
                $planHolderModel = new PlanHolderModel();
                $planHolderModel->update((int) $existing['plan_holder_id'], [
                    'user_id'           => $newUserId,
                    'is_linked_account' => 1,
                ]);

                $linkedProfile = $planHolderModel->find((int) $existing['plan_holder_id']);
                $userModel->update($newUserId, [
                    'is_plan_holder' => 1,
                    'branch_id'      => $linkedProfile['branch_id'] ?? null,
                    'account_status' => 'verified',
                ]);

                $linkedExisting = true;
            }
        }

        return [
            'user_id'         => $newUserId,
            'role_id'         => $roleId,
            'linked_existing' => $linkedExisting,
        ];
    }

    /**
     * users.role_id has a foreign key to roles. On a freshly emptied
     * database the roles table can be empty too, which would make the very
     * first sign-up fail, so the five core roles are restored if missing.
     */
    private function ensureCoreRoles($db): void
    {
        $db->query(
            "INSERT IGNORE INTO roles (role_id, role_name) VALUES
                (1, 'Admin'), (2, 'Branch Admin'), (3, 'Staff'), (4, 'Plan Holder'), (5, 'Collector')"
        );
    }

    private function acquireLock($db): bool
    {
        $row = $db->query('SELECT GET_LOCK(?, 10) AS got', [self::FIRST_ACCOUNT_LOCK])->getRowArray();

        if ((int) ($row['got'] ?? 0) !== 1) {
            throw new \RuntimeException('The system is busy creating another account. Please try again.');
        }

        return true;
    }

    private function releaseLock($db): void
    {
        $db->query('SELECT RELEASE_LOCK(?)', [self::FIRST_ACCOUNT_LOCK]);
    }
}
