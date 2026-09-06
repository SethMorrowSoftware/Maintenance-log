<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Csv;
use App\Dates;
use App\Models\Asset;
use App\Models\MaintenanceLog;
use App\Paginator;
use App\Request;
use App\Status;
use App\View;

Auth::requireLogin();
Acl::requirePermission('logs.view');

if (is_post()) {
    Csrf::verify();

    if (Request::string('action') === 'delete') {
        Acl::requirePermission('logs.delete');

        if (MaintenanceLog::delete(Request::int('id'))) {
            flash('success', 'Log deleted. Any parts it used have been put back into stock.');
        } else {
            flash('error', 'That log could not be found.');
        }
    }

    redirect(url('logs.php', $_GET));
}

// A named date range keeps the common cases one click away.
$range = Request::string('range');
[$presetFrom, $presetTo] = $range !== '' ? Dates::preset($range) : [null, null];

$filters = [
    'q'           => Request::string('q'),
    'asset_id'    => Request::int('asset_id'),
    'category_id' => Request::int('category_id'),
    'location_id' => Request::int('location_id'),
    'user_id'     => Request::int('user_id'),
    'log_type'    => Request::string('log_type'),
    'from'        => $range !== '' ? (string) $presetFrom : Request::string('from'),
    'to'          => $range !== '' ? (string) $presetTo : Request::string('to'),
    'followup'    => Request::bool('followup') ? 1 : 0,
];

$sort      = Request::string('sort', 'performed');
$direction = Request::string('dir', 'desc');

// Somebody who cannot see money cannot rank jobs by it either.
if (!isset(MaintenanceLog::SORTS[$sort]) || ($sort === 'cost' && !costs_visible())) {
    $sort = 'performed';
}

if (Request::string('export') === 'csv') {
    Acl::requirePermission('reports.export');

    $rows = MaintenanceLog::forExport($filters);
    audit('export', 'maintenance_log', null, 'Exported ' . count($rows) . ' maintenance logs to CSV');

    // One column per entry, so the money columns can be dropped for anybody who
    // is not allowed to see them without two copies of the list.
    $columns = [
        'Date'            => static fn (array $r) => Dates::date((string) $r['performed_at'], ''),
        'Time'            => static fn (array $r) => Dates::time((string) $r['performed_at'], ''),
        asset_word(false, true) . ' tag'       => static fn (array $r) => $r['asset_tag'],
        asset_word(false, true)           => static fn (array $r) => $r['asset_name'],
        'Category'        => static fn (array $r) => $r['category'],
        'Location'        => static fn (array $r) => $r['location'],
        'Type'            => static fn (array $r) => Status::label((string) $r['log_type'], 'log_type'),
        'Job'             => static fn (array $r) => $r['title'],
        'Description'     => static fn (array $r) => $r['description'],
        'Work performed'  => static fn (array $r) => $r['work_performed'],
        'Technician'      => static fn (array $r) => trim((string) $r['technician']),
        'Hours'           => static fn (array $r) => $r['labor_hours'],
        'Labour cost'     => static fn (array $r) => $r['labor_cost'],
        'Parts cost'      => static fn (array $r) => $r['parts_cost'],
        'Other cost'      => static fn (array $r) => $r['other_cost'],
        'Total cost'      => static fn (array $r) => $r['total_cost'],
        'Parts used'      => static fn (array $r) => $r['parts_used'],
        'Meter reading'   => static fn (array $r) => $r['meter_reading'],
        'Downtime (min)'  => static fn (array $r) => $r['downtime_minutes'],
        'Status after'    => static fn (array $r) => $r['status_after'] === null ? '' : Status::label((string) $r['status_after'], 'asset'),
        'Follow-up needed' => static fn (array $r) => (int) $r['requires_followup'] === 1 ? 'Yes' : 'No',
        'Follow-up notes' => static fn (array $r) => $r['followup_notes'],
    ];

    if (!costs_visible()) {
        unset($columns['Labour cost'], $columns['Parts cost'], $columns['Other cost'], $columns['Total cost']);
    }

    Csv::stream(
        Csv::filename('maintenance-logs'),
        array_keys($columns),
        $rows,
        static function (array $row) use ($columns): array {
            $out = [];

            foreach ($columns as $cell) {
                $out[] = $cell($row);
            }

            return $out;
        }
    );
}


$total     = MaintenanceLog::count($filters);
$paginator = Paginator::fromRequest($total, null, 'logs.php');
$logs      = MaintenanceLog::paginate($filters, $sort, $direction, $paginator->limit(), $paginator->offset());
$totals    = MaintenanceLog::totals($filters);

$hasFilters = false;

foreach ($filters as $key => $value) {
    if ($value !== '' && $value !== 0) {
        $hasFilters = true;
        break;
    }
}

$actions = '';

if (can('logs.create')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('log-edit.php')) . '">'
        . icon('plus', '', 17) . ' Log maintenance</a>';
}

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('logs.php', array_merge($_GET, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

View::render('logs/index', [
    'title'       => 'Maintenance Logs',
    'subtitle'    => 'Every job recorded, newest first',
    'activeNav'   => 'logs.php',
    'pageActions' => $actions,
    'logs'        => $logs,
    'paginator'   => $paginator,
    'totals'      => $totals,
    'filters'     => $filters,
    'range'       => $range,
    'hasFilters'  => $hasFilters,
    'sort'        => $sort,
    'direction'   => $direction,
    'assets'      => Asset::options(true),
    'categories'  => Asset::categoryOptions(),
    'locations'   => Asset::locationOptions(),
    'technicians' => MaintenanceLog::technicianOptions(),
]);
