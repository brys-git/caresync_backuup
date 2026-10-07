<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php $s = $summary ?? []; ?>

<?= view('components/alert', [
    'type' => 'info',
    'dismissible' => false,
    'message' => 'Dedicated collection-entry, remittance, and commission-tracking screens for this role are still being built. This overview reflects payments already on record where you\'re the one who received them.',
]) ?>

<?= view('components/stat_rail', ['stats' => [
    [
        'label' => 'Collected today',
        'icon'  => 'ti-cash',
        'value' => $s['today_collected'] ?? 0,
        'money' => true,
        'tone'  => 'money',
    ],
    [
        'label' => 'Collected this month',
        'icon'  => 'ti-calendar',
        'value' => $s['month_collected'] ?? 0,
        'money' => true,
        'tone'  => 'money',
    ],
    [
        'label' => 'Commission this month',
        'icon'  => 'ti-percentage',
        'value' => $s['estimated_commission'] ?? 0,
        'money' => true,
        'tone'  => 'ok',
    ],
    [
        'label' => 'Total transactions',
        'icon'  => 'ti-receipt',
        'value' => $s['transaction_count'] ?? 0,
    ],
]]) ?>

<section class="cs-panel">
    <div class="cs-panel__head">
        <div>
            <h2 class="cs-panel__title">Recent payments you recorded</h2>
        </div>
        <a class="btn btn-ghost btn-sm" href="<?= base_url('collector/commission') ?>">
            Commission <i class="ti ti-arrow-narrow-right" aria-hidden="true"></i>
        </a>
    </div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php $rows = $s['recent_payments'] ?? []; ?>
        <?php if (empty($rows)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-cash-off',
                'title' => 'No payments recorded yet',
                'text'  => 'Payments you record for plan holders will show up here.',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Plan holder</th>
                            <th>Method</th>
                            <th>Date</th>
                            <th class="cs-num">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= esc(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: '—') ?></td>
                                <td><?= esc(strtoupper((string) ($row['payment_method'] ?? '—'))) ?></td>
                                <td class="cs-table__sub text-nowrap"><?= cs_date($row['payment_date'] ?? null) ?></td>
                                <td class="cs-num"><?= cs_money($row['amount'] ?? 0) ?></td>
                                <td><?= cs_status((string) ($row['status'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
