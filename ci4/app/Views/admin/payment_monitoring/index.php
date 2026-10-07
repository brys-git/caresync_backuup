<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<form class="cs-filters mb-3" method="get" action="<?= base_url('admin/payment-monitoring') ?>">
    <div class="cs-filters__field cs-filters__field--wide">
        <label class="form-label" for="search">Search</label>
        <input id="search" name="search" type="search" class="form-control" maxlength="100"
               value="<?= esc((string) ($filters['search'] ?? '')) ?>" placeholder="Plan holder, plan number, receipt or reference">
    </div>
    <div class="cs-filters__field">
        <label class="form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select">
            <option value="" <?= ($filters['status'] ?? '') === '' ? 'selected' : '' ?>>All</option>
            <option value="pending" <?= ($filters['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="paid" <?= ($filters['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="cancelled" <?= ($filters['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </div>
    <div class="cs-filters__field">
        <label class="form-label" for="payment_method">Method</label>
        <select id="payment_method" name="payment_method" class="form-select">
            <option value="" <?= ($filters['payment_method'] ?? '') === '' ? 'selected' : '' ?>>All</option>
            <option value="cash" <?= ($filters['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="gcash" <?= ($filters['payment_method'] ?? '') === 'gcash' ? 'selected' : '' ?>>GCash</option>
        </select>
    </div>
    <div class="cs-filters__field">
        <label class="form-label" for="branch_id">Branch</label>
        <select id="branch_id" name="branch_id" class="form-select">
            <option value="0">All Branches</option>
            <?php foreach ($branches as $branch): ?>
                <option value="<?= (int) $branch['branch_id'] ?>" <?= (int) ($filters['branch_id'] ?? 0) === (int) $branch['branch_id'] ? 'selected' : '' ?>>
                    <?= esc((string) $branch['branch_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="cs-filters__field">
        <label class="form-label" for="date_from">Date From</label>
        <input id="date_from" name="date_from" type="date" class="form-control" value="<?= esc((string) ($filters['date_from'] ?? '')) ?>">
    </div>
    <div class="cs-filters__field">
        <label class="form-label" for="date_to">Date To</label>
        <input id="date_to" name="date_to" type="date" class="form-control" value="<?= esc((string) ($filters['date_to'] ?? '')) ?>">
    </div>
    <div class="cs-filters__actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        <?php if (array_filter($filters ?? [], static fn ($v) => $v !== '' && $v !== 0 && $v !== null)): ?>
            <a class="btn btn-ghost" href="<?= base_url('admin/payment-monitoring') ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<div class="d-flex justify-content-end mb-2">
    <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/payment-monitoring/export?' . http_build_query($filters ?? [])) ?>">
        <i class="ti ti-download me-1"></i> Export CSV
    </a>
</div>

<section class="cs-panel">
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if (empty($rows)): ?>
            <?= view('components/empty_state', [
                'icon'  => 'ti-receipt',
                'title' => ! empty($filters['search']) ? 'No payments match "' . $filters['search'] . '"' : 'No payment records found for selected filters',
                'text'  => ! empty($filters['search']) ? 'Check the spelling or try fewer words, then search again - or clear the search.' : '',
            ]) ?>
        <?php else: ?>
            <div class="cs-tablewrap">
                <?php $paymentService = new \App\Services\PaymentService(); ?>
                <table class="cs-table">
                    <thead>
                        <tr>
                            <th>Plan Holder</th>
                            <th>Branch</th>
                            <th>Coverage Period</th>
                            <th class="cs-num">Amount</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th>Reference / OR</th>
                            <th>Staff Account</th>
                            <th>Proof</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $coverageStart = (string) ($row['coverage_start'] ?? $row['payment_date'] ?? '');
                            $coverageLabel = $coverageStart !== ''
                                ? $paymentService->describeCoverage($coverageStart, (int) ($row['months_covered'] ?? 1))
                                : (int) ($row['months_covered'] ?? 1) . ' month(s)';
                            $staffName = trim((string) ($row['staff_first_name'] ?? '') . ' ' . (string) ($row['staff_last_name'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <?= esc((string) ($row['first_name'] . ' ' . $row['last_name'])) ?>
                                    <div class="cs-table__sub"><?= esc((string) ($row['unique_identifier'] ?: 'No ID')) ?></div>
                                </td>
                                <td><?= esc((string) ($row['branch_name'] ?? '-')) ?></td>
                                <td><?= esc($coverageLabel) ?></td>
                                <td class="cs-num"><?= cs_money($row['amount']) ?></td>
                                <td class="text-nowrap"><?= cs_date($row['payment_date']) ?></td>
                                <td><?= esc(strtoupper((string) $row['payment_method'])) ?></td>
                                <td><?= esc((string) ($row['reference_number'] ?: ($row['official_receipt_number'] ?: '-'))) ?></td>
                                <td><?= $staffName !== '' ? esc($staffName) : '<span class="text-muted">-</span>' ?></td>
                                <td>
                                    <?php if (! empty($supports_proof_upload) && ! empty($row['proof_image'] ?? null)): ?>
                                        <a href="<?= base_url('uploads/payment-proofs/' . $row['proof_image']) ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?= $this->endSection() ?>
