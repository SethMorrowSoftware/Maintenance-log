<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Dates;
use App\Models\Asset;
use App\Models\MaintenanceLog;
use App\Models\Part;
use App\Request;
use App\Settings;
use App\Status;
use App\Uploader;
use App\Validator;
use App\View;

Auth::requireLogin();

$id      = Request::int('id');
$editing = $id > 0;
$log     = null;

if ($editing) {
    Acl::requirePermission('logs.view');

    $log = MaintenanceLog::find($id);

    if ($log === null) {
        abort(404, 'That maintenance log does not exist.');
    }

    if (!Acl::canEditLog($log)) {
        abort(403, 'You can only edit maintenance logs you recorded yourself. '
            . 'Ask a manager if this one needs changing.');
    }
} else {
    Acl::requirePermission('logs.create');
}

// -----------------------------------------------------------------------------
// Save
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $validator = Validator::make($_POST, [
        'asset_id'         => 'required|int|exists:assets,id',
        'log_type'         => 'required|' . Status::rule('log_type'),
        'title'            => 'required|string|max:191',
        'performed_at'     => 'required|datetime|not_future',
        'user_id'          => 'nullable|int|exists:users,id',
        'description'      => 'nullable|text|max:5000',
        'work_performed'   => 'nullable|text|max:10000',
        'labor_hours'      => 'nullable|decimal|min:0|max:9999',
        'labor_rate'       => 'nullable|decimal|min:0',
        'labor_cost'       => 'nullable|decimal|min:0',
        'parts_cost'       => 'nullable|decimal|min:0',
        'other_cost'       => 'nullable|decimal|min:0',
        'meter_reading'    => 'nullable|decimal|min:0',
        'downtime_minutes' => 'nullable|int|min:0|max:525600',
        'status_after'     => 'nullable|' . Status::rule('asset'),
        'schedule_id'      => 'nullable|int',
        'work_order_id'    => 'nullable|int',
        'followup_notes'   => 'nullable|text|max:2000',
    ], [
        'performed_at.not_future' => 'The date and time cannot be in the future. '
                                   . 'If you are logging work you are about to do, log it once it is done.',
        'title.required'          => 'Give the job a short title, so it is recognisable in a list.',
        'asset_id.required'       => 'Choose which ' . asset_word() . ' this work was done on.',
    ], [
        'asset_id'         => asset_word(false, true),
        'log_type'         => 'Type of work',
        'performed_at'     => 'Date and time',
        'labor_hours'      => 'Time taken',
        'meter_reading'    => 'Meter reading',
        'downtime_minutes' => 'Downtime',
    ]);

    // Non-managers always log against themselves, whatever the form says.
    if (!can('logs.edit_any')) {
        $_POST['user_id'] = Auth::id();
    }

    if ($validator->fails()) {
        flash_errors($validator->errors(), $_POST);
        redirect(url('log-edit.php', $editing ? ['id' => $id] : []));
    }

    $data  = $validator->validated();
    $parts = feature_on('parts')
        ? MaintenanceLog::normaliseParts($_POST['parts'] ?? [], $editing ? MaintenanceLog::parts($id) : [])
        : ($editing ? MaintenanceLog::parts($id) : []);

    // Fields that belong to a module that is switched off never reach the form.
    if (!feature_on('downtime')) {
        unset($data['downtime_minutes']);
    }

    if (!feature_on('meters')) {
        unset($data['meter_reading']);
    }

    if (!feature_on('schedules')) {
        $data['schedule_id'] = null;
    }

    if (!feature_on('work_orders')) {
        $data['work_order_id'] = null;
    }

    // Somebody who cannot see money cannot set it either: their form has no
    // cost fields, so anything arriving here was not typed on our page.
    // Editing an existing log keeps the rate and extras an administrator
    // recorded; labour cost is recomputed from the hours they just gave.
    if (!costs_visible()) {
        foreach (['labor_rate', 'labor_cost', 'parts_cost', 'other_cost'] as $moneyField) {
            $data[$moneyField] = null;
        }

        if ($editing) {
            $data['labor_rate'] = $log['labor_rate'];
            $data['other_cost'] = $log['other_cost'];
        }
    }

    $data['user_id']           = can('logs.edit_any') && !empty($data['user_id'])
        ? (int) $data['user_id']
        : Auth::id();
    $data['requires_followup'] = Request::bool('requires_followup') ? 1 : 0;
    $data['is_completed']      = Request::bool('not_finished') ? 0 : 1;
    $data['close_work_order']  = Request::bool('close_work_order');

    if (!$data['requires_followup']) {
        $data['followup_notes'] = null;
    }

    // Record what the machine's status was before, so the history reads properly.
    $asset = Asset::find((int) $data['asset_id']);
    $data['status_before'] = $asset === null ? null : (string) $asset['status'];

    if (($data['status_after'] ?? '') === '') {
        $data['status_after'] = null;
    }

    // A meter reading only makes sense on a machine that has a meter.
    if ($asset !== null && (string) $asset['meter_type'] === 'none') {
        $data['meter_reading'] = null;
    }

    // Catch a mistyped meter here, where it can still be corrected in the same
    // form, rather than saving the log and quietly dropping the reading.
    if ($asset !== null
        && ($data['meter_reading'] ?? '') !== ''
        && (float) $data['meter_reading'] < (float) $asset['meter_reading'] - 0.004) {
        flash_errors([
            'meter_reading' => 'The meter currently reads ' . decimal($asset['meter_reading']) . ' '
                . (string) $asset['meter_type'] . '. A reading cannot go backwards — check the number. '
                . 'If the meter was replaced, change it on the ' . asset_word() . ' itself.',
        ], $_POST);

        redirect(url('log-edit.php', $editing ? ['id' => $id] : []));
    }

    try {
        if ($editing) {
            MaintenanceLog::update($id, $data, $parts);
            $savedId = $id;
            flash('success', 'Saved.');
            \App\Flash::clearDraft('log-' . $id);
        } else {
            $savedId = MaintenanceLog::create($data, $parts);
            flash('success', 'Logged. Thanks for keeping the record up to date.');
            \App\Flash::clearDraft('log-new');
        }

        // Photos
        $files = Request::files('attachments');

        if ($files !== []) {
            $result = Uploader::handleMany($files, 'maintenance_log', $savedId, Auth::id());

            foreach ($result['errors'] as $error) {
                flash('warning', $error);
            }
        }

        // Raising a follow-up creates the work order, rather than leaving it as
        // a note nobody acts on.
        if ($data['requires_followup'] && !$editing && feature_on('work_orders') && can('workorders.create')) {
            try {
                \App\Models\WorkOrder::create([
                    'asset_id'    => (int) $data['asset_id'],
                    'title'       => 'Follow-up: ' . (string) $data['title'],
                    'description' => (string) ($data['followup_notes'] ?? ''),
                    'priority'    => 'normal',
                    'status'      => 'open',
                    'source'      => 'preventive',
                    'reported_by' => Auth::id(),
                ]);

                flash('info', 'A work order has been opened for the follow-up.');
            } catch (Throwable $e) {
                log_error('Follow-up work order failed: ' . $e->getMessage());
            }
        }

        if (Request::string('after') === 'new') {
            redirect(url('log-edit.php', ['asset_id' => (int) $data['asset_id']]));
        }

        redirect(url('log-view.php', ['id' => $savedId]));
    } catch (Throwable $e) {
        log_error('Maintenance log save failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        flash('error', 'The log could not be saved. The error has been recorded.');
        redirect(url('log-edit.php', $editing ? ['id' => $id] : []));
    }
}

