<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Application settings, stored in the settings table and edited from the
 * Settings screen.
 *
 * Read on almost every request, so the whole table is loaded once per request
 * and mirrored to a small PHP file in storage/cache. The file cache is
 * invalidated on every write, and falls back silently to the database if the
 * cache directory is not writable.
 */
final class Settings
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /** @var array<string, array<string, mixed>>|null full rows, loaded on demand */
    private static ?array $meta = null;

    private function __construct()
    {
    }

    private static function cacheFile(): string
    {
        return rtrim((string) (defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir()), '/') . '/cache/settings.php';
    }

    /**
     * Every setting as key => raw string value.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $file = self::cacheFile();

        if (is_file($file) && is_readable($file)) {
            try {
                /** @psalm-suppress UnresolvableInclude */
                $cached = include $file;

                if (is_array($cached)) {
                    self::$cache = $cached;

                    return self::$cache;
                }
            } catch (Throwable $e) {
                // Corrupt cache: fall through and rebuild from the database.
            }
        }

        return self::refresh();
    }

    /**
     * Reload from the database and rewrite the cache file.
     *
     * @return array<string, string>
     */
    public static function refresh(): array
    {
        $values = [];

        try {
            $rows = db()->all('SELECT setting_key, setting_value FROM {settings}');

            foreach ($rows as $row) {
                $values[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        } catch (Throwable $e) {
            // Before installation, or if the table is missing. Behave as empty
            // rather than fataling, so the installer and error pages still work.
            self::$cache = [];

            return self::$cache;
        }

        self::$cache = $values;
        self::writeCache($values);

        return $values;
    }

    /**
     * @param array<string, string> $values
     */
    private static function writeCache(array $values): void
    {
        $file = self::cacheFile();
        $dir  = dirname($file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        $php = "<?php\n"
             . "// RideLog settings cache. Generated automatically — safe to delete.\n"
             . 'return ' . var_export($values, true) . ";\n";

        // Write to a temporary file then rename, so a concurrent request never
        // reads a half-written cache.
        $tmp = $file . '.' . getmypid() . '.tmp';

        if (@file_put_contents($tmp, $php, LOCK_EX) !== false) {
            if (!@rename($tmp, $file)) {
                @unlink($tmp);
            }

            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
        }
    }

    /** Drop both the in-memory and on-disk caches. */
    public static function flush(): void
    {
        self::$cache = null;
        self::$meta  = null;

        $file = self::cacheFile();

        if (is_file($file)) {
            @unlink($file);

            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
        }
    }

    /**
     * Read a setting.
     *
     * @param  mixed $default returned when the key is absent OR stored empty
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $all = self::all();

        if (!array_key_exists($key, $all)) {
            return $default;
        }

        $value = $all[$key];

        // An empty stored value falls back to the default, which is what a site
        // owner means when they clear a field like "from address".
        if ($value === '' && $default !== null) {
            return $default;
        }

        return $value;
    }

    /** A setting read as a boolean. "1", "true", "yes" and "on" are all true. */
    public static function bool(string $key, bool $default = false): bool
    {
        $all = self::all();

        if (!array_key_exists($key, $all) || $all[$key] === '') {
            return $default;
        }

        return in_array(strtolower($all[$key]), ['1', 'true', 'yes', 'on'], true);
    }

    /** A setting read as an integer, clamped to an optional range. */
    public static function int(string $key, int $default = 0, ?int $min = null, ?int $max = null): int
    {
        $all = self::all();

        if (!array_key_exists($key, $all) || $all[$key] === '' || !is_numeric($all[$key])) {
            $value = $default;
        } else {
            $value = (int) $all[$key];
        }

        if ($min !== null && $value < $min) {
            $value = $min;
        }

        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }

    /** A setting read as a float. */
    public static function float(string $key, float $default = 0.0): float
    {
        $all = self::all();

        if (!array_key_exists($key, $all) || $all[$key] === '' || !is_numeric($all[$key])) {
            return $default;
        }

        return (float) $all[$key];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * Write one setting. Creates the row if it does not exist.
     *
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        self::setMany([$key => $value]);
    }

    /**
     * Write several settings in one transaction.
     *
     * @param array<string, mixed> $values
     */
    public static function setMany(array $values): void
    {
        if ($values === []) {
            return;
        }

        $db = db();

        $db->transaction(static function (Database $db) use ($values): void {
            foreach ($values as $key => $value) {
                $stored = self::stringify($value);

                $exists = $db->value(
                    'SELECT id FROM {settings} WHERE setting_key = ? LIMIT 1',
                    [$key]
                );

                if ($exists === null) {
                    $db->insert('settings', [
                        'setting_key'   => $key,
                        'setting_value' => $stored,
                        'setting_type'  => 'string',
                        'setting_group' => 'general',
                    ]);
                } else {
                    $db->update('settings', ['setting_value' => $stored], ['setting_key' => $key]);
                }
            }
        });

        self::flush();
    }

    /**
     * Normalise a PHP value for storage.
     *
     * @param mixed $value
     */
    private static function stringify($value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return (string) json_encode($value);
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /** Remove a setting entirely. */
    public static function forget(string $key): void
    {
        try {
            db()->delete('settings', ['setting_key' => $key]);
        } catch (Throwable $e) {
            return;
        }

        self::flush();
    }

    /**
     * Full setting rows for one group, ordered for the Settings screen.
     *
     * @return list<array<string, mixed>>
     */
    public static function group(string $group): array
    {
        return db()->all(
            'SELECT * FROM {settings} WHERE setting_group = ? ORDER BY sort_order ASC, setting_key ASC',
            [$group]
        );
    }

    /**
     * All editable groups in display order, excluding the internal one.
     *
     * @return array<string, string> group key => label
     */
    public static function groups(): array
    {
        return [
            'general'      => 'General',
            'localization' => 'Localization',
            'maintenance'  => 'Maintenance',
            'uploads'      => 'Uploads',
            'email'        => 'Email',
            'security'     => 'Security',
            'branding'     => 'Branding',
        ];
    }

    /**
     * The metadata row for a key (type, label, description), or null.
     *
     * @return array<string, mixed>|null
     */
    public static function meta(string $key): ?array
    {
        if (self::$meta === null) {
            self::$meta = [];

            try {
                foreach (db()->all('SELECT * FROM {settings}') as $row) {
                    self::$meta[(string) $row['setting_key']] = $row;
                }
            } catch (Throwable $e) {
                self::$meta = [];
            }
        }

        return self::$meta[$key] ?? null;
    }

    // -------------------------------------------------------------------------
    // Frequently-read settings, with the right type and sensible clamping
    // -------------------------------------------------------------------------

    public static function siteName(): string
    {
        $name = trim((string) self::get('site_name', ''));

        return $name !== '' ? $name : 'RideLog';
    }

    public static function organizationName(): string
    {
        $name = trim((string) self::get('organization_name', ''));

        return $name !== '' ? $name : self::siteName();
    }

    public static function currency(): string
    {
        $symbol = (string) self::get('currency_symbol', '$');

        return $symbol !== '' ? $symbol : '$';
    }

    public static function perPage(): int
    {
        return self::int('items_per_page', 25, 5, 200);
    }

    public static function sessionTimeoutMinutes(): int
    {
        return self::int('session_timeout_minutes', 480, 5, 43200);
    }

    public static function passwordMinLength(): int
    {
        return self::int('password_min_length', 8, 8, 128);
    }

    /**
     * Attachment size cap in bytes, never above what the host actually allows.
     */
    public static function maxUploadBytes(): int
    {
        $configured = self::int('max_upload_mb', 8, 1, 512) * 1024 * 1024;
        $hostLimit  = self::hostUploadLimitBytes();

        return $hostLimit > 0 ? min($configured, $hostLimit) : $configured;
    }

    /**
     * The smaller of upload_max_filesize and post_max_size, in bytes.
     * Returns 0 when neither is set.
     */
    public static function hostUploadLimitBytes(): int
    {
        $upload = self::iniToBytes((string) ini_get('upload_max_filesize'));
        $post   = self::iniToBytes((string) ini_get('post_max_size'));

        $limits = array_filter([$upload, $post], static function (int $v): bool {
            return $v > 0;
        });

        return $limits === [] ? 0 : (int) min($limits);
    }

    /** Convert a php.ini shorthand value like "8M" into bytes. */
    public static function iniToBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit   = strtolower(substr($value, -1));
        $number = (float) $value;

        switch ($unit) {
            case 'g':
                return (int) ($number * 1024 * 1024 * 1024);
            case 'm':
                return (int) ($number * 1024 * 1024);
            case 'k':
                return (int) ($number * 1024);
            default:
                return (int) $number;
        }
    }

    public static function cronToken(): string
    {
        return (string) self::get('cron_token', '');
    }

    public static function mailEnabled(): bool
    {
        return self::bool('mail_enabled', false);
    }

    public static function schemaVersion(): string
    {
        return (string) self::get('schema_version', '0.0.0');
    }
}
