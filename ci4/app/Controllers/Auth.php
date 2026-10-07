<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AccountRegistrationService;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use App\Services\PasswordResetService;

class Auth extends BaseController
{
    public function login()
    {
        if (session()->has('user_id')) {
            return redirect()->to($this->defaultDashboardForRole((int) session('role_id')));
        }

        return view('auth/login');
    }

    public function attemptLogin()
    {
        $identifier = trim((string) $this->request->getPost('identifier'));
        $password   = (string) $this->request->getPost('password');

        if ($identifier === '' || $password === '') {
            return redirect()->back()->withInput()->with('error', 'Username/email and password are required.');
        }

        $userModel = new UserModel();
        // Joins branches/roles so the topbar's name/branch chip (CareSync UI)
        // has what it needs without a second query - left join on branches
        // since a System Admin may have no branch_id.
        $user = $userModel
            ->select('users.*, branches.branch_name AS branch_name, roles.role_name AS role_name')
            ->join('branches', 'branches.branch_id = users.branch_id', 'left')
            ->join('roles', 'roles.role_id = users.role_id', 'left')
            ->groupStart()
            ->where('users.username', $identifier)
            ->orWhere('users.email', $identifier)
            ->groupEnd()
            ->first();

        if (! $user || ! password_verify($password, (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid credentials.');
        }

        if (($user['status'] ?? 'active') !== 'active') {
            return redirect()->back()->withInput()->with('error', 'Your account is inactive.');
        }

        // Temporary password issued by staff: it only works until it expires.
        if ((int) ($user['must_change_password'] ?? 0) === 1
            && ! empty($user['temp_password_expires_at'])
            && strtotime((string) $user['temp_password_expires_at']) < time()) {
            return redirect()->back()->withInput()->with('error', 'Your temporary password has expired. Please ask your branch to resend your login details.');
        }

        session()->set([
            'user_id'   => (int) $user['user_id'],
            'role_id'   => (int) $user['role_id'],
            'branch_id' => $user['branch_id'] === null ? null : (int) $user['branch_id'],
            'is_plan_holder' => (int) ($user['is_plan_holder'] ?? 0),
            'must_change_password' => (int) ($user['must_change_password'] ?? 0),
            // For the CareSync UI topbar (name + branch chip) - see BaseController::shareCareSyncViewData().
            'first_name'  => (string) ($user['first_name'] ?? ''),
            'last_name'   => (string) ($user['last_name'] ?? ''),
            'branch_name' => (string) ($user['branch_name'] ?? ''),
            'role_name'   => (string) ($user['role_name'] ?? ''),
        ]);

        $userModel->update((int) $user['user_id'], ['last_login' => date('Y-m-d H:i:s')]);

        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            return redirect()->to('/change-password')->with('success', 'Please change your temporary password.');
        }

        return redirect()->to($this->defaultDashboardForRole((int) $user['role_id']));
    }

    public function register()
    {
        return view('auth/register', [
            'isFirstAccount' => ! (new AccountRegistrationService())->hasAnyAccount(),
        ]);
    }

    public function attemptRegister()
    {
        $service = new AccountRegistrationService();

        if (! $this->validate($service->rules())) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        // Only the fields the form actually has are passed on. A posted
        // role_id / branch_id is never read: the service decides the role.
        try {
            $result = $service->registerPublic([
                'username'          => $this->request->getPost('username'),
                'email'             => $this->request->getPost('email'),
                'first_name'        => $this->request->getPost('first_name'),
                'last_name'         => $this->request->getPost('last_name'),
                'contact_number'    => $this->request->getPost('contact_number'),
                'unique_identifier' => $this->request->getPost('unique_identifier'),
                'password'          => $this->request->getPost('password'),
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $newUserId = $result['user_id'];
        $notificationService = new NotificationService();
        $activityLogService = new ActivityLogService();

        if ($result['first_admin']) {
            $activityLogService->log(
                $newUserId,
                'created',
                'user',
                $newUserId,
                'First account created as System Admin',
                null,
                ['role_id' => AccountRegistrationService::ROLE_ADMIN]
            );

            return redirect()->to('/login')->with('success', 'Your System Administrator account has been created. Please sign in.');
        }

        if ($result['linked_existing']) {
            $notificationService->notify($newUserId, 'Your account has been successfully linked to your membership.', 'registration_pending');
        } else {
            $notificationService->notify($newUserId, 'Your account was created. Complete plan holder registration to unlock services and payments.', 'registration_pending');
        }

        $activityLogService->log(
            $newUserId,
            'created',
            'user',
            $newUserId,
            'Registered new client account',
            null,
            ['linked_existing_plan_holder' => $result['linked_existing']]
        );

        return redirect()->to('/login')->with('success', 'Account created successfully. Sign in to continue.');
    }

    public function forgotPassword()
    {
        if (session()->has('user_id')) {
            return redirect()->to($this->defaultDashboardForRole((int) session('role_id')));
        }

        return view('auth/forgot_password', ['pageTitle' => 'Forgot password']);
    }

    public function sendResetLink()
    {
        if (! $this->validate(['email' => 'required|valid_email|max_length[100]'])) {
            return redirect()->back()->withInput()->with('error', 'Please enter a valid email address.');
        }

        // Same answer whether or not the email is registered, and whether or
        // not the rate limit was hit (see PasswordResetService::request()).
        (new PasswordResetService())->request(
            (string) $this->request->getPost('email'),
            $this->request->getIPAddress()
        );

        return redirect()->to('/forgot-password')
            ->with('success', 'If that email is registered, we sent a reset link. Check your inbox - the link works once and expires in 60 minutes.');
    }

    public function resetPassword(string $token = '')
    {
        $inspection = (new PasswordResetService())->inspect($token);

        return view('auth/reset_password', [
            'pageTitle' => 'Reset password',
            'status'    => $inspection['status'],
            'token'     => $token,
        ]);
    }

    public function updateResetPassword(string $token = '')
    {
        $service = new PasswordResetService();

        if ($service->inspect($token)['status'] !== 'valid') {
            return redirect()->to('/reset-password/' . $token);
        }

        $accountRules = (new AccountRegistrationService())->rules();
        $rules = [
            'password'         => $accountRules['password'],
            'password_confirm' => $accountRules['password_confirm'],
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        if (! $service->complete($token, (string) $this->request->getPost('password'))) {
            return redirect()->to('/reset-password/' . $token);
        }

        return redirect()->to('/login')->with('success', 'Your password has been changed. Sign in with your new password.');
    }

    public function changePassword()
    {
        if (! session()->has('user_id')) {
            return redirect()->to('/login')->with('error', 'Please sign in first.');
        }

        return view('auth/change_password');
    }

    public function updatePassword()
    {
        if (! session()->has('user_id')) {
            return redirect()->to('/login')->with('error', 'Please sign in first.');
        }

        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|min_length[8]',
            'new_password_confirm' => 'required|matches[new_password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $userModel = new UserModel();
        $userId    = (int) session('user_id');
        $user      = $userModel->find($userId);

        if (! $user || ! password_verify((string) $this->request->getPost('current_password'), (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Current password is incorrect.');
        }

        $wasTemporary = (int) ($user['must_change_password'] ?? 0) === 1;

        // The new password must actually be new - for a temporary password
        // this is what stops "change it" from meaning "keep it".
        if (password_verify((string) $this->request->getPost('new_password'), (string) $user['password_hash'])) {
            return redirect()->back()->withInput()->with('error', 'Your new password must be different from your current one.');
        }

        $userModel->update($userId, [
            'password_hash' => password_hash((string) $this->request->getPost('new_password'), PASSWORD_DEFAULT),
            'password_changed_at' => date('Y-m-d H:i:s'),
            'must_change_password' => 0,
            'temp_password_expires_at' => null,
        ]);

        session()->set('must_change_password', 0);

        (new NotificationService())->notify($userId, 'Your password was changed successfully.', 'general');
        (new ActivityLogService())->log($userId, 'updated', 'user', $userId, $wasTemporary ? 'Changed temporary password at first sign-in' : 'Changed account password');

        return redirect()->to($this->defaultDashboardForRole((int) session('role_id')))->with('success', 'Password updated successfully.');
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to('/login')->with('success', 'You have been signed out.');
    }

    private function defaultDashboardForRole(int $roleId): string
    {
        return match ($roleId) {
            1 => '/dashboard/admin',
            2 => '/dashboard/branch-admin',
            3 => '/dashboard/staff',
            4 => '/client/dashboard',
            5 => '/dashboard/collector',
            default => '/login',
        };
    }
}
