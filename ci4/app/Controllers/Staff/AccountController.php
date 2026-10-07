<?php

namespace App\Controllers\Staff;

use App\Controllers\AccountCreatorController;
use App\Services\AccountRegistrationService;

/**
 * Staff "Create Account": Client accounts only, always in the Staff
 * member's own branch (no role choice, no branch choice).
 * See AccountCreatorController.
 */
class AccountController extends AccountCreatorController
{
    protected function creatorRole(): int
    {
        return AccountRegistrationService::ROLE_STAFF;
    }

    protected function layout(): string
    {
        return 'layouts/staff';
    }

    protected function urlPrefix(): string
    {
        return 'staff';
    }

    protected function roleOptions(): array
    {
        return [AccountRegistrationService::ROLE_CLIENT => 'Client (Plan Holder)'];
    }

    protected function pageSub(): string
    {
        return 'Add a Client account to your branch.';
    }
}
