<?php

declare(strict_types=1);

namespace App\Models;

use App\Acl;
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
        $log = db()->one('SELECT title, work_performed FROM {maintenance_logs} WHERE id = ?', [$logId]);

        self::finish($workOrderId, [
            'resolution' => $log === null ? '' : trim((string) ($log['work_performed'] ?: $log['title'])),
            'note'       => 'Finished by a maintenance log. See the job record for details.',
        ]);
    }

    /**
     * Say the job is finished.
     *
     * The one route every "done" goes through — the button on the work order,
     * the button on a list row, and saving a maintenance log against it — so
     * the machine coming back into service, the comment, the notification and
     * the Slack post happen once and happen the same way.
     *
     * @param  array{resolution?: string, note?: string, back_in_service?: bool, downtime?: int|null} $opts
     * @return bool false when there was nothing to finish
     */
    public static function finish(int $workOrderId, array $opts = []): bool
    {
        $workOrder = self::find($workOrderId);

        // Already closed: a second tap on a slow phone must not write a second
        // close, a second Slack post and a second round of notifications.
        if ($workOrder === null || Status::isClosedWorkOrder((string) $workOrder['status'])) {
            return false;
        }

        $update     = ['status' => 'completed'];
        $resolution = trim((string) ($opts['resolution'] ?? ''));

        // What was done, in order of who said it best: what was typed now,
        // then the newest job logged against this order, then whatever is
        // already on the record. A sentence somebody wrote is never blanked
        // and never overwritten by a log title.
        if ($resolution === '' && trim((string) ($workOrder['resolution'] ?? '')) === '') {
            $log = db()->one(
                'SELECT work_performed, title FROM {maintenance_logs}
                 WHERE work_order_id = ? AND deleted_at IS NULL
                 ORDER BY performed_at DESC, id DESC LIMIT 1',
                [$workOrderId]
            );

            if ($log !== null) {
                $resolution = trim((string) ($log['work_performed'] ?: $log['title']));
            }
        }

        if ($resolution !== '') {
            $update['resolution'] = mb_substr($resolution, 0, 5000, 'UTF-8');
        }

        // The record should say who fixed it, even if nobody ever assigned it.
        if (empty($workOrder['assigned_to']) && Auth::id() !== null) {
            $update['assigned_to'] = Auth::id();
        }

        if (isset($opts['downtime']) && $opts['downtime'] !== null) {
            $update['downtime_minutes'] = min(max(0, (int) $opts['downtime']), 525600);
        }

        self::update($workOrderId, $update);

        if (!empty($opts['note'])) {
            self::addComment($workOrderId, (string) $opts['note'], false);
        }

        // The kart this work order took off the track goes back on it — asked
        // for, and decided here rather than taken on trust from the form, so
        // a forged post cannot put a ride back in front of guests.
        if (!empty($opts['back_in_service'])
            && !empty($workOrder['asset_id'])
            && self::mayReturnToService($workOrder)) {
            try {
                Asset::changeStatus(
                    (int) $workOrder['asset_id'],
                    'in_service',
                    'Back in service after ' . (string) $workOrder['wo_number']
                );
            } catch (Throwable $e) {
                log_error('Return to service after finishing a work order failed: ' . $e->getMessage());
            }
        }

        // Whoever runs the workshop hears about every job somebody else
        // finished. This is the oversight that replaces the old rule where
        // only a manager could close anything.
        if (!Acl::can('workorders.assign')) {
            try {
                $fresh = self::find($workOrderId);
                Notifier::pushToRole(
                    'workorders.assign',
                    'wo_updated',
                    'Finished by ' . (user_name() ?: 'a technician') . ': ' . (string) $workOrder['title'],
                    (string) $workOrder['wo_number']
                        . (trim((string) ($fresh['resolution'] ?? '')) !== ''
                            ? ' — ' . Str::limit((string) $fresh['resolution'], 120)
                            : ''),
                    'workorder-view.php?id=' . $workOrderId,
                    'work_order',
                    $workOrderId,
                    false,
                    Auth::id()
                );
            } catch (Throwable $e) {
                log_error('Work order finished notification failed: ' . $e->getMessage());
            }
        }

        return true;
    }

    /**
     * A job was logged against this order and it is staying open. Put a line
     * in the thread so the work is visible on the record, and move it along
     * from "nobody has touched it" if it was still sitting untouched.
     */
    public static function noteWorkLogged(int $workOrderId, int $logId): void
    {
        $workOrder = self::find($workOrderId);

        if ($workOrder === null || Status::isClosedWorkOrder((string) $workOrder['status'])) {
            return;
        }

        $log = db()->one('SELECT title FROM {maintenance_logs} WHERE id = ?', [$logId]);

        self::addComment(
            $workOrderId,
            (user_name() ?: 'Somebody') . ' logged work against this'
            . ($log === null ? '' : ': ' . Str::limit((string) $log['title'], 120))
            . '. It is still open.',
            false
        );

        // Somebody is clearly on it now.
        if (in_array((string) $workOrder['status'], ['open', 'assigned'], true)) {
            $update = ['status' => 'in_progress'];

            if (empty($workOrder['assigned_to']) && Auth::id() !== null) {
                $update['assigned_to'] = Auth::id();
            }

            self::update($workOrderId, $update);
        }
    }

    /**
     * The mechanic has finished, but this site wants a manager to sign work
     * off. Keep everything they said, say so on the record, and tell the
     * people who run the workshop that it is waiting for them.
     *
     * No new status: the vocabulary is fixed, and "somebody says it is done"
     * is a note and a message, not a seventh state.
     */
    public static function markFinishedPendingSignOff(int $workOrderId, string $resolution = '', ?int $downtime = null): bool
    {
        $workOrder = self::find($workOrderId);

        if ($workOrder === null || Status::isClosedWorkOrder((string) $workOrder['status'])) {
            return false;
        }

        $who    = user_name() ?: 'A technician';
        $update = [];

        if (trim($resolution) !== '') {
            $update['resolution'] = mb_substr(trim($resolution), 0, 5000, 'UTF-8');
        }

        if ($downtime !== null) {
            $update['downtime_minutes'] = min(max(0, $downtime), 525600);
        }

        // Put their name on it, so the manager knows who to ask.
        if (empty($workOrder['assigned_to']) && Auth::id() !== null) {
            $update['assigned_to'] = Auth::id();
        }

        if ((string) $workOrder['status'] !== 'in_progress') {
            $update['status'] = 'in_progress';
        }

        if ($update !== []) {
            self::update($workOrderId, $update);
        }

        self::addComment(
            $workOrderId,
            $who . ' says this job is finished and it is ready to be signed off.'
            . (trim($resolution) !== '' ? ' ' . trim($resolution) : ''),
            false
        );

        try {
            Notifier::pushToRole(
                'workorders.assign',
                'wo_updated',
                'Ready to sign off: ' . (string) $workOrder['title'],
                (string) $workOrder['wo_number'] . ' — finished by ' . $who,
                'workorder-view.php?id=' . $workOrderId,
                'work_order',
                $workOrderId,
                false,
                Auth::id()
            );
        } catch (Throwable $e) {
            log_error('Ready-to-sign-off notification failed: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Should finishing this order offer to put its machine back in service?
     *
     * Only when the machine is actually down, only when this order is what
     * took it down, and never while another open order still holds it there.
     *
     * @param array<string, mixed> $workOrder
     */
    public static function mayReturnToService(array $workOrder): bool
    {
        if (empty($workOrder['asset_id']) || (string) ($workOrder['asset_status'] ?? '') === 'in_service') {
            return false;
        }

        if ((int) ($workOrder['took_out_of_service'] ?? 0) !== 1) {
            return false;
        }

        return self::othersHoldingOutOfService((int) $workOrder['asset_id'], (int) $workOrder['id']) === 0;
    }

    /** Is the machine still held down by some OTHER unfinished work order? */
    public static function othersHoldingOutOfService(int $assetId, int $exceptWorkOrderId): int
    {
        return db()->count(
            "SELECT COUNT(*) FROM {work_orders}
             WHERE asset_id = ? AND id <> ? AND deleted_at IS NULL
               AND (took_out_of_service = 1 OR is_safety_issue = 1)
               AND status NOT IN ('completed','cancelled')",
            [$assetId, $exceptWorkOrderId]
        );
    }

    /**
     * Put this person's own name on the job and start the clock. The assignee
     * is always whoever is signed in — this is never a way to assign somebody
     * else, which stays behind 'workorders.assign'.
     */
    public static function claim(int $workOrderId): bool
    {
        $workOrder = self::find($workOrderId);
        $userId    = Auth::id();

        if ($workOrder === null || $userId === null
            || Status::isClosedWorkOrder((string) $workOrder['status'])) {
            return false;
        }

        $had = (int) ($workOrder['assigned_to'] ?? 0);

        self::update($workOrderId, ['assigned_to' => $userId, 'status' => 'in_progress']);

        if ($had > 0 && $had !== $userId) {
            self::addComment(
                $workOrderId,
                'Taken over by ' . (user_name() ?: 'somebody else') . '.',
                false
            );
        }

        return true;
    }

    /**
     * Put it back on the pile: the way out of a mis-tapped "I'm on it".
     */
    public static function handBack(int $workOrderId): bool
    {
        $workOrder = self::find($workOrderId);

        if ($workOrder === null || Status::isClosedWorkOrder((string) $workOrder['status'])) {
            return false;
        }

        self::update($workOrderId, ['assigned_to' => null, 'status' => 'open']);

        return true;
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
    public static function assigneeOptions(?int $keepUserId = null): array
    {
        $users = db()->all(
            "SELECT id, first_name, last_name, username, role, is_active
             FROM {users}
             WHERE deleted_at IS NULL AND (is_active = 1 OR id = ?)
             ORDER BY last_name ASC, first_name ASC",
            [$keepUserId ?? 0]
        );

        $out = [];

        foreach ($users as $user) {
            $isKept = $keepUserId !== null && (int) $user['id'] === $keepUserId;

            // Only offer people who could actually do the work — but never
            // drop whoever this work order is already assigned to, or the
            // picker would silently unassign them the next time it is saved.
            if (!$isKept && !\App\Acl::can('workorders.edit', $user)) {
                continue;
            }

            $name = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
            $name = $name !== '' ? $name : (string) $user['username'];

            if ($isKept && (int) $user['is_active'] !== 1) {
                $name .= ' (no longer signs in)';
            }

            $out[(int) $user['id']] = $name;
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
