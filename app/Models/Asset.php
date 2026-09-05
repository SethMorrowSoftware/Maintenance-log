<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit;
use App\Auth;
use App\Dates;
use App\Scheduler;
use App\Str;
use App\Uploader;
use Throwable;

/**
 * The karts, rides and other machines being maintained. Called "assets" in
 * the code and the database, "machines" everywhere a person sees them.
 */
final class Asset
{
    /** Columns a list may be sorted by, mapped to safe SQL. */
    public const SORTS = [
        'name'        => 'a.name',
        'tag'         => 'a.asset_tag',
        'category'    => 'c.name',
        'location'    => 'l.name',
        'status'      => 'a.status',
        'criticality' => "FIELD(a.criticality, 'critical', 'high', 'medium', 'low')",
        'meter'       => 'a.meter_reading',
        'last_service'=> 'last_service',
        'created'     => 'a.created_at',
    ];

    private function __construct()
    {
    }

    /**
     * One machine with its category and location names.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id, bool $withDeleted = false): ?array
    {
        $sql = 'SELECT a.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
                       l.name AS location_name,
                       cu.first_name AS created_first, cu.last_name AS created_last
                FROM {assets} a
                LEFT JOIN {asset_categories} c ON c.id = a.category_id
                LEFT JOIN {locations} l ON l.id = a.location_id
                LEFT JOIN {users} cu ON cu.id = a.created_by
                WHERE a.id = ?';

        if (!$withDeleted) {
            $sql .= ' AND a.deleted_at IS NULL';
        }

        return db()->one($sql . ' LIMIT 1', [$id]);
    }

    /**
     * Look a machine up by its tag, or by the slug in a QR code.
     *
     * @return array<string, mixed>|null
     */
    public static function findByTagOrSlug(string $value): ?array
    {
        return db()->one(
            'SELECT * FROM {assets} WHERE (asset_tag = ? OR qr_slug = ?) AND deleted_at IS NULL LIMIT 1',
            [$value, $value]
        );
    }

