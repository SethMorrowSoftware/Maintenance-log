<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Dates;
use App\Models\Asset;
use App\Models\WorkOrder;
use App\Request;
use App\Scheduler;
use App\Status;
use App\Validator;
use App\View;

Auth::requireLogin();
Acl::requirePermission('schedules.manage');

$id       = Request::int('id');
$editing  = $id > 0;
$schedule = null;

if ($editing) {
    $schedule = db()->one(
        'SELECT s.*, a.name AS asset_name, a.meter_type FROM {maintenance_schedules} s
         INNER JOIN {assets} a ON a.id = s.asset_id WHERE s.id = ? LIMIT 1',
        [$id]
    );

    if ($schedule === null) {
        abort(404, 'That schedule does not exist.');
    }
}

if (is_post()) {
    Csrf::verify();

    $validator = Validator::make($_POST, [
        'asset_id'        => 'required|int|exists:assets,id',
        'name'            => 'required|string|max:191',
        'description'     => 'nullable|text|max:2000',
        'log_type'        => 'required|' . Status::rule('log_type'),
        'frequency_type'  => 'required|in:daily,weekly,monthly,quarterly,semiannual,annual,days,weeks,months,meter',
        'frequency_value' => 'nullable|int|min:1|max:999',
        'meter_interval'  => 'nullable|decimal|min:0',
        'lead_time_days'  => 'nullable|int|min:0|max:365',
        'estimated_hours' => 'nullable|decimal|min:0|max:999',
        'assigned_to'     => 'nullable|int|exists:users,id',
        'checklist_id'    => 'nullable|int|exists:checklists,id',
        'priority'        => 'required|' . Status::rule('priority'),
        'instructions'    => 'nullable|text|max:5000',
    ], [
        'name.required' => 'Give the job a name, such as "50 hour service".',
    ], [
        'asset_id'        => asset_word(false, true),
        'frequency_type'  => 'How often',
        'frequency_value' => 'Interval',
        'meter_interval'  => 'Meter interval',
        'lead_time_days'  => 'Warning period',
    ]);

    if ($validator->fails()) {
        flash_errors($validator->errors(), $_POST);
        redirect(url('schedule-edit.php', $editing ? ['id' => $id] : []));
    }

    $data = $validator->validated();

    // A meter interval only means something on a machine with a meter, and
    // only while the site tracks meters at all.
    $meterAsset = db()->one('SELECT meter_type FROM {assets} WHERE id = ? LIMIT 1', [(int) $data['asset_id']]);
    $meterless  = $meterAsset === null || (string) $meterAsset['meter_type'] === 'none' || !feature_on('meters');

    if ($meterless && ($data['frequency_type'] === 'meter' || !empty($data['meter_interval']))) {
        flash_errors([
            'meter_interval' => feature_on('meters')
                ? 'That ' . asset_word() . ' has no meter, so it cannot be serviced by the meter. Give it a calendar interval, or set its meter type on the ' . asset_word() . ' first.'
                : 'Meters are switched off on this site. Use a calendar interval.',
        ], $_POST);
        redirect(url('schedule-edit.php', $editing ? ['id' => $id] : []));
    }

    // A meter schedule needs an interval; a calendar one does not.
    if ($data['frequency_type'] === 'meter') {
        if (empty($data['meter_interval']) || (float) $data['meter_interval'] <= 0) {
            flash_errors(['meter_interval' => 'Say how many hours or laps between services.'], $_POST);
            redirect(url('schedule-edit.php', $editing ? ['id' => $id] : []));
        }
    } else {
        // Keep any meter interval: a schedule can legitimately be "every three
        // months or every 50 hours, whichever comes first".
        $data['meter_interval'] = empty($data['meter_interval']) ? null : (float) $data['meter_interval'];
    }

    $data['frequency_value'] = max(1, (int) ($data['frequency_value'] ?: 1));
    $data['lead_time_days']  = (int) ($data['lead_time_days'] ?? 7);
    $data['is_active']       = Request::bool('is_active') ? 1 : 0;
    $data['updated_by']      = Auth::id();

    foreach (['assigned_to', 'checklist_id'] as $key) {
        if (empty($data[$key])) {
            $data[$key] = null;
        }
    }

    try {
        if ($editing) {
            $before = $schedule;

            // A never-done schedule keeps its first due date through edits,
            // unless the interval itself changed — then it starts afresh.
            if (empty($before['last_performed_at'])) {
                // Numbers are compared as numbers: the database hands back
                // "50.00" for a meter interval typed as 50.
                $changed = (string) $data['frequency_type'] !== (string) ($before['frequency_type'] ?? '')
                    || (int) $data['frequency_value'] !== (int) ($before['frequency_value'] ?? 0)
                    || abs((float) ($data['meter_interval'] ?? 0) - (float) ($before['meter_interval'] ?? 0)) > 0.004;

                if ($changed) {
                    $data['next_due_date']  = null;
                    $data['next_due_meter'] = null;
                }
            }

            db()->update('maintenance_schedules', $data, ['id' => $id]);
            audit('update', 'schedule', $id, 'Updated schedule "' . (string) $data['name'] . '"', $before, $data);
            $savedId = $id;
            flash('success', 'Saved.');
        } else {
            $data['created_by'] = Auth::id();
            $data['created_at'] = Dates::nowUtc();
            $savedId = db()->insert('maintenance_schedules', $data);
            audit('create', 'schedule', $savedId, 'Added schedule "' . (string) $data['name'] . '"');
            flash('success', 'Schedule added.');
        }

        // Work out when it is next due.
        Scheduler::recompute($savedId);

        redirect(url('asset-view.php', ['id' => (int) $data['asset_id'], 'tab' => 'schedules']));
    } catch (Throwable $e) {
        log_error('Schedule save failed: ' . $e->getMessage());
        flash('error', 'The schedule could not be saved.');
        redirect(url('schedule-edit.php', $editing ? ['id' => $id] : []));
    }
}

$assetId = $editing ? (int) $schedule['asset_id'] : Request::int('asset_id');
$asset   = $assetId > 0 ? Asset::find($assetId) : null;

$defaults = [
    'asset_id'        => $assetId,
    'name'            => '',
    'description'     => '',
    'log_type'        => 'preventive',
    'frequency_type'  => 'monthly',
    'frequency_value' => 1,
    'meter_interval'  => '',
    'lead_time_days'  => 7,
    'estimated_hours' => '',
    'assigned_to'     => 0,
    'checklist_id'    => 0,
    'priority'        => 'normal',
    'instructions'    => '',
    'is_active'       => 1,
];

$values = $editing ? array_merge($defaults, $schedule) : $defaults;

View::render('schedules/edit', [
    'title'    => $editing ? 'Edit schedule' : 'Add a schedule',
    'subtitle' => $asset === null ? 'Planned servicing' : (string) $asset['name'],
    'activeNav' => 'schedules.php',
    'breadcrumbs' => [
        ['label' => 'Scheduled Service', 'url' => url('schedules.php')],
        ['label' => $editing ? 'Edit' : 'New'],
    ],
    'editing'    => $editing,
    'schedule'   => $schedule,
    'values'     => $values,
    'asset'      => $asset,
    'assets'     => Asset::options(),
    'assignees'  => WorkOrder::assigneeOptions(),
    'checklists' => db()->pairs('SELECT id, name FROM {checklists} WHERE is_active = 1 ORDER BY name'),
]);
