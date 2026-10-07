<?php

namespace App\Controllers;

use App\Services\AccountRegistrationService;
use App\Services\ActivityLogService;
use App\Services\LoginDetailsService;

/**
 * Shared "Create Account" page for the staff-side roles. System Admin,
 * Branch Admin and Staff each get a thin subclass that says which roles it may
 * create and whether the branch is chosen or fixed to the creator's own.
 *
 * Everything that matters is enforced here on the server: the role is checked
 * against the subclass's whitelist, a fixed branch is always read from the
 * creator's own record (a posted branch_id is ignored), and the page and the
 * POST both re-check the creator's role on top of the route filter.
 */
abstract class AccountCreatorController extends BaseController
{
    private const NO_BRANCH_MESSAGE = 'Your account is not linked to a branch. Please contact the System Administrator.';

    /** Role id of the user allowed to use this page. */
    abstract protected function creatorRole(): int;

    abstract protected function layout(): string;

    /** URL prefix of this role's area, e.g. 'branch-admin'. */
    abstract protected function urlPrefix(): string;

    /** @return array<int,string> role_id => label this creator may create */
    abstract protected function roleOptions(): array;

    /** True when the creator picks the branch (System Admin only). */
    protected function branchSelectable(): bool
    {
        return false;
    }

    /** Whether a Client created here is placed in the branch. */
    protected function clientGetsBranch(): bool
    {
        return true;
    }

    protected function pageSub(): string
    {
        return 'Add an account. Login details can be generated for you.';
    }

    public function create()
    {
        if ($denied = $this->denyUnlessCreator()) {
            return $denied;
        }

        $branch = $this->branchSelectable() ? null : $this->ownBranch();
        $branchError = (! $this->branchSelectable() && $branch === null) ? self::NO_BRANCH_MESSAGE : null;

        return view('accounts/create', [
            'role_layout'      => $this->layout(),
            'page_title'       => 'Create account',
            'page_sub'         => $this->pageSub(),
            'crumbs'           => [['label' => 'Accounts'], ['label' => 'Create account']],
            'formAction'       => site_url($this->urlPrefix() . '/create-account'),
            'roleOptions'      => $this->roleOptions(),
            'branchRoles'      => AccountRegistrationService::BRANCH_ROLES,
            'branchSelectable' => $this->branchSelectable(),
            'branches'         => $this->branchSelectable() ? $this->activeBranches() : [],
            'branch'           => $branch,
            'branchError'      => $branchError,
        ]);
    }

    public function store()
    {
        if ($denied = $this->denyUnlessCreator()) {
            return $denied;
        }

        $service     = new AccountRegistrationService();
        $roleOptions = $this->roleOptions();
        $temporary   = (string) $this->request->getPost('use_temp_password') === '1';

        $branchId = null;
        $branch   = null;

        if (! $this->branchSelectable()) {
            $branch = $this->ownBranch();
            if ($branch === null) {
                return redirect()->back()->withInput()->with('error', self::NO_BRANCH_MESSAGE);
            }
            $branchId = (int) $branch['branch_id'];
        }

        $rules = $service->rules($temporary) + [
            'role_id' => 'required|in_list[' . implode(',', array_keys($roleOptions)) . ']',
        ];

        $messages = [
            'role_id' => ['in_list' => 'You can only create ' . implode(' or ', array_map(
                static fn (string $label) => preg_replace('/ \(.*\)$/', '', $label),
                array_values($roleOptions)
            )) . ' accounts.'],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('error', implode(' ', $this->validator->getErrors()));
        }

        $roleId = (int) $this->request->getPost('role_id');

        if ($this->branchSelectable() && in_array($roleId, AccountRegistrationService::BRANCH_ROLES, true)) {
            $branchId = (int) $this->request->getPost('branch_id');
            $branchOk = $branchId > 0 && db_connect()->table('branches')
                ->where('branch_id', $branchId)
                ->where('status', 'active')
                ->countAllResults() === 1;

            if (! $branchOk) {
                return redirect()->back()->withInput()->with('error', 'Please select an active branch for this role.');
            }
        }

        try {
            $result = $service->createByAdmin([
                'username'          => $this->request->getPost('username'),
                'email'             => $this->request->getPost('email'),
                'first_name'        => $this->request->getPost('first_name'),
                'last_name'         => $this->request->getPost('last_name'),
                'contact_number'    => $this->request->getPost('contact_number'),
                'unique_identifier' => $this->request->getPost('unique_identifier'),
                'password'          => $this->request->getPost('password'),
            ], $roleId, $branchId, $roleOptions, [
                'clientGetsBranch'  => $this->clientGetsBranch(),
                'temporaryPassword' => $temporary,
            ]);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $fullName  = trim((string) $this->request->getPost('first_name') . ' ' . (string) $this->request->getPost('last_name'));
        $roleLabel = $roleId === AccountRegistrationService::ROLE_CLIENT ? 'Client' : preg_replace('/ \(.*\)$/', '', $roleOptions[$roleId]);

        $actorId = (int) session('user_id');
        $logs    = new ActivityLogService();

        $logs->log(
            $actorId,
            'created',
            'user',
            $result['user_id'],
            "Created {$roleLabel} account for {$fullName}" . ($temporary ? ' with a temporary password' : ''),
            null,
            ['role_id' => $roleId, 'branch_id' => $branchId, 'temporary_password' => $temporary]
        );

        $success = "{$roleLabel} account for {$fullName} has been created.";

        if (! $temporary) {
            return redirect()->to(site_url($this->urlPrefix() . '/create-account'))->with('success', $success);
        }

        // Temporary password: email it (when there is an address) and hand the
        // creator the one-time screen, so details can also go out by SMS or
        // in person.
        $loginDetails = new LoginDetailsService();
        $details = $loginDetails->details(
            [
                'user_id'    => $result['user_id'],
                'username'   => trim((string) $this->request->getPost('username')),
                'email'      => trim((string) $this->request->getPost('email')),
                'first_name' => trim((string) $this->request->getPost('first_name')),
                'last_name'  => trim((string) $this->request->getPost('last_name')),
                'role_name'  => $roleLabel,
                'branch_name' => $branch['branch_name'] ?? $this->branchNameFor($branchId),
            ],
            $result['temp_password'],
            $result['temp_expires_at']
        );
        $details['emailed']     = $loginDetails->email($details);
        $details['back_url']    = site_url($this->urlPrefix() . '/create-account');
        $details['back_label']  = 'Create another account';

        return redirect()->to(site_url('account-login-details'))
            ->with('success', $success)
            ->with('login_details', $details);
    }

    /**
     * Route filter already blocks other roles; this is the second,
     * in-controller check.
     */
    private function denyUnlessCreator()
    {
        if ((int) session('role_id') !== $this->creatorRole()) {
            return redirect()->to('/unauthorized');
        }

        return null;
    }

    /** The creator's branch, read from their user record (never the request). */
    private function ownBranch(): ?array
    {
        $row = db_connect()->table('users u')
            ->select('b.branch_id, b.branch_name')
            ->join('branches b', 'b.branch_id = u.branch_id', 'inner')
            ->where('u.user_id', (int) session('user_id'))
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    private function branchNameFor(?int $branchId): string
    {
        if (! $branchId) {
            return '';
        }

        $row = db_connect()->table('branches')->select('branch_name')->where('branch_id', $branchId)->get()->getRowArray();

        return (string) ($row['branch_name'] ?? '');
    }

    private function activeBranches(): array
    {
        return db_connect()->table('branches')
            ->select('branch_id, branch_name')
            ->where('status', 'active')
            ->orderBy('branch_name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
