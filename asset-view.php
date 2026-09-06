<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Audit;
use App\Auth;
use App\Csrf;
use App\Models\Asset;
use App\Request;
use App\Scheduler;
use App\Status;
use App\Uploader;
use App\View;

Auth::requireLogin();
Acl::requirePermission('assets.view');

$id    = Request::int('id');
$asset = Asset::find($id);

if ($asset === null) {
    abort(404, 'That ' . asset_word() . ' does not exist. It may have been deleted.');
}

// -----------------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');

    if ($action === 'status') {
        Acl::requirePermission('assets.edit');

        if (Asset::changeStatus($id, Request::string('status'), Request::string('reason'))) {
            flash('success', 'Status updated.');
        } else {
            flash('error', 'That status could not be applied.');
        }
    }

    if (in_array($action, ['upload_attachment', 'delete_attachment', 'set_photo'], true)) {
        require_feature('photos');
    }

    if ($action === 'upload_attachment') {
        Acl::requirePermission('assets.edit');

        $files = Request::files('attachments');

        if ($files === []) {
            flash('error', 'Choose a file first.');
        } else {
            $result = Uploader::handleMany($files, 'asset', $id, Auth::id());

            if ($result['uploaded'] > 0) {
                flash('success', $result['uploaded'] . ' file' . ($result['uploaded'] === 1 ? '' : 's') . ' attached.');
            }

            foreach ($result['errors'] as $error) {
                flash('error', $error);
            }
        }
    }

    if ($action === 'delete_attachment') {
        Acl::requirePermission('assets.edit');

        $attachmentId = Request::int('attachment_id');
        $attachment   = db()->find('attachments', $attachmentId);

        // Only remove a file that actually belongs to this machine.
        if ($attachment !== null
            && (string) $attachment['entity_type'] === 'asset'
            && (int) $attachment['entity_id'] === $id) {
            // If it was the profile photo, clear that too.
            if ((string) $attachment['file_path'] === (string) $asset['image_path']) {
                db()->update('assets', ['image_path' => null], ['id' => $id]);
            }

            Uploader::delete($attachmentId);
            flash('success', 'Attachment removed.');
        } else {
            flash('error', 'That attachment could not be found.');
        }
    }

    if ($action === 'set_photo') {
        Acl::requirePermission('assets.edit');

        $attachmentId = Request::int('attachment_id');
        $attachment   = db()->find('attachments', $attachmentId);

        if ($attachment !== null
            && (string) $attachment['entity_type'] === 'asset'
            && (int) $attachment['entity_id'] === $id
            && (int) $attachment['is_image'] === 1) {
            db()->update('assets', ['image_path' => (string) $attachment['file_path']], ['id' => $id]);
            flash('success', 'Photo updated.');
        }
    }

    redirect(url('asset-view.php', ['id' => $id, 'tab' => Request::string('tab')]));
}

// -----------------------------------------------------------------------------
// Data
// -----------------------------------------------------------------------------

$counts  = Asset::relatedCounts($id);
$summary = Asset::summary($id);

$tab = Request::enum(
    'tab',
    ['overview', 'timeline', 'logs', 'schedules', 'inspections', 'workorders', 'files', 'meter', 'history'],
    'overview'
);

$q    = trim(Request::string('q'));
$data = [];

// A tab whose module is switched off (or that this person may not see)
// falls back to the overview rather than showing an empty page.
$tabGates = [
    'schedules'   => can('schedules.view'),
    'inspections' => can('inspections.view'),
    'workorders'  => can('workorders.view'),
    'files'       => feature_on('photos'),
    'meter'       => feature_on('meters'),
    'history'     => can('audit.view'),
];

if (isset($tabGates[$tab]) && !$tabGates[$tab]) {
    $tab = 'overview';
}

