<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Csv;
use App\Dates;
use App\Models\Asset;
use App\Paginator;
use App\Request;
use App\Status;
use App\View;

Auth::requireLogin();
Acl::requirePermission('assets.view');

// -----------------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');
    $id     = Request::int('id');

    if ($action === 'delete') {
        Acl::requirePermission('assets.delete');

        if (Asset::delete($id)) {
            flash('success', 'Asset deleted. Its maintenance history has been kept.');
        } else {
            flash('error', 'That asset could not be found.');
        }
    }

    if ($action === 'status') {
        Acl::requirePermission('assets.edit');

        $status = Request::string('status');

        if (Asset::changeStatus($id, $status, Request::string('reason'))) {
            flash('success', 'Status updated.');
        } else {
            flash('error', 'That status could not be applied.');
        }
    }

    redirect(url('assets.php', $_GET));
}

// -----------------------------------------------------------------------------
// Filters
// -----------------------------------------------------------------------------

$filters = [
    'q'           => Request::string('q'),
    'category_id' => Request::int('category_id'),
    'location_id' => Request::int('location_id'),
    'status'      => Request::string('status'),
    'criticality' => Request::string('criticality'),
];

$sort      = Request::string('sort', 'name');
$direction = Request::string('dir', 'asc');
$view      = Request::enum('view', ['table', 'cards'], 'table');

if (!isset(Asset::SORTS[$sort])) {
    $sort = 'name';
}

// -----------------------------------------------------------------------------
// CSV export
// -----------------------------------------------------------------------------

if (Request::string('export') === 'csv') {
    Acl::requirePermission('reports.export');

    $rows = Asset::forExport($filters);

    audit('export', 'asset', null, 'Exported ' . count($rows) . ' assets to CSV');

    Csv::stream(
        Csv::filename('assets'),
        [
            'Asset tag', 'Name', 'Category', 'Location', 'Status', 'Criticality',
            'Manufacturer', 'Model', 'Serial number', 'VIN', 'Year',
            'Purchase date', 'Purchase cost', 'Warranty expires',
            'Meter type', 'Meter reading', 'In service since',
            'Last service', 'Lifetime maintenance cost', 'Notes',
        ],
        $rows,
        static function (array $row): array {
            return [
                $row['asset_tag'],
                $row['name'],
                $row['category'],
                $row['location'],
                Status::label((string) $row['status'], 'asset'),
                Status::label((string) $row['criticality'], 'criticality'),
                $row['manufacturer'],
                $row['model'],
                $row['serial_number'],
                $row['vin'],
                $row['year_manufactured'],
                $row['purchase_date'],
                $row['purchase_cost'],
                $row['warranty_expires'],
                $row['meter_type'] === 'none' ? '' : $row['meter_type'],
                $row['meter_type'] === 'none' ? '' : $row['meter_reading'],
                $row['in_service_date'],
                $row['last_service'] === null ? '' : Dates::date((string) $row['last_service'], ''),
                $row['lifetime_cost'],
                $row['notes'],
            ];
        }
    );
}

// -----------------------------------------------------------------------------
// Data
// -----------------------------------------------------------------------------

$total     = Asset::count($filters);
$paginator = Paginator::fromRequest($total, null, 'assets.php');
$assets    = Asset::paginate($filters, $sort, $direction, $paginator->limit(), $paginator->offset());

$hasFilters = false;

foreach ($filters as $value) {
    if ($value !== '' && $value !== 0) {
        $hasFilters = true;
        break;
    }
}

// Counts for the quick status tabs, so somebody can see at a glance how many
// machines are down without running a filter first.
$statusCounts = db()->pairs(
    "SELECT status, COUNT(*) FROM {assets} WHERE deleted_at IS NULL GROUP BY status"
);

$actions = '';

if (can('assets.create')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('asset-edit.php')) . '">'
        . icon('plus', '', 17) . ' Add asset</a>';
}

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('assets.php', array_merge($_GET, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

$actions .= '<a class="btn btn-secondary" href="' . e(url('labels.php')) . '">'
    . icon('qr-code', '', 17) . ' Print labels</a>';

View::render('assets/index', [
    'title'        => 'Assets',
    'subtitle'     => 'Every kart, ride and machine you look after',
    'activeNav'    => 'assets.php',
    'pageActions'  => $actions,
    'assets'       => $assets,
    'paginator'    => $paginator,
    'filters'      => $filters,
    'hasFilters'   => $hasFilters,
    'sort'         => $sort,
    'direction'    => $direction,
    'view'         => $view,
    'categories'   => Asset::categoryOptions(),
    'locations'    => Asset::locationOptions(),
    'statusCounts' => $statusCounts,
]);
