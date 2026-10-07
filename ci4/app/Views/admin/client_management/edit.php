<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>
<?php
$this->setData([
    'client' => $client,
    'beneficiaries' => $beneficiaries ?? [],
    'update_action' => base_url('admin/client-management/update/' . $client['plan_holder_id']),
    'cancel_url' => base_url('admin/client-management/view/' . $client['plan_holder_id']),
]) ?>
<?= $this->include('partials/registration/_edit_form') ?>
<?= $this->include('partials/registration/_wizard_scripts') ?>
<?= $this->endSection() ?>
