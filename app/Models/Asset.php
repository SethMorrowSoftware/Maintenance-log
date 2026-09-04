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
 * Assets: the karts, rides and machines being maintained.
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
     * One asset with its category and location names.
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
     * Look an asset up by its tag, or by the slug in a QR code.
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
     * Count assets matching the filters.
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
     * A page of assets, each with its last service date and open work order count.
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
     * Every asset, for a dropdown.
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
     * Create an asset.
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

        Audit::created('asset', $id, 'Added asset "' . (string) $data['name'] . '"', $data);

        // A first meter reading is history too.
        if (!empty($data['meter_reading']) && (float) $data['meter_reading'] > 0) {
            self::recordMeterReading($id, (float) $data['meter_reading'], 'import', null, 'Initial reading');
        }

        return $id;
    }

    /**
     * Update an asset, auditing what changed.
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

        Audit::updated('asset', $id, 'Updated asset "' . (string) $before['name'] . '"', $before, $data);

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
     * Change status on its own, from a list row or the asset page.
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

        Audit::deleted('asset', $id, 'Deleted asset "' . (string) $asset['name'] . '"', [
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
        Audit::record('restore', 'asset', $id, 'Restored asset "' . (string) $asset['name'] . '"');

        return true;
    }

    // -------------------------------------------------------------------------
    // Meters
    // -------------------------------------------------------------------------

    /**
     * Record a meter reading and move the asset's current value.
     *
     * @return array{ok: bool, error: string}
     */
    public static function updateMeter(int $id, float $reading, string $notes = '', string $source = 'manual', ?int $referenceId = null): array
    {
        $asset = self::find($id);

        if ($asset === null) {
            return ['ok' => false, 'error' => 'That asset does not exist.'];
        }

        if ((string) $asset['meter_type'] === 'none') {
            return ['ok' => false, 'error' => 'This asset does not have a meter.'];
        }

        if ($reading < 0) {
            return ['ok' => false, 'error' => 'A meter reading cannot be negative.'];
        }

        $previous = (float) $asset['meter_reading'];

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
    // Related records
    // -------------------------------------------------------------------------

    /**
     * Everything shown on an asset's page, counted for the tab badges.
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
     * Headline numbers for one asset: what it has cost and how often it breaks.
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

    /** A short random slug for the QR code, unique across assets. */
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
     * Suggest the next asset tag in a series, so adding kart nine after kart
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
