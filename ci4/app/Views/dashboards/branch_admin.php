<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<p class="cs-muted mb-3">Welcome back, <?= esc($operator_name) ?> — <?= esc($branch_name) ?>. Last updated <?= esc($current_time) ?>.</p>

<?= view('components/stat_rail', ['stats' => [
    [
        'label' => 'Active members',
        'icon'  => 'ti-users',
        'value' => $member_stats['total_active'] ?? 0,
        'tone'  => 'ok',
    ],
    [
        'label' => 'Pending registrations',
        'icon'  => 'ti-user-plus',
        'value' => $pending_approvals ?? 0,
        'tone'  => ((int) ($pending_approvals ?? 0) > 0) ? 'warn' : '',
    ],
    [
        'label' => 'Collections this month',
        'icon'  => 'ti-cash',
        'value' => $collections_this_month ?? 0,
        'money' => true,
        'tone'  => 'money',
    ],
    [
        'label' => 'Ongoing services',
        'icon'  => 'ti-progress',
        'value' => $ongoing_services ?? 0,
    ],
]]) ?>

<div class="row g-3">
    <div class="col-lg-8">
        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Operational alert feed</h2>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php
                $alertTones = ['danger' => 'stop', 'warning' => 'pending', 'info' => 'info', 'secondary' => 'idle'];
                ?>
                <?php if (! empty($payment_alerts)): ?>
                    <?php foreach ($payment_alerts as $alert): ?>
                        <div class="cs-linkrow">
                            <span class="cs-status cs-status--<?= esc($alertTones[$alert['type']] ?? 'idle') ?>">&nbsp;</span>
                            <strong><?= esc($alert['title']) ?>:</strong> <?= esc($alert['detail']) ?>
                        </div>
                    <?php endforeach ?>
                <?php else: ?>
                    <?= view('components/empty_state', [
                        'icon'  => 'ti-circle-check',
                        'title' => 'No critical operational flags',
                        'text'  => 'Branch workflows are stable.',
                    ]) ?>
                <?php endif ?>
            </div>
        </section>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Today's service agenda</h2>
                <a class="btn btn-ghost btn-sm" href="<?= base_url('branch-admin/service-package/ongoing') ?>">View all</a>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($today_operations)): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-calendar-off', 'title' => 'No scheduled services for today']) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Service</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_operations as $operation): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= esc($operation['event_time'] ?: 'TBD') ?></td>
                                        <td><?= esc($operation['event_name']) ?></td>
                                        <td><?= esc(trim(($operation['first_name'] ?? '') . ' ' . ($operation['last_name'] ?? ''))) ?: esc($operation['unique_identifier'] ?? 'N/A') ?></td>
                                        <td><?= cs_status((string) $operation['status']) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </section>

        <section class="cs-panel">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Recent payments</h2>
                <a class="btn btn-ghost btn-sm" href="<?= base_url('branch-admin/payment-tracking') ?>">All payments</a>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($recent_payments)): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-cash-off', 'title' => 'No recent branch payments available']) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Member</th>
                                    <th class="cs-num">Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= cs_date($payment['payment_date'] ?? null) ?></td>
                                        <td><?= esc(trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? ''))) ?></td>
                                        <td class="cs-num"><?= cs_money($payment['amount'] ?? 0) ?></td>
                                        <td><?= cs_status((string) ($payment['status'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </section>
    </div>

    <div class="col-lg-4">
        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Quick actions</h2>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <a class="cs-linkrow" href="<?= base_url('branch-admin/service-package/requests') ?>">Review pending service applications</a>
                <a class="cs-linkrow" href="<?= base_url('branch-admin/payment-tracking') ?>">Verify branch payments</a>
                <a class="cs-linkrow" href="<?= base_url('branch-admin/service-package/ongoing') ?>">Monitor ongoing operations</a>
            </div>
        </section>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Service request monitoring</h2>
            </div>
            <div class="cs-panel__body">
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="cs-muted">Pending review</span>
                    <strong class="cs-num"><?= esc(number_format((int) ($service_request_counts['pending'] ?? 0))) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="cs-muted">Approved requests</span>
                    <strong class="cs-num"><?= esc(number_format((int) ($service_request_counts['approved'] ?? 0))) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-baseline mb-3">
                    <span class="cs-muted">Rejected / returned</span>
                    <strong class="cs-num"><?= esc(number_format((int) ($service_request_counts['rejected'] ?? 0))) ?></strong>
                </div>
                <a href="<?= base_url('branch-admin/service-package/requests') ?>" class="btn btn-outline-primary btn-sm w-100">Open requests dashboard</a>
            </div>
        </section>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Operational readiness</h2>
            </div>
            <div class="cs-panel__body">
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="cs-muted">Staff on duty</span>
                    <strong class="cs-num"><?= esc(number_format((int) $staff_on_duty)) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="cs-muted">Upcoming events</span>
                    <strong class="cs-num"><?= esc(number_format((int) $upcoming_services)) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-baseline">
                    <span class="cs-muted">Delinquent members</span>
                    <strong class="cs-num"><?= esc(number_format((int) ($member_stats['total_delinquent'] ?? 0))) ?></strong>
                </div>
            </div>
        </section>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Collections trend</h2>
            </div>
            <div class="cs-panel__body">
                <?php if (! empty($payment_analytics['months'])): ?>
                    <?php $maxTotal = max(array_values($payment_analytics['totals']) ?: [1]); ?>
                    <?php foreach ($payment_analytics['months'] as $key => $label): ?>
                        <?php $value = $payment_analytics['totals'][$key] ?? 0; ?>
                        <div class="cs-meterrow">
                            <span><?= esc($label) ?></span>
                            <strong><?= cs_money($value) ?></strong>
                        </div>
                        <div class="cs-meter mb-3">
                            <div class="cs-meter__fill" style="width: <?= esc($value > 0 ? min(100, ($value / $maxTotal) * 100) : 2) ?>%"></div>
                        </div>
                    <?php endforeach ?>
                <?php else: ?>
                    <p class="cs-muted mb-0">No approved collections recorded yet.</p>
                <?php endif ?>
            </div>
        </section>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Payment method mix</h2>
            </div>
            <div class="cs-panel__body">
                <?php if (! empty($payment_analytics['method_breakdown'])): ?>
                    <?php foreach ($payment_analytics['method_breakdown'] as $method): ?>
                        <div class="cs-meterrow">
                            <span><?= esc($method['method']) ?></span>
                            <strong><?= esc($method['share']) ?>%</strong>
                        </div>
                        <div class="cs-meter mb-3">
                            <div class="cs-meter__fill" style="width: <?= esc($method['share']) ?>%"></div>
                        </div>
                    <?php endforeach ?>
                <?php else: ?>
                    <p class="cs-muted mb-0">No payment method breakdown available for the current month.</p>
                <?php endif ?>
            </div>
        </section>

        <section class="cs-panel">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Recent branch activity</h2>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($activity_logs)): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-history', 'title' => 'No activity log entries available']) ?>
                <?php else: ?>
                    <?php foreach ($activity_logs as $log): ?>
                        <div class="cs-linkrow cs-linkrow--block">
                            <div class="cs-table__sub mb-1"><?= cs_datetime($log['created_at'] ?? null) ?></div>
                            <div><strong><?= esc(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))) ?></strong> <?= esc($log['action']) ?> <?= esc($log['module']) ?></div>
                        </div>
                    <?php endforeach ?>
                <?php endif ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
