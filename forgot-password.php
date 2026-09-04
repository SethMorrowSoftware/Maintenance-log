<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Mailer;
use App\Request;
use App\Settings;
use App\View;

Auth::requireGuest();

$sent  = false;
$email = '';
$error = '';

if (is_post()) {
    Csrf::verify();

    $email = Request::string('email');

    if ($email === '') {
        $error = 'Enter the email address on your account.';
    } else {
        $link = Auth::createPasswordReset($email);

        if ($link !== null && Settings::mailEnabled()) {
            $body = '<p>Somebody asked to reset the password for your '
                  . e(Settings::siteName()) . ' account.</p>'
                  . '<p><a href="' . e($link) . '">Choose a new password</a></p>'
                  . '<p>The link works once and expires in ' . Auth::RESET_MINUTES . ' minutes. '
                  . 'If you did not ask for this, ignore this message: nothing has changed.</p>'
                  . '<p style="font-size:12px;color:#666;word-break:break-all">'
                  . 'If the link does not work, copy this address into your browser:<br>'
                  . e($link) . '</p>';

            Mailer::send($email, 'Reset your ' . Settings::siteName() . ' password', $body);
        }

        // The same answer whether or not the account exists: revealing which
        // addresses are registered hands an attacker a list of valid usernames.
        $sent = true;
    }
}

ob_start();
?>
<?php if ($sent): ?>

    <div class="alert alert-success" role="status">
        <?= icon('mail', '', 18) ?>
        <div class="alert-body">
            <strong class="alert-title">Check your email</strong>
            <p style="margin:4px 0 0">
                If an account exists for <strong><?= e($email) ?></strong>, a reset link is on its way.
                It works once and expires in <?= (int) Auth::RESET_MINUTES ?> minutes.
            </p>
        </div>
    </div>

    <?php if (!Settings::mailEnabled()): ?>
        <div class="alert alert-warning">
            <?= icon('alert-triangle', '', 18) ?>
            <div class="alert-body">
                <strong class="alert-title">Email is switched off on this site</strong>
                <p style="margin:4px 0 0">
                    No message can be sent. Ask an administrator to reset your password from the
                    Users screen, or to turn on email in Settings.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <a class="btn btn-secondary btn-block mt-4" href="<?= e(url('login.php')) ?>">Back to sign in</a>

<?php else: ?>

    <form method="post" action="<?= e(url('forgot-password.php')) ?>" data-validate>
        <?= csrf_field() ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <?= icon('alert-circle', '', 18) ?>
                <div class="alert-body"><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <p class="text-muted mb-4">
            Enter the email address on your account and we will send you a link to choose a new password.
        </p>

        <?php View::partial('form-field', [
            'name'     => 'email',
            'label'    => 'Email address',
            'type'     => 'email',
            'value'    => $email,
            'required' => true,
            'noOld'    => true,
            'attrs'    => ['autocomplete' => 'email', 'autofocus' => true],
        ]); ?>

        <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
        <a class="btn btn-ghost btn-block mt-2" href="<?= e(url('login.php')) ?>">Back to sign in</a>
    </form>

<?php endif; ?>
<?php
View::render('raw', [
    'html'    => (string) ob_get_clean(),
    'title'   => 'Forgot password',
    'heading' => 'Forgot your password?',
    'tagline' => '',
], 'layout-auth');
