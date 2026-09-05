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
use App\Uploader;
use App\View;

Auth::requireLogin();
Acl::requirePermission('inspections.view');

$id         = Request::int('id');
$inspection = Inspection::find($id);

if ($inspection === null) {
    abort(404, 'That inspection does not exist. It may have been deleted.');
}

// An unfinished one belongs on the runner, not here.
if ((string) $inspection['status'] === 'in_progress' && !Request::bool('print')) {
    redirect(url('inspection-run.php', ['id' => $id]));
}

if (is_post()) {
    Csrf::verify();
    Acl::requirePermission('inspections.delete');

    if (Request::string('action') === 'delete') {
        db()->delete('inspections', ['id' => $id]);
        audit('delete', 'inspection', $id, 'Deleted the '
            . (string) $inspection['checklist_name'] . ' of ' . (string) $inspection['asset_name']);
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
    'subtitle'    => (string) $inspection['asset_name'] . ' · ' . (string) $inspection['asset_tag'],
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
        asset_word(false, true)     => (string) $inspection['asset_name'] . ' (' . (string) $inspection['asset_tag'] . ')',
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
    $actions .= '<a class="btn btn-primary" href="'
        . e(url('inspection-run.php', ['asset_id' => (int) $inspection['asset_id']])) . '">'
        . icon('clipboard-check', '', 17) . ' Check it again</a>';
}

$data['pageActions'] = $actions;

View::render('inspections/view', $data);
