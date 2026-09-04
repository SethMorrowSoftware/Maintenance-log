<?php

declare(strict_types=1);

/**
 * Full search results.
 *
 * The box in the header covers "I know roughly what I want and I want it now".
 * This page is for the other case: everything that mentions a phrase, with
 * enough of each result to tell them apart.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Dates;
use App\Request;
use App\Status;
use App\Str;
use App\View;

Auth::requireLogin();

$query   = trim(Request::string('q'));
$results = [];
$total   = 0;

if (mb_strlen($query) >= 2) {
    $like = Str::likeContains($query);

    if (can('assets.view')) {
        $results['Assets'] = db()->all(
            "SELECT a.id, a.name, a.asset_tag, a.status, a.model, a.manufacturer,
                    c.name AS category_name, loc.name AS location_name
             FROM {assets} a
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} loc ON loc.id = a.location_id
             WHERE a.deleted_at IS NULL
               AND (a.name LIKE ? OR a.asset_tag LIKE ? OR a.serial_number LIKE ?
                    OR a.model LIKE ? OR a.manufacturer LIKE ? OR a.vin LIKE ?
                    OR a.notes LIKE ?)
             ORDER BY a.name LIMIT 40",
            array_fill(0, 7, $like)
        );
    }

    if (can('logs.view')) {
        $results['Maintenance logs'] = db()->all(
            "SELECT l.id, l.title, l.description, l.performed_at, l.log_type, l.total_cost,
                    a.name AS asset_name, a.asset_tag,
                    TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS technician
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE l.deleted_at IS NULL
               AND (l.title LIKE ? OR l.description LIKE ? OR l.work_performed LIKE ?)
             ORDER BY l.performed_at DESC LIMIT 40",
            [$like, $like, $like]
        );
    }

    if (can('workorders.view')) {
        $results['Work orders'] = db()->all(
            "SELECT w.id, w.wo_number, w.title, w.description, w.status, w.priority, w.created_at,
                    a.name AS asset_name
             FROM {work_orders} w
             LEFT JOIN {assets} a ON a.id = w.asset_id
             WHERE w.deleted_at IS NULL
               AND (w.title LIKE ? OR w.description LIKE ? OR w.wo_number LIKE ?
                    OR w.resolution LIKE ?)
             ORDER BY w.created_at DESC LIMIT 40",
            [$like, $like, $like, $like]
        );
    }

    if (can('parts.view')) {
        $results['Parts'] = db()->all(
            "SELECT p.id, p.name, p.part_number, p.description, p.category,
                    p.quantity_on_hand, p.reorder_level, p.unit_of_measure, p.supplier
             FROM {parts} p
             WHERE p.deleted_at IS NULL
               AND (p.name LIKE ? OR p.part_number LIKE ? OR p.description LIKE ?
                    OR p.supplier LIKE ? OR p.supplier_part_number LIKE ?)
             ORDER BY p.name LIMIT 40",
            array_fill(0, 5, $like)
        );
    }

    if (can('inspections.view')) {
        $results['Inspections'] = db()->all(
            "SELECT i.id, i.checklist_name, i.status, i.started_at, i.notes,
                    a.name AS asset_name
             FROM {inspections} i
             INNER JOIN {assets} a ON a.id = i.asset_id
             WHERE (i.checklist_name LIKE ? OR i.notes LIKE ? OR i.signature_name LIKE ?)
             ORDER BY i.started_at DESC LIMIT 20",
            [$like, $like, $like]
        );
    }

    foreach ($results as $rows) {
        $total += count($rows);
    }

    if ($total > 0) {
        audit('search', '', null, 'Searched for "' . Str::limit($query, 80) . '" (' . $total . ' results)');
    }
}

View::render('search', [
    'title'     => 'Search',
    'subtitle'  => $query === ''
        ? 'Everything in one place'
        : $total . ' result' . ($total === 1 ? '' : 's') . ' for “' . $query . '”',
    'activeNav' => '',
    'query'     => $query,
    'results'   => $results,
    'total'     => $total,
]);
