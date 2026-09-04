<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * The preventive-maintenance engine.
 *
 * A schedule comes due either on a calendar interval ("every 3 months") or on
 * a meter interval ("every 50 engine hours"), and this class works out when
 * that next falls, rolls it forward when the work is logged, and raises the
 * notifications that get somebody to actually do it.
 *
 * Calendar dates are LOCAL calendar dates and never timezone-shifted: a job
 * due on 12 March is due on 12 March.
 */
final class Scheduler
{
    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // Computing the next due point
    // -------------------------------------------------------------------------

    /**
     * Work out next_due_date and next_due_meter for a schedule row.
     *
     * @param  array<string, mixed> $schedule
     * @param  array<string, mixed>|null $asset used for the current meter reading
     * @return array{next_due_date: string|null, next_due_meter: float|null}
     */
    public static function computeNextDue(array $schedule, ?array $asset = null): array
    {
        $frequencyType = (string) ($schedule['frequency_type'] ?? 'monthly');
        $frequencyVal  = max(1, (int) ($schedule['frequency_value'] ?? 1));

        $nextDate  = null;
        $nextMeter = null;

        // --- Calendar component ---------------------------------------------
        if ($frequencyType !== 'meter') {
            // Base the next date on when it was last done, falling back to
            // today so a brand new schedule becomes due one interval out.
            $lastPerformed = $schedule['last_performed_at'] ?? null;

            if (is_string($lastPerformed) && $lastPerformed !== '') {
                $local = Dates::toLocal($lastPerformed);
                $base  = $local === null ? Dates::today() : $local->format(Dates::DB_DATE);
            } else {
                $base = Dates::today();
            }

            $nextDate = Dates::addInterval($base, $frequencyType, $frequencyVal);

            // If the schedule has been neglected the computed date can still be
            // in the past. That is correct and we leave it: the point of the
            // overdue state is that somebody sees it.
        }

        // --- Meter component -------------------------------------------------
        $meterInterval = $schedule['meter_interval'] ?? null;

        if ($meterInterval !== null && (float) $meterInterval > 0) {
            $interval = (float) $meterInterval;
            $lastMeter = $schedule['last_meter'] ?? null;

            if ($lastMeter !== null && $lastMeter !== '') {
                $base = (float) $lastMeter;
            } elseif ($asset !== null) {
                $base = (float) ($asset['meter_reading'] ?? 0);
            } else {
                $base = 0.0;
            }

            $nextMeter = round($base + $interval, 2);
        }

        return ['next_due_date' => $nextDate, 'next_due_meter' => $nextMeter];
    }

    /**
     * Recalculate and persist the due point for one schedule.
     *
     * @return array{next_due_date: string|null, next_due_meter: float|null}
     */
    public static function recompute(int $scheduleId): array
    {
        $schedule = db()->one('SELECT * FROM {maintenance_schedules} WHERE id = ? LIMIT 1', [$scheduleId]);

        if ($schedule === null) {
            return ['next_due_date' => null, 'next_due_meter' => null];
        }

        $asset = db()->one('SELECT * FROM {assets} WHERE id = ? LIMIT 1', [(int) $schedule['asset_id']]);
        $next  = self::computeNextDue($schedule, $asset);

        db()->update('maintenance_schedules', [
            'next_due_date'  => $next['next_due_date'],
            'next_due_meter' => $next['next_due_meter'],
        ], ['id' => $scheduleId]);

        return $next;
    }

