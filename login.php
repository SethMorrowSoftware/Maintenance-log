<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Request;
use App\Settings;
use App\View;

Auth::requireGuest();

$error      = '';
$identifier = '';
$redirect   = Request::safeRedirect('redirect');

if (is_post()) {
    Csrf::verify();

    $identifier = Request::string('username');
    $password   = (string) Request::input('password', '');
    $remember   = Request::bool('remember');

    $result = Auth::attempt($identifier, $password, $remember);

    if ($result['ok']) {
        flash('success', 'Welcome back, ' . Auth::name($result['user']) . '.');
        // A path that starts with "/" already carries the site's folder, so
        // it goes back as it came; a bare page name gets the folder added.
        redirect($redirect === null
            ? url('index.php')
            : (strpos($redirect, '/') === 0 ? $redirect : url($redirect)));
    }

    $error = $result['error'];
}

ob_start();
?>
<form method="post" action="<?= e(url('login.php', $redirect !== null ? ['redirect' => $redirect] : [])) ?>"
      data-validate autocomplete="on">
    <?= csrf_field() ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert">
            <?= icon('alert-circle', '', 18) ?>
            <div class="alert-body"><?= e($error) ?></div>
        </div>
    <?php endif; ?>

    <?php View::partial('form-field', [
        'name'     => 'username',
        'label'    => 'Username or email',
        'type'     => 'text',
        'value'    => $identifier,
        'required' => true,
        'noOld'    => true,
        'attrs'    => ['autocomplete' => 'username', 'autofocus' => true, 'autocapitalize' => 'none', 'spellcheck' => 'false'],
    ]); ?>

    <?php View::partial('form-field', [
        'name'     => 'password',
        'label'    => 'Password',
        'type'     => 'password',
        'required' => true,
        'noOld'    => true,
        'attrs'    => ['autocomplete' => 'current-password'],
    ]); ?>

    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
        <label class="form-check" for="f_remember" style="padding:0">
            <input type="checkbox" id="f_remember" name="remember" value="1">
            <span class="form-check-label">Keep me signed in</span>
        </label>
        <a class="text-sm" href="<?= e(url('forgot-password.php')) ?>">Forgot your password?</a>
    </div>

    <button type="submit" class="btn btn-primary btn-block btn-lg">
        <?= icon('login', '', 18) ?> Sign in
    </button>
</form>

<div class="auth-footer">
    <?= e(Settings::organizationName()) ?>
    <div class="text-xs text-subtle mt-1">
        Trouble signing in? Ask an administrator to reset your password.
    </div>
</div>
<?php
$content = (string) ob_get_clean();

View::render('raw', [
    'html'    => $content,
    'title'   => 'Sign in',
    'heading' => Settings::siteName(),
    'tagline' => 'Maintenance log and dashboard',
], 'layout-auth');
