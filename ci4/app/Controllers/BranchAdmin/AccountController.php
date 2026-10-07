<?php

namespace App\Controllers\BranchAdmin;

use App\Controllers\AccountCreatorController;
use App\Services\AccountRegistrationService;

/**
 * Branch Admin "Create Account": Staff and Client only, always in the Branch
 * Admin's own branch. See AccountCreatorController.
 */
class AccountController extends AccountCreatorController
{
    protected function creatorRole(): int
    {
        return AccountRegistrationService::ROLE_BRANCH_ADMIN;
    }

    protected function layout(): string
    {
        return 'layouts/branch_admin';
    }

    protected function urlPrefix(): string
    {
        return 'branch-admin';
    }

    protected function roleOptions(): array
    {
        return [
            AccountRegistrationService::ROLE_STAFF  => 'Staff',
            AccountRegistrationService::ROLE_CLIENT => 'Client (Plan Holder)',
        ];
    }

    protected function pageSub(): string
    {
        return 'Add a Staff or Client account to your branch.';
    }
}
