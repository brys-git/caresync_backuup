<?= $this->extend($role_layout ?? 'layouts/staff') ?>

<?= $this->section('content') ?>
<?php if (! empty($branch_issue)): ?>
    <div class="alert alert-warning"><?= esc($branch_issue) ?></div>
<?php endif; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link" href="<?= site_url('/staff/services?tab=services') ?>">Services</a></li>
    <li class="nav-item"><a class="nav-link" href="<?= site_url('/staff/services?tab=packages') ?>">Packages</a></li>
    <li class="nav-item"><a class="nav-link" href="<?= site_url('/staff/services/requests') ?>">Service Requests</a></li>
    <li class="nav-item"><a class="nav-link active" href="<?= site_url('/staff/services/ongoing') ?>">Ongoing Services</a></li>
</ul>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php $rows = $services ?? []; ?>
        <?php if (empty($rows)): ?>
            <?= view('components/empty_state', ['icon' => 'ti-truck', 'title' => 'No ongoing services found for your branch']) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Plan Holder</th>
                            <th>Service</th>
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
<?= $this->endSection() ?>
