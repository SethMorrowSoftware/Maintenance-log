<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Request;
use App\Settings;
use App\Validator;
use App\View;

Auth::requireGuest();

$selector = Request::string('selector');
$token    = Request::string('token');
$user     = Auth::validateResetToken($selector, $token);
$errors   = [];
$done     = false;

if ($user !== null && is_post()) {
    Csrf::verify();

    $validator = Validator::make($_POST, [
        'password' => 'required|password|confirmed',
    ], [
        'password.confirmed' => 'The two passwords do not match.',
    ], [
        'password' => 'Password',
    ]);

    // The same rule as the profile page: not your own name, username or email.
    $personal = $validator->fails() ? '' : Auth::validatePassword((string) $_POST['password'], $user);

    if ($validator->fails()) {
        $errors = $validator->errors();
    } elseif ($personal !== '') {
        $errors['password'] = $personal;
    } elseif (Auth::consumeReset($selector, $token, (string) $_POST['password'])) {
        $done = true;
    } else {
        $errors['_form'] = 'That link is no longer valid. Please request a new one.';
        $user = null;
    }
}

ob_start();
?>
<?php if ($done): ?>

    <div class="alert alert-success" role="status">
        <?= icon('check-circle', '', 18) ?>
        <div class="alert-body">
            <strong class="alert-title">Password changed</strong>
            <p style="margin:4px 0 0">
                You can sign in with your new password now. Any other devices that were kept
                signed in have been signed out.
            </p>
        </div>
    </div>
    <a class="btn btn-primary btn-block mt-4" href="<?= e(url('login.php')) ?>">Sign in</a>

<?php elseif ($user === null): ?>

    <div class="alert alert-error" role="alert">
        <?= icon('alert-circle', '', 18) ?>
        <div class="alert-body">
            <strong class="alert-title">This link cannot be used</strong>
            <p style="margin:4px 0 0">
                Reset links work once and expire after <?= (int) Auth::RESET_MINUTES ?> minutes.
                This one has been used, has expired, or was mistyped.
            </p>
        </div>
    </div>
    <a class="btn btn-primary btn-block mt-4" href="<?= e(url('forgot-password.php')) ?>">
        Request a new link
    </a>
    <a class="btn btn-ghost btn-block mt-2" href="<?= e(url('login.php')) ?>">Back to sign in</a>

<?php else: ?>

    <form method="post" action="<?= e(url('reset-password.php', ['selector' => $selector, 'token' => $token])) ?>"
          data-validate>
        <?= csrf_field() ?>

        <?php if (!empty($errors['_form'])): ?>
            <div class="alert alert-error" role="alert">
                <?= icon('alert-circle', '', 18) ?>
                <div class="alert-body"><?= e((string) $errors['_form']) ?></div>
            </div>
        <?php endif; ?>

        <p class="text-muted mb-4">
            Choose a new password for <strong><?= e((string) $user['username']) ?></strong>.
        </p>

        <div class="form-group<?= isset($errors['password']) ? ' has-error' : '' ?>">
            <label class="form-label" for="f_password">New password<span class="required">*</span></label>
            <div class="password-field">
                <input type="password" class="form-input" id="f_password" name="password"
                       required minlength="<?= (int) Settings::passwordMinLength() ?>"
                       autocomplete="new-password" autofocus>
                <button type="button" class="password-toggle" data-password-toggle
                        aria-label="Show password"><?= icon('eye', '', 17) ?></button>
            </div>
            <div class="form-hint">
                At least <?= (int) Settings::passwordMinLength() ?> characters.
                A short phrase you will remember beats a scramble you will not.
            </div>
            <?php if (isset($errors['password'])): ?>
                <div class="form-error"><span><?= e((string) $errors['password']) ?></span></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label" for="f_password_confirmation">Confirm new password<span class="required">*</span></label>
            <input type="password" class="form-input" id="f_password_confirmation"
                   name="password_confirmation" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary btn-block">Set new password</button>
    </form>

<?php endif; ?>
<?php
View::render('raw', [
    'html'    => (string) ob_get_clean(),
    'title'   => 'Reset password',
    'heading' => 'Choose a new password',
    'tagline' => '',
], 'layout-auth');
