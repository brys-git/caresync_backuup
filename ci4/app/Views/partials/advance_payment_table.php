<?php
/**
 * Reusable Advance Payment results table.
 *
 * Expected view data:
 *   array  $ap_rows           payment rows (needs the staff_first_name/staff_last_name and
 *                              payment_coverage_until columns joined in by the controller)
 *   bool   $ap_can_approve    (optional) show Approve/Reject actions for pending rows
 *   string $ap_action_base    (optional) base URL for approve/reject actions, e.g.
 *                              base_url('branch-admin/payment-tracking')
 *   bool   $ap_show_proof     (optional) show a Proof column
 *   bool   $ap_show_status    (optional, default true) show the Status column
 *   bool   $ap_show_remarks   (optional, default false) show the payment's
 *                              own remarks note (payments.remarks) as a column
 *   bool   $ap_remaining_from_months_paid  (optional, default false) reads
 *                              Remaining Months as (60 - plans.months_paid)
 *                              instead of the payment_coverage_until-based
 *                              advance-payment-buffer calculation - needs
 *                              plans.months_paid joined in by the controller
 *   bool   $ap_remarks_editable  (optional, default false) render the
 *                              Remarks cell as an inline save form instead
 *                              of plain text - needs $ap_show_remarks too
 *   string $ap_remarks_action_base  (optional) base URL the remarks form
 *                              posts to, e.g. base_url('staff/payment-management/save-remarks')
 *   string $ap_empty_message  (optional) text shown when there are no rows
 */
$paymentService = new \App\Services\PaymentService();
$canApprove = ! empty($ap_can_approve ?? false);
$showProof = ! empty($ap_show_proof ?? false);
$showStatus = (bool) ($ap_show_status ?? true);
$showRemarks = (bool) ($ap_show_remarks ?? false);
$remainingFromMonthsPaid = (bool) ($ap_remaining_from_months_paid ?? false);
$remarksEditable = (bool) ($ap_remarks_editable ?? false);
$remarksActionBase = (string) ($ap_remarks_action_base ?? '');
$actionBase = (string) ($ap_action_base ?? '');
// Plan term used only for the "remaining months" reading when
// $remainingFromMonthsPaid is on - a flat total months_paid counts down
// from, not the monetary cycle target PaymentService::remainingPayableMonths()
// uses for payment-capping elsewhere. This view is included more than once
// per request (Initial Payments + Monitoring tabs), so this must be a plain
// variable, not a const - redeclaring a const on the second include fatals.
$apPlanTermMonths = 60;
?>
<?php if (empty($ap_rows)): ?>
    <?= view('components/empty_state', [
        'icon'  => 'ti-receipt',
        'title' => (string) ($ap_empty_message ?? 'No payment records found.'),
    ]) ?>
<?php else: ?>
    <div class="cs-tablewrap">
        <table class="cs-table">
            <thead>
                <tr>
                    <th>Plan Holder</th>
                    <th>Coverage Period</th>
                    <th class="cs-num">Amount</th>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference / OR</th>
                    <th>Staff Account</th>
                    <th>Remaining Months</th>
                    <?php if ($showStatus): ?><th>Status</th><?php endif; ?>
                    <?php if ($showRemarks): ?><th>Remarks</th><?php endif; ?>
                    <?php if ($showProof): ?><th>Proof</th><?php endif; ?>
                    <?php if ($canApprove): ?><th class="cs-table__actions">Action</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ap_rows as $row): ?>
                    <?php
                    $status = strtolower((string) ($row['status'] ?? 'pending'));
                    $coverageStart = (string) ($row['coverage_start'] ?? $row['payment_date'] ?? '');
                    $coverageLabel = $coverageStart !== ''
                        ? $paymentService->describeCoverage($coverageStart, (int) ($row['months_covered'] ?? 1))
                        : (int) ($row['months_covered'] ?? 1) . ' month(s)';
                    $remaining = $remainingFromMonthsPaid
                        ? max(0, $apPlanTermMonths - (int) ($row['months_paid'] ?? 0))
                        : $paymentService->remainingMonths($row['payment_coverage_until'] ?? null);
                    $staffName = trim((string) ($row['staff_first_name'] ?? '') . ' ' . (string) ($row['staff_last_name'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <?= esc((string) ($row['first_name'] . ' ' . $row['last_name'])) ?>
                            <div class="cs-table__sub"><?= esc((string) ($row['unique_identifier'] ?: 'No ID')) ?></div>
                        </td>
                        <td><?= esc($coverageLabel) ?></td>
                        <td class="cs-num"><?= cs_money($row['amount']) ?></td>
                        <td class="text-nowrap"><?= cs_date($row['payment_date']) ?></td>
                        <td><?= esc(strtoupper((string) $row['payment_method'])) ?></td>
                        <td><?= esc((string) ($row['reference_number'] ?: ($row['official_receipt_number'] ?: '-'))) ?></td>
                        <td><?= $staffName !== '' ? esc($staffName) : '<span class="text-muted">-</span>' ?></td>
                        <td><?= $remaining > 0 ? esc((string) $remaining) : '<span class="text-muted">-</span>' ?></td>
                        <?php if ($showStatus): ?><td><?= cs_status($status) ?></td><?php endif; ?>
                        <?php if ($showRemarks): ?>
                            <td>
                                <?php if ($remarksEditable): ?>
                                    <form method="post" action="<?= esc($remarksActionBase . '/' . (int) $row['payment_id'], 'attr') ?>" style="display:flex;gap:.4rem;align-items:center;min-width:180px;">
                                        <?= csrf_field() ?>
                                        <input type="text" name="remarks" class="form-control form-control-sm" style="flex:1 1 auto;" value="<?= esc((string) ($row['remarks'] ?? '')) ?>" placeholder="Add a note&hellip;" maxlength="1000">
                                        <button type="submit" class="btn btn-ghost btn-sm">Save</button>
                                    </form>
                                <?php else: ?>
                                    <?= esc((string) ($row['remarks'] ?: '-')) ?>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <?php if ($showProof): ?>
                            <td>
                                <?php if (! empty($row['proof_image'] ?? null)): ?>
                                    <a href="<?= base_url('uploads/payment-proofs/' . $row['proof_image']) ?>" target="_blank">View</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <?php if ($canApprove): ?>
                            <td class="cs-table__actions">
                                <?php if ($status === 'pending'): ?>
                                    <form method="post" action="<?= esc($actionBase . '/approve/' . (int) $row['payment_id'], 'attr') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                                    </form>
                                    <form method="post" action="<?= esc($actionBase . '/reject/' . (int) $row['payment_id'], 'attr') ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="text" name="rejection_reason" class="form-control form-control-sm d-inline-block" style="max-width: 180px;" placeholder="Rejection reason">
                                        <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
