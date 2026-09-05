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
use App\Status;
use App\Uploader;
use App\Validator;
use App\View;

Auth::requireLogin();

$id        = Request::int('id');
$editing   = $id > 0;
$workOrder = null;

if ($editing) {
    Acl::requirePermission('workorders.view');

    $workOrder = WorkOrder::find($id);

    if ($workOrder === null) {
        abort(404, 'That work order does not exist.');
    }

    if (!Acl::canEditWorkOrder($workOrder)) {
        abort(403, 'You can only update work orders you raised or that are assigned to you.');
    }
} else {
    Acl::requirePermission('workorders.create');
}

if (is_post()) {
    Csrf::verify();

    $validator = Validator::make($_POST, [
        'title'            => 'required|string|max:191',
        'asset_id'         => 'nullable|int|exists:assets,id',
        'description'      => 'nullable|text|max:5000',
        'priority'         => 'required|' . Status::rule('priority'),
        'source'           => 'required|' . Status::rule('wo_source'),
        'assigned_to'      => 'nullable|int|exists:users,id',
        'due_date'         => 'nullable|date',
        'estimated_hours'  => 'nullable|decimal|min:0|max:9999',
    ], [
        'title.required' => 'Say briefly what the problem is.',
    ], [
        'asset_id'        => 'Machine',
        'assigned_to'     => 'Assigned to',
        'due_date'        => 'Due date',
        'estimated_hours' => 'Estimated hours',
    ]);

    if ($validator->fails()) {
        flash_errors($validator->errors(), $_POST);
        redirect(url('workorder-edit.php', $editing ? ['id' => $id] : []));
    }

    $data = $validator->validated();

    foreach (['asset_id', 'assigned_to'] as $key) {
        if (empty($data[$key])) {
            $data[$key] = null;
        }
    }

    // Only somebody who can assign work may choose the assignee.
    if (!can('workorders.assign')) {
        unset($data['assigned_to']);
    }

    $data['is_safety_issue']     = Request::bool('is_safety_issue') ? 1 : 0;
    $data['took_out_of_service'] = Request::bool('took_out_of_service') ? 1 : 0;

    try {
        if ($editing) {
            WorkOrder::update($id, $data);
            $savedId = $id;
            flash('success', 'Saved.');
            \App\Flash::clearDraft('wo-' . $id);
        } else {
            $data['status'] = 'open';
            $savedId = WorkOrder::create($data);
            flash('success', 'Reported. Thanks — somebody will pick this up.');
            \App\Flash::clearDraft('wo-new');
        }

        $files = Request::files('attachments');

        if ($files !== []) {
            $result = Uploader::handleMany($files, 'work_order', $savedId, Auth::id());

            foreach ($result['errors'] as $error) {
                flash('warning', $error);
            }
        }

        redirect(url('workorder-view.php', ['id' => $savedId]));
    } catch (Throwable $e) {
        log_error('Work order save failed: ' . $e->getMessage());
        flash('error', 'The work order could not be saved. The error has been logged.');
        redirect(url('workorder-edit.php', $editing ? ['id' => $id] : []));
    }
}

$assetId = Request::int('asset_id');

$defaults = [
    'title'           => '',
    'asset_id'        => $assetId,
    'description'     => '',
    'priority'        => 'normal',
    'source'          => 'operator_report',
    'assigned_to'     => 0,
    'due_date'        => '',
    'estimated_hours' => '',
    'is_safety_issue' => 0,
    'took_out_of_service' => 0,
];

$values = $editing ? array_merge($defaults, $workOrder) : $defaults;

// The machine this report is about, so the form can show what has already
// happened to it. Rebuilt on the page if a different one is picked.
$asset = (int) $values['asset_id'] > 0 ? Asset::find((int) $values['asset_id']) : null;

View::render('workorders/edit', [
    'title'    => $editing ? 'Edit ' . (string) $workOrder['wo_number'] : 'Report an issue',
    'subtitle' => $editing
        ? (string) $workOrder['title']
        : 'Tell the maintenance team about a problem with a kart, ride or machine',
    'activeNav'   => 'workorders.php',
    'breadcrumbs' => [
        ['label' => 'Work Orders', 'url' => url('workorders.php')],
        ['label' => $editing ? (string) $workOrder['wo_number'] : 'Report an issue'],
    ],
    'editing'   => $editing,
    'workOrder' => $workOrder,
    'values'    => $values,
    'assets'    => Asset::options(),
    'assignees' => WorkOrder::assigneeOptions(),
    'asset'        => $asset,
    'assetHistory' => $asset === null ? [] : Asset::timeline((int) $asset['id'], '', 6),
]);
