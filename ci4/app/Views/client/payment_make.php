<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$e = static fn ($v): string => esc((string) ($v ?? ''));
$peso = static fn ($v): string => '₱' . number_format((float) $v, 2);
$pesoPlain = static fn ($v): string => number_format((float) $v, 2, '.', '');
$remaining = (int) $schedule['remaining_months'];
$total = (int) $schedule['total_months'];
$paid = (int) $schedule['verified_months'];
$pending = (int) $schedule['pending_months'];
$fee = (float) $schedule['monthly_fee'];
$payable = (float) $schedule['payable_amount'];
$firstChip = $chips[0] ?? null;
$defaultTotal = min($fee, $payable);
$defaultCovers = $firstChip['label'] ?? '';
$chipsByYear = [];
foreach ($chips as $chip) {
    $chipsByYear[$chip['year']][] = $chip;
}
?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>
<link rel="stylesheet" href="<?= cs_asset_url('assets/css/client-payments.css') ?>">

<div class="cp-page">
    <a class="cp-back" href="<?= base_url('client/payment') ?>">← Back to payments</a>
    <header class="cp-head">
        <h1 class="cp-title">Pay with GCash</h1>
        <p class="cp-sub">Three steps. Your collector confirms the payment against their GCash history.</p>
    </header>

    <form id="makePaymentForm" method="post" action="<?= base_url('client/payment/make') ?>" class="cp-layout" data-cs-once>
        <?= csrf_field() ?>
        <input type="hidden" name="months_count" id="monthsCount" value="1">
        <input type="hidden" name="covers_from" id="coversFrom" value="<?= $e($firstChip['month'] ?? '') ?>">
        <input type="hidden" name="covers_to" id="coversTo" value="<?= $e($firstChip['month'] ?? '') ?>">

        <div class="cp-steps">
            <section class="cp-card" aria-labelledby="cp-step1-title">
                <h2 id="cp-step1-title" class="cp-step__title"><span class="cp-step__num" aria-hidden="true">1</span> How many months are you paying?</h2>

                <?php if ($remaining > 0): ?>
                    <div class="cp-pills">
                        <span class="cp-pill"><?= $total ?> months in plan</span>
                        <span class="cp-pill cp-pill--paid"><?= $paid ?> paid</span>
                        <span class="cp-pill cp-pill--remaining"><?= $remaining ?> remaining</span>
                    </div>
                    <?php if ($pending > 0): ?>
                        <p class="cp-note"><?= $pending ?> <?= $pending === 1 ? 'month' : 'months' ?> awaiting collector approval <?= $pending === 1 ? 'is' : 'are' ?> not counted here.</p>
                    <?php endif; ?>

                    <div class="cp-stepper">
                        <button type="button" class="cp-stepper__btn" id="stepMinus" aria-label="One month fewer">−</button>
                        <output class="cp-stepper__value" id="monthsOutput" for="monthsCount" aria-live="polite">1 month</output>
                        <button type="button" class="cp-stepper__btn" id="stepPlus" aria-label="One month more">+</button>
                        <button type="button" class="cp-btn cp-btn--ghost" id="payAllBtn">Pay all <?= $remaining ?> remaining</button>
                    </div>

                    <button type="button" class="cp-btn cp-btn--ghost cp-cal-toggle" id="calToggle" aria-expanded="false" aria-controls="calPanel">
                        <span class="cp-toggle-inner">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                            <span id="calToggleLabel">Choose months on a calendar</span>
                        </span>
                        <svg class="cp-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <div class="cp-cal" id="calPanel" hidden>
                        <?php foreach ($chipsByYear as $year => $items): ?>
                            <div>
                                <h3 class="cp-cal__year"><?= (int) $year ?></h3>
                                <div class="cp-cal__grid">
                                    <?php foreach ($items as $chip): ?>
                                        <button type="button" class="cp-chip" data-index="<?= $chip['index'] ?>" data-month="<?= $e($chip['month']) ?>" data-label="<?= $e($chip['label']) ?>" aria-pressed="false" aria-label="<?= $e($chip['label']) ?>"><?= $e($chip['short']) ?></button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="cp-covers">Covers <strong id="coversText"><?= $e($defaultCovers) ?></strong> · <strong id="coversAmount"><?= $peso($defaultTotal) ?></strong></p>
                <?php else: ?>
                    <p class="cp-fully-paid">Plan fully paid. There are no months left to pay.</p>
                <?php endif; ?>
            </section>

            <section class="cp-card" aria-labelledby="cp-step2-title">
                <h2 id="cp-step2-title" class="cp-step__title"><span class="cp-step__num" aria-hidden="true">2</span> Send exactly <span id="sendInline"><?= $peso($defaultTotal) ?></span> to your collector's GCash</h2>

                <?php if ($gcash === null): ?>
                    <p class="cp-fully-paid" style="background:#fbf0da;color:#8f5d00">The collector for your area hasn't added a GCash account yet, so there is no number to pay to right now. Please contact your branch before sending money.</p>
                <?php else: ?>
                    <div class="cp-gcash">
                        <div>
                            <?php if ($gcash['qr_url'] !== null): ?>
                                <img class="cp-qr" src="<?= $e($gcash['qr_url']) ?>" width="188" height="188" alt="GCash QR code of <?= $e($gcash['collector_name']) ?>">
                                <a class="cp-btn cp-btn--ghost cp-btn--block" href="<?= $e($gcash['qr_url']) ?>" download="gcash-qr.png" style="margin-top:10px">Save QR image</a>
                            <?php else: ?>
                                <div class="cp-qr cp-qr--missing">QR not available — send to the number instead</div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="cp-detail-label">Collector</p>
                            <p class="cp-detail-value"><?= $e($gcash['collector_name']) ?></p>

                            <p class="cp-detail-label" style="margin-top:12px">GCash account name</p>
                            <p class="cp-detail-value"><?= $e($gcash['account_name'] ?? '—') ?></p>

                            <p class="cp-detail-label" style="margin-top:12px">GCash number</p>
                            <div class="cp-number">
                                <span class="cp-number__digits cp-mono"><?= $e($gcash['formatted']) ?></span>
                                <button type="button" class="cp-copy cp-js-copy" data-copy="<?= $e($gcash['digits']) ?>" data-copy-label="Copy number">Copy number</button>
                            </div>

                            <div class="cp-divider"></div>

                            <p class="cp-detail-label">Amount to send</p>
                            <div class="cp-amount-row">
                                <strong id="sendAmount"><?= $peso($defaultTotal) ?></strong>
                                <button type="button" class="cp-copy cp-js-copy" id="copyAmount" data-copy="<?= $pesoPlain($defaultTotal) ?>" data-copy-label="Copy amount">Copy amount</button>
                            </div>

                            <p class="cp-helper">Scan the QR in the GCash app, or send to the number above. Check that the name GCash shows matches before you confirm.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="cp-card" aria-labelledby="cp-step3-title">
                <h2 id="cp-step3-title" class="cp-step__title"><span class="cp-step__num" aria-hidden="true">3</span> Enter your GCash reference number</h2>
                <div class="cp-field">
                    <label for="reference_number">GCash reference number</label>
                    <input
                        id="reference_number"
                        name="reference_number"
                        class="cp-input"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="13"
                        pattern="\d{13}"
                        placeholder="13 digits"
                        value="<?= old('reference_number', '') ?>"
                        aria-describedby="refHint"
                        <?= $remaining > 0 ? '' : 'disabled' ?>
                    >
                    <p class="cp-hint" id="refHint" aria-live="polite">0 of 13 digits</p>
                </div>
            </section>
        </div>

        <aside class="cp-card cp-summary" aria-labelledby="cp-summary-title">
            <h2 id="cp-summary-title" class="cp-card__title" style="margin-bottom:12px">Summary</h2>
            <dl>
                <div class="cp-summary__row"><dt>Plan</dt><dd><?= $e($plan_name !== '' ? $plan_name : '—') ?></dd></div>
                <div class="cp-summary__row"><dt>Monthly</dt><dd><?= $peso($fee) ?></dd></div>
                <div class="cp-summary__row"><dt>Paying</dt><dd id="sumMonths"><?= $remaining > 0 ? '1 month' : '—' ?></dd></div>
                <div class="cp-summary__row"><dt>Covers</dt><dd id="sumCovers"><?= $e($defaultCovers !== '' ? $defaultCovers : '—') ?></dd></div>
                <div class="cp-summary__row"><dt>Left after this</dt><dd id="sumLeft"><?= max(0, $remaining - 1) ?> <?= max(0, $remaining - 1) === 1 ? 'month' : 'months' ?></dd></div>
                <div class="cp-summary__row"><dt>Pay to</dt><dd><?= $gcash !== null ? $e($gcash['collector_name']) . ' (GCash)' : '—' ?></dd></div>
            </dl>
            <div class="cp-summary__total">
                <span>Total</span>
                <strong id="sumTotal"><?= $peso($defaultTotal) ?></strong>
            </div>
            <button type="button" class="cp-btn" id="openConfirmModal" disabled>Review payment</button>
            <p class="cp-note">Your payment shows as Pending in your history until your collector approves it.</p>
        </aside>
    </form>

    <dialog id="confirmPaymentModal" class="cp-dialog" aria-labelledby="confirmTitle">
        <div class="cp-dialog__body">
            <h2 id="confirmTitle" class="cp-card__title" style="margin-bottom:14px">Confirm your payment</h2>
            <div class="cp-dialog__grid">
                <div><span class="cp-detail-label">Plan</span><p class="cp-detail-value"><?= $e($plan_name !== '' ? $plan_name : '—') ?></p></div>
                <div><span class="cp-detail-label">Months</span><p class="cp-detail-value" id="confirmMonths"></p></div>
                <div><span class="cp-detail-label">Covers</span><p class="cp-detail-value" id="confirmCovers"></p></div>
                <div><span class="cp-detail-label">Total</span><p class="cp-detail-value" id="confirmTotal"></p></div>
                <div class="cp-wide"><span class="cp-detail-label">GCash reference number</span><p class="cp-detail-value cp-mono" id="confirmReference"></p></div>
            </div>
            <label class="cp-check">
                <input type="checkbox" id="confirmReviewedCheck">
                <span>I have reviewed this payment information and confirm it is correct.</span>
            </label>
        </div>
        <div class="cp-dialog__foot">
            <button type="button" class="cp-btn cp-btn--ghost" id="confirmBackBtn">Go back</button>
            <button type="submit" class="cp-btn" id="confirmSubmitBtn" form="makePaymentForm" disabled>Confirm &amp; submit payment</button>
        </div>
    </dialog>
