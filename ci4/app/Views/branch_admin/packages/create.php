<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<div class="mb-3 text-end">
    <a href="<?= base_url('branch-admin/service-package/packages') ?>" class="btn btn-ghost btn-sm">Back</a>
</div>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<section class="cs-panel">
    <div class="cs-panel__body">
        <form method="post" action="<?= base_url('branch-admin/packages/store') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="package_name" class="form-label">Package Name</label>
                    <input
                        type="text"
                        id="package_name"
                        name="package_name"
                        class="form-control"
                        value="<?= esc(old('package_name')) ?>"
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
                <div class="col-md-6">
                    <label for="is_customizable" class="form-label">Customizable</label>
                    <select id="is_customizable" name="is_customizable" class="form-select">
                        <option value="1" <?= old('is_customizable', '1') === '1' ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= old('is_customizable') === '0' ? 'selected' : '' ?>>No</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="initial_effective_date" class="form-label">Price Effective Date</label>
                    <input
                        type="date"
                        id="initial_effective_date"
                        name="initial_effective_date"
                        class="form-control"
                        value="<?= esc(old('initial_effective_date', date('Y-m-d'))) ?>"
                        required
                    >
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
                        rows="3"
                    ><?= esc(old('description')) ?></textarea>
                </div>
            </div>

            <hr class="cs-hairline my-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h6 mb-0">Package Services</h2>
                <button type="button" class="btn btn-outline-primary btn-sm" id="addItemRow">Add Service</button>
            </div>
            <div id="packageItems" class="d-grid gap-2">
                <div class="row g-2 align-items-start item-row">
                    <div class="col-md-5">
                        <select name="service_list_id[]" class="form-select service-select" required>
                            <option value="">Select service</option>
                            <?php foreach (($service_list ?? []) as $service): ?>
                                <option value="<?= (int) $service['service_list_id'] ?>" data-description="<?= esc($service['description'] ?? '') ?>">
                                    <?= esc($service['service_name'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" name="item_description[]" class="form-control service-description" placeholder="Description" readonly>
                    </div>
                    <div class="col-md-1 d-grid">
                        <button type="button" class="btn btn-danger remove-item">-</button>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= base_url('branch-admin/service-package/packages') ?>" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Package</button>
            </div>
        </form>
    </div>
</section>
<script>
(function () {
    const container = document.getElementById('packageItems');
    const addButton = document.getElementById('addItemRow');

    addButton.addEventListener('click', function () {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-start item-row';
        row.innerHTML = `
            <div class="col-md-5">
                <select name="service_list_id[]" class="form-select service-select" required>
                    <option value="">Select service</option>
                    <?php foreach (($service_list ?? []) as $service): ?>
                        <option value="<?= (int) $service['service_list_id'] ?>" data-description="<?= esc($service['description'] ?? '') ?>"><?= esc($service['service_name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <input type="text" name="item_description[]" class="form-control service-description" placeholder="Description" readonly>
            </div>
            <div class="col-md-1 d-grid">
                <button type="button" class="btn btn-danger remove-item">-</button>
            </div>
        `;
        container.appendChild(row);
    });

    container.addEventListener('change', function (event) {
        if (!event.target.classList.contains('service-select')) {
            return;
        }

        const selectedOption = event.target.options[event.target.selectedIndex];
        const row = event.target.closest('.item-row');
        const descriptionInput = row.querySelector('.service-description');
        descriptionInput.value = selectedOption ? (selectedOption.dataset.description || '') : '';
    });

    container.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-item')) {
            return;
        }

        const rows = container.querySelectorAll('.item-row');
        if (rows.length === 1) {
            rows[0].querySelector('select[name="service_list_id[]"]').value = '';
            rows[0].querySelector('input[name="item_description[]"]').value = '';
            return;
        }

        event.target.closest('.item-row').remove();
    });
})();
</script>
<?= $this->endSection() ?>
