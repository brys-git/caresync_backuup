<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>

<?php $account = $account ?? null; ?>
<?php $hasQr = ! empty($account['qr_path']); ?>

<div class="row g-3">
    <div class="col-lg-8">
        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">GCash details</h2></div>
            <div class="cs-panel__body">
                <form method="post" action="<?= base_url('collector/gcash-account') ?>" enctype="multipart/form-data" class="row g-3" data-cs-once>
                    <?= csrf_field() ?>
                    <div class="col-md-6">
                        <label class="form-label" for="gcash_number">GCash number</label>
                        <input id="gcash_number" name="gcash_number" class="form-control" inputmode="tel" maxlength="20"
                               placeholder="09171234567" required
                               value="<?= esc((string) old('gcash_number', $account['gcash_number'] ?? ''), 'attr') ?>">
                        <div class="form-text">The mobile number registered to the GCash account you collect on.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="account_name">Account name <span class="cs-muted">(optional)</span></label>
                        <input id="account_name" name="account_name" class="form-control" maxlength="100"
                               placeholder="Name shown in GCash"
                               value="<?= esc((string) old('account_name', $account['account_name'] ?? ''), 'attr') ?>">
                        <div class="form-text">Helps clients confirm they are paying the right person.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="qr">GCash QR code</label>
                        <input id="qr" name="qr" type="file" class="form-control" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text">PNG, JPG or WebP, up to 2 MB. <?= $hasQr ? 'Choosing a new file replaces the current QR.' : 'Screenshot the QR from GCash &gt; QR &gt; My QR.' ?></div>
                    </div>
                    <?php if ($hasQr): ?>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remove_qr" name="remove_qr" value="1">
                                <label class="form-check-label" for="remove_qr">Remove my current QR code</label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save GCash account</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <div class="col-lg-4">
        <section class="cs-panel">
            <div class="cs-panel__head"><h2 class="cs-panel__title">What clients see</h2></div>
            <div class="cs-panel__body">
                <?php if (! $account): ?>
                    <p class="cs-muted mb-0">Nothing saved yet. Until you add your GCash number, clients in your area are told no collector GCash account is set up.</p>
                <?php else: ?>
                    <small class="cs-muted d-block">GCash number</small>
                    <strong class="d-block mb-2"><?= esc((string) $account['gcash_number']) ?></strong>
                    <?php if (! empty($account['account_name'])): ?>
                        <small class="cs-muted d-block">Account name</small>
                        <strong class="d-block mb-2"><?= esc((string) $account['account_name']) ?></strong>
                    <?php endif; ?>
                    <?php if ($hasQr): ?>
                        <img src="<?= base_url('gcash-qr/' . (int) session('user_id')) ?>?v=<?= esc((string) strtotime((string) ($account['updated_at'] ?? 'now')), 'attr') ?>"
                             alt="Your GCash QR code" class="img-fluid rounded border cs-qr">
                    <?php else: ?>
                        <p class="cs-muted mb-0">No QR uploaded - clients will only see your number.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
