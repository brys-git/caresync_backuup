<?php
/**
 * CareSync application shell — the only real layout in the system.
 *
 * Role layouts (layouts/admin.php, layouts/staff.php, …) are thin wrappers that
 * set $cs_role and include this file, so controllers keep passing
 * 'role_layout' => 'layouts/admin' exactly as they do now. No controller changes.
 *
 * Optional view data, all with fallbacks:
 *   $cs_role      string  admin | branch_admin | staff | collector | plan_holder
 *   $page_title   string  browser title + page heading
 *   $page_sub     string  one line under the heading
 *   $crumbs       array   [['label' => 'Plan holders', 'url' => '...'], ['label' => 'Maria Santos']]
 *   $active_nav   string  nav key to highlight (falls back to URL matching in JS)
 *   $nav_counts   array   ['pending_approvals' => 4, 'unread' => 2]
 *   $cs_user      array   ['name' => 'Ana Cruz', 'role' => 'Branch administrator']
 *   $cs_branch    string  'Calapan Branch'
 *   $page_actions string  raw HTML for the buttons on the right of the heading
 */

// Derive the role from role_layout when the wrapper didn't set it.
$cs_role = $cs_role ?? basename((string) ($role_layout ?? 'layouts/admin'), '.php');

$nav        = config('Nav');
$navDef     = $nav->for($cs_role);
$navCounts  = $nav_counts ?? [];
// Nothing ever passed nav_counts['unread'] from a controller, so the bell
// badge (topbar.php) and the plan_holder sidebar's "Notifications" count
// (Nav.php) were permanently stuck at 0 even with real unread rows in the
// table. Computed here, once, as the shared shell every role's layout
// extends - so it fills in the badge everywhere without touching every
// controller's view() call, and any controller that does pass its own
// 'unread' still wins.
if (! array_key_exists('unread', $navCounts)) {
    $navUserId = (int) (session('user_id') ?? 0);
    $navCounts['unread'] = $navUserId > 0
        ? (new \App\Services\NotificationService())->getUnreadCount($navUserId)
        : 0;
}
$activeNav  = (string) ($active_nav ?? '');
$pageTitle  = (string) ($page_title ?? 'Dashboard');
$navPref    = (string) (service('request')->getCookie('cs_nav') ?? 'open');
$isHolder   = $cs_role === 'plan_holder';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1b2473">
    <title><?= esc($pageTitle) ?> · CareSync</title>
    <link rel="icon" href="<?= base_url('assets/favicon_io/favicon.ico') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/main.css') ?>">
    <link rel="stylesheet" href="<?= cs_asset_url('assets/css/caresync.css') ?>">
</head>
<body class="<?= $navPref === 'collapsed' ? 'cs-nav-collapsed' : '' ?> <?= $isHolder ? 'cs-holder-view' : '' ?>">

<div class="cs-app">
    <?php
    // $this->include() starts a fresh render that only sees data pushed via
    // setData()/setVar() - a plain "$navDef = ..." assignment above never
    // reaches the included view, so without this, partials/sidebar.php finds
    // $navDef undefined and silently falls back to config('Nav')->fallback
    // (a single "Dashboard" link only).
    $this->setData([
        'navDef'    => $navDef,
        'navCounts' => $navCounts,
        'activeNav' => $activeNav,
        'cs_role'   => $cs_role,
    ], 'raw');
    ?>
    <?= $this->include('partials/sidebar') ?>

    <div class="cs-main">
        <?= $this->include('partials/topbar') ?>

        <div class="cs-content">
            <?php
            // Flash messages become toasts so they don't shove the page down.
            foreach (['success' => 'ok', 'error' => 'stop', 'message' => 'info'] as $key => $kind):
                $flash = session()->getFlashdata($key);
                if ($flash):
                    ?>
                    <div class="cs-visually-hidden" data-cs-flash="<?= $kind ?>"><?= esc($flash) ?></div>
                <?php endif;
            endforeach; ?>

            <?php if ($pageTitle !== '' && ($show_page_head ?? true)): ?>
                <header class="cs-pagehead <?= $isHolder ? 'cs-pagehead--holder' : '' ?>">
                    <div>
                        <h1 class="cs-pagehead__title"><?= esc($pageTitle) ?></h1>
                        <?php if (! empty($page_sub)): ?>
                            <p class="cs-pagehead__sub"><?= esc($page_sub) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (! empty($page_actions)): ?>
                        <div class="cs-pagehead__actions"><?= $page_actions ?></div>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <?= $this->renderSection('content') ?>
        </div>
    </div>
</div>

<div class="cs-scrim" data-cs-scrim aria-hidden="true"></div>

<script src="<?= base_url('assets/js/caresync.js') ?>" defer></script>
<script src="<?= base_url('assets/js/main.js') ?>" type="module"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
