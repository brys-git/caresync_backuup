<?= $this->extend($role_layout ?? 'layouts/admin') ?>

<?= $this->section('content') ?>
<?php $a = $analytics ?? []; ?>

<?= view('components/stat_rail', ['stats' => [
    ['label' => 'Total members', 'icon' => 'ti-users', 'value' => $a['total_members'] ?? 0],
    ['label' => 'Active members', 'icon' => 'ti-user-check', 'value' => $a['active_members'] ?? 0, 'tone' => 'ok'],
    ['label' => 'Delinquent members', 'icon' => 'ti-alert-triangle', 'value' => $a['delinquent_members'] ?? 0, 'tone' => 'stop'],
    ['label' => 'Suspended members', 'icon' => 'ti-user-off', 'value' => $a['suspended_members'] ?? 0, 'tone' => 'stop'],
]]) ?>

<?= view('components/stat_rail', ['stats' => [
    ['label' => 'Total collections (all time)', 'icon' => 'ti-cash', 'value' => $a['total_collections'] ?? 0, 'money' => true, 'tone' => 'money'],
    ['label' => 'Pending payment approvals', 'icon' => 'ti-clock', 'value' => $a['pending_approvals'] ?? 0, 'tone' => ((int) ($a['pending_approvals'] ?? 0) > 0) ? 'warn' : ''],
    ['label' => 'Pending service requests', 'icon' => 'ti-clipboard-list', 'value' => $a['pending_service_requests'] ?? 0, 'tone' => ((int) ($a['pending_service_requests'] ?? 0) > 0) ? 'warn' : ''],
    ['label' => 'Branches / Staff / Collectors', 'icon' => 'ti-building-community', 'value' => (string) ($a['total_branches'] ?? 0) . ' / ' . (string) ($a['total_staff'] ?? 0) . ' / ' . (string) ($a['total_collectors'] ?? 0)],
]]) ?>

<div class="row g-3">
    <div class="col-lg-7">
        <section class="cs-panel">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Monthly collections (last 12 months)</h2>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($a['monthly_collections'])): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-cash-off', 'title' => 'No collections recorded yet']) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead><tr><th>Month</th><th class="cs-num">Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($a['monthly_collections'] as $row): ?>
                                    <tr>
                                        <td><?= esc((string) ($row['month'] ?? '—')) ?></td>
                                        <td class="cs-num"><?= cs_money($row['amount'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="cs-panel">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Top branches by membership</h2>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($a['top_branches'])): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-building-community', 'title' => 'No branch data yet']) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead><tr><th>Branch</th><th class="cs-num">Members</th></tr></thead>
                            <tbody>
                                <?php foreach ($a['top_branches'] as $row): ?>
                                    <tr>
                                        <td><?= esc((string) ($row['branch_name'] ?? '—')) ?></td>
                                        <td class="cs-num"><?= esc((string) ($row['member_count'] ?? 0)) ?></td>
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
<?= $this->endSection() ?>
