<?php

namespace App\Controllers\Admin;

use App\Controllers\AccountCreatorController;
use App\Services\AccountRegistrationService;

/**
 * System Admin "Create Account": Branch Admin, Staff or Client, with a
 * Branch dropdown for the first two. See AccountCreatorController.
 */
class AccountController extends AccountCreatorController
{
    protected function creatorRole(): int
    {
        return AccountRegistrationService::ROLE_ADMIN;
    }

    protected function layout(): string
    {
        return 'layouts/admin';
    }

    protected function urlPrefix(): string
    {
        return 'admin';
    }

    protected function roleOptions(): array
    {
        return AccountRegistrationService::ADMIN_CREATABLE_ROLES;
    }

    protected function branchSelectable(): bool
    {
        return true;
    }

    // The System Admin page leaves clients unassigned, like public sign-up.
    protected function clientGetsBranch(): bool
    {
        return false;
    }

    protected function pageSub(): string
    {
        return 'Add a Branch Admin, Staff, or Client account.';
    }
}
