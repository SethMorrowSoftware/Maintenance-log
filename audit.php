<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Audit;
use App\Auth;
use App\Csv;
use App\Dates;
use App\Paginator;
use App\Request;
use App\View;

Auth::requireLogin();
Acl::requirePermission('audit.view');

$filters = [
    'q'           => Request::string('q'),
    'user_id'     => Request::int('user_id'),
    'action'      => Request::string('action'),
    'entity_type' => Request::string('entity_type'),
    'from'        => Request::string('from'),
    'to'          => Request::string('to'),
];

$where  = ['1'];
$params = [];

if ($filters['q'] !== '') {
    $like     = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['q']) . '%';
    $where[]  = '(a.description LIKE ? OR a.user_name LIKE ?)';
    array_push($params, $like, $like);
}

if ($filters['user_id'] > 0) {
    $where[]  = 'a.user_id = ?';
    $params[] = $filters['user_id'];
}

if ($filters['action'] !== '') {
    $where[]  = 'a.action = ?';
    $params[] = $filters['action'];
}

if ($filters['entity_type'] !== '') {
    $where[]  = 'a.entity_type = ?';
    $params[] = $filters['entity_type'];
}

[$fromUtc, $toUtc] = Dates::rangeToUtc($filters['from'], $filters['to']);

if ($fromUtc !== null) {
    $where[]  = 'a.created_at >= ?';
    $params[] = $fromUtc;
}

if ($toUtc !== null) {
    $where[]  = 'a.created_at < ?';
    $params[] = $toUtc;
}

$whereSql = implode(' AND ', $where);

// -----------------------------------------------------------------------------
// Export
// -----------------------------------------------------------------------------

if (Request::string('export') === 'csv') {
    Acl::requirePermission('reports.export');

    $rows = db()->all(
        "SELECT a.created_at, a.user_name, a.action, a.entity_type, a.entity_id,
                a.description, a.ip_address
         FROM {audit_log} a WHERE {$whereSql}
         ORDER BY a.created_at DESC, a.id DESC LIMIT 20000",
        $params
    );

    audit('export', 'audit', null, 'Exported ' . count($rows) . ' audit entries to CSV');

    Csv::stream(
        Csv::filename('audit-log'),
        ['When', 'Who', 'What', 'Record type', 'Record', 'Detail', 'IP address'],
        $rows,
        static function (array $row): array {
            return [
                Dates::datetime((string) $row['created_at'], ''),
                $row['user_name'],
                Audit::actionLabel((string) $row['action']),
                $row['entity_type'],
                $row['entity_id'],
                $row['description'],
                $row['ip_address'],
            ];
        }
    );
}

// -----------------------------------------------------------------------------
// Page
// -----------------------------------------------------------------------------

$total     = db()->count("SELECT COUNT(*) FROM {audit_log} a WHERE {$whereSql}", $params);
$paginator = Paginator::fromRequest($total, null, 'audit.php');

$entries = db()->all(
    "SELECT a.*, u.first_name, u.last_name, u.username, u.avatar_path
     FROM {audit_log} a
     LEFT JOIN {users} u ON u.id = a.user_id
     WHERE {$whereSql}
     ORDER BY a.created_at DESC, a.id DESC
     LIMIT " . $paginator->limit() . ' OFFSET ' . $paginator->offset(),
    $params
);

$actions = '';

if (can('reports.export')) {
    $actions .= '<a class="btn btn-secondary" href="'
        . e(url('audit.php', array_merge($_GET, ['export' => 'csv']))) . '">'
        . icon('download', '', 17) . ' Export</a>';
}

$actionOptions = [];

foreach (Audit::knownActions() as $action) {
    $actionOptions[$action] = Audit::actionLabel($action);
}

asort($actionOptions);

$entityOptions = [];

foreach (Audit::knownEntityTypes() as $entity) {
    $entityOptions[$entity] = ucfirst(str_replace('_', ' ', $entity));
}

View::render('audit/index', [
    'title'       => 'Audit Log',
    'subtitle'    => 'Who changed what, and when',
    'activeNav'   => 'audit.php',
    'pageActions' => $actions,
    'entries'     => $entries,
    'paginator'   => $paginator,
    'filters'     => $filters,
    'actions'     => $actionOptions,
    'entities'    => $entityOptions,
    'people'      => db()->pairs(
        "SELECT id, TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')))
         FROM {users} ORDER BY first_name, last_name"
    ),
    'retentionDays' => \App\Settings::int('audit_retention_days', 365),
]);
