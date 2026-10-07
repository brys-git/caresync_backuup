<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$state = (string) ($access['state'] ?? 'unregistered');
$program = $program ?? ['name' => 'Damayan Burial Program', 'monthly_fee' => 240.0];
$userFullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
$membership_since = (string) ($membership_since ?? '');
$branch_name = (string) ($branch_name ?? '');
$statusLabel = (string) ($access['badge_label'] ?? 'Unregistered');
$monthlyFee = (float) ($plan['monthly_fee'] ?? ($program['monthly_fee'] ?? 240));
$planAmount = (float) ($plan['total_plan_amount'] ?? \App\Services\MembershipService::TOTAL_CONTRIBUTION);
$paidAmount = 0.0;
if (! empty($plan['plan_id'])) {
    $paidAmount = (float) ($plan['paid_amount'] ?? $plan['total_paid'] ?? 0);
}
$remainingBalance = max(0, (float) ($plan['remaining_balance'] ?? ($planAmount - $paidAmount)));
if (! empty($summary)) {
    // One source with the staff client page: ClientPlanSummary.
    $planAmount = (float) $summary['contract_amount'];
    $paidAmount = (float) $summary['total_paid'];
    $remainingBalance = (float) $summary['remaining_balance'];
}
$latestPayment = $recent_payments[0] ?? null;
$latestPaymentLabel = $latestPayment ? ('#' . (string) ($latestPayment['payment_id'] ?? '')) : 'None yet';
$statusTone = $state === 'active' ? 'active' : ($state === 'awaiting_activation' ? 'pending' : 'inactive');
$statusText = $state === 'active'
    ? 'Your account is fully verified and eligible for services.'
    : ($state === 'awaiting_activation' ? 'Please complete your initial payment.' : 'Please complete registration to unlock access.');
$progress = ! empty($summary) ? (float) $summary['progress_pct'] : ($planAmount > 0 ? min(100, max(0, (($paidAmount / max($planAmount, 1)) * 100))) : 0);
?>
<p class="cs-muted mb-3">
    Welcome back, <?= esc($userFullName ?: 'Member') ?> — Kaagapay member since <?= esc($membership_since !== '' ? $membership_since : 'not available') ?>.
</p>

<?= view('partials/plan_standing', ['standing' => $standing ?? null, 'standing_details' => true]) ?>

<?php if (session()->getFlashdata('error')): ?>
    <?= view('components/alert', ['type' => 'danger', 'message' => session()->getFlashdata('error')]) ?>
<?php endif; ?>
<?php if (session()->getFlashdata('success')): ?>
    <?= view('components/alert', ['type' => 'success', 'message' => session()->getFlashdata('success')]) ?>
<?php endif; ?>

<?= view('components/stat_rail', ['stats' => [
    ['label' => 'Plan amount', 'icon' => 'ti-certificate', 'value' => $planAmount, 'money' => true],
    ['label' => 'Paid', 'icon' => 'ti-cash', 'value' => $paidAmount, 'money' => true, 'tone' => 'ok'],
    ['label' => 'Remaining balance', 'icon' => 'ti-hourglass', 'value' => $remainingBalance, 'money' => true, 'tone' => 'money'],
    ['label' => 'Next due', 'icon' => 'ti-calendar', 'value' => strip_tags(cs_date($plan['next_due_date'] ?? null))],
]]) ?>

