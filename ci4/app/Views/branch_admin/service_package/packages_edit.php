<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<div class="mb-3 text-end">
    <a href="<?= site_url('/branch-admin/packages/view/' . (int) $package['package_id']) ?>" class="btn btn-ghost btn-sm">Back</a>
</div>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<section class="cs-panel">
    <div class="cs-panel__body">
        <form method="post" action="<?= site_url('/branch-admin/packages/update/' . (int) $package['package_id']) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Package Name</label>
                    <input type="text" name="package_name" class="form-control" value="<?= esc(old('package_name', $package['package_name'])) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Customizable</label>
                    <select name="is_customizable" class="form-select" required>
                        <option value="1" <?= (string) old('is_customizable', (string) $package['is_customizable']) === '1' ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= (string) old('is_customizable', (string) $package['is_customizable']) === '0' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Current Base Price</label>
                    <input type="text" class="form-control" value="<?= esc(number_format((float) ($package['base_price'] ?? 0), 2)) ?>" readonly>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= esc(old('description', $package['description'])) ?></textarea>
                </div>
            </div>

            <hr class="cs-hairline">
            <h6>Add New Price Version</h6>
            <p class="text-muted small">Existing price versions are preserved. Adding a value here creates a new record in package_versions.</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">New Price</label>
                    <input type="number" name="new_price" step="0.01" min="0.01" class="form-control" value="<?= esc(old('new_price')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Effective Date</label>
                    <input type="date" name="effective_date" class="form-control" value="<?= esc(old('effective_date', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Version Status</label>
                    <select name="version_status" class="form-select">
                        <option value="active" <?= old('version_status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= old('version_status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <hr class="cs-hairline">
            <h6>Add New Package Services</h6>
            <p class="text-muted small">Existing items stay unchanged. Add new rows only when needed.</p>
            <div id="newItemRows">
                <div class="row g-2 mb-2 new-item-row">
                    <div class="col-md-5">
                        <select name="new_service_list_id[]" class="form-select service-select" required>
                            <option value="">Select service</option>
                            <?php foreach (($service_list ?? []) as $service): ?>
                                <option value="<?= (int) $service['service_list_id'] ?>" data-description="<?= esc($service['description'] ?? '') ?>"><?= esc($service['service_name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><input type="text" name="new_item_description[]" class="form-control service-description" placeholder="Description" readonly></div>
                    <div class="col-md-1 d-grid"><button type="button" class="btn btn-danger remove-new-item">-</button></div>
                </div>
            </div>
            <button type="button" id="addNewItem" class="btn btn-sm btn-outline-primary">Add Service</button>

            <div class="mt-3 d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Update Package</button>
            </div>
        </form>
    </div>
</section>
<script>
    (function () {
        const container = document.getElementById('newItemRows');
        const addBtn = document.getElementById('addNewItem');

        addBtn.addEventListener('click', function () {
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 new-item-row';
            row.innerHTML = '<div class="col-md-5"><select name="new_service_list_id[]" class="form-select service-select" required><option value="">Select service</option><?php foreach (($service_list ?? []) as $service): ?><option value="<?= (int) $service['service_list_id'] ?>" data-description="<?= esc($service['description'] ?? '') ?>"><?= esc($service['service_name'] ?? '') ?></option><?php endforeach; ?></select></div>' +
                '<div class="col-md-6"><input type="text" name="new_item_description[]" class="form-control service-description" placeholder="Description" readonly></div>' +
                '<div class="col-md-1 d-grid"><button type="button" class="btn btn-danger remove-new-item">-</button></div>';
            container.appendChild(row);
        });

        container.addEventListener('change', function (event) {
            if (!event.target.classList.contains('service-select')) {
                return;
            }

            const selectedOption = event.target.options[event.target.selectedIndex];
            const row = event.target.closest('.new-item-row');
            const descriptionInput = row.querySelector('.service-description');
            descriptionInput.value = selectedOption ? (selectedOption.dataset.description || '') : '';
        });

        container.addEventListener('click', function (event) {
            if (!event.target.classList.contains('remove-new-item')) {
                return;
            }

            const rows = container.querySelectorAll('.new-item-row');
            if (rows.length === 1) {
                rows[0].querySelector('select[name="new_service_list_id[]"]').value = '';
                rows[0].querySelector('input[name="new_item_description[]"]').value = '';
                return;
            }

            event.target.closest('.new-item-row').remove();
        });
    })();
</script>
<?= $this->endSection() ?>
