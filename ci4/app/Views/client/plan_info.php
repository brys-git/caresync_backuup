<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$program = $program ?? ['name' => 'Damayan Burial Program'];
$monthlyFee = (float) ($monthly_fee ?? 240.0);
$totalContribution = (float) ($total_contribution ?? 14500.0);
$entitlementPackage = $entitlement_package ?? null;
$otherPackages = $other_packages ?? [];
$services = $services ?? [];
$minMonths = (int) ceil($monthlyFee > 0 ? 480 / $monthlyFee : 2);
?>
<div class="cs-narrow">
    <section class="cs-panel mb-3">
        <div class="cs-panel__head"><h2 class="cs-panel__title"><?= esc((string) ($program['name'] ?? 'Damayan Burial Program')) ?></h2></div>
        <div class="cs-panel__body">
            <?= view('components/stat_rail', ['stats' => [
                ['label' => 'Monthly contribution', 'value' => $monthlyFee, 'money' => true],
                ['label' => 'Initial payment (to activate)', 'value' => $monthlyFee * max(2, $minMonths), 'money' => true, 'meta' => 'Minimum ' . max(2, $minMonths) . ' months up front'],
                ['label' => 'Full contribution target', 'value' => $totalContribution, 'money' => true, 'tone' => 'money', 'meta' => 'Unlocks your free casket benefit'],
            ]]) ?>
            <p class="cs-muted small mb-0">
                Once you've completed at least 2 months of contributions, you can already avail or claim from the
                Services &amp; Packages catalog. Once your total contributions reach <?= cs_money($totalContribution) ?>,
                your standard casket benefit below becomes free to claim.
            </p>
        </div>
    </section>

    <?php if ($entitlementPackage): ?>
        <section class="cs-panel cs-panel--brass mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title"><i class="ti ti-award me-1"></i>Your Damayan entitlement</h2></div>
            <div class="cs-panel__body">
                <p class="mb-3">
                    Every Damayan Plan Holder is entitled to claim a
                    <strong><?= esc((string) $entitlementPackage['package_name']) ?></strong>
                    (worth <?= cs_money($entitlementPackage['base_price']) ?>) at
                    <strong>no additional cost</strong> once your <?= cs_money($totalContribution) ?> contribution
                    target is fully paid.
                </p>
                <p class="fw-semibold mb-2">What's included</p>
                <?php $this->setData(['inclusions' => $inclusions ?? [], 'compact' => true]); ?>
                <?= $this->include('client/partials/inclusions_list') ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (! empty($otherPackages)): ?>
        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Other casket options</h2></div>
            <div class="cs-panel__body">
                <p class="cs-muted small mb-2">
                    Prefer a different casket? These are also available - as an active Damayan Plan Holder in good
                    standing, a <?= cs_money($totalContribution) ?> benefit credit is applied toward any of
                    them, waiving the remaining unpaid contribution rather than adding it on top.
                </p>
                <?php foreach ($otherPackages as $pkg): ?>
                    <div class="cs-routerow">
                        <span><?= esc((string) $pkg['package_name']) ?></span>
                        <span class="fw-semibold"><?= cs_money($pkg['base_price']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (! empty($services)): ?>
        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Other services</h2></div>
            <div class="cs-panel__body">
                <?php foreach ($services as $svc): ?>
                    <div class="mb-2">
                        <div class="fw-semibold"><?= esc((string) $svc['service_name']) ?></div>
                        <div class="cs-muted small"><?= esc((string) ($svc['description'] ?? '')) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="cs-panel">
        <div class="cs-panel__body">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="agreePlan">
                <label class="form-check-label" for="agreePlan">I agree to the terms and conditions</label>
            </div>
        </div>
        <div class="cs-panel__foot d-flex justify-content-end">
            <a id="proceedBtn" href="<?= base_url('plan-registration') ?>" class="btn btn-primary disabled" aria-disabled="true">Proceed to registration</a>
        </div>
    </section>
</div>

<script>
    (function () {
        const checkbox = document.getElementById('agreePlan');
        const button = document.getElementById('proceedBtn');

        if (!checkbox || !button) {
            return;
        }

        checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
                button.classList.remove('disabled');
                button.setAttribute('aria-disabled', 'false');
            } else {
                button.classList.add('disabled');
                button.setAttribute('aria-disabled', 'true');
            }
        });
    })();
</script>
<?= $this->endSection() ?>
