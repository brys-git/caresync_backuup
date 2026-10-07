<?= $this->extend($role_layout ?? 'layouts/plan_holder') ?>

<?= $this->section('content') ?>
<?php if (session()->getFlashdata('success')): ?>
    <?= view('components/alert', ['type' => 'success', 'message' => session()->getFlashdata('success')]) ?>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
    <?= view('components/alert', ['type' => 'danger', 'message' => session()->getFlashdata('error')]) ?>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4 order-lg-2">
        <section class="cs-panel mb-3">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">Balance summary</h2>
            </div>
            <div class="cs-panel__body">
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Member</span><strong><?= esc(trim((string) ($balance['first_name'] ?? '') . ' ' . (string) ($balance['last_name'] ?? ''))) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Service</span><strong><?= esc((string) ($balance['service_name'] ?? $balance['package_name'] ?? '—')) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Status</span><?= cs_status((string) ($balance['status'] ?? '')) ?></div>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Package cost</span><strong class="cs-num"><?= cs_money($balance['package_cost'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Assistance</span><strong class="cs-num"><?= cs_money($balance['assistance_amount'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Remaining</span><strong class="cs-num"><?= cs_money($balance['remaining_balance'] ?? 0) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Beneficiary</span><strong><?= esc((string) ($balance['beneficiary_name'] ?? '—')) ?></strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="cs-muted">Relationship</span><strong><?= esc((string) ($balance['beneficiary_relationship'] ?? '—')) ?></strong></div>
                <div class="d-flex justify-content-between"><span class="cs-muted">Next due</span><strong><?= cs_date($balance['next_due_date'] ?? null) ?></strong></div>
            </div>
        </section>
    </div>

    <div class="col-lg-8 order-lg-1">
        <?php if (! empty($application)): ?>
            <section class="cs-panel mb-3">
                <div class="cs-panel__head"><h2 class="cs-panel__title">Application details</h2></div>
                <div class="cs-panel__body">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Deceased name:</strong> <?= esc((string) ($application['deceased_name'] ?? '—')) ?></div>
                        <div class="col-md-4"><strong>Date of death:</strong> <?= cs_date($application['deceased_date_of_death'] ?? null) ?></div>
                        <div class="col-md-4"><strong>Relationship:</strong> <?= esc((string) ($application['relationship_to_deceased'] ?? '—')) ?></div>
                        <div class="col-md-6"><strong>Deceased address:</strong> <?= esc((string) ($application['deceased_address'] ?? '—')) ?></div>
                        <div class="col-md-6"><strong>Beneficiary contact:</strong> <?= esc((string) ($application['beneficiary_contact'] ?? '—')) ?></div>
                        <div class="col-12"><strong>Application notes:</strong> <?= esc((string) ($application['application_notes'] ?? '—')) ?></div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if (! empty($documents)): ?>
            <section class="cs-panel mb-3">
                <div class="cs-panel__head"><h2 class="cs-panel__title">Submitted documents</h2></div>
                <div class="cs-panel__body cs-panel__body--flush">
                    <?php foreach ($documents as $document): ?>
                        <div class="cs-linkrow">
                            <span><?= esc((string) ($document['original_name'] ?? 'Unnamed document')) ?></span>
                            <?php if ((int) session('role_id') === 2): ?>
                                <a href="<?= site_url('/branch-admin/service-package/requests/document/' . (int) $document['document_id']) ?>" class="btn btn-ghost btn-sm ms-auto">Download</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ((int) session('role_id') === 4 && (string) ($balance['status'] ?? '') === 'pending_acknowledgment'): ?>
            <section class="cs-panel mb-3">
                <div class="cs-panel__head"><h2 class="cs-panel__title">Beneficiary acknowledgment</h2></div>
                <form method="post" action="<?= site_url('client/service-balances/acknowledge/' . (int) ($balance['service_balance_id'] ?? 0)) ?>">
                    <?= csrf_field() ?>
                    <div class="cs-panel__body">
                        <label class="form-label">Acknowledgment notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Accept remaining balance and installment terms."></textarea>
                    </div>
                    <div class="cs-panel__foot d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">Acknowledge remaining balance</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Recorded payments</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <?php if (empty($payments)): ?>
                    <?= view('components/empty_state', ['icon' => 'ti-cash-off', 'title' => 'No payments recorded yet']) ?>
                <?php else: ?>
                    <div class="cs-tablewrap">
                        <table class="cs-table">
                            <thead>
                                <tr><th>Date</th><th class="cs-num">Amount</th><th>Method</th><th>Reference</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td class="text-nowrap"><?= cs_date($payment['paid_at'] ?? null) ?></td>
                                        <td class="cs-num"><?= cs_money($payment['amount'] ?? 0) ?></td>
                                        <td><?= esc((string) ($payment['payment_method'] ?? '—')) ?></td>
                                        <td><?= esc((string) ($payment['reference_number'] ?? '—')) ?></td>
                                        <td><?= cs_status((string) ($payment['status'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (in_array((int) session('role_id'), [2, 4], true)): ?>
            <section class="cs-panel">
                <div class="cs-panel__head"><h2 class="cs-panel__title">Record payment</h2></div>
                <form method="post" action="<?= (int) session('role_id') === 2 ? site_url('branch-admin/service-balances/pay/' . (int) ($balance['service_balance_id'] ?? 0)) : site_url('client/service-balances/pay/' . (int) ($balance['service_balance_id'] ?? 0)) ?>">
                    <?= csrf_field() ?>
                    <div class="cs-panel__body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Amount</label>
                                <input class="form-control" type="number" step="0.01" min="0.01" name="amount" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Payment method</label>
                                <input class="form-control" type="text" name="payment_method" placeholder="cash / gcash / bank">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Reference number</label>
                                <input class="form-control" type="text" name="reference_number">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="cs-panel__foot d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">Save payment</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
