<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>
<?php
$this->setData(['wizard' => registration_wizard_config('staff', [
    'branch_name' => $branch_name ?? '',
    'selected_branch_id' => session('branch_id'),
    'existing_users' => $existing_users ?? [],
    'program' => $program ?? [],
    'plans' => $plans ?? [],
    'id_types' => $id_types ?? [],
])]) ?>
<div class="cs-wizard">
    <?= $this->include('partials/registration/_wizard') ?>
</div>

<?= $this->include('partials/registration/_wizard_scripts') ?>
<?= $this->endSection() ?>
