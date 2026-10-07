<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<div class="mb-3 text-end">
    <a href="<?= site_url('/branch-admin/packages/edit/' . (int) $package['package_id']) ?>" class="btn btn-primary btn-sm">Edit</a>
    <a href="<?= site_url('/branch-admin/service-package/packages') ?>" class="btn btn-ghost btn-sm">Back</a>
</div>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<section class="cs-panel mb-3">
    <div class="cs-panel__body">
        <div class="row g-3">
            <div class="col-md-6"><strong>Package Name:</strong> <?= esc($package['package_name']) ?></div>
            <div class="col-md-3"><strong>Base Price:</strong> <?= cs_money($package['base_price'] ?? 0) ?></div>
            <div class="col-md-3"><strong>Customizable:</strong> <?= ((int) ($package['is_customizable'] ?? 0) === 1) ? 'Yes' : 'No' ?></div>
            <div class="col-12"><strong>Description:</strong> <?= esc($package['description'] ?? '-') ?></div>
        </div>
    </div>
</section>

<div class="row g-3">
    <div class="col-md-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Services Included</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($package['items'])): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-truck', 'title' => 'No services found']) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead><tr><th>Service Name</th><th>Description</th></tr></thead>
                            <tbody>
                                <?php foreach ($package['items'] as $item): ?>
                                    <tr>
                                        <td><?= esc($item['item_name'] ?? '-') ?></td>
                                        <td><?= esc($item['description'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-md-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Price Versions</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($package['versions'])): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-history', 'title' => 'No versions found']) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead><tr><th class="cs-num">Price</th><th>Effective Date</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($package['versions'] as $version): ?>
                                    <tr>
                                        <td class="cs-num"><?= cs_money($version['price'] ?? 0) ?></td>
                                        <td class="text-nowrap"><?= cs_date($version['effective_date'] ?? null) ?></td>
                                        <td><?= cs_status((string) ($version['status'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<section class="cs-panel mt-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Add Package Service</h2></div>
    <div class="cs-panel__body">
        <form method="post" action="<?= site_url('/branch-admin/packages/add-item/' . (int) $package['package_id']) ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Service Name</label>
                    <select name="service_list_id" class="form-select service-select" required>
                        <option value="">Select service</option>
                        <?php foreach (($service_list ?? []) as $service): ?>
                            <option value="<?= (int) $service['service_list_id'] ?>" data-description="<?= esc($service['description'] ?? '') ?>">
                                <?= esc($service['service_name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control service-description" readonly>
                </div>
                <div class="col-md-2 d-grid align-items-end">
                    <button type="submit" class="btn btn-primary">Add Service</button>
                </div>
            </div>
        </form>
    </div>
</section>
<script>
(function () {
    const serviceSelect = document.querySelector('.service-select');
    const descriptionInput = document.querySelector('.service-description');

    if (!serviceSelect || !descriptionInput) {
        return;
    }

    serviceSelect.addEventListener('change', function () {
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        descriptionInput.value = selectedOption ? (selectedOption.dataset.description || '') : '';
    });
})();
</script>
<?= $this->endSection() ?>
