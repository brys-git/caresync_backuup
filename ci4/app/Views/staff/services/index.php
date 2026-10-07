<?= $this->extend($role_layout ?? 'layouts/staff') ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<?php if (! empty($branch_issue)): ?>
    <div class="alert alert-warning"><?= esc($branch_issue) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'services' ? 'active' : '' ?>" href="<?= site_url('/staff/services?tab=services') ?>">Services</a></li>
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'packages' ? 'active' : '' ?>" href="<?= site_url('/staff/services?tab=packages') ?>">Packages</a></li>
    <li class="nav-item"><a class="nav-link" href="<?= site_url('/staff/services/requests') ?>">Service Requests</a></li>
    <li class="nav-item"><a class="nav-link" href="<?= site_url('/staff/services/ongoing') ?>">Ongoing Services</a></li>
</ul>

<?php if (($active_tab ?? '') === 'services'): ?>
    <section class="cs-panel">
        <div class="cs-panel__body cs-panel__body--flush">
            <?php $rows = $services ?? []; ?>
            <?php if (empty($rows)): ?>
                <?= view('components/empty_state', ['icon' => 'ti-truck', 'title' => 'No services found for your branch']) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Plan Holder</th>
                                <th>Service</th>
                                <th>Package</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="cs-table__actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= esc(trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')))) ?></td>
                                    <td><?= esc((string) ($row['service_name'] ?? '-')) ?></td>
                                    <td><?= esc((string) ($row['package_name'] ?? '-')) ?></td>
                                    <td class="text-nowrap"><?= cs_date($row['service_date'] ?? null) ?></td>
                                    <td><?= cs_status((string) ($row['status'] ?? '')) ?></td>
                                    <td class="cs-table__actions">
                                        <a href="<?= site_url('/staff/services?tab=services&service_id=' . (int) ($row['service_id'] ?? 0)) ?>" class="btn btn-sm btn-ghost">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (! empty($selected_service)): ?>
        <section class="cs-panel mt-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Service Details</h2></div>
            <div class="cs-panel__body">
                <div><strong>Plan Holder:</strong> <?= esc(trim((string) (($selected_service['first_name'] ?? '') . ' ' . ($selected_service['last_name'] ?? '')))) ?></div>
                <div><strong>Service:</strong> <?= esc((string) ($selected_service['service_name'] ?? '-')) ?></div>
                <div><strong>Package:</strong> <?= esc((string) ($selected_service['package_name'] ?? '-')) ?></div>
                <div><strong>Date:</strong> <?= cs_date($selected_service['service_date'] ?? null) ?></div>
                <div><strong>Status:</strong> <?= cs_status((string) ($selected_service['status'] ?? '')) ?></div>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php if (($active_tab ?? '') === 'packages'): ?>
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
                                <th class="cs-table__actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= esc((string) ($row['package_name'] ?? '-')) ?></td>
                                    <td><?= esc((string) ($row['description'] ?? '-')) ?></td>
                                    <td class="cs-num"><?= cs_money($row['base_price'] ?? 0) ?></td>
                                    <td class="cs-table__actions">
                                        <a href="<?= site_url('/staff/services?tab=packages&package_id=' . (int) ($row['package_id'] ?? 0)) ?>" class="btn btn-sm btn-ghost">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (! empty($selected_package)): ?>
        <section class="cs-panel mt-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Package Details</h2></div>
            <div class="cs-panel__body">
                <div><strong>Package:</strong> <?= esc((string) ($selected_package['package_name'] ?? '-')) ?></div>
                <div><strong>Description:</strong> <?= esc((string) ($selected_package['description'] ?? '-')) ?></div>
                <div><strong>Base Price:</strong> <?= cs_money($selected_package['base_price'] ?? 0) ?></div>
            </div>
        </section>

        <div class="row g-3 mt-3">
            <div class="col-md-6">
                <section class="cs-panel h-100">
                    <div class="cs-panel__head"><h2 class="cs-panel__title">Services Included</h2></div>
                    <div class="cs-panel__body cs-panel__body--flush">
                        <?php $serviceRows = $selected_package_services ?? []; ?>
                        <?php if (empty($serviceRows)): ?>
                            <?= view('components/empty_state', ['icon' => 'ti-truck', 'title' => 'No services found']) ?>
                        <?php else: ?>
                            <div class="cs-tablewrap">
                                <table class="cs-table">
                                    <thead><tr><th>Service Name</th><th>Description</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($serviceRows as $service): ?>
                                            <tr>
                                                <td><?= esc((string) ($service['service_name'] ?? '-')) ?></td>
                                                <td><?= esc((string) ($service['description'] ?? '-')) ?></td>
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
                        <?php $versionRows = $selected_package_versions ?? []; ?>
                        <?php if (empty($versionRows)): ?>
                            <?= view('components/empty_state', ['icon' => 'ti-history', 'title' => 'No versions found']) ?>
                        <?php else: ?>
                            <div class="cs-tablewrap">
                                <table class="cs-table">
                                    <thead><tr><th class="cs-num">Price</th><th>Effective Date</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($versionRows as $version): ?>
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
    <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
