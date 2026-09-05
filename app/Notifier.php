<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * In-app notifications (the bell in the header), with optional email.
 *
 * Notifications are deduplicated: the scheduled task runs repeatedly and must
 * not tell a technician sixteen times that the same PM is overdue.
 */
final class Notifier
{
    private function __construct()
    {
    }

    /**
     * Create a notification for one user.
     *
     * Returns the new id, or 0 when it was suppressed as a duplicate.
     */
    public static function push(
        int $userId,
        string $type,
        string $title,
        string $message = '',
        string $link = '',
        string $entityType = '',
        ?int $entityId = null,
        bool $email = false
    ): int {
        if ($userId <= 0) {
            return 0;
        }

        try {
            // Suppress an identical unread notification for the same record.
            if ($entityType !== '' && $entityId !== null) {
                $existing = db()->value(
                    'SELECT id FROM {notifications}
                     WHERE user_id = ? AND type = ? AND entity_type = ? AND entity_id = ? AND is_read = 0
                     LIMIT 1',
                    [$userId, $type, $entityType, $entityId]
                );

                if ($existing !== null) {
                    return 0;
                }
            }

            $id = db()->insert('notifications', [
                'user_id'     => $userId,
                'type'        => $type,
                'title'       => mb_substr($title, 0, 191, 'UTF-8'),
                'message'     => mb_substr($message, 0, 500, 'UTF-8'),
                'link'        => mb_substr($link, 0, 255, 'UTF-8'),
                'entity_type' => mb_substr($entityType, 0, 50, 'UTF-8'),
                'entity_id'   => $entityId,
                'is_read'     => 0,
                'created_at'  => Dates::nowUtc(),
            ]);
        } catch (Throwable $e) {
            log_error('Notification insert failed: ' . $e->getMessage());

            return 0;
        }

        if ($email) {
            self::emailIfEnabled($userId, $title, $message, $link);
        }

        return $id;
    }

