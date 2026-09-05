<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Dates;
use App\Mailer;
use App\Request;
use App\Scope;
use App\Settings;
use App\Str;
use App\Validator;
use App\View;

Auth::requireLogin();
Acl::requirePermission('users.manage');

$id      = Request::int('id');
$editing = $id > 0;
$target  = null;

if ($editing) {
    $target = db()->one('SELECT * FROM {users} WHERE id = ? AND deleted_at IS NULL', [$id]);

    if ($target === null) {
        abort(404, 'That person is not on the list.');
    }
}

// -----------------------------------------------------------------------------
// Save
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');

    // ------------------------------------------------------- reset a password
    if ($action === 'reset_password' && $editing) {
        $newPassword = Str::password(12);

        Auth::changePassword($id, $newPassword);
        db()->update('users', ['must_change_password' => 1], ['id' => $id]);
        Auth::revokeAllTokens($id);

        audit('password.reset', 'user', $id,
            'Reset the password for ' . (string) $target['username']);

        // The password is shown once, on screen. Emailing it would put it in a
        // mailbox for good; this way it is handed over and then gone.
        flash('warning', 'New password for ' . (string) $target['username'] . ': '
            . $newPassword . ' — write it down now, it will not be shown again. '
            . 'They will be asked to change it when they sign in.');

        redirect(url('user-edit.php', ['id' => $id]));
    }

    $rules = [
        'username'        => 'required|string|max:64|alpha_dash|unique:users,username' . ($editing ? ',' . $id : ''),
        'email'           => 'required|email|max:190|unique:users,email' . ($editing ? ',' . $id : ''),
        'first_name'      => 'required|string|max:80',
        'last_name'       => 'required|string|max:80',
        'role'            => 'required|in:' . implode(',', array_keys(Acl::roles())),
        'phone'           => 'nullable|string|max:40',
        'job_title'       => 'nullable|string|max:100',
        'employee_number' => 'nullable|string|max:50',
        'notes'           => 'nullable|text|max:2000',
    ];

    $validator = Validator::make($_POST, $rules, [
        'username.alpha_dash' => 'A username can only use letters, numbers, dots, dashes and underscores.',
        'username.unique'     => 'Somebody already signs in with that username.',
        'email.unique'        => 'Somebody is already using that email address.',
    ], [
        'first_name'      => 'First name',
        'last_name'       => 'Last name',
        'employee_number' => 'Employee number',
    ]);

    $password = Request::string('password');

    if (!$editing && $password === '') {
        $password = Str::password(12);
        $generated = true;
    } else {
        $generated = false;
    }

    if ($password !== '') {
        $problem = Auth::validatePassword($password);

        if ($problem !== '') {
            $validator->addError('password', $problem);
        }
    }

    if ($validator->fails()) {
        flash_errors($validator->errors(), $_POST);
        redirect(url('user-edit.php', $editing ? ['id' => $id] : []));
    }

    $data = $validator->validated();

    // Demoting or switching off the last administrator locks everybody out.
    if ($editing
        && Acl::isLastActiveAdmin($id)
        && ((string) $data['role'] !== 'admin' || !Request::bool('is_active'))) {
        flash('error', trim((string) $target['first_name'] . ' ' . (string) $target['last_name'])
            . ' is the only administrator left. Make somebody else an administrator first.');
        redirect(url('user-edit.php', ['id' => $id]));
    }

    $data['is_active']            = Request::bool('is_active') ? 1 : 0;
    $data['must_change_password'] = Request::bool('must_change_password') ? 1 : 0;
    $data['notify_email']         = Request::bool('notify_email') ? 1 : 0;
    $data['updated_by']           = Auth::id();

    foreach (['phone', 'job_title', 'employee_number'] as $field) {
        $data[$field] = (string) ($data[$field] ?? '');
    }

    // Where they work: the areas and checklists ticked, if any. An empty set
    // means "everything", which is what most accounts want.
    $areaIds      = is_array($_POST['areas'] ?? null) ? $_POST['areas'] : [];
    $checklistIds = is_array($_POST['checklists'] ?? null) ? $_POST['checklists'] : [];

    try {
        if ($editing) {
            db()->update('users', $data, ['id' => $id]);
            Scope::save($id, $areaIds, $checklistIds);

            if ($password !== '') {
                Auth::changePassword($id, $password);
                Auth::revokeAllTokens($id);
            }

            if ($data['is_active'] === 0) {
                Auth::revokeAllTokens($id);
            }

            $savedId = $id;
            audit('update', 'user', $id, 'Updated ' . (string) $data['username']);
            flash('success', 'Saved.');
        } else {
            $data['password_hash']       = Auth::hash($password);
            $data['password_changed_at'] = Dates::nowUtc();
            $data['created_by']          = Auth::id();
            $data['created_at']          = Dates::nowUtc();

            $savedId = db()->insert('users', $data);
            Scope::save($savedId, $areaIds, $checklistIds);

            audit('create', 'user', $savedId, 'Added ' . (string) $data['username']
                . ' as ' . Acl::roleLabel((string) $data['role']));

            if ($generated) {
                flash('warning', 'Password for ' . (string) $data['username'] . ': ' . $password
                    . ' — write it down now, it will not be shown again.');
            } else {
                flash('success', (string) $data['first_name'] . ' can now sign in.');
            }

            // A welcome email is a nicety, not a requirement: if the mail
            // server is not set up, the account still works. The password is
            // never in it — that is handed over in person.
            if (Settings::mailEnabled()) {
                try {
                    Mailer::send(
                        (string) $data['email'],
                        'Your ' . Settings::siteName() . ' account',
                        '<p>Hello ' . e((string) $data['first_name']) . ',</p>'
                        . '<p>An account has been set up for you on '
                        . e(Settings::siteName()) . ', where we keep the maintenance records for '
                        . e(Settings::organizationName()) . '.</p>'
                        . '<p>Your username is <strong>' . e((string) $data['username'])
                        . '</strong>. Whoever set the account up will give you the password.</p>'
                        . '<p><a href="' . e(absolute_url('login.php')) . '">Sign in</a></p>'
                    );
                } catch (Throwable $e) {
                    log_error('Welcome email failed: ' . $e->getMessage());
                }
            }
        }

        redirect(url('users.php'));
    } catch (Throwable $e) {
        log_error('User save failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        flash('error', 'That could not be saved. The error has been recorded.');
        redirect(url('user-edit.php', $editing ? ['id' => $id] : []));
    }
}

