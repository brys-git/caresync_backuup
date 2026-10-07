<?php
/**
 * One sidebar for every role. Reads Config\Nav.
 * Expects $navDef, $navCounts, $activeNav from layouts/_shell.php.
 */
$navDef    = $navDef    ?? config('Nav')->fallback;
$navCounts = $navCounts ?? [];
$activeNav = (string) ($activeNav ?? '');
?>
<aside class="cs-sidebar" id="cs-sidebar">
    <a class="cs-brand" href="<?= base_url('dashboard') ?>">
        <span class="cs-brand__mark">
            <img src="<?= cs_asset_url('assets/images/company-logo.png') ?>" alt="">
        </span>
        <span class="cs-brand__text">
            <span class="cs-brand__name">CareSync</span>
            <span class="cs-brand__role"><?= esc((string) ($navDef['label'] ?? '')) ?></span>
        </span>
    </a>

    <nav class="cs-nav" aria-label="Main">
        <?php foreach (($navDef['groups'] ?? []) as $group): ?>
            <div class="cs-nav__group">
                <?php if (! empty($group['title'])): ?>
                    <div class="cs-nav__group-title"><?= esc((string) $group['title']) ?></div>
                <?php endif; ?>

                <?php foreach (($group['items'] ?? []) as $item): ?>
                    <?php
                    // 'capability', when set, hides the item unless that
                    // check passes - e.g. 'collect' => can_collect()
                    // (caresync_helper.php). The route itself stays gated
                    // regardless; this only avoids showing a Staff account
                    // a link that would just 403 for them.
                    if (($item['capability'] ?? '') === 'collect' && ! can_collect()) {
                        continue;
                    }
                    $key    = (string) ($item['key'] ?? '');
                    $count  = isset($item['count']) ? (int) ($navCounts[$item['count']] ?? 0) : 0;
                    $active = $key !== '' && $key === $activeNav;
                    ?>
                    <a class="cs-nav__link <?= $active ? 'is-active' : '' ?>"
                       href="<?= base_url((string) ($item['url'] ?? '#')) ?>"
                       <?= $active ? 'aria-current="page"' : '' ?>
                       title="<?= esc((string) ($item['label'] ?? '')) ?>">
                        <i class="ti <?= esc((string) ($item['icon'] ?? 'ti-point')) ?>" aria-hidden="true"></i>
                        <span class="cs-nav__label"><?= esc((string) ($item['label'] ?? '')) ?></span>
                        <?php if ($count > 0): ?>
                            <span class="cs-nav__count"><?= $count > 99 ? '99+' : $count ?>
                                <span class="cs-visually-hidden">pending</span>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="cs-sidebar__foot">
        <a class="cs-nav__link" href="<?= base_url(($cs_role ?? '') === 'plan_holder' ? 'client/profile' : ($cs_role ?? 'admin') . '/profile') ?>">
            <i class="ti ti-settings" aria-hidden="true"></i>
            <span class="cs-nav__label cs-sidebar__foot-text">Account settings</span>
        </a>
        <a class="cs-nav__link cs-nav__link--exit" href="<?= base_url('logout') ?>"
           data-cs-confirm="Sign out of CareSync?">
            <i class="ti ti-logout" aria-hidden="true"></i>
            <span class="cs-nav__label cs-sidebar__foot-text">Sign out</span>
        </a>
    </div>
</aside>
