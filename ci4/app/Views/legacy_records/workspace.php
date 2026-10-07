<?php
/**
 * One client's past records. Tabs are plain links (?tab=...) so every action
 * is an ordinary form post and a reload always lands on the same tab.
 *
 * @var array $client  @var ?array $record  @var string $tab  @var string $base
 */
use App\Services\LegacyRecordService as L;

$name      = trim($client['first_name'] . ' ' . $client['last_name']);
$userId    = (int) $client['user_id'];
$hasHolder = ! empty($client['plan_holder_id']);
$old       = static fn (string $k, $default = '') => old($k) !== null ? old($k) : $default;
$v         = static fn (string $k, $default = '') => $record[$k] ?? $default;
$tabs      = ['plan' => 'Membership & plan', 'beneficiaries' => 'Beneficiaries', 'payments' => 'Payment history', 'remittances' => 'Remittances', 'services' => 'Services & claims', 'documents' => 'Documents', 'notes' => 'Notes'];
$pendingCount = 0;
foreach ([$payments, $remittances, $services, $documents, $notes] as $set) {
    foreach ($set as $r) { if ($r['record_status'] === 'pending_verification') { $pendingCount++; } }
}
foreach ($beneficiaries as $b) { if (($b['source'] ?? '') === 'legacy_manual' && $b['verification_status'] === 'pending') { $pendingCount++; } }
if ($record && $record['record_status'] === 'pending_verification') { $pendingCount++; }
$statusBadge = static fn (array $r) => cs_status($r['record_status'], match ($r['record_status']) { 'pending_verification' => 'Pending verification', 'verified' => 'Verified', default => 'Rejected' });
$delete = static function (string $type, int $id, string $base, string $confirm = 'Remove this record?'): string {
    return '<form method="post" action="' . esc($base . '/delete/' . $type . '/' . $id) . '" class="d-inline">' . csrf_field()
        . '<button type="submit" class="btn btn-ghost btn-sm" data-cs-confirm="' . esc($confirm, 'attr') . '"><i class="ti ti-trash" aria-hidden="true"></i><span class="cs-visually-hidden">Remove</span></button></form>';
};
?>
<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are surfaced as toasts by layouts/_shell.php. ?>
<?php if (session()->getFlashdata('warning')): ?>
    <div class="alert alert-warning" role="alert">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <span><?= esc((string) session()->getFlashdata('warning')) ?></span>
    </div>
<?php endif; ?>

<section class="cs-panel mb-3">
    <div class="cs-panel__body">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-1"><?= esc($name) ?></h2>
                <div class="cs-muted">
                    Branch: <strong><?= esc((string) ($client['branch_name'] ?: '—')) ?></strong>
                    · Plan number: <strong><?= esc((string) ($client['unique_identifier'] ?: 'assigned when the first record is saved')) ?></strong>
                </div>
                <div class="mt-2 d-flex flex-wrap gap-2 align-items-center">
                    <span class="cs-muted">Account status:</span>
                    <?= cs_status((string) ($client['plan_holder_status'] ?: $client['account_status'])) ?>
                    <?php if ($plan): ?><span class="cs-muted">Plan:</span> <?= cs_status((string) $plan['status']) ?><?php endif; ?>
                </div>
            </div>
            <div class="text-end">
                <div class="cs-muted">Verified past payments</div>
                <div class="fs-4 fw-semibold"><?= cs_money($total_paid) ?></div>
                <div class="cs-muted"><?= (int) $months_paid ?> month(s) counted
                    <?php if ($plan && $plan['payment_coverage_until']): ?> · covered until <?= esc(cs_date($plan['payment_coverage_until'])) ?><?php endif; ?></div>
            </div>
        </div>
        <?php if (! $is_admin_role): ?>
            <p class="cs-muted mt-3 mb-0"><i class="ti ti-info-circle" aria-hidden="true"></i>
                Records you save are marked <strong>Pending verification</strong> and only count toward the client's totals after your Branch Admin verifies them.</p>
        <?php elseif ($pendingCount > 0): ?>
            <p class="cs-muted mt-3 mb-0"><i class="ti ti-info-circle" aria-hidden="true"></i>
                <?= (int) $pendingCount ?> record(s) for this client are waiting for verification.
                <a href="<?= esc($base . '/pending') ?>">Review them</a>.</p>
        <?php endif; ?>
    </div>
