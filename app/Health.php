<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Is this copy of RideLog healthy?
 *
 * The person who set it up is not going to read server logs. This gathers
 * the handful of things that actually go wrong on shared hosting — a folder
 * that stopped being writable, a cron job that was never set up, a PHP
 * upgrade that dropped an extension — into one page of plain answers.
 *
 * Every check is wrapped so that a failure to *measure* something never
 * takes the page down.
 */
final class Health
{
    private function __construct()
    {
    }

    /**
     * @return array{
     *   checks: list<array{label: string, value: string, state: string, hint: string}>,
     *   facts: list<array{label: string, value: string}>,
     *   counts: list<array{label: string, value: string, icon: string}>
     * }
     */
    public static function report(): array
    {
        $checks = [];

        foreach ([
            [self::class, 'php'],
            [self::class, 'extensions'],
            [self::class, 'database'],
            [self::class, 'folders'],
            [self::class, 'installer'],
            [self::class, 'https'],
            [self::class, 'nightlyJob'],
            [self::class, 'errors'],
            [self::class, 'diskSpace'],
            [self::class, 'labourRate'],
            [self::class, 'email'],
        ] as $check) {
            try {
                $result = $check();

                if ($result !== null) {
                    $checks[] = $result;
                }
            } catch (Throwable $e) {
                // A check that cannot run is reported, not hidden.
                $checks[] = self::row('Check failed', $e->getMessage(), 'warn', '');
            }
        }

        return [
            'checks' => $checks,
            'facts'  => self::facts(),
            'counts' => self::counts(),
        ];
    }

    /**
     * @return array{label: string, value: string, state: string, hint: string}
     */
    private static function row(string $label, string $value, string $state, string $hint): array
    {
        return ['label' => $label, 'value' => $value, 'state' => $state, 'hint' => $hint];
    }

    // -------------------------------------------------------------------------
    // Checks
    // -------------------------------------------------------------------------

    /** @return array<string, string> */
    private static function php(): array
    {
        $version = PHP_VERSION;
        $ok      = version_compare($version, '8.0.0', '>=');
        $current = version_compare($version, '8.1.0', '>=');

        return self::row(
            'PHP',
            $version,
            $ok ? ($current ? 'ok' : 'warn') : 'fail',
            $ok
                ? ($current ? 'Fine.' : 'Works, but PHP 8.0 no longer receives security fixes. Pick a newer version under "Select PHP Version" in cPanel.')
                : 'RideLog needs PHP 8.0 or newer. Change it under "Select PHP Version" in cPanel.'
        );
    }

