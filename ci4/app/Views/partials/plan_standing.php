<?php
/**
 * The member's own plan standing, from PlanLedgerService (the one rule).
 * Shown on the client dashboard and the Payments page.
 *
 * @var array|null $standing PlanLedgerService::summarize() result
 */
$s = $standing ?? null;
if (! $s || ! in_array($s['lifecycle'], ['grace', 'lapsed', 'forfeited', 'fully_paid', 'active'], true)) {
    return;
}

$date = static fn (?string $d): string => $d ? date('F j, Y', strtotime($d)) : '';
?>
<?php if ($s['lifecycle'] === 'grace'): ?>
    <div class="alert alert-warning" role="status">
        <i class="ti ti-alert-triangle" aria-hidden="true"></i>
        <span>
            <strong><?= (int) $s['grace_days_left'] ?> day<?= (int) $s['grace_days_left'] === 1 ? '' : 's' ?> left to pay.</strong>
            You have <?= (int) $s['overdue_count'] ?> overdue contribution<?= (int) $s['overdue_count'] === 1 ? '' : 's' ?>
            (<?= esc(cs_peso($s['overdue_amount'])) ?>), the oldest due <?= esc($date($s['oldest_unpaid_due'])) ?>.
            Pay by <?= esc($date($s['grace_ends'])) ?> and your plan stays active - nothing else is needed.
        </span>
    </div>
<?php elseif ($s['lifecycle'] === 'lapsed'): ?>
    <div class="alert alert-danger" role="status">
        <i class="ti ti-player-pause" aria-hidden="true"></i>
        <span>
            <strong>Your plan is on hold (lapsed).</strong>
            Contributions were not paid within <?= (int) $s['grace_days'] ?> days. Nothing has been erased.
            To reinstate it, pay <?= esc(cs_peso($s['reinstatement']['total'])) ?> at your branch
            (<?= esc(cs_peso($s['reinstatement']['overdue_amount'])) ?> overdue<?= $s['reinstatement']['fee_remaining'] > 0 ? ' + ' . esc(cs_peso($s['reinstatement']['fee_remaining'])) . ' reinstatement fee' : '' ?>).
        </span>
    </div>
<?php elseif ($s['lifecycle'] === 'forfeited'): ?>
    <div class="alert alert-danger" role="status">
        <i class="ti ti-alert-circle" aria-hidden="true"></i>
        <span><strong>Your plan has been forfeited.</strong> Please contact your branch about your options.</span>
    </div>
<?php endif; ?>

<?php if (! empty($standing_details)): ?>
    <dl class="row mb-3">
        <dt class="col-sm-4">Months paid</dt><dd class="col-sm-8"><?= (int) $s['months_paid'] ?> of <?= (int) $s['total_contributions'] ?> (<?= (int) $s['months_left'] ?> left)</dd>
        <dt class="col-sm-4">Paid ahead</dt><dd class="col-sm-8"><?= (int) $s['advance'] ?> month<?= (int) $s['advance'] === 1 ? '' : 's' ?></dd>
        <?php if ($s['overdue_count'] > 0): ?>
            <dt class="col-sm-4">Overdue</dt><dd class="col-sm-8"><?= (int) $s['overdue_count'] ?> contribution<?= (int) $s['overdue_count'] === 1 ? '' : 's' ?> · <?= esc(cs_peso($s['overdue_amount'])) ?></dd>
        <?php endif; ?>
    </dl>
<?php endif; ?>