<div class="row g-3">
    <div class="col-lg-8">
        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <div>
                    <h2 class="cs-panel__title">Membership completion</h2>
                    <p class="cs-panel__note"><?= ! empty($summary) ? (int) $summary['months_paid'] . ' of ' . (int) $summary['total_months'] : esc((int) floor($paidAmount / max($monthlyFee ?: 1, 1))) . ' of ' . esc((int) ceil($planAmount / max($monthlyFee ?: 1, 1))) ?> months paid</p>
                </div>
                <span class="cs-muted">Latest payment: <?= esc($latestPaymentLabel) ?></span>
            </div>
            <div class="cs-panel__body">
                <div class="cs-meterrow"><span>Progress</span><strong><?= esc(number_format($progress, 0)) ?>%</strong></div>
                <div class="cs-meter"><div class="cs-meter__fill" style="width: <?= esc(number_format($progress, 0)) ?>%"></div></div>
            </div>
        </section>

        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Recent activity</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($recent_payments ?? [])): ?>
                    <?= view('components/empty_state', [
                        'icon'  => 'ti-history',
                        'title' => 'No recent activity yet',
                        'text'  => 'Your payments will show up here once they are recorded.',
                    ]) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead><tr><th>Date</th><th>Activity</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= cs_date($payment['payment_date'] ?? ($payment['created_at'] ?? null)) ?></td>
                                        <td>
                                            <?php if (! empty($payment['is_legacy'])): ?>
                                                Payment <strong>recorded by branch</strong> (<?= esc((string) ($payment['channel_label'] ?? 'Cash')) ?>)
                                            <?php else: ?>
                                                Payment recorded via <?= esc(strtoupper((string) ($payment['payment_method'] ?? '—'))) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= cs_status((string) ($payment['status'] ?? 'pending')) ?></td>
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
                <h2 class="cs-panel__title">Membership status</h2>
                <?= cs_status($statusTone, $statusLabel) ?>
            </div>
            <div class="cs-panel__body">
                <p><?= esc($statusText) ?></p>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Membership ID</span><strong><?= esc((string) ($plan_holder['unique_identifier'] ?? 'N/A')) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Branch</span><strong><?= esc($branch_name !== '' ? $branch_name : 'N/A') ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Package</span><strong><?= esc((string) ($plan['program_name'] ?? $program['name'] ?? 'Damayan Burial Program')) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="cs-muted">Plan status</span><?= cs_status((string) ($plan['status'] ?? 'inactive')) ?></div>
            </div>
        </section>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Quick actions</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <a class="cs-linkrow" href="<?= base_url('client/membership') ?>"><span>View membership</span><i class="ti ti-chevron-right cs-muted ms-auto" aria-hidden="true"></i></a>
                <a class="cs-linkrow" href="<?= base_url('initial-payment') ?>"><span>Make payment</span><i class="ti ti-chevron-right cs-muted ms-auto" aria-hidden="true"></i></a>
                <a class="cs-linkrow" href="<?= base_url('client/payment') ?>"><span>View payment history</span><i class="ti ti-chevron-right cs-muted ms-auto" aria-hidden="true"></i></a>
                <a class="cs-linkrow" href="<?= base_url('client/service') ?>"><span>Request a service</span><i class="ti ti-chevron-right cs-muted ms-auto" aria-hidden="true"></i></a>
                <a class="cs-linkrow" href="<?= base_url('client/profile') ?>"><span>Update profile</span><i class="ti ti-chevron-right cs-muted ms-auto" aria-hidden="true"></i></a>
            </div>
        </section>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Service eligibility</h2></div>
            <div class="cs-panel__body">
                <?= view('components/alert', [
                    'type' => $state === 'active' ? 'success' : 'warning',
                    'dismissible' => false,
                    'message' => $state === 'active' ? 'You are eligible to apply for services.' : 'Service applications are unavailable until membership activation.',
                ]) ?>
                <p class="cs-muted small mb-0">
                    <?= $state === 'active'
                        ? 'You can proceed to service requests from the Services & Packages page.'
                        : 'Service requests stay locked until your membership becomes active.' ?>
                </p>
            </div>
        </section>

        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Need assistance?</h2></div>
            <div class="cs-panel__body">
                <p class="mb-2"><strong>Branch:</strong> <?= esc($branch_name !== '' ? $branch_name : 'Kaagapay Branch') ?></p>
                <p class="mb-2"><strong>Hotline:</strong> 0912-345-6789</p>
                <p class="mb-2"><strong>Email:</strong> support@kaagapay.local</p>
                <p class="mb-0"><strong>Office hours:</strong> Mon-Sat, 8:00 AM - 5:00 PM</p>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
