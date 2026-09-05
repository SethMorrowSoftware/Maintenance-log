<?php

declare(strict_types=1);

/**
 * Who can do what.
 *
 * One grid: a row per permission, a column per role. Administrators can
 * always do everything; the other three columns are the site's to change.
 * Saved as one setting, so "Reset to defaults" is a single delete.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Features;
use App\Request;
use App\View;

Auth::requireLogin();
Acl::requirePermission('users.manage');

if (is_post()) {
    Csrf::verify();

    if (Request::string('action') === 'reset') {
        Acl::resetMatrix();
        audit('update', 'settings', null, 'Put every role back to its default permissions');
        flash('success', 'Every role is back to its default permissions.');
        redirect(url('roles.php'));
    }

    $submitted = $_POST['perm'] ?? [];
    $matrix    = [];

    foreach ([Acl::ROLE_VIEWER, Acl::ROLE_TECHNICIAN, Acl::ROLE_MANAGER] as $role) {
        $list = is_array($submitted) ? ($submitted[$role] ?? []) : [];

        $matrix[$role] = is_array($list) ? array_map('strval', $list) : [];
    }

    Acl::saveMatrix($matrix);

    audit('update', 'settings', null, 'Changed what each role can do');
    flash('success', 'Saved. Everybody picks up their new permissions on their next page.');
    redirect(url('roles.php'));
}

// Columns least to most privileged, the way people think about them.
$roles = [
    Acl::ROLE_VIEWER     => Acl::roleLabel(Acl::ROLE_VIEWER),
    Acl::ROLE_TECHNICIAN => Acl::roleLabel(Acl::ROLE_TECHNICIAN),
    Acl::ROLE_MANAGER    => Acl::roleLabel(Acl::ROLE_MANAGER),
    Acl::ROLE_ADMIN      => Acl::roleLabel(Acl::ROLE_ADMIN),
];

$matrix   = [];
$defaults = Acl::defaults();

foreach (array_keys($roles) as $role) {
    $matrix[$role] = Acl::permissionsFor($role);
}

// Which permissions belong to a module that is switched off. They stay in the
// grid, dimmed, so nothing is lost when the module comes back.
$offModules = [];

foreach (Acl::allPermissions() as $permission) {
    $module = Features::forPermission($permission);

    if ($module !== null && !Features::on($module)) {
        $offModules[$permission] = Features::label($module);
    }
}

// How many people are in each role, so the columns feel real.
$headcount = [];

foreach (db()->all('SELECT role, COUNT(*) AS n FROM {users} WHERE deleted_at IS NULL AND is_active = 1 GROUP BY role') as $row) {
    $headcount[(string) $row['role']] = (int) $row['n'];
}

View::render('users/roles', [
    'title'        => 'Roles',
    'subtitle'     => 'What each role is allowed to do',
    'activeNav'    => 'roles.php',
    'roles'        => $roles,
    'descriptions' => Acl::roleDescriptions(),
    'catalogue'    => Acl::catalogue(),
    'matrix'       => $matrix,
    'defaults'     => $defaults,
    'offModules'   => $offModules,
    'headcount'    => $headcount,
    'customised'   => Acl::isCustomised(),
]);
