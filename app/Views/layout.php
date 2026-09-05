<?php
/**
 * The main application layout.
 *
 * Variables (all optional except $content):
 *   $title        string  page title
 *   $subtitle     string  small line under the page title
 *   $content      string  rendered view HTML
 *   $activeNav    string  script name of the current page, e.g. "assets.php"
 *   $breadcrumbs  array   [['label' => ..., 'url' => ...], ...]
 *   $pageActions  string  HTML for the buttons beside the page title
 *   $bodyClass    string  extra classes on <body>
 *   $extraCss     array   extra stylesheet paths
 *   $extraJs      array   extra script paths, loaded deferred
 *   $hidePageHeader bool  suppress the title block (for pages that draw their own)
 */

use App\Acl;
use App\Csrf;
use App\Notifier;
use App\Request;
use App\Settings;
use App\View;

$title          = $title          ?? 'RideLog';
$subtitle       = $subtitle       ?? '';
$activeNav      = $activeNav      ?? Request::script();
$breadcrumbs    = $breadcrumbs    ?? [];
$pageActions    = $pageActions    ?? '';
$bodyClass      = $bodyClass      ?? '';
$extraCss       = $extraCss       ?? [];
$extraJs        = $extraJs        ?? [];
$hidePageHeader = $hidePageHeader ?? false;

$me        = user();
$siteName  = Settings::siteName();
$logoUrl   = Settings::logoUrl();
$fullTitle = $title === $siteName ? $siteName : $title . ' · ' . $siteName;
$showBell  = $me !== null && feature_on('notifications');
$unread    = $showBell ? Notifier::unreadCount((int) $me['id']) : 0;

/**
 * The whole navigation, in one place. Each item names the permission that
 * reveals it; the server still enforces access on the page itself.
 */
$navigation = [
    'Overview' => [
        ['label' => 'Dashboard', 'url' => 'index.php', 'icon' => 'dashboard', 'permission' => null],
    ],
    'Maintenance' => [
        ['label' => 'Maintenance Logs', 'url' => 'logs.php',        'icon' => 'wrench',           'permission' => 'logs.view'],
        ['label' => 'Work Orders',      'url' => 'workorders.php',  'icon' => 'work-order',       'permission' => 'workorders.view'],
        ['label' => 'Scheduled Service','url' => 'schedules.php',   'icon' => 'calendar',         'permission' => 'schedules.view'],
        ['label' => "Today's Checks",   'url' => 'checks.php',      'icon' => 'checklist',        'permission' => 'inspections.view'],
        ['label' => 'Inspections',      'url' => 'inspections.php', 'icon' => 'clipboard-check',  'permission' => 'inspections.view'],
    ],
    'Equipment' => [
        ['label' => asset_word(true, true),          'url' => 'assets.php', 'icon' => 'assets',  'permission' => 'assets.view'],
        ['label' => 'Parts',           'url' => 'parts.php',  'icon' => 'package', 'permission' => 'parts.view'],
    ],
    'Insight' => [
        ['label' => 'Reports', 'url' => 'reports.php', 'icon' => 'chart-bar', 'permission' => 'reports.view'],
    ],
    'Administration' => [
        ['label' => 'Users',                  'url' => 'users.php',      'icon' => 'users',     'permission' => 'users.view'],
        ['label' => 'Roles',                  'url' => 'roles.php',      'icon' => 'shield',    'permission' => 'users.manage'],
        ['label' => 'Checklists',             'url' => 'checklists.php', 'icon' => 'checklist', 'permission' => 'checklists.view'],
        ['label' => 'Categories & Locations', 'url' => 'categories.php', 'icon' => 'tag',       'permission' => 'assets.edit'],
        ['label' => 'Settings',               'url' => 'settings.php',   'icon' => 'settings',  'permission' => 'settings.manage'],
        ['label' => 'Audit Log',              'url' => 'audit.php',      'icon' => 'history',   'permission' => 'audit.view'],
    ],
];

