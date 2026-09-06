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

        // Somebody limited to an area may only delete checks in it.
        if ($inspection !== null && !\App\Scope::allowsInspection($inspection)) {
            abort(403, 'This check is outside your area.');
        }

        if ($inspection !== null) {
            db()->delete('inspections', ['id' => $id]);
            audit('delete', 'inspection', $id, 'Deleted inspection of ' . Inspection::subject($inspection));
            flash('success', 'Inspection deleted.');
        }
    }

    redirect(url('inspections.php', $_GET));
}

$filters = [
    'asset_id'     => Request::int('asset_id'),
    'location_id'  => Request::int('location_id'),
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
        ['Started', 'Completed', 'Due by', 'On time', asset_word(false, true) . ' tag', asset_word(false, true), 'Area', 'Checklist', 'Result',
         'Passed', 'Failed', 'N/A', 'Critical failure', 'Meter', 'Minutes',
         'Signed by', 'Inspector', 'Notes'],
        $rows,
        static function (array $row): array {
            return [
                Dates::datetime((string) $row['started_at'], ''),
                Dates::datetime((string) ($row['completed_at'] ?? ''), ''),
                Dates::datetime((string) ($row['due_at'] ?? ''), ''),
                $row['due_at'] === null || $row['completed_at'] === null ? '' : ((int) $row['was_late'] === 1 ? 'No' : 'Yes'),
                $row['asset_tag'], $row['asset_name'], $row['location_name'], $row['checklist_name'],
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
        . icon('clipboard-check', '', 17) . ' Run a check</a>';
}

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('inspections.php', array_merge($_GET, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

// The pick-lists, narrowed to the user's area when they have one.
[$scopeSql, $scopeParams] = \App\Scope::assetFilter('a');

$assetOptions = [];

foreach (db()->all(
    "SELECT a.id, a.name, a.asset_tag FROM {assets} a WHERE a.deleted_at IS NULL"
    . ($scopeSql !== null ? ' AND ' . $scopeSql : '')
    . ' ORDER BY a.sort_order ASC, a.name ASC',
    $scopeParams
) as $asset) {
    $assetOptions[(int) $asset['id']] = (string) $asset['name'] . ' — ' . (string) $asset['asset_tag'];
}

$locationOptions = Asset::locationOptions(false);
$areas           = \App\Scope::areas();

if ($areas !== [] && \App\Scope::limited()) {
    $locationOptions = array_intersect_key($locationOptions, array_flip($areas));
}

// The checklist pick-list, narrowed the same way: somebody limited to an
// area is not shown the names of lists for places they never see.
$checklistOptions = [];
$scopedMachines   = \App\Scope::limited()
    ? db()->all(
        'SELECT a.id, a.category_id, a.location_id FROM {assets} a WHERE a.deleted_at IS NULL'
        . ($scopeSql !== null ? ' AND ' . $scopeSql : ''),
        $scopeParams
    )
    : [];

foreach (db()->all('SELECT * FROM {checklists} ORDER BY name') as $checklist) {
    if (\App\Scope::limited() && !\App\Scope::allowsChecklist($checklist, null)) {
        // A machine checklist counts when it covers at least one machine in the area.
        $covers = false;

        foreach ($scopedMachines as $machine) {
            $matches = (string) $checklist['applies_to'] === 'all'
                || ((string) $checklist['applies_to'] === 'category' && (int) $checklist['category_id'] === (int) $machine['category_id'])
                || ((string) $checklist['applies_to'] === 'asset' && (int) $checklist['asset_id'] === (int) $machine['id']);

            if ($matches && \App\Scope::allowsChecklist($checklist, $machine)) {
                $covers = true;
                break;
            }
        }

        if (!$covers) {
            continue;
        }
    }

    $checklistOptions[(int) $checklist['id']] = (string) $checklist['name'];
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
    'locations'   => $locationOptions,
    'checklists'  => $checklistOptions,
]);