    /** @return array<string, string> */
    private static function extensions(): array
    {
        $required = ['pdo_mysql' => 'talks to the database', 'mbstring' => 'handles accented text',
                     'json' => 'reads and writes data', 'openssl' => 'keeps passwords and tokens safe',
                     'fileinfo' => 'checks uploaded files are what they claim'];
        $optional = ['gd' => 'resizes photos', 'zip' => 'makes the full export a single ZIP file',
                     'intl' => 'formats numbers and dates for the locale'];

        $missing = [];

        foreach ($required as $ext => $why) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext . ' (' . $why . ')';
            }
        }

        $missingOptional = [];

        foreach ($optional as $ext => $why) {
            if (!extension_loaded($ext)) {
                $missingOptional[] = $ext . ' (' . $why . ')';
            }
        }

        if ($missing !== []) {
            return self::row('PHP extensions', 'Missing: ' . implode(', ', $missing), 'fail',
                'Turn these on under "Select PHP Version" → Extensions in cPanel.');
        }

        if ($missingOptional !== []) {
            return self::row('PHP extensions', 'All required ones present. Not on: ' . implode(', ', $missingOptional), 'warn',
                'Everything works without them, but they are worth turning on under "Select PHP Version" → Extensions.');
        }

        return self::row('PHP extensions', 'All present', 'ok', '');
    }

    /** @return array<string, string> */
    private static function database(): array
    {
        $db      = db();
        $version = (string) $db->value('SELECT VERSION()', [], '');
        $prefix  = $db->prefix();
        $like    = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';

        $stats = $db->one(
            'SELECT COUNT(*) AS tables_n, COALESCE(SUM(data_length + index_length), 0) AS bytes
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name LIKE ?',
            [$like]
        );

        $tables = (int) ($stats['tables_n'] ?? 0);
        $bytes  = (int) ($stats['bytes'] ?? 0);

        return self::row(
            'Database',
            ($version !== '' ? $version . ' · ' : '') . $tables . ' tables · ' . Str::formatBytes($bytes),
            $tables > 0 ? 'ok' : 'fail',
            $tables > 0 ? 'Connected. Back it up now and then from cPanel → Backup, or use the export below.'
                        : 'No RideLog tables were found with the prefix "' . $prefix . '".'
        );
    }

    /** @return array<string, string> */
    private static function folders(): array
    {
        $bad = [];

        foreach (['uploads' => UPLOAD_PATH, 'logs' => STORAGE_PATH . '/logs', 'cache' => STORAGE_PATH . '/cache'] as $name => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            if (!is_dir($path) || !is_writable($path)) {
                $bad[] = 'storage/' . $name;
            }
        }

        if ($bad !== []) {
            return self::row('Storage folders', 'Not writable: ' . implode(', ', $bad), 'fail',
                'Photos and logs cannot be saved. In cPanel → File Manager, set the folder permissions to 755 '
                . '(or 775) and make sure they are owned by your account.');
        }

        return self::row('Storage folders', 'Writable', 'ok', 'Photos, logs and the cache can all be written.');
    }

    /** @return array<string, string>|null */
    private static function installer(): ?array
    {
        $notes = [];

        if (is_dir(APP_ROOT . '/install')) {
            $notes[] = 'The install folder is still on the server. Delete it now that setup is finished.';
        }

        if (is_file(CONFIG_PATH . '/config.php') && is_writable(CONFIG_PATH . '/config.php')) {
            $notes[] = 'config/config.php is writable. Optional: set it to read-only (644) in File Manager once you are happy with the setup.';
        }

        if ($notes === []) {
            return self::row('Setup files', 'Tidy', 'ok', 'The installer is gone and the configuration is protected.');
        }

        return self::row('Setup files', 'Worth tidying', 'warn', implode(' ', $notes));
    }

    /** @return array<string, string> */
    private static function https(): array
    {
        $https = (string) ($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $https = $https || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $local = in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true);

        if ($https) {
            return self::row('Secure connection', 'HTTPS', 'ok', 'Passwords and photos travel encrypted.');
        }

        return self::row('Secure connection', 'Plain HTTP', $local ? 'info' : 'warn',
            $local
                ? 'Fine for a machine on the local network.'
                : 'Passwords are sent unencrypted. Turn on SSL in cPanel (AutoSSL is free) and set the site address to https.');
    }

    /** @return array<string, string> */
    private static function nightlyJob(): array
    {
        // cron.php writes an audit entry; the lighter hourly run from the
        // dashboard does not, so the two can be told apart.
        $full = db()->value(
            "SELECT MAX(created_at) FROM {audit_log} WHERE action = 'cron.run'",
            [],
            null
        );
        $light = (string) Settings::get('last_cron_run', '');

        if ($full !== null && $full !== '') {
            $age = (Dates::now()->getTimestamp() - (int) (Dates::parseUtc((string) $full)?->getTimestamp() ?? 0)) / 3600;

            if ($age <= 26) {
                return self::row('Nightly job', 'Ran ' . Dates::ago((string) $full), 'ok',
                    'Due dates, low stock warnings and tidying are all up to date.');
            }

            return self::row('Nightly job', 'Last ran ' . Dates::ago((string) $full), 'warn',
                'It should run every day. Check the cron job in cPanel is still there and the token in it matches Settings → Security.');
        }

        return self::row(
            'Nightly job',
            'Never run from cron',
            'warn',
            'Set up the cron job under Settings → Security. Until then RideLog does a lighter version of the job '
            . 'whenever somebody opens the dashboard'
            . ($light !== '' ? ' (last time ' . Dates::ago($light) . ')' : '')
            . ', but low stock warnings and tidying only happen in the real one.'
        );
    }

    /** @return array<string, string> */
    private static function errors(): array
    {
        $dir   = STORAGE_PATH . '/logs';
        $count = 0;
        $last  = '';

        for ($i = 0; $i < 7; $i++) {
            $file = $dir . '/error-' . gmdate('Y-m-d', time() - $i * 86400) . '.log';

            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $handle = fopen($file, 'rb');

            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                if ($line !== '' && $line[0] === '[') {
                    $count++;

                    if ($last === '') {
                        $last = Str::limit(trim($line), 160);
                    }
                }
            }

            fclose($handle);
        }

        if ($count === 0) {
            return self::row('Errors this week', 'None', 'ok', 'Nothing has gone wrong that RideLog noticed.');
        }

        return self::row(
            'Errors this week',
            $count . ' logged',
            $count > 20 ? 'warn' : 'info',
            'The details are in storage/logs. Most recent: ' . $last
        );
    }

    /** @return array<string, string>|null */
    private static function diskSpace(): ?array
    {
        $free = @disk_free_space(STORAGE_PATH);

        if ($free === false) {
            return null;
        }

        $free = (int) $free;

        if ($free < 200 * 1048576) {
            return self::row('Disk space', Str::formatBytes($free) . ' free', 'fail',
                'Almost full. Photos will stop saving. Free some space or ask your host for more.');
        }

        if ($free < 1024 * 1048576) {
            return self::row('Disk space', Str::formatBytes($free) . ' free', 'warn',
                'Getting low. Keep an eye on it, or ask your host for more.');
        }

        return self::row('Disk space', Str::formatBytes($free) . ' free', 'ok', '');
    }

    /** @return array<string, string>|null */
    private static function labourRate(): ?array
    {
        $rate = (float) Settings::get('default_labor_rate', 0);

        if ($rate > 0) {
            return null;
        }

        return self::row('Labour rate', 'Not set', 'info',
            'Technicians do not see money, so their jobs are costed from the default labour rate. '
            . 'While it is zero, labour on their jobs shows as free. Set it under Settings → Maintenance.');
    }

    /** @return array<string, string> */
    private static function email(): array
    {
        if (!Settings::mailEnabled()) {
            return self::row('Email', 'Off', 'info',
                'Notifications show inside RideLog only. Turn email on under Settings → Email if you want them in an inbox too.');
        }

        $transport = (string) Settings::get('mail_transport', 'mail');

        return self::row('Email', 'On, via ' . ($transport === 'smtp' ? 'SMTP (' . (string) Settings::get('smtp_host', '') . ')' : 'the server'), 'ok',
            'Send yourself a test under Settings → Email if you are not sure it works.');
    }

    // -------------------------------------------------------------------------
    // Plain facts
    // -------------------------------------------------------------------------

    /** @return list<array{label: string, value: string}> */
    private static function facts(): array
    {
        $facts = [
            ['label' => 'RideLog version', 'value' => RIDELOG_VERSION . ' (database ' . Settings::schemaVersion() . ')'],
            ['label' => 'Installed', 'value' => Dates::datetime((string) Settings::get('app_installed_at', ''), 'unknown')],
            ['label' => 'Server time now', 'value' => Dates::datetime(Dates::nowUtc()) . ' (' . Dates::displayZone()->getName() . ')'],
            ['label' => 'Largest upload allowed', 'value' => Str::formatBytes(Settings::hostUploadLimitBytes())],
            ['label' => 'Server software', 'value' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown')],
            ['label' => 'Site address', 'value' => absolute_url('')],
        ];

        try {
            $uploads = db()->one('SELECT COUNT(*) AS n, COALESCE(SUM(file_size), 0) AS bytes FROM {attachments}');
            $facts[] = [
                'label' => 'Photos and files',
                'value' => num((int) ($uploads['n'] ?? 0)) . ' · ' . Str::formatBytes((int) ($uploads['bytes'] ?? 0)),
            ];
        } catch (Throwable $e) {
            // Leave it out.
        }

        return $facts;
    }

    /** @return list<array{label: string, value: string, icon: string}> */
    private static function counts(): array
    {
        $queries = [
            ['Machines',     'assets',           'assets',          'deleted_at IS NULL'],
            ['Jobs logged',  'wrench',           'maintenance_logs', 'deleted_at IS NULL'],
            ['Work orders',  'work-order',       'work_orders',      'deleted_at IS NULL'],
            ['Inspections',  'clipboard-check',  'inspections',      '1=1'],
            ['Parts',        'package',          'parts',            'deleted_at IS NULL'],
            ['People',       'users',            'users',            'is_active = 1'],
        ];

        $out = [];

        foreach ($queries as [$label, $icon, $table, $where]) {
            try {
                $out[] = [
                    'label' => $label,
                    'icon'  => $icon,
                    'value' => num(db()->count('SELECT COUNT(*) FROM {' . $table . '} WHERE ' . $where)),
                ];
            } catch (Throwable $e) {
                // Skip a table that is not there.
            }
        }

        return $out;
    }
}
