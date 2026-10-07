<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$t = $totals ?? [];
$quick = (string) ($range['quick'] ?? 'this_month');
$rangeLabel = 'All time';
if (! empty($range['from']) || ! empty($range['to'])) {
    $rangeLabel = cs_date($range['from'] ?? null) . ' - ' . cs_date($range['to'] ?? null);
}
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <p class="cs-muted mb-0">Showing: <?= esc($rangeLabel) ?></p>
    <div class="d-flex gap-2">
        <a class="btn btn-sm <?= $quick === 'this_month' ? 'btn-primary' : 'btn-ghost' ?>" href="?quick=this_month">This month</a>
        <a class="btn btn-sm <?= $quick === 'last_month' ? 'btn-primary' : 'btn-ghost' ?>" href="?quick=last_month">Last month</a>
        <a class="btn btn-sm <?= $quick === 'all_time' ? 'btn-primary' : 'btn-ghost' ?>" href="?quick=all_time">All time</a>
    </div>
</div>

<section class="cs-panel mb-3">
    <div class="cs-panel__body">
        <?= view('components/stat_rail', ['stats' => [
            ['label' => 'Collected (verified)', 'value' => $t['collected'] ?? 0, 'money' => true, 'icon' => 'ti-cash'],
            ['label' => 'Commission earned (10%)', 'value' => $t['commission'] ?? 0, 'money' => true, 'tone' => 'money', 'icon' => 'ti-coin'],
            ['label' => 'Clients', 'value' => $t['clients'] ?? 0, 'icon' => 'ti-users'],
            [
                'label' => 'Awaiting verification',
                'value' => $t['pending_amount'] ?? 0,
                'money' => true,
                'tone' => 'warn',
                'icon' => 'ti-clock',
                'meta' => ($t['pending_amount'] ?? 0) > 0
                    ? ('₱' . number_format((float) ($t['pending_commission'] ?? 0), 2) . ' commission once verified')
                    : null,
            ],
        ]]) ?>
    </div>
</section>

<section class="cs-panel mb-3">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Commission per client</h2>
        <p class="cs-panel__note">Verified cash collections in this range, newest first.</p>
    </div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($per_client)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-coin-off',
                'title' => 'No collections in this range',
                'text'  => 'Verified cash collections you record will show up here, with your 10% commission per client.',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Plan No.</th>
                            <th>Barangay</th>
                            <th class="cs-num">Payments</th>
                            <th class="cs-num">Months</th>
                            <th class="cs-num">Collected</th>
                            <th class="cs-num">Commission 10%</th>
                            <th>Last Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $totalCollected = 0.0; $totalCommission = 0.0; ?>
                        <?php foreach ($per_client as $row): ?>
                            <?php $totalCollected += (float) $row['collected']; $totalCommission += (float) $row['commission']; ?>
                            <tr>
                                <td><?= esc((string) $row['client_name']) ?></td>
                                <td><?= esc((string) $row['plan_number']) ?></td>
                                <td><?= esc((string) $row['barangay']) ?></td>
                                <td class="cs-num"><?= esc((string) $row['payments_count']) ?></td>
                                <td class="cs-num"><?= esc((string) $row['months_total']) ?></td>
                                <td class="cs-num"><?= cs_money($row['collected']) ?></td>
                                <td class="cs-num"><?= cs_money($row['commission'], true, 'cs-money--brass') ?></td>
                                <td class="text-nowrap"><?= cs_date($row['last_collection_date'] ?? null) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-end">Totals</th>
                            <th class="cs-num"><?= cs_money($totalCollected) ?></th>
                            <th class="cs-num"><?= cs_money($totalCommission, true, 'cs-money--brass') ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Awaiting verification</h2>
        <p class="cs-panel__note">Not yet earned - counts once your branch admin verifies it.</p>
    </div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($pending)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-clock-check',
                'title' => 'Nothing awaiting verification',
                'text'  => 'Cash you record shows up here until your branch admin verifies it.',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Date</th>
                            <th>OR Number</th>
                            <th class="cs-num">Amount</th>
                            <th class="cs-num">Projected Commission</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $row): ?>
                            <tr>
                                <td><?= esc((string) $row['client_name']) ?></td>
                                <td class="text-nowrap"><?= cs_date($row['payment_date'] ?? null) ?></td>
                                <td><?= esc((string) ($row['official_receipt_number'] ?: '-')) ?></td>
                                <td class="cs-num"><?= cs_money($row['amount']) ?></td>
                                <td class="cs-num"><?= cs_money($row['projected_commission']) ?></td>
                                <td><?= cs_status('pending') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
