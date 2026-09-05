<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Dates;
use App\Models\Asset;
use App\Request;
use App\Scheduler;
use App\Status;
use App\View;

Auth::requireLogin();
Acl::requirePermission('schedules.view');

if (is_post()) {
    Csrf::verify();
    Acl::requirePermission('schedules.manage');

    $id     = Request::int('id');
    $action = Request::string('action');

    if ($action === 'delete') {
        $schedule = db()->find('maintenance_schedules', $id);

        if ($schedule !== null) {
            db()->delete('maintenance_schedules', ['id' => $id]);
            audit('delete', 'schedule', $id, 'Deleted schedule "' . (string) $schedule['name'] . '"');
            flash('success', 'Schedule deleted.');
        }
    }

    if ($action === 'toggle') {
        $schedule = db()->find('maintenance_schedules', $id);

        if ($schedule !== null) {
            $active = (int) $schedule['is_active'] === 1 ? 0 : 1;
            db()->update('maintenance_schedules', ['is_active' => $active], ['id' => $id]);
            audit('update', 'schedule', $id, ($active ? 'Resumed' : 'Paused') . ' schedule "' . (string) $schedule['name'] . '"');
            flash('success', $active ? 'Schedule resumed.' : 'Schedule paused.');
        }
    }

    redirect(url('schedules.php', $_GET));
}

$show     = Request::enum('due', ['all', 'soon', 'overdue'], 'all');
$assetId  = Request::int('asset_id');
$window   = $show === 'all' ? 3650 : ($show === 'overdue' ? 0 : 30);
$schedules = Scheduler::due($window, $assetId > 0 ? $assetId : null);

if ($show === 'overdue') {
    $schedules = array_values(array_filter($schedules, static function (array $s): bool {
        return $s['due_state'] === 'overdue';
    }));
}

// Everything, including things not yet due, when "all" is asked for.
if ($show === 'all') {
    $params = [];
    $sql = "SELECT s.*, a.name AS asset_name, a.asset_tag, a.meter_type, a.meter_reading,
                   c.name AS checklist_name,
                   u.first_name AS assignee_first, u.last_name AS assignee_last
            FROM {maintenance_schedules} s
            INNER JOIN {assets} a ON a.id = s.asset_id AND a.deleted_at IS NULL
            LEFT JOIN {checklists} c ON c.id = s.checklist_id
            LEFT JOIN {users} u ON u.id = s.assigned_to";

    if ($assetId > 0) {
        $sql .= ' WHERE s.asset_id = ?';
        $params[] = $assetId;
    }

    $sql .= ' ORDER BY s.is_active DESC, s.next_due_date IS NULL, s.next_due_date ASC, a.name ASC';

    $rows = db()->all($sql, $params);
    $schedules = [];

    foreach ($rows as $row) {
        $meter = $row['meter_reading'] === null ? null : (float) $row['meter_reading'];
        $row['due_state']  = Status::dueState($row, $meter);
        $row['days_until'] = Dates::daysUntil($row['next_due_date'] ?? null);
        $schedules[] = $row;
    }
}

$counts = ['overdue' => 0, 'due_soon' => 0, 'ok' => 0];

foreach (Scheduler::due(30) as $item) {
    if ($item['due_state'] === 'overdue') {
        $counts['overdue']++;
    } elseif (in_array($item['due_state'], ['due', 'due_soon'], true)) {
        $counts['due_soon']++;
    }
}

$actions = '';

if (can('schedules.manage')) {
    $actions = '<a class="btn btn-primary" href="' . e(url('schedule-edit.php')) . '">'
        . icon('plus', '', 17) . ' Add a schedule</a>';
}

View::render('schedules/index', [
    'title'       => 'Scheduled Service',
    'subtitle'    => 'Planned servicing, by date or by meter',
    'activeNav'   => 'schedules.php',
    'pageActions' => $actions,
    'schedules'   => $schedules,
    'show'        => $show,
    'assetId'     => $assetId,
    'counts'      => $counts,
    'assets'      => Asset::options(),
]);
