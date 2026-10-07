<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<div class="mb-3 text-end">
    <a href="<?= site_url('/branch-admin/services/view/' . (int) ($service['service_list_id'] ?? 0)) ?>" class="btn btn-ghost btn-sm">Back</a>
</div>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<section class="cs-panel">
    <div class="cs-panel__body">
        <form method="post" action="<?= site_url('/branch-admin/services/update/' . (int) ($service['service_list_id'] ?? 0)) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Service Name</label>
                    <input type="text" name="service_name" class="form-control" value="<?= esc(old('service_name', $service['service_name'] ?? '')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Base Price</label>
                    <input type="number" step="0.01" min="0" name="base_price" class="form-control" value="<?= esc(old('base_price', (string) ($service['base_price'] ?? '0'))) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="active" <?= old('status', (string) ($service['status'] ?? 'active')) === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= old('status', (string) ($service['status'] ?? 'active')) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4"><?= esc(old('description', $service['description'] ?? '')) ?></textarea>
                </div>
            </div>
            <div class="mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Update Service</button>
            </div>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
