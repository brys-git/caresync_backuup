<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$errors = $errors ?? [];
$plan = $plan ?? null;
$package = $package ?? null;
$packages = $packages ?? [];
$isEdit = $program_id !== null;
$actionUrl = $isEdit ? base_url('plan-builder/' . (int) $program_id . '/update') : base_url('plan-builder/store');
$selectedPackageId = (string) old('package_id', (string) ($plan['package_id'] ?? ''));
?>

<a class="text-decoration-none text-muted mb-3 d-inline-block" href="<?= base_url($isEdit ? 'plan-builder/' . (int) $program_id : 'plan-builder') ?>">
    <i class="ti ti-arrow-left me-1"></i> <?= $isEdit ? 'Back to Plan' : 'Back to Plan Builder' ?>
</a>

<?php if (! empty($needs_approval)): ?>
    <?= view('components/alert', [
        'type' => 'info',
        'dismissible' => false,
        'message' => 'This plan will be submitted to System Admin for approval - it will not appear at registration or be selectable until approved.',
    ]) ?>
<?php endif; ?>

<form method="post" action="<?= $actionUrl ?>" id="planBuilderForm">
    <?= csrf_field() ?>

    <section class="cs-panel mb-3">
        <div class="cs-panel__head"><h2 class="cs-panel__title">Plan details</h2></div>
        <div class="cs-panel__body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="plan_name">Plan Name</label>
                    <input id="plan_name" name="plan_name" class="form-control <?= isset($errors['plan_name']) ? 'is-invalid' : '' ?>" maxlength="150" required
                        value="<?= esc(old('plan_name', (string) ($plan['program_name'] ?? ''))) ?>">
                    <?php if (isset($errors['plan_name'])): ?><div class="invalid-feedback"><?= esc($errors['plan_name']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="plan_price">Plan Price</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input id="plan_price" name="plan_price" type="number" min="0.01" step="0.01" class="form-control <?= isset($errors['plan_price']) ? 'is-invalid' : '' ?>" required
                            value="<?= esc(old('plan_price', (string) ($plan['plan_price'] ?? '14500.00'))) ?>">
                    </div>
                    <?php if (isset($errors['plan_price'])): ?><div class="invalid-feedback d-block"><?= esc($errors['plan_price']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="monthly_fee">Monthly Contribution</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input id="monthly_fee" name="monthly_fee" type="number" min="0.01" step="0.01" class="form-control <?= isset($errors['monthly_fee']) ? 'is-invalid' : '' ?>" required
                            value="<?= esc(old('monthly_fee', (string) ($plan['monthly_fee'] ?? '240.00'))) ?>">
                    </div>
                    <?php if (isset($errors['monthly_fee'])): ?><div class="invalid-feedback d-block"><?= esc($errors['monthly_fee']) ?></div><?php endif; ?>
                </div>

                <div class="col-md-3">
                    <label class="form-label d-block">Term</label>
                    <strong id="termDisplay" class="fs-5">-</strong>
                    <div class="form-text">Calculated from plan price ÷ monthly contribution.</div>
                </div>
                <div class="col-md-9">
                    <label class="form-label" for="description">Description</label>
                    <input id="description" name="description" class="form-control" maxlength="500"
                        value="<?= esc(old('description', (string) ($plan['description'] ?? ''))) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label d-block">Status</label>
                    <?php $isActive = (int) old('is_active', (string) ($plan['is_active'] ?? 1)) === 1; ?>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active_switch" <?= $isActive ? 'checked' : '' ?>
                            onchange="document.getElementById('is_active').value = this.checked ? '1' : '0';">
                        <label class="form-check-label" for="is_active_switch">Active</label>
                    </div>
                    <input type="hidden" id="is_active" name="is_active" value="<?= $isActive ? '1' : '0' ?>">
                </div>
            </div>
        </div>
    </section>

    <section class="cs-panel mb-3">
        <div class="cs-panel__head"><h2 class="cs-panel__title">Entitled package</h2></div>
        <div class="cs-panel__body">
            <?php if (empty($packages)): ?>
                <?= view('components/alert', [
                    'type' => 'info',
                    'dismissible' => false,
                    'message' => 'No packages exist yet in the Services & Packages catalog. Create one there first, then come back to build this plan.',
                ]) ?>
                <a href="<?= base_url((int) session('role_id') === 2 ? 'branch-admin/packages/create' : 'admin/service-offer?tab=packages') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="ti ti-plus me-1"></i> Create a package
                </a>
            <?php else: ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="package_id">Package</label>
                        <select id="package_id" name="package_id" class="form-select <?= isset($errors['package_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">Select a package Kaagapay offers&hellip;</option>
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?= (int) $pkg['package_id'] ?>" <?= $selectedPackageId === (string) $pkg['package_id'] ? 'selected' : '' ?>>
                                    <?= esc((string) $pkg['package_name']) ?> (<?= cs_peso($pkg['base_price'] ?? 0) ?>)<?= (int) ($pkg['is_available'] ?? 1) === 0 ? ' - unavailable' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['package_id'])): ?><div class="invalid-feedback"><?= esc($errors['package_id']) ?></div><?php endif; ?>
                        <div class="form-text">
                            Managed on the <a href="<?= base_url((int) session('role_id') === 2 ? 'branch-admin/service-package' : 'admin/service-offer') ?>">Services &amp; Packages</a> page - its name, price, and inclusions aren't edited here.
                        </div>
                    </div>
                </div>
                <div id="packageInclusionsPreview" class="mt-3"></div>
            <?php endif; ?>
        </div>
    </section>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary" <?= empty($packages) ? 'disabled' : '' ?>><?= $isEdit ? 'Save Changes' : 'Create Plan' ?></button>
        <a href="<?= base_url($isEdit ? 'plan-builder/' . (int) $program_id : 'plan-builder') ?>" class="btn btn-ghost">Cancel</a>
    </div>
