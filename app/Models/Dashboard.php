<?php

declare(strict_types=1);

namespace App\Models;

use App\Auth;
use App\Dates;
use App\Scheduler;
use App\Settings;
use Throwable;

/**
 * Every query the dashboard needs, in one place.
 *
 * The dashboard is the page opened most often and by the widest range of
 * people, so each method is written to answer one question with one round
 * trip and to degrade to something sensible rather than throw.
 */
final class Dashboard
{
    private function __construct()
    {
    }

    /**
     * The headline counts.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $assets = db()->one(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'in_service')     AS in_service,
                SUM(status = 'maintenance')    AS maintenance,
                SUM(status = 'out_of_service') AS out_of_service,
                SUM(status = 'retired')        AS retired
             FROM {assets}
             WHERE deleted_at IS NULL"
        ) ?? [];

        $workOrders = db()->one(
            "SELECT
                COUNT(*) AS total,
                SUM(status IN ('open','assigned','in_progress','on_hold')) AS open,
                SUM(priority = 'urgent' AND status NOT IN ('completed','cancelled')) AS urgent
             FROM {work_orders}
             WHERE deleted_at IS NULL"
        ) ?? [];

        return [
            'assets_total'      => (int) ($assets['total'] ?? 0),
            'assets_in_service' => (int) ($assets['in_service'] ?? 0),
            'assets_down'       => (int) ($assets['maintenance'] ?? 0) + (int) ($assets['out_of_service'] ?? 0),
            'assets_retired'    => (int) ($assets['retired'] ?? 0),
            'wo_open'           => (int) ($workOrders['open'] ?? 0),
            'wo_urgent'         => (int) ($workOrders['urgent'] ?? 0),
            'logs_30d'          => self::logsInLastDays(30),
            'logs_7d'           => self::logsInLastDays(7),
        ];
    }

    private static function logsInLastDays(int $days): int
    {
        return db()->count(
            'SELECT COUNT(*) FROM {maintenance_logs}
             WHERE deleted_at IS NULL AND performed_at >= ?',
            [gmdate(Dates::DB_FORMAT, time() - ($days * 86400))]
        );
    }

    /**
     * Machine counts by status, shaped for the donut chart.
     *
     * @return list<array{label: string, value: int, color: string}>
     */
    public static function statusBreakdown(): array
    {
        $rows = db()->all(
            'SELECT status, COUNT(*) AS n FROM {assets} WHERE deleted_at IS NULL GROUP BY status'
        );

        $colors = [
            'in_service'     => '#16a34a',
            'maintenance'    => '#f59e0b',
            'out_of_service' => '#dc2626',
            'retired'        => '#94a3b8',
        ];

        $order = ['in_service', 'maintenance', 'out_of_service', 'retired'];
        $byKey = [];

        foreach ($rows as $row) {
            $byKey[(string) $row['status']] = (int) $row['n'];
        }

        $out = [];

        foreach ($order as $status) {
            if (!isset($byKey[$status]) || $byKey[$status] === 0) {
                continue;
            }

            $out[] = [
                'label' => \App\Status::label($status, 'asset'),
                'value' => $byKey[$status],
                'color' => $colors[$status],
            ];
        }

        return $out;
    }

