<?php
/**
 * Centred card layout for sign-in, password reset and similar pages.
 *
 * Variables: $title, $content, $bodyClass, $heading, $tagline
 */

use App\Csrf;
use App\Settings;
use App\View;

$title     = $title     ?? 'Sign in';
$bodyClass = $bodyClass ?? '';
$heading   = $heading   ?? Settings::siteName();
$tagline   = $tagline   ?? 'Maintenance log and dashboard';
$siteName  = Settings::siteName();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#4f46e5">
<meta name="robots" content="noindex, nofollow">
<?= Csrf::metaTag() ?>
<title><?= e($title . ' · ' . $siteName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset_url('assets/img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
<script src="<?= e(asset_url('assets/js/theme-init.js')) ?>"></script>
<script type="application/json" id="rl-config"><?= js([
    'baseUrl'   => app_base_path() . '/',
    'apiUrl'    => url('api/index.php'),
    'csrfToken' => csrf_token(),
    'userId'    => null,
    'canNotify' => false,
]) ?></script>
</head>
<body class="<?= e(trim('auth-page ' . $bodyClass)) ?>">

<main class="auth-card<?= isset($wide) && $wide ? ' is-wide' : '' ?>">
    <div class="auth-brand">
        <span class="brand-mark" aria-hidden="true"><?php require APP_ROOT . '/assets/img/logo.svg'; ?></span>
        <h1><?= e($heading) ?></h1>
        <?php if ($tagline !== ''): ?><p><?= e($tagline) ?></p><?php endif; ?>
    </div>

    <?php View::partial('flash', ['inline' => true]); ?>

    <?= $content ?? '' ?>
</main>

<div id="toast-root" aria-live="polite"></div>
<script src="<?= e(asset_url('assets/js/core.js')) ?>" defer></script>
</body>
</html>