// -----------------------------------------------------------------------------
// Prefill
// -----------------------------------------------------------------------------

$assetId     = Request::int('asset_id');
$scheduleId  = Request::int('schedule_id');
$workOrderId = Request::int('work_order_id');
$schedule    = null;
$workOrder   = null;

$defaults = [
    'asset_id'         => $assetId,
    'log_type'         => 'corrective',
    'title'            => '',
    'description'      => '',
    'work_performed'   => '',
    'performed_at'     => Dates::nowUtc(),
    'user_id'          => Auth::id(),
    'labor_hours'      => '',
    'labor_rate'       => Settings::get('default_labor_rate', '') ?: '',
    'labor_cost'       => '',
    'parts_cost'       => '',
    'other_cost'       => '',
    'meter_reading'    => '',
    'downtime_minutes' => '',
    'status_after'     => '',
    'schedule_id'      => $scheduleId,
    'work_order_id'    => $workOrderId,
    'requires_followup'=> 0,
    'followup_notes'   => '',
    'is_completed'     => 1,
];

// Coming from a due schedule: fill in what we already know.
if ($scheduleId > 0) {
    $schedule = db()->one(
        'SELECT s.*, a.name AS asset_name, a.meter_type, a.meter_reading AS asset_meter
         FROM {maintenance_schedules} s
         INNER JOIN {assets} a ON a.id = s.asset_id
         WHERE s.id = ? LIMIT 1',
        [$scheduleId]
    );

    if ($schedule !== null) {
        $defaults['asset_id']    = (int) $schedule['asset_id'];
        $defaults['log_type']    = (string) $schedule['log_type'];
        $defaults['title']       = (string) $schedule['name'];
        $defaults['description'] = (string) ($schedule['description'] ?? '');
        $defaults['labor_hours'] = $schedule['estimated_hours'] ?? '';
    }
}

