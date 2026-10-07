<?php
/**
 * Shared 5-step registration wizard shell (Prompt 7, Step A) - the exact
 * same markup that used to live only in client/plan_registration.php,
 * extracted so Staff/Branch Admin/System Admin registration pages can
 * render the identical wizard instead of their own plain single-page
 * forms. This step is a pure extraction: client/plan_registration.php's
 * own output must look and behave exactly as it did before.
 *
 * Expects a single $wizard array (via $this->setData() before
 * $this->include('partials/registration/_wizard')):
 *
 *   'action'               form POST url (required)
 *   'mode'                 'self' (client registering themselves) | 'staff'
 *                           (someone registering on someone else's behalf)
 *   'id_verify_endpoint'   endpoint caresync-wizard.js posts ID images to
 *   'id_result_field_id'   id/name of the hidden field the ID verification
 *                           result gets written into
 *   'id_result_field_key'  'verification_id' (self) | 'pending_token' (staff)
 *   'id_result_field_value' pre-fill for that hidden field (self mode only
 *                           - carries a prior verification through reload)
 *   'selected_id_type'     pre-selected Government ID Type, if any
 *   'id_types'              array<string,string> value => label
 *   'show_branch_select'   true for client self-service and System Admin
 *                           (both pick a branch explicitly); false for
 *                           Branch Admin/Staff, who see their own branch as
 *                           a read-only field instead (see 'branch_name')
 *   'branches'             branch list when show_branch_select is true
 *   'selected_branch_id'   pre-selected branch_id
 *   'branch_name'          Branch Admin/Staff's own branch, shown read-only
 *                           when show_branch_select is false
 *   'cancel_url'           where the wizard's Cancel link goes
 *   'show_account_section' true for staff mode - email/contact/link-existing
 *   'existing_users'       array of linkable accounts for staff mode's
 *                           "link to an existing plan-holder account" search
 *   'hidden_plan_id'       self mode's fixed plan id (renders a hidden field)
 *   'plans'                selectable plans for staff mode (renders a <select>)
 *   'program'               ['name' => ..., 'monthly_fee' => ...] display fallback
 *   'plan_holder'           prefill array (empty on create)
 *   'user'                  prefill array for the linked users row (empty on create)
 *   'beneficiaries'         prefill rows
 *   'latest_verification'   self mode's latest Government ID verification row, if any
 *   'certify_label'         certification checkbox text
 *   'submit_label'          submit button text
 *   'errors'                flat array of validation error strings
 */
$mode = (string) ($wizard['mode'] ?? 'self');
$errors = $wizard['errors'] ?? [];
?>
<?php if (! empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $message): ?>
                <li><?= esc((string) $message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- Progress indicator -->
<ol class="wizard-progress mb-4" id="wizardProgress">
    <li class="wizard-progress__item is-active" data-progress-step="1"><span class="wizard-progress__badge">1</span><span class="wizard-progress__label">Applicant Info</span></li>
    <li class="wizard-progress__item" data-progress-step="2"><span class="wizard-progress__badge">2</span><span class="wizard-progress__label">Government ID</span></li>
    <li class="wizard-progress__item" data-progress-step="3"><span class="wizard-progress__badge">3</span><span class="wizard-progress__label">Beneficiaries</span></li>
    <li class="wizard-progress__item" data-progress-step="4"><span class="wizard-progress__badge">4</span><span class="wizard-progress__label">Emergency Contact</span></li>
    <li class="wizard-progress__item" data-progress-step="5"><span class="wizard-progress__badge">5</span><span class="wizard-progress__label">Review</span></li>
</ol>

<form method="post" action="<?= esc((string) ($wizard['action'] ?? ''), 'attr') ?>" enctype="multipart/form-data" id="registrationForm">
    <?= csrf_field() ?>
    <?php if ($mode === 'self' && isset($wizard['hidden_plan_id'])): ?>
        <input type="hidden" name="plan_id" value="<?= (int) $wizard['hidden_plan_id'] ?>">
        <input type="hidden" name="package_id" value="<?= (int) $wizard['hidden_plan_id'] ?>">
    <?php endif; ?>

    <?= $this->include('partials/registration/_step_applicant') ?>
    <?= $this->include('partials/registration/_step_government_id') ?>
    <?= $this->include('partials/registration/_step_beneficiaries') ?>
    <?= $this->include('partials/registration/_step_emergency') ?>
    <?= $this->include('partials/registration/_step_review') ?>
</form>