/** Pages that should light up a nav item other than their own file name. */
$navAliases = [
    'asset-view.php'      => 'assets.php',
    'asset-edit.php'      => 'assets.php',
    'labels.php'          => 'assets.php',
    'log-view.php'        => 'logs.php',
    'log-edit.php'        => 'logs.php',
    'schedule-edit.php'   => 'schedules.php',
    'checklist-edit.php'  => 'checklists.php',
    'inspection-run.php'  => 'inspections.php',
    'inspection-view.php' => 'inspections.php',
    'workorder-view.php'  => 'workorders.php',
    'workorder-edit.php'  => 'workorders.php',
    'part-view.php'       => 'parts.php',
    'part-edit.php'       => 'parts.php',
    'user-edit.php'       => 'users.php',
];

$currentNav = $navAliases[$activeNav] ?? $activeNav;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#4f46e5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#171a22" media="(prefers-color-scheme: dark)">
<meta name="robots" content="noindex, nofollow">
<?= Csrf::metaTag() ?>

<title><?= e($fullTitle) ?></title>

<link rel="icon" type="image/svg+xml" href="<?= e(asset_url('assets/img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/print.css')) ?>" media="print">
<?php foreach ($extraCss as $css): ?>
<link rel="stylesheet" href="<?= e(asset_url($css)) ?>">
<?php endforeach; ?>

<?php // Applies the saved theme before first paint so the page never flashes. ?>
<script src="<?= e(asset_url('assets/js/theme-init.js')) ?>"></script>

<?php // Server data for the client, as JSON rather than an inline script (CSP). ?>
<script type="application/json" id="rl-config"><?= js([
    'baseUrl'    => app_base_path() . '/',
    'apiUrl'     => url('api/index.php'),
    'csrfToken'  => csrf_token(),
    'userId'     => $me === null ? null : (int) $me['id'],
    'theme'      => $me === null ? 'system' : (string) ($me['theme'] ?? 'system'),
    'currency'   => Settings::currency(),
    'canNotify'  => $me !== null,
    'clearDrafts' => \App\Flash::draftsToClear(),
    'assetWord'  => ['one' => asset_word(), 'many' => asset_word(true)],
]) ?></script>
</head>

<body class="<?= e(trim('app ' . $bodyClass)) ?>" data-nav="<?= e($currentNav) ?>">

<a class="skip-link" href="#main-content">Skip to content</a>

