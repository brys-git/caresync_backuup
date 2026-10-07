<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<?php $a = $analytics ?? []; ?>

<?= view('components/stat_rail', ['stats' => [
    ['label' => 'Branch members', 'icon' => 'ti-users', 'value' => $a['branch_members'] ?? 0],
    ['label' => 'Active members', 'icon' => 'ti-user-check', 'value' => $a['active_members'] ?? 0, 'tone' => 'ok'],
    ['label' => 'Overdue accounts', 'icon' => 'ti-alert-triangle', 'value' => $a['overdue_accounts'] ?? 0, 'tone' => 'stop'],
    ['label' => 'Staff count', 'icon' => 'ti-id-badge-2', 'value' => $a['staff_count'] ?? 0],
]]) ?>

<?= view('components/stat_rail', ['stats' => [
    ['label' => 'This month\'s collections', 'icon' => 'ti-cash', 'value' => $a['monthly_total'] ?? 0, 'money' => true, 'tone' => 'money'],
    ['label' => 'Pending initial payments', 'icon' => 'ti-clock', 'value' => $a['pending_initial_payments'] ?? 0, 'tone' => ((int) ($a['pending_initial_payments'] ?? 0) > 0) ? 'warn' : ''],
    ['label' => 'Pending service requests', 'icon' => 'ti-clipboard-list', 'value' => $a['pending_service_requests'] ?? 0, 'tone' => ((int) ($a['pending_service_requests'] ?? 0) > 0) ? 'warn' : ''],
    ['label' => 'Ongoing services', 'icon' => 'ti-progress', 'value' => $a['ongoing_services'] ?? 0],
]]) ?>

<section class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Daily collections this month</h2>
    </div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($a['daily_collections'])): ?>
            <?= view('components/empty_state', ['icon' => 'ti-cash-off', 'title' => 'No collections recorded yet this month']) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead><tr><th>Date</th><th class="cs-num">Transactions</th><th class="cs-num">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($a['daily_collections'] as $row): ?>
                            <tr>
                                <td><?= cs_date($row['date'] ?? null) ?></td>
                                <td class="cs-num"><?= esc((string) ($row['count'] ?? 0)) ?></td>
                                <td class="cs-num"><?= cs_money($row['total'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
