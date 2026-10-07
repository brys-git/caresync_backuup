<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are surfaced as toasts by layouts/_shell.php. ?>
<?php if (! empty($branchError)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="ti ti-alert-circle" aria-hidden="true"></i>
        <span><?= esc($branchError) ?></span>
    </div>
<?php else: ?>
<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Create account</h2></div>
    <div class="cs-panel__body">
        <?php if (! $branchSelectable && $branch): ?>
            <p class="cs-muted mb-4">
                <strong>Branch:</strong> <?= esc($branch['branch_name']) ?>
                (accounts you create are added to your branch)
            </p>
        <?php endif; ?>
        <?= view('auth/_account_form', [
            'formAction'         => $formAction,
            'submitLabel'        => 'Create Account',
            'withRole'           => true,
            'roleOptions'        => $roleOptions,
            'branchRoles'        => $branchRoles,
            'branches'           => $branches,
            'branchSelectable'   => $branchSelectable,
            'tempPasswordOption' => true,
        ]) ?>
    </div>
</section>
<?php endif; ?>
<?= $this->endSection() ?>
