<?php
/**
 * Plan standing for staff-side client pages (client details for Admin,
 * Branch Admin, Staff; Existing member records). Numbers come from
 * PlanLedgerService, the one rule, so they match the client's own dashboard.
 *
 * @var array|null $ledger       PlanLedgerService::summarize() result
 * @var ?string    $reinstate_url POST target for the "Reinstate plan" form (null = hide the form)
 */
$s = $ledger ?? null;
if (! $s) {
    return;
}

$labels = ['active' => 'Active', 'grace' => 'In grace period', 'lapsed' => 'Lapsed (on hold)', 'forfeited' => 'Forfeited', 'fully_paid' => 'Fully paid', 'not_started' => 'Not started'];
$date = static fn (?string $d): string => $d ? date('M j, Y', strtotime($d)) : '—';
?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Plan standing</h2>
        <?= cs_status((string) $s['lifecycle'], $labels[$s['lifecycle']] ?? $s['lifecycle']) ?>
    </div>
    <div class="cs-panel__body">
        <?php if ($s['lifecycle'] === 'not_started'): ?>
            <p class="cs-muted mb-0">The plan has not been activated yet, so no contributions are scheduled.</p>
        <?php else: ?>
            <?php if ($s['lifecycle'] === 'grace'): ?>
                <p class="mb-3"><strong><?= (int) $s['grace_days_left'] ?> day(s) left to pay.</strong>
                    The plan still counts as active. Paying everything overdue by <?= esc($date($s['grace_ends'])) ?> keeps it Active - nothing else is needed.</p>
            <?php elseif ($s['lifecycle'] === 'lapsed'): ?>
                <p class="mb-3">The plan is <strong>on hold</strong>: contributions were not paid within <?= (int) $s['grace_days'] ?> days of <?= esc($date($s['oldest_unpaid_due'])) ?>. No history has been changed.</p>
            <?php elseif ($s['lifecycle'] === 'forfeited'): ?>
                <p class="mb-3">The plan has been <strong>forfeited</strong>. No history has been changed.</p>
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-6 col-md-3"><div class="cs-muted">Months paid</div><div class="fs-5 fw-semibold"><?= (int) $s['months_paid'] ?> <span class="cs-muted fs-6">of <?= (int) $s['total_contributions'] ?></span></div></div>
                <div class="col-6 col-md-3"><div class="cs-muted">Months left</div><div class="fs-5 fw-semibold"><?= (int) $s['months_left'] ?></div></div>
                <div class="col-6 col-md-3"><div class="cs-muted">Paid ahead</div><div class="fs-5 fw-semibold"><?= (int) $s['advance'] ?> month<?= (int) $s['advance'] === 1 ? '' : 's' ?></div></div>
                <div class="col-6 col-md-3"><div class="cs-muted">Oldest unpaid due</div><div class="fs-5 fw-semibold"><?= esc($date($s['oldest_unpaid_due'])) ?></div></div>
                <div class="col-6 col-md-3"><div class="cs-muted">Overdue</div>
                    <div class="fs-5 fw-semibold <?= $s['overdue_count'] ? 'text-danger' : '' ?>"><?= (int) $s['overdue_count'] ?> · <?= esc(cs_peso($s['overdue_amount'])) ?></div></div>
                <div class="col-12 col-md-9"><div class="cs-muted">Overdue due dates</div>
                    <div><?= $s['overdue_dues'] ? esc(implode(', ', array_map($date, array_slice($s['overdue_dues'], 0, 24)))) . (count($s['overdue_dues']) > 24 ? ' …' : '') : '—' ?></div></div>
            </div>

            <?php if ($s['lifecycle'] === 'lapsed' && $s['reinstatement']['applies']): ?>
                <hr class="cs-hairline">
                <h3 class="h6">Reinstate plan</h3>
                <dl class="row mb-2">
                    <dt class="col-sm-4">Overdue contributions</dt><dd class="col-sm-8"><?= esc(cs_peso($s['reinstatement']['overdue_amount'])) ?></dd>
                    <dt class="col-sm-4">Reinstatement fee</dt><dd class="col-sm-8"><?= esc(cs_peso($s['reinstatement']['fee_remaining'])) ?></dd>
                    <dt class="col-sm-4">Amount needed</dt><dd class="col-sm-8"><strong><?= esc(cs_peso($s['reinstatement']['total'])) ?></strong></dd>
                </dl>
                <?php if (! empty($reinstate_url)): ?>
                    <form method="post" action="<?= esc($reinstate_url) ?>" data-cs-once>
                        <?= csrf_field() ?>
                        <button class="btn btn-primary" type="submit"
                                data-cs-confirm="Record <?= esc(cs_peso($s['reinstatement']['total']), 'attr') ?> received in cash at the branch counter? The plan returns to Active once it is covered.">
                            Record cash payment of <?= esc(cs_peso($s['reinstatement']['total'])) ?>
                        </button>
                    </form>
                    <p class="cs-muted mt-2 mb-0">Cash only here. The plan goes back to Active by itself as soon as the payment covers everything owed.</p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
