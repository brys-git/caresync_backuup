<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\ActivityLogService;
use App\Services\GovernmentIdVerificationService;
use App\Services\NotificationService;
use Config\Services;

class Users extends BaseController
{
    public function create(): string
    {
        $db = db_connect();

        $roles = $db->table('roles')->select('role_id, role_name')->orderBy('role_id', 'ASC')->get()->getResultArray();
        $branches = $db->table('branches')
            ->select('branch_id, branch_name')
            ->where('status', 'active')
            ->orderBy('branch_name', 'ASC')
            ->get()
            ->getResultArray();

        return view('users/create', [
            'roles' => $roles,
            'branches' => $branches,
            'current_role_id' => (int) session('role_id'),
            'role_layout' => $this->resolveLayoutView(),
            'id_types' => (new GovernmentIdVerificationService())->idTypes(),
            'page_title' => 'Create user account',
            'page_sub' => 'Complete each step, then review before creating the account.',
        ]);
    }

    public function store()
    {
        $creatorRole = (int) session('role_id');

        $rules = [
            'username' => 'required|min_length[4]|max_length[50]|is_unique[users.username]',
            'email' => 'required|valid_email|max_length[100]|is_unique[users.email]',
            'first_name' => 'required|max_length[50]',
            'last_name' => 'required|max_length[50]',
            'role_id' => 'required|in_list[1,2,3,4,5]',
            'status' => 'required|in_list[active,inactive]',
            'account_status' => 'required|in_list[pending,verified]',
        ];

        if ($creatorRole === 1) {
            $rules['password'] = 'required|min_length[8]';
            $rules['password_confirm'] = 'required|matches[password]';
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Government ID Verification, applied here the same way it's
        // applied to Client/Plan Holder self-registration: the wizard's
        // step-gating is client-side UX only, never trusted alone. The
        // new account doesn't exist yet at upload time, so the browser
        // only ever holds a random token pointing at a result this
        // service already computed server-side (see
        // GovernmentIdVerificationService::verifyPending()).
        $pendingVerificationToken = trim((string) $this->request->getPost('government_id_pending_token'));
        if ($pendingVerificationToken === '') {
            return redirect()->back()->withInput()->with('error', 'Please complete the Government ID Verification step before submitting.');
        }

        $idVerificationService = new GovernmentIdVerificationService();
        $pendingVerification = $idVerificationService->peekPending($pendingVerificationToken);
        if ($pendingVerification === null) {
            return redirect()->back()->withInput()->with('error', 'Your Government ID verification could not be found. Please verify the ID again.');
        }

        $submittedIdentityForId = [
            'first_name'  => trim((string) $this->request->getPost('first_name')),
            'middle_name' => trim((string) $this->request->getPost('middle_name')),
            'last_name'   => trim((string) $this->request->getPost('last_name')),
        ];

        if (! $idVerificationService->isValidForSubmission($pendingVerification, $submittedIdentityForId)) {
            $message = (string) ($pendingVerification['verification_status'] ?? '') === 'failed'
                ? 'The Government ID does not match the details entered. Please correct the details or upload the correct ID.'
                : 'The name was changed after verifying the Government ID. Please verify the ID again.';

            return redirect()->back()->withInput()->with('error', $message);
        }

        $requestedRoleId = (int) $this->request->getPost('role_id');

        // Security fix (2026-09-10 scan): role_id was previously trusted
        // as-is for any creator except Staff, so a Branch Admin could
        // submit role_id=1 and mint themselves (or anyone) a System Admin
        // account - the view's "disabled" select for Staff was never a
        // real server-side control for Admin/Branch Admin. A creator may
        // never assign a role equal to or above their own.
        if ($creatorRole === 3) {
            $requestedRoleId = 4;
        } elseif ($creatorRole === 2 && ! in_array($requestedRoleId, [3, 4, 5], true)) {
            return redirect()->back()->withInput()->with('error', 'Branch Admins may only create Staff, Plan Holder, or Collector accounts.');
        } elseif ($creatorRole !== 1 && $creatorRole !== 2) {
            return redirect()->to('/unauthorized');
        }

        $branchIdInput = $this->request->getPost('branch_id');
        $branchId = ($branchIdInput === null || $branchIdInput === '') ? null : (int) $branchIdInput;

        // Branch Admin/Staff can only ever populate their own branch, never
        // an arbitrary one submitted from the form - same fix as above.
        if ($creatorRole === 2 || $creatorRole === 3) {
            $branchId = (int) session('branch_id') ?: null;
        }

        $isPlanHolder = (int) $this->request->getPost('is_plan_holder');
        if ($isPlanHolder !== 1) {
            $isPlanHolder = 0;
        }

        $mustChangePassword = (int) $this->request->getPost('must_change_password') === 1 ? 1 : 0;
        $plainPassword = (string) $this->request->getPost('password');

        // Bug fix (2026-09-10 scan): the view only ever renders password
        // fields for creatorRole === 1 - for a Branch-Admin-created (role
        // 2) account, $plainPassword was silently '' (no field submitted),
        // so every such account got password_hash('', ...) instead of a
        // real temporary password. Branch Admin now gets the same
        // generate-and-email flow Staff already had.
        if ($creatorRole === 2 || $creatorRole === 3) {
            $plainPassword = $this->generateTemporaryPassword();
            $mustChangePassword = 1;
        }

        $payload = [
            'username' => trim((string) $this->request->getPost('username')),
            'email' => trim((string) $this->request->getPost('email')),
            'first_name' => trim((string) $this->request->getPost('first_name')),
            'middle_name' => trim((string) $this->request->getPost('middle_name')) ?: null,
            'last_name' => trim((string) $this->request->getPost('last_name')),
            'contact_number' => trim((string) $this->request->getPost('contact_number')),
            'role_id' => $requestedRoleId,
            'branch_id' => $branchId,
            'status' => (string) $this->request->getPost('status'),
            'account_status' => (string) $this->request->getPost('account_status'),
            'is_plan_holder' => $isPlanHolder,
            'must_change_password' => $mustChangePassword,
            'password_hash' => password_hash($plainPassword, PASSWORD_DEFAULT),
        ];

        if ($creatorRole === 3) {
            $payload['account_status'] = 'verified';
            $payload['is_plan_holder'] = 1;
        }

        $userModel = new UserModel();

        $newUserId = $userModel->insert($payload, true);

        if (! $newUserId) {
            return redirect()->back()->withInput()->with('error', 'Failed to create user account.');
        }

        $idVerificationService->commitPending($pendingVerificationToken, (int) $newUserId);

        $notificationService = new NotificationService();
        $activityLogService = new ActivityLogService();

        if ($creatorRole === 2 || $creatorRole === 3) {
            $emailSent = $this->sendTemporaryPasswordEmail($payload['email'], $payload['username'], $plainPassword);
            if (! $emailSent) {
                return redirect()->back()->with('error', 'User created but temporary password email failed to send.');
            }

            $notificationService->notify((int) $newUserId, 'Your account was created with a temporary password. Check your email and change it after sign in.', 'registration_pending');
            $activityLogService->log((int) session('user_id'), 'created', 'user', (int) $newUserId, 'Created user account with temporary password');

            return redirect()->back()->with('success', 'User created. Temporary password was sent via email and password change is required.');
        }

        $notificationService->notify((int) $newUserId, 'Your account was created successfully.', 'registration_pending');
        $activityLogService->log((int) session('user_id'), 'created', 'user', (int) $newUserId, 'Created user account');

        return redirect()->back()->with('success', 'User account created successfully.');
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

        if ($role === 5) {
            return 'layouts/collector';
        }

        return 'layouts/plan_holder';
    }

    private function generateTemporaryPassword(): string
    {
        return strtoupper(bin2hex(random_bytes(4))) . substr((string) time(), -2);
    }

    private function sendTemporaryPasswordEmail(string $emailAddress, string $username, string $temporaryPassword): bool
    {
        $email = Services::email();

        $email->setTo($emailAddress);
        $email->setSubject('CareSync Temporary Password');
        $email->setMessage(
            "Hello {$username},\n\n"
            . "Your temporary password is: {$temporaryPassword}\n"
            . "Please sign in and change your password immediately.\n\n"
            . "- CareSync"
        );

        return $email->send(false);
    }
}
