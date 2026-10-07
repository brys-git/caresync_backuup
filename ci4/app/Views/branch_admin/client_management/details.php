<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
    $holderStatus = strtolower((string) ($holder['plan_holder_status'] ?? 'inactive'));
    $paymentStatus = strtolower((string) ($initial_payment['status'] ?? 'none'));
?>
<div class="mb-3 d-flex gap-2 justify-content-end">
    <a href="<?= base_url('branch-admin/client-management') ?>" class="btn btn-ghost btn-sm">Back to Client Management</a>
    <a href="<?= base_url('branch-admin/existing-records/client/' . (int) $holder['user_id']) ?>" class="btn btn-outline-primary btn-sm"><i class="ti ti-history" aria-hidden="true"></i> Add past records</a>
    <form method="post" action="<?= base_url('branch-admin/client-management/resend-login/' . $holder['plan_holder_id']) ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost btn-sm" data-cs-confirm="Issue a new temporary password? The client's current password will stop working.">Resend login details</button>
    </form>
</div>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<?= view('partials/plan_ledger_panel', ['ledger' => $ledger ?? null, 'reinstate_url' => $reinstate_url ?? null]) ?>

<div class="row g-3">
    <div class="col-lg-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Client Profile</h2></div>
            <div class="cs-panel__body">
                <div class="cs-tablewrap">
                    <table class="cs-table cs-table--compact mb-0">
                        <tbody>
                            <tr><th style="width:40%;">Name</th><td><?= esc((string) ($holder['first_name'] . ' ' . $holder['last_name'])) ?></td></tr>
                            <tr><th>Email</th><td><?= esc((string) ($holder['email'] ?? '-')) ?></td></tr>
                            <tr><th>Unique ID</th><td><?= esc((string) ($holder['unique_identifier'] ?: 'Not assigned')) ?></td></tr>
                            <tr><th>Address</th><td><?= esc(trim((string) (($holder['address_barangay'] ?? '') . ', ' . ($holder['address_city'] ?? '')), ' ,')) ?></td></tr>
                            <tr><th>Plan Holder Status</th><td><?= cs_status($holderStatus) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="col-lg-6">
        <section class="cs-panel h-100">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Initial Payment Verification</h2></div>
            <div class="cs-panel__body">
                <?php if (! empty($initial_payment)): ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table cs-table--compact mb-3">
                            <tbody>
                                <tr><th style="width:40%;">Payment ID</th><td>#<?= esc((string) ($initial_payment['payment_id'] ?? '-')) ?></td></tr>
                                <tr><th>Amount</th><td><?= cs_money($initial_payment['amount'] ?? 0) ?></td></tr>
                                <tr><th>Date</th><td><?= cs_date($initial_payment['payment_date'] ?? null) ?></td></tr>
                                <tr><th>Method</th><td><?= esc(strtoupper((string) ($initial_payment['payment_method'] ?? '-'))) ?></td></tr>
                                <tr><th>Reference</th><td><?= esc((string) ($initial_payment['reference_number'] ?? '-')) ?></td></tr>
                                <tr><th>Status</th><td><?= cs_status($paymentStatus) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary">No initial payment record found.</div>
                <?php endif; ?>

                <?php if ($can_approve): ?>
                    <form method="post" action="<?= base_url('branch-admin/client-management/approve/' . (int) $holder['plan_holder_id']) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-primary">Approve Registration</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn btn-ghost" disabled>Approve Registration</button>
                    <div class="form-text mt-2"><?= esc((string) $approval_message) ?></div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
