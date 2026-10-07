<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>

<a class="text-decoration-none text-muted mb-3 d-inline-block" href="<?= base_url('collector/collection-list') ?>">
    <i class="ti ti-arrow-left me-1"></i> Back to Collection List
</a>

<section class="cs-panel">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Record Payment</h2>
        <p class="cs-panel__note">Cash collected in the field. Your branch admin still has to verify this before it counts toward the plan.</p>
    </div>
    <div class="cs-panel__body">
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <small class="cs-muted d-block">Client</small>
                <strong><?= esc($plan['client_name']) ?></strong>
            </div>
            <div class="col-md-4">
                <small class="cs-muted d-block">Contact</small>
                <strong><?= esc((string) ($plan['contact_number'] ?: '-')) ?></strong>
            </div>
            <div class="col-md-4">
                <small class="cs-muted d-block">Monthly Contribution</small>
                <strong><?= cs_money($plan['monthly_fee'] ?? 0) ?></strong>
            </div>
        </div>

        <?php if ($remaining_months <= 0): ?>
            <div class="alert alert-info mb-0">This plan's current cycle is already fully covered. Nothing more to collect right now.</div>
        <?php else: ?>
            <form method="post" action="<?= base_url('collector/collection-list/record-payment/' . (int) $plan['plan_id']) ?>" id="recordPaymentForm" data-cs-once>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="months_covered">Months Covered</label>
                        <select id="months_covered" name="months_covered" class="form-select" required>
                            <?php for ($m = 1; $m <= $remaining_months; $m++): ?>
                                <option value="<?= $m ?>"><?= $m ?> Month<?= $m === 1 ? '' : 's' ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="form-text"><?= esc((string) $remaining_months) ?> month(s) left in this plan's current cycle.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-block">Total Amount</label>
                        <strong id="totalAmountDisplay" class="fs-5"></strong>
                        <div class="form-text">Your commission when verified: <strong id="commissionEstimate" class="cs-money--brass"></strong> (estimate)</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-block">Payment Method</label>
                        <strong>Cash</strong>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="official_receipt_number">Official Receipt Number</label>
                        <input
                            id="official_receipt_number"
                            name="official_receipt_number"
                            class="form-control"
                            maxlength="100"
                            placeholder="OR number from your receipt booklet"
                            value="<?= old('official_receipt_number', '') ?>"
                            required
                        >
                        <div class="form-text">Write the same number on the client's paper receipt.</div>
                    </div>
                </div>

                <button type="button" class="btn btn-primary mt-4" id="openConfirmModal">Submit</button>
            </form>

            <div class="modal fade" id="confirmPaymentModal" tabindex="-1" aria-labelledby="confirmPaymentModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmPaymentModalLabel">Confirm This Collection</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-2 mb-3">
                                <div class="col-6"><small class="cs-muted d-block">Client</small><strong><?= esc($plan['client_name']) ?></strong></div>
                                <div class="col-6"><small class="cs-muted d-block">Months</small><strong id="confirmMonths"></strong></div>
                                <div class="col-6"><small class="cs-muted d-block">Total Amount</small><strong id="confirmTotal"></strong></div>
                                <div class="col-6"><small class="cs-muted d-block">Payment Method</small><strong>Cash</strong></div>
                                <div class="col-12"><small class="cs-muted d-block">Official Receipt Number</small><strong id="confirmReceipt"></strong></div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmReviewedCheck">
                                <label class="form-check-label" for="confirmReviewedCheck">
                                    I have handed the client this receipt number and confirm these details are correct.
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Go Back</button>
                            <button type="submit" class="btn btn-primary" id="confirmSubmitBtn" form="recordPaymentForm" disabled>Confirm &amp; Save</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($remaining_months > 0): ?>
<script>
    (function () {
        const monthlyFee = Number('<?= esc((string) ($plan['monthly_fee'] ?? 0)) ?>');
        const commissionRate = Number('<?= esc((string) \App\Services\CommissionService::RATE) ?>');
        const monthsSelect = document.getElementById('months_covered');
        const totalDisplay = document.getElementById('totalAmountDisplay');
        const commissionDisplay = document.getElementById('commissionEstimate');
        const receiptInput = document.getElementById('official_receipt_number');
        const openModalBtn = document.getElementById('openConfirmModal');
        const confirmCheck = document.getElementById('confirmReviewedCheck');
        const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
        const confirmMonths = document.getElementById('confirmMonths');
        const confirmTotal = document.getElementById('confirmTotal');
        const confirmReceipt = document.getElementById('confirmReceipt');
        const modalEl = document.getElementById('confirmPaymentModal');

        function formatMoney(value) {
            return '₱' + value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function currentTotal() {
            const months = Number(monthsSelect.value || 1);
            return monthlyFee * months;
        }

        function updateTotal() {
            totalDisplay.textContent = formatMoney(currentTotal());
            commissionDisplay.textContent = formatMoney(currentTotal() * commissionRate);
        }

        monthsSelect.addEventListener('change', updateTotal);
        updateTotal();

        openModalBtn.addEventListener('click', function () {
            if (!receiptInput.reportValidity()) {
                return;
            }

            confirmMonths.textContent = monthsSelect.value + ' month(s)';
            confirmTotal.textContent = formatMoney(currentTotal());
            confirmReceipt.textContent = receiptInput.value.trim();
            confirmCheck.checked = false;
            confirmSubmitBtn.disabled = true;

            if (window.bootstrap && modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });

        confirmCheck.addEventListener('change', function () {
            confirmSubmitBtn.disabled = !confirmCheck.checked;
        });
    })();
</script>
<?php endif; ?>

<?= $this->endSection() ?>
