<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csv;
use App\Dates;
use App\Models\Asset;
use App\Reports;
use App\Request;
use App\Status;
use App\View;

Auth::requireLogin();
Acl::requirePermission('reports.view');

// -----------------------------------------------------------------------------
// Which report, over which period
// -----------------------------------------------------------------------------

$report = Request::string('report', 'history');

if (!isset(Reports::CATALOGUE[$report])) {
    $report = 'history';
}

$preset = Request::string('preset', 'last_90');
$from   = Request::string('from');
$to     = Request::string('to');

// A preset fills the dates in. Typing your own dates clears the preset, so the
// two controls never disagree about what is being shown.
if ($from === '' && $to === '') {
    [$from, $to] = Dates::preset($preset);
    $from = (string) $from;
    $to   = (string) $to;
} else {
    $preset = 'custom';
}

$filters = [
    'from'        => $from,
    'to'          => $to,
    'asset_id'    => Request::int('asset_id'),
    'category_id' => Request::int('category_id'),
    'location_id' => Request::int('location_id'),
    'log_type'    => Request::string('log_type'),
    'user_id'     => Request::int('user_id'),
    'status'      => Request::string('status'),
];

$result = Reports::run($report, $filters);

// -----------------------------------------------------------------------------
// CSV export
// -----------------------------------------------------------------------------

if (Request::string('export') === 'csv') {
    Acl::requirePermission('reports.export');

    audit('export', 'report', null,
        'Exported the "' . Reports::CATALOGUE[$report]['label'] . '" report ('
        . count($result['rows']) . ' rows)');

    $columns = $result['columns'];

    Csv::stream(
        Csv::filename('report-' . $report),
        array_column($columns, 'label'),
        $result['rows'],
        static function (array $row) use ($columns): array {
            $out = [];

            foreach ($columns as $column) {
                $value = $row[$column['key']] ?? '';

                // A spreadsheet should be able to add these up, so numbers go
                // out raw. Only the coded values get turned into words.
                switch ($column['format'] ?? 'text') {
                    case 'datetime':
                        $out[] = $value === '' || $value === null ? '' : Dates::datetime((string) $value, '');
                        break;
                    case 'date':
                        $out[] = $value === '' || $value === null ? '' : Dates::date((string) $value, '');
                        break;
                    case 'date_only':
                        $out[] = $value === '' || $value === null ? '' : Dates::dateOnly((string) $value, '');
                        break;
                    case 'log_type':
                        $out[] = Status::label((string) $value, 'log_type');
                        break;
                    case 'asset_status':
                        $out[] = Status::label((string) $value, 'asset');
                        break;
                    case 'role':
                        $out[] = Acl::roleLabel((string) $value);
                        break;
                    default:
                        $out[] = $value;
                }
            }

            return $out;
        }
    );
}

// -----------------------------------------------------------------------------
// Page
// -----------------------------------------------------------------------------

$printing = Request::bool('print');

// Only offer the filters a report actually uses, so nobody wonders why setting
// one changed nothing.
$usesDates = $report !== 'inventory';
$usesWho   = in_array($report, ['history'], true);
$usesType  = in_array($report, ['history'], true);
$usesState = $report === 'inventory';

$data = [
    'title'       => 'Reports',
    'subtitle'    => Reports::CATALOGUE[$report]['blurb'],
    'activeNav'   => 'reports.php',
    'report'      => $report,
    'catalogue'   => Reports::CATALOGUE,
    'result'      => $result,
    'filters'     => $filters,
    'preset'      => $preset,
    'usesDates'   => $usesDates,
    'usesWho'     => $usesWho,
    'usesType'    => $usesType,
    'usesState'   => $usesState,
    'assets'      => Asset::options(true),
    'categories'  => Asset::categoryOptions(),
    'locations'   => Asset::locationOptions(),
    'technicians' => db()->pairs(
        "SELECT id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')))
         FROM {users} WHERE deleted_at IS NULL ORDER BY first_name, last_name"
    ),
    'printing'    => $printing,
];

if ($printing) {
    $data['docTitle']  = Reports::CATALOGUE[$report]['label'];
    $data['printMeta'] = [
        'Period' => $from === '' && $to === ''
            ? 'All time'
            : trim(($from !== '' ? Dates::dateOnly($from) : 'the beginning')
                 . ' to ' . ($to !== '' ? Dates::dateOnly($to) : 'today')),
        'Rows'   => (string) count($result['rows']),
    ];

    View::render('reports/index', $data, 'layout-print');
    exit;
}

$query = array_filter([
    'report'      => $report,
    'from'        => $from,
    'to'          => $to,
    'asset_id'    => $filters['asset_id'],
    'category_id' => $filters['category_id'],
    'location_id' => $filters['location_id'],
    'log_type'    => $filters['log_type'],
    'user_id'     => $filters['user_id'],
    'status'      => $filters['status'],
], static function ($value): bool {
    return $value !== '' && $value !== 0;
});

$actions = '';

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('reports.php', array_merge($query, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

$actions .= '<a class="btn btn-secondary" href="'
    . e(url('reports.php', array_merge($query, ['print' => 1]))) . '">'
    . icon('printer', '', 17) . ' Print</a>';

$data['pageActions'] = $actions;

View::render('reports/index', $data);