    /**
     * Maintenance logs per month for the last N months, split by type group.
     *
     * @return array{labels: list<string>, series: list<array{name: string, values: list<int>}>}
     */
    public static function logsByMonth(int $months = 12): array
    {
        $range  = Dates::monthRange($months);
        $labels = [];
        $buckets = [];

        foreach ($range as $month) {
            $labels[] = $month['short'] === 'Jan' ? $month['label'] : $month['short'];
            $buckets[$month['key']] = ['preventive' => 0, 'corrective' => 0, 'other' => 0];
        }

        $first = $range[0]['start_utc'] ?? Dates::nowUtc();

        $rows = db()->all(
            "SELECT DATE_FORMAT(performed_at, '%Y-%m') AS ym, log_type, COUNT(*) AS n
             FROM {maintenance_logs}
             WHERE deleted_at IS NULL AND performed_at >= ?
             GROUP BY ym, log_type",
            [$first]
        );

        foreach ($rows as $row) {
            $key = (string) $row['ym'];

            if (!isset($buckets[$key])) {
                continue;
            }

            $type  = (string) $row['log_type'];
            $group = in_array($type, ['preventive', 'inspection', 'daily_check', 'cleaning'], true)
                ? 'preventive'
                : (in_array($type, ['corrective', 'repair', 'safety'], true) ? 'corrective' : 'other');

            $buckets[$key][$group] += (int) $row['n'];
        }

        $planned    = [];
        $unplanned  = [];
        $other      = [];

        foreach ($buckets as $counts) {
            $planned[]   = $counts['preventive'];
            $unplanned[] = $counts['corrective'];
            $other[]     = $counts['other'];
        }

        $series = [
            ['name' => 'Planned', 'values' => $planned],
            ['name' => 'Unplanned', 'values' => $unplanned],
        ];

        if (array_sum($other) > 0) {
            $series[] = ['name' => 'Other', 'values' => $other];
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * Maintenance spend per month.
     *
     * @return array{labels: list<string>, series: list<array{name: string, values: list<float>}>}
     */
    public static function costByMonth(int $months = 12): array
    {
        $range   = Dates::monthRange($months);
        $labels  = [];
        $buckets = [];

        foreach ($range as $month) {
            $labels[] = $month['short'] === 'Jan' ? $month['label'] : $month['short'];
            $buckets[$month['key']] = 0.0;
        }

        $first = $range[0]['start_utc'] ?? Dates::nowUtc();

        $rows = db()->all(
            "SELECT DATE_FORMAT(performed_at, '%Y-%m') AS ym, SUM(total_cost) AS total
             FROM {maintenance_logs}
             WHERE deleted_at IS NULL AND performed_at >= ?
             GROUP BY ym",
            [$first]
        );

        foreach ($rows as $row) {
            $key = (string) $row['ym'];

            if (isset($buckets[$key])) {
                $buckets[$key] = round((float) $row['total'], 2);
            }
        }

        return [
            'labels' => $labels,
            'series' => [['name' => 'Maintenance cost', 'values' => array_values($buckets)]],
        ];
    }

    /**
     * Machines that are not earning: down or in the shop.
     *
     * @return list<array<string, mixed>>
     */
    public static function assetsDown(int $limit = 8): array
    {
        return db()->all(
            "SELECT a.id, a.name, a.asset_tag, a.status, a.updated_at,
                    c.name AS category_name, l.name AS location_name
             FROM {assets} a
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} l ON l.id = a.location_id
             WHERE a.deleted_at IS NULL
               AND a.status IN ('out_of_service', 'maintenance')
             ORDER BY FIELD(a.status, 'out_of_service', 'maintenance'), a.name ASC
             LIMIT " . max(1, min(50, $limit))
        );
    }

    /**
     * Open work orders, most urgent first.
     *
     * @return list<array<string, mixed>>
     */
    public static function openWorkOrders(int $limit = 8, ?int $assignedTo = null): array
    {
        $params = [];
        $sql = "SELECT w.id, w.wo_number, w.title, w.status, w.priority, w.due_date, w.created_at,
                       a.name AS asset_name, a.asset_tag,
                       u.first_name, u.last_name, u.username, u.avatar_path, u.id AS assignee_id
                FROM {work_orders} w
                LEFT JOIN {assets} a ON a.id = w.asset_id
                LEFT JOIN {users} u ON u.id = w.assigned_to
                WHERE w.deleted_at IS NULL
                  AND w.status NOT IN ('completed', 'cancelled')";

        if ($assignedTo !== null) {
            $sql .= ' AND w.assigned_to = ?';
            $params[] = $assignedTo;
        }

        $sql .= " ORDER BY FIELD(w.priority, 'urgent', 'high', 'normal', 'low'),
                          w.due_date IS NULL, w.due_date ASC, w.created_at ASC
                  LIMIT " . max(1, min(50, $limit));

        return db()->all($sql, $params);
    }

    /**
     * Maintenance falling due, most urgent first.
     *
     * @return list<array<string, mixed>>
     */
    public static function dueMaintenance(int $limit = 10): array
    {
        $window = Settings::int('dashboard_pm_lookahead_days', 14, 0, 365);

        return Scheduler::due($window, null, $limit);
    }

    /**
     * Today's checks that are still to do, for the person looking: one row
     * per machine (or per area, for an area checklist) that has a check
     * expected today and no finished run yet. Somebody limited to an area
     * sees only theirs.
     *
     * @return list<array<string, mixed>>
     */
    public static function inspectionsDueToday(int $limit = 12): array
    {
        try {
            $rows = \App\Checks::occurrences(Dates::today(), Auth::user());
        } catch (Throwable $e) {
            log_error('Dashboard inspection query failed: ' . $e->getMessage());

            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            if (in_array((string) $row['status'], ['done', 'late'], true)) {
                continue;
            }

            $checklist = $row['checklist'];
            $asset     = $row['asset'];

            $out[] = [
                'id'             => $asset === null ? null : (int) $asset['id'],
                'name'           => $asset === null ? (string) ($checklist['location_name'] ?? 'Area') : (string) $asset['name'],
                'asset_tag'      => $asset === null ? '' : (string) $asset['asset_tag'],
                'checklist_id'   => (int) $checklist['id'],
                'checklist_name' => (string) $checklist['name'],
                'location_id'    => $asset === null ? (int) ($checklist['location_id'] ?? 0) : null,
                'due_time'       => $checklist['due_time'] ?? null,
                'status'         => (string) $row['status'],
                'inspection_id'  => $row['inspection'] === null ? null : (int) $row['inspection']['id'],
            ];
        }

        return array_slice($out, 0, max(1, min(50, $limit)));
    }

    /**
     * The most recent maintenance logs.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentLogs(int $limit = 8): array
    {
        return db()->all(
            'SELECT l.id, l.title, l.log_type, l.performed_at, l.total_cost,
                    a.id AS asset_id, a.name AS asset_name, a.asset_tag,
                    u.id AS user_id, u.first_name, u.last_name, u.username, u.avatar_path
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE l.deleted_at IS NULL
             ORDER BY l.performed_at DESC, l.id DESC
             LIMIT ' . max(1, min(50, $limit))
        );
    }

    /**
     * Parts at or below their reorder level.
     *
     * @return list<array<string, mixed>>
     */
    public static function lowStock(int $limit = 6): array
    {
        return db()->all(
            'SELECT id, part_number, name, quantity_on_hand, reorder_level, unit_of_measure
             FROM {parts}
             WHERE deleted_at IS NULL
               AND is_active = 1
               AND reorder_level > 0
               AND quantity_on_hand <= reorder_level
             ORDER BY (quantity_on_hand / reorder_level) ASC, name ASC
             LIMIT ' . max(1, min(50, $limit))
        );
    }

    /**
     * Logs that were flagged as needing a follow-up and have not had one.
     *
     * @return list<array<string, mixed>>
     */
    public static function followUps(int $limit = 6): array
    {
        return db()->all(
            'SELECT l.id, l.title, l.followup_notes, l.performed_at,
                    a.name AS asset_name, a.asset_tag
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             WHERE l.deleted_at IS NULL
               AND l.requires_followup = 1
             ORDER BY l.performed_at DESC
             LIMIT ' . max(1, min(50, $limit))
        );
    }

    /**
     * Total maintenance spend over a window, and the window before it, so the
     * dashboard can show a direction of travel.
     *
     * @return array{current: float, previous: float, change_pct: float|null}
     */
    public static function costTrend(int $days = 30): array
    {
        $now      = time();
        $thisFrom = gmdate(Dates::DB_FORMAT, $now - ($days * 86400));
        $prevFrom = gmdate(Dates::DB_FORMAT, $now - ($days * 2 * 86400));

        $current = (float) db()->value(
            'SELECT COALESCE(SUM(total_cost), 0) FROM {maintenance_logs}
             WHERE deleted_at IS NULL AND performed_at >= ?',
            [$thisFrom],
            0
        );

        $previous = (float) db()->value(
            'SELECT COALESCE(SUM(total_cost), 0) FROM {maintenance_logs}
             WHERE deleted_at IS NULL AND performed_at >= ? AND performed_at < ?',
            [$prevFrom, $thisFrom],
            0
        );

        $change = null;

        if ($previous > 0.009) {
            $change = round((($current - $previous) / $previous) * 100, 1);
        }

        return ['current' => $current, 'previous' => $previous, 'change_pct' => $change];
    }

    /**
     * Downtime minutes recorded over a window.
     */
    public static function downtimeMinutes(int $days = 30): int
    {
        return (int) db()->value(
            'SELECT COALESCE(SUM(downtime_minutes), 0) FROM {maintenance_logs}
             WHERE deleted_at IS NULL AND performed_at >= ?',
            [gmdate(Dates::DB_FORMAT, time() - ($days * 86400))],
            0
        );
    }

    /**
     * Work assigned to the person looking at the screen.
     *
     * @return array{work_orders: list<array<string, mixed>>, count: int}
     */
    public static function myWork(int $limit = 5): array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return ['work_orders' => [], 'count' => 0];
        }

        return [
            'work_orders' => self::openWorkOrders($limit, $userId),
            'count'       => db()->count(
                "SELECT COUNT(*) FROM {work_orders}
                 WHERE deleted_at IS NULL AND assigned_to = ?
                   AND status NOT IN ('completed','cancelled')",
                [$userId]
            ),
        ];
    }
}
