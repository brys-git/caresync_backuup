<?= $this->extend($role_layout ?? 'layouts/admin') ?>

<?= $this->section('content') ?>
<?php $this->setData(['reports_base_path' => $reports_base_path ?? '/admin/reports']) ?>
<?= $this->include('partials/reports_nav') ?>

<section class="cs-panel mb-3">
    <div class="cs-panel__body">
        <form class="cs-filters" method="get" action="<?= site_url(($reports_base_path ?? '/admin/reports') . '/ledger') ?>">
            <div class="cs-filters__field cs-filters__field--wide">
                <label class="form-label">Search by name, mobile number, email or plan number</label>
                <input type="text" name="q" class="form-control" value="<?= esc((string) ($query ?? '')) ?>" placeholder="e.g. Juan Dela Cruz, 0917…, or PH-00015-20260418">
            </div>
            <div class="cs-filters__actions">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>
    </div>
</section>

<?php if (! empty($query) && empty($ledger)): ?>
    <section class="cs-panel mb-3">
        <div class="cs-panel__body">
            <?php if (empty($matches)): ?>
                <p class="cs-muted mb-0">No plan holders matched "<?= esc((string) $query) ?>".</p>
            <?php else: ?>
                <p class="fw-semibold mb-2">Select a plan holder:</p>
                <div class="list-group">
                    <?php foreach ($matches as $match): ?>
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                           href="<?= site_url(($reports_base_path ?? '/admin/reports') . '/ledger?q=' . urlencode((string) $query) . '&plan_holder_id=' . (int) $match['plan_holder_id']) ?>">
                            <span><?= esc(trim((string) ($match['first_name'] ?? '') . ' ' . (string) ($match['last_name'] ?? ''))) ?> <small class="text-muted"><?= esc((string) ($match['unique_identifier'] ?? '-')) ?></small></span>
                            <span class="text-muted small"><?= esc((string) ($match['branch_name'] ?? '-')) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (! empty($ledger)): ?>
    <?php $holder = $ledger['holder']; $plan = $ledger['plan']; $payments = $ledger['payments']; ?>
    <section class="cs-panel mb-3">
        <div class="cs-panel__body">
            <div class="row gy-2">
                <div class="col-md-4"><strong>Plan Holder:</strong> <?= esc(trim((string) ($holder['first_name'] ?? '') . ' ' . (string) ($holder['last_name'] ?? ''))) ?></div>
                <div class="col-md-4"><strong>Unique ID:</strong> <?= esc((string) ($holder['unique_identifier'] ?? '-')) ?></div>
                <div class="col-md-4"><strong>Branch:</strong> <?= esc((string) ($holder['branch_name'] ?? '-')) ?></div>
                <div class="col-md-4"><strong>Contact:</strong> <?= esc((string) ($holder['contact_number'] ?? '-')) ?></div>
                <div class="col-md-4"><strong>Monthly Fee:</strong> <?= cs_money($plan['monthly_fee'] ?? 0) ?></div>
                <div class="col-md-4"><strong>Months Paid:</strong> <?= esc((string) ($plan['months_paid'] ?? 0)) ?></div>
                <div class="col-md-4"><strong>Remaining Balance:</strong> <?= cs_money($plan['remaining_balance'] ?? 0) ?></div>
                <div class="col-md-4"><strong>Next Due Date:</strong> <?= cs_date($plan['next_due_date'] ?? null) ?></div>
                <div class="col-md-4"><strong>Plan Status:</strong> <?= cs_status((string) ($plan['status'] ?? '')) ?></div>
            </div>
        </div>
    </section>

    <section class="cs-panel">
        <div class="cs-panel__body cs-panel__body--flush">
            <?php if (empty($payments)): ?>
                <?= view('components/empty_state', [
                    'icon'  => 'ti-receipt',
                    'title' => 'No payments recorded on this plan yet',
                ]) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="cs-num">Amount</th>
                                <th>Method</th>
                                <th>Reference / OR</th>
                                <th>Staff Account</th>
                                <th>Status</th>
                                <th class="cs-num">Running Total Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="text-nowrap"><?= cs_date($payment['payment_date']) ?></td>
                                    <td class="cs-num"><?= cs_money($payment['amount']) ?></td>
                                    <td><?= esc(strtoupper((string) $payment['payment_method'])) ?></td>
                                    <td><?= esc((string) ($payment['reference_number'] ?: ($payment['official_receipt_number'] ?: '-'))) ?></td>
                                    <td><?= esc(trim((string) ($payment['staff_first'] ?? '') . ' ' . (string) ($payment['staff_last'] ?? '')) ?: '-') ?></td>
                                    <td><?= cs_status((string) ($payment['status'] ?? 'pending')) ?></td>
                                    <td class="cs-num"><?= cs_money($payment['running_total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
<?= $this->endSection() ?>