    /**
     * Build the WHERE clause for the list screen from the filter values.
     *
     * @param  array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private static function buildFilter(array $filters): array
    {
        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            // Every term must appear somewhere, so "kart brake" narrows down.
            foreach (Str::parseSearch($search, 4) as $term) {
                $like = Str::likeContains($term);
                $where[] = '(a.name LIKE ? OR a.asset_tag LIKE ? OR a.serial_number LIKE ?
                             OR a.manufacturer LIKE ? OR a.model LIKE ? OR a.vin LIKE ?)';
                array_push($params, $like, $like, $like, $like, $like, $like);
            }
        }

        foreach (['category_id' => 'a.category_id', 'location_id' => 'a.location_id'] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[]  = $column . ' = ?';
                $params[] = (int) $filters[$key];
            }
        }

        $status = (string) ($filters['status'] ?? '');

        if ($status === 'down') {
            // One click for "what is not running", which is the question
            // people actually ask.
            $where[] = "a.status IN ('out_of_service', 'maintenance')";
        } elseif ($status !== '' && $status !== 'all') {
            $where[]  = 'a.status = ?';
            $params[] = $status;
        } elseif ($status === '') {
            // Retired kit is history: hide it unless it is asked for.
            $where[] = "a.status <> 'retired'";
        }

        if (!empty($filters['criticality'])) {
            $where[]  = 'a.criticality = ?';
            $params[] = (string) $filters['criticality'];
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * Count machines matching the filters.
     *
     * @param array<string, mixed> $filters
     */
    public static function count(array $filters = []): int
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->count(
            'SELECT COUNT(*) FROM {assets} a
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} l ON l.id = a.location_id
             WHERE ' . $where,
            $params
        );
    }

    /**
     * A page of machines, each with its last service date and open work order count.
     *
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function paginate(array $filters, string $sort, string $direction, int $limit, int $offset): array
    {
        [$where, $params] = self::buildFilter($filters);

        $orderBy = self::SORTS[$sort] ?? self::SORTS['name'];
        $dir     = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return db()->all(
            "SELECT a.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
                    l.name AS location_name,
                    (SELECT MAX(performed_at) FROM {maintenance_logs} ml
                      WHERE ml.asset_id = a.id AND ml.deleted_at IS NULL) AS last_service,
                    (SELECT COUNT(*) FROM {work_orders} wo
                      WHERE wo.asset_id = a.id AND wo.deleted_at IS NULL
                        AND wo.status NOT IN ('completed','cancelled')) AS open_work_orders
             FROM {assets} a
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} l ON l.id = a.location_id
             WHERE {$where}
             ORDER BY {$orderBy} {$dir}, a.name ASC
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * Every machine, for a dropdown.
     *
     * @return list<array<string, mixed>>
     */
    public static function options(bool $includeRetired = false): array
    {
        $sql = "SELECT a.id, a.name, a.asset_tag, a.status, a.meter_type, a.meter_reading,
                       c.name AS category_name
                FROM {assets} a
                LEFT JOIN {asset_categories} c ON c.id = a.category_id
                WHERE a.deleted_at IS NULL";

        if (!$includeRetired) {
            $sql .= " AND a.status <> 'retired'";
        }

        return db()->all($sql . ' ORDER BY c.sort_order ASC, c.name ASC, a.sort_order ASC, a.name ASC');
    }

    /**
     * Create a machine.
     *
     * @param array<string, mixed> $data already validated
     */
    public static function create(array $data): int
    {
        $userId = Auth::id();

        $data['qr_slug']    = self::uniqueSlug();
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $data['created_at'] = Dates::nowUtc();

        if (!empty($data['meter_reading']) && empty($data['meter_updated_at'])) {
            $data['meter_updated_at'] = Dates::nowUtc();
        }

        $id = db()->insert('assets', $data);

        Audit::created('asset', $id, 'Added ' . asset_word() . ' "' . (string) $data['name'] . '"', $data);

        // A first meter reading is history too.
        if (!empty($data['meter_reading']) && (float) $data['meter_reading'] > 0) {
            self::recordMeterReading($id, (float) $data['meter_reading'], 'import', null, 'Initial reading');
        }

        return $id;
    }

    /**
     * Update a machine, auditing what changed.
     *
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $before = self::find($id);

        if ($before === null) {
            return;
        }

        $data['updated_by'] = Auth::id();

        db()->update('assets', $data, ['id' => $id]);

        Audit::updated('asset', $id, 'Updated ' . asset_word() . ' "' . (string) $before['name'] . '"', $before, $data);

        // A status change is the thing people most often want to look back on,
        // so it gets its own audit line in plain words.
        if (isset($data['status']) && (string) $data['status'] !== (string) $before['status']) {
            Audit::record(
                'status.change',
                'asset',
                $id,
                (string) $before['name'] . ' changed from '
                . \App\Status::label((string) $before['status'], 'asset') . ' to '
                . \App\Status::label((string) $data['status'], 'asset')
            );
        }
    }

    /**
     * Change status on its own, from a list row or the machine page.
     */
    public static function changeStatus(int $id, string $status, string $reason = ''): bool
    {
        $asset = self::find($id);

        if ($asset === null || !\App\Status::isValid($status, 'asset')) {
            return false;
        }

        if ((string) $asset['status'] === $status) {
            return true;
        }

        $data = ['status' => $status, 'updated_by' => Auth::id()];

        if ($status === 'retired' && empty($asset['retired_date'])) {
            $data['retired_date'] = Dates::today();
        }

        if ($status === 'in_service' && !empty($asset['retired_date'])) {
            $data['retired_date'] = null;
        }

        db()->update('assets', $data, ['id' => $id]);

        Audit::record(
            'status.change',
            'asset',
            $id,
            (string) $asset['name'] . ': '
            . \App\Status::label((string) $asset['status'], 'asset') . ' to '
            . \App\Status::label($status, 'asset')
            . ($reason !== '' ? ' — ' . $reason : '')
        );

        \App\Slack::statusChanged($asset, $status, $reason);

        return true;
    }

    /**
     * Soft delete. History is kept: the logs, inspections and work orders stay
     * in the database so a year-end report is still accurate.
     */
    public static function delete(int $id): bool
    {
        $asset = self::find($id);

        if ($asset === null) {
            return false;
        }

        db()->update('assets', [
            'deleted_at' => Dates::nowUtc(),
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        Audit::deleted('asset', $id, 'Deleted ' . asset_word() . ' "' . (string) $asset['name'] . '"', [
            'asset_tag' => $asset['asset_tag'],
            'name'      => $asset['name'],
        ]);

        return true;
    }

    public static function restore(int $id): bool
    {
        $asset = self::find($id, true);

        if ($asset === null || $asset['deleted_at'] === null) {
            return false;
        }

        db()->update('assets', ['deleted_at' => null, 'updated_by' => Auth::id()], ['id' => $id]);
        Audit::record('restore', 'asset', $id, 'Restored ' . asset_word() . ' "' . (string) $asset['name'] . '"');

        return true;
    }

    // -------------------------------------------------------------------------
    // Meters
    // -------------------------------------------------------------------------

    /**
     * Record a meter reading and move the machine's current value.
     *
     * @return array{ok: bool, error: string}
     */
    public static function updateMeter(
        int $id,
        float $reading,
        string $notes = '',
        string $source = 'manual',
        ?int $referenceId = null,
        bool $allowDecrease = false
    ): array {
        $asset = self::find($id);

        if ($asset === null) {
            return ['ok' => false, 'error' => 'That ' . asset_word() . ' does not exist.'];
        }

        if ((string) $asset['meter_type'] === 'none') {
            return ['ok' => false, 'error' => 'This ' . asset_word() . ' does not have a meter.'];
        }

        if ($reading < 0) {
            return ['ok' => false, 'error' => 'A meter reading cannot be negative.'];
        }

        $previous = (float) $asset['meter_reading'];

        // Hours and miles only go up. A lower number is nearly always a typo,
        // and letting it through would rewind every meter-based service due
        // date on the machine. Correcting a replaced meter is a deliberate act,
        // done on the machine itself.
        if (!$allowDecrease && $reading < $previous - 0.004) {
            return [
                'ok'    => false,
                'error' => 'That reading (' . decimal($reading) . ') is lower than the last one ('
                    . decimal($previous) . ' ' . (string) $asset['meter_type'] . '). '
                    . 'Check the number. If the meter was replaced or reset, change it on the ' . asset_word() . ' itself.',
            ];
        }

        self::recordMeterReading($id, $reading, $source, $referenceId, $notes, $previous);

        db()->update('assets', [
            'meter_reading'    => $reading,
            'meter_updated_at' => Dates::nowUtc(),
            'updated_by'       => Auth::id(),
        ], ['id' => $id]);

        Audit::record(
            'meter.update',
            'asset',
            $id,
            (string) $asset['name'] . ' meter: ' . decimal($previous) . ' to ' . decimal($reading)
            . ' ' . (string) $asset['meter_type']
        );

        // A meter-based service may have just come due.
        try {
            Scheduler::onMeterUpdated($id);
        } catch (Throwable $e) {
            log_error('Meter schedule check failed: ' . $e->getMessage());
        }

        return ['ok' => true, 'error' => ''];
    }

    private static function recordMeterReading(
        int $assetId,
        float $reading,
        string $source,
        ?int $referenceId,
        string $notes,
        ?float $previous = null
    ): void {
        try {
            db()->insert('meter_readings', [
                'asset_id'         => $assetId,
                'reading'          => $reading,
                'previous_reading' => $previous,
                'recorded_at'      => Dates::nowUtc(),
                'user_id'          => Auth::id(),
                'source'           => $source,
                'reference_id'     => $referenceId,
                'notes'            => mb_substr($notes, 0, 255, 'UTF-8'),
            ]);
        } catch (Throwable $e) {
            log_error('Meter reading insert failed: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function meterHistory(int $assetId, int $limit = 50): array
    {
        return db()->all(
            'SELECT m.*, u.first_name, u.last_name, u.username
             FROM {meter_readings} m
             LEFT JOIN {users} u ON u.id = m.user_id
             WHERE m.asset_id = ?
             ORDER BY m.recorded_at DESC, m.id DESC
             LIMIT ' . max(1, min(500, $limit)),
            [$assetId]
        );
    }

    // -------------------------------------------------------------------------
    // The full history of one machine
    // -------------------------------------------------------------------------

    /**
     * Everything that has ever happened to a machine, newest first, as one list.
     *
     * This is the troubleshooting view. Somebody standing at a kart with a
     * noise wants to see, in one place and in order: the last time it was in
     * the workshop, what was replaced, the check that failed last month, the
     * work order that closed, the day it went out of service and why. Five
     * tables hold those; this puts them on one line each.
     *
     * A search phrase narrows every kind at once — "brake" finds the job that
     * replaced the pads, the inspection line that failed, and the work order
     * that reported the squeal.
     *
     * @return list<array{when: string, kind: string, tone: string, icon: string,
     *                    title: string, detail: string, who: string, url: string}>
     */
    public static function timeline(int $assetId, string $search = '', int $limit = 300): array
    {
        $search = trim($search);
        $like   = $search === '' ? null : Str::likeContains($search);
        $events = [];

        // Maintenance logs, with the parts that went on.
        $sql = "SELECT l.id, l.title, l.log_type, l.performed_at, l.description, l.work_performed,
                       l.labor_hours, l.downtime_minutes, l.status_after, l.requires_followup,
                       TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS who,
                       (SELECT GROUP_CONCAT(CONCAT(mp.quantity, ' × ', mp.part_name) SEPARATOR ', ')
                          FROM {maintenance_log_parts} mp WHERE mp.log_id = l.id) AS parts_used
                FROM {maintenance_logs} l
                LEFT JOIN {users} u ON u.id = l.user_id
                WHERE l.asset_id = ? AND l.deleted_at IS NULL";
        $params = [$assetId];

        if ($like !== null) {
            $sql .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.work_performed LIKE ?
                          OR EXISTS (SELECT 1 FROM {maintenance_log_parts} mp
                                      WHERE mp.log_id = l.id AND mp.part_name LIKE ?))";
            array_push($params, $like, $like, $like, $like);
        }

        foreach (db()->all($sql . ' ORDER BY l.performed_at DESC LIMIT ' . (int) $limit, $params) as $row) {
            $bits = [];

            if ((string) ($row['work_performed'] ?? '') !== '') {
                $bits[] = Str::limit((string) $row['work_performed'], 220);
            } elseif ((string) ($row['description'] ?? '') !== '') {
                $bits[] = Str::limit((string) $row['description'], 220);
            }

            if ((string) ($row['parts_used'] ?? '') !== '') {
                $bits[] = 'Parts: ' . (string) $row['parts_used'];
            }

            $meta = [];

            if ((float) $row['labor_hours'] > 0) {
                $meta[] = Dates::humanHours((float) $row['labor_hours']);
            }

            if ((int) $row['downtime_minutes'] > 0) {
                $meta[] = 'out of service ' . Dates::humanDuration((int) $row['downtime_minutes']);
            }

            $events[] = [
                'when'   => (string) $row['performed_at'],
                'kind'   => 'log',
                'tone'   => \App\Status::tone((string) $row['log_type'], 'log_type'),
                'icon'   => 'wrench',
                'label'  => \App\Status::label((string) $row['log_type'], 'log_type'),
                'title'  => (string) $row['title'],
                'detail' => implode("\n", $bits),
                'meta'   => implode(' · ', $meta),
                'who'    => (string) $row['who'],
                'url'    => url('log-view.php', ['id' => (int) $row['id']]),
                'flag'   => (int) $row['requires_followup'] === 1 ? 'Needs follow-up' : '',
            ];
        }

        // Inspections, with whatever failed.
        $sql = "SELECT i.id, i.checklist_name, i.status, i.started_at, i.completed_at,
                       i.passed_count, i.failed_count, i.critical_failed, i.notes,
                       TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS who,
                       (SELECT GROUP_CONCAT(ii.item_text SEPARATOR '; ')
                          FROM {inspection_items} ii
                         WHERE ii.inspection_id = i.id AND ii.response IN ('fail','no')) AS failed_items
                FROM {inspections} i
                LEFT JOIN {users} u ON u.id = i.user_id
                WHERE i.asset_id = ? AND i.status <> 'in_progress'";
        $params = [$assetId];

        if ($like !== null) {
            $sql .= " AND (i.checklist_name LIKE ? OR i.notes LIKE ?
                          OR EXISTS (SELECT 1 FROM {inspection_items} ii
                                      WHERE ii.inspection_id = i.id
                                        AND (ii.item_text LIKE ? OR ii.notes LIKE ?)))";
            array_push($params, $like, $like, $like, $like);
        }

        foreach (db()->all($sql . ' ORDER BY i.started_at DESC LIMIT ' . (int) $limit, $params) as $row) {
            $failed = (int) $row['failed_count'];
            $detail = $failed > 0 && (string) ($row['failed_items'] ?? '') !== ''
                ? 'Failed: ' . Str::limit((string) $row['failed_items'], 240)
                : '';

            if ((string) ($row['notes'] ?? '') !== '') {
                $detail .= ($detail === '' ? '' : "\n") . Str::limit((string) $row['notes'], 160);
            }

            $events[] = [
                'when'   => (string) ($row['completed_at'] ?: $row['started_at']),
                'kind'   => 'inspection',
                'tone'   => $failed > 0 ? ((int) $row['critical_failed'] === 1 ? 'danger' : 'warn') : 'ok',
                'icon'   => 'clipboard-check',
                'label'  => $failed > 0 ? 'Inspection failed' : 'Inspection passed',
                'title'  => (string) $row['checklist_name'],
                'detail' => $detail,
                'meta'   => (int) $row['passed_count'] . ' passed' . ($failed > 0 ? ', ' . $failed . ' failed' : ''),
                'who'    => (string) $row['who'],
                'url'    => url('inspection-view.php', ['id' => (int) $row['id']]),
                'flag'   => (int) $row['critical_failed'] === 1 ? 'Safety-critical' : '',
            ];
        }

        // Work orders: raised, and closed if they have been.
        $sql = "SELECT w.id, w.wo_number, w.title, w.description, w.resolution, w.status, w.priority,
                       w.created_at, w.completed_at, w.is_safety_issue,
                       TRIM(CONCAT(COALESCE(r.first_name,''), ' ', COALESCE(r.last_name,''))) AS reporter,
                       TRIM(CONCAT(COALESCE(c.first_name,''), ' ', COALESCE(c.last_name,''))) AS closer
                FROM {work_orders} w
                LEFT JOIN {users} r ON r.id = w.reported_by
                LEFT JOIN {users} c ON c.id = w.closed_by
                WHERE w.asset_id = ? AND w.deleted_at IS NULL";
        $params = [$assetId];

        if ($like !== null) {
            $sql .= ' AND (w.title LIKE ? OR w.description LIKE ? OR w.resolution LIKE ? OR w.wo_number LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        foreach (db()->all($sql . ' ORDER BY w.created_at DESC LIMIT ' . (int) $limit, $params) as $row) {
            $url = url('workorder-view.php', ['id' => (int) $row['id']]);

            $events[] = [
                'when'   => (string) $row['created_at'],
                'kind'   => 'workorder',
                'tone'   => \App\Status::tone((string) $row['priority'], 'priority'),
                'icon'   => 'work-order',
                'label'  => 'Problem reported',
                'title'  => (string) $row['wo_number'] . ' — ' . (string) $row['title'],
                'detail' => Str::limit((string) ($row['description'] ?? ''), 220),
                'meta'   => \App\Status::label((string) $row['priority'], 'priority') . ' priority'
                    . (in_array((string) $row['status'], ['completed', 'cancelled'], true)
                        ? '' : ' · still ' . strtolower(\App\Status::label((string) $row['status'], 'workorder'))),
                'who'    => (string) $row['reporter'],
                'url'    => $url,
                'flag'   => (int) $row['is_safety_issue'] === 1 ? 'Safety' : '',
            ];

            if (!empty($row['completed_at'])) {
                $events[] = [
                    'when'   => (string) $row['completed_at'],
                    'kind'   => 'workorder_done',
                    'tone'   => 'ok',
                    'icon'   => 'check-circle',
                    'label'  => 'Problem ' . ((string) $row['status'] === 'cancelled' ? 'cancelled' : 'fixed'),
                    'title'  => (string) $row['wo_number'] . ' — ' . (string) $row['title'],
                    'detail' => Str::limit((string) ($row['resolution'] ?? ''), 220),
                    'meta'   => '',
                    'who'    => (string) $row['closer'],
                    'url'    => $url,
                    'flag'   => '',
                ];
            }
        }

        // Status changes come from the audit trail, which is the only place the
        // reason ("Failed inspection on…") is written down.
        $sql = "SELECT a.description, a.created_at, a.user_name
                FROM {audit_log} a
                WHERE a.entity_type = 'asset' AND a.entity_id = ? AND a.action = 'status.change'";
        $params = [$assetId];

        if ($like !== null) {
            $sql     .= ' AND a.description LIKE ?';
            $params[] = $like;
        }

        foreach (db()->all($sql . ' ORDER BY a.created_at DESC LIMIT ' . (int) $limit, $params) as $row) {
            // "Go-Kart #2: In service to Out of service — Failed inspection…"
            $text  = (string) $row['description'];
            $colon = strpos($text, ': ');
            $text  = $colon === false ? $text : substr($text, $colon + 2);
            $parts = explode(' — ', $text, 2);

            $events[] = [
                'when'   => (string) $row['created_at'],
                'kind'   => 'status',
                'tone'   => stripos($parts[0], 'to in service') !== false ? 'ok' : 'warn',
                'icon'   => 'activity',
                'label'  => 'Status changed',
                'title'  => $parts[0],
                'detail' => $parts[1] ?? '',
                'meta'   => '',
                'who'    => (string) $row['user_name'],
                'url'    => '',
                'flag'   => '',
            ];
        }

        // Meter readings typed in by hand. The ones taken on a job or a check
        // already show against that job or check.
        if ($like === null) {
            $rows = db()->all(
                "SELECT m.reading, m.previous_reading, m.recorded_at, m.notes,
                        TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS who
                 FROM {meter_readings} m
                 LEFT JOIN {users} u ON u.id = m.user_id
                 WHERE m.asset_id = ? AND m.source = 'manual'
                 ORDER BY m.recorded_at DESC LIMIT " . (int) $limit,
                [$assetId]
            );

            foreach ($rows as $row) {
                $events[] = [
                    'when'   => (string) $row['recorded_at'],
                    'kind'   => 'meter',
                    'tone'   => 'muted',
                    'icon'   => 'gauge',
                    'label'  => 'Meter reading',
                    'title'  => decimal($row['reading'])
                        . ($row['previous_reading'] !== null ? ' (was ' . decimal($row['previous_reading']) . ')' : ''),
                    'detail' => (string) ($row['notes'] ?? ''),
                    'meta'   => '',
                    'who'    => (string) $row['who'],
                    'url'    => '',
                    'flag'   => '',
                ];
            }
        }

        usort($events, static function (array $a, array $b): int {
            return strcmp($b['when'], $a['when']);
        });

        // Blank lines in a write-up would leave holes in the list.
        foreach ($events as &$event) {
            $event['detail'] = trim((string) preg_replace("/\n{2,}/", "\n", str_replace("\r", '', (string) $event['detail'])));
        }
        unset($event);

        return array_slice($events, 0, $limit);
    }

    // -------------------------------------------------------------------------
    // Related records
    // -------------------------------------------------------------------------

    /**
     * Everything shown on a machine's page, counted for the tab badges.
     *
     * @return array<string, int>
     */
    public static function relatedCounts(int $assetId): array
    {
        return [
            'logs' => db()->count(
                'SELECT COUNT(*) FROM {maintenance_logs} WHERE asset_id = ? AND deleted_at IS NULL',
                [$assetId]
            ),
            'schedules' => db()->count(
                'SELECT COUNT(*) FROM {maintenance_schedules} WHERE asset_id = ?',
                [$assetId]
            ),
            'inspections' => db()->count(
                'SELECT COUNT(*) FROM {inspections} WHERE asset_id = ?',
                [$assetId]
            ),
            'work_orders' => db()->count(
                'SELECT COUNT(*) FROM {work_orders} WHERE asset_id = ? AND deleted_at IS NULL',
                [$assetId]
            ),
            'attachments' => Uploader::countForEntity('asset', $assetId),
            'meter'       => db()->count('SELECT COUNT(*) FROM {meter_readings} WHERE asset_id = ?', [$assetId]),
        ];
    }

    /**
     * Headline numbers for one machine: what it has cost and how often it breaks.
     *
     * @return array<string, mixed>
     */
    public static function summary(int $assetId): array
    {
        $row = db()->one(
            "SELECT
                COUNT(*)                             AS log_count,
                COALESCE(SUM(total_cost), 0)         AS total_cost,
                COALESCE(SUM(labor_hours), 0)        AS total_hours,
                COALESCE(SUM(downtime_minutes), 0)   AS downtime_minutes,
                MAX(performed_at)                    AS last_service,
                SUM(log_type IN ('corrective','repair','safety')) AS unplanned
             FROM {maintenance_logs}
             WHERE asset_id = ? AND deleted_at IS NULL",
            [$assetId]
        ) ?? [];

        $twelveMonths = (float) db()->value(
            'SELECT COALESCE(SUM(total_cost), 0) FROM {maintenance_logs}
             WHERE asset_id = ? AND deleted_at IS NULL AND performed_at >= ?',
            [$assetId, gmdate(Dates::DB_FORMAT, time() - (365 * 86400))],
            0
        );

        return [
            'log_count'        => (int) ($row['log_count'] ?? 0),
            'total_cost'       => (float) ($row['total_cost'] ?? 0),
            'cost_12m'         => $twelveMonths,
            'total_hours'      => (float) ($row['total_hours'] ?? 0),
            'downtime_minutes' => (int) ($row['downtime_minutes'] ?? 0),
            'last_service'     => $row['last_service'] ?? null,
            'unplanned'        => (int) ($row['unplanned'] ?? 0),
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** A short random slug for the QR code, unique across machines. */
    private static function uniqueSlug(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $slug = 'a' . Str::random(16);

            if (!db()->exists('assets', ['qr_slug' => $slug])) {
                return $slug;
            }
        }

        return 'a' . Str::random(24);
    }

    /**
     * Suggest the next machine tag in a series, so adding kart nine after kart
     * eight does not mean typing GK-009 and getting it wrong.
     */
    public static function suggestTag(?int $categoryId): string
    {
        $prefix = 'AS';

        if ($categoryId !== null) {
            $category = db()->one('SELECT name FROM {asset_categories} WHERE id = ? LIMIT 1', [$categoryId]);

            if ($category !== null) {
                $words = preg_split('/[\s\-\/]+/', (string) $category['name']) ?: [];
                $prefix = '';

                foreach ($words as $word) {
                    if ($word !== '') {
                        $prefix .= strtoupper(substr($word, 0, 1));
                    }
                }

                $prefix = substr($prefix !== '' ? $prefix : 'AS', 0, 3);
            }
        }

        $last = db()->value(
            'SELECT asset_tag FROM {assets}
             WHERE asset_tag LIKE ?
             ORDER BY LENGTH(asset_tag) DESC, asset_tag DESC LIMIT 1',
            [$prefix . '-%']
        );

        $next = $last === null ? 1 : Str::sequenceNumber((string) $last) + 1;

        return Str::sequence($next, $prefix . '-', 3);
    }

    /**
     * Categories and locations, for filters and forms.
     *
     * @return array<int, string>
     */
    public static function categoryOptions(bool $activeOnly = true): array
    {
        $sql = 'SELECT id, name FROM {asset_categories}';

        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }

        return db()->pairs($sql . ' ORDER BY sort_order ASC, name ASC');
    }

    /**
     * @return array<int, string>
     */
    public static function locationOptions(bool $activeOnly = true): array
    {
        $sql = 'SELECT id, name FROM {locations}';

        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }

        return db()->pairs($sql . ' ORDER BY sort_order ASC, name ASC');
    }

    /**
     * Rows for the CSV export.
     *
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function forExport(array $filters): array
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->all(
            "SELECT a.asset_tag, a.name, c.name AS category, l.name AS location, a.status,
                    a.criticality, a.manufacturer, a.model, a.serial_number, a.vin,
                    a.year_manufactured, a.purchase_date, a.purchase_cost, a.warranty_expires,
                    a.meter_type, a.meter_reading, a.in_service_date, a.notes,
                    (SELECT MAX(performed_at) FROM {maintenance_logs} ml
                      WHERE ml.asset_id = a.id AND ml.deleted_at IS NULL) AS last_service,
                    (SELECT COALESCE(SUM(total_cost),0) FROM {maintenance_logs} ml
                      WHERE ml.asset_id = a.id AND ml.deleted_at IS NULL) AS lifetime_cost
             FROM {assets} a
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} l ON l.id = a.location_id
             WHERE {$where}
             ORDER BY a.name ASC",
            $params
        );
    }
}
