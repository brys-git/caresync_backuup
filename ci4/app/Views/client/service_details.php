<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$state = (string) ($access['state'] ?? 'new');
$canApply = (bool) ($can_apply ?? false);
$routes = $routes ?? [];
?>
<?php // Flash messages are already surfaced as toasts by layouts/_shell.php - no need to render them again here. ?>
<div class="cs-narrow">
    <a class="btn btn-ghost mb-3" href="<?= site_url('/client/service?tab=services') ?>">
        <i class="ti ti-arrow-left me-1"></i> Back to services
    </a>

    <div class="cs-hero mb-3">
        <div class="cs-hero__image">
            <?php if (! empty($service['image_path'])): ?>
                <img src="<?= esc(base_url((string) $service['image_path'])) ?>" alt="<?= esc((string) ($service['service_name'] ?? 'Service')) ?>">
            <?php else: ?>
                <i class="ti ti-truck" aria-hidden="true"></i>
            <?php endif; ?>
        </div>
        <div class="cs-hero__body">
            <p class="mb-0 cs-muted"><?= esc((string) ($service['description'] ?? 'No description available.')) ?></p>
        </div>
    </div>

    <?php if (! empty($routes)): ?>
        <section class="cs-panel mb-3">
            <div class="cs-panel__head"><h2 class="cs-panel__title">Available routes</h2></div>
            <div class="cs-panel__body cs-panel__body--flush">
                <div class="cs-tablewrap">
                    <table class="cs-table">
                        <thead><tr><th>Route</th><th>Casket benefit</th><th class="cs-num">Price</th></tr></thead>
                        <tbody>
                            <?php foreach ($routes as $route): ?>
                                <tr>
                                    <td><?= esc((string) $route['route_name']) ?></td>
                                    <td>
                                        <?php if (! empty($route['casket_benefit_covered'])): ?>
                                            <?= cs_status('approved', 'Covered') ?>
                                            <span class="cs-muted small">Separate casket payment: <?= cs_money(0) ?></span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td class="cs-num"><?= cs_money($route['price']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="cs-panel__foot">
                <span class="cs-muted small">The route price above is the full transport service fee. Your Damayan casket entitlement is a separate benefit and does not reduce this fee.</span>
            </div>
        </section>
    <?php else: ?>
        <p class="fw-semibold mb-3">Price: <?= cs_money($service['base_price'] ?? 0) ?></p>
    <?php endif; ?>

    <?php if ($canApply): ?>
        <a class="btn btn-primary" href="<?= site_url('/client/apply-service/' . (int) ($service['service_list_id'] ?? 0)) ?>">Apply for service</a>
    <?php elseif ($state === 'pending'): ?>
        <?= view('components/alert', ['type' => 'warning', 'dismissible' => false, 'message' => 'Approval required before requesting services.']) ?>
    <?php else: ?>
        <?= view('components/alert', ['type' => 'info', 'dismissible' => false, 'message' => 'You must register as a Plan Holder to apply.']) ?>
        <a class="btn btn-primary" href="<?= site_url('/plan-info') ?>">Register now</a>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
