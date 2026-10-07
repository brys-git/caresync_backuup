<?= $this->extend($role_layout) ?>

<?= $this->section('content') ?>
<?php
$e = static fn ($v): string => esc((string) ($v ?? ''));
$peso = static fn ($v): string => '₱' . number_format((float) $v, 2);
$isPackages = (string) $active_tab === 'packages';
$canApply = (bool) $eligibility['eligible'];
$claimBlocked = $entitlement !== null && ! $entitlement_claim && ! $claim_eligible;
$helperText = ! $canApply ? (string) $eligibility['reason'] : ($claimBlocked ? (string) $claim_reason : '');
$packageCount = count($packages) + ($entitlement !== null ? 1 : 0);
$placeholder = '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>';
$shield = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg>';
?>
<link rel="stylesheet" href="<?= cs_asset_url('assets/css/client-catalog.css') ?>">

<div class="rq-page">
    <header class="rq-head">
        <div>
            <h1 class="rq-title">Request a service</h1>
            <p class="rq-sub">Funeral packages and services available through your plan.</p>
        </div>
        <p class="rq-pill <?= $canApply ? 'rq-pill--ok' : 'rq-pill--warn' ?>" role="status">
            <?php if ($canApply): ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                <span><strong>You can apply</strong> · <?= $e($eligibility['detail']) ?></span>
            <?php else: ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg>
                <span><?= $e($eligibility['reason']) ?></span>
            <?php endif; ?>
        </p>
    </header>

    <?php if ($entitlement !== null): ?>
        <?php $eId = (int) $entitlement['package_id']; ?>
        <article class="rq-feature" aria-labelledby="rq-ent-title">
            <div class="rq-media rq-media--feature">
                <div class="rq-ph" aria-hidden="true"><?= $placeholder ?></div>
                <?php if (! empty($entitlement['image_url'])): ?>
                    <img src="<?= $e($entitlement['image_url']) ?>" alt="<?= $e($entitlement['package_name']) ?>" loading="lazy" onerror="this.remove()">
                <?php endif; ?>
            </div>
            <div class="rq-feature__body">
                <span class="rq-badge"><?= $shield ?> Included in your plan</span>
                <h2 id="rq-ent-title" class="rq-feature__name"><?= $e($entitlement['package_name']) ?></h2>
                <p class="rq-body">Your Damayan plan entitles you to this casket at no cost. Claim it when you need it — staff will handle the rest.</p>
                <p class="rq-price">
                    <s class="rq-muted rq-num"><?= $peso($entitlement['base_price']) ?></s>
                    <span class="rq-covered">Fully covered</span>
                </p>
                <div class="rq-actions">
                    <?php if ($entitlement_claim): ?>
                        <?php $claimIsApproved = $entitlement_claim['status'] === 'approved'; ?>
                        <span class="rq-pill <?= $claimIsApproved ? 'rq-pill--ok' : 'rq-pill--warn' ?>"><?= $claimIsApproved ? 'Approved' : 'Pending review' ?></span>
                        <a class="rq-btn rq-btn--ghost" href="<?= site_url('/client/apply-package/submitted/' . (int) $entitlement_claim['application_id']) ?>">View claim</a>
                    <?php elseif ($claim_eligible): ?>
                        <a class="rq-btn rq-btn--green" href="<?= site_url('/client/apply-package/' . $eId) ?>">Claim casket</a>
                    <?php else: ?>
                        <button type="button" class="rq-btn rq-btn--green" disabled aria-describedby="rq-why">Claim casket</button>
                    <?php endif; ?>
                    <a class="rq-btn rq-btn--ghost" href="<?= site_url('/client/package/' . $eId) ?>">View details</a>
                </div>
            </div>
        </article>
    <?php endif; ?>

    <div class="rq-toolbar">
        <nav class="rq-tabs" role="tablist" aria-label="Catalog">
            <a role="tab" class="rq-tab" aria-selected="<?= $isPackages ? 'true' : 'false' ?>" href="<?= site_url('/client/service?tab=packages') ?>">
                Casket packages <span class="rq-count"><?= $packageCount ?></span>
            </a>
            <a role="tab" class="rq-tab" aria-selected="<?= $isPackages ? 'false' : 'true' ?>" href="<?= site_url('/client/service?tab=services') ?>">
                Services <span class="rq-count"><?= count($services) ?></span>
            </a>
        </nav>
        <?php if ($isPackages && count($packages) > 1): ?>
            <label class="rq-sort">
                <span>Sort by</span>
                <select id="rq-sort" class="rq-select">
                    <option value="asc">Price low to high</option>
                    <option value="desc">Price high to low</option>
                </select>
            </label>
        <?php endif; ?>
    </div>

    <?php if ($helperText !== ''): ?>
        <p id="rq-why" class="rq-why">Requests are paused for now: <?= $e($helperText) ?></p>
    <?php endif; ?>

    <?php if ($isPackages): ?>
        <?php if ($packages === []): ?>
            <p class="rq-empty">No casket packages are available right now.</p>
        <?php else: ?>
            <div class="rq-grid" id="rq-grid">
                <?php foreach ($packages as $pkg): ?>
                    <?php
                    $pId = (int) $pkg['package_id'];
                    $names = $pkg['inclusion_names'] ?? [];
                    $summary = $names !== []
                        ? implode(', ', array_slice($names, 0, 4)) . ' — ' . count($names) . ' ' . (count($names) === 1 ? 'inclusion' : 'inclusions') . '. See details for the full list.'
                        : (string) ($pkg['description'] ?? '');
                    ?>
                    <article class="rq-card" data-price="<?= $e(number_format((float) $pkg['base_price'], 2, '.', '')) ?>">
                        <div class="rq-media">
                            <div class="rq-ph" aria-hidden="true"><?= $placeholder ?></div>
                            <?php if (! empty($pkg['image_url'])): ?>
                                <img src="<?= $e($pkg['image_url']) ?>" alt="<?= $e($pkg['package_name']) ?>" loading="lazy" onerror="this.remove()">
                            <?php endif; ?>
                            <?php if ($names !== []): ?>
                                <span class="rq-tag">All-in program</span>
                            <?php endif; ?>
                        </div>
                        <div class="rq-card__body">
                            <h3 class="rq-card__name"><?= $e($pkg['package_name']) ?></h3>
                            <p class="rq-card__desc"><?= $e($summary) ?></p>
                            <p class="rq-card__price rq-num"><?= $peso($pkg['base_price']) ?></p>
                            <div class="rq-card__actions">
                                <a class="rq-btn rq-btn--ghost" href="<?= site_url('/client/package/' . $pId) ?>">Details</a>
                                <?php if ($canApply): ?>
                                    <a class="rq-btn rq-btn--primary" href="<?= site_url('/client/apply-package/' . $pId) ?>">Avail</a>
                                <?php else: ?>
                                    <button type="button" class="rq-btn rq-btn--primary" disabled aria-describedby="rq-why">Avail</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($services === []): ?>
            <p class="rq-empty">No services are available right now.</p>
        <?php else: ?>
            <div class="rq-services">
                <?php foreach ($services as $svc): ?>
                    <?php
                    $sId = (int) $svc['service_list_id'];
                    $routes = $svc['routes'] ?? [];
                    $firstRouteId = $routes !== [] ? (int) $routes[0]['route_id'] : 0;
                    $applyBase = site_url('/client/apply-service/' . $sId);
                    ?>
                    <article class="rq-svc">
                        <div class="rq-media">
                            <div class="rq-ph" aria-hidden="true"><?= $placeholder ?></div>
                            <?php if (! empty($svc['image_url'])): ?>
                                <img src="<?= $e($svc['image_url']) ?>" alt="<?= $e($svc['service_name']) ?>" loading="lazy" onerror="this.remove()">
                            <?php endif; ?>
                        </div>
                        <div class="rq-svc__body">
                            <h3 class="rq-card__name"><?= $e($svc['service_name']) ?></h3>
                            <p class="rq-body"><?= $e($svc['description'] ?? '') ?></p>

                            <?php if ($routes !== []): ?>
                                <fieldset class="rq-routes">
                                    <legend class="rq-label">Choose a route</legend>
                                    <?php foreach ($routes as $i => $route): ?>
                                        <?php $isSelected = $i === 0; ?>
                                        <label class="rq-route<?= $isSelected ? ' is-selected' : '' ?>">
                                            <input type="radio" name="route_<?= $sId ?>" value="<?= (int) $route['route_id'] ?>" data-apply="apply-<?= $sId ?>" data-base="<?= $e($applyBase) ?>" <?= $isSelected ? 'checked' : '' ?>>
                                            <span class="rq-route__name"><?= $e($route['route_name']) ?></span>
                                            <span class="rq-num"><?= $peso($route['price']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php else: ?>
                                <p class="rq-price rq-num"><?= $peso($svc['base_price'] ?? 0) ?></p>
                            <?php endif; ?>

                            <div class="rq-actions">
                                <?php if ($canApply): ?>
                                    <a id="apply-<?= $sId ?>" class="rq-btn rq-btn--primary" href="<?= $e($applyBase . ($firstRouteId > 0 ? '?route=' . $firstRouteId : '')) ?>">Apply for this service</a>
                                <?php else: ?>
                                    <button type="button" class="rq-btn rq-btn--primary" disabled aria-describedby="rq-why">Apply for this service</button>
                                <?php endif; ?>
                                <a class="rq-btn rq-btn--ghost" href="<?= site_url('/client/service/' . $sId) ?>">View details</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var sort = document.getElementById('rq-sort');
    var grid = document.getElementById('rq-grid');
    if (sort && grid) {
        sort.addEventListener('change', function () {
            var cards = Array.prototype.slice.call(grid.querySelectorAll('.rq-card'));
            var direction = sort.value === 'desc' ? -1 : 1;
            cards.sort(function (a, b) {
                return direction * (Number(a.getAttribute('data-price')) - Number(b.getAttribute('data-price')));
            });
            cards.forEach(function (card) { grid.appendChild(card); });
        });
    }

    document.querySelectorAll('input[type="radio"][data-apply]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            var link = document.getElementById(radio.getAttribute('data-apply'));
            if (link) { link.href = radio.getAttribute('data-base') + '?route=' + radio.value; }
            radio.closest('fieldset').querySelectorAll('.rq-route').forEach(function (row) {
                row.classList.toggle('is-selected', row.querySelector('input').checked);
            });
        });
    });
})();
</script>
<?= $this->endSection() ?>
