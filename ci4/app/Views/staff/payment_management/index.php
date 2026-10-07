<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<?php $this->setData([
    'ap_action' => base_url('staff/payment-management/record-cash'),
    'ap_prefix' => 'staff_ap',
    'ap_plan_options' => $plan_options ?? [],
    'ap_heading' => 'Advance Payment Entry',
    'ap_button_label' => 'Record Advance Payment',
]) ?>
<?= $this->include('partials/advance_payment_form') ?>

<section class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Payment Records</h2>
        <form method="get" action="<?= base_url('staff/payment-management') ?>" class="d-flex flex-wrap gap-2 align-items-center" role="search">
            
            <input type="search" name="search" class="form-control form-control-sm" style="width:16rem" maxlength="100"
                   value="<?= esc((string) ($search ?? ''), 'attr') ?>" placeholder="Plan holder, plan number, receipt or reference" aria-label="Search payments">
            <button type="submit" class="btn btn-primary btn-sm"><i class="ti ti-search" aria-hidden="true"></i> Search</button>
            <?php if (! empty($search)): ?>
                <a class="btn btn-ghost btn-sm" href="<?= base_url('staff/payment-management') ?>?<?= http_build_query(array_filter(['tab' => '', 'status' => $selected_status ?? ''])) ?>">Clear</a>
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
            'ap_can_approve' => false,
            'ap_action_base' => '',
            'ap_show_proof' => false,
            'ap_show_remarks' => true,
            'ap_remarks_editable' => true,
            'ap_remarks_action_base' => base_url('staff/payment-management/save-remarks'),
            'ap_empty_message' => ! empty($search) ? 'No payment records match "' . $search . '". Check the spelling or clear the search.' : 'No payment records found.',
        ]) ?>
        <?= $this->include('partials/advance_payment_table') ?>
    </div>
</section>
<?= $this->endSection() ?>
