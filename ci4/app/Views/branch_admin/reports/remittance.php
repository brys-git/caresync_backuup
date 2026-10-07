<?= $this->extend($role_layout ?? 'layouts/branch_admin') ?>

<?= $this->section('content') ?>
<?php // The .no-print class is already hidden by layouts/_shell.php's own @media print rules - no local style block needed. ?>

<div class="mb-3 text-end no-print">
    <div class="d-flex gap-2 justify-content-end">
        <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener"
           href="<?= site_url('/branch-admin/reports/remittance?' . http_build_query(array_merge($filters, ['mode' => 'print']))) ?>">
            <i class="ti ti-printer me-1"></i> Print
        </a>
        <form method="post" action="<?= site_url('/branch-admin/reports/remittance/generate') ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="date_from" value="<?= esc($filters['date_from']) ?>">
            <input type="hidden" name="date_to" value="<?= esc($filters['date_to']) ?>">
            <input type="hidden" name="payment_method" value="<?= esc($filters['payment_method']) ?>">
            <input type="hidden" name="received_by" value="<?= esc((string) $filters['received_by']) ?>">
            <input type="hidden" name="search" value="<?= esc((string) ($filters['search'] ?? ''), 'attr') ?>">
            <input type="hidden" name="action" value="pdf">
            <button type="submit" class="btn btn-outline-primary btn-sm">Export PDF</button>
        </form>
        <form method="post" action="<?= site_url('/branch-admin/reports/remittance/generate') ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="date_from" value="<?= esc($filters['date_from']) ?>">
            <input type="hidden" name="date_to" value="<?= esc($filters['date_to']) ?>">
            <input type="hidden" name="payment_method" value="<?= esc($filters['payment_method']) ?>">
            <input type="hidden" name="received_by" value="<?= esc((string) $filters['received_by']) ?>">
            <input type="hidden" name="search" value="<?= esc((string) ($filters['search'] ?? ''), 'attr') ?>">
            <input type="hidden" name="action" value="csv">
            <button type="submit" class="btn btn-outline-primary btn-sm">Export Excel</button>
        </form>
    </div>
</div>

<?php $this->setData(['reports_base_path' => '/branch-admin/reports', 'reports_hidden_tabs' => ['ledger', 'collections'], 'active_report' => 'remittance']) ?>
<?= $this->include('partials/reports_nav') ?>

<section class="cs-panel mb-3 no-print">
    <div class="cs-panel__body">
        <form method="post" action="<?= site_url('/branch-admin/reports/remittance/generate') ?>">
            <?= csrf_field() ?>
            <div class="row g-3 align-items-end">
                <div class="col-12">
                    <label class="form-label" for="remit-search">Search</label>
                    <input id="remit-search" type="search" class="form-control" name="search" maxlength="100"
                           value="<?= esc((string) ($filters['search'] ?? ''), 'attr') ?>" placeholder="Client, recorded by, receipt or reference">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?= esc($filters['date_from']) ?>" required>
                </div>
                <div class="col-md-3">
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
                <div class="col-md-3">
                    <label class="form-label">Recorded By</label>
                    <select class="form-select" name="received_by">
                        <option value="0">All</option>
                        <?php foreach (($staff_options ?? []) as $staff): ?>
                            <option value="<?= (int) $staff['user_id'] ?>" <?= (int) $filters['received_by'] === (int) $staff['user_id'] ? 'selected' : '' ?>>
                                <?= esc(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary">Apply</button>
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
        <section class="cs-panel"><div class="cs-panel__body"><div class="text-muted small">Total Amount Collected</div><div class="h5 mb-0"><?= cs_money($summary['total_amount'] ?? 0) ?></div></div></section>
    </div>
    <div class="col-md-3">
        <section class="cs-panel"><div class="cs-panel__body"><div class="text-muted small">Cash Total</div><div class="h5 mb-0"><?= cs_money($summary['cash_total'] ?? 0) ?></div></div></section>
    </div>
    <div class="col-md-3">
        <section class="cs-panel"><div class="cs-panel__body"><div class="text-muted small">GCash Total</div><div class="h5 mb-0"><?= cs_money($summary['gcash_total'] ?? 0) ?></div></div></section>
    </div>
    <div class="col-md-3">
        <section class="cs-panel"><div class="cs-panel__body"><div class="text-muted small">Unverified</div><div class="h5 mb-0 text-warning"><?= (int) ($summary['pending_count'] ?? 0) ?> (<?= cs_money($summary['pending_amount'] ?? 0) ?>)</div></div></section>
    </div>
</div>

<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Remittance Transactions</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($report_rows ?? [])): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-receipt',
                'title' => 'No remittance transactions for selected filters',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client Name</th>
                            <th>Months</th>
                            <th class="cs-num">Amount</th>
                            <th>Method</th>
                            <th>Reference / OR</th>
                            <th>Recorded By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($report_rows ?? []) as $row): ?>
                            <tr>
                                <td class="text-nowrap"><?= cs_date($row['payment_date'] ?? null) ?></td>
                                <td><?= esc(trim(((string) ($row['client_first'] ?? '')) . ' ' . ((string) ($row['client_last'] ?? '')))) ?></td>
                                <td><?= esc((string) ((int) ($row['months_covered'] ?? 1))) ?></td>
                                <td class="cs-num"><?= cs_money($row['amount'] ?? 0) ?></td>
                                <td><?= esc(strtoupper((string) ($row['payment_method'] ?? ''))) ?></td>
                                <td><?= esc($row['reference_number'] ?? ($row['official_receipt_number'] ?? '-')) ?></td>
                                <td><?= esc(trim(((string) ($row['staff_first'] ?? '')) . ' ' . ((string) ($row['staff_last'] ?? '')))) ?></td>
                                <td><?= cs_status((string) ($row['status'] ?? 'pending')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="cs-panel">
    <div class="cs-panel__body">
        <h2 class="cs-panel__title mb-0">Total Remittance: <?= cs_money($total_remittance ?? 0) ?></h2>
    </div>
</section>
<?= $this->endSection() ?>
