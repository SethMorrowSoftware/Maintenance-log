<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Flash;

/**
 * Signing out changes state, so it is a POST with a CSRF token. A plain link
 * could be triggered by a prefetcher or an <img> tag on another site, which is
 * only a nuisance here but is the kind of habit worth keeping.
 */
if (is_post()) {
    Csrf::verify();
    Auth::logout();
    Flash::clear();

    @session_start();
    flash('success', 'You have been signed out.');

    redirect(url('login.php'));
}

// A GET lands here only if someone followed a stale link: offer the button.
if (!Auth::check()) {
    redirect(url('login.php'));
}

ob_start();
?>
<form method="post" action="<?= e(url('logout.php')) ?>">
    <?= csrf_field() ?>
    <p class="text-center mb-4">Sign out of <?= e(App\Settings::siteName()) ?>?</p>
    <button type="submit" class="btn btn-primary btn-block">Sign out</button>
    <a class="btn btn-ghost btn-block mt-2" href="<?= e(url('index.php')) ?>">Stay signed in</a>
</form>
<?php
App\View::render('raw', [
    'html'    => (string) ob_get_clean(),
    'title'   => 'Sign out',
    'heading' => 'Sign out',
    'tagline' => '',
], 'layout-auth');