    /**
     * Notify several users at once.
     *
     * @param  list<int> $userIds
     * @return int       how many were actually created
     */
    public static function pushMany(
        array $userIds,
        string $type,
        string $title,
        string $message = '',
        string $link = '',
        string $entityType = '',
        ?int $entityId = null,
        bool $email = false
    ): int {
        $created = 0;

        foreach (array_unique($userIds) as $userId) {
            if (self::push((int) $userId, $type, $title, $message, $link, $entityType, $entityId, $email) > 0) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Notify everyone holding a permission — used for unassigned work and
     * low-stock alerts, which are nobody's job in particular.
     */
    public static function pushToRole(
        string $permission,
        string $type,
        string $title,
        string $message = '',
        string $link = '',
        string $entityType = '',
        ?int $entityId = null
    ): int {
        try {
            $users = db()->all('SELECT id, role FROM {users} WHERE is_active = 1 AND deleted_at IS NULL');
        } catch (Throwable $e) {
            return 0;
        }

        $ids = [];

        foreach ($users as $user) {
            if (Acl::can($permission, ['id' => $user['id'], 'role' => $user['role'], 'is_active' => 1])) {
                $ids[] = (int) $user['id'];
            }
        }

        return self::pushMany($ids, $type, $title, $message, $link, $entityType, $entityId);
    }

    /**
     * A user's notifications, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function forUser(int $userId, bool $onlyUnread = false, int $limit = 25): array
    {
        $limit = max(1, min(200, $limit));

        $sql = 'SELECT * FROM {notifications} WHERE user_id = ?';

        if ($onlyUnread) {
            $sql .= ' AND is_read = 0';
        }

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $limit;

        try {
            return db()->all($sql, [$userId]);
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function unreadCount(?int $userId = null): int
    {
        $userId = $userId ?? Auth::id();

        if ($userId === null) {
            return 0;
        }

        try {
            return db()->count(
                'SELECT COUNT(*) FROM {notifications} WHERE user_id = ? AND is_read = 0',
                [$userId]
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Mark one notification read. Scoped to the owner so ids cannot be probed. */
    public static function markRead(int $id, int $userId): bool
    {
        try {
            return db()->update(
                'notifications',
                ['is_read' => 1, 'read_at' => Dates::nowUtc()],
                ['id' => $id, 'user_id' => $userId]
            ) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function markAllRead(int $userId): int
    {
        try {
            return db()->run(
                'UPDATE {notifications} SET is_read = 1, read_at = ? WHERE user_id = ? AND is_read = 0',
                [Dates::nowUtc(), $userId]
            )->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function delete(int $id, int $userId): bool
    {
        try {
            return db()->delete('notifications', ['id' => $id, 'user_id' => $userId]) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Clear every read notification for a user. */
    public static function clearRead(int $userId): int
    {
        try {
            return db()->run(
                'DELETE FROM {notifications} WHERE user_id = ? AND is_read = 1',
                [$userId]
            )->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Send the notification by email too, when the site and the user both
     * allow it. Failure is logged, never surfaced.
     */
    public static function emailIfEnabled(int $userId, string $subject, string $message, string $link = ''): void
    {
        if (!Settings::mailEnabled()) {
            return;
        }

        try {
            $user = db()->one(
                'SELECT email, first_name, last_name, notify_email FROM {users}
                 WHERE id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
                [$userId]
            );
        } catch (Throwable $e) {
            return;
        }

        if ($user === null || !(int) $user['notify_email'] || (string) $user['email'] === '') {
            return;
        }

        $absolute = $link === '' ? '' : (Str::startsWith($link, 'http') ? $link : absolute_url($link));

        $body = '<p>' . e($message) . '</p>';

        if ($absolute !== '') {
            $body .= '<p><a href="' . e($absolute) . '" class="button">Open in ' . e(Settings::siteName()) . '</a></p>';
        }

        Mailer::send((string) $user['email'], $subject, $body);
    }

    /** Delete read notifications older than N days. Called by cron.php. */
    public static function prune(int $days = 60): int
    {
        try {
            return db()->run(
                'DELETE FROM {notifications} WHERE is_read = 1 AND created_at < ?',
                [gmdate(Dates::DB_FORMAT, time() - ($days * 86400))]
            )->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // Domain events
    // -------------------------------------------------------------------------

    /**
     * Tell someone a work order is now theirs.
     *
     * @param array<string, mixed> $workOrder
     */
    public static function workOrderAssigned(array $workOrder, int $assigneeId): void
    {
        if ($assigneeId <= 0 || $assigneeId === Auth::id()) {
            // No point telling someone they assigned something to themselves.
            return;
        }

        self::push(
            $assigneeId,
            'wo_assigned',
            'Work order assigned to you: ' . (string) $workOrder['wo_number'],
            (string) $workOrder['title'],
            'workorder-view.php?id=' . (int) $workOrder['id'],
            'work_order',
            (int) $workOrder['id'],
            true
        );
    }

    /**
     * @param array<string, mixed> $inspection a row from Inspection::find(),
     *                                         which already carries asset_name
     */
    public static function inspectionFailed(array $inspection): void
    {
        $failed = (int) $inspection['failed_count'];

        self::pushToRole(
            'workorders.assign',
            'inspection_failed',
            'Inspection failed: ' . (string) ($inspection['asset_name'] ?? 'a machine'),
            $failed . ' item' . ($failed === 1 ? '' : 's') . ' failed'
            . ((int) $inspection['critical_failed'] ? ', including a safety-critical one.' : '.'),
            'inspection-view.php?id=' . (int) $inspection['id'],
            'inspection',
            (int) $inspection['id']
        );
    }

    /**
     * @param array<string, mixed> $part
     */
    public static function lowStock(array $part): void
    {
        if (!Settings::bool('low_stock_alerts', true)) {
            return;
        }

        self::pushToRole(
            'parts.manage',
            'low_stock',
            'Low stock: ' . (string) $part['name'],
            decimal($part['quantity_on_hand']) . ' ' . (string) $part['unit_of_measure']
            . ' remaining, reorder level is ' . decimal($part['reorder_level']) . '.',
            'part-view.php?id=' . (int) $part['id'],
            'part',
            (int) $part['id']
        );
    }
}
