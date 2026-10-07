<?php
/**
 * CareSync topbar.
 *
 * Answers three questions in the top 60px, on every screen:
 *   where am I (breadcrumbs), which branch am I acting on (chip), who am I (menu).
 *
 * All inputs are optional and degrade quietly.
 */
$crumbs   = $crumbs   ?? [];
$csUser   = $cs_user  ?? [];
$csBranch = (string) ($cs_branch ?? '');
// _shell.php pushes this to every include as 'navCounts' (matching
// partials/sidebar.php's own $navCounts) via setData() - this used to read
// $nav_counts, a variable that's never actually passed here, so the badge
// was permanently stuck at 0.
$unread   = (int) (($navCounts['unread'] ?? 0));

$userName = trim((string) ($csUser['name'] ?? ''));
if ($userName === '') {
    $userName = 'Signed in';
}

// Initials for the avatar: "Maria Santos" -> MS
$initials = '';
foreach (preg_split('/\s+/', $userName) as $part) {
    if ($part !== '' && ctype_alpha($part[0])) {
        $initials .= strtoupper($part[0]);
    }
    if (strlen($initials) === 2) {
        break;
    }
}
$initials = $initials !== '' ? $initials : 'CS';

$isHolder    = ($cs_role ?? '') === 'plan_holder';
// Collector has no client-management page (their own Collection List is
// already scoped to their assigned area) - this used to still render
// action="collector/client-management", a route that's never existed, so
// submitting the search box 404'd.
// Each role's own plan-holder list. (The role key 'branch_admin' is not the URL
// segment 'branch-admin', so building the URL from the key 404'd for Branch Admin.)
$clientSearchUrls = [
    'admin'        => 'admin/client-management',
    'branch_admin' => 'branch-admin/client-management',
    'staff'        => 'staff/client-management',
];
$hasClientSearch = isset($clientSearchUrls[$cs_role ?? '']);
$topSearchTerm   = (string) ($search_term ?? '');
if ($topSearchTerm === '' && $hasClientSearch && str_ends_with(trim(service('request')->getUri()->getPath(), '/'), 'client-management')) {
    $topSearchTerm = cs_search_term(service('request')->getGet('search'));
}
$profileUrl  = $isHolder ? 'client/profile' : ($cs_role ?? 'admin') . '/profile';
$passwordUrl = 'change-password';
?>
<header class="cs-topbar">
    <button class="cs-iconbtn cs-navtoggle" type="button"
            data-cs-navtoggle aria-expanded="false" aria-controls="cs-sidebar">
        <i class="ti ti-menu-2" aria-hidden="true"></i>
        <span class="cs-visually-hidden">Open navigation</span>
    </button>

    <button class="cs-iconbtn d-none d-lg-inline-grid" type="button"
            data-cs-collapse aria-expanded="true" aria-controls="cs-sidebar">
        <i class="ti ti-layout-sidebar-left-collapse" aria-hidden="true"></i>
        <span class="cs-visually-hidden">Collapse navigation</span>
    </button>

    <?php if ($crumbs !== []): ?>
        <nav class="cs-crumbs d-none d-md-flex" aria-label="Breadcrumb">
            <?php $last = count($crumbs) - 1; ?>
            <?php foreach ($crumbs as $i => $crumb): ?>
                <?php if ($i > 0): ?>
                    <span class="cs-crumbs__sep" aria-hidden="true">/</span>
                <?php endif; ?>

                <?php if ($i === $last || empty($crumb['url'])): ?>
                    <span class="cs-crumbs__here"><?= esc((string) ($crumb['label'] ?? '')) ?></span>
                <?php else: ?>
                    <a href="<?= base_url((string) $crumb['url']) ?>"><?= esc((string) ($crumb['label'] ?? '')) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if ($csBranch !== ''): ?>
        <span class="cs-branchchip" title="You are working in this branch">
            <i class="ti ti-building-community" aria-hidden="true"></i>
            <?= esc($csBranch) ?>
        </span>
    <?php endif; ?>

    <?php if ($hasClientSearch): ?>
        <form class="cs-topsearch" role="search" method="get" action="<?= base_url($clientSearchUrls[$cs_role]) ?>">
            <i class="ti ti-search" aria-hidden="true"></i>
            <input type="search" name="search" placeholder="Find a plan holder"
                   aria-label="Find a plan holder" value="<?= esc($topSearchTerm) ?>" maxlength="100">
        </form>
    <?php else: ?>
        <span class="ms-auto"></span>
    <?php endif; ?>

    <a class="cs-iconbtn" href="<?= base_url($isHolder ? 'client/notification' : 'notifications') ?>">
        <i class="ti ti-bell" aria-hidden="true"></i>
        <?php if ($unread > 0): ?>
            <span class="cs-iconbtn__dot"><?= $unread > 9 ? '9+' : $unread ?></span>
        <?php endif; ?>
        <span class="cs-visually-hidden">
            Notifications<?= $unread > 0 ? ', ' . $unread . ' unread' : '' ?>
        </span>
    </a>

    <div class="dropdown">
        <button class="cs-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <?php if (! empty($csUser['photo_url'])): ?>
                <img class="cs-avatar cs-avatar--photo" src="<?= esc($csUser['photo_url'], 'attr') ?>" alt="" aria-hidden="true">
            <?php else: ?>
                <span class="cs-avatar" aria-hidden="true"><?= esc($initials) ?></span>
            <?php endif; ?>
            <span class="cs-user__name"><?= esc($userName) ?></span>
            <i class="ti ti-chevron-down cs-muted" aria-hidden="true" style="font-size:.875rem"></i>
        </button>

        <ul class="dropdown-menu dropdown-menu-end">
            <li class="px-2 py-2 d-flex align-items-center gap-2" style="min-width:220px">
                <?php if (! empty($csUser['photo_url'])): ?>
                    <img class="cs-avatar cs-avatar--lg cs-avatar--photo" src="<?= esc($csUser['photo_url'], 'attr') ?>" alt="" aria-hidden="true">
                <?php else: ?>
                    <span class="cs-avatar cs-avatar--lg" aria-hidden="true"><?= esc($initials) ?></span>
                <?php endif; ?>
                <span class="lh-sm">
                    <span class="d-block fw-semibold"><?= esc($userName) ?></span>
                    <span class="d-block cs-muted" style="font-size:.78125rem">
                        <?= esc((string) ($csUser['role'] ?? ($navDef['label'] ?? 'CareSync user'))) ?>
                    </span>
                </span>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="<?= base_url($profileUrl) ?>">
                    <i class="ti ti-user-circle me-1" aria-hidden="true"></i> My profile
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="<?= base_url($passwordUrl) ?>">
                    <i class="ti ti-key me-1" aria-hidden="true"></i> Change password
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item text-danger" href="<?= base_url('logout') ?>"
                   data-cs-confirm="Sign out of CareSync?">
                    <i class="ti ti-logout me-1" aria-hidden="true"></i> Sign out
                </a>
            </li>
        </ul>
    </div>
</header>
