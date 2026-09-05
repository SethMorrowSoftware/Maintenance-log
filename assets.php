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
            flash('success', asset_word(false, true) . ' deleted. Its maintenance history has been kept.');
        } else {
            flash('error', 'That ' . asset_word() . ' could not be found.');
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

    // Several machines at once: tick them on the list, choose a category
    // and/or location, apply. "Keep" leaves that side alone.
    if ($action === 'bulk_move') {
        Acl::requirePermission('assets.edit');

        $ids     = array_slice(array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))))), 0, 500);
        $changes = [];

        foreach (['category_id' => 'asset_categories', 'location_id' => 'locations'] as $field => $table) {
            $choice = trim((string) ($_POST[$field] ?? ''));

            if ($choice === 'none') {
                $changes[$field] = null;
            } elseif ($choice !== '' && ctype_digit($choice) && db()->exists($table, ['id' => (int) $choice])) {
                $changes[$field] = (int) $choice;
            }
        }

        if ($ids === []) {
            flash('error', 'Tick the ' . asset_word(true) . ' to move first.');
        } elseif ($changes === []) {
            flash('error', 'Choose a category or a location to move them to.');
        } else {
            $moved = 0;

            foreach ($ids as $assetId) {
                if (Asset::find($assetId) !== null) {
                    Asset::update($assetId, $changes);
                    $moved++;
                }
            }

            flash('success', $moved . ' ' . ($moved === 1 ? asset_word() : asset_word(true)) . ' moved.');
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

    audit('export', 'asset', null, 'Exported ' . count($rows) . ' ' . asset_word(true) . ' to CSV');

    $columns = [
        asset_word(false, true) . ' tag'        => static fn (array $r) => $r['asset_tag'],
        'Name'             => static fn (array $r) => $r['name'],
        'Category'         => static fn (array $r) => $r['category'],
        'Location'         => static fn (array $r) => $r['location'],
        'Status'           => static fn (array $r) => Status::label((string) $r['status'], 'asset'),
        'Criticality'      => static fn (array $r) => Status::label((string) $r['criticality'], 'criticality'),
        'Manufacturer'     => static fn (array $r) => $r['manufacturer'],
        'Model'            => static fn (array $r) => $r['model'],
        'Serial number'    => static fn (array $r) => $r['serial_number'],
        'VIN'              => static fn (array $r) => $r['vin'],
        'Year'             => static fn (array $r) => $r['year_manufactured'],
        'Purchase date'    => static fn (array $r) => $r['purchase_date'],
        'Purchase cost'    => static fn (array $r) => $r['purchase_cost'],
        'Warranty expires' => static fn (array $r) => $r['warranty_expires'],
        'Meter type'       => static fn (array $r) => $r['meter_type'] === 'none' ? '' : $r['meter_type'],
        'Meter reading'    => static fn (array $r) => $r['meter_type'] === 'none' ? '' : $r['meter_reading'],
        'In service since' => static fn (array $r) => $r['in_service_date'],
        'Last service'     => static fn (array $r) => $r['last_service'] === null ? '' : Dates::date((string) $r['last_service'], ''),
        'Lifetime maintenance cost' => static fn (array $r) => $r['lifetime_cost'],
        'Notes'            => static fn (array $r) => $r['notes'],
    ];

    // The extra fields from Settings → Fields, after the built-in columns.
    foreach (\App\CustomFields::all() as $field) {
        $heading = (string) $field['label'];

        while (isset($columns[$heading])) {
            $heading .= ' (extra)';
        }

        $columns[$heading] = static fn (array $r) => \App\CustomFields::valueOn($field, $r);
    }

    if (!costs_visible()) {
        unset($columns['Purchase cost'], $columns['Lifetime maintenance cost']);
    }

    if (!feature_on('meters')) {
        unset($columns['Meter type'], $columns['Meter reading']);
    }

    Csv::stream(
        Csv::filename('assets'),
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
        . icon('plus', '', 17) . ' Add ' . asset_word() . '</a>';
}

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('assets.php', array_merge($_GET, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

if (feature_on('labels')) {
    $actions .= '<a class="btn btn-secondary" href="' . e(url('labels.php')) . '">'
        . icon('qr-code', '', 17) . ' Print labels</a>';
}

View::render('assets/index', [
    'title'        => asset_word(true, true),
    'subtitle'     => 'Every kart, ride and ' . asset_word() . ' you look after',
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
