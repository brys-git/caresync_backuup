<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php $a = $analytics ?? []; ?>

<?= view('components/stat_rail', ['stats' => [
    [
        'label' => 'Branch members',
        'icon'  => 'ti-users',
        'value' => $a['staff_members'] ?? 0,
    ],
    [
        'label' => 'Pending requests',
        'icon'  => 'ti-clipboard-list',
        'value' => $a['pending_requests'] ?? 0,
        'tone'  => ((int) ($a['pending_requests'] ?? 0) > 0) ? 'warn' : '',
    ],
    [
        'label' => 'Approved services',
        'icon'  => 'ti-checkbox',
        'value' => $a['assigned_services'] ?? 0,
        'tone'  => 'ok',
    ],
    [
        'label' => 'Cash collected today',
        'icon'  => 'ti-cash',
        'value' => $a['cash_collected_today'] ?? 0,
        'money' => true,
        'tone'  => 'money',
    ],
]]) ?>

<section class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Quick links</h2>
    </div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php
        $links = [
            ['staff/services/requests', 'ti-clipboard-list', 'Review service requests', (int) ($a['pending_requests'] ?? 0)],
            ['staff/client-management', 'ti-users', 'Plan holders', 0],
            ['staff/payment-management', 'ti-cash', 'Payment management', 0],
            ['staff/services', 'ti-package', 'Services & packages', 0],
            ['staff/services/ongoing', 'ti-clock', 'Ongoing services', 0],
            ['staff/reports', 'ti-report', 'Reports', 0],
            ['staff/analytics', 'ti-chart-bar', 'My analytics', 0],
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
<?= $this->endSection() ?>