</section>

<?= view('partials/plan_ledger_panel', ['ledger' => $ledger ?? null, 'reinstate_url' => $reinstate_url ?? null]) ?>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $tab === $key ? 'active' : '' ?>" href="<?= esc($base . '/client/' . $userId . '?tab=' . $key) ?>"><?= esc($label) ?></a></li>
    <?php endforeach; ?>
</ul>

<?php // ============================ PLAN ============================ ?>
<?php if ($tab === 'plan'): ?>
<section class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Membership and plan</h2>
        <?php if ($record): ?><?= $statusBadge($record) ?><?php endif; ?>
    </div>
    <div class="cs-panel__body">
        <?php if ($record && $record['record_status'] === 'rejected'): ?>
            <div class="alert alert-danger"><i class="ti ti-alert-circle" aria-hidden="true"></i>
                <span>Rejected by the Branch Admin: <?= esc((string) $record['rejection_reason']) ?>. Correct the details and save again.</span></div>
        <?php endif; ?>
        <form method="post" action="<?= esc($base . '/client/' . $userId . '/plan') ?>" data-cs-once>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="plan_name">Plan / package name</label>
                    <input class="form-control" id="plan_name" name="plan_name" maxlength="150" value="<?= esc((string) $old('plan_name', $v('plan_name'))) ?>" placeholder="e.g. Damayan Burial Program">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="package_id">Linked package in the catalog (optional)</label>
                    <select class="form-select" id="package_id" name="package_id">
                        <option value="">None</option>
                        <?php foreach ($packages as $p): ?>
                            <option value="<?= (int) $p['package_id'] ?>" <?= (int) $old('package_id', $v('package_id', 0)) === (int) $p['package_id'] ? 'selected' : '' ?>><?= esc($p['package_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="contract_number">Plan / contract number</label>
                    <input class="form-control" id="contract_number" name="contract_number" maxlength="60" value="<?= esc((string) $old('contract_number', $v('contract_number'))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="original_membership_date">Original membership date <span class="text-danger">*</span></label>
                    <input class="form-control" type="date" id="original_membership_date" name="original_membership_date" max="<?= date('Y-m-d') ?>" value="<?= esc((string) $old('original_membership_date', $v('original_membership_date'))) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="original_branch_id">Branch originally joined</label>
                    <select class="form-select" id="original_branch_id" name="original_branch_id">
                        <option value="">Same as current branch</option>
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= (int) $b['branch_id'] ?>" <?= (int) $old('original_branch_id', $v('original_branch_id', 0)) === (int) $b['branch_id'] ? 'selected' : '' ?>><?= esc($b['branch_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="monthly_contribution">Monthly contribution (₱) <span class="text-danger">*</span></label>
                    <input class="form-control" type="number" step="0.01" min="0.01" id="monthly_contribution" name="monthly_contribution" value="<?= esc((string) $old('monthly_contribution', $v('monthly_contribution', '240.00'))) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="payment_frequency">Payment frequency</label>
                    <select class="form-select" id="payment_frequency" name="payment_frequency">
                        <?php foreach (L::FREQUENCIES as $key => $label): ?>
                            <option value="<?= $key ?>" <?= (string) $old('payment_frequency', $v('payment_frequency', 'monthly')) === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="total_contract_amount">Total contract amount (₱)</label>
                    <input class="form-control" type="number" step="0.01" min="0" id="total_contract_amount" name="total_contract_amount" value="<?= esc((string) $old('total_contract_amount', $v('total_contract_amount', '14500.00'))) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="declared_payments_made">Number of payments made</label>
                    <input class="form-control" type="number" min="0" max="600" id="declared_payments_made" name="declared_payments_made" value="<?= esc((string) $old('declared_payments_made', $v('declared_payments_made'))) ?>">
                    <?php if ($record && $record['declared_payments_made'] !== null && (int) $record['declared_payments_made'] !== (int) $months_paid): ?>
                        <div class="form-text text-warning">The payment history adds up to <?= (int) $months_paid ?> verified payment(s), not <?= (int) $record['declared_payments_made'] ?>.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="plan_status">Status on the old record <span class="text-danger">*</span></label>
                    <select class="form-select" id="plan_status" name="plan_status" required>
                        <option value="">Choose…</option>
                        <?php foreach (L::PLAN_STATUSES as $key => $label): ?>
                            <option value="<?= $key ?>" <?= (string) $old('plan_status', $v('plan_status')) === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">For reference only. CareSync works out the real status from the payments, the same way everywhere.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="assigned_collector_id">Assigned collector (optional)</label>
                    <select class="form-select" id="assigned_collector_id" name="assigned_collector_id">
                        <option value="">None</option>
                        <?php foreach ($collectors as $c): ?>
                            <option value="<?= (int) $c['user_id'] ?>" <?= (int) $old('assigned_collector_id', $v('assigned_collector_id', 0)) === (int) $c['user_id'] ? 'selected' : '' ?>><?= esc(trim($c['first_name'] . ' ' . $c['last_name'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button class="btn btn-primary mt-4" type="submit">Save plan details</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php // ======================== BENEFICIARIES ======================== ?>
<?php if ($tab === 'beneficiaries'): ?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Beneficiaries (<?= count($beneficiaries) ?>)</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if ($beneficiaries === []): ?>
            <?= view('components/empty_state', ['icon' => 'ti-users', 'title' => 'No beneficiaries yet', 'text' => 'Add the people named on the member\'s old contract below.']) ?>
        <?php else: ?>
            <div class="cs-tablewrap"><table class="cs-table">
                <thead><tr><th>Name</th><th>Relationship</th><th>Birth date</th><th>Contact</th><th>Status</th><th class="cs-table__actions">Action</th></tr></thead>
                <tbody>
                <?php foreach ($beneficiaries as $b): $legacy = ($b['source'] ?? '') === 'legacy_manual'; ?>
                    <tr>
                        <td><?= esc(trim($b['first_name'] . ' ' . ($b['middle_name'] ?? '') . ' ' . $b['last_name'])) ?><?= (int) $b['is_primary'] === 1 ? ' <span class="cs-muted">(primary)</span>' : '' ?></td>
                        <td><?= esc($b['relationship']) ?></td>
                        <td><?= esc($b['date_of_birth'] ? cs_date($b['date_of_birth']) : '—') ?></td>
                        <td><?= esc((string) ($b['contact_number'] ?: '—')) ?></td>
                        <td><?= $legacy ? cs_status($b['verification_status'] === 'pending' ? 'pending_verification' : $b['verification_status'], $b['verification_status'] === 'pending' ? 'Pending verification' : ucfirst($b['verification_status'])) : '<span class="cs-muted">Registration</span>' ?></td>
                        <td class="cs-table__actions"><?= $legacy ? $delete('beneficiary', (int) $b['beneficiary_id'], $base) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        <?php endif; ?>
    </div>
</section>
<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Add a beneficiary</h2></div>
    <div class="cs-panel__body">
        <form method="post" action="<?= esc($base . '/client/' . $userId . '/beneficiary') ?>" data-cs-once>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="b_first">First name <span class="text-danger">*</span></label><input class="form-control" id="b_first" name="first_name" maxlength="100" value="<?= esc((string) old('first_name')) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="b_mid">Middle name</label><input class="form-control" id="b_mid" name="middle_name" maxlength="100" value="<?= esc((string) old('middle_name')) ?>"></div>
                <div class="col-md-4"><label class="form-label" for="b_last">Last name <span class="text-danger">*</span></label><input class="form-control" id="b_last" name="last_name" maxlength="100" value="<?= esc((string) old('last_name')) ?>" required></div>
                <div class="col-md-4">
                    <label class="form-label" for="b_rel">Relationship <span class="text-danger">*</span></label>
                    <select class="form-select" id="b_rel" name="relationship" required>
                        <option value="">Choose…</option>
                        <?php foreach ($relationships as $key => $label): ?>
                            <option value="<?= esc($key) ?>" <?= (string) old('relationship') === (string) $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label" for="b_other">If "Other", specify</label><input class="form-control" id="b_other" name="relationship_other" maxlength="50" value="<?= esc((string) old('relationship_other')) ?>"></div>
                <div class="col-md-2"><label class="form-label" for="b_dob">Birth date</label><input class="form-control" type="date" id="b_dob" name="birthday" max="<?= date('Y-m-d') ?>" value="<?= esc((string) old('birthday')) ?>"></div>
                <div class="col-md-2"><label class="form-label" for="b_phone">Contact number</label><input class="form-control" id="b_phone" name="contact_number" maxlength="30" value="<?= esc((string) old('contact_number')) ?>" placeholder="09xxxxxxxxx"></div>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Add beneficiary</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php // ========================== PAYMENTS ========================== ?>
<?php if ($tab === 'payments'): ?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Payment history (<?= count($payments) ?>)</h2>
        <a class="btn btn-outline-primary btn-sm" href="<?= esc($base . '/import') ?>"><i class="ti ti-file-import" aria-hidden="true"></i> Import from file</a>
    </div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if ($payments === []): ?>
            <?= view('components/empty_state', ['icon' => 'ti-cash', 'title' => 'No past payments recorded yet', 'text' => 'Enter them below, one row per payment, or import a file.']) ?>
        <?php else: ?>
            <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
                <thead><tr><th>Paid on</th><th>Period</th><th class="text-end">Amount</th><th>OR no.</th><th>Method</th><th>Collected by</th><th>Remarks</th><th>Status</th><th class="cs-table__actions">Action</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= esc(cs_date($p['payment_date'])) ?></td>
                        <td><?= esc(date('M Y', mktime(0, 0, 0, (int) $p['period_month'], 1, (int) $p['period_year']))) ?><?= (int) $p['months_covered'] > 1 ? ' <span class="cs-muted">+' . ((int) $p['months_covered'] - 1) . ' mo</span>' : '' ?></td>
                        <td class="text-end"><?= cs_money($p['amount']) ?></td>
                        <td><?= esc((string) ($p['or_number'] ?: '—')) ?></td>
                        <td><?= esc(L::CHANNELS[$p['channel']] ?? $p['channel']) ?></td>
                        <td><?= esc((string) ($p['collected_by'] ?: '—')) ?></td>
                        <td><?= esc((string) ($p['remarks'] ?: '')) ?></td>
                        <td><?= $statusBadge($p) ?><?= $p['record_status'] === 'rejected' && $p['rejection_reason'] ? '<div class="cs-table__sub">' . esc($p['rejection_reason']) . '</div>' : '' ?></td>
                        <td class="cs-table__actions">
                            <details class="d-inline">
                                <summary class="btn btn-ghost btn-sm" role="button"><i class="ti ti-pencil" aria-hidden="true"></i><span class="cs-visually-hidden">Edit</span></summary>
                                <form method="post" action="<?= esc($base . '/client/' . $userId . '/payment/' . (int) $p['legacy_payment_id']) ?>" class="border rounded p-2 mt-2 text-start" style="min-width:20rem">
                                    <?= csrf_field() ?>
                                    <div class="row g-2">
                                        <div class="col-6"><label class="form-label">Paid on</label><input class="form-control form-control-sm" type="date" name="payment_date" value="<?= esc($p['payment_date']) ?>" required></div>
                                        <div class="col-6"><label class="form-label">Amount</label><input class="form-control form-control-sm" type="number" step="0.01" name="amount" value="<?= esc($p['amount']) ?>" required></div>
                                        <div class="col-4"><label class="form-label">Month</label><input class="form-control form-control-sm" type="number" min="1" max="12" name="period_month" value="<?= (int) $p['period_month'] ?>" required></div>
                                        <div class="col-4"><label class="form-label">Year</label><input class="form-control form-control-sm" type="number" name="period_year" value="<?= (int) $p['period_year'] ?>" required></div>
                                        <div class="col-4"><label class="form-label">Months</label><input class="form-control form-control-sm" type="number" min="1" max="60" name="months_covered" value="<?= (int) $p['months_covered'] ?>"></div>
                                        <div class="col-6"><label class="form-label">OR no.</label><input class="form-control form-control-sm" name="or_number" maxlength="100" value="<?= esc((string) $p['or_number']) ?>"></div>
                                        <div class="col-6"><label class="form-label">Method</label>
                                            <select class="form-select form-select-sm" name="channel"><?php foreach (L::CHANNELS as $k => $lbl): ?><option value="<?= $k ?>" <?= $p['channel'] === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option><?php endforeach; ?></select></div>
                                        <div class="col-6"><label class="form-label">Collected by</label><input class="form-control form-control-sm" name="collected_by" maxlength="120" value="<?= esc((string) $p['collected_by']) ?>"></div>
                                        <div class="col-6"><label class="form-label">Remarks</label><input class="form-control form-control-sm" name="remarks" maxlength="255" value="<?= esc((string) $p['remarks']) ?>"></div>
                                    </div>
                                    <button class="btn btn-primary btn-sm mt-2" type="submit">Save changes</button>
                                    <?php if (! $is_admin_role): ?><div class="cs-muted mt-1">Editing sends it back to pending verification.</div><?php endif; ?>
                                </form>
                            </details>
                            <?= $delete('payment', (int) $p['legacy_payment_id'], $base, 'Remove this payment? The client\'s totals will be recalculated.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="2">Verified total</th><th class="text-end"><?= cs_money($total_paid) ?></th><th colspan="6"><?= (int) $months_paid ?> month(s) counted</th></tr></tfoot>
            </table></div>
        <?php endif; ?>
    </div>
</section>

<?php if ($missing !== []): ?>
    <div class="alert alert-warning" role="alert">
        <i class="ti ti-calendar-off" aria-hidden="true"></i>
        <span><strong><?= count($missing) ?> month(s) with no payment</strong> between the original membership date and the last month covered:
            <?= esc(implode(', ', array_slice($missing, 0, 36))) ?><?= count($missing) > 36 ? ' …' : '' ?></span>
    </div>
<?php endif; ?>

<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Add payments</h2></div>
    <div class="cs-panel__body">
        <?php foreach ($pay_errors as $i => $messages): ?>
            <div class="alert alert-danger py-2" role="alert"><i class="ti ti-alert-circle" aria-hidden="true"></i>
                <span><strong>Row <?= (int) $i + 1 ?> not saved:</strong> <?= esc(implode('; ', $messages)) ?></span></div>
        <?php endforeach; ?>
        <form method="post" action="<?= esc($base . '/client/' . $userId . '/payments') ?>" id="pay-form" data-cs-once>
            <?= csrf_field() ?>
            <div class="cs-tablewrap">
                <table class="cs-table cs-table--compact" id="pay-table">
                    <thead><tr><th>Paid on</th><th>Amount (₱)</th><th>Month</th><th>Year</th><th>Months</th><th>OR no.</th><th>Method</th><th>Collected / received by</th><th>Remarks</th><th></th></tr></thead>
                    <tbody id="pay-rows"></tbody>
                    <tfoot><tr><th>Total</th><th id="pay-total">₱0.00</th><th colspan="8"></th></tr></tfoot>
                </table>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-outline-primary btn-sm" id="pay-add"><i class="ti ti-plus" aria-hidden="true"></i> Add row</button>
                <button type="submit" class="btn btn-primary btn-sm">Save payments</button>
            </div>
            <p class="cs-muted mt-2 mb-0">Duplicate OR numbers in the same branch are blocked. A second payment for the same client and month is saved with a warning.</p>
        </form>
    </div>
</section>

<template id="pay-row-template">
    <tr>
        <td><input class="form-control form-control-sm" type="date" data-f="payment_date" max="<?= date('Y-m-d') ?>"></td>
        <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" data-f="amount" style="min-width:6rem"></td>
        <td><input class="form-control form-control-sm" type="number" min="1" max="12" data-f="period_month" style="width:4.5rem"></td>
        <td><input class="form-control form-control-sm" type="number" min="1990" max="<?= (int) date('Y') + 1 ?>" data-f="period_year" style="width:5.5rem"></td>
        <td><input class="form-control form-control-sm" type="number" min="1" max="60" data-f="months_covered" value="1" style="width:4.5rem"></td>
        <td><input class="form-control form-control-sm" data-f="or_number" maxlength="100" style="min-width:7rem"></td>
        <td><select class="form-select form-select-sm" data-f="channel"><?php foreach (L::CHANNELS as $k => $lbl): ?><option value="<?= $k ?>"><?= esc($lbl) ?></option><?php endforeach; ?></select></td>
        <td><input class="form-control form-control-sm" data-f="collected_by" maxlength="120" style="min-width:8rem"></td>
        <td><input class="form-control form-control-sm" data-f="remarks" maxlength="255" style="min-width:8rem"></td>
        <td><button type="button" class="btn btn-ghost btn-sm" data-remove aria-label="Remove row"><i class="ti ti-x" aria-hidden="true"></i></button></td>
    </tr>
</template>
<script>
(function () {
    var body = document.getElementById('pay-rows');
    var tpl = document.getElementById('pay-row-template');
    var total = document.getElementById('pay-total');
    var seed = <?= json_encode(array_values($pay_old), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var index = 0;

    function recalc() {
        var sum = 0;
        body.querySelectorAll('[data-f="amount"]').forEach(function (i) { sum += parseFloat(i.value) || 0; });
        total.textContent = '₱' + sum.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function addRow(values) {
        var row = tpl.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-f]').forEach(function (el) {
            var f = el.getAttribute('data-f');
            el.name = 'rows[' + index + '][' + f + ']';
            if (values && values[f] !== undefined && values[f] !== null) { el.value = values[f]; }
        });
        index++;
        body.appendChild(row);
        recalc();
    }
    document.getElementById('pay-add').addEventListener('click', function () {
        // New row inherits the next month so a long run is quick to type.
        var last = body.lastElementChild, v = {};
        if (last) {
            var g = function (f) { return last.querySelector('[data-f="' + f + '"]').value; };
            var m = parseInt(g('period_month'), 10), y = parseInt(g('period_year'), 10);
            if (m && y) { m++; if (m > 12) { m = 1; y++; } v.period_month = m; v.period_year = y; v.amount = g('amount'); v.channel = g('channel'); v.collected_by = g('collected_by'); }
        }
        addRow(v);
    });
    body.addEventListener('input', recalc);
    body.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove]');
        if (btn) { btn.closest('tr').remove(); recalc(); }
    });
    if (seed.length) { seed.forEach(addRow); } else { addRow({}); }
})();
</script>
<?php endif; ?>

<?php // ========================= REMITTANCES ========================= ?>
<?php if ($tab === 'remittances'): ?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Remittances (<?= count($remittances) ?>)</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if ($remittances === []): ?>
            <?= view('components/empty_state', ['icon' => 'ti-arrows-exchange', 'title' => 'No remittances recorded', 'text' => 'Only needed when a collector brought this member\'s payments to the branch.']) ?>
        <?php else: ?>
            <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
                <thead><tr><th>Date</th><th>Collector</th><th class="text-end">Amount</th><th>Reference</th><th>Covers</th><th>Status</th><th class="cs-table__actions">Action</th></tr></thead>
                <tbody>
                <?php foreach ($remittances as $r): ?>
                    <tr>
                        <td><?= esc(cs_date($r['remittance_date'])) ?></td>
                        <td><?= esc((string) $r['collector_name']) ?></td>
                        <td class="text-end"><?= cs_money($r['amount']) ?></td>
                        <td><?= esc((string) ($r['reference_number'] ?: '—')) ?></td>
                        <td><?= $r['covers_payments'] ? count(explode(',', $r['covers_payments'])) . ' payment(s)' : '—' ?></td>
                        <td><?= $statusBadge($r) ?></td>
                        <td class="cs-table__actions"><?= $delete('remittance', (int) $r['legacy_remittance_id'], $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        <?php endif; ?>
    </div>
</section>
<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Add a remittance</h2></div>
    <div class="cs-panel__body">
        <form method="post" action="<?= esc($base . '/client/' . $userId . '/remittance') ?>" data-cs-once>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label" for="r_date">Remittance date <span class="text-danger">*</span></label><input class="form-control" type="date" id="r_date" name="remittance_date" max="<?= date('Y-m-d') ?>" value="<?= esc((string) old('remittance_date')) ?>" required></div>
                <div class="col-md-3"><label class="form-label" for="r_col">Collector</label>
                    <select class="form-select" id="r_col" name="collector_id"><option value="">Not in the system…</option>
                        <?php foreach ($collectors as $c): ?><option value="<?= (int) $c['user_id'] ?>"><?= esc(trim($c['first_name'] . ' ' . $c['last_name'])) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label" for="r_name">…or collector's name</label><input class="form-control" id="r_name" name="collector_name" maxlength="120" value="<?= esc((string) old('collector_name')) ?>"></div>
                <div class="col-md-3"><label class="form-label" for="r_amt">Amount remitted (₱) <span class="text-danger">*</span></label><input class="form-control" type="number" step="0.01" min="0.01" id="r_amt" name="amount" value="<?= esc((string) old('amount')) ?>" required></div>
                <div class="col-md-4"><label class="form-label" for="r_ref">Reference number</label><input class="form-control" id="r_ref" name="reference_number" maxlength="100" value="<?= esc((string) old('reference_number')) ?>"></div>
                <div class="col-md-8"><label class="form-label" for="r_rem">Remarks</label><input class="form-control" id="r_rem" name="remarks" maxlength="255" value="<?= esc((string) old('remarks')) ?>"></div>
                <?php if ($payments !== []): ?>
                    <div class="col-12">
                        <span class="form-label d-block">Payments this remittance covers</span>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($payments as $p): ?>
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="covers_payments[]" value="<?= (int) $p['legacy_payment_id'] ?>">
                                    <span class="form-check-label"><?= esc(date('M Y', mktime(0, 0, 0, (int) $p['period_month'], 1, (int) $p['period_year']))) ?> · <?= cs_peso($p['amount']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Save remittance</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php // =========================== SERVICES =========================== ?>
<?php if ($tab === 'services'): ?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Services and claims already availed (<?= count($services) ?>)</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if ($services === []): ?>
            <?= view('components/empty_state', ['icon' => 'ti-clipboard-list', 'title' => 'Nothing recorded', 'text' => 'Record any service or claim the member already used before CareSync.']) ?>
        <?php else: ?>
            <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
                <thead><tr><th>Service / package</th><th>Date</th><th>Outcome</th><th class="text-end">Amount</th><th>Remarks</th><th>Status</th><th class="cs-table__actions">Action</th></tr></thead>
                <tbody>
                <?php foreach ($services as $s): ?>
                    <tr>
                        <td><?= esc($s['service_name']) ?></td>
                        <td><?= esc(cs_date($s['service_date'])) ?></td>
                        <td><?= esc(L::SERVICE_STATUSES[$s['service_status']] ?? $s['service_status']) ?></td>
                        <td class="text-end"><?= $s['amount'] !== null ? cs_money($s['amount']) : '—' ?></td>
                        <td><?= esc((string) $s['remarks']) ?></td>
                        <td><?= $statusBadge($s) ?></td>
                        <td class="cs-table__actions"><?= $delete('service', (int) $s['legacy_service_id'], $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        <?php endif; ?>
    </div>
</section>
<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Add a service or claim</h2></div>
    <div class="cs-panel__body">
        <form method="post" action="<?= esc($base . '/client/' . $userId . '/service') ?>" data-cs-once>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="s_name">Service / package <span class="text-danger">*</span></label><input class="form-control" id="s_name" name="service_name" maxlength="150" value="<?= esc((string) old('service_name')) ?>" required></div>
                <div class="col-md-2"><label class="form-label" for="s_date">Date <span class="text-danger">*</span></label><input class="form-control" type="date" id="s_date" name="service_date" max="<?= date('Y-m-d') ?>" value="<?= esc((string) old('service_date')) ?>" required></div>
                <div class="col-md-3"><label class="form-label" for="s_status">Status</label>
                    <select class="form-select" id="s_status" name="service_status"><?php foreach (L::SERVICE_STATUSES as $k => $lbl): ?><option value="<?= $k ?>"><?= esc($lbl) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label" for="s_amt">Amount (₱)</label><input class="form-control" type="number" step="0.01" min="0" id="s_amt" name="amount" value="<?= esc((string) old('amount')) ?>"></div>
                <div class="col-12"><label class="form-label" for="s_rem">Remarks</label><input class="form-control" id="s_rem" name="remarks" maxlength="255" value="<?= esc((string) old('remarks')) ?>"></div>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Save</button>
        </form>
    </div>
</section>
<?php endif; ?>

<?php // =========================== DOCUMENTS =========================== ?>
<?php if ($tab === 'documents'): ?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Scanned documents (<?= count($documents) ?>)</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if ($documents === []): ?>
            <?= view('components/empty_state', ['icon' => 'ti-file-text', 'title' => 'No documents uploaded', 'text' => 'Scan the old ledger card, contract, receipts or ID and upload them here.']) ?>
        <?php else: ?>
            <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
                <thead><tr><th>Type</th><th>File</th><th>Size</th><th>Uploaded</th><th>Status</th><th class="cs-table__actions">Action</th></tr></thead>
                <tbody>
                <?php foreach ($documents as $d): ?>
                    <tr>
                        <td><?= esc(L::DOC_TYPES[$d['doc_type']] ?? $d['doc_type']) ?></td>
                        <td><a href="<?= esc($base . '/document/' . (int) $d['legacy_document_id']) ?>" target="_blank" rel="noopener"><?= esc($d['original_name']) ?></a></td>
                        <td><?= esc(number_format($d['file_size'] / 1024, 0)) ?> KB</td>
                        <td><?= esc(cs_date($d['encoded_at'])) ?></td>
                        <td><?= $statusBadge($d) ?></td>
                        <td class="cs-table__actions"><?= $delete('document', (int) $d['legacy_document_id'], $base, 'Remove this document? The file is deleted.') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        <?php endif; ?>
    </div>
</section>
<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Upload a document</h2></div>
    <div class="cs-panel__body">
        <form method="post" action="<?= esc($base . '/client/' . $userId . '/document') ?>" enctype="multipart/form-data" data-cs-once>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="d_type">What is it?</label>
                    <select class="form-select" id="d_type" name="doc_type" required><?php foreach (L::DOC_TYPES as $k => $lbl): ?><option value="<?= $k ?>"><?= esc($lbl) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-8"><label class="form-label" for="d_file">File (PDF, JPG or PNG, up to <?= (int) (L::MAX_UPLOAD_BYTES / 1024 / 1024) ?> MB)</label>
                    <input class="form-control" type="file" id="d_file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></div>
            </div>
            <button class="btn btn-primary mt-3" type="submit">Upload</button>
            <p class="cs-muted mt-2 mb-0">Files are stored privately and can only be opened by staff of this branch.</p>
        </form>
    </div>
</section>
<?php endif; ?>

<?php // ============================= NOTES ============================= ?>
<?php if ($tab === 'notes'): ?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Notes (<?= count($notes) ?>)</h2></div>
    <div class="cs-panel__body cs-panel__body--flush">
        <?php if ($notes === []): ?>
            <?= view('components/empty_state', ['icon' => 'ti-notes', 'title' => 'No notes yet', 'text' => 'Anything else worth knowing about this member\'s history.']) ?>
        <?php else: ?>
            <div class="cs-tablewrap"><table class="cs-table cs-table--compact">
                <thead><tr><th>Note</th><th>Added</th><th>Status</th><th class="cs-table__actions">Action</th></tr></thead>
                <tbody>
                <?php foreach ($notes as $n): ?>
                    <tr>
                        <td style="white-space:pre-wrap"><?= esc($n['note']) ?></td>
                        <td><?= esc(cs_date($n['encoded_at'])) ?></td>
                        <td><?= $statusBadge($n) ?></td>
                        <td class="cs-table__actions"><?= $delete('note', (int) $n['legacy_note_id'], $base) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        <?php endif; ?>
    </div>
</section>
<section class="cs-panel">
    <div class="cs-panel__head"><h2 class="cs-panel__title">Add a note</h2></div>
    <div class="cs-panel__body">
        <form method="post" action="<?= esc($base . '/client/' . $userId . '/note') ?>" data-cs-once>
            <?= csrf_field() ?>
            <label class="form-label" for="n_note">Note</label>
            <textarea class="form-control" id="n_note" name="note" rows="4" maxlength="5000" required><?= esc((string) old('note')) ?></textarea>
            <button class="btn btn-primary mt-3" type="submit">Save note</button>
        </form>
    </div>
</section>
<?php endif; ?>
<?= $this->endSection() ?>
