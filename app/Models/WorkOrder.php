<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit;
use App\Auth;
use App\Dates;
use App\Notifier;
use App\Settings;
use App\Status;
use App\Str;
use Throwable;

/**
 * Work orders: a reported problem, tracked from "the kart pulls left" through
 * to somebody fixing it.
 *
 * Anyone who can see a ride can raise one. That is deliberate — the ride
 * operator who notices the problem is rarely the person who repairs it, and a
 * report that is awkward to file is a report that never gets filed.
 */
final class WorkOrder
{
    public const SORTS = [
        'number'   => 'w.wo_number',
        'title'    => 'w.title',
        'asset'    => 'a.name',
        'priority' => "FIELD(w.priority, 'urgent', 'high', 'normal', 'low')",
        'status'   => "FIELD(w.status, 'open', 'assigned', 'in_progress', 'on_hold', 'completed', 'cancelled')",
        'due'      => 'w.due_date',
        'created'  => 'w.created_at',
        'assignee' => 'u.last_name',
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
            'SELECT w.*,
                    a.name AS asset_name, a.asset_tag, a.status AS asset_status,
                    c.name AS category_name, l.name AS location_name,
                    r.first_name AS reporter_first, r.last_name AS reporter_last,
                    r.username AS reporter_username, r.avatar_path AS reporter_avatar, r.id AS reporter_id,
                    u.first_name, u.last_name, u.username, u.avatar_path, u.id AS assignee_id,
                    cb.first_name AS closer_first, cb.last_name AS closer_last
             FROM {work_orders} w
             LEFT JOIN {assets} a ON a.id = w.asset_id
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} l ON l.id = a.location_id
             LEFT JOIN {users} r ON r.id = w.reported_by
             LEFT JOIN {users} u ON u.id = w.assigned_to
             LEFT JOIN {users} cb ON cb.id = w.closed_by
             WHERE w.id = ? AND w.deleted_at IS NULL
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
        $where  = ['w.deleted_at IS NULL'];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            foreach (Str::parseSearch($search, 3) as $term) {
                $like = Str::likeContains($term);
                $where[] = '(w.title LIKE ? OR w.description LIKE ? OR w.wo_number LIKE ?
                             OR a.name LIKE ? OR a.asset_tag LIKE ?)';
                array_push($params, $like, $like, $like, $like, $like);
            }
        }

        $status = (string) ($filters['status'] ?? '');

        if ($status === 'open') {
            $where[] = "w.status NOT IN ('completed', 'cancelled')";
        } elseif ($status === 'closed') {
            $where[] = "w.status IN ('completed', 'cancelled')";
        } elseif ($status !== '' && $status !== 'all') {
            $where[]  = 'w.status = ?';
            $params[] = $status;
        } elseif ($status === '') {
            $where[] = "w.status NOT IN ('completed', 'cancelled')";
        }

        foreach (['asset_id' => 'w.asset_id', 'assigned_to' => 'w.assigned_to'] as $key => $column) {
            if (!empty($filters[$key])) {
                $where[]  = $column . ' = ?';
                $params[] = (int) $filters[$key];
            }
        }

        if (!empty($filters['priority'])) {
            $where[]  = 'w.priority = ?';
            $params[] = (string) $filters['priority'];
        }

        if (!empty($filters['unassigned'])) {
            $where[] = 'w.assigned_to IS NULL';
        }

        if (!empty($filters['overdue'])) {
            $where[]  = "w.due_date IS NOT NULL AND w.due_date < ? AND w.status NOT IN ('completed','cancelled')";
            $params[] = Dates::today();
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
            'SELECT COUNT(*) FROM {work_orders} w
             LEFT JOIN {assets} a ON a.id = w.asset_id
             LEFT JOIN {users} u ON u.id = w.assigned_to
             WHERE ' . $where,
            $params
        );
    }

    /**
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function paginate(array $filters, string $sort, string $direction, int $limit, int $offset): array
    {
        [$where, $params] = self::buildFilter($filters);

        $orderBy = self::SORTS[$sort] ?? self::SORTS['priority'];
        $dir     = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        return db()->all(
            "SELECT w.*, a.name AS asset_name, a.asset_tag,
                    u.first_name, u.last_name, u.username, u.avatar_path, u.id AS assignee_id,
                    (SELECT COUNT(*) FROM {work_order_comments} wc WHERE wc.work_order_id = w.id AND wc.is_status_change = 0) AS comment_count
             FROM {work_orders} w
             LEFT JOIN {assets} a ON a.id = w.asset_id
             LEFT JOIN {users} u ON u.id = w.assigned_to
             WHERE {$where}
             ORDER BY {$orderBy} {$dir}, w.created_at DESC
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * Generate the next work order number.
     *
     * Reads the highest existing number rather than counting rows, so deleting
     * one never causes a duplicate.
     */
    public static function nextNumber(): string
    {
        $prefix = (string) Settings::get('wo_number_prefix', 'WO-');
        $last   = db()->value(
            'SELECT wo_number FROM {work_orders} ORDER BY id DESC LIMIT 1'
        );

        $next = $last === null ? 1 : Str::sequenceNumber((string) $last) + 1;

        // Guard against a gap in the sequence colliding with an existing row.
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $candidate = Str::sequence($next, $prefix, 6);

            if (!db()->exists('work_orders', ['wo_number' => $candidate])) {
                return $candidate;
            }

            $next++;
        }

        return Str::sequence($next, $prefix, 6) . '-' . Str::random(4);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $userId = Auth::id();

        $data['wo_number']   = self::nextNumber();
        $data['reported_by'] = $data['reported_by'] ?? $userId;
        $data['created_by']  = $userId;
        $data['updated_by']  = $userId;
        $data['created_at']  = Dates::nowUtc();

        if (!empty($data['assigned_to']) && ($data['status'] ?? 'open') === 'open') {
            $data['status'] = 'assigned';
        }

        $id = db()->insert('work_orders', $data);

        Audit::created('work_order', $id, 'Raised ' . $data['wo_number'] . ': ' . (string) $data['title'], [
            'asset_id' => $data['asset_id'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
        ]);

        // Taking a ride out of service is part of raising the report, not a
        // separate thing to remember afterwards.
        if (!empty($data['took_out_of_service']) && !empty($data['asset_id'])) {
            Asset::changeStatus(
                (int) $data['asset_id'],
                'out_of_service',
                'Taken out of service by ' . $data['wo_number']
            );
        }

        if (!empty($data['assigned_to'])) {
            self::notifyAssignee($id, (int) $data['assigned_to']);
        } else {
            // Nobody owns it yet, so tell the people who can assign it.
            try {
                $row = self::find($id);
                Notifier::pushToRole(
                    'workorders.assign',
                    'wo_updated',
                    ((string) ($data['priority'] ?? '') === 'urgent' ? 'Urgent: ' : 'New work order: ')
                    . (string) $data['title'],
                    ($row['asset_name'] ?? 'No ' . asset_word()) . ' — ' . $data['wo_number'],
                    'workorder-view.php?id=' . $id,
                    'work_order',
                    $id,
                    false,
                    Auth::id()
                );
            } catch (Throwable $e) {
                log_error('Work order notification failed: ' . $e->getMessage());
            }
        }

        \App\Slack::problemReported($id);

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

        $data['updated_by'] = Auth::id();

        // Timestamps that should look after themselves.
        if (isset($data['status'])) {
            if ($data['status'] === 'in_progress' && empty($before['started_at'])) {
                $data['started_at'] = Dates::nowUtc();
            }

            if (Status::isClosedWorkOrder((string) $data['status']) && empty($before['completed_at'])) {
                $data['completed_at'] = Dates::nowUtc();
                $data['closed_by']    = Auth::id();
            }

            if (!Status::isClosedWorkOrder((string) $data['status'])) {
                $data['completed_at'] = null;
                $data['closed_by']    = null;
            }
        }

        db()->update('work_orders', $data, ['id' => $id]);

        Audit::updated('work_order', $id, 'Updated ' . (string) $before['wo_number'], $before, $data);

        // A status change gets its own comment, so the thread reads as a story.
        if (isset($data['status']) && (string) $data['status'] !== (string) $before['status']) {
            self::addComment(
                $id,
                'Status changed from ' . Status::label((string) $before['status'], 'workorder')
                . ' to ' . Status::label((string) $data['status'], 'workorder') . '.',
                true,
                (string) $before['status'],
                (string) $data['status']
            );

            if (Status::isClosedWorkOrder((string) $data['status'])
                && !Status::isClosedWorkOrder((string) $before['status'])) {
                \App\Slack::problemFixed($id);

                // The people involved hear that it was fixed (or dropped),
                // unless they are the one who closed it.
                $me         = (int) Auth::id();
                $recipients = array_values(array_unique(array_filter(
                    [(int) ($before['reported_by'] ?? 0), (int) ($before['assigned_to'] ?? 0)],
                    static function (int $userId) use ($me): bool {
                        return $userId > 0 && $userId !== $me;
                    }
                )));

                if ($recipients !== []) {
                    try {
                        $resolution = trim((string) ($data['resolution'] ?? $before['resolution'] ?? ''));

                        Notifier::pushMany(
                            $recipients,
                            'wo_updated',
                            ((string) $data['status'] === 'cancelled' ? 'Cancelled: ' : 'Fixed: ') . (string) $before['title'],
                            (string) $before['wo_number'] . ($resolution !== '' ? ' — ' . Str::limit($resolution, 120) : ''),
                            'workorder-view.php?id=' . $id,
                            'work_order',
                            $id
                        );
                    } catch (Throwable $e) {
                        log_error('Work order close notification failed: ' . $e->getMessage());
                    }
                }
            }
        }

        if (isset($data['assigned_to'])
            && (int) $data['assigned_to'] !== (int) ($before['assigned_to'] ?? 0)
            && (int) $data['assigned_to'] > 0) {
            self::notifyAssignee($id, (int) $data['assigned_to']);
        }
    }

    private static function notifyAssignee(int $workOrderId, int $userId): void
    {
        try {
            $workOrder = self::find($workOrderId);

            if ($workOrder !== null) {
                Notifier::workOrderAssigned($workOrder, $userId);
            }
        } catch (Throwable $e) {
            log_error('Assignee notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Close a work order because a maintenance log recorded the fix.
     */
    public static function completeFromLog(int $workOrderId, int $logId): void
    {
        $workOrder = self::find($workOrderId);

        if ($workOrder === null || Status::isClosedWorkOrder((string) $workOrder['status'])) {
            return;
        }

        $log = db()->one('SELECT title, work_performed, performed_at FROM {maintenance_logs} WHERE id = ?', [$logId]);

        self::update($workOrderId, [
            'status'     => 'completed',
            'resolution' => $log === null
                ? 'Completed via a maintenance log.'
                : trim((string) ($log['work_performed'] ?: $log['title'])),
        ]);

        self::addComment($workOrderId, 'Completed by a maintenance log. See the job record for details.', false);
    }

    /**
     * Add a comment. Status changes are recorded the same way, flagged so the
     * view can render them differently.
     */
    public static function addComment(
        int $workOrderId,
        string $comment,
        bool $isStatusChange = false,
        string $oldStatus = '',
        string $newStatus = ''
    ): int {
        $comment = trim($comment);

        if ($comment === '') {
            return 0;
        }

        $id = db()->insert('work_order_comments', [
            'work_order_id'    => $workOrderId,
            'user_id'          => Auth::id(),
            'comment'          => $comment,
            'is_status_change' => $isStatusChange ? 1 : 0,
            'old_status'       => $oldStatus,
            'new_status'       => $newStatus,
            'created_at'       => Dates::nowUtc(),
        ]);

        // Tell whoever else is involved, but never tell people about their own
        // comment.
        if (!$isStatusChange) {
            try {
                $workOrder = self::find($workOrderId);

                if ($workOrder !== null) {
                    $recipients = array_filter([
                        (int) ($workOrder['assigned_to'] ?? 0),
                        (int) ($workOrder['reported_by'] ?? 0),
                    ], static function (int $userId): bool {
                        return $userId > 0 && $userId !== (int) Auth::id();
                    });

                    if ($recipients !== []) {
                        Notifier::pushMany(
                            array_values($recipients),
                            'wo_updated',
                            'New comment on ' . (string) $workOrder['wo_number'],
                            Str::limit($comment, 140),
                            'workorder-view.php?id=' . $workOrderId,
                            'work_order',
                            $workOrderId
                        );
                    }
                }
            } catch (Throwable $e) {
                log_error('Comment notification failed: ' . $e->getMessage());
            }
        }

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function comments(int $workOrderId): array
    {
        return db()->all(
            'SELECT c.*, u.first_name, u.last_name, u.username, u.avatar_path, u.id AS user_id
             FROM {work_order_comments} c
             LEFT JOIN {users} u ON u.id = c.user_id
             WHERE c.work_order_id = ?
             ORDER BY c.created_at ASC, c.id ASC',
            [$workOrderId]
        );
    }

    public static function delete(int $id): bool
    {
        $workOrder = self::find($id);

        if ($workOrder === null) {
            return false;
        }

        db()->update('work_orders', [
            'deleted_at' => Dates::nowUtc(),
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        // A bell that leads to a record that no longer exists helps nobody.
        try {
            db()->run(
                "UPDATE {notifications} SET is_read = 1, read_at = ?
                 WHERE entity_type = 'work_order' AND entity_id = ? AND is_read = 0",
                [Dates::nowUtc(), $id]
            );
        } catch (Throwable $e) {
            // Not worth failing the delete over.
        }

        Audit::deleted('work_order', $id, 'Deleted ' . (string) $workOrder['wo_number']);

        return true;
    }

    /**
     * Counts per status, for the board and the filter strip.
     *
     * @return array<string, int>
     */
    public static function statusCounts(): array
    {
        $rows = db()->pairs(
            'SELECT status, COUNT(*) FROM {work_orders} WHERE deleted_at IS NULL GROUP BY status'
        );

        $out = [];

        foreach (array_keys(Status::options('workorder')) as $status) {
            $out[$status] = (int) ($rows[$status] ?? 0);
        }

        return $out;
    }

    /**
     * Users who can be assigned work.
     *
     * @return array<int, string>
     */
    public static function assigneeOptions(): array
    {
        $users = db()->all(
            "SELECT id, first_name, last_name, username, role
             FROM {users}
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY last_name ASC, first_name ASC"
        );

        $out = [];

        foreach ($users as $user) {
            // Only offer people who could actually do the work.
            if (!\App\Acl::can('workorders.edit', $user)) {
                continue;
            }

            $name = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
            $out[(int) $user['id']] = $name !== '' ? $name : (string) $user['username'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function forExport(array $filters): array
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->all(
            "SELECT w.wo_number, a.asset_tag, a.name AS asset_name, w.title, w.description,
                    w.priority, w.status, w.source,
                    CONCAT(COALESCE(r.first_name,''), ' ', COALESCE(r.last_name,'')) AS reported_by,
                    CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS assigned_to,
                    w.created_at, w.due_date, w.started_at, w.completed_at,
                    w.downtime_minutes, w.actual_hours, w.resolution
             FROM {work_orders} w
             LEFT JOIN {assets} a ON a.id = w.asset_id
             LEFT JOIN {users} r ON r.id = w.reported_by
             LEFT JOIN {users} u ON u.id = w.assigned_to
             WHERE {$where}
             ORDER BY w.created_at DESC",
            $params
        );
    }
}
