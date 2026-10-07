<?php
/**
 * "Send your payment here" card for the collector's GCash account.
 * Expects $gcash_account (CollectorGcashService::resolveForClient() shape,
 * or null when no collector with a saved GCash number covers this client).
 */
$gcashAccount = $gcash_account ?? null;
?>
<section class="cs-panel mb-3">
    <div class="cs-panel__head">
        <h2 class="cs-panel__title">Send your GCash payment to</h2>
    </div>
    <div class="cs-panel__body">
        <?php if (! $gcashAccount): ?>
            <?= view('components/alert', ['type' => 'warning', 'dismissible' => false, 'class' => 'mb-0', 'message' => 'The collector for your area hasn\'t added a GCash account yet, so there is no number to pay to right now. Please contact your branch before sending money.']) ?>
        <?php else: ?>
            <div class="row g-3 align-items-center">
                <div class="<?= ! empty($gcashAccount['qr_path']) ? 'col-md-7' : 'col-12' ?>">
                    <small class="cs-muted d-block">GCash number</small>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <strong class="fs-4" id="gcashNumberText"><?= esc($gcashAccount['gcash_number']) ?></strong>
                        <button type="button" class="btn btn-ghost btn-sm" id="copyGcashNumber" data-number="<?= esc($gcashAccount['gcash_number'], 'attr') ?>">
                            <i class="ti ti-copy" aria-hidden="true"></i> Copy
                        </button>
                    </div>
                    <?php if (! empty($gcashAccount['account_name'])): ?>
                        <small class="cs-muted d-block">Account name</small>
                        <strong class="d-block mb-3"><?= esc($gcashAccount['account_name']) ?></strong>
                    <?php endif; ?>
                    <small class="cs-muted d-block">Your collector</small>
                    <strong class="d-block mb-3"><?= esc($gcashAccount['collector_name']) ?></strong>
                    <p class="cs-muted small mb-0">Send the exact total below, then enter the 13-digit reference number from your GCash receipt. Your collector checks it against their GCash history before your payment is approved.</p>
                </div>
                <?php if (! empty($gcashAccount['qr_path'])): ?>
                    <div class="col-md-5 text-md-center">
                        <img src="<?= base_url('gcash-qr/' . (int) $gcashAccount['user_id']) ?>"
                             alt="GCash QR code of <?= esc($gcashAccount['collector_name'], 'attr') ?>"
                             class="img-fluid rounded border cs-qr">
                        <small class="cs-muted d-block mt-1">Scan with the GCash app</small>
                    </div>
                <?php endif; ?>
            </div>
            <script>
                (function () {
                    var btn = document.getElementById('copyGcashNumber');
                    if (!btn) { return; }
                    btn.addEventListener('click', function () {
                        var number = btn.getAttribute('data-number');
                        var done = function () { btn.textContent = 'Copied'; };
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(number).then(done, function () {});
                        }
                    });
                })();
            </script>
        <?php endif; ?>
    </div>
</section>