</div>

<script>
    (function () {
        var remaining = <?= $remaining ?>;
        if (remaining <= 0) { return; }

        var fee = <?= json_encode(round($fee, 2)) ?>;
        var payable = <?= json_encode(round($payable, 2)) ?>;
        var hasGcash = <?= $gcash !== null ? 'true' : 'false' ?>;

        var n = 1;
        var chips = Array.prototype.slice.call(document.querySelectorAll('.cp-chip'));
        var stepMinus = document.getElementById('stepMinus');
        var stepPlus = document.getElementById('stepPlus');
        var payAll = document.getElementById('payAllBtn');
        var monthsOutput = document.getElementById('monthsOutput');
        var monthsCount = document.getElementById('monthsCount');
        var coversFrom = document.getElementById('coversFrom');
        var coversTo = document.getElementById('coversTo');
        var calToggle = document.getElementById('calToggle');
        var calToggleLabel = document.getElementById('calToggleLabel');
        var calPanel = document.getElementById('calPanel');
        var coversText = document.getElementById('coversText');
        var coversAmount = document.getElementById('coversAmount');
        var sendInline = document.getElementById('sendInline');
        var sendAmount = document.getElementById('sendAmount');
        var copyAmount = document.getElementById('copyAmount');
        var sumMonths = document.getElementById('sumMonths');
        var sumCovers = document.getElementById('sumCovers');
        var sumLeft = document.getElementById('sumLeft');
        var sumTotal = document.getElementById('sumTotal');
        var reference = document.getElementById('reference_number');
        var refHint = document.getElementById('refHint');
        var reviewBtn = document.getElementById('openConfirmModal');
        var dialog = document.getElementById('confirmPaymentModal');
        var confirmMonths = document.getElementById('confirmMonths');
        var confirmCovers = document.getElementById('confirmCovers');
        var confirmTotal = document.getElementById('confirmTotal');
        var confirmReference = document.getElementById('confirmReference');
        var confirmCheck = document.getElementById('confirmReviewedCheck');
        var confirmSubmit = document.getElementById('confirmSubmitBtn');
        var confirmBack = document.getElementById('confirmBackBtn');

        function money(value) {
            return '₱' + value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        function plural(count) {
            return count + ' month' + (count === 1 ? '' : 's');
        }
        function totalFor(months) {
            return Math.round(Math.min(months * fee, payable) * 100) / 100;
        }
        function label(index) {
            return chips[index - 1].getAttribute('data-label');
        }
        function coversLabel(months) {
            return months === 1 ? label(1) : label(1) + ' – ' + label(months);
        }

        function render() {
            var total = totalFor(n);
            var covers = coversLabel(n);
            var left = remaining - n;

            monthsCount.value = String(n);
            monthsOutput.textContent = plural(n);
            chips.forEach(function (chip) {
                chip.setAttribute('aria-pressed', Number(chip.getAttribute('data-index')) <= n ? 'true' : 'false');
            });
            coversFrom.value = chips[0].getAttribute('data-month');
            coversTo.value = chips[n - 1].getAttribute('data-month');

            coversText.textContent = covers;
            coversAmount.textContent = money(total);
            sendInline.textContent = money(total);
            sendAmount.textContent = money(total);
            if (copyAmount) { copyAmount.setAttribute('data-copy', total.toFixed(2)); }
            sumMonths.textContent = plural(n);
            sumCovers.textContent = covers;
            sumLeft.textContent = plural(left);
            sumTotal.textContent = money(total);

            stepMinus.disabled = n <= 1;
            stepPlus.disabled = n >= remaining;
            payAll.disabled = n === remaining;
        }

        function updateReference() {
            var length = reference.value.length;
            refHint.textContent = length === 13 ? 'Looks good — 13 digits.' : length + ' of 13 digits';
            refHint.classList.toggle('is-ok', length === 13);
            reviewBtn.disabled = !(length === 13 && hasGcash);
        }

        stepMinus.addEventListener('click', function () { n = Math.max(1, n - 1); render(); });
        stepPlus.addEventListener('click', function () { n = Math.min(remaining, n + 1); render(); });
        payAll.addEventListener('click', function () { n = remaining; render(); });
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                n = Number(chip.getAttribute('data-index'));
                render();
            });
        });

        calToggle.addEventListener('click', function () {
            var opening = calPanel.hidden;
            calPanel.hidden = !opening;
            calToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
            calToggleLabel.textContent = opening ? 'Hide month calendar' : 'Choose months on a calendar';
        });

        reference.addEventListener('input', function () {
            reference.value = reference.value.replace(/\D/g, '').slice(0, 13);
            updateReference();
        });

        reviewBtn.addEventListener('click', function () {
            if (reference.value.length !== 13) { return; }
            confirmMonths.textContent = plural(n);
            confirmCovers.textContent = coversLabel(n);
            confirmTotal.textContent = money(totalFor(n));
            confirmReference.textContent = reference.value;
            confirmCheck.checked = false;
            confirmSubmit.disabled = true;
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
        });
        confirmCheck.addEventListener('change', function () { confirmSubmit.disabled = !confirmCheck.checked; });
        confirmBack.addEventListener('click', function () { dialog.close(); });

        function copyText(text, button) {
            var label = button.getAttribute('data-copy-label');
            var done = function () {
                button.setAttribute('data-copied', 'true');
                button.textContent = 'Copied';
                setTimeout(function () {
                    button.setAttribute('data-copied', 'false');
                    button.textContent = label;
                }, 2000);
            };
            var fallback = function () {
                var area = document.createElement('textarea');
                area.value = text;
                area.setAttribute('readonly', '');
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                try { if (document.execCommand('copy')) { done(); } } catch (err) { /* no clipboard available */ }
                document.body.removeChild(area);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done, fallback);
            } else {
                fallback();
            }
        }
        Array.prototype.forEach.call(document.querySelectorAll('.cp-js-copy'), function (button) {
            button.addEventListener('click', function () {
                copyText(button.getAttribute('data-copy'), button);
            });
        });

        render();
        updateReference();
    })();
</script>
<?= $this->endSection() ?>
