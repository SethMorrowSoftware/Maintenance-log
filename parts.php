<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Csv;
use App\Models\Part;
use App\Paginator;
use App\Request;
use App\Settings;
use App\View;

Auth::requireLogin();
Acl::requirePermission('parts.view');

// -----------------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');
    $id     = Request::int('id');

    if ($action === 'delete') {
        Acl::requirePermission('parts.manage');

        if (Part::delete($id)) {
            flash('success', 'Part removed from the list. Past maintenance logs still show it.');
        } else {
            flash('error', 'That part could not be found.');
        }
    }

    // The quick "took some" / "put some back" control on the list.
    if ($action === 'adjust') {
        Acl::requirePermission('parts.adjust');

        $part   = Part::find($id);
        $amount = (float) str_replace(',', '', Request::string('amount'));
        $way    = Request::string('way');

        $tooMany = $part !== null && $way === 'out' ? Part::cannotTake($part, $amount) : null;

        if ($part === null) {
            flash('error', 'That part could not be found.');
        } elseif ($amount <= 0) {
            flash('error', 'Type how many you took or put back.');
        } elseif ($tooMany !== null) {
            flash('error', $tooMany);
        } else {
            $delta  = $way === 'out' ? -$amount : $amount;
            $result = Part::adjustStock(
                $id,
                $delta,
                $way === 'out' ? 'out' : 'in',
                'manual',
                null,
                Request::string('notes')
            );

            audit('stock.adjust', 'part', $id,
                (string) $part['name'] . ': ' . ($delta > 0 ? '+' : '') . decimal($delta)
                . ' → ' . decimal($result['balance']) . ' on hand');

            flash('success', (string) $part['name'] . ' is now '
                . decimal($result['balance']) . ' ' . (string) $part['unit_of_measure'] . ' on hand.');
        }
    }

    redirect(url('parts.php', $_GET));
}

// -----------------------------------------------------------------------------
// Filters
// -----------------------------------------------------------------------------

$filters = [
    'q'        => Request::string('q'),
    'category' => Request::string('category'),
    'stock'    => Request::string('stock'),
    'active'   => Request::string('active', '1'),
];

$sort      = Request::string('sort', 'name');
$direction = Request::string('dir', 'asc');

if (!isset(Part::SORTS[$sort])) {
    $sort = 'name';
}

// -----------------------------------------------------------------------------
// CSV export
// -----------------------------------------------------------------------------

if (Request::string('export') === 'csv') {
    Acl::requirePermission('reports.export');

    $rows = Part::forExport($filters);
    audit('export', 'part', null, 'Exported ' . count($rows) . ' parts to CSV');

    $columns = [
        'Part number'          => static fn (array $r) => $r['part_number'],
        'Name'                 => static fn (array $r) => $r['name'],
        'Description'          => static fn (array $r) => $r['description'],
        'Category'             => static fn (array $r) => $r['category'],
        'Manufacturer'         => static fn (array $r) => $r['manufacturer'],
        'Supplier'             => static fn (array $r) => $r['supplier'],
        'Supplier part number' => static fn (array $r) => $r['supplier_part_number'],
        'Unit cost'            => static fn (array $r) => $r['unit_cost'],
        'Unit'                 => static fn (array $r) => $r['unit_of_measure'],
        'On hand'              => static fn (array $r) => $r['quantity_on_hand'],
        'Reorder at'           => static fn (array $r) => $r['reorder_level'],
        'Reorder quantity'     => static fn (array $r) => $r['reorder_quantity'],
        'Stock value'          => static fn (array $r) => $r['stock_value'],
        'Bin'                  => static fn (array $r) => $r['location_bin'],
        'Notes'                => static fn (array $r) => $r['notes'],
    ];

    if (!costs_visible()) {
        unset($columns['Unit cost'], $columns['Stock value']);
    }

    Csv::stream(
        Csv::filename('parts'),
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

$total     = Part::count($filters);
$paginator = Paginator::fromRequest($total, null, 'parts.php');
$parts     = Part::paginate($filters, $sort, $direction, $paginator->limit(), $paginator->offset());

$actions = '';

if (can('parts.manage')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('part-edit.php')) . '">'
        . icon('plus', '', 17) . ' Add part</a>';
}

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('parts.php', array_merge($_GET, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

View::render('parts/index', [
    'title'       => 'Parts',
    'subtitle'    => 'What is on the shelf, what is running out',
    'activeNav'   => 'parts.php',
    'pageActions' => $actions,
    'parts'       => $parts,
    'paginator'   => $paginator,
    'filters'     => $filters,
    'sort'        => $sort,
    'direction'   => $direction,
    'categories'  => Part::categoryOptions(),
    'summary'     => Part::summary(),
    'currency'    => Settings::currency(),
]);
