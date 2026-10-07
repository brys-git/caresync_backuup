<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'services' ? 'active' : '' ?>" href="<?= site_url('/branch-admin/service-package/services') ?>">Services</a></li>
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'packages' ? 'active' : '' ?>" href="<?= site_url('/branch-admin/service-package/packages') ?>">Packages</a></li>
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'requests' ? 'active' : '' ?>" href="<?= site_url('/branch-admin/service-package/requests') ?>">Claims</a></li>
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'ongoing' ? 'active' : '' ?>" href="<?= site_url('/branch-admin/service-package/ongoing') ?>">Ongoing Services</a></li>
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'schedule' ? 'active' : '' ?>" href="<?= site_url('/branch-admin/service-package/schedule') ?>">Schedule Service</a></li>
</ul>

<?php if (($active_tab ?? '') === 'services'): ?>
    <div class="d-flex justify-content-end mb-3">
        <a href="<?= site_url('/branch-admin/services/create') ?>" class="btn btn-primary">Create Service</a>
    </div>
    <section class="cs-panel">
        <div class="cs-panel__body cs-panel__body--flush">
            <?php $rows = $services ?? []; ?>
            <?php if (empty($rows)): ?>
                <?= view('components/empty_state', ['icon' => 'ti-truck', 'title' => 'No services found']) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Service Name</th>
                                <th>Description</th>
                                <th class="cs-num">Base Price</th>
                                <th>Status</th>
                                <th class="cs-table__actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $serviceId = (int) ($row['service_list_id'] ?? ($row['offer_id'] ?? ($row['id'] ?? 0))); ?>
                                <tr>
                                    <td><?= esc($row['service_name'] ?? '-') ?></td>
                                    <td><?= esc($row['description'] ?? '-') ?></td>
                                    <td class="cs-num"><?= cs_money($row['base_price'] ?? 0) ?></td>
                                    <td><?= cs_status((string) ($row['status'] ?? '')) ?></td>
                                    <td class="cs-table__actions">
                                        <a href="<?= site_url('/branch-admin/services/view/' . $serviceId) ?>" class="btn btn-sm btn-ghost">View</a>
                                        <a href="<?= site_url('/branch-admin/services/edit/' . $serviceId) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (($active_tab ?? '') === 'packages'): ?>
    <div class="d-flex justify-content-end mb-3">
        <a href="<?= site_url('/branch-admin/packages/create') ?>" class="btn btn-primary">Create Package</a>
    </div>
    <section class="cs-panel">
        <div class="cs-panel__body cs-panel__body--flush">
            <?php $rows = $packages ?? []; ?>
            <?php if (empty($rows)): ?>
                <?= view('components/empty_state', ['icon' => 'ti-package', 'title' => 'No packages found']) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Package Name</th>
                                <th>Description</th>
                                <th class="cs-num">Base Price</th>
                                <th>Customizable</th>
                                <th class="cs-table__actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= esc($row['package_name'] ?? '-') ?></td>
                                    <td><?= esc($row['description'] ?? '-') ?></td>
                                    <td class="cs-num"><?= cs_money($row['base_price'] ?? 0) ?></td>
                                    <td><?= ((int) ($row['is_customizable'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
                                    <td class="cs-table__actions">
                                        <a href="<?= site_url('/branch-admin/packages/view/' . (int) $row['package_id']) ?>" class="btn btn-sm btn-ghost">View</a>
                                        <a href="<?= site_url('/branch-admin/packages/edit/' . (int) $row['package_id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (($active_tab ?? '') === 'schedule'): ?>
    <section class="cs-panel">
        <div class="cs-panel__head"><h2 class="cs-panel__title">Schedule Service</h2></div>
        <div class="cs-panel__body">
            <form method="post" action="<?= site_url('/branch-admin/service-package/schedule/store') ?>">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Plan Holder</label>
                        <select name="plan_holder_id" class="form-select" required>
                            <option value="">Select plan holder</option>
                            <?php foreach (($plan_holders ?? []) as $holder): ?>
                                <option value="<?= (int) $holder['plan_holder_id'] ?>" <?= (int) old('plan_holder_id') === (int) $holder['plan_holder_id'] ? 'selected' : '' ?>>
                                    <?= esc(($holder['first_name'] ?? '') . ' ' . ($holder['last_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Package</label>
                        <select id="schedule_package_id" name="package_id" class="form-select" required>
                            <option value="">Select package</option>
                            <?php foreach (($packages ?? []) as $package): ?>
                                <option value="<?= (int) $package['package_id'] ?>" <?= (int) old('package_id') === (int) $package['package_id'] ? 'selected' : '' ?>>
                                    <?= esc($package['package_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Service Definition (Optional)</label>
                        <select id="schedule_service_list_id" name="service_list_id" class="form-select">
                            <option value="">No specific service</option>
                            <?php foreach (($service_list ?? []) as $service): ?>
                                <option value="<?= (int) $service['service_list_id'] ?>" data-label="<?= esc($service['service_name']) ?>" <?= (int) old('service_list_id') === (int) $service['service_list_id'] ? 'selected' : '' ?>>
                                    <?= esc($service['service_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="schedule_service_help" class="text-muted">Pick package first to narrow service suggestions.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Service Date</label>
                        <input type="date" name="service_date" class="form-control" value="<?= esc(old('service_date', date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Service Time</label>
                        <input type="time" name="service_time" class="form-control" value="<?= esc(old('service_time')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Burial Location</label>
                        <input type="text" name="burial_location" class="form-control" value="<?= esc(old('burial_location')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= esc(old('notes')) ?></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Schedule Service</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php if (($active_tab ?? '') === 'requests'): ?>
    <?= view('partials/search_bar', [
        'action'      => site_url('/branch-admin/service-package/requests'),
        'term'        => $search ?? '',
        'placeholder' => 'Plan holder, plan number, or package / service claimed',
    ]) ?>
    <section class="cs-panel">
        <div class="cs-panel__body cs-panel__body--flush">
            <?php $rows = $requests ?? []; ?>
            <?php if (empty($rows)): ?>
                <?= view('components/empty_state', ['icon' => 'ti-clipboard-list', 'title' => ! empty($search) ? 'No claims match "' . $search . '"' : 'No claims found', 'text' => ! empty($search) ? 'Check the spelling or try fewer words, then search again - or clear the search.' : '']) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Plan Holder</th>
                                <th>Claiming</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th class="cs-table__actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= esc(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                                    <td><?= esc($row['claim_label'] ?? $row['package_name'] ?? $row['service_name'] ?? '-') ?></td>
                                    <td><?= cs_status((string) ($row['status'] ?? '')) ?></td>
                                    <td class="text-nowrap"><?= cs_date($row['created_at'] ?? null) ?></td>
                                    <td class="cs-table__actions">
                                        <a href="<?= site_url('/branch-admin/service-package/requests/' . (int) $row['application_id']) ?>" class="btn btn-sm btn-primary me-1">View</a>
                                        <?php if (($row['status'] ?? '') === 'pending'): ?>
                                            <form action="<?= site_url('/branch-admin/service-package/requests/approve/' . (int) $row['application_id']) ?>" method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                                            </form>
                                            <form action="<?= site_url('/branch-admin/service-package/requests/reject/' . (int) $row['application_id']) ?>" method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="text" name="rejection_reason" class="form-control form-control-sm d-inline-block" style="max-width: 160px;" placeholder="Reason (optional)">
                                                <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (($active_tab ?? '') === 'ongoing'): ?>
    <section class="cs-panel">
        <div class="cs-panel__body cs-panel__body--flush">
            <?php $rows = $ongoing_services ?? []; ?>
            <?php if (empty($rows)): ?>
                <?= view('components/empty_state', ['icon' => 'ti-truck', 'title' => 'No ongoing services found']) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Service</th>
                                <th>Service Date</th>
                                <th>Package</th>
                                <th>Status</th>
                                <th>Assigned Staff</th>
                                <th class="cs-table__actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= esc(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                                    <td><?= esc($row['service_name'] ?? '-') ?></td>
                                    <td class="text-nowrap"><?= cs_date($row['service_date'] ?? null) ?></td>
                                    <td><?= esc($row['package_name'] ?? '-') ?></td>
                                    <td><?= cs_status((string) ($row['status'] ?? '')) ?></td>
                                    <td><?= esc(trim((string) (($row['staff_first_name'] ?? '') . ' ' . ($row['staff_last_name'] ?? '')))) ?: '-' ?></td>
                                    <td class="cs-table__actions">
                                        <form action="<?= site_url('/branch-admin/service-package/ongoing/update-status/' . (int) $row['service_id']) ?>" method="post" class="d-inline-block mt-1">
                                            <?= csrf_field() ?>
                                            <div class="d-flex gap-1">
                                                <select name="status" class="form-select form-select-sm">
                                                    <?php foreach (['pending', 'ongoing', 'completed', 'cancelled'] as $status): ?>
                                                        <option value="<?= $status ?>" <?= ($row['status'] ?? '') === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select name="assigned_staff" class="form-select form-select-sm">
                                                    <option value="">Unassigned</option>
                                                    <?php foreach (($staff ?? []) as $member): ?>
                                                        <option value="<?= (int) $member['user_id'] ?>" <?= (int) ($row['assigned_staff'] ?? 0) === (int) $member['user_id'] ? 'selected' : '' ?>>
                                                            <?= esc($member['first_name'] . ' ' . $member['last_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button class="btn btn-sm btn-primary" type="submit">Save</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (($active_tab ?? '') === 'schedule'): ?>
<script>
(function () {
    const packageSelect = document.getElementById('schedule_package_id');
    const serviceSelect = document.getElementById('schedule_service_list_id');
    const helpText = document.getElementById('schedule_service_help');
    const selectedServiceId = '<?= (int) old('service_list_id') ?>';

    if (!packageSelect || !serviceSelect) {
        return;
    }

    const allServiceOptions = Array.from(serviceSelect.options)
        .filter((option) => option.value !== '')
        .map((option) => ({
            value: option.value,
            label: option.getAttribute('data-label') || option.textContent.trim(),
            text: option.textContent.trim(),
        }));

    const packageServiceMap = <?= json_encode($package_service_map ?? []) ?>;

    const rebuildServiceOptions = function () {
        const packageId = packageSelect.value;
        const previousValue = serviceSelect.value;

        serviceSelect.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = 'No specific service';
        serviceSelect.appendChild(defaultOption);

        if (!packageId || !Object.prototype.hasOwnProperty.call(packageServiceMap, packageId)) {
            allServiceOptions.forEach((optionData) => {
                const option = document.createElement('option');
                option.value = optionData.value;
                option.textContent = optionData.text;
                serviceSelect.appendChild(option);
            });

            const preferredValue = previousValue || selectedServiceId;
            if (preferredValue) {
                serviceSelect.value = preferredValue;
            }

            if (helpText) {
                helpText.textContent = 'Showing all active services. Select a package to narrow suggestions.';
            }
            return;
        }

        const linkedServices = packageServiceMap[packageId] || [];
        linkedServices.forEach((service) => {
            const option = document.createElement('option');
            option.value = String(service.service_list_id || '');
            option.textContent = service.service_name || '-';
            serviceSelect.appendChild(option);
        });

        const candidate = previousValue || selectedServiceId;
        if (candidate && linkedServices.some((service) => String(service.service_list_id) === String(candidate))) {
            serviceSelect.value = String(candidate);
        }

        if (helpText) {
            helpText.textContent = linkedServices.length > 0
                ? 'Suggestions are filtered by selected package.'
                : 'No services are assigned to this package yet. You may leave this blank.';
        }
    };

    packageSelect.addEventListener('change', rebuildServiceOptions);
    rebuildServiceOptions();
})();
</script>
<?php endif; ?>
<?= $this->endSection() ?>
