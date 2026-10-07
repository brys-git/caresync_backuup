<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$statusLabels = [
    'overdue' => 'OVERDUE',
    'due' => 'DUE',
    'awaiting_verification' => 'AWAITING VERIFICATION',
    'not_due' => 'NOT DUE',
    'no_balance_due' => 'NO BALANCE DUE',
];
?>
<?php if (! empty($no_assignment)): ?>
    <?= view('components/empty_state', [
        'icon' => 'ti-map-pin-off',
        'title' => 'No collection area assigned yet',
        'text' => 'Ask your branch admin to assign you a barangay before you can see who to collect from.',
    ]) ?>
<?php else: ?>

    <div class="cl-statbar">
        <div class="cl-stat"><div class="cl-stat__value"><?= (int) $summary['overdue'] ?></div><div class="cl-stat__label">Overdue</div></div>
        <div class="cl-stat"><div class="cl-stat__value"><?= (int) $summary['due'] ?></div><div class="cl-stat__label">Due</div></div>
        <div class="cl-stat"><div class="cl-stat__value"><?= (int) $summary['awaiting_verification'] ?></div><div class="cl-stat__label">Awaiting Verification</div></div>
        <div class="cl-stat"><div class="cl-stat__value"><?= (int) ($summary['not_due'] + $summary['no_balance_due']) ?></div><div class="cl-stat__label">No Visit Needed</div></div>
        <div class="cl-stat"><div class="cl-stat__value"><?= cs_money($summary['expected_collection'], false) ?></div><div class="cl-stat__label">Expected Collection</div></div>
    </div>

    <form class="cs-filters mb-3" method="get">
        <div class="cs-filters__field cs-filters__field--wide">
            <label class="form-label" for="search">Search</label>
            <input id="search" name="search" type="search" class="form-control" maxlength="100"
                   value="<?= esc((string) ($filters['search'] ?? ''), 'attr') ?>" placeholder="Name, mobile number, plan number or barangay">
        </div>
        <div class="cs-filters__field">
            <label class="form-label" for="period">Period</label>
            <input id="period" name="period" type="month" class="form-control" value="<?= esc((string) $filters['period']) ?>">
        </div>
        <div class="cs-filters__field">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">All</option>
                <?php foreach ($statusLabels as $val => $lbl): ?>
                    <option value="<?= esc($val) ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cs-filters__field">
            <label class="form-label" for="barangay_code">Barangay</label>
            <input id="barangay_code" name="barangay_code" class="form-control" placeholder="Code" value="<?= esc((string) $filters['barangay_code']) ?>">
        </div>
        <?php if (! empty($show_collector_filter)): ?>
            <div class="cs-filters__field">
                <label class="form-label" for="collector_id">Collector</label>
                <select id="collector_id" name="collector_id" class="form-select">
                    <option value="0">All Collectors</option>
                    <?php foreach (($collectors ?? []) as $c): ?>
                        <option value="<?= (int) $c['user_id'] ?>" <?= (int) $filters['collector_id'] === (int) $c['user_id'] ? 'selected' : '' ?>>
                            <?= esc((string) ($c['first_name'] . ' ' . $c['last_name'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="cs-filters__actions">
            <button type="submit" class="btn btn-primary">Apply</button>
            <?php if (! empty($filters['search'])): ?>
                <a class="btn btn-ghost" href="?<?= http_build_query(array_filter(['period' => $filters['period'], 'status' => $filters['status'], 'barangay_code' => $filters['barangay_code'], 'collector_id' => $filters['collector_id']])) ?>">Clear search</a>
            <?php endif; ?>
            <a class="btn btn-ghost" href="<?= esc((string) ($print_url ?? '#')) ?>" target="_blank">
                <i class="ti ti-printer me-1"></i> Print Route Sheet
            </a>
        </div>
    </form>

    <?php if (isset($gcash_rows)): ?>
        <section class="cs-panel mb-4">
            <div class="cs-panel__head">
                <h2 class="cs-panel__title">GCash Payments Awaiting Your Verification (<?= count($gcash_rows) ?>)</h2>
            </div>
            <div class="cs-panel__body cs-panel__body--flush">
                <p class="cs-muted px-3 pt-3 mb-0">Check the reference number below against your own GCash account's transaction history before approving.</p>
                <?php $this->setData([
                    'ap_rows' => $gcash_rows,
                    'ap_can_approve' => true,
                    'ap_action_base' => $gcash_action_base ?? '',
                    'ap_show_proof' => (bool) ($supports_proof_upload ?? false),
                    'ap_empty_message' => 'No GCash payments waiting on you right now.',
                ]) ?>
                <?= $this->include('partials/advance_payment_table') ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="cs-panel mb-4">
        <div class="cs-panel__head"><h2 class="cs-panel__title">Visit Today (<?= count($visit_rows) ?>)</h2></div>
        <div class="cs-panel__body cs-panel__body--flush">
            <?php if (empty($visit_rows)): ?>
                <?= view('components/empty_state', [
                    'icon' => 'ti-checkbox',
                    'title' => 'Nobody to visit for this period',
                    'text' => 'Everyone in this list has either paid, paid in advance, or has a payment awaiting verification.',
                ]) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table cl-table">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Barangay</th>
                                <th>Plan No.</th>
                                <th class="cs-num">Monthly</th>
                                <th>Months Behind</th>
                                <th class="cs-num">Amount Due</th>
                                <th>Status</th>
                                <th>Contact</th>
                                <th>Remarks</th>
                                <?php if (! empty($can_record_payment)): ?><th class="cs-table__actions">Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visit_rows as $row): ?>
                                <tr>
                                    <td class="cl-name-cell" data-label="Client">
                                        <span class="cs-table__primary"><?= esc($row['client_name']) ?></span>
                                        <span class="cs-table__sub"><?= esc($row['city']) ?></span>
                                    </td>
                                    <td data-label="Barangay"><?= esc($row['barangay']) ?></td>
                                    <td data-label="Plan No."><?= esc($row['plan_number']) ?></td>
                                    <td class="cs-num" data-label="Monthly"><?= cs_money($row['monthly_fee']) ?></td>
                                    <td data-label="Months Behind"><?= $row['months_behind'] > 0 ? esc((string) $row['months_behind']) : '—' ?></td>
                                    <td class="cs-num" data-label="Amount Due"><?= cs_money($row['amount_due']) ?></td>
                                    <td data-label="Status"><?= cs_status($row['collection_status'], $statusLabels[$row['collection_status']] ?? null) ?></td>
                                    <td data-label="Contact"><?= esc($row['contact_number']) ?></td>
                                    <td data-label="Remarks">
                                        <form class="cl-remarks-form" method="post" action="<?= base_url($remarks_route_base . '/' . (int) $row['plan_holder_id']) ?>">
                                            <?= csrf_field() ?>
                                            <input type="text" name="remarks" class="form-control form-control-sm" value="<?= esc($row['remarks']) ?>" placeholder="Leave a note about this client&hellip;" maxlength="1000">
                                            <button type="submit" class="btn btn-ghost btn-sm">Save</button>
                                        </form>
                                    </td>
                                    <?php if (! empty($can_record_payment)): ?>
                                        <td class="cl-actions-cell">
                                            <a class="btn btn-primary btn-sm" href="<?= base_url('collector/collection-list/record-payment/' . (int) $row['plan_id']) ?>">
                                                Record Payment
                                            </a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="cs-panel">
        <div class="cs-panel__head">
            <h2 class="cs-panel__title">No Visit Needed (<?= count($no_visit_rows) ?>)</h2>
        </div>
        <div class="cs-panel__body cs-panel__body--flush">
            <?php if (empty($no_visit_rows)): ?>
                <?= view('components/empty_state', ['icon' => 'ti-list', 'title' => 'Nothing here']) ?>
            <?php else: ?>
                <div class="cs-tablewrap">
                    <table class="cs-table cl-table cs-table--compact">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Barangay</th>
                                <th>Plan No.</th>
                                <th>Status</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($no_visit_rows as $row): ?>
                                <tr>
                                    <td class="cl-name-cell" data-label="Client"><?= esc($row['client_name']) ?></td>
                                    <td data-label="Barangay"><?= esc($row['barangay']) ?></td>
                                    <td data-label="Plan No."><?= esc($row['plan_number']) ?></td>
                                    <td data-label="Status"><?= cs_status($row['collection_status'], $statusLabels[$row['collection_status']] ?? null) ?></td>
                                    <td data-label="Contact"><?= esc($row['contact_number']) ?></td>
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
