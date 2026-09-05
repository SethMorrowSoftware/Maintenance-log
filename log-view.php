<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Audit;
use App\Auth;
use App\Csrf;
use App\Models\MaintenanceLog;
use App\Request;
use App\Uploader;
use App\View;

Auth::requireLogin();
Acl::requirePermission('logs.view');

$id  = Request::int('id');
$log = MaintenanceLog::find($id);

if ($log === null) {
    abort(404, 'That maintenance log does not exist. It may have been deleted.');
}

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');

    if ($action === 'delete') {
        Acl::requirePermission('logs.delete');

        if (MaintenanceLog::delete($id)) {
            flash('success', 'Log deleted. Any parts it used have been put back into stock.');
            redirect(url('logs.php'));
        }

        flash('error', 'That log could not be deleted.');
    }

    if ($action === 'upload_attachment') {
        if (!Acl::canEditLog($log)) {
            abort(403, 'You can only add files to logs you recorded yourself.');
        }

        $files = Request::files('attachments');

        if ($files === []) {
            flash('error', 'Choose a file first.');
        } else {
            $result = Uploader::handleMany($files, 'maintenance_log', $id, Auth::id());

            if ($result['uploaded'] > 0) {
                flash('success', $result['uploaded'] . ' file' . ($result['uploaded'] === 1 ? '' : 's') . ' attached.');
            }

            foreach ($result['errors'] as $error) {
                flash('error', $error);
            }
        }
    }

    if ($action === 'delete_attachment') {
        if (!Acl::canEditLog($log)) {
            abort(403, 'You can only change logs you recorded yourself.');
        }

        $attachmentId = Request::int('attachment_id');
        $attachment   = db()->find('attachments', $attachmentId);

        if ($attachment !== null
            && (string) $attachment['entity_type'] === 'maintenance_log'
            && (int) $attachment['entity_id'] === $id) {
            Uploader::delete($attachmentId);
            flash('success', 'Attachment removed.');
        }
    }

    redirect(url('log-view.php', ['id' => $id]));
}

$printing = Request::bool('print');

$data = [
    'title'       => (string) $log['title'],
    'subtitle'    => (string) $log['asset_name'] . ' · ' . (string) $log['asset_tag'],
    'activeNav'   => 'logs.php',
    'breadcrumbs' => [
        ['label' => 'Maintenance Logs', 'url' => url('logs.php')],
        ['label' => (string) $log['title']],
    ],
    'log'         => $log,
    'parts'       => MaintenanceLog::parts($id),
    'attachments' => Uploader::forEntity('maintenance_log', $id),
    'history'     => can('audit.view') ? Audit::forEntity('maintenance_log', $id, 20) : [],
    'printing'    => $printing,
];

if ($printing) {
    $data['docTitle']  = 'Maintenance record';
    $data['printMeta'] = [
        'Machine'  => (string) $log['asset_name'] . ' (' . (string) $log['asset_tag'] . ')',
        'Job'    => (string) $log['title'],
        'Record' => '#' . $id,
    ];

    View::render('logs/view', $data, 'layout-print');
    exit;
}

$actions = '';

if (Acl::canEditLog($log)) {
    $actions .= '<a class="btn btn-secondary" href="' . e(url('log-edit.php', ['id' => $id])) . '">'
        . icon('edit', '', 17) . ' Edit</a>';
}

$actions .= '<a class="btn btn-secondary" href="' . e(url('log-view.php', ['id' => $id, 'print' => 1])) . '">'
    . icon('printer', '', 17) . ' Print</a>';

$data['pageActions'] = $actions;

View::render('logs/view', $data);
