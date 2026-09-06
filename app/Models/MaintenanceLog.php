<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit;
use App\Auth;
use App\Dates;
use App\Database;
use App\Scheduler;
use App\Settings;
use App\Str;
use App\Uploader;
use Throwable;

/**
 * Maintenance logs: the record of who worked on what, when, and what they did.
 *
 * This is the table the whole application exists to fill in, so saving one is
 * deliberately forgiving. A technician can record a job with a machine, a title
 * and a time; everything else is optional and can be added later.
 */
final class MaintenanceLog
{
    public const SORTS = [
        'performed' => 'l.performed_at',
        'asset'     => 'a.name',
        'title'     => 'l.title',
        'type'      => 'l.log_type',
        'user'      => 'u.last_name',
        'cost'      => 'l.total_cost',
        'hours'     => 'l.labor_hours',
        'created'   => 'l.created_at',
    ];

    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return db()->one(
            'SELECT l.*,
                    a.name AS asset_name, a.asset_tag, a.meter_type, a.id AS asset_id,
                    c.name AS category_name, loc.name AS location_name,
                    u.first_name, u.last_name, u.username, u.avatar_path, u.id AS user_id,
                    eu.first_name AS editor_first, eu.last_name AS editor_last,
                    s.name AS schedule_name,
                    w.wo_number, w.title AS work_order_title
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} loc ON loc.id = a.location_id
             LEFT JOIN {users} u ON u.id = l.user_id
             LEFT JOIN {users} eu ON eu.id = l.updated_by
             LEFT JOIN {maintenance_schedules} s ON s.id = l.schedule_id
             LEFT JOIN {work_orders} w ON w.id = l.work_order_id
             WHERE l.id = ? AND l.deleted_at IS NULL
             LIMIT 1',
            [$id]
        );
    }

    /**
     * @param  array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private static function buildFilter(array $filters): array
    {
        $where  = ['l.deleted_at IS NULL', 'a.deleted_at IS NULL'];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            foreach (Str::parseSearch($search, 4) as $term) {
                $like = Str::likeContains($term);
                $where[] = '(l.title LIKE ? OR l.description LIKE ? OR l.work_performed LIKE ?
                             OR a.name LIKE ? OR a.asset_tag LIKE ?)';
                array_push($params, $like, $like, $like, $like, $like);
            }
        }

        if (!empty($filters['asset_id'])) {
            $where[]  = 'l.asset_id = ?';
            $params[] = (int) $filters['asset_id'];
        }

        if (!empty($filters['category_id'])) {
            $where[]  = 'a.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }

        if (!empty($filters['location_id'])) {
            $where[]  = 'a.location_id = ?';
            $params[] = (int) $filters['location_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[]  = 'l.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['log_type'])) {
            $where[]  = 'l.log_type = ?';
            $params[] = (string) $filters['log_type'];
        }

        if (!empty($filters['followup'])) {
            $where[] = 'l.requires_followup = 1';
        }

        [$from, $to] = Dates::rangeToUtc(
            (string) ($filters['from'] ?? ''),
            (string) ($filters['to'] ?? '')
        );

        if ($from !== null) {
            $where[]  = 'l.performed_at >= ?';
            $params[] = $from;
        }

        if ($to !== null) {
            $where[]  = 'l.performed_at < ?';
            $params[] = $to;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public static function count(array $filters = []): int
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->count(
            'SELECT COUNT(*) FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE ' . $where,
            $params
        );
    }

    /**
     * Totals for the current filter, shown above the list so a manager can
     * answer "what did this cost us" without exporting anything.
     *
     * @param  array<string, mixed> $filters
     * @return array<string, float|int>
     */
    public static function totals(array $filters = []): array
    {
        [$where, $params] = self::buildFilter($filters);

        $row = db()->one(
            'SELECT COUNT(*) AS n,
                    COALESCE(SUM(l.total_cost), 0)       AS cost,
                    COALESCE(SUM(l.labor_hours), 0)      AS hours,
                    COALESCE(SUM(l.downtime_minutes), 0) AS downtime
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE ' . $where,
            $params
        ) ?? [];

        return [
            'count'    => (int) ($row['n'] ?? 0),
            'cost'     => (float) ($row['cost'] ?? 0),
            'hours'    => (float) ($row['hours'] ?? 0),
            'downtime' => (int) ($row['downtime'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function paginate(array $filters, string $sort, string $direction, int $limit, int $offset): array
    {
        [$where, $params] = self::buildFilter($filters);

        $orderBy = self::SORTS[$sort] ?? self::SORTS['performed'];
        $dir     = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return db()->all(
            "SELECT l.*, a.name AS asset_name, a.asset_tag, a.id AS asset_id,
                    c.name AS category_name,
                    u.first_name, u.last_name, u.username, u.avatar_path, u.id AS user_id,
                    (SELECT COUNT(*) FROM {attachments} at
                      WHERE at.entity_type = 'maintenance_log' AND at.entity_id = l.id) AS attachment_count
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE {$where}
             ORDER BY {$orderBy} {$dir}, l.id DESC
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * Save a log, along with its parts, meter reading and side effects.
     *
     * Everything happens in one transaction: a log that half-saved, decrementing
     * stock but recording no job, would be worse than not saving at all.
     *
     * @param  array<string, mixed>       $data  validated fields
     * @param  list<array<string, mixed>> $parts rows from the repeatable section
     * @return int the log id
     */
    public static function create(array $data, array $parts = []): int
    {
        $userId = Auth::id();

        $logId = db()->transaction(static function (Database $db) use ($data, $parts, $userId): int {
            $scheduleId = $data['schedule_id'] ?? null;
            $meter      = $data['meter_reading'] ?? null;
            $statusAfter = $data['status_after'] ?? null;

            $row = self::rowFor($data, $parts);
            $row['created_by'] = $userId;
            $row['updated_by'] = $userId;
            $row['created_at'] = Dates::nowUtc();

            $logId = $db->insert('maintenance_logs', $row);

            self::savePartsFor($logId, $parts, true);

            Audit::created('maintenance_log', $logId, 'Logged "' . (string) $row['title'] . '"', [
                'asset_id'     => $row['asset_id'],
                'log_type'     => $row['log_type'],
                'performed_at' => $row['performed_at'],
                'total_cost'   => $row['total_cost'],
            ]);

            self::applySideEffects($logId, (int) $row['asset_id'], $data, $meter, $statusAfter, $scheduleId);

            return $logId;
        });

        // Only once the record is safely committed.
        \App\Slack::jobLogged($logId);

        return $logId;
    }

    /**
     * @param array<string, mixed>       $data
     * @param list<array<string, mixed>> $parts
     */
    public static function update(int $id, array $data, array $parts = []): void
    {
        $before = self::find($id);

        if ($before === null) {
            return;
        }

        $userId      = Auth::id();
        $beforeParts = self::parts($id);

        db()->transaction(static function (Database $db) use ($id, $data, $parts, $before, $beforeParts, $userId): void {
            $row = self::rowFor($data, $parts);
            $row['updated_by'] = $userId;

            $db->update('maintenance_logs', $row, ['id' => $id]);

            // Editing replaces the parts list. Stock moves only by the
            // difference, so fixing a typo in the title writes no movement
            // and cannot trip a low-stock alert twice.
            self::savePartsFor($id, $parts, false);
            self::reconcileStock($id, $beforeParts, $parts);

            Audit::updated('maintenance_log', $id, 'Edited "' . (string) $row['title'] . '"', $before, $row);
        });

        // Only what actually changed takes effect again. Replaying every
        // side effect would rewind a schedule a later job has rolled forward,
        // put the machine back into a status it has since left, and write a
        // duplicate meter reading — all from correcting a spelling mistake.
        $meter        = $data['meter_reading'] ?? null;
        $meterGiven   = $meter !== null && $meter !== '';
        $meterChanged = $meterGiven
            && ($before['meter_reading'] === null || abs((float) $meter - (float) $before['meter_reading']) > 0.004);

        $statusAfter   = (string) ($data['status_after'] ?? '');
        $statusChanged = $statusAfter !== '' && $statusAfter !== (string) ($before['status_after'] ?? '');

        $scheduleId      = $data['schedule_id'] ?? null;
        $scheduleChanged = !empty($scheduleId) && (
            (int) $scheduleId !== (int) ($before['schedule_id'] ?? 0)
            || (string) ($data['performed_at'] ?? '') !== (string) $before['performed_at']
            || $meterChanged
        );

        self::applySideEffects(
            $id,
            (int) ($data['asset_id'] ?? $before['asset_id']),
            $data,
            $meterChanged ? $meter : null,
            $statusChanged ? $statusAfter : null,
            $scheduleChanged ? $scheduleId : null
        );
    }

    /**
     * Move stock by the difference between the parts a log used to list and
     * the parts it lists now.
     *
     * @param list<array<string, mixed>> $before rows from parts()
     * @param list<array<string, mixed>> $after  normalised rows from the form
     */
    private static function reconcileStock(int $logId, array $before, array $after): void
    {
        $was = [];
        $now = [];

        foreach ($before as $line) {
            if (!empty($line['part_id']) && (int) ($line['from_inventory'] ?? 1) === 1) {
                $was[(int) $line['part_id']] = ($was[(int) $line['part_id']] ?? 0.0) + (float) $line['quantity'];
            }
        }

        foreach ($after as $line) {
            if (!empty($line['part_id'])) {
                $now[(int) $line['part_id']] = ($now[(int) $line['part_id']] ?? 0.0) + (float) $line['quantity'];
            }
        }

        foreach (array_unique(array_merge(array_keys($was), array_keys($now))) as $partId) {
            $delta = round(($now[$partId] ?? 0.0) - ($was[$partId] ?? 0.0), 2);

            if (abs($delta) < 0.001) {
                continue;
            }

            try {
                Part::adjustStock(
                    $partId,
                    -$delta,
                    $delta > 0 ? 'out' : 'in',
                    'maintenance_log',
                    $logId,
                    $delta > 0 ? 'Used on a job (added when the job was edited)' : 'Returned when the job was edited'
                );
            } catch (Throwable $e) {
                log_error('Stock adjustment failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Build the database row from validated input, computing the money.
     *
     * @param  array<string, mixed>       $data
     * @param  list<array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    private static function rowFor(array $data, array $parts): array
    {
        $partsCost = 0.0;

        foreach ($parts as $part) {
            $partsCost += (float) ($part['total_cost'] ?? 0);
        }

        // A hand-entered parts cost wins over the calculated one, because
        // somebody may be recording a supplier invoice rather than line items.
        if (isset($data['parts_cost']) && $data['parts_cost'] !== null && $data['parts_cost'] !== '') {
            $partsCost = (float) $data['parts_cost'];
        }

        $laborHours = (float) ($data['labor_hours'] ?? 0);
        $laborRate  = $data['labor_rate'] ?? null;

        if ($laborRate === null || $laborRate === '') {
            $default   = (float) Settings::get('default_labor_rate', 0);
            $laborRate = $default > 0 ? $default : null;
        } else {
            $laborRate = (float) $laborRate;
        }

        $laborCost = $data['labor_cost'] ?? null;

        if (($laborCost === null || $laborCost === '') && $laborRate !== null) {
            $laborCost = round($laborHours * (float) $laborRate, 2);
        }

        $laborCost = (float) ($laborCost ?? 0);
        $otherCost = (float) ($data['other_cost'] ?? 0);

        return [
            'asset_id'          => (int) $data['asset_id'],
            'user_id'           => $data['user_id'] ?? Auth::id(),
            'log_type'          => (string) $data['log_type'],
            'title'             => (string) $data['title'],
            'description'       => $data['description'] ?? null,
            'work_performed'    => $data['work_performed'] ?? null,
            'performed_at'      => (string) $data['performed_at'],
            'completed_at'      => $data['completed_at'] ?? null,
            'labor_hours'       => $laborHours,
            'labor_rate'        => $laborRate,
            'labor_cost'        => $laborCost,
            'parts_cost'        => round($partsCost, 2),
            'other_cost'        => $otherCost,
            'total_cost'        => round($partsCost + $laborCost + $otherCost, 2),
            'meter_reading'     => $data['meter_reading'] ?? null,
            'downtime_minutes'  => $data['downtime_minutes'] ?? null,
            'status_before'     => $data['status_before'] ?? null,
            'status_after'      => $data['status_after'] ?? null,
            'schedule_id'       => empty($data['schedule_id']) ? null : (int) $data['schedule_id'],
            'work_order_id'     => empty($data['work_order_id']) ? null : (int) $data['work_order_id'],
            'inspection_id'     => empty($data['inspection_id']) ? null : (int) $data['inspection_id'],
            'is_completed'      => isset($data['is_completed']) ? (int) $data['is_completed'] : 1,
            'requires_followup' => isset($data['requires_followup']) ? (int) $data['requires_followup'] : 0,
            'followup_notes'    => $data['followup_notes'] ?? null,
        ];
    }

    /**
     * Everything that happens because a job was logged.
     *
     * Kept out of the transaction: these touch other tables and raise
     * notifications, and a failure here must not throw away the log itself.
     *
     * @param array<string, mixed> $data
     */
    private static function applySideEffects(
        int $logId,
        int $assetId,
        array $data,
        $meterReading,
        $statusAfter,
        $scheduleId
    ): void {
        // 1. Meter reading
        if ($meterReading !== null && $meterReading !== '') {
            try {
                $meterResult = Asset::updateMeter(
                    $assetId,
                    (float) $meterReading,
                    'Recorded on a maintenance log',
                    'maintenance_log',
                    $logId
                );

                // The log itself is saved either way; say so rather than
                // letting a rejected reading disappear silently.
                if (!$meterResult['ok']) {
                    flash('warning', 'The log was saved, but the meter reading was not applied. '
                        . $meterResult['error']);
                }
            } catch (Throwable $e) {
                log_error('Meter update from log failed: ' . $e->getMessage());
            }
        }

        // 2. Machine status
        if ($statusAfter !== null && $statusAfter !== '') {
            try {
                Asset::changeStatus($assetId, (string) $statusAfter, 'Set on a maintenance log');
            } catch (Throwable $e) {
                log_error('Status change from log failed: ' . $e->getMessage());
            }
        }

        // 3. Roll the schedule forward — once the job is actually finished.
        if (!empty($scheduleId) && (int) ($data['is_completed'] ?? 1) === 1) {
            try {
                Scheduler::markPerformed(
                    (int) $scheduleId,
                    (string) $data['performed_at'],
                    ($meterReading === null || $meterReading === '') ? null : (float) $meterReading,
                    $logId
                );
            } catch (Throwable $e) {
                log_error('Schedule roll-forward failed: ' . $e->getMessage());
            }
        }

        // 4. Say what this job did to the work order it was against: finish
        //    it, hand it over for sign-off, or — when it stays open — at least
        //    tell the people who run the workshop that work has been done, so
        //    a finished job is never sitting on the board unannounced.
        if (!empty($data['work_order_id'])) {
            $workOrderId = (int) $data['work_order_id'];

            try {
                if (!empty($data['close_work_order'])) {
                    WorkOrder::completeFromLog($workOrderId, $logId);
                } elseif (!empty($data['handover_work_order'])) {
                    WorkOrder::markFinishedPendingSignOff($workOrderId, (string) ($data['work_performed'] ?: $data['title']));
                } else {
                    WorkOrder::noteWorkLogged($workOrderId, $logId);
                }
            } catch (Throwable $e) {
                log_error('Work order update from log failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Save the parts used, taking them out of stock where they came from it.
     *
     * @param list<array<string, mixed>> $parts
     */
    private static function savePartsFor(int $logId, array $parts, bool $adjustStock): void
    {
        db()->delete('maintenance_log_parts', ['log_id' => $logId]);

        foreach ($parts as $part) {
            $name = trim((string) ($part['part_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $quantity = (float) ($part['quantity'] ?? 0);
            $unitCost = (float) ($part['unit_cost'] ?? 0);
            $partId   = empty($part['part_id']) ? null : (int) $part['part_id'];

            db()->insert('maintenance_log_parts', [
                'log_id'         => $logId,
                'part_id'        => $partId,
                'part_number'    => (string) ($part['part_number'] ?? ''),
                'part_name'      => $name,
                'quantity'       => $quantity,
                'unit_cost'      => $unitCost,
                'total_cost'     => round($quantity * $unitCost, 2),
                'from_inventory' => $partId !== null ? 1 : 0,
                'notes'          => mb_substr((string) ($part['notes'] ?? ''), 0, 255, 'UTF-8'),
            ]);

            if ($adjustStock && $partId !== null && $quantity > 0) {
                try {
                    Part::adjustStock($partId, -$quantity, 'out', 'maintenance_log', $logId, 'Used on a job');
                } catch (Throwable $e) {
                    log_error('Stock adjustment failed: ' . $e->getMessage());
                }
            }
        }
    }

    /** Put back the stock taken by a log, before replacing or deleting it. */
    private static function returnPartsToStock(int $logId): void
    {
        $rows = db()->all(
            'SELECT part_id, quantity FROM {maintenance_log_parts}
             WHERE log_id = ? AND part_id IS NOT NULL AND from_inventory = 1',
            [$logId]
        );

        foreach ($rows as $row) {
            try {
                Part::adjustStock(
                    (int) $row['part_id'],
                    (float) $row['quantity'],
                    'in',
                    'maintenance_log',
                    $logId,
                    'Returned when the job was edited or removed'
                );
            } catch (Throwable $e) {
                log_error('Stock return failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Soft delete, returning any stock the job consumed.
     */
    public static function delete(int $id): bool
    {
        $log = self::find($id);

        if ($log === null) {
            return false;
        }

        self::returnPartsToStock($id);

        db()->update('maintenance_logs', [
            'deleted_at' => Dates::nowUtc(),
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        Audit::deleted('maintenance_log', $id, 'Deleted log "' . (string) $log['title'] . '"', [
            'asset'        => $log['asset_name'],
            'performed_at' => $log['performed_at'],
            'total_cost'   => $log['total_cost'],
        ]);

        // A schedule this job had marked done goes back to the job before it,
        // or to "not yet done", so the service comes round again.
        if (!empty($log['schedule_id'])) {
            self::rewindSchedule((int) $log['schedule_id'], $id);
        }

        return true;
    }

    /**
     * Recompute a schedule after the log that last performed it was deleted.
     */
    private static function rewindSchedule(int $scheduleId, int $deletedLogId): void
    {
        try {
            $schedule = db()->one('SELECT * FROM {maintenance_schedules} WHERE id = ? LIMIT 1', [$scheduleId]);

            if ($schedule === null || (int) ($schedule['last_log_id'] ?? 0) !== $deletedLogId) {
                return;
            }

            $previous = db()->one(
                'SELECT id, performed_at, meter_reading FROM {maintenance_logs}
                 WHERE schedule_id = ? AND deleted_at IS NULL AND is_completed = 1
                 ORDER BY performed_at DESC, id DESC LIMIT 1',
                [$scheduleId]
            );

            db()->update('maintenance_schedules', [
                'last_performed_at' => $previous === null ? null : (string) $previous['performed_at'],
                'last_meter'        => $previous === null ? null : $previous['meter_reading'],
                'last_log_id'       => $previous === null ? null : (int) $previous['id'],
                // Never done now: recompute() gives it a fresh first due point.
                'next_due_date'     => null,
                'next_due_meter'    => null,
            ], ['id' => $scheduleId]);

            Scheduler::recompute($scheduleId);
        } catch (Throwable $e) {
            log_error('Schedule rewind after log delete failed: ' . $e->getMessage());
        }
    }

    /**
     * The parts recorded against a log.
     *
     * @return list<array<string, mixed>>
     */
    public static function parts(int $logId): array
    {
        return db()->all(
            'SELECT p.*, inv.name AS inventory_name, inv.quantity_on_hand
             FROM {maintenance_log_parts} p
             LEFT JOIN {parts} inv ON inv.id = p.part_id
             WHERE p.log_id = ?
             ORDER BY p.id ASC',
            [$logId]
        );
    }

    /**
     * Normalise the repeatable parts rows a form submits.
     *
     * @param  mixed $input
     * @return list<array<string, mixed>>
     */
    public static function normaliseParts($input, array $existing = []): array
    {
        if (!is_array($input)) {
            return [];
        }

        // Prices already on this log, so a line re-submitted by somebody who
        // cannot see prices keeps the one an administrator gave it.
        $knownById   = [];
        $knownByName = [];

        foreach ($existing as $line) {
            if (!empty($line['part_id'])) {
                $knownById[(int) $line['part_id']] = (float) $line['unit_cost'];
            }

            $knownByName[mb_strtolower(trim((string) $line['part_name']), 'UTF-8')] = (float) $line['unit_cost'];
        }

        $out = [];

        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['part_name'] ?? ''));

            if ($name === '') {
                continue;   // an empty repeater row the user never filled in
            }

            $partId   = empty($row['part_id']) ? null : (int) $row['part_id'];
            $quantity = (float) preg_replace('/[^0-9.\-]/', '', (string) ($row['quantity'] ?? '1'));

            // The price, in order of who knows best: whatever was typed; the
            // price this line already had; the shelf price of the stock part;
            // nothing. A form that hides prices sends none, and still ends up
            // with the right figure on the record.
            $typed = trim((string) ($row['unit_cost'] ?? ''));

            if ($typed !== '') {
                $unitCost = (float) preg_replace('/[^0-9.\-]/', '', $typed);
            } elseif ($partId !== null && isset($knownById[$partId])) {
                $unitCost = $knownById[$partId];
            } elseif (isset($knownByName[mb_strtolower($name, 'UTF-8')])) {
                $unitCost = $knownByName[mb_strtolower($name, 'UTF-8')];
            } elseif ($partId !== null) {
                $unitCost = (float) (db()->value('SELECT unit_cost FROM {parts} WHERE id = ?', [$partId]) ?? 0);
            } else {
                $unitCost = 0.0;
            }

            // The part number comes from the stock record when the line was
            // picked from stock; the form has no box for it.
            $partNumber = trim((string) ($row['part_number'] ?? ''));

            if ($partNumber === '' && $partId !== null) {
                $partNumber = (string) (db()->value('SELECT part_number FROM {parts} WHERE id = ?', [$partId]) ?? '');
            }

            $out[] = [
                'part_id'     => $partId,
                'part_number' => mb_substr($partNumber, 0, 100, 'UTF-8'),
                'part_name'   => mb_substr($name, 0, 191, 'UTF-8'),
                'quantity'    => max(0, $quantity),
                'unit_cost'   => max(0, $unitCost),
                'total_cost'  => round(max(0, $quantity) * max(0, $unitCost), 2),
                'notes'       => mb_substr(trim((string) ($row['notes'] ?? '')), 0, 255, 'UTF-8'),
            ];
        }

        return $out;
    }

    /**
     * Rows for CSV export.
     *
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function forExport(array $filters): array
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->all(
            "SELECT l.performed_at, a.asset_tag, a.name AS asset_name, c.name AS category,
                    loc.name AS location, l.log_type, l.title, l.description, l.work_performed,
                    CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS technician,
                    l.labor_hours, l.labor_cost, l.parts_cost, l.other_cost, l.total_cost,
                    l.meter_reading, l.downtime_minutes, l.status_after,
                    l.requires_followup, l.followup_notes,
                    (SELECT GROUP_CONCAT(CONCAT(mp.part_name, ' x', mp.quantity) SEPARATOR '; ')
                       FROM {maintenance_log_parts} mp WHERE mp.log_id = l.id) AS parts_used
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} loc ON loc.id = a.location_id
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE {$where}
             ORDER BY l.performed_at DESC",
            $params
        );
    }

    /**
     * Technicians who have logged work, for the filter dropdown.
     *
     * @return array<int, string>
     */
    public static function technicianOptions(): array
    {
        return db()->pairs(
            "SELECT u.id, TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')))
             FROM {users} u
             WHERE u.deleted_at IS NULL
               AND EXISTS (SELECT 1 FROM {maintenance_logs} l WHERE l.user_id = u.id AND l.deleted_at IS NULL)
             ORDER BY u.last_name ASC, u.first_name ASC"
        );
    }
}
