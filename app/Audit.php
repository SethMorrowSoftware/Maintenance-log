<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * The audit trail.
 *
 * Every create, update and delete of a domain record writes a row here, along
 * with sign-ins, permission changes and denied access attempts. Anything that
 * looks like a credential is redacted before it is stored.
 *
 * Auditing must never break the operation it is recording, so every method
 * swallows its own errors and logs them instead.
 */
final class Audit
{
    /** Keys whose values are never written to the audit trail. */
    private const REDACT_PATTERN = '/pass|token|secret|hash|salt|_key$|^key$/i';

    /** Columns that change on every save and would only add noise. */
    private const IGNORE_COLUMNS = ['updated_at', 'created_at', 'updated_by', 'created_by'];

    private function __construct()
    {
    }

    /**
     * Write an audit row.
     *
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public static function record(
        string $action,
        string $entityType = '',
        ?int $entityId = null,
        string $description = '',
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $user = Auth::user();

            // For an update, store only what actually changed.
            if ($oldValues !== null && $newValues !== null) {
                [$oldValues, $newValues] = self::diff($oldValues, $newValues);

                // Nothing meaningful changed — do not clutter the trail.
                if ($newValues === []) {
                    return;
                }
            }

            db()->insert('audit_log', [
                'user_id'     => $user === null ? null : (int) $user['id'],
                'user_name'   => $user === null ? 'System' : Auth::name($user),
                'action'      => mb_substr($action, 0, 60, 'UTF-8'),
                'entity_type' => mb_substr($entityType, 0, 50, 'UTF-8'),
                'entity_id'   => $entityId,
                'description' => mb_substr($description, 0, 500, 'UTF-8'),
                'old_values'  => $oldValues === null ? null : self::encode($oldValues),
                'new_values'  => $newValues === null ? null : self::encode($newValues),
                'ip_address'  => Request::ip(),
                'user_agent'  => Request::userAgent(),
                'created_at'  => Dates::nowUtc(),
            ]);
        } catch (Throwable $e) {
            log_error('Audit write failed: ' . $e->getMessage(), ['action' => $action]);
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function created(string $entityType, int $entityId, string $description, array $values = []): void
    {
        self::record('create', $entityType, $entityId, $description, null, $values === [] ? null : self::clean($values));
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    public static function updated(string $entityType, int $entityId, string $description, array $before, array $after): void
    {
        self::record('update', $entityType, $entityId, $description, $before, $after);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function deleted(string $entityType, int $entityId, string $description, array $values = []): void
    {
        self::record('delete', $entityType, $entityId, $description, $values === [] ? null : self::clean($values), null);
    }

    public static function auth(string $action, ?int $userId, string $description): void
    {
        self::record('auth.' . $action, 'user', $userId, $description);
    }

    /**
     * Reduce two row snapshots to just the columns that differ.
     *
     * @param  array<string, mixed> $before
     * @param  array<string, mixed> $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $oldChanged = [];
        $newChanged = [];

        foreach ($after as $key => $newValue) {
            if (in_array($key, self::IGNORE_COLUMNS, true)) {
                continue;
            }

            $oldValue = $before[$key] ?? null;

            if (self::same($oldValue, $newValue)) {
                continue;
            }

            $oldChanged[$key] = self::scalarize($oldValue);
            $newChanged[$key] = self::scalarize($newValue);
        }

        return [self::clean($oldChanged), self::clean($newChanged)];
    }

    /**
     * Loose comparison that treats "5" and 5, and null and '', as unchanged —
     * because form input arrives as strings and the database returns typed values.
     *
     * @param mixed $a
     * @param mixed $b
     */
    private static function same($a, $b): bool
    {
        if ($a === null && ($b === null || $b === '')) {
            return true;
        }

        if ($b === null && ($a === null || $a === '')) {
            return true;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }

        return (string) self::scalarize($a) === (string) self::scalarize($b);
    }

