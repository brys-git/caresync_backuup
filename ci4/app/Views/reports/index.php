<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php if (! empty($show_reports_nav)): ?>
    <?php $this->setData(['reports_base_path' => $reports_base_path ?? '/admin/reports', 'active_report' => '']) ?>
    <?= $this->include('partials/reports_nav') ?>
    <p class="cs-muted small mt-n2 mb-3">Overdue, Ledger, Collections, Commission, and Remittance are dedicated, exportable reports (panel brief section 7) - the dashboard below stays as a general at-a-glance summary.</p>
<?php endif; ?>

<?= view('components/stat_rail', ['stats' => [
    ['label' => 'Payments', 'icon' => 'ti-receipt', 'value' => count($payments)],
    ['label' => 'Branches', 'icon' => 'ti-building-community', 'value' => count($branches)],
    ['label' => 'Services', 'icon' => 'ti-package', 'value' => count($services)],
    ['label' => 'Staff activity rows', 'icon' => 'ti-clipboard-list', 'value' => count($staff_activity)],
]]) ?>

<?= view('components/stat_rail', ['stats' => [
    ['label' => 'Total members', 'icon' => 'ti-users', 'value' => $summary['member_status']['total_members'] ?? 0],
    ['label' => 'Active members', 'icon' => 'ti-user-check', 'value' => $summary['member_status']['active_members'] ?? 0, 'tone' => 'ok'],
    ['label' => 'Inactive members', 'icon' => 'ti-user-off', 'value' => $summary['member_status']['inactive_members'] ?? 0, 'tone' => 'idle'],
    ['label' => 'Monthly collections', 'icon' => 'ti-cash', 'value' => $summary['payment_breakdown']['total_amount'] ?? 0, 'money' => true, 'tone' => 'money'],
]]) ?>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Payments report</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap">
            <table class="cs-table">
                <thead><tr><th>Date</th><th>Branch</th><th>Receiver</th><th class="cs-num">Months</th><th class="cs-num">Amount</th><th>Method</th><th>Reference / OR</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="text-nowrap"><?= cs_date($payment['payment_date'] ?? null) ?></td>
                            <td><?= esc((string) ($payment['branch_name'] ?? '—')) ?></td>
                            <td><?= esc(trim((string) ($payment['client_first'] ?? '') . ' ' . (string) ($payment['client_last'] ?? ''))) ?></td>
                            <td class="cs-num"><?= esc((string) ((int) ($payment['months_covered'] ?? 1))) ?></td>
                            <td class="cs-num"><?= cs_money($payment['amount'] ?? 0) ?></td>
                            <td><?= esc(strtoupper((string) ($payment['payment_method'] ?? ''))) ?></td>
                            <td><?= esc((string) ($payment['reference_number'] ?? ($payment['official_receipt_number'] ?? '—'))) ?></td>
                            <td><?= cs_status((string) ($payment['status'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Monthly collections</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap">
            <table class="cs-table">
                <thead><tr><th>Month</th><th class="cs-num">Transactions</th><th class="cs-num">Total amount</th></tr></thead>
                <tbody>
                    <?php foreach (($summary['monthly_collections'] ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['month_label'] ?? '—')) ?></td>
                            <td class="cs-num"><?= esc((string) ($row['total_transactions'] ?? 0)) ?></td>
                            <td class="cs-num"><?= cs_money($row['total_amount'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Delinquent accounts</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap">
            <table class="cs-table">
                <thead><tr><th>Client</th><th>Branch</th><th class="cs-num">Remaining balance</th><th class="cs-num">Months paid</th><th>Next due</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach (($summary['delinquent_accounts'] ?? []) as $row): ?>
                        <tr>
                            <td><?= esc(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''))) ?></td>
                            <td><?= esc((string) ($row['branch_name'] ?? '—')) ?></td>
                            <td class="cs-num"><?= cs_money($row['remaining_balance'] ?? 0) ?></td>
                            <td class="cs-num"><?= esc((string) ($row['months_paid'] ?? 0)) ?></td>
                            <td class="text-nowrap"><?= cs_date($row['next_due_date'] ?? null) ?></td>
                            <td><?= cs_status((string) ($row['plan_status'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Service usage statistics</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap">
            <table class="cs-table">
                <thead><tr><th>Service</th><th class="cs-num">Total</th><th class="cs-num">Completed</th><th class="cs-num">Cancelled</th></tr></thead>
                <tbody>
                    <?php foreach (($summary['service_usage'] ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['service_name'] ?? '—')) ?></td>
                            <td class="cs-num"><?= esc((string) ($row['total_services'] ?? 0)) ?></td>
                            <td class="cs-num"><?= esc((string) ($row['completed_services'] ?? 0)) ?></td>
                            <td class="cs-num"><?= esc((string) ($row['cancelled_services'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Payment trends</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap">
            <table class="cs-table">
                <thead><tr><th>Month</th><th class="cs-num">Cash</th><th class="cs-num">GCash</th></tr></thead>
                <tbody>
                    <?php foreach (($summary['payment_trends'] ?? []) as $row): ?>
                        <tr>
                            <td><?= esc((string) ($row['month_label'] ?? '—')) ?></td>
                            <td class="cs-num"><?= cs_money($row['cash_total'] ?? 0) ?></td>
                            <td class="cs-num"><?= cs_money($row['gcash_total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Branch performance</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap">
            <table class="cs-table">
                <thead><tr><th>Branch</th><th class="cs-num">Revenue</th><th class="cs-num">Staff</th><th class="cs-num">Clients</th><th class="cs-num">Services</th></tr></thead>
                <tbody>
                    <?php foreach ($branches as $branch): ?>
                        <tr>
                            <td><?= esc($branch['branch_name']) ?></td>
                            <td class="cs-num"><?= cs_money($branch['revenue'] ?? 0) ?></td>
                            <td class="cs-num"><?= esc((string) $branch['staff_count']) ?></td>
                            <td class="cs-num"><?= esc((string) $branch['client_count']) ?></td>
                            <td class="cs-num"><?= esc((string) $branch['service_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Services report</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap">
            <table class="cs-table">
                <thead><tr><th>ID</th><th>Client</th><th>Branch</th><th>Type</th><th>Status</th><th>Date</th><th class="cs-num">Total cost</th></tr></thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <td>#<?= esc((string) $service['service_id']) ?></td>
                            <td><?= esc($service['first_name'] . ' ' . $service['last_name']) ?></td>
                            <td><?= esc($service['branch_name']) ?></td>
                            <td><?= esc((string) $service['service_type']) ?></td>
                            <td><?= cs_status((string) $service['status']) ?></td>
                            <td class="text-nowrap"><?= cs_date($service['service_date'] ?? null) ?></td>
                            <td class="cs-num"><?= cs_money($service['total_cost'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Plan holders report</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap">
            <table class="cs-table">
                <thead><tr><th>Plan holder</th><th>Identifier</th><th>Branch</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?= esc($client['first_name'] . ' ' . $client['last_name']) ?></td>
                            <td><?= esc((string) $client['unique_identifier']) ?></td>
                            <td><?= esc($client['branch_name']) ?></td>
                            <td><?= cs_status((string) $client['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Staff activity report</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <div class="cs-tablewrap">
            <table class="cs-table">
                <thead><tr><th>Staff</th><th class="cs-num">Tasks</th><th class="cs-num">Payments handled</th><th class="cs-num">Services handled</th></tr></thead>
                <tbody>
                    <?php foreach ($staff_activity as $staff): ?>
                        <tr>
                            <td><?= esc($staff['first_name'] . ' ' . $staff['last_name']) ?></td>
                            <td class="cs-num"><?= esc((string) $staff['tasks_assigned']) ?></td>
                            <td class="cs-num"><?= esc((string) $staff['payments_handled']) ?></td>
                            <td class="cs-num"><?= esc((string) $staff['services_handled']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
