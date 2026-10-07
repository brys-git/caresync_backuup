<?= $this->extend($role_layout ?? 'layouts/admin') ?>

<?= $this->section('content') ?>
<?php $remitQuery = http_build_query($filters ?? []); ?>
<div class="mb-3 text-end">
    <a class="btn btn-ghost btn-sm" href="<?= site_url('/admin/reports/remittance?mode=print&' . $remitQuery) ?>" target="_blank" rel="noopener">
        <i class="ti ti-printer me-1"></i> Print
    </a>
    <a class="btn btn-ghost btn-sm" href="<?= site_url('/admin/reports/remittance?mode=csv&' . $remitQuery) ?>">
        <i class="ti ti-file-spreadsheet me-1"></i> Export Excel
    </a>
    <a class="btn btn-primary btn-sm" href="<?= site_url('/admin/reports/remittance?mode=pdf&' . $remitQuery) ?>">
        <i class="ti ti-file-type-pdf me-1"></i> Export PDF
    </a>
</div>

<?php $this->setData(['reports_base_path' => '/admin/reports']) ?>
<?= $this->include('partials/reports_nav') ?>

<section class="cs-panel mb-3">
    <div class="cs-panel__body">
        <form class="cs-filters" method="get" action="<?= site_url('/admin/reports/remittance') ?>">
            <div class="cs-filters__field cs-filters__field--wide">
                <label class="form-label">Search</label>
                <input type="search" name="search" class="form-control" maxlength="100" value="<?= esc((string) ($filters['search'] ?? ''), 'attr') ?>" placeholder="Client, collector, receipt or reference">
            </div>
            <div class="cs-filters__field">
                <label class="form-label">Date From</label>
                <input type="date" class="form-control" name="date_from" value="<?= esc((string) ($filters['date_from'] ?? '')) ?>">
            </div>
            <div class="cs-filters__field">
                <label class="form-label">Date To</label>
                <input type="date" class="form-control" name="date_to" value="<?= esc((string) ($filters['date_to'] ?? '')) ?>">
            </div>
            <div class="cs-filters__field">
                <label class="form-label">Method</label>
                <select class="form-select" name="payment_method">
                    <option value="" <?= ($filters['payment_method'] ?? '') === '' ? 'selected' : '' ?>>All</option>
                    <option value="cash" <?= ($filters['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="gcash" <?= ($filters['payment_method'] ?? '') === 'gcash' ? 'selected' : '' ?>>GCash</option>
                </select>
            </div>
            <div class="cs-filters__field">
                <label class="form-label">Branch</label>
                <select class="form-select" name="branch_id">
                    <option value="0">All Branches</option>
                    <?php foreach (($branches ?? []) as $branch): ?>
                        <option value="<?= (int) $branch['branch_id'] ?>" <?= (int) ($filters['branch_id'] ?? 0) === (int) $branch['branch_id'] ? 'selected' : '' ?>>
                            <?= esc((string) $branch['branch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cs-filters__actions">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>
</section>

<section class="cs-panel mb-3">
    <div class="cs-panel__body">
        <h2 class="cs-panel__title mb-0">Total Remittance: <?= cs_money($total_remittance ?? 0) ?></h2>
    </div>
</section>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($rows)): ?>
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
                            <th>Unique ID</th>
                            <th class="cs-num">Amount</th>
                            <th>Method</th>
                            <th>Reference / OR</th>
                            <th>Received By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="text-nowrap"><?= cs_date($row['payment_date'] ?? null) ?></td>
                                <td><?= esc(trim((string) ($row['client_first'] ?? '') . ' ' . (string) ($row['client_last'] ?? ''))) ?></td>
                                <td><?= esc((string) ($row['unique_identifier'] ?? '-')) ?></td>
                                <td class="cs-num"><?= cs_money($row['amount'] ?? 0) ?></td>
                                <td><?= esc(strtoupper((string) ($row['payment_method'] ?? ''))) ?></td>
                                <td><?= esc((string) ($row['reference_number'] ?? ($row['official_receipt_number'] ?? '-'))) ?></td>
                                <td><?= esc(trim((string) ($row['staff_first'] ?? '') . ' ' . (string) ($row['staff_last'] ?? ''))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
