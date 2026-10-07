<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$activeTab = (string) ($active_tab ?? '');
if ($activeTab === '') {
    $activeTab = ($selected_status ?? '') !== '' ? 'monitoring' : 'record';
}
?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link <?= $activeTab === 'record' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#record-tab" type="button">Record Payment</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link <?= $activeTab === 'initial' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#initial-tab" type="button">Initial Payments</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link <?= $activeTab === 'monitoring' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#monitoring-tab" type="button">Monitoring</button></li>
    <li class="nav-item" role="presentation"><a class="nav-link" href="<?= base_url('branch-admin/payment-tracking?status=pending') ?>">Pending Verification</a></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade <?= $activeTab === 'record' ? 'show active' : '' ?>" id="record-tab">
        <?php $this->setData([
            'ap_action' => base_url('branch-admin/payment-tracking/record-cash'),
            'ap_prefix' => 'ba_record',
            'ap_plan_options' => $plan_options ?? [],
            'ap_heading' => 'Advance Payment Entry',
            'ap_button_label' => 'Record Advance Payment',
        ]) ?>
        <?= $this->include('partials/advance_payment_form') ?>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'initial' ? 'show active' : '' ?>" id="initial-tab">
        <?php $this->setData([
            'ap_action' => base_url('branch-admin/payment-tracking/record-cash'),
            'ap_prefix' => 'ba_initial',
            'ap_plan_options' => $initial_plan_options ?? [],
            'ap_heading' => 'Record Initial Payment',
            'ap_button_label' => 'Record Initial Payment',
        ]) ?>
        <?= $this->include('partials/advance_payment_form') ?>

        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Initial Payments</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php $this->setData([
                    'ap_rows' => $initial_rows ?? [],
                    'ap_can_approve' => (bool) ($can_approve ?? false),
                    'ap_action_base' => base_url('branch-admin/payment-tracking'),
                    'ap_show_proof' => (bool) ($supports_proof_upload ?? false),
                    'ap_empty_message' => 'No initial payment records found.',
                ]) ?>
                <?= $this->include('partials/advance_payment_table') ?>
            </div>
        </section>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'monitoring' ? 'show active' : '' ?>" id="monitoring-tab">
        <section class="cs-panel">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Payment Monitoring</h2>
                <form method="get" action="<?= base_url('branch-admin/payment-tracking') ?>" class="d-flex flex-wrap gap-2 align-items-center" role="search">
                    <input type="hidden" name="tab" value="monitoring">
                    <input type="search" name="search" class="form-control form-control-sm" style="width:16rem" maxlength="100"
                           value="<?= esc((string) ($search ?? ''), 'attr') ?>" placeholder="Plan holder, plan number, receipt or reference" aria-label="Search payments">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-search" aria-hidden="true"></i> Search</button>
                    <?php if (! empty($search)): ?>
                        <a class="btn btn-ghost btn-sm" href="<?= base_url('branch-admin/payment-tracking') ?>?<?= http_build_query(array_filter(['tab' => 'monitoring', 'status' => $selected_status ?? ''])) ?>">Clear</a>
                    <?php endif; ?>
                    <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="" <?= ($selected_status ?? '') === '' ? 'selected' : '' ?>>All</option>
                        <option value="pending" <?= ($selected_status ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= ($selected_status ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= ($selected_status ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </form>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php $this->setData([
                    'ap_rows' => $rows ?? [],
                    'ap_can_approve' => (bool) ($can_approve ?? false),
                    'ap_action_base' => base_url('branch-admin/payment-tracking'),
                    'ap_show_proof' => (bool) ($supports_proof_upload ?? false),
                    'ap_show_status' => false,
                    'ap_show_remarks' => true,
                    'ap_remarks_editable' => true,
                    'ap_remarks_action_base' => base_url('branch-admin/payment-tracking/save-remarks'),
                    'ap_remaining_from_months_paid' => true,
                    'ap_empty_message' => ! empty($search) ? 'No payment records match "' . $search . '". Check the spelling or clear the search.' : 'No payment records found.',
                ]) ?>
                <?= $this->include('partials/advance_payment_table') ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
