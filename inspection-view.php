<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Audit;
use App\Auth;
use App\Csrf;
use App\Dates;
use App\Models\Inspection;
use App\Request;
use App\Scope;
use App\Uploader;
use App\View;

Auth::requireLogin();
Acl::requirePermission('inspections.view');

$id         = Request::int('id');
$inspection = Inspection::find($id);

if ($inspection === null) {
    abort(404, 'That inspection does not exist. It may have been deleted.');
}

// Somebody limited to an area only sees its checks.
if (!Scope::allowsInspection($inspection)) {
    abort(403, 'This check is outside your area.');
}

// An unfinished one belongs on the runner, not here.
if ((string) $inspection['status'] === 'in_progress' && !Request::bool('print')) {
    redirect(url('inspection-run.php', ['id' => $id]));
}

$subject = Inspection::subject($inspection);
$isArea  = $inspection['asset_id'] === null;

if (is_post()) {
    Csrf::verify();
    Acl::requirePermission('inspections.delete');

    if (Request::string('action') === 'delete') {
        db()->delete('inspections', ['id' => $id]);
        audit('delete', 'inspection', $id, 'Deleted the '
            . (string) $inspection['checklist_name'] . ' of ' . $subject);
        flash('success', 'Inspection deleted.');
        redirect(url('inspections.php'));
    }

    redirect(url('inspection-view.php', ['id' => $id]));
}

$items    = Inspection::items($id);
$sections = [];

foreach ($items as $item) {
    $sections[(string) $item['section']][] = $item;
}

$printing = Request::bool('print');

$data = [
    'title'       => (string) $inspection['checklist_name'],
    'subtitle'    => $subject . ($isArea || (string) $inspection['asset_tag'] === '' ? '' : ' · ' . (string) $inspection['asset_tag']),
    'activeNav'   => 'inspections.php',
    'breadcrumbs' => [
        ['label' => 'Inspections', 'url' => url('inspections.php')],
        ['label' => (string) $inspection['checklist_name']],
    ],
    'inspection'  => $inspection,
    'sections'    => $sections,
    'items'       => $items,
    'attachments' => Uploader::forEntity('inspection', $id),
    'history'     => can('audit.view') ? Audit::forEntity('inspection', $id, 20) : [],
    'printing'    => $printing,
];

if ($printing) {
    $data['docTitle']  = 'Inspection report';
    $data['printMeta'] = [
        ($isArea ? 'Area' : asset_word(false, true)) => $subject . ($isArea ? '' : ' (' . (string) $inspection['asset_tag'] . ')'),
        'Checklist' => (string) $inspection['checklist_name'],
        'Carried out' => Dates::datetime((string) ($inspection['completed_at'] ?: $inspection['started_at'])),
        'Report'    => '#' . $id,
    ];

    View::render('inspections/view', $data, 'layout-print');
    exit;
}

$actions = '<a class="btn btn-secondary" href="'
    . e(url('inspection-view.php', ['id' => $id, 'print' => 1])) . '">'
    . icon('printer', '', 17) . ' Print</a>';

if (can('inspections.perform')) {
    // The same list again, without the "which check?" step in between.
    $again = $isArea
        ? ['checklist_id' => (int) $inspection['checklist_id']]
        : array_filter(['asset_id' => (int) $inspection['asset_id'], 'checklist_id' => (int) ($inspection['checklist_id'] ?? 0)]);

    if (!$isArea || !empty($inspection['checklist_id'])) {
        $actions .= '<a class="btn btn-primary" href="'
            . e(url('inspection-run.php', $again)) . '">'
            . icon('clipboard-check', '', 17) . ' Check it again</a>';
    }
}

$data['pageActions'] = $actions;

View::render('inspections/view', $data);