<div class="app-shell">

    <!-- ================= Sidebar ================= -->
    <aside class="app-sidebar" id="app-sidebar" aria-label="Main navigation">
        <a class="sidebar-brand" href="<?= e(url('index.php')) ?>">
            <span class="brand-mark" aria-hidden="true">
                <?php if ($logoUrl !== null): ?>
                    <img src="<?= e($logoUrl) ?>" alt="">
                <?php else: ?>
                    <?php require APP_ROOT . '/assets/img/logo.svg'; ?>
                <?php endif; ?>
            </span>
            <span class="brand-text"><?= e($siteName) ?></span>
        </a>

        <nav class="sidebar-nav">
            <?php foreach ($navigation as $groupName => $items): ?>
                <?php
                $visible = [];

                foreach ($items as $item) {
                    if ($item['permission'] === null || can($item['permission'])) {
                        $visible[] = $item;
                    }
                }

                if ($visible === []) {
                    continue;
                }
                ?>
                <div class="nav-group">
                    <div class="nav-group-title"><?= e($groupName) ?></div>
                    <?php foreach ($visible as $item): ?>
                        <a class="nav-link<?= $currentNav === $item['url'] ? ' is-active' : '' ?>"
                           href="<?= e(url($item['url'])) ?>"
                           <?= $currentNav === $item['url'] ? 'aria-current="page"' : '' ?>>
                            <?= icon($item['icon'], '', 18) ?>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <div><?= e($siteName) ?></div>
            <div>RideLog v<?= e(RIDELOG_VERSION) ?></div>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebar-backdrop" hidden></div>

    <!-- ================= Main ================= -->
    <div class="app-main">

        <header class="app-header">
            <button type="button" class="header-menu-btn" id="sidebar-toggle"
                    aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false">
                <?= icon('menu') ?>
            </button>

            <?php if ($me !== null): ?>
                <div class="header-search" id="header-search">
                    <?= icon('search', '', 17) ?>
                    <label class="sr-only" for="global-search">Search <?= e(asset_word(true)) ?>, logs and work orders</label>
                    <input type="search" id="global-search" placeholder="Search…"
                           autocomplete="off" spellcheck="false" data-global-search>
                    <span class="kbd hide-sm" aria-hidden="true">/</span>
                </div>
            <?php endif; ?>

            <div class="header-spacer"></div>

            <div class="header-actions">
                <button type="button" class="header-btn" id="theme-toggle"
                        aria-label="Switch between light and dark theme" title="Switch theme">
                    <span class="theme-icon-light"><?= icon('sun') ?></span>
                    <span class="theme-icon-dark" hidden><?= icon('moon') ?></span>
                </button>

                <?php if ($me !== null): ?>
                    <?php if ($showBell): ?>
                        <a class="header-btn" href="<?= e(url('notifications.php')) ?>"
                           aria-label="Notifications" title="Notifications" id="notification-bell">
                            <?= icon('bell') ?>
                            <span class="notif-dot" id="notif-count" <?= $unread > 0 ? '' : 'hidden' ?>><?= $unread > 99 ? '99+' : (int) $unread ?></span>
                        </a>
                    <?php endif; ?>

                    <div class="user-menu">
                        <button type="button" class="user-menu-trigger" id="user-menu-trigger"
                                aria-haspopup="true" aria-expanded="false" aria-controls="user-menu-dropdown">
                            <?php View::partial('avatar', ['user' => $me]); ?>
                            <span class="user-menu-name">
                                <strong><?= e(user_name($me)) ?></strong>
                                <span><?= e(Acl::roleLabel((string) $me['role'])) ?></span>
                            </span>
                            <?= icon('chevron-down', '', 15) ?>
                        </button>

                        <div class="dropdown" id="user-menu-dropdown" role="menu" hidden>
                            <div class="dropdown-header">
                                <strong><?= e(user_name($me)) ?></strong>
                                <span><?= e((string) $me['email']) ?></span>
                            </div>
                            <a class="dropdown-item" role="menuitem" href="<?= e(url('profile.php')) ?>">
                                <?= icon('user', '', 17) ?> My profile
                            </a>
                            <a class="dropdown-item" role="menuitem" href="<?= e(url('profile.php', ['tab' => 'password'])) ?>">
                                <?= icon('key', '', 17) ?> Change password
                            </a>
                            <?php if ($showBell): ?>
                                <a class="dropdown-item" role="menuitem" href="<?= e(url('notifications.php')) ?>">
                                    <?= icon('bell', '', 17) ?> Notifications
                                    <?php if ($unread > 0): ?>
                                        <span class="badge badge-danger" style="margin-left:auto"><?= (int) $unread ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <form method="post" action="<?= e(url('logout.php')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item is-danger" role="menuitem">
                                    <?= icon('logout', '', 17) ?> Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <main class="app-content" id="main-content">
            <?php View::partial('flash'); ?>

            <?php if (!$hidePageHeader): ?>
                <?php if ($breadcrumbs !== []): ?>
                    <?php View::partial('breadcrumbs', ['breadcrumbs' => $breadcrumbs]); ?>
                <?php endif; ?>

                <div class="page-header">
                    <div>
                        <h1 class="page-title"><?= e($title) ?></h1>
                        <?php if ($subtitle !== ''): ?>
                            <p class="page-subtitle"><?= e($subtitle) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($pageActions !== ''): ?>
                        <div class="page-actions no-print"><?= $pageActions ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?= $content ?? '' ?>
        </main>

        <footer class="app-footer no-print">
            <div>
                <?= e(Settings::organizationName()) ?>
                <?php $footerText = (string) setting('footer_text', ''); ?>
                <?php if ($footerText !== ''): ?>
                    &middot; <?= e($footerText) ?>
                <?php endif; ?>
            </div>
            <div>RideLog v<?= e(RIDELOG_VERSION) ?></div>
        </footer>
    </div>
</div>

<div id="toast-root" aria-live="polite" aria-atomic="false"></div>
<div id="modal-root"></div>

<script src="<?= e(asset_url('assets/js/core.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/js/charts.js')) ?>" defer></script>
<?php foreach ($extraJs as $script): ?>
<script src="<?= e(asset_url($script)) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
