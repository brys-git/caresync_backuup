<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$a    = $analytics ?? [];
$logs = $recent_activity ?? [];
?>

<?= view('components/stat_rail', ['stats' => [
    [
        'label' => 'Plan holders',
        'icon'  => 'ti-users',
        'value' => $a['total_members'] ?? 0,
        'meta'  => number_format((float) ($a['active_members'] ?? 0)) . ' active',
    ],
    [
        'label' => 'Collected to date',
        'icon'  => 'ti-cash',
        'value' => $a['total_collections'] ?? 0,
        'money' => true,
        'tone'  => 'money',
    ],
    [
        'label' => 'Awaiting approval',
        'icon'  => 'ti-user-check',
        'value' => $a['pending_approvals'] ?? 0,
        'tone'  => ((int) ($a['pending_approvals'] ?? 0) > 0) ? 'warn' : '',
        'meta'  => ((int) ($a['pending_approvals'] ?? 0) > 0) ? 'Registrations to review' : 'Nothing waiting',
    ],
    [
        'label' => 'Open service requests',
        'icon'  => 'ti-clipboard-list',
        'value' => $a['pending_service_requests'] ?? 0,
        'tone'  => ((int) ($a['pending_service_requests'] ?? 0) > 0) ? 'warn' : '',
    ],
]]) ?>

<div class="row g-3">
    <div class="col-lg-8">
        <section class="cs-panel">
            <div class="cs-panel__head">
                <div>
                    <h2 class="cs-panel__title">Recent activity</h2>
                    <p class="cs-panel__note">Actions recorded across every branch.</p>
                </div>
                <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/reports') ?>">
                    Reports <i class="ti ti-arrow-narrow-right" aria-hidden="true"></i>
                </a>
            </div>

            <div class="cs-panel__body cs-panel__body--flush">
                <?php if ($logs === []): ?>
                    <?= view('components/empty_state', [
                        'icon'  => 'ti-history',
                        'title' => 'No activity recorded yet',
                        'text'  => 'Approvals, payments, and record changes will show up here as staff work.',
                    ]) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead>
                                <tr>
                                    <th>Who</th>
                                    <th>What happened</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <?php $who = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'System'; ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="cs-avatar" aria-hidden="true"><?= esc(cs_initials($who)) ?></span>
                                                <span class="cs-table__primary"><?= esc($who) ?></span>
                                            </div>
                                        </td>
                                        <td><?= esc((string) ($log['description'] ?? ($log['action'] ?? '—'))) ?></td>
                                        <td class="cs-table__sub text-nowrap"><?= cs_date($log['created_at'] ?? null, true) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-lg-4">
        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Network</h2>
            </div>
            <div class="cs-panel__body">
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="cs-muted">Branches</span>
                    <strong class="cs-num"><?= esc(number_format((float) ($a['total_branches'] ?? 0))) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="cs-muted">Staff</span>
                    <strong class="cs-num"><?= esc(number_format((float) ($a['total_staff'] ?? 0))) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-baseline">
                    <span class="cs-muted">Collectors</span>
                    <strong class="cs-num"><?= esc(number_format((float) ($a['total_collectors'] ?? 0))) ?></strong>
                </div>
            </div>
        </section>

        <section class="cs-panel">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Jump to</h2>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php
                $links = [
                    ['admin/registration-approvals', 'ti-user-check',        'Registration approvals', (int) ($a['pending_approvals'] ?? 0)],
                    ['admin/client-management',      'ti-users',             'Plan holders',           0],
                    ['admin/payment-monitoring',     'ti-cash',              'Payment monitoring',     0],
                    ['admin/branch-management',      'ti-building-community','Branches',               0],
                    ['admin/service-offer',          'ti-package',           'Services & packages',    0],
                ];
                ?>
                <?php foreach ($links as [$url, $icon, $label, $count]): ?>
                    <a class="cs-linkrow" href="<?= base_url($url) ?>">
                        <i class="ti <?= $icon ?> cs-muted" aria-hidden="true"></i>
                        <span><?= esc($label) ?></span>
                        <?php if ($count > 0): ?>
                            <span class="cs-status cs-status--pending ms-auto"><?= esc((string) $count) ?></span>
                        <?php else: ?>
                            <i class="ti ti-chevron-right cs-muted ms-auto" aria-hidden="true"></i>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
