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

if (!isset(MaintenanceLog::SORTS[$sort])) {
    $sort = 'performed';
}

if (Request::string('export') === 'csv') {
    Acl::requirePermission('reports.export');

    $rows = MaintenanceLog::forExport($filters);
    audit('export', 'maintenance_log', null, 'Exported ' . count($rows) . ' maintenance logs to CSV');

    Csv::stream(
        Csv::filename('maintenance-logs'),
        [
            'Date', 'Time', 'Asset tag', 'Asset', 'Category', 'Location', 'Type', 'Job',
            'Description', 'Work performed', 'Technician', 'Hours', 'Labour cost',
            'Parts cost', 'Other cost', 'Total cost', 'Parts used', 'Meter reading',
            'Downtime (min)', 'Status after', 'Follow-up needed', 'Follow-up notes',
        ],
        $rows,
        static function (array $row): array {
            return [
                Dates::date((string) $row['performed_at'], ''),
                Dates::time((string) $row['performed_at'], ''),
                $row['asset_tag'],
                $row['asset_name'],
                $row['category'],
                $row['location'],
                Status::label((string) $row['log_type'], 'log_type'),
                $row['title'],
                $row['description'],
                $row['work_performed'],
                trim((string) $row['technician']),
                $row['labor_hours'],
                $row['labor_cost'],
                $row['parts_cost'],
                $row['other_cost'],
                $row['total_cost'],
                $row['parts_used'],
                $row['meter_reading'],
                $row['downtime_minutes'],
                $row['status_after'] === null ? '' : Status::label((string) $row['status_after'], 'asset'),
                (int) $row['requires_followup'] === 1 ? 'Yes' : 'No',
                $row['followup_notes'],
            ];
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