    /**
     * Record that a schedule was carried out, and roll it forward.
     *
     * Called when a maintenance log is saved against the schedule.
     */
    public static function markPerformed(int $scheduleId, string $performedAtUtc, ?float $meterReading = null, ?int $logId = null): void
    {
        $schedule = db()->one('SELECT * FROM {maintenance_schedules} WHERE id = ? LIMIT 1', [$scheduleId]);

        if ($schedule === null) {
            return;
        }

        $updated = array_merge($schedule, [
            'last_performed_at' => $performedAtUtc,
            'last_meter'        => $meterReading,
            'last_log_id'       => $logId,
        ]);

        $asset = db()->one('SELECT * FROM {assets} WHERE id = ? LIMIT 1', [(int) $schedule['asset_id']]);
        $next  = self::computeNextDue($updated, $asset);

        db()->update('maintenance_schedules', [
            'last_performed_at' => $performedAtUtc,
            'last_meter'        => $meterReading,
            'last_log_id'       => $logId,
            'next_due_date'     => $next['next_due_date'],
            'next_due_meter'    => $next['next_due_meter'],
        ], ['id' => $scheduleId]);

        // The old "it is due" notifications are stale now.
        try {
            db()->run(
                "UPDATE {notifications} SET is_read = 1, read_at = ?
                 WHERE entity_type = 'schedule' AND entity_id = ? AND is_read = 0
                   AND type IN ('pm_due', 'pm_overdue')",
                [Dates::nowUtc(), $scheduleId]
            );
        } catch (Throwable $e) {
            // Not important enough to fail the save.
        }

        Audit::record(
            'schedule.performed',
            'schedule',
            $scheduleId,
            'Marked "' . (string) $schedule['name'] . '" as performed; next due '
            . ($next['next_due_date'] ?? 'by meter')
        );
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /**
     * Schedules that are overdue or coming due, ordered most urgent first.
     *
     * @param  int  $withinDays how far ahead to look
     * @return list<array<string, mixed>> schedule rows joined to their asset,
     *                                    each with a computed 'due_state' and
     *                                    'days_until'
     */
    public static function due(int $withinDays = 14, ?int $assetId = null, ?int $limit = null): array
    {
        $params = [];
        $sql = "SELECT s.*, a.name AS asset_name, a.asset_tag, a.status AS asset_status,
                       a.meter_reading, a.meter_type, a.deleted_at AS asset_deleted,
                       c.name AS category_name, l.name AS location_name,
                       u.first_name AS assignee_first, u.last_name AS assignee_last
                FROM {maintenance_schedules} s
                INNER JOIN {assets} a ON a.id = s.asset_id
                LEFT JOIN {asset_categories} c ON c.id = a.category_id
                LEFT JOIN {locations} l ON l.id = a.location_id
                LEFT JOIN {users} u ON u.id = s.assigned_to
                WHERE s.is_active = 1
                  AND a.deleted_at IS NULL
                  AND a.status <> 'retired'";

        if ($assetId !== null) {
            $sql .= ' AND s.asset_id = ?';
            $params[] = $assetId;
        }

        $sql .= ' ORDER BY s.next_due_date IS NULL, s.next_due_date ASC, s.id ASC';

        try {
            $rows = db()->all($sql, $params);
        } catch (Throwable $e) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            $meter = $row['meter_reading'] === null ? null : (float) $row['meter_reading'];
            $state = Status::dueState($row, $meter);

            if (!in_array($state, ['overdue', 'due', 'due_soon'], true)) {
                // Not due yet — but a longer lookahead window may still want it.
                $days = Dates::daysUntil($row['next_due_date'] ?? null);

                if ($days === null || $days > $withinDays) {
                    continue;
                }
            }

            $row['due_state']  = $state;
            $row['days_until'] = Dates::daysUntil($row['next_due_date'] ?? null);

            $out[] = $row;
        }

        // Most urgent first: overdue, then due, then due soon, then by date.
        $rank = ['overdue' => 0, 'due' => 1, 'due_soon' => 2, 'ok' => 3, 'none' => 4, 'inactive' => 5];

        usort($out, static function (array $a, array $b) use ($rank): int {
            $byState = ($rank[$a['due_state']] ?? 9) <=> ($rank[$b['due_state']] ?? 9);

            if ($byState !== 0) {
                return $byState;
            }

            return ($a['days_until'] ?? PHP_INT_MAX) <=> ($b['days_until'] ?? PHP_INT_MAX);
        });

        if ($limit !== null && $limit > 0) {
            $out = array_slice($out, 0, $limit);
        }

        return $out;
    }