</form>

<script>
(function () {
    function esc(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    const packageSelect = document.getElementById('package_id');
    const preview = document.getElementById('packageInclusionsPreview');

    if (packageSelect && preview) {
        function loadPreview() {
            const id = packageSelect.value;
            if (!id) {
                preview.innerHTML = '';
                return;
            }
            preview.innerHTML = '<span class="cs-muted small"><i class="ti ti-loader-2 me-1"></i>Loading inclusions&hellip;</span>';
            fetch('<?= base_url('plan-builder/package-inclusions') ?>?package_id=' + encodeURIComponent(id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.items || !data.items.length) {
                        preview.innerHTML = '<span class="cs-muted small">No inclusions listed for this package.</span>';
                        return;
                    }
                    const list = data.items.map(function (item) {
                        return '<li><strong>' + esc(item.name) + '</strong>' + (item.description ? ' - ' + esc(item.description) : '') + '</li>';
                    }).join('');
                    preview.innerHTML = '<div class="cs-muted small mb-1">Inclusions:</div><ul class="mb-0 small">' + list + '</ul>';
                })
                .catch(function () { preview.innerHTML = ''; });
        }

        packageSelect.addEventListener('change', loadPreview);
        if (packageSelect.value) {
            loadPreview();
        }
    }

    const priceInput = document.getElementById('plan_price');
    const feeInput = document.getElementById('monthly_fee');
    const termDisplay = document.getElementById('termDisplay');

    function updateTerm() {
        const price = parseFloat(priceInput.value || '0');
        const fee = parseFloat(feeInput.value || '0');
        if (price > 0 && fee > 0) {
            termDisplay.textContent = Math.ceil(price / fee) + ' months';
        } else {
            termDisplay.textContent = '-';
        }
    }

    priceInput.addEventListener('input', updateTerm);
    feeInput.addEventListener('input', updateTerm);
    updateTerm();
})();
</script>
<?= $this->endSection() ?>