    /**
     * @param  mixed $value
     * @return mixed
     */
    private static function scalarize($value)
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_object($value)) {
            return method_exists($value, '__toString') ? (string) $value : get_class($value);
        }

        return $value;
    }

    /**
     * Strip credential-shaped keys and truncate anything huge.
     *
     * @param  array<string, mixed> $values
     * @return array<string, mixed>
     */
    public static function clean(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;

            if (preg_match(self::REDACT_PATTERN, $key)) {
                $out[$key] = '[redacted]';
                continue;
            }

            if (is_string($value) && mb_strlen($value, 'UTF-8') > 1000) {
                $out[$key] = mb_substr($value, 0, 1000, 'UTF-8') . '… [truncated]';
                continue;
            }

            $out[$key] = self::scalarize($value);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function encode(array $values): string
    {
        $json = json_encode($values, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json === false ? '{}' : $json;
    }

    /**
     * Decode a stored value blob for display.
     *
     * @return array<string, mixed>
     */
    public static function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Recent activity for the dashboard feed.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 15): array
    {
        $limit = max(1, min(100, $limit));

        return db()->all(
            "SELECT a.*, u.username, u.first_name, u.last_name, u.avatar_path
             FROM {audit_log} a
             LEFT JOIN {users} u ON u.id = a.user_id
             WHERE a.action NOT IN ('access.denied', 'auth.login', 'auth.logout')
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT " . $limit
        );
    }

    /**
     * The trail for one record, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function forEntity(string $entityType, int $entityId, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        return db()->all(
            'SELECT a.*, u.username, u.first_name, u.last_name
             FROM {audit_log} a
             LEFT JOIN {users} u ON u.id = a.user_id
             WHERE a.entity_type = ? AND a.entity_id = ?
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT ' . $limit,
            [$entityType, $entityId]
        );
    }

    /**
     * Human label for an action code.
     */
    public static function actionLabel(string $action): string
    {
        $labels = [
            'create'                  => 'Created',
            'update'                  => 'Updated',
            'delete'                  => 'Deleted',
            'restore'                 => 'Restored',
            'auth.login'              => 'Signed in',
            'auth.logout'             => 'Signed out',
            'auth.failed'             => 'Failed sign-in',
            'auth.locked'             => 'Account locked',
            'auth.password_changed'   => 'Password changed',
            'auth.password_reset'     => 'Password reset',
            'auth.reset_requested'    => 'Password reset requested',
            'auth.remember_mismatch'  => 'Remember-me token rejected',
            'access.denied'           => 'Access denied',
            'settings.update'         => 'Settings changed',
            'export'                  => 'Exported data',
            'import'                  => 'Imported data',
            'status.change'           => 'Status changed',
            'meter.update'            => 'Meter updated',
            'stock.adjust'            => 'Stock adjusted',
            'install'                 => 'Installed',
            'upgrade'                 => 'Upgraded',
            'cron'                    => 'Scheduled task',
        ];

        if (isset($labels[$action])) {
            return $labels[$action];
        }

        return Str::humanize(str_replace('.', ' ', $action));
    }

    /**
     * The tone to render an action with: ok, warn, danger, info or muted.
     */
    public static function actionTone(string $action): string
    {
        if (strpos($action, 'delete') !== false) {
            return 'danger';
        }

        if ($action === 'access.denied' || $action === 'auth.locked' || $action === 'auth.remember_mismatch') {
            return 'warn';
        }

        if (strpos($action, 'create') !== false) {
            return 'ok';
        }

        if (strpos($action, 'auth.') === 0) {
            return 'muted';
        }

        return 'info';
    }

    /**
     * Every distinct action currently in the log, for the audit page filter.
     *
     * @return list<string>
     */
    public static function knownActions(): array
    {
        try {
            $values = db()->column('SELECT DISTINCT action FROM {audit_log} ORDER BY action ASC');
        } catch (Throwable $e) {
            return [];
        }

        return array_map('strval', $values);
    }

    /**
     * Every distinct entity type in the log, for the audit page filter.
     *
     * @return list<string>
     */
    public static function knownEntityTypes(): array
    {
        try {
            $values = db()->column(
                "SELECT DISTINCT entity_type FROM {audit_log} WHERE entity_type <> '' ORDER BY entity_type ASC"
            );
        } catch (Throwable $e) {
            return [];
        }

        return array_map('strval', $values);
    }

    /**
     * Delete rows older than the retention setting. Called by cron.php.
     * A retention of 0 keeps everything.
     */
    public static function prune(?int $days = null): int
    {
        $days = $days ?? Settings::int('audit_retention_days', 365, 0, 3650);

        if ($days <= 0) {
            return 0;
        }

        try {
            return db()->run(
                'DELETE FROM {audit_log} WHERE created_at < ?',
                [gmdate(Dates::DB_FORMAT, time() - ($days * 86400))]
            )->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
