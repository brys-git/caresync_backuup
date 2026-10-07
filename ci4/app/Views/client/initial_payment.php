<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$initialStatus = strtolower((string) (($latest_initial_payment['status'] ?? 'none')));
$program = $program ?? ['name' => 'Damayan Burial Program', 'monthly_fee' => 240.0];
$monthlyFee = (float) ($monthly_fee ?? ($program['monthly_fee'] ?? 240));
$fact = static function (string $label, string $html): string {
    return '<div class="col-md-3"><div class="cs-fact"><span class="cs-fact__label">' . esc($label) . '</span><span class="cs-fact__value">' . $html . '</span></div></div>';
};
?>
<div class="row g-3">
    <div class="col-lg-8">
        <?php // Plain error/success flash already handled by layouts/_shell.php's toast. The "errors" list below is a validation-array flash, a different shape the shell's toast loop doesn't cover, so it stays. ?>
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger">
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach (session()->getFlashdata('errors') as $field => $message): ?>
                        <li><?= esc($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (! $plan_holder || ! $plan): ?>
            <div class="alert alert-warning">
                Plan registration is incomplete. Please complete registration before making payment.
                <a href="<?= base_url('plan-registration') ?>" class="alert-link">Go to plan registration</a>.
            </div>
        <?php else: ?>
            <?php if ($initialStatus === 'pending'): ?>
                <?= view('components/alert', ['type' => 'warning', 'dismissible' => false, 'message' => 'Your initial payment is pending verification.']) ?>
            <?php elseif ($initialStatus === 'paid' && strtolower((string) ($plan_holder['status'] ?? 'inactive')) === 'inactive'): ?>
                <?= view('components/alert', ['type' => 'info', 'dismissible' => false, 'message' => 'Initial payment is verified. Your account will activate automatically after OR matching is completed.']) ?>
            <?php elseif ($initialStatus === 'cancelled'): ?>
                <?= view('components/alert', ['type' => 'danger', 'dismissible' => false, 'message' => 'Payment rejected. Please resubmit.']) ?>
            <?php endif; ?>

            <?php // Revealed by the payment-method script below only while GCash is selected. ?>
            <div id="gcashAccountCard" class="d-none">
                <?= view('client/_gcash_account', ['gcash_account' => $gcash_account ?? null]) ?>
            </div>

            <form method="post" action="<?= base_url('initial-payment') ?>">
                <?= csrf_field() ?>
                <section class="cs-panel">
                    <div class="cs-panel__body">
                        <div class="row g-3 mb-3">
                            <?= $fact('Monthly contribution', cs_money($monthlyFee)) ?>
                            <?= $fact('Plan', esc((string) ($program['name'] ?? 'Damayan Burial Program'))) ?>
                            <?= $fact('Status', cs_status((string) ($plan['status'] ?? 'inactive'))) ?>
                            <?= $fact('Initial payment due', '<span id="initial_amount_preview" class="cs-money">' . esc(number_format($monthlyFee * 2, 2)) . '</span>') ?>
                        </div>

                        <?= view('components/alert', ['type' => 'info', 'dismissible' => false, 'message' => 'Your initial payment must cover at least 2 months so your membership is immediately eligible to avail or claim services after it\'s verified.']) ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="months_covered">Months covered (minimum 2)</label>
                                <input id="months_covered" name="months_covered" type="number" min="2" data-monthly-fee="<?= esc((string) $monthlyFee) ?>" class="form-control" value="<?= esc(old('months_covered', '2')) ?>" required <?= in_array($initialStatus, ['pending', 'paid'], true) ? 'readonly' : '' ?>>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payment_method">Payment method</label>
                                <select id="payment_method" name="payment_method" class="form-select" required <?= $initialStatus === 'paid' ? 'disabled' : '' ?> >
                                    <option value="">Select method</option>
                                    <option value="cash" <?= old('payment_method') === 'cash' ? 'selected' : '' ?>>Cash (at branch)</option>
                                    <option value="gcash" <?= old('payment_method') === 'gcash' ? 'selected' : '' ?>>GCash</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="reference_number">Reference/receipt number</label>
                                <input id="reference_number" name="reference_number" class="form-control" placeholder="Enter receipt number from branch or GCash reference" value="<?= esc(old('reference_number')) ?>" required <?= $initialStatus === 'paid' ? 'readonly' : '' ?>>
                                <small class="cs-muted d-block mt-1" id="hint_receipt">
                                    <span id="hint_cash" class="d-none">Ask your branch for the official receipt number after paying.</span>
                                    <span id="hint_gcash" class="d-none">Enter your GCash transaction reference.</span>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="cs-panel__foot d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary" <?= $initialStatus === 'paid' ? 'disabled' : '' ?>>Submit initial payment</button>
                    </div>
                </section>
            </form>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Payment methods</h2></div>
            <div class="cs-panel__body small">
                <p class="mb-2"><strong>GCash:</strong> Pay online and enter your transaction reference.</p>
                <p class="mb-3"><strong>Cash:</strong> Visit your branch to pay, then ask for the official receipt number.</p>
                <?= view('components/alert', ['type' => 'info', 'dismissible' => false, 'class' => 'mb-0', 'title' => 'Tip:', 'message' => 'Once you submit the correct receipt/reference, your membership is automatically activated!']) ?>
            </div>
        </section>
    </div>
</div>

<script>
    (function () {
        const method = document.getElementById('payment_method');
        const reference = document.getElementById('reference_number');
        const hintCash = document.getElementById('hint_cash');
        const hintGcash = document.getElementById('hint_gcash');
        const gcashCard = document.getElementById('gcashAccountCard');

        if (!method || !reference) {
            return;
        }

        // Don't disable the field if it's readonly (already submitted)
        const isReadonly = reference.hasAttribute('readonly');

        function toggleReferenceField() {
            const isCash = method.value === 'cash';
            const isGcash = method.value === 'gcash';

            if (!isReadonly) {
                reference.disabled = !(isCash || isGcash);
            }
            reference.required = isCash || isGcash;

            if (isCash) {
                reference.placeholder = 'Enter official receipt number from branch';
            } else if (isGcash) {
                reference.placeholder = 'Enter GCash transaction reference';
            } else {
                reference.placeholder = '';
            }
            hintCash.classList.toggle('d-none', !isCash);
            hintGcash.classList.toggle('d-none', !isGcash);
            if (gcashCard) {
                gcashCard.classList.toggle('d-none', !isGcash);
            }

            if (!isCash && !isGcash) {
                reference.value = '';
            }
        }

        method.addEventListener('change', toggleReferenceField);
        toggleReferenceField();
    })();

    (function () {
        const monthsInput = document.getElementById('months_covered');
        const preview = document.getElementById('initial_amount_preview');
        if (!monthsInput || !preview) {
            return;
        }
        const monthlyFee = parseFloat(monthsInput.dataset.monthlyFee || '0');

        function updatePreview() {
            const months = Math.max(2, parseInt(monthsInput.value, 10) || 2);
            const total = monthlyFee * months;
            preview.textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        monthsInput.addEventListener('input', updatePreview);
        updatePreview();
    })();
</script>
<?= $this->endSection() ?>
