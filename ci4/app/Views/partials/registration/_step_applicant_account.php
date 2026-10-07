<?php
/**
 * Staff-mode Account card (Prompt 7, Step B): email + contact number, and a
 * "Create a new account" / "Link to an existing plan-holder account" radio
 * pair - replaces branch_admin/clients/register.php's own client_account_mode
 * toggle + plan_holders/register.php's registration_mode toggle with one
 * shared version, now available to Staff too (which never had this choice
 * before - Staff's old form only ever created new accounts).
 *
 * $wizard['existing_users']: array of {user_id, first_name, middle_name,
 *   last_name, email, contact_number} - accounts eligible to link (role_id 4,
 *   no plan_holder row yet). Matching/autofill happens in caresync-wizard.js
 * via config.existingUsers, keyed by email.
 */
$existingUsers = $wizard['existing_users'] ?? [];
$mode = (string) old('client_account_mode', 'new');
?>
<div class="cs-panel mb-4">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Account</h2>
    </div>
    <div class="cs-panel__body">
        <div class="border rounded p-3 mb-3">
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="client_account_mode" id="mode_new" value="new" <?= $mode === 'new' ? 'checked' : '' ?>>
                <label class="form-check-label" for="mode_new">Create a new account</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="client_account_mode" id="mode_existing" value="existing" <?= $mode === 'existing' ? 'checked' : '' ?>>
                <label class="form-check-label" for="mode_existing">Link to an existing plan-holder account</label>
            </div>
        </div>
        <p class="text-muted small mb-0" id="accountModeHelp">Fill in the applicant's name and details below, then their email.</p>
    </div>
</div>