switch ($tab) {
    case 'timeline':
        // Everything that ever happened to it, in one list, searchable.
        $data['events'] = Asset::timeline($id, $q);
        break;

    case 'logs':
        $data['logs'] = db()->all(
            'SELECT l.*, u.first_name, u.last_name, u.username, u.avatar_path
             FROM {maintenance_logs} l
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE l.asset_id = ? AND l.deleted_at IS NULL
             ORDER BY l.performed_at DESC, l.id DESC
             LIMIT 200',
            [$id]
        );
        break;

    case 'schedules':
        $data['schedules'] = Scheduler::forAsset($id);
        break;

    case 'inspections':
        $data['inspections'] = db()->all(
            'SELECT i.*, u.first_name, u.last_name, u.username
             FROM {inspections} i
             LEFT JOIN {users} u ON u.id = i.user_id
             WHERE i.asset_id = ?
             ORDER BY i.started_at DESC
             LIMIT 100',
            [$id]
        );
        break;

    case 'workorders':
        $data['workOrders'] = db()->all(
            'SELECT w.*, u.first_name, u.last_name, u.username
             FROM {work_orders} w
             LEFT JOIN {users} u ON u.id = w.assigned_to
             WHERE w.asset_id = ? AND w.deleted_at IS NULL
             ORDER BY FIELD(w.status, \'open\',\'assigned\',\'in_progress\',\'on_hold\',\'completed\',\'cancelled\'),
                      w.created_at DESC
             LIMIT 100',
            [$id]
        );
        break;

    case 'files':
        $data['attachments'] = Uploader::forEntity('asset', $id);
        break;

    case 'meter':
        $data['readings'] = Asset::meterHistory($id, 100);
        break;

    case 'history':
        $data['audit'] = Audit::forEntity('asset', $id, 100);
        break;

    default:
        $data['recentLogs'] = db()->all(
            'SELECT l.id, l.title, l.log_type, l.performed_at, l.total_cost,
                    u.id AS user_id, u.first_name, u.last_name, u.username, u.avatar_path
             FROM {maintenance_logs} l
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE l.asset_id = ? AND l.deleted_at IS NULL
             ORDER BY l.performed_at DESC LIMIT 6',
            [$id]
        );
        $data['dueSchedules']   = can('schedules.view') ? Scheduler::forAsset($id) : [];
        $data['openWorkOrders'] = can('workorders.view') ? db()->all(
            "SELECT id, wo_number, title, status, priority FROM {work_orders}
             WHERE asset_id = ? AND deleted_at IS NULL AND status NOT IN ('completed','cancelled')
             ORDER BY FIELD(priority,'urgent','high','normal','low') LIMIT 5",
            [$id]
        ) : [];
        break;
}

$actions = '';

if (can('logs.create')) {
    $actions .= '<a class="btn btn-primary" href="'
        . e(url('log-edit.php', ['asset_id' => $id])) . '">'
        . icon('wrench', '', 17) . ' Log maintenance</a>';
}

if (can('workorders.create')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('workorder-edit.php', ['asset_id' => $id])) . '">'
        . icon('work-order', '', 17) . ' Report an issue</a>';
}

if (can('assets.edit')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('asset-edit.php', ['id' => $id])) . '">'
        . icon('edit', '', 17) . ' Edit</a>';
}

if (can('assets.create')) {
    $actions .= '<a class="btn btn-secondary btn-icon" href="' . e(url('asset-edit.php', ['copy_from' => $id]))
        . '" title="Add another like this" aria-label="Add another like this">' . icon('copy', '', 17) . '</a>';
}

if (feature_on('labels')) {
    $actions .= '<a class="btn btn-secondary btn-icon" href="' . e(url('labels.php', ['id' => $id]))
        . '" title="Print a label" aria-label="Print a label">' . icon('qr-code', '', 17) . '</a>';
}

View::render('assets/view', [
    'title'       => (string) $asset['name'],
    'subtitle'    => (string) $asset['asset_tag']
        . (empty($asset['category_name']) ? '' : ' · ' . (string) $asset['category_name']),
    'activeNav'   => 'assets.php',
    'pageActions' => $actions,
    'breadcrumbs' => [
        ['label' => asset_word(true, true), 'url' => url('assets.php')],
        ['label' => (string) $asset['name']],
    ],
    'asset'    => $asset,
    'counts'   => $counts,
    'summary'  => $summary,
    'tab'      => $tab,
    'q'        => $q,
    'data'     => $data,
]);
