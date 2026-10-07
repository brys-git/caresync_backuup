<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<div class="mb-3 text-end">
    <a href="<?= site_url('/branch-admin/staff-monitoring') ?>" class="btn btn-ghost btn-sm">Back to Staff Monitoring</a>
</div>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<?= view('partials/search_bar', [
    'action'      => site_url('/branch-admin/staff-management'),
    'term'        => $search ?? '',
    'placeholder' => 'Staff name, email, username or mobile number',
]) ?>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($staff ?? [])): ?>
            <?= view('components/empty_state', ['icon' => 'ti-users', 'title' => ! empty($search) ? 'No staff match "' . $search . '"' : 'No staff records found', 'text' => ! empty($search) ? 'Check the spelling or try fewer words, then search again - or clear the search.' : '']) ?>
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
                        <?php foreach (($staff ?? []) as $member): ?>
                            <tr>
                                <td><?= esc(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></td>
                                <td><?= esc($member['email'] ?? '-') ?></td>
                                <td><?= esc($member['contact_number'] ?? '-') ?></td>
                                <td><?= cs_status((string) ($member['status'] ?? '')) ?></td>
                                <td class="cs-table__actions">
                                    <a href="<?= site_url('/branch-admin/staff-management/edit/' . (int) $member['user_id']) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
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
