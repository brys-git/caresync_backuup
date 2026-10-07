<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$state = (string) ($access['state'] ?? 'unregistered');
$e = static fn ($v): string => esc((string) ($v ?? ''));
$peso = static fn ($v): string => '₱' . number_format((float) $v, 2);
$fmt = static fn (?string $d): string => ($d !== null && $d !== '' && strtotime($d) !== false) ? date('j M Y', strtotime($d)) : '—';
$statusLabels = ['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected'];
?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<?php if ($state === 'unregistered' || $state === 'awaiting_activation'): ?>
    <?php $unregistered = $state === 'unregistered'; ?>
    <div class="cs-restricted">
        <div class="cs-restricted__blur">
            <section class="cs-panel">
                <div class="cs-panel__head"><h2 class="cs-panel__title">Payment history</h2></div>
                <div class="cs-panel__body">
                    <p class="cs-muted mb-0"><?= $unregistered ? 'Register to view your plan\'s payment overview.' : 'Complete your initial payment to unlock your payment overview.' ?></p>
                </div>
            </section>
        </div>
        <div class="cs-restricted__overlay">
            <div class="cs-panel cs-restricted__card">
                <div class="cs-panel__body text-center">
                    <h2 class="cs-panel__title mb-2"><?= $unregistered ? 'Register to access this feature' : 'Complete your initial payment' ?></h2>
                    <p class="cs-muted"><?= $unregistered ? 'Payment is restricted until you complete plan registration.' : 'You already registered. Submit your initial payment to unlock your membership.' ?></p>
                    <a href="<?= base_url($unregistered ? 'plan-info' : 'initial-payment') ?>" class="btn btn-primary"><?= $unregistered ? 'Register now' : 'Go to initial payment' ?></a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if (($access['initial_payment_status'] ?? 'none') === 'cancelled'): ?>
        <?= view('components/alert', ['type' => 'danger', 'dismissible' => false, 'message' => 'Payment rejected. Please resubmit.']) ?>
    <?php endif; ?>

    <?php if ($plan && $schedule): ?>
        <?php
        $remaining = (int) $schedule['remaining_months'];
        $total = (int) $schedule['total_months'];
        $verified = (int) $schedule['verified_months'];
        $paidPct = $schedule['target'] > 0 ? min(100, round($schedule['paid_amount'] / $schedule['target'] * 100, 1)) : 0;
        $days = $due['days'];
        $daysText = $days === null ? '' : ($days === 0 ? 'due today' : ($days === 1 ? 'in 1 day' : 'in ' . $days . ' days'));
        $approvedCount = 0;
        $approvedTotal = 0.0;
        foreach ($history as $row) {
            if ($row['status'] === 'approved') {
                $approvedCount++;
                $approvedTotal += $row['amount'];
            }
        }
        ?>
        <link rel="stylesheet" href="<?= cs_asset_url('assets/css/client-payments.css') ?>">
        <div class="cp-page">
            <header class="cp-head">
                <h1 class="cp-title">Payments</h1>
                <p class="cp-sub">Your contributions for <?= $e($plan_name !== '' ? $plan_name : 'your plan') ?></p>
            </header>

            <?= view('partials/plan_standing', ['standing' => $standing ?? null, 'standing_details' => false]) ?>

            <div class="cp-top">
                <section class="cp-next<?= $due['overdue'] ? ' cp-next--overdue' : '' ?>" aria-labelledby="cp-next-title">
                    <h2 id="cp-next-title" class="cp-label"><?= $due['overdue'] ? 'Payment overdue' : 'Next payment due' ?></h2>
                    <p class="cp-next__amount"><?= $peso($due['pay_amount'] ?? $due['amount']) ?></p>
                    <?php if ($remaining <= 0): ?>
                        <p class="cp-next__when">Plan fully paid</p>
                    <?php elseif ($due['overdue']): ?>
                        <p class="cp-next__when">Overdue since <?= $e($fmt($due['overdue_since'])) ?></p>
                    <?php elseif ($due['date'] !== null): ?>
                        <p class="cp-next__when"><?= $e(date('l, j M Y', strtotime($due['date']))) ?> · <?= $e($daysText) ?></p>
                    <?php else: ?>
                        <p class="cp-next__when">Not scheduled yet</p>
                    <?php endif; ?>
                    <?php if ($remaining > 0): ?>
                        <a class="cp-btn cp-btn--white" href="<?= base_url('client/payment/make') ?>">Make payment →</a>
                    <?php else: ?>
                        <span class="cp-btn cp-btn--white" aria-disabled="true">Make payment →</span>
                    <?php endif; ?>
                </section>

                <section class="cp-card" aria-labelledby="cp-progress-title">
                    <h2 id="cp-progress-title" class="cp-card__title cp-sr">Plan progress</h2>
                    <div class="cp-progress__row">
                        <span><strong><?= $peso($schedule['paid_amount']) ?></strong> paid</span>
                        <span><strong><?= $peso($schedule['remaining_amount']) ?></strong> remaining</span>
                    </div>
                    <div class="cp-bar" role="progressbar" aria-label="Share of the plan paid" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $paidPct ?>">
                        <span style="width: <?= $paidPct ?>%"></span>
                    </div>
                    <dl class="cp-mini">
                        <div>
                            <dt>Monthly contribution</dt>
                            <dd><?= $peso($due['amount']) ?></dd>
                        </div>
                        <div>
                            <dt>Months paid</dt>
                            <dd><?= $verified ?> <span class="cp-muted">of <?= $total ?></span></dd>
                        </div>
                        <div>
                            <dt>Covered until</dt>
                            <dd><?= $due['covered_until'] !== null ? $e($fmt($due['covered_until'])) : 'Not yet' ?></dd>
                        </div>
                    </dl>
                </section>
            </div>

            <section class="cp-card" aria-labelledby="cp-history-title">
                <div class="cp-card__head">
                    <h2 id="cp-history-title" class="cp-card__title">Payment history</h2>
                    <?php if ($history !== []): ?>
                        <div class="cp-seg" role="group" aria-label="Filter by status">
                            <button type="button" class="cp-seg__btn" data-filter="all" aria-pressed="true">All</button>
                            <button type="button" class="cp-seg__btn" data-filter="pending" aria-pressed="false">Pending</button>
                            <button type="button" class="cp-seg__btn" data-filter="approved" aria-pressed="false">Approved</button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($history === []): ?>
                    <p class="cp-empty">No payments recorded yet. Once your first payment is submitted, it appears here.</p>
                <?php else: ?>
                    <div class="cp-tablewrap">
                        <table class="cp-table">
                            <caption class="cp-sr">Payments, newest first</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Date paid</th>
                                    <th scope="col">Covers</th>
                                    <th scope="col" class="cp-amount">Amount</th>
                                    <th scope="col">Method</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $row): ?>
                                    <tr data-status="<?= $e($row['status']) ?>">
                                        <td><?= $e($fmt($row['date'])) ?></td>
                                        <td><span class="cp-pill"><?= $e($row['covers']) ?></span></td>
                                        <td class="cp-amount"><?= $peso($row['amount']) ?></td>
                                        <td>
                                            <?= $e($row['method']) ?>
                                            <?php if (! empty($row['recorded_by_branch'])): ?>
                                                <div class="cp-ref">Recorded by branch</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="cp-status cp-status--<?= $e($row['status']) ?>"><?= $e($statusLabels[$row['status']] ?? 'Pending') ?></span></td>
                                        <td>
                                            <?php if ($row['receipt_url'] !== null): ?>
                                                <a href="<?= $e($row['receipt_url']) ?>"><?= $e($row['receipt']) ?></a>
                                            <?php elseif ($row['receipt'] !== null): ?>
                                                <span class="cp-ref"><?= $e($row['receipt']) ?></span>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="cp-filter-empty" hidden>
                                    <td colspan="6" class="cp-empty">No payments match this filter.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cp-foot">
                        <span>Total paid · <?= $approvedCount ?> <?= $approvedCount === 1 ? 'payment' : 'payments' ?></span>
                        <strong><?= $peso($approvedTotal) ?></strong>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <script>
            (function () {
                var buttons = document.querySelectorAll('[data-filter]');
                var rows = document.querySelectorAll('.cp-table tbody tr[data-status]');
                var emptyRow = document.querySelector('.cp-filter-empty');
                if (!buttons.length) { return; }

                buttons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        var filter = button.getAttribute('data-filter');
                        buttons.forEach(function (other) {
                            other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
                        });
                        var visible = 0;
                        rows.forEach(function (row) {
                            var show = filter === 'all' || row.getAttribute('data-status') === filter;
                            row.hidden = !show;
                            if (show) { visible++; }
                        });
                        emptyRow.hidden = visible > 0;
                    });
                });
            })();
        </script>
    <?php else: ?>
        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Payment history</h2></div>
            <div class="cs-panel__body">
                <p class="cs-muted mb-0">No plan found on your account yet.</p>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?= $this->endSection() ?>