// Coming from a work order: same idea.
if ($workOrderId > 0) {
    $workOrder = \App\Models\WorkOrder::find($workOrderId);

    if ($workOrder !== null) {
        $defaults['asset_id']    = (int) ($workOrder['asset_id'] ?? 0);
        $defaults['title']       = (string) $workOrder['title'];
        $defaults['description'] = (string) ($workOrder['description'] ?? '');
        $defaults['log_type']    = 'repair';
    }
}

$values    = $editing ? array_merge($defaults, $log) : $defaults;
$logParts  = $editing ? MaintenanceLog::parts($id) : [];
$asset     = ((int) $values['asset_id']) > 0 ? Asset::find((int) $values['asset_id']) : null;

// Schedules on the chosen machine, so the log can be tied to one.
$assetSchedules = [];

if ($asset !== null && feature_on('schedules')) {
    $assetSchedules = db()->pairs(
        'SELECT id, name FROM {maintenance_schedules} WHERE asset_id = ? AND is_active = 1 ORDER BY name',
        [(int) $asset['id']]
    );
}

$openWorkOrders = [];

if ($asset !== null && feature_on('work_orders')) {
    $openWorkOrders = db()->pairs(
        "SELECT id, CONCAT(wo_number, ' — ', title) FROM {work_orders}
         WHERE asset_id = ? AND deleted_at IS NULL AND status NOT IN ('completed','cancelled')
         ORDER BY created_at DESC",
        [(int) $asset['id']]
    );
}

View::render('logs/edit', [
    'title'       => $editing ? 'Edit maintenance log' : 'Log maintenance',
    'subtitle'    => $editing
        ? (string) $log['asset_name']
        : 'Record work you have done on a kart, ride or ' . asset_word(),
    'activeNav'   => 'logs.php',
    'breadcrumbs' => [
        ['label' => 'Maintenance Logs', 'url' => url('logs.php')],
        ['label' => $editing ? 'Edit' : 'New'],
    ],
    'editing'        => $editing,
    'log'            => $log,
    'values'         => $values,
    'logParts'       => $logParts,
    'asset'          => $asset,
    'schedule'       => $schedule,
    'workOrder'      => $workOrder,
    'assets'         => Asset::options(),
    'assetHistory'   => $asset === null ? [] : Asset::timeline((int) $asset['id'], '', 6),
    'partOptions'    => can('parts.view') ? Part::options() : [],
    'assetSchedules' => $assetSchedules,
    'openWorkOrders' => $openWorkOrders,
    'technicians'    => can('logs.edit_any') ? \App\Models\WorkOrder::assigneeOptions() : [],
]);
