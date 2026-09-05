<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Csv;
use App\Dates;
use App\Paginator;
use App\Request;
use App\View;

Auth::requireLogin();
Acl::requirePermission('users.view');

// -----------------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();
    Acl::requirePermission('users.manage');

    $action = Request::string('action');
    $id     = Request::int('id');
    $target = $id > 0 ? db()->one('SELECT * FROM {users} WHERE id = ? AND deleted_at IS NULL', [$id]) : null;

    if ($target === null) {
        flash('error', 'That person is not on the list.');
        redirect(url('users.php', $_GET));
    }

    $name = trim((string) $target['first_name'] . ' ' . (string) $target['last_name'])
        ?: (string) $target['username'];

    // Somebody who manages people without being an administrator cannot
    // switch off or remove an administrator.
    if (!Acl::isAdmin() && (string) $target['role'] === Acl::ROLE_ADMIN) {
        flash('error', 'Only an administrator can change another administrator\'s account.');
        redirect(url('users.php', $_GET));
    }

    if ($action === 'toggle') {
        $active = (int) $target['is_active'] === 1 ? 0 : 1;

        // Locking out the last administrator would leave nobody able to fix it.
        if ($active === 0 && Acl::isLastActiveAdmin($id)) {
            flash('error', $name . ' is the only administrator left. '
                . 'Make somebody else an administrator first.');
            redirect(url('users.php', $_GET));
        }

        db()->update('users', ['is_active' => $active, 'updated_by' => Auth::id()], ['id' => $id]);

        if ($active === 0) {
            Auth::revokeAllTokens($id);
        }

        audit('update', 'user', $id, ($active === 1 ? 'Switched on ' : 'Switched off ') . $name);
        flash('success', $active === 1
            ? $name . ' can sign in again.'
            : $name . ' can no longer sign in. Their maintenance history stays put.');
    }

    if ($action === 'delete') {
        if ($id === Auth::id()) {
            flash('error', 'You cannot remove your own account.');
            redirect(url('users.php', $_GET));
        }

        if (Acl::isLastActiveAdmin($id)) {
            flash('error', $name . ' is the only administrator left. '
                . 'Make somebody else an administrator first.');
            redirect(url('users.php', $_GET));
        }

        // Soft delete: every log, inspection and work order they touched still
        // has to say who did it.
        db()->update('users', [
            'deleted_at' => Dates::nowUtc(),
            'is_active'  => 0,
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        Auth::revokeAllTokens($id);

        audit('delete', 'user', $id, 'Removed ' . $name);
        flash('success', $name . ' has been removed. Their name stays on the work they did.');
    }

    redirect(url('users.php', $_GET));
}

// -----------------------------------------------------------------------------
// List
// -----------------------------------------------------------------------------

$filters = [
    'q'      => Request::string('q'),
    'role'   => Request::string('role'),
    'active' => Request::string('active'),
];

$where  = ['u.deleted_at IS NULL'];
$params = [];

if ($filters['q'] !== '') {
    $like     = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['q']) . '%';
    $where[]  = '(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    array_push($params, $like, $like, $like, $like);
}

if (Acl::isValidRole($filters['role'])) {
    $where[]  = 'u.role = ?';
    $params[] = $filters['role'];
}

if ($filters['active'] === '1' || $filters['active'] === '0') {
    $where[]  = 'u.is_active = ?';
    $params[] = (int) $filters['active'];
}

$whereSql = implode(' AND ', $where);

if (Request::string('export') === 'csv') {
    Acl::requirePermission('reports.export');

    $rows = db()->all(
        "SELECT u.username, u.first_name, u.last_name, u.email, u.role, u.job_title,
                u.employee_number, u.phone, u.is_active, u.last_login_at, u.created_at
         FROM {users} u WHERE {$whereSql} ORDER BY u.last_name, u.first_name",
        $params
    );

    audit('export', 'user', null, 'Exported ' . count($rows) . ' people to CSV');

    Csv::stream(
        Csv::filename('people'),
        ['Username', 'First name', 'Last name', 'Email', 'Role', 'Job title',
         'Employee number', 'Phone', 'Can sign in', 'Last signed in', 'Added'],
        $rows,
        static function (array $row): array {
            return [
                $row['username'], $row['first_name'], $row['last_name'], $row['email'],
                Acl::roleLabel((string) $row['role']), $row['job_title'],
                $row['employee_number'], $row['phone'],
                (int) $row['is_active'] === 1 ? 'Yes' : 'No',
                Dates::datetime((string) ($row['last_login_at'] ?? ''), ''),
                Dates::date((string) $row['created_at'], ''),
            ];
        }
    );
}

$total     = db()->count("SELECT COUNT(*) FROM {users} u WHERE {$whereSql}", $params);
$paginator = Paginator::fromRequest($total, null, 'users.php');

$users = db()->all(
    "SELECT u.*,
            (SELECT COUNT(*) FROM {maintenance_logs} l
              WHERE l.user_id = u.id AND l.deleted_at IS NULL) AS log_count,
            (SELECT COUNT(*) FROM {work_orders} w
              WHERE w.assigned_to = u.id AND w.deleted_at IS NULL
                AND w.status NOT IN ('completed','cancelled')) AS open_work,
            (SELECT GROUP_CONCAT(l.name ORDER BY l.sort_order, l.name SEPARATOR ', ')
               FROM {user_areas} ua INNER JOIN {locations} l ON l.id = ua.location_id
              WHERE ua.user_id = u.id) AS areas,
            (SELECT COUNT(*) FROM {user_checklists} uc WHERE uc.user_id = u.id) AS checklist_count
     FROM {users} u
     WHERE {$whereSql}
     ORDER BY u.is_active DESC, u.last_name ASC, u.first_name ASC
     LIMIT " . $paginator->limit() . ' OFFSET ' . $paginator->offset(),
    $params
);

$actions = '';

if (can('users.manage')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('user-edit.php')) . '">'
        . icon('plus', '', 17) . ' Add someone</a>';
}

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('users.php', array_merge($_GET, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

View::render('users/index', [
    'title'       => 'People',
    'subtitle'    => 'Everyone who can sign in, and what they are allowed to do',
    'activeNav'   => 'users.php',
    'pageActions' => $actions,
    'users'       => $users,
    'paginator'   => $paginator,
    'filters'     => $filters,
    'roles'       => Acl::roles(),
]);