    /**
     * How many schedules are overdue right now. Used by the dashboard tile.
     */
    public static function overdueCount(): int
    {
        $count = 0;

        foreach (self::due(0) as $schedule) {
            if ($schedule['due_state'] === 'overdue') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Every schedule for one asset, with its computed state.
     *
     * @return list<array<string, mixed>>
     */
    public static function forAsset(int $assetId): array
    {
        $rows = db()->all(
            'SELECT s.*, c.name AS checklist_name,
                    u.first_name AS assignee_first, u.last_name AS assignee_last
             FROM {maintenance_schedules} s
             LEFT JOIN {checklists} c ON c.id = s.checklist_id
             LEFT JOIN {users} u ON u.id = s.assigned_to
             WHERE s.asset_id = ?
             ORDER BY s.is_active DESC, s.next_due_date IS NULL, s.next_due_date ASC',
            [$assetId]
        );

        $asset = db()->one('SELECT meter_reading, meter_type FROM {assets} WHERE id = ? LIMIT 1', [$assetId]);
        $meter = $asset === null || $asset['meter_reading'] === null ? null : (float) $asset['meter_reading'];

        foreach ($rows as $index => $row) {
            $rows[$index]['due_state']  = Status::dueState($row, $meter);
            $rows[$index]['days_until'] = Dates::daysUntil($row['next_due_date'] ?? null);
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Scheduled task
    // -------------------------------------------------------------------------

    /**
     * Raise notifications for work that is due or overdue.
     *
     * Run from cron.php, and opportunistically from the dashboard so the app
     * still works on hosting without cron.
     *
     * @return array{notified: int, overdue: int, due: int}
     */
    public static function raiseDueNotifications(): array
    {
        $leadDays = Settings::int('notify_pm_due_days', 7, 0, 365);
        $notified = 0;
        $overdue  = 0;
        $due      = 0;

        foreach (self::due($leadDays) as $schedule) {
            $state = (string) $schedule['due_state'];

            if ($state === 'overdue') {
                $overdue++;
            } elseif (in_array($state, ['due', 'due_soon'], true)) {
                $due++;
            } else {
                continue;
            }

            $assetName = (string) $schedule['asset_name'];
            $title     = $state === 'overdue'
                ? 'Overdue: ' . (string) $schedule['name']
                : 'Due soon: ' . (string) $schedule['name'];

            $when = $schedule['next_due_date'] !== null
                ? Dates::dateOnly((string) $schedule['next_due_date'])
                : 'by meter reading';

            $message = $assetName . ' (' . (string) $schedule['asset_tag'] . ') — due ' . $when . '.';
            $link    = 'schedules.php?id=' . (int) $schedule['id'];

            $assignee = (int) ($schedule['assigned_to'] ?? 0);

            if ($assignee > 0) {
                $notified += self::pushOne($assignee, $state, $title, $message, $link, (int) $schedule['id']);
            } else {
                // Nobody owns it, so tell everyone who could act on it.
                $notified += Notifier::pushToRole(
                    'schedules.manage',
                    $state === 'overdue' ? 'pm_overdue' : 'pm_due',
                    $title,
                    $message,
                    $link,
                    'schedule',
                    (int) $schedule['id']
                );
            }
        }

        return ['notified' => $notified, 'overdue' => $overdue, 'due' => $due];
    }

    private static function pushOne(int $userId, string $state, string $title, string $message, string $link, int $scheduleId): int
    {
        return Notifier::push(
            $userId,
            $state === 'overdue' ? 'pm_overdue' : 'pm_due',
            $title,
            $message,
            $link,
            'schedule',
            $scheduleId,
            true
        ) > 0 ? 1 : 0;
    }

    /**
     * Recalculate every active schedule. Cheap enough to run nightly, and used
     * by the installer after loading demo data.
     */
    public static function recomputeAll(): int
    {
        try {
            $ids = db()->column('SELECT id FROM {maintenance_schedules} WHERE is_active = 1');
        } catch (Throwable $e) {
            return 0;
        }

        $count = 0;

        foreach ($ids as $id) {
            try {
                self::recompute((int) $id);
                $count++;
            } catch (Throwable $e) {
                log_error('Schedule recompute failed for id ' . (int) $id . ': ' . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * When an asset's meter moves, any meter-based schedule may have crossed
     * its threshold. Nothing to recompute — the due state is derived — but the
     * notification needs raising promptly rather than waiting for cron.
     */
    public static function onMeterUpdated(int $assetId): void
    {
        try {
            $schedules = db()->all(
                'SELECT * FROM {maintenance_schedules}
                 WHERE asset_id = ? AND is_active = 1 AND meter_interval IS NOT NULL AND meter_interval > 0',
                [$assetId]
            );
        } catch (Throwable $e) {
            return;
        }

        if ($schedules === []) {
            return;
        }

        $asset = db()->one('SELECT * FROM {assets} WHERE id = ? LIMIT 1', [$assetId]);

        if ($asset === null) {
            return;
        }

        $meter = (float) ($asset['meter_reading'] ?? 0);

        foreach ($schedules as $schedule) {
            if (Status::dueState($schedule, $meter) !== 'overdue') {
                continue;
            }

            $title   = 'Overdue: ' . (string) $schedule['name'];
            $message = (string) $asset['name'] . ' has reached '
                     . decimal($meter) . ' ' . (string) $asset['meter_type']
                     . ' (due at ' . decimal($schedule['next_due_meter']) . ').';
            $link    = 'schedules.php?id=' . (int) $schedule['id'];

            $assignee = (int) ($schedule['assigned_to'] ?? 0);

            if ($assignee > 0) {
                Notifier::push($assignee, 'pm_overdue', $title, $message, $link, 'schedule', (int) $schedule['id'], true);
            } else {
                Notifier::pushToRole('schedules.manage', 'pm_overdue', $title, $message, $link, 'schedule', (int) $schedule['id']);
            }
        }
    }

    /**
     * Turn a schedule into the pre-filled values for a new maintenance log.
     *
     * @param  array<string, mixed> $schedule
     * @return array<string, mixed>
     */
    public static function logDefaults(array $schedule): array
    {
        return [
            'asset_id'    => (int) $schedule['asset_id'],
            'schedule_id' => (int) $schedule['id'],
            'log_type'    => (string) ($schedule['log_type'] ?? 'preventive'),
            'title'       => (string) $schedule['name'],
            'description' => (string) ($schedule['description'] ?? ''),
            'labor_hours' => $schedule['estimated_hours'] ?? null,
            'instructions'=> (string) ($schedule['instructions'] ?? ''),
        ];
    }
}
