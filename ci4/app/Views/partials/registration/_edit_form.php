<?php
/**
 * Shared plan-holder edit form (Part A, item 8) - Applicant Info,
 * Beneficiaries, and Emergency Contact, reusing the same step partials as
 * registration (partials/registration/_step_*.php) in a flat "edit"
 * context: no Government ID step, no Review step, no wizard stepper -
 * every section renders inline, already visible. Built once so the three
 * near-identical admin/branch_admin/staff edit.php files stay in sync.
 *
 * Beneficiary rows render prefilled from $beneficiaries but are NOT saved
 * on submit - none of the 3 edit controllers' update() methods process a
 * 'beneficiaries' POST key, and this task's ground rules restrict
 * controllers to changing only what they pass to views, not what
 * update() persists. Flagged here rather than silently "fixed".
 *
 * Expects (via $this->setData() before $this->include()):
 *   $client         flat plan_holder+user+branch array (ClientService::getClientDetails())
 *   $beneficiaries  prefill rows for the Beneficiaries table
 *   $update_action  form POST url
 *   $cancel_url     Cancel button / link target
 */
$client = $client ?? [];
$beneficiaries = $beneficiaries ?? [];

$this->setData(['wizard' => [
    'context' => 'edit',
    'plan_holder' => $client,
    'user' => $client,
    'beneficiaries' => $beneficiaries,
    'show_account_section' => false,
    'show_branch_select' => false,
]]);
?>
<form method="post" action="<?= esc((string) $update_action, 'attr') ?>">
    <?= csrf_field() ?>

    <div class="cs-panel mb-4">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">Identifiers</h2>
        </div>
        <div class="cs-panel__body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Client ID</label>
                    <input type="text" class="form-control" value="<?= esc((string) ($client['plan_holder_id'] ?? '')) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Branch</label>
                    <input type="text" class="form-control" value="<?= esc((string) ($client['branch_name'] ?? 'N/A')) ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Unique Identifier</label>
                    <input type="text" class="form-control" value="<?= esc((string) ($client['unique_identifier'] ?? '')) ?>" readonly>
                </div>
            </div>
        </div>
    </div>

    <?= $this->include('partials/registration/_step_applicant') ?>
    <?= $this->include('partials/registration/_step_beneficiaries') ?>
    <?= $this->include('partials/registration/_step_emergency') ?>

    <div class="cs-panel mt-4">
        <div class="cs-panel__foot d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0" for="status">Status</label>
                <select id="status" name="status" class="form-select form-select-sm w-auto" required>
                    <option value="active" <?= old('status', (string) ($client['plan_holder_status'] ?? 'active')) === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= old('status', (string) ($client['plan_holder_status'] ?? 'active')) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= esc((string) $cancel_url, 'attr') ?>" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Update plan holder</button>
            </div>
        </div>
    </div>
</form>
