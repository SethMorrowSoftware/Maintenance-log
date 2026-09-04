<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Audit;
use App\Auth;
use App\Csrf;
use App\Dates;
use App\Request;
use App\Settings;
use App\Uploader;
use App\Validator;
use App\View;

Auth::requireLogin();

$me     = user();
$userId = (int) $me['id'];
$forced = (int) ($me['must_change_password'] ?? 0) === 1;
$tab    = Request::enum('tab', ['details', 'password', 'preferences'], $forced ? 'password' : 'details');

// -----------------------------------------------------------------------------
// POST
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');

    // ---------------------------------------------------------------- details
    if ($action === 'details') {
        $validator = Validator::make($_POST, [
            'first_name' => 'required|string|max:80',
            'last_name'  => 'required|string|max:80',
            'email'      => 'required|email|max:190|unique:users,email,' . $userId,
            'phone'      => 'nullable|string|max:40',
            'job_title'  => 'nullable|string|max:100',
        ], [], [
            'first_name' => 'First name',
            'last_name'  => 'Last name',
            'email'      => 'Email address',
            'phone'      => 'Phone',
            'job_title'  => 'Job title',
        ]);

        if ($validator->fails()) {
            flash_errors($validator->errors(), $_POST);
            redirect(url('profile.php', ['tab' => 'details']));
        }

        $data   = $validator->validated();
        $before = $me;

        db()->update('users', [
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'],
            'job_title'  => $data['job_title'],
            'updated_by' => $userId,
        ], ['id' => $userId]);

        Audit::updated('user', $userId, 'Updated their own profile', $before, $data);

        flash('success', 'Your details have been saved.');
        redirect(url('profile.php', ['tab' => 'details']));
    }

    // --------------------------------------------------------------- password
    if ($action === 'password') {
        $current = (string) Request::input('current_password', '');
        $new     = (string) Request::input('password', '');
        $confirm = (string) Request::input('password_confirmation', '');
        $errors  = [];

        // A forced change happens because an administrator set a temporary
        // password, so the person may not know a "current" one to type.
        if (!$forced) {
            if ($current === '') {
                $errors['current_password'] = 'Enter your current password.';
            } elseif (!password_verify($current, (string) $me['password_hash'])) {
                $errors['current_password'] = 'That is not your current password.';
            }
        }

        $problem = Auth::validatePassword($new, $me);

        if ($new === '') {
            $errors['password'] = 'Enter a new password.';
        } elseif ($problem !== '') {
            $errors['password'] = $problem;
        } elseif ($new !== $confirm) {
            $errors['password_confirmation'] = 'The two passwords do not match.';
        } elseif ($current !== '' && $new === $current) {
            $errors['password'] = 'Your new password must be different from the current one.';
        }

        if ($errors !== []) {
            flash_errors($errors, []);
            redirect(url('profile.php', ['tab' => 'password']));
        }

        Auth::changePassword($userId, $new);

        // changePassword revokes remember-me tokens, so re-establish this
        // session rather than signing the person out of the tab they are in.
        Auth::forgetCache();
        Csrf::rotate();

        flash('success', 'Your password has been changed. Any other signed-in devices have been signed out.');
        redirect(url('index.php'));
    }

    // ------------------------------------------------------------ preferences
    if ($action === 'preferences') {
        $theme    = Request::enum('theme', ['system', 'light', 'dark'], 'system');
        $timezone = Request::string('timezone');
        $notify   = Request::bool('notify_email');

        if ($timezone !== '' && !in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = '';
        }

        db()->update('users', [
            'theme'        => $theme,
            'timezone'     => $timezone === '' ? null : $timezone,
            'notify_email' => $notify ? 1 : 0,
            'updated_by'   => $userId,
        ], ['id' => $userId]);

        Dates::resetZoneCache();
        Audit::record('update', 'user', $userId, 'Updated their preferences');

        flash('success', 'Your preferences have been saved.');
        redirect(url('profile.php', ['tab' => 'preferences']));
    }

    // ----------------------------------------------------------------- avatar
    if ($action === 'avatar') {
        $file = Request::file('avatar');

        if ($file === null) {
            flash('error', 'Choose an image first.');
            redirect(url('profile.php', ['tab' => 'details']));
        }

        // Replace rather than accumulate.
        foreach (Uploader::forEntity('user', $userId) as $old) {
            Uploader::delete((int) $old['id']);
        }

        $result = Uploader::handle($file, 'user', $userId, $userId);

        if (!$result['ok']) {
            flash('error', $result['error']);
        } elseif ((int) ($result['attachment']['is_image'] ?? 0) !== 1) {
            Uploader::delete($result['id']);
            flash('error', 'That file is not an image. Use a JPG or PNG.');
        } else {
            db()->update('users', ['avatar_path' => (string) $result['attachment']['file_path']], ['id' => $userId]);
            flash('success', 'Your picture has been updated.');
        }

        redirect(url('profile.php', ['tab' => 'details']));
    }

    if ($action === 'remove_avatar') {
        foreach (Uploader::forEntity('user', $userId) as $old) {
            Uploader::delete((int) $old['id']);
        }

        db()->update('users', ['avatar_path' => null], ['id' => $userId]);
        flash('success', 'Your picture has been removed.');
        redirect(url('profile.php', ['tab' => 'details']));
    }
}

// -----------------------------------------------------------------------------
// Data for the view
// -----------------------------------------------------------------------------

$me = user();

$stats = [
    'logs' => db()->count(
        'SELECT COUNT(*) FROM {maintenance_logs} WHERE user_id = ? AND deleted_at IS NULL',
        [$userId]
    ),
    'inspections' => db()->count(
        'SELECT COUNT(*) FROM {inspections} WHERE user_id = ?',
        [$userId]
    ),
    'open_work_orders' => db()->count(
        "SELECT COUNT(*) FROM {work_orders}
         WHERE assigned_to = ? AND deleted_at IS NULL AND status NOT IN ('completed','cancelled')",
        [$userId]
    ),
];

$recentLogs = db()->all(
    'SELECT l.id, l.title, l.performed_at, l.log_type, a.name AS asset_name, a.asset_tag
     FROM {maintenance_logs} l
     INNER JOIN {assets} a ON a.id = l.asset_id
     WHERE l.user_id = ? AND l.deleted_at IS NULL
     ORDER BY l.performed_at DESC
     LIMIT 8',
    [$userId]
);

$tabs = [
    'details'     => ['label' => 'Your details', 'icon' => 'user',     'url' => url('profile.php', ['tab' => 'details'])],
    'password'    => ['label' => 'Password',     'icon' => 'key',      'url' => url('profile.php', ['tab' => 'password'])],
    'preferences' => ['label' => 'Preferences',  'icon' => 'settings', 'url' => url('profile.php', ['tab' => 'preferences'])],
];

View::render('profile', [
    'title'       => 'My profile',
    'subtitle'    => Acl::roleLabel((string) $me['role']),
    'activeNav'   => 'profile.php',
    'me'          => $me,
    'tab'         => $tab,
    'tabs'        => $tabs,
    'forced'      => $forced,
    'stats'       => $stats,
    'recentLogs'  => $recentLogs,
]);
