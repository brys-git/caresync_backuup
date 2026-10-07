<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'staff-list' ? 'active' : '' ?>" href="<?= site_url('/branch-admin/staff-monitoring') ?>">Staff List</a></li>
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'assign' ? 'active' : '' ?>" href="<?= site_url('/branch-admin/staff-monitoring/assign') ?>">Assign Tasks</a></li>
    <li class="nav-item"><a class="nav-link <?= ($active_tab ?? '') === 'activities' ? 'active' : '' ?>" href="<?= site_url('/branch-admin/staff-monitoring/activities') ?>">Staff Activities</a></li>
</ul>

<?php if (($active_tab ?? '') === 'staff-list'): ?>
    <?= view('partials/search_bar', [
        'action'      => site_url('/branch-admin/staff-monitoring'),
        'term'        => $search ?? '',
        'placeholder' => 'Staff name, email, username or mobile number',
    ]) ?>
    <section class="cs-panel mb-3">
        <div class="cs-panel__body cs-panel__body--flush">
            <?php $rows = $staff ?? []; ?>
            <?php if (empty($rows)): ?>
                <?= view('components/empty_state', ['icon' => 'ti-users', 'title' => ! empty($search) ? 'No staff match "' . $search . '"' : 'No staff found in your branch', 'text' => ! empty($search) ? 'Check the spelling or try fewer words, then search again - or clear the search.' : '']) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th class="cs-table__actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php $statusValue = (string) ($row['status'] ?? ($row['account_status'] ?? 'unknown')); ?>
                                <tr>
                                    <td><?= esc(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                                    <td><?= esc($row['email'] ?? '-') ?></td>
                                    <td><?= esc($row['contact_number'] ?? '-') ?></td>
                                    <td><?= cs_status($statusValue) ?></td>
                                    <td class="cs-table__actions">
                                        <a href="<?= site_url('/branch-admin/staff-monitoring?staff_id=' . (int) $row['user_id']) ?>" class="btn btn-sm btn-ghost">View Profile</a>
                                        <a href="<?= site_url('/branch-admin/staff-management/edit/' . (int) $row['user_id']) ?>" class="btn btn-sm btn-outline-primary">Edit Staff</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (! empty($selected_staff)): ?>
        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Staff Profile</h2></div>
            <div class="cs-panel__body">
                <div class="row g-3">
                    <div class="col-md-4"><strong>Name:</strong> <?= esc(($selected_staff['first_name'] ?? '') . ' ' . ($selected_staff['last_name'] ?? '')) ?></div>
                    <div class="col-md-4"><strong>Email:</strong> <?= esc($selected_staff['email'] ?? '-') ?></div>
                    <div class="col-md-4"><strong>Contact:</strong> <?= esc($selected_staff['contact_number'] ?? '-') ?></div>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php if (($active_tab ?? '') === 'assign'): ?>
    <section class="cs-panel mb-3">
        <div class="cs-panel__head"><h2 class="cs-panel__title">Assign Staff to Service</h2></div>
        <div class="cs-panel__body">
            <form method="post" action="<?= site_url('/branch-admin/staff-monitoring/store') ?>">
                <?= csrf_field() ?>
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Service</label>
                        <select name="service_id" class="form-select" required>
                            <option value="">Select service</option>
                            <?php foreach (($services ?? []) as $service): ?>
                                <option value="<?= (int) $service['service_id'] ?>" <?= (int) old('service_id') === (int) $service['service_id'] ? 'selected' : '' ?>>
                                    #<?= (int) $service['service_id'] ?> - <?= esc($service['service_type'] ?? '-') ?> (<?= esc($service['service_date'] ?? '-') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Staff</label>
                        <select name="staff_id" class="form-select" required>
                            <option value="">Select staff</option>
                            <?php foreach (($staff ?? []) as $member): ?>
                                <option value="<?= (int) $member['user_id'] ?>" <?= (int) old('staff_id') === (int) $member['user_id'] ? 'selected' : '' ?>>
                                    <?= esc(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">Assign</button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="cs-panel">
        <div class="cs-panel__head"><h2 class="cs-panel__title">Assignment History</h2></div>
        <div class="cs-panel__body cs-panel__body--flush">
            <?php $rows = $assignments ?? []; ?>
            <?php if (empty($rows)): ?>
                <?= view('components/empty_state', ['icon' => 'ti-clipboard-list', 'title' => 'No assignments yet']) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Staff</th>
                                <th>Date Assigned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>#<?= (int) ($row['service_id'] ?? 0) ?> - <?= esc($row['service_type'] ?? '-') ?></td>
                                    <td><?= esc(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                                    <td class="text-nowrap"><?= cs_date($row['assigned_date'] ?? null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (($active_tab ?? '') === 'activities'): ?>
    <div class="row g-3 mb-3">
        <?php foreach (($staff ?? []) as $member): ?>
            <?php $staffId = (int) $member['user_id']; ?>
            <?php $p = $performance[$staffId] ?? ['total_assigned' => 0, 'completed' => 0, 'ongoing' => 0, 'pending' => 0, 'cancelled' => 0]; ?>
            <div class="col-md-6 col-xl-4">
                <section class="cs-panel h-100">
                    <div class="cs-panel__body">
                        <h6 class="mb-2"><?= esc(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></h6>
                        <div class="small text-muted">Total Assigned: <?= (int) $p['total_assigned'] ?></div>
                        <div class="small text-success">Completed: <?= (int) $p['completed'] ?></div>
                        <div class="small text-primary">Ongoing: <?= (int) $p['ongoing'] ?></div>
                        <div class="small text-warning">Pending: <?= (int) $p['pending'] ?></div>
                        <div class="small text-secondary">Cancelled: <?= (int) $p['cancelled'] ?></div>
                    </div>
                </section>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="cs-panel">
        <div class="cs-panel__head"><h2 class="cs-panel__title">Staff Activities</h2></div>
        <div class="cs-panel__body cs-panel__body--flush">
            <?php $rows = $activities ?? []; ?>
            <?php if (empty($rows)): ?>
                <?= view('components/empty_state', ['icon' => 'ti-activity', 'title' => 'No activity records found']) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Staff Name</th>
                                <th>Service</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Assigned Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td><?= esc(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?></td>
                                    <td><?= esc($row['service_type'] ?? '-') ?></td>
                                    <td class="text-nowrap"><?= cs_date($row['service_date'] ?? null) ?></td>
                                    <td><?= cs_status((string) ($row['status'] ?? '')) ?></td>
                                    <td class="text-nowrap"><?= cs_date($row['assigned_date'] ?? null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?= $this->endSection() ?>
