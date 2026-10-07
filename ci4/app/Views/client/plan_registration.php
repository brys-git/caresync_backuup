<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$beneficiaries = $beneficiaries ?? [];
$program = $program ?? ['name' => 'Damayan Burial Program', 'monthly_fee' => 240.0];
$planId = (int) ($plan_id ?? 0);
$idTypes = $id_types ?? [];
$latestVerification = $latest_verification ?? null;
$errors = session()->getFlashdata('errors') ?? [];

$this->setData(['wizard' => registration_wizard_config('plan_holder', [
    'errors' => $errors,
    'branches' => $branches ?? [],
    'hidden_plan_id' => $planId,
    'program' => $program,
    'plans' => $plans ?? [],
    'plan_holder' => $plan_holder ?? [],
    'user' => $user ?? [],
    'beneficiaries' => $beneficiaries,
    'id_types' => $idTypes,
    'id_result_field_value' => (string) ($latestVerification['verification_id'] ?? ''),
    'selected_id_type' => (string) ($latestVerification['id_type'] ?? ''),
    'id_initial_status' => $latestVerification['verification_status'] ?? null,
])]) ?>
<div class="cs-wizard">
    <?php // Plain error/success flash already handled by layouts/_shell.php's toast. The "errors" list is a validation-array flash, a different shape the shell's toast loop doesn't cover, so _wizard.php renders it separately. ?>
    <?= $this->include('partials/registration/_wizard') ?>
</div>

<?= $this->include('partials/registration/_wizard_scripts') ?>
<?= $this->endSection() ?>
