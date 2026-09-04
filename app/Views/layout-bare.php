<?php
/**
 * Minimal layout with no navigation. Used for error pages, which must render
 * even when the session or the database is unavailable.
 *
 * Variables: $title, $content, $bodyClass
 */

use App\Settings;

$title     = $title     ?? 'RideLog';
$bodyClass = $bodyClass ?? '';

$siteName = 'RideLog';

try {
    $siteName = Settings::siteName();
} catch (\Throwable $e) {
    // The database may be the reason we are on an error page at all.
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title . ' · ' . $siteName) ?></title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset_url('assets/img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
<script src="<?= e(asset_url('assets/js/theme-init.js')) ?>"></script>
</head>
<body class="<?= e(trim('auth-page ' . $bodyClass)) ?>">
<main class="auth-card">
    <?= $content ?? '' ?>
</main>
</body>
</html>
