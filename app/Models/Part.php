<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit;
use App\Auth;
use App\Dates;
use App\Notifier;
use App\Status;
use App\Str;
use Throwable;

/**
 * Parts inventory.
 *
 * Stock is only ever changed through adjustStock(), which writes a transaction
 * row at the same time. That way quantity_on_hand is always explainable: every
 * movement has a reason, a person and a timestamp behind it.
 */
final class Part
{
    public const SORTS = [
        'name'     => 'p.name',
        'number'   => 'p.part_number',
        'category' => 'p.category',
        'stock'    => 'p.quantity_on_hand',
        'cost'     => 'p.unit_cost',
        'supplier' => 'p.supplier',
        'value'    => '(p.quantity_on_hand * p.unit_cost)',
    ];

    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return db()->one('SELECT * FROM {parts} WHERE id = ? AND deleted_at IS NULL LIMIT 1', [$id]);
    }

    /**
     * @param  array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private static function buildFilter(array $filters): array
    {
        $where  = ['p.deleted_at IS NULL'];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            foreach (Str::parseSearch($search, 3) as $term) {
                $like = Str::likeContains($term);
                $where[] = '(p.name LIKE ? OR p.part_number LIKE ? OR p.description LIKE ?
                             OR p.manufacturer LIKE ? OR p.supplier LIKE ?)';
                array_push($params, $like, $like, $like, $like, $like);
            }
        }

        if (!empty($filters['category'])) {
            $where[]  = 'p.category = ?';
            $params[] = (string) $filters['category'];
        }

        $stock = (string) ($filters['stock'] ?? '');

        if ($stock === 'low') {
            $where[] = 'p.reorder_level > 0 AND p.quantity_on_hand <= p.reorder_level';
        } elseif ($stock === 'out') {
            $where[] = 'p.quantity_on_hand <= 0';
        } elseif ($stock === 'in') {
            $where[] = 'p.quantity_on_hand > 0';
        }

        if (($filters['active'] ?? '1') !== 'all') {
            $where[] = 'p.is_active = 1';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public static function count(array $filters = []): int
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->count('SELECT COUNT(*) FROM {parts} p WHERE ' . $where, $params);
    }

    /**
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function paginate(array $filters, string $sort, string $direction, int $limit, int $offset): array
    {
        [$where, $params] = self::buildFilter($filters);

        $orderBy = self::SORTS[$sort] ?? self::SORTS['name'];
        $dir     = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return db()->all(
            "SELECT p.*, (p.quantity_on_hand * p.unit_cost) AS stock_value
             FROM {parts} p
             WHERE {$where}
             ORDER BY {$orderBy} {$dir}, p.name ASC
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $opening = (float) ($data['quantity_on_hand'] ?? 0);

        // Record the opening balance as a movement, so the running total adds up.
        $data['quantity_on_hand'] = 0;
        $data['created_by']       = Auth::id();
        $data['updated_by']       = Auth::id();
        $data['created_at']       = Dates::nowUtc();

        $id = db()->insert('parts', $data);

        Audit::created('part', $id, 'Added part "' . (string) $data['name'] . '"', $data);

        if ($opening > 0) {
            self::adjustStock($id, $opening, 'in', 'opening', null, 'Opening stock');
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $before = self::find($id);

        if ($before === null) {
            return;
        }

        // Stock is never set directly; it moves through adjustStock().
        unset($data['quantity_on_hand']);

        $data['updated_by'] = Auth::id();

        db()->update('parts', $data, ['id' => $id]);

        Audit::updated('part', $id, 'Updated part "' . (string) $before['name'] . '"', $before, $data);
    }

    public static function delete(int $id): bool
    {
        $part = self::find($id);

        if ($part === null) {
            return false;
        }

        db()->update('parts', [
            'deleted_at' => Dates::nowUtc(),
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        Audit::deleted('part', $id, 'Deleted part "' . (string) $part['name'] . '"');

        return true;
    }

    /**
     * Move stock and record why.
     *
     * A negative delta takes stock out. Stock is allowed to go negative rather
     * than blocking the save: if a technician has physically used a part, the
     * log should record reality and the count can be corrected afterwards.
     *
     * @param  float  $delta signed change
     * @param  string $type  in | out | adjust
     * @return array{ok: bool, balance: float}
     */
    public static function adjustStock(
        int $partId,
        float $delta,
        string $type = 'adjust',
        string $referenceType = '',
        ?int $referenceId = null,
        string $notes = ''
    ): array {
        $part = self::find($partId);

        if ($part === null) {
            return ['ok' => false, 'balance' => 0.0];
        }

        $balance = round((float) $part['quantity_on_hand'] + $delta, 2);

        db()->update('parts', [
            'quantity_on_hand' => $balance,
            'updated_by'       => Auth::id(),
        ], ['id' => $partId]);

        try {
            db()->insert('part_transactions', [
                'part_id'          => $partId,
                'transaction_type' => in_array($type, ['in', 'out', 'adjust'], true) ? $type : 'adjust',
                'quantity'         => abs($delta),
                'unit_cost'        => $part['unit_cost'],
                'balance_after'    => $balance,
                'reference_type'   => mb_substr($referenceType, 0, 40, 'UTF-8'),
                'reference_id'     => $referenceId,
                'user_id'          => Auth::id(),
                'notes'            => mb_substr($notes, 0, 255, 'UTF-8'),
                'created_at'       => Dates::nowUtc(),
            ]);
        } catch (Throwable $e) {
            log_error('Part transaction insert failed: ' . $e->getMessage());
        }

        // Warn once when a part crosses its reorder level going down.
        $level = (float) $part['reorder_level'];

        if ($delta < 0 && $level > 0 && $balance <= $level && (float) $part['quantity_on_hand'] > $level) {
            try {
                Notifier::lowStock(array_merge($part, ['quantity_on_hand' => $balance]));
            } catch (Throwable $e) {
                log_error('Low stock notification failed: ' . $e->getMessage());
            }

            \App\Slack::lowStock(array_merge($part, ['quantity_on_hand' => $balance]));
        }

        return ['ok' => true, 'balance' => $balance];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function transactions(int $partId, int $limit = 100): array
    {
        return db()->all(
            'SELECT t.*, u.first_name, u.last_name, u.username
             FROM {part_transactions} t
             LEFT JOIN {users} u ON u.id = t.user_id
             WHERE t.part_id = ?
             ORDER BY t.created_at DESC, t.id DESC
             LIMIT ' . max(1, min(500, $limit)),
            [$partId]
        );
    }

    /**
     * Where a part has been used.
     *
     * @return list<array<string, mixed>>
     */
    public static function usage(int $partId, int $limit = 50): array
    {
        return db()->all(
            'SELECT mp.*, l.id AS log_id, l.title, l.performed_at,
                    a.name AS asset_name, a.asset_tag
             FROM {maintenance_log_parts} mp
             INNER JOIN {maintenance_logs} l ON l.id = mp.log_id AND l.deleted_at IS NULL
             INNER JOIN {assets} a ON a.id = l.asset_id
             WHERE mp.part_id = ?
             ORDER BY l.performed_at DESC
             LIMIT ' . max(1, min(200, $limit)),
            [$partId]
        );
    }

    /**
     * Parts for the picker on the maintenance log form.
     *
     * @return list<array<string, mixed>>
     */
    public static function options(): array
    {
        return db()->all(
            'SELECT id, part_number, name, unit_cost, quantity_on_hand, unit_of_measure
             FROM {parts}
             WHERE deleted_at IS NULL AND is_active = 1
             ORDER BY name ASC'
        );
    }

    /**
     * Distinct categories in use, for the filter.
     *
     * @return array<string, string>
     */
    public static function categoryOptions(): array
    {
        $values = db()->column(
            "SELECT DISTINCT category FROM {parts}
             WHERE deleted_at IS NULL AND category <> '' ORDER BY category ASC"
        );

        $out = [];

        foreach ($values as $value) {
            $out[(string) $value] = (string) $value;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(): array
    {
        $row = db()->one(
            'SELECT COUNT(*) AS n,
                    COALESCE(SUM(quantity_on_hand * unit_cost), 0) AS value,
                    SUM(reorder_level > 0 AND quantity_on_hand <= reorder_level) AS low,
                    SUM(quantity_on_hand <= 0) AS out_of_stock
             FROM {parts}
             WHERE deleted_at IS NULL AND is_active = 1'
        ) ?? [];

        return [
            'count'        => (int) ($row['n'] ?? 0),
            'value'        => (float) ($row['value'] ?? 0),
            'low'          => (int) ($row['low'] ?? 0),
            'out_of_stock' => (int) ($row['out_of_stock'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function forExport(array $filters): array
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->all(
            "SELECT p.part_number, p.name, p.description, p.category, p.manufacturer,
                    p.supplier, p.supplier_part_number, p.unit_cost, p.unit_of_measure,
                    p.quantity_on_hand, p.reorder_level, p.reorder_quantity,
                    (p.quantity_on_hand * p.unit_cost) AS stock_value, p.location_bin, p.notes
             FROM {parts} p
             WHERE {$where}
             ORDER BY p.name ASC",
            $params
        );
    }
}