// -----------------------------------------------------------------------------
// Form
// -----------------------------------------------------------------------------

$defaults = [
    'username'             => '',
    'email'                => '',
    'first_name'           => '',
    'last_name'            => '',
    'role'                 => 'technician',
    'phone'                => '',
    'job_title'            => '',
    'employee_number'      => '',
    'notes'                => '',
    'is_active'            => 1,
    'must_change_password' => 1,
    'notify_email'         => 1,
];

View::render('users/edit', [
    'title'       => $editing ? 'Edit person' : 'Add someone',
    'subtitle'    => $editing
        ? trim((string) $target['first_name'] . ' ' . (string) $target['last_name'])
        : 'Somebody new who needs to sign in',
    'activeNav'   => 'users.php',
    'breadcrumbs' => [
        ['label' => 'People', 'url' => url('users.php')],
        ['label' => $editing ? 'Edit' : 'New'],
    ],
    'editing'          => $editing,
    'target'           => $target,
    'values'           => $editing ? array_merge($defaults, $target) : $defaults,
    'roles'            => Acl::roles(),
    'roleDescriptions' => Acl::roleDescriptions(),
    'isSelf'           => $editing && $id === Auth::id(),
    // Where they work. After a rejected save the ticks come back from the form.
    'areaOptions'      => \App\Models\Asset::locationOptions(),
    'checklistOptions' => db()->pairs('SELECT id, name FROM {checklists} WHERE is_active = 1 ORDER BY name'),
    'areas'            => array_map('intval', (array) old('areas', $editing ? Scope::forUser($id)['areas'] : [])),
    'checklists'       => array_map('intval', (array) old('checklists', $editing ? Scope::forUser($id)['checklists'] : [])),
]);
