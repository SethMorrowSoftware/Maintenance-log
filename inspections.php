<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Csv;
use App\Dates;
use App\Models\Asset;
use App\Models\Inspection;
use App\Paginator;
use App\Request;
use App\Status;
use App\View;

Auth::requireLogin();
Acl::requirePermission('inspections.view');

if (Request::string('action') === 'start') {
    Acl::requirePermission('inspections.perform');
    redirect(url('inspection-run.php'));
}

if (is_post()) {
    Csrf::verify();
    Acl::requirePermission('inspections.delete');

    if (Request::string('action') === 'delete') {
        $id = Request::int('id');
        $inspection = Inspection::find($id);

        if ($inspection !== null) {
            db()->delete('inspections', ['id' => $id]);
            audit('delete', 'inspection', $id, 'Deleted inspection of ' . (string) $inspection['asset_name']);
            flash('success', 'Inspection deleted.');
        }
    }

    redirect(url('inspections.php', $_GET));
}

$filters = [
    'asset_id'     => Request::int('asset_id'),
    'user_id'      => Request::int('user_id'),
    'checklist_id' => Request::int('checklist_id'),
    'status'       => Request::string('status'),
    'from'         => Request::string('from'),
    'to'           => Request::string('to'),
];

if (Request::string('export') === 'csv') {
    Acl::requirePermission('reports.export');

    $rows = Inspection::forExport($filters);
    audit('export', 'inspection', null, 'Exported ' . count($rows) . ' inspections to CSV');

    Csv::stream(
        Csv::filename('inspections'),
        ['Started', 'Completed', asset_word(false, true) . ' tag', asset_word(false, true), 'Checklist', 'Result',
         'Passed', 'Failed', 'N/A', 'Critical failure', 'Meter', 'Minutes',
         'Signed by', 'Inspector', 'Notes'],
        $rows,
        static function (array $row): array {
            return [
                Dates::datetime((string) $row['started_at'], ''),
                Dates::datetime((string) ($row['completed_at'] ?? ''), ''),
                $row['asset_tag'], $row['asset_name'], $row['checklist_name'],
                Status::label((string) $row['status'], 'inspection'),
                $row['passed_count'], $row['failed_count'], $row['na_count'],
                (int) $row['critical_failed'] === 1 ? 'Yes' : 'No',
                $row['meter_reading'], $row['duration_minutes'],
                $row['signature_name'], trim((string) $row['inspector']), $row['notes'],
            ];
        }
    );
}

$total     = Inspection::count($filters);
$paginator = Paginator::fromRequest($total, null, 'inspections.php');
$rows      = Inspection::paginate($filters, $paginator->limit(), $paginator->offset());

$actions = '';

if (can('inspections.perform')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('inspection-run.php')) . '">'
        . icon('clipboard-check', '', 17) . ' Run an inspection</a>';
}

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('inspections.php', array_merge($_GET, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

$assetOptions = [];

foreach (Asset::options(true) as $asset) {
    $assetOptions[(int) $asset['id']] = (string) $asset['name'] . ' — ' . (string) $asset['asset_tag'];
}

View::render('inspections/index', [
    'title'       => 'Inspections',
    'subtitle'    => 'Completed safety and pre-operation checks',
    'activeNav'   => 'inspections.php',
    'pageActions' => $actions,
    'rows'        => $rows,
    'paginator'   => $paginator,
    'filters'     => $filters,
    'assets'      => $assetOptions,
    'checklists'  => db()->pairs('SELECT id, name FROM {checklists} ORDER BY name'),
]);
