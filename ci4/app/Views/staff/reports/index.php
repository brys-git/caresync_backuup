<?= $this->extend($role_layout ?? 'layouts/staff') ?>

<?= $this->section('content') ?>
<?php // The .no-print class is already hidden by layouts/_shell.php's own @media print rules - no local style block needed. ?>

<div class="mb-3 text-end no-print">
    <div class="d-flex gap-2 justify-content-end">
        <a
            href="<?= site_url('/staff/reports?' . http_build_query(array_merge($filters, ['mode' => 'print']))) ?>"
            class="btn btn-ghost btn-sm"
            target="_blank"
            rel="noopener"
        >Print</a>
        <a
            href="<?= site_url('/staff/reports?' . http_build_query(array_merge($filters, ['mode' => 'pdf']))) ?>"
            class="btn btn-outline-primary btn-sm"
        >Export PDF</a>
        <a
            href="<?= site_url('/staff/reports?' . http_build_query(array_merge($filters, ['mode' => 'csv']))) ?>"
            class="btn btn-outline-primary btn-sm"
        >Export Excel</a>
    </div>
</div>

<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<section class="cs-panel mb-3 no-print">
    <div class="cs-panel__body">
        <form method="get" action="<?= site_url('/staff/reports') ?>">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?= esc($filters['date_from']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to" value="<?= esc($filters['date_to']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Method</label>
                    <select class="form-select" name="payment_method">
                        <option value="" <?= $filters['payment_method'] === '' ? 'selected' : '' ?>>All</option>
                        <option value="cash" <?= $filters['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="gcash" <?= $filters['payment_method'] === 'gcash' ? 'selected' : '' ?>>GCash</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>All</option>
                        <option value="paid" <?= $filters['status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Received By</label>
                    <select class="form-select" name="received_by">
                        <option value="0">All</option>
                        <?php foreach (($staff_options ?? []) as $staff): ?>
                            <option value="<?= (int) $staff['user_id'] ?>" <?= (int) $filters['received_by'] === (int) $staff['user_id'] ? 'selected' : '' ?>>
                                <?= esc(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?= esc($filters['search']) ?>" placeholder="Plan holder / ref #">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="<?= site_url('/staff/reports') ?>" class="btn btn-ghost">Reset</a>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </div>
        </form>
    </div>
</section>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <section class="cs-panel"><div class="cs-panel__body"><div class="text-muted small">Total Transactions</div><div class="h5 mb-0"><?= (int) ($summary['total_transactions'] ?? 0) ?></div></div></section>
    </div>
    <div class="col-md-3">
        <section class="cs-panel"><div class="cs-panel__body"><div class="text-muted small">Total Collected</div><div class="h5 mb-0"><?= cs_money($summary['total_collected'] ?? 0) ?></div></div></section>
    </div>
    <div class="col-md-3">
        <section class="cs-panel"><div class="cs-panel__body"><div class="text-muted small">Total Pending</div><div class="h5 mb-0"><?= cs_money($summary['total_pending'] ?? 0) ?></div></div></section>
    </div>
    <div class="col-md-3">
        <section class="cs-panel"><div class="cs-panel__body"><div class="text-muted small">Total Cancelled</div><div class="h5 mb-0"><?= cs_money($summary['total_cancelled'] ?? 0) ?></div></div></section>
    </div>
</div>

<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Remittance Transactions (View Only)</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($report_rows ?? [])): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-receipt',
                'title' => 'No payment records found for selected filters',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Plan Holder Name</th>
                            <th>Months</th>
                            <th class="cs-num">Amount</th>
                            <th>Payment Date</th>
                            <th>Payment Method</th>
                            <th>Reference / OR</th>
                            <th>Received By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($report_rows ?? []) as $row): ?>
                            <?php $status = strtolower((string) ($row['status'] ?? '')); ?>
                            <tr>
                                <td><?= esc(trim(((string) ($row['client_first'] ?? '')) . ' ' . ((string) ($row['client_last'] ?? '')))) ?></td>
                                <td><?= esc((string) ((int) ($row['months_covered'] ?? 1))) ?></td>
                                <td class="cs-num"><?= cs_money($row['amount'] ?? 0) ?></td>
                                <td class="text-nowrap"><?= cs_date($row['payment_date'] ?? null) ?></td>
                                <td><?= esc(strtoupper((string) ($row['payment_method'] ?? '-'))) ?></td>
                                <td><?= esc((string) ($row['reference_number'] ?? ($row['official_receipt_number'] ?? '-'))) ?></td>
                                <td><?= esc(trim(((string) ($row['staff_first'] ?? '')) . ' ' . ((string) ($row['staff_last'] ?? '')))) ?></td>
                                <td><?= cs_status($status) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
