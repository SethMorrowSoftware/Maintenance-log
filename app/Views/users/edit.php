<?php
/**
 * Add or edit somebody.
 *
 * The role picker is the important control on this page, so each role says in
 * one sentence what the person will actually be able to do rather than making
 * an administrator go and look it up.
 */

use App\Acl;
use App\Dates;
use App\View;

$targetId = $editing ? (int) $target['id'] : 0;

// After a rejected save an unticked box is absent from what came back, so
// "missing" has to mean "off" rather than "keep the stored value".
$rejected  = old('username', null) !== null;
$wasTicked = static function (string $field, int $stored) use ($rejected): int {
    return $rejected ? (int) old($field, 0) : $stored;
};
?>

<form method="post" action="<?= e(url('user-edit.php', $editing ? ['id' => $targetId] : [])) ?>" data-guard>
    <?= csrf_field() ?>

    <div class="grid grid-sidebar">
        <div>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('user', '', 18) ?> Who they are</h2>
                </div>
                <div class="card-body">
                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name'     => 'first_name',
                            'label'    => 'First name',
                            'value'    => $values['first_name'],
                            'required' => true,
                            'attrs'    => ['maxlength' => 80, 'autofocus' => !$editing],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'     => 'last_name',
                            'label'    => 'Last name',
                            'value'    => $values['last_name'],
                            'required' => true,
                            'attrs'    => ['maxlength' => 80],
                        ]); ?>
                    </div>

                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name'     => 'username',
                            'label'    => 'Username',
                            'value'    => $values['username'],
                            'required' => true,
                            'hint'     => 'What they type to sign in. Letters, numbers, dots and dashes.',
                            'attrs'    => ['maxlength' => 64, 'autocapitalize' => 'off', 'autocorrect' => 'off'],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'     => 'email',
                            'label'    => 'Email address',
                            'type'     => 'email',
                            'value'    => $values['email'],
                            'required' => true,
                            'hint'     => 'Used for password resets and notifications.',
                            'attrs'    => ['maxlength' => 190],
                        ]); ?>
                    </div>

                    <div class="form-row cols-3">
                        <?php View::partial('form-field', [
                            'name'  => 'phone',
                            'label' => 'Phone',
                            'type'  => 'tel',
                            'value' => $values['phone'],
                            'attrs' => ['maxlength' => 40],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'        => 'job_title',
                            'label'       => 'Job title',
                            'value'       => $values['job_title'],
                            'placeholder' => 'Ride mechanic',
                            'attrs'       => ['maxlength' => 100],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'  => 'employee_number',
                            'label' => 'Employee number',
                            'value' => $values['employee_number'],
                            'attrs' => ['maxlength' => 50],
                        ]); ?>
                    </div>
                </div>
            </div>

            <?php // ==================== Role ==================== ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= icon('shield', '', 18) ?> What they can do</h2>
                        <p class="card-subtitle">Pick the smallest one that covers their job</p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (has_error('role')): ?>
                        <p class="form-error"><?= e(error_for('role')) ?></p>
                    <?php endif; ?>

                    <?php foreach ($roles as $roleKey => $roleLabel): ?>
                        <label class="form-check-card" for="role_<?= e((string) $roleKey) ?>">
                            <input type="radio" id="role_<?= e((string) $roleKey) ?>" name="role"
                                   value="<?= e((string) $roleKey) ?>"
                                <?= checked(old('role', (string) $values['role']), (string) $roleKey) ?>>
                            <span class="form-check-label">
                                <strong><?= e((string) $roleLabel) ?></strong>
                                <small><?= e((string) ($roleDescriptions[$roleKey] ?? '')) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>

                    <?php if (can('users.manage')): ?>
                        <p class="form-hint" style="margin-top:var(--space-3)">
                            <?php if (\App\Acl::isCustomised()): ?>
                                Some roles have been changed from the defaults, so treat the descriptions as a guide.
                            <?php endif; ?>
                            Want a role to do more or less?
                            <a href="<?= e(url('roles.php')) ?>">Change what each role can do</a>.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php // ==================== Where they work ==================== ?>
            <div class="card" id="where-they-work">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= icon('map-pin', '', 18) ?> Where they work</h2>
                        <p class="card-subtitle">
                            Tick an area and they only see the checks for it: on their home page,
                            on the inspections list, when starting a check. Nothing ticked means
                            they see every check. A Staff account needs at least one area or
                            checklist; administrators always see everything, whatever is ticked.
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (has_error('areas')): ?>
                        <p class="form-error"><?= e(error_for('areas')) ?></p>
                    <?php endif; ?>
                    <?php if ($areaOptions === []): ?>
                        <p class="text-muted" style="margin:0">
                            No locations yet. Add some under
                            <a href="<?= e(url('categories.php', ['tab' => 'locations'])) ?>">Categories &amp; Locations</a>
                            and they appear here.
                        </p>
                    <?php else: ?>
                        <div class="form-label">Areas</div>
                        <div class="tick-grid">
                            <?php foreach ($areaOptions as $areaId => $areaName): ?>
                                <label class="form-check">
                                    <input type="checkbox" name="areas[]" value="<?= (int) $areaId ?>"
                                        <?= in_array((int) $areaId, $areas, true) ? 'checked' : '' ?>>
                                    <span class="form-check-label"><?= e((string) $areaName) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($checklistOptions !== []): ?>
                        <details class="mt-3" <?= $checklists !== [] ? 'open' : '' ?>>
                            <summary class="form-label" style="cursor:pointer">
                                Particular checklists, wherever they are
                                <span class="text-muted text-sm">(optional)</span>
                            </summary>
                            <div class="tick-grid mt-2">
                                <?php foreach ($checklistOptions as $checklistId => $checklistName): ?>
                                    <label class="form-check">
                                        <input type="checkbox" name="checklists[]" value="<?= (int) $checklistId ?>"
                                            <?= in_array((int) $checklistId, $checklists, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label"><?= e((string) $checklistName) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            </div>

            <?php // ==================== Password ==================== ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('key', '', 18) ?> Password</h2>
                </div>
                <div class="card-body">
                    <?php View::partial('form-field', [
                        'name'  => 'password',
                        'label' => $editing ? 'Set a new password' : 'Password',
                        'type'  => 'password',
                        'value' => '',
                        'noOld' => true,
                        'hint'  => $editing
                            ? 'Leave blank to keep the one they have.'
                            : 'Leave blank and one will be made up for you and shown once.',
                        'attrs' => ['autocomplete' => 'new-password', 'maxlength' => 200],
                    ]); ?>

                    <label class="form-check" for="f_must_change_password">
                        <input type="checkbox" id="f_must_change_password"
                               name="must_change_password" value="1"
                            <?= checked($wasTicked('must_change_password', (int) $values['must_change_password']), 1) ?>>
                        <span class="form-check-label">
                            Make them pick their own next time they sign in
                            <small>Recommended whenever you set a password for somebody else.</small>
                        </span>
                    </label>

                    <?php if ($editing): ?>
                        <hr>
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <p class="text-sm text-muted" style="margin:0">
                                Forgotten their password? Make a new one and hand it over.
                                It is shown on screen once.
                            </p>
                            <button type="submit" name="action" value="reset_password"
                                    class="btn btn-secondary" data-no-guard
                                    data-confirm="Make a new password for this person? The old one stops working straight away."
                                    data-confirm-title="Reset password">
                                <?= icon('refresh', '', 16) ?> Reset password
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <details class="card">
                <summary class="card-header">
                    <h2 class="card-title"><?= icon('file-text', '', 18) ?> Notes</h2>
                    <span class="text-sm text-muted">Optional <?= icon('chevron-down', '', 15) ?></span>
                </summary>
                <div class="card-body">
                    <?php View::partial('form-field', [
                        'name'        => 'notes',
                        'label'       => '',
                        'type'        => 'textarea',
                        'value'       => $values['notes'],
                        'rows'        => 3,
                        'placeholder' => 'Certifications, shift pattern, anything worth knowing.',
                        'attrs'       => ['maxlength' => 2000, 'data-autogrow' => true],
                    ]); ?>
                </div>
            </details>
        </div>

        <div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Account</h3></div>
                <div class="card-body">
                    <label class="form-check" for="f_is_active">
                        <input type="checkbox" id="f_is_active" name="is_active" value="1"
                            <?= checked($wasTicked('is_active', (int) $values['is_active']), 1) ?>>
                        <span class="form-check-label">
                            Can sign in
                            <small>Untick when somebody leaves. Their work history stays.</small>
                        </span>
                    </label>

                    <label class="form-check" for="f_notify_email">
                        <input type="checkbox" id="f_notify_email" name="notify_email" value="1"
                            <?= checked($wasTicked('notify_email', (int) $values['notify_email']), 1) ?>>
                        <span class="form-check-label">
                            Send them emails
                            <small>Work orders assigned to them, failed inspections, low stock.</small>
                        </span>
                    </label>
                </div>
            </div>

            <?php if ($editing): ?>
                <div class="card">
                    <div class="card-header"><h3 class="card-title">History</h3></div>
                    <div class="card-body">
                        <dl class="detail-list">
                            <dt>Added</dt>
                            <dd><?= e(Dates::date((string) $target['created_at'])) ?></dd>

                            <dt>Last signed in</dt>
                            <dd>
                                <?php if (!empty($target['last_login_at'])): ?>
                                    <?= e(Dates::datetime((string) $target['last_login_at'])) ?>
                                    <?php if (!empty($target['last_login_ip'])): ?>
                                        <div class="text-sm text-subtle">
                                            from <?= e((string) $target['last_login_ip']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-subtle">Never</span>
                                <?php endif; ?>
                            </dd>

                            <?php if (!empty($target['password_changed_at'])): ?>
                                <dt>Password last changed</dt>
                                <dd><?= e(Dates::date((string) $target['password_changed_at'])) ?></dd>
                            <?php endif; ?>

                            <?php if (!empty($target['locked_until']) && !Dates::isPast((string) $target['locked_until'])): ?>
                                <dt>Locked out until</dt>
                                <dd class="text-warn"><?= e(Dates::datetime((string) $target['locked_until'])) ?></dd>
                            <?php endif; ?>
                        </dl>

                        <?php if (can('audit.view')): ?>
                            <a class="btn btn-ghost btn-sm btn-block mt-2"
                               href="<?= e(url('audit.php', ['user_id' => $targetId])) ?>">
                                <?= icon('history', '', 15) ?> What they have been doing
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">
                    <?= icon('save', '', 17) ?>
                    <?= $editing ? 'Save changes' : 'Add them' ?>
                </button>
                <a class="btn btn-ghost btn-block" href="<?= e(url('users.php')) ?>" data-no-guard>Cancel</a>
            </div>

            <?php if ($isSelf): ?>
                <div class="alert alert-info">
                    <?= icon('info', '', 18) ?>
                    <div class="alert-body">
                        This is your own account. You cannot switch yourself off or
                        remove yourself — ask another administrator if you need that.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>
