<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Csv;
use App\Dates;
use App\Models\Asset;
use App\Models\WorkOrder;
use App\Paginator;
use App\Request;
use App\Status;
use App\View;

Auth::requireLogin();
Acl::requirePermission('workorders.view');

if (is_post()) {
    Csrf::verify();

    // Pick it up, hand it back, mark it done — the same buttons as on the work
    // order itself, so a mechanic can clear a job without opening it.
    \App\WorkOrderActions::handle();

    if (Request::string('action') === 'delete') {
        Acl::requirePermission('workorders.delete');
        WorkOrder::delete(Request::int('id'))
            ? flash('success', 'Work order deleted.')
            : flash('error', 'That work order could not be found.');
    }

    redirect(url('workorders.php', $_GET));
}

$filters = [
    'q'           => Request::string('q'),
    'status'      => Request::string('status'),
    'priority'    => Request::string('priority'),
    'asset_id'    => Request::int('asset_id'),
    'assigned_to' => Request::int('assigned_to'),
    'unassigned'  => Request::bool('unassigned') ? 1 : 0,
    'overdue'     => Request::bool('overdue') ? 1 : 0,
];

$sort      = Request::string('sort', 'priority');
$direction = Request::string('dir', 'asc');

if (!isset(WorkOrder::SORTS[$sort])) {
    $sort = 'priority';
}

if (Request::string('export') === 'csv') {
    Acl::requirePermission('reports.export');

    $rows = WorkOrder::forExport($filters);
    audit('export', 'work_order', null, 'Exported ' . count($rows) . ' work orders to CSV');

    Csv::stream(
        Csv::filename('work-orders'),
        ['Number', asset_word(false, true) . ' tag', asset_word(false, true), 'Title', 'Description', 'Priority', 'Status',
         'Source', 'Reported by', 'Assigned to', 'Raised', 'Due', 'Started', 'Completed',
         'Downtime (min)', 'Hours', 'Resolution'],
        $rows,
        static function (array $row): array {
            return [
                $row['wo_number'], $row['asset_tag'], $row['asset_name'], $row['title'],
                $row['description'],
                Status::label((string) $row['priority'], 'priority'),
                Status::label((string) $row['status'], 'workorder'),
                Status::label((string) $row['source'], 'wo_source'),
                trim((string) $row['reported_by']), trim((string) $row['assigned_to']),
                Dates::datetime((string) $row['created_at'], ''),
                $row['due_date'],
                Dates::datetime((string) ($row['started_at'] ?? ''), ''),
                Dates::datetime((string) ($row['completed_at'] ?? ''), ''),
                $row['downtime_minutes'], $row['actual_hours'], $row['resolution'],
            ];
        }
    );
}

$total     = WorkOrder::count($filters);
$paginator = Paginator::fromRequest($total, null, 'workorders.php');
$orders    = WorkOrder::paginate($filters, $sort, $direction, $paginator->limit(), $paginator->offset());

$hasFilters = false;

foreach ($filters as $value) {
    if ($value !== '' && $value !== 0) {
        $hasFilters = true;
        break;
    }
}

$actions = '';

if (can('workorders.create')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('workorder-edit.php')) . '">'
        . icon('plus', '', 17) . ' Report an issue</a>';
}

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('workorders.php', array_merge($_GET, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

$assetOptions = [];

foreach (Asset::options(true) as $asset) {
    $assetOptions[(int) $asset['id']] = (string) $asset['name'] . ' — ' . (string) $asset['asset_tag'];
}

View::render('workorders/index', [
    'title'        => 'Work Orders',
    'subtitle'     => 'Reported problems, from first report to fixed',
    'activeNav'    => 'workorders.php',
    'pageActions'  => $actions,
    'orders'       => $orders,
    'paginator'    => $paginator,
    'filters'      => $filters,
    'hasFilters'   => $hasFilters,
    'sort'         => $sort,
    'direction'    => $direction,
    'statusCounts' => WorkOrder::statusCounts(),
    'assets'       => $assetOptions,
    'assignees'    => WorkOrder::assigneeOptions(),
]);
