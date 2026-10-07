<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$state = (string) ($access['state'] ?? 'new');
$canApply = (bool) ($can_apply ?? false);
$isEntitlement = (bool) ($is_entitlement ?? false);
$entitlement = $entitlement ?? null;
$benefitCredit = (float) ($benefit_credit ?? 0);
$attirePrice = (float) ($attire_price ?? 0);
$basePrice = (float) ($package['base_price'] ?? 0);
$eligibleToClaim = $isEntitlement && (bool) ($entitlement['eligible'] ?? false);
?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>
<div class="cs-narrow">
    <a class="btn btn-ghost mb-3" href="<?= site_url('/client/service?tab=packages') ?>">
        <i class="ti ti-arrow-left me-1"></i> Back to packages
    </a>

    <div class="cs-hero mb-3">
        <div class="cs-hero__image">
            <?php if (! empty($package['image_path'])): ?>
                <img src="<?= esc(base_url((string) $package['image_path'])) ?>" alt="<?= esc((string) ($package['package_name'] ?? 'Package')) ?>">
            <?php else: ?>
                <i class="ti ti-package" aria-hidden="true"></i>
            <?php endif; ?>
        </div>
        <div class="cs-hero__body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div class="fs-4 fw-semibold"><?= cs_money($basePrice) ?></div>
                <?php if ($isEntitlement): ?>
                    <?= $eligibleToClaim ? cs_status('approved', 'Claim') : cs_status('inactive', 'Your entitlement') ?>
                <?php else: ?>
                    <?= cs_status('available', 'Available') ?>
                <?php endif; ?>
            </div>
            <p class="cs-muted mb-0"><?= esc((string) ($package['description'] ?? 'No description available.')) ?></p>
        </div>
    </div>

    <section class="cs-panel mb-3">
        <div class="cs-panel__head"><h2 class="cs-panel__title">What's included</h2></div>
        <div class="cs-panel__body">
            <?php $this->setData(['inclusions' => $inclusions ?? []]); ?>
            <?= $this->include('client/partials/inclusions_list') ?>
        </div>
    </section>

    <?php if ($isEntitlement): ?>
        <section class="cs-panel cs-panel--brass mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Your Damayan entitlement</h2></div>
            <div class="cs-panel__body">
                <?php if ($eligibleToClaim): ?>
                    <p class="mb-3"><?= cs_status('approved', 'Eligible to claim') ?><br>
                        <span class="cs-muted small">You're eligible to claim this Regular Wood Casket at no cost.</span>
                    </p>
                <?php else: ?>
                    <p class="mb-3"><?= cs_status('inactive', 'Not yet eligible') ?><br>
                        <span class="cs-muted small"><?= esc((string) ($entitlement['locked_reason'] ?? 'This entitlement is not available to claim yet.')) ?></span>
                    </p>
                <?php endif; ?>

                <?php if ($canApply && $eligibleToClaim): ?>
                    <a class="btn btn-primary" href="<?= site_url('/client/apply-package/' . (int) ($package['package_id'] ?? 0)) ?>">Claim regular casket</a>
                <?php elseif ($canApply): ?>
                    <button type="button" class="btn btn-primary" disabled>Claim regular casket</button>
                <?php else: ?>
                    <?= view('components/alert', ['type' => 'info', 'dismissible' => false, 'class' => 'mb-0', 'message' => (string) ($entitlement['locked_reason'] ?? 'You must register as an active Plan Holder to claim this entitlement.')]) ?>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Available package</h2></div>
            <div class="cs-panel__body">
                <?php if ($canApply && $benefitCredit > 0): ?>
                    <div class="cs-routerow"><span>Package price</span><?= cs_money($basePrice) ?></div>
                    <div class="cs-routerow cs-routerow--credit"><span><i class="ti ti-award me-1"></i>Damayan benefit credit</span><span>− <?= cs_money($benefitCredit) ?></span></div>
                    <div class="cs-routerow cs-routerow--total"><span>Amount due</span><?= cs_money(max(0, $basePrice - $benefitCredit)) ?></div>
                    <p class="cs-muted small mt-2 mb-3">As a Damayan Plan Holder, your remaining unpaid contribution is waived against this package - it is not added on top of the amount due above.</p>
                <?php else: ?>
                    <p class="cs-muted small mb-3">Full package price applies. Damayan Plan Holders in good standing receive a <?= cs_money(14500) ?> benefit credit toward this package.</p>
                <?php endif; ?>

                <?php if ($canApply): ?>
                    <a class="btn btn-primary" href="<?= site_url('/client/apply-package/' . (int) ($package['package_id'] ?? 0)) ?>">Avail package</a>
                <?php elseif ($state === 'pending'): ?>
                    <?= view('components/alert', ['type' => 'warning', 'dismissible' => false, 'class' => 'mb-0', 'message' => 'Approval required before requesting services.']) ?>
                <?php else: ?>
                    <?= view('components/alert', ['type' => 'info', 'dismissible' => false, 'message' => 'You must register as a Plan Holder to apply.']) ?>
                    <a class="btn btn-primary" href="<?= site_url('/plan-info') ?>">Register now</a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="cs-panel">
        <div class="cs-panel__body">
            <?= cs_status('scheduled', 'Optional add-on') ?>
            <div class="mt-2 fw-semibold">Burial attire package — <?= cs_money($attirePrice) ?></div>
            <p class="cs-muted small mb-0">A complete burial attire set for the deceased. Not included by default - you can select it on the application form.</p>
        </div>
    </section>
</div>
<?= $this->endSection() ?>
