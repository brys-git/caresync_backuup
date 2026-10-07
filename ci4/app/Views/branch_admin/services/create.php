<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<div class="mb-3 text-end">
    <a href="<?= base_url('branch-admin/service-package/services') ?>" class="btn btn-ghost btn-sm">Back</a>
</div>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<section class="cs-panel">
    <div class="cs-panel__body">
        <form method="post" action="<?= base_url('branch-admin/services/store') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="service_name" class="form-label">Service Name</label>
                    <input
                        type="text"
                        id="service_name"
                        name="service_name"
                        class="form-control"
                        value="<?= esc(old('service_name')) ?>"
                        required
                    >
                </div>
                <div class="col-md-6">
                    <label for="base_price" class="form-label">Base Price</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="base_price"
                        name="base_price"
                        class="form-control"
                        value="<?= esc(old('base_price')) ?>"
                        required
                    >
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
                        rows="4"
                    ><?= esc(old('description')) ?></textarea>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select" required>
                        <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= base_url('branch-admin/service-package/services') ?>" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Service</button>
            </div>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
