<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Models\WorkOrder;
use App\Request;
use App\Status;
use App\Uploader;
use App\View;

Auth::requireLogin();
Acl::requirePermission('workorders.view');

$id        = Request::int('id');
$workOrder = WorkOrder::find($id);

if ($workOrder === null) {
    abort(404, 'That work order does not exist.');
}

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');

    if ($action === 'comment') {
        $comment = Request::string('comment');

        if ($comment === '') {
            flash('error', 'Type something first.');
        } else {
            WorkOrder::addComment($id, $comment);
            flash('success', 'Comment added.');
        }
    }

    if ($action === 'status') {
        if (!Acl::canEditWorkOrder($workOrder)) {
            abort(403, 'You cannot change this work order.');
        }

        $status = Request::string('status');

        if (Status::isClosedWorkOrder($status) && !can('workorders.close')) {
            abort(403, 'You do not have permission to close a work order.');
        }

        if (Status::isValid($status, 'workorder')) {
            $update = ['status' => $status];
            $resolution = Request::string('resolution');

            if ($resolution !== '') {
                $update['resolution'] = $resolution;
            }

            $downtime = Request::intOrNull('downtime_minutes');

            if ($downtime !== null) {
                $update['downtime_minutes'] = $downtime;
            }

            WorkOrder::update($id, $update);
            flash('success', 'Status updated.');
        }
    }

    if ($action === 'assign') {
        Acl::requirePermission('workorders.assign');

        $assignee = Request::intOrNull('assigned_to');

        WorkOrder::update($id, [
            'assigned_to' => $assignee,
            'status'      => $assignee !== null && (string) $workOrder['status'] === 'open'
                ? 'assigned'
                : (string) $workOrder['status'],
        ]);

        flash('success', $assignee === null ? 'Unassigned.' : 'Assigned.');
    }

    if ($action === 'delete') {
        Acl::requirePermission('workorders.delete');

        if (WorkOrder::delete($id)) {
            flash('success', 'Work order deleted.');
            redirect(url('workorders.php'));
        }
    }

    if ($action === 'upload_attachment') {
        $files = Request::files('attachments');

        if ($files !== []) {
            $result = Uploader::handleMany($files, 'work_order', $id, Auth::id());

            if ($result['uploaded'] > 0) {
                flash('success', $result['uploaded'] . ' file attached.');
            }

            foreach ($result['errors'] as $error) {
                flash('error', $error);
            }
        }
    }

    if ($action === 'delete_attachment') {
        $attachmentId = Request::int('attachment_id');
        $attachment   = db()->find('attachments', $attachmentId);

        if ($attachment !== null
            && (string) $attachment['entity_type'] === 'work_order'
            && (int) $attachment['entity_id'] === $id) {
            Uploader::delete($attachmentId);
            flash('success', 'Attachment removed.');
        }
    }

    redirect(url('workorder-view.php', ['id' => $id]));
}

$actions = '';

if (!Status::isClosedWorkOrder((string) $workOrder['status']) && can('logs.create')) {
    $actions .= '<a class="btn btn-primary" href="'
        . e(url('log-edit.php', ['work_order_id' => $id, 'asset_id' => (int) ($workOrder['asset_id'] ?? 0)])) . '">'
        . icon('wrench', '', 17) . ' Log the fix</a>';
}

if (Acl::canEditWorkOrder($workOrder)) {
    $actions .= '<a class="btn btn-secondary" href="' . e(url('workorder-edit.php', ['id' => $id])) . '">'
        . icon('edit', '', 17) . ' Edit</a>';
}

View::render('workorders/view', [
    'title'       => (string) $workOrder['title'],
    'subtitle'    => (string) $workOrder['wo_number'],
    'activeNav'   => 'workorders.php',
    'pageActions' => $actions,
    'breadcrumbs' => [
        ['label' => 'Work Orders', 'url' => url('workorders.php')],
        ['label' => (string) $workOrder['wo_number']],
    ],
    'workOrder'   => $workOrder,
    'comments'    => WorkOrder::comments($id),
    'attachments' => Uploader::forEntity('work_order', $id),
    'assignees'   => WorkOrder::assigneeOptions(),
    'logs'        => db()->all(
        'SELECT id, title, performed_at, total_cost FROM {maintenance_logs}
         WHERE work_order_id = ? AND deleted_at IS NULL ORDER BY performed_at DESC',
        [$id]
    ),
]);
