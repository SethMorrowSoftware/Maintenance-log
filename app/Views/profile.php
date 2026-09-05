<?php
/**
 * My profile.
 *
 * Variables: $me, $tab, $tabs, $forced, $stats, $recentLogs
 */

use App\Acl;
use App\Dates;
use App\Settings;
use App\Status;
use App\View;
?>

<?php if ($forced): ?>
    <div class="alert alert-warning">
        <?= icon('key', '', 18) ?>
        <div class="alert-body">
            <strong class="alert-title">Choose a new password to continue</strong>
            <p style="margin:4px 0 0">
                Your account is using a password an administrator set for you. Pick your own before
                you carry on.
            </p>
        </div>
    </div>
<?php endif; ?>

<div class="grid grid-sidebar">
    <div>
        <?php View::partial('tabs', ['tabs' => $tabs, 'active' => $tab, 'mode' => 'link']); ?>

        <div class="tab-panel">

            <?php // ============================ Details ============================ ?>
            <?php if ($tab === 'details'): ?>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= icon('user', '', 18) ?> Your details</h2>
                    </div>
                    <form method="post" action="<?= e(url('profile.php')) ?>" data-validate data-guard>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="details">
                        <div class="card-body">
                            <div class="form-row cols-2">
                                <?php View::partial('form-field', [
                                    'name' => 'first_name', 'label' => 'First name', 'type' => 'text',
                                    'value' => $me['first_name'], 'required' => true,
                                ]); ?>
                                <?php View::partial('form-field', [
                                    'name' => 'last_name', 'label' => 'Last name', 'type' => 'text',
                                    'value' => $me['last_name'], 'required' => true,
                                ]); ?>
                            </div>

                            <?php View::partial('form-field', [
                                'name' => 'email', 'label' => 'Email address', 'type' => 'email',
                                'value' => $me['email'], 'required' => true,
                                'hint' => 'Used for password resets and, if enabled, notifications.',
                            ]); ?>

                            <div class="form-row cols-2">
                                <?php View::partial('form-field', [
                                    'name' => 'phone', 'label' => 'Phone', 'type' => 'tel',
                                    'value' => $me['phone'],
                                ]); ?>
                                <?php View::partial('form-field', [
                                    'name' => 'job_title', 'label' => 'Job title', 'type' => 'text',
                                    'value' => $me['job_title'],
                                    'placeholder' => 'Ride Technician',
                                ]); ?>
                            </div>

                            <dl class="detail-list mt-5">
                                <dt>Username</dt>
                                <dd>
                                    <code><?= e((string) $me['username']) ?></code>
                                    <span class="text-subtle text-sm">— only an administrator can change this</span>
                                </dd>

                                <dt>Role</dt>
                                <dd>
                                    <?php View::partial('status-badge', ['value' => (string) $me['role'], 'vocabulary' => 'role']); ?>
                                    <div class="text-sm text-muted mt-1">
                                        <?= e(Acl::roleDescriptions()[(string) $me['role']] ?? '') ?>
                                    </div>
                                </dd>

                                <?php if (!empty($me['employee_number'])): ?>
                                    <dt>Employee number</dt>
                                    <dd><?= e((string) $me['employee_number']) ?></dd>
                                <?php endif; ?>

                                <dt>Member since</dt>
                                <dd><?= e(Dates::date((string) $me['created_at'])) ?></dd>

                                <dt>Last signed in</dt>
                                <dd>
                                    <?php if (!empty($me['last_login_at'])): ?>
                                        <?= e(Dates::datetime((string) $me['last_login_at'])) ?>
                                        <span class="text-subtle">(<?= e(Dates::ago((string) $me['last_login_at'])) ?>)</span>
                                    <?php else: ?>
                                        <span class="text-subtle">This is your first session</span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                        <div class="card-footer">
                            <span class="flex-1"></span>
                            <button type="submit" class="btn btn-primary">
                                <?= icon('save', '', 17) ?> Save details
                            </button>
                        </div>
                    </form>
                </div>

            <?php // =========================== Password ============================ ?>
            <?php elseif ($tab === 'password'): ?>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= icon('key', '', 18) ?> Change your password</h2>
                    </div>
                    <form method="post" action="<?= e(url('profile.php')) ?>" data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="password">
                        <div class="card-body">

                            <?php if (!$forced): ?>
                                <div class="form-group<?= has_error('current_password') ? ' has-error' : '' ?>">
                                    <label class="form-label" for="f_current">Current password<span class="required">*</span></label>
                                    <div class="password-field">
                                        <input type="password" class="form-input" id="f_current"
                                               name="current_password" required autocomplete="current-password">
                                        <button type="button" class="password-toggle" data-password-toggle
                                                aria-label="Show password"><?= icon('eye', '', 17) ?></button>
                                    </div>
                                    <?php if (has_error('current_password')): ?>
                                        <div class="form-error"><span><?= e(error_for('current_password')) ?></span></div>
                                    <?php endif; ?>
                                </div>
                                <hr>
                            <?php endif; ?>

                            <div class="form-group<?= has_error('password') ? ' has-error' : '' ?>">
                                <label class="form-label" for="f_new">New password<span class="required">*</span></label>
                                <div class="password-field">
                                    <input type="password" class="form-input" id="f_new" name="password"
                                           required minlength="<?= (int) Settings::passwordMinLength() ?>"
                                           autocomplete="new-password" <?= $forced ? 'autofocus' : '' ?>>
                                    <button type="button" class="password-toggle" data-password-toggle
                                            aria-label="Show password"><?= icon('eye', '', 17) ?></button>
                                </div>
                                <div class="form-hint">
                                    At least <?= (int) Settings::passwordMinLength() ?> characters. A short
                                    phrase you will remember beats a scramble you will not.
                                </div>
                                <?php if (has_error('password')): ?>
                                    <div class="form-error"><span><?= e(error_for('password')) ?></span></div>
                                <?php endif; ?>
                            </div>

                            <div class="form-group<?= has_error('password_confirmation') ? ' has-error' : '' ?>">
                                <label class="form-label" for="f_confirm">Confirm new password<span class="required">*</span></label>
                                <input type="password" class="form-input" id="f_confirm"
                                       name="password_confirmation" required autocomplete="new-password">
                                <?php if (has_error('password_confirmation')): ?>
                                    <div class="form-error"><span><?= e(error_for('password_confirmation')) ?></span></div>
                                <?php endif; ?>
                            </div>

                            <p class="text-sm text-muted mb-0">
                                Changing your password signs out every other device that was kept signed in,
                                and cancels any outstanding reset links.
                            </p>
                        </div>
                        <div class="card-footer">
                            <span class="flex-1"></span>
                            <button type="submit" class="btn btn-primary">
                                <?= icon('key', '', 17) ?> Change password
                            </button>
                        </div>
                    </form>
                </div>

            <?php // ========================== Preferences ========================== ?>
            <?php else: ?>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= icon('settings', '', 18) ?> Preferences</h2>
                    </div>
                    <form method="post" action="<?= e(url('profile.php')) ?>" data-guard>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="preferences">
                        <div class="card-body">

                            <?php View::partial('form-field', [
                                'name'    => 'theme',
                                'label'   => 'Appearance',
                                'type'    => 'select',
                                'value'   => (string) ($me['theme'] ?? 'system'),
                                'options' => [
                                    'system' => 'Match my device',
                                    'light'  => 'Always light',
                                    'dark'   => 'Always dark',
                                ],
                                'hint' => 'The toggle in the header changes this too.',
                            ]); ?>

                            <?php View::partial('form-field', [
                                'name'   => 'timezone',
                                'label'  => 'Your time zone',
                                'type'   => 'select',
                                'value'  => (string) ($me['timezone'] ?? ''),
                                'groups' => Dates::timezones(),
                                'empty'  => 'Use the site default (' . e((string) Settings::get('timezone', 'UTC')) . ')',
                                'hint'   => 'Only affects how times are displayed to you.',
                            ]); ?>

                            <?php View::partial('form-field', [
                                'name'       => 'notify_email',
                                'label'      => 'Email notifications',
                                'type'       => 'checkbox',
                                'value'      => (int) ($me['notify_email'] ?? 1),
                                'checkLabel' => 'Email me about work assigned to me and maintenance that falls due',
                                'hint'       => Settings::mailEnabled()
                                    ? 'In-app notifications always appear in the bell menu regardless.'
                                    : 'Email is currently switched off for the whole site, so nothing will be sent.',
                            ]); ?>
                        </div>
                        <div class="card-footer">
                            <span class="flex-1"></span>
                            <button type="submit" class="btn btn-primary">
                                <?= icon('save', '', 17) ?> Save preferences
                            </button>
                        </div>
                    </form>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <?php // ============================== Sidebar ============================== ?>
    <div>
        <div class="card">
            <div class="card-body" style="text-align:center">
                <div style="display:inline-block;position:relative">
                    <?php View::partial('avatar', ['user' => $me, 'size' => 'xl']); ?>
                </div>
                <h3 class="mt-3 mb-1"><?= e(user_name($me)) ?></h3>
                <p class="text-muted text-sm mb-3"><?= e((string) ($me['job_title'] ?: Acl::roleLabel((string) $me['role']))) ?></p>

                <form method="post" action="<?= e(url('profile.php')) ?>" enctype="multipart/form-data"
                      class="flex flex-col gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="avatar">
                    <label class="btn btn-secondary btn-sm btn-block" style="cursor:pointer">
                        <?= icon('camera', '', 16) ?> Change picture
                        <?php // data-autosubmit is wired in core.js; an inline
                              // onchange would need 'unsafe-inline' in the CSP. ?>
                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                               class="sr-only" data-autosubmit>
                    </label>
                </form>

                <?php if (!empty($me['avatar_path'])): ?>
                    <form method="post" action="<?= e(url('profile.php')) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_avatar">
                        <button type="submit" class="btn btn-ghost btn-sm btn-block"
                                data-confirm="Remove your profile picture?">Remove picture</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Your activity</h3>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <dt>Maintenance logs</dt>
                    <dd><strong><?= e(num($stats['logs'])) ?></strong></dd>
                    <dt>Inspections run</dt>
                    <dd><strong><?= e(num($stats['inspections'])) ?></strong></dd>
                    <dt>Open work orders</dt>
                    <dd>
                        <strong><?= e(num($stats['open_work_orders'])) ?></strong>
                        <?php if ($stats['open_work_orders'] > 0): ?>
                            <a class="text-sm" href="<?= e(url('workorders.php', ['assigned_to' => (int) $me['id']])) ?>">view</a>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>

        <?php if ($recentLogs !== []): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Your recent work</h3>
                </div>
                <ul class="activity-list">
                    <?php foreach ($recentLogs as $log): ?>
                        <li class="activity-item">
                            <span class="status-dot tone-<?= e(Status::tone((string) $log['log_type'], 'log_type')) ?>"
                                  style="margin-top:7px"></span>
                            <span class="activity-body">
                                <a href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>">
                                    <?= e((string) $log['title']) ?>
                                </a>
                                <div class="text-sm text-muted">
                                    <?= e((string) $log['asset_name']) ?>
                                    &middot; <?= e(Dates::ago((string) $log['performed_at'])) ?>
                                </div>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
