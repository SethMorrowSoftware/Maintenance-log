<?php

declare(strict_types=1);

/**
 * RideLog — web installer.
 *
 * Six steps: Welcome, Requirements, Database, Administrator, Site settings,
 * Install. State lives in the session, so Back works and nothing is lost.
 *
 * The installer boots without configuration — bootstrap.php notices it is
 * running from install/ and skips the "redirect to the installer" branch —
 * and it refuses to run once config/installed.lock exists, so nobody can
 * reinstall over live data by visiting a URL.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Config;
use App\Database;
use App\Dates;
use App\SqlRunner;
use App\Str;
use App\Validator;

// -----------------------------------------------------------------------------
// Guard: never run over an existing installation
// -----------------------------------------------------------------------------

$configFile = CONFIG_PATH . '/config.php';
$lockFile   = CONFIG_PATH . '/installed.lock';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (is_file($lockFile)) {
    // The lock is written as the last act of a successful install, which would
    // otherwise hide the very page that tells the site owner what to do next.
    // Let the success screen through for the session that just ran the install.
    $justFinished = ($_GET['step'] ?? '') === 'done' && !empty($_SESSION['install_done']);

    if (!$justFinished) {
        render_locked();
        exit;
    }
}

// -----------------------------------------------------------------------------
// Step routing
// -----------------------------------------------------------------------------

$steps = [
    'welcome'      => 'Welcome',
    'requirements' => 'Requirements',
    'database'     => 'Database',
    'admin'        => 'Administrator',
    'site'         => 'Site details',
    'install'      => 'Install',
];

$stepKeys = array_keys($steps);
$step     = (string) ($_GET['step'] ?? 'welcome');

if (!isset($steps[$step])) {
    $step = 'welcome';
}

if (!isset($_SESSION['install']) || !is_array($_SESSION['install'])) {
    $_SESSION['install'] = [];
}

/** @var array<string, mixed> $state */
$state = &$_SESSION['install'];

$errors = [];
$posted = $_SERVER['REQUEST_METHOD'] === 'POST';

// A lightweight CSRF token of the installer's own — the app's Csrf class needs
// a configured application, which does not exist yet.
if (empty($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(32));
}

$token = (string) $_SESSION['install_token'];

if ($posted && !hash_equals($token, (string) ($_POST['_token'] ?? ''))) {
    $errors['_form'] = 'Your session expired. Please try that step again.';
    $posted = false;
}

// -----------------------------------------------------------------------------
// Requirement checks
// -----------------------------------------------------------------------------

/**
 * @return list<array{name: string, detail: string, value: string, status: string}>
 */
function requirement_checks(): array
{
    $checks = [];

    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $checks[] = [
        'name'   => 'PHP 8.0 or newer',
        'detail' => $phpOk
            ? 'Your PHP version is supported.'
            : 'RideLog needs PHP 8.0 or newer. In cPanel, open "MultiPHP Manager" and select a newer version for this domain.',
        'value'  => PHP_VERSION,
        'status' => $phpOk ? 'ok' : 'fail',
    ];

    $required = [
        'pdo_mysql' => 'Connects to your MySQL database.',
        'mbstring'  => 'Handles text correctly in any language.',
        'json'      => 'Encodes data for the interface.',
        'session'   => 'Keeps people signed in.',
        'openssl'   => 'Generates secure tokens.',
        'fileinfo'  => 'Verifies what an uploaded file really is.',
        'pcre'      => 'Pattern matching used throughout.',
    ];

    foreach ($required as $extension => $why) {
        $loaded = extension_loaded($extension);
        $checks[] = [
            'name'   => 'PHP extension: ' . $extension,
            'detail' => $loaded ? $why : $why . ' Ask your host to enable it, or tick it in cPanel under "Select PHP Version".',
            'value'  => $loaded ? 'Enabled' : 'Missing',
            'status' => $loaded ? 'ok' : 'fail',
        ];
    }

    $optional = [
        'gd'   => 'Resizes uploaded photos and strips their metadata. Without it, photos are stored as uploaded.',
        'curl' => 'Not required, but useful for future integrations.',
        'zip'  => 'Not required by RideLog itself.',
        'intl' => 'Improves number and date formatting.',
    ];

    foreach ($optional as $extension => $why) {
        $loaded = extension_loaded($extension);
        $checks[] = [
            'name'   => 'PHP extension: ' . $extension . ' (optional)',
            'detail' => $why,
            'value'  => $loaded ? 'Enabled' : 'Not installed',
            'status' => $loaded ? 'ok' : 'warn',
        ];
    }

    // Writable directories
    $writable = [
        'config'         => CONFIG_PATH,
        'storage'        => STORAGE_PATH,
        'storage/uploads' => STORAGE_PATH . '/uploads',
        'storage/logs'   => STORAGE_PATH . '/logs',
        'storage/cache'  => STORAGE_PATH . '/cache',
    ];

    foreach ($writable as $label => $path) {
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }

        $ok = is_dir($path) && is_writable($path);
        $checks[] = [
            'name'   => 'Writable: ' . $label . '/',
            'detail' => $ok
                ? 'RideLog can write here.'
                : 'Set this folder to 755 (or 775) in the cPanel File Manager: right-click it and choose "Change Permissions".',
            'value'  => $ok ? 'Writable' : 'Not writable',
            'status' => $ok ? 'ok' : 'fail',
        ];
    }

    // Upload size
    $uploadLimit = \App\Settings::iniToBytes((string) ini_get('upload_max_filesize'));
    $checks[] = [
        'name'   => 'Upload size limit',
        'detail' => $uploadLimit >= 4 * 1024 * 1024
            ? 'Large enough for maintenance photos.'
            : 'Photos from a phone are often 3-6 MB. Raise upload_max_filesize and post_max_size in cPanel under "MultiPHP INI Editor".',
        'value'  => (string) ini_get('upload_max_filesize'),
        'status' => $uploadLimit >= 4 * 1024 * 1024 ? 'ok' : 'warn',
    ];

    // Memory
    $memory = \App\Settings::iniToBytes((string) ini_get('memory_limit'));
    $checks[] = [
        'name'   => 'Memory limit',
        'detail' => ($memory <= 0 || $memory >= 64 * 1024 * 1024)
            ? 'Comfortable for reports and image resizing.'
            : 'At least 64M is recommended, especially for CSV exports.',
        'value'  => (string) ini_get('memory_limit'),
        'status' => ($memory <= 0 || $memory >= 64 * 1024 * 1024) ? 'ok' : 'warn',
    ];

    // HTTPS
    $secure = \App\Request::isSecure();
    $checks[] = [
        'name'   => 'HTTPS',
        'detail' => $secure
            ? 'Passwords and session cookies are encrypted in transit.'
            : 'You are installing over plain HTTP. Install a free certificate in cPanel under "SSL/TLS Status", then use the https:// address.',
        'value'  => $secure ? 'In use' : 'Not in use',
        'status' => $secure ? 'ok' : 'warn',
    ];

    return $checks;
}

function requirements_pass(array $checks): bool
{
    foreach ($checks as $check) {
        if ($check['status'] === 'fail') {
            return false;
        }
    }

    return true;
}

// -----------------------------------------------------------------------------
// Step handlers
// -----------------------------------------------------------------------------

if ($posted) {
    switch ($step) {

        // ---------------------------------------------------------------------
        case 'database':
            $input = [
                'db_host'   => trim((string) ($_POST['db_host'] ?? 'localhost')),
                'db_port'   => trim((string) ($_POST['db_port'] ?? '3306')),
                'db_name'   => trim((string) ($_POST['db_name'] ?? '')),
                'db_user'   => trim((string) ($_POST['db_user'] ?? '')),
                'db_pass'   => (string) ($_POST['db_pass'] ?? ''),
                'db_prefix' => trim((string) ($_POST['db_prefix'] ?? 'rl_')),
            ];

            if ($input['db_host'] === '') {
                $errors['db_host'] = 'Enter the database host. On most cPanel accounts this is "localhost".';
            }

            if ($input['db_name'] === '') {
                $errors['db_name'] = 'Enter the database name, including your cPanel account prefix.';
            }

            if ($input['db_user'] === '') {
                $errors['db_user'] = 'Enter the database username.';
            }

            if ($input['db_prefix'] !== '' && !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,20}$/', $input['db_prefix'])) {
                $errors['db_prefix'] = 'The prefix must start with a letter and use only letters, numbers and underscores.';
            }

            if ($errors === []) {
                try {
                    $db = Database::connect([
                        'host'   => $input['db_host'],
                        'port'   => (int) $input['db_port'],
                        'name'   => $input['db_name'],
                        'user'   => $input['db_user'],
                        'pass'   => $input['db_pass'],
                        'prefix' => $input['db_prefix'],
                    ]);

                    // Confirm we can actually create things, not merely connect.
                    $probe = 'rl_probe_' . bin2hex(random_bytes(4));
                    $db->pdo()->exec('CREATE TABLE `' . $probe . '` (id INT) ENGINE=InnoDB');
                    $db->pdo()->exec('DROP TABLE `' . $probe . '`');

                    $existing = SqlRunner::existingTables($db->pdo(), $input['db_prefix']);

                    if ($existing !== [] && empty($_POST['confirm_overwrite'])) {
                        $errors['_form'] = 'This database already contains RideLog tables ('
                            . implode(', ', array_slice($existing, 0, 3))
                            . (count($existing) > 3 ? ', …' : '')
                            . '). Choose a different table prefix, or tick the box below to install alongside them.';
                        $state['existing_tables'] = $existing;
                    } else {
                        $state = array_merge($state, $input);
                        $state['db_version'] = $db->serverVersion();
                        unset($state['existing_tables']);

                        redirect_step('admin');
                    }
                } catch (\Throwable $e) {
                    $errors['_form'] = $e->getMessage();
                }
            }

            $state = array_merge($state, $input);
            break;

        // ---------------------------------------------------------------------
        case 'admin':
            $validator = Validator::make($_POST, [
                'admin_username'   => 'required|username',
                'admin_email'      => 'required|email|max:190',
                'admin_first_name' => 'required|string|max:80',
                'admin_last_name'  => 'required|string|max:80',
                'admin_password'   => 'required|min:8|max:200|confirmed',
            ], [
                'admin_password.confirmed' => 'The two passwords do not match.',
                'admin_password.min'       => 'Use at least 8 characters. A short phrase you will remember beats a scramble you will not.',
            ], [
                'admin_username'   => 'Username',
                'admin_email'      => 'Email address',
                'admin_first_name' => 'First name',
                'admin_last_name'  => 'Last name',
                'admin_password'   => 'Password',
            ]);

            if ($validator->fails()) {
                $errors = $validator->errors();
            } else {
                $state = array_merge($state, $validator->validated());
                $state['admin_password'] = (string) $_POST['admin_password'];

                redirect_step('site');
            }

            foreach (['admin_username', 'admin_email', 'admin_first_name', 'admin_last_name'] as $field) {
                $state[$field] = trim((string) ($_POST[$field] ?? ''));
            }
            break;

        // ---------------------------------------------------------------------
        case 'site':
            $siteName = trim((string) ($_POST['site_name'] ?? ''));
            $orgName  = trim((string) ($_POST['organization_name'] ?? ''));
            $timezone = (string) ($_POST['timezone'] ?? 'America/New_York');
            $appUrl   = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');

            if ($siteName === '') {
                $errors['site_name'] = 'Give the site a name. It appears in the header and on printed reports.';
            }

            if (!in_array($timezone, timezone_identifiers_list(), true)) {
                $errors['timezone'] = 'Choose a time zone from the list.';
            }

            if ($appUrl === '' || !preg_match('#^https?://#i', $appUrl)) {
                $errors['app_url'] = 'Enter the full web address, starting with http:// or https://.';
            }

            if ($errors === []) {
                $state['site_name']         = $siteName;
                $state['organization_name'] = $orgName !== '' ? $orgName : $siteName;
                $state['timezone']          = $timezone;
                $state['app_url']           = $appUrl;
                $state['install_demo']      = !empty($_POST['install_demo']);

                redirect_step('install');
            }

            $state['site_name']         = $siteName;
            $state['organization_name'] = $orgName;
            $state['timezone']          = $timezone;
            $state['app_url']           = $appUrl;
            $state['install_demo']      = !empty($_POST['install_demo']);
            break;

        // ---------------------------------------------------------------------
        case 'install':
            $result = run_installation($state);

            if ($result['ok']) {
                unset($_SESSION['install'], $_SESSION['install_token']);
                $_SESSION['install_done'] = [
                    'app_url'   => (string) ($state['app_url'] ?? ''),
                    'username'  => (string) ($state['admin_username'] ?? ''),
                    'cron_url'  => $result['cron_url'],
                    'demo'      => !empty($state['install_demo']),
                    'warnings'  => $result['warnings'],
                ];

                header('Location: index.php?step=done');
                exit;
            }

            $errors['_form'] = $result['error'];
            $state['install_errors'] = $result['details'];
            break;
    }
}

// The completion screen is not part of the numbered step list; it is reached
// only by the redirect that follows a successful install.
if (($_GET['step'] ?? '') === 'done') {
    $step = 'done';
}

// -----------------------------------------------------------------------------
// Installation
// -----------------------------------------------------------------------------

/**
 * @param  array<string, mixed> $state
 * @return array{ok: bool, error: string, details: list<string>, cron_url: string, warnings: list<string>}
 */
function run_installation(array $state): array
{
    $fail = static function (string $message, array $details = []): array {
        return ['ok' => false, 'error' => $message, 'details' => $details, 'cron_url' => '', 'warnings' => []];
    };

    $prefix   = (string) ($state['db_prefix'] ?? '');
    $warnings = [];

    // --- Connect ------------------------------------------------------------
    try {
        $db = Database::connect([
            'host'   => (string) ($state['db_host'] ?? 'localhost'),
            'port'   => (int) ($state['db_port'] ?? 3306),
            'name'   => (string) ($state['db_name'] ?? ''),
            'user'   => (string) ($state['db_user'] ?? ''),
            'pass'   => (string) ($state['db_pass'] ?? ''),
            'prefix' => $prefix,
        ]);
    } catch (\Throwable $e) {
        return $fail('Could not connect to the database: ' . $e->getMessage());
    }

    $pdo = $db->pdo();

    // --- Schema -------------------------------------------------------------
    $schema = SqlRunner::executeFile($pdo, __DIR__ . '/schema.sql', $prefix);

    if (!$schema['ok']) {
        return $fail(
            'The database tables could not be created. Nothing has been changed that you need to undo — '
            . 'fix the problem below and try again.',
            $schema['errors']
        );
    }

    // --- Reference data -----------------------------------------------------
    $seed = SqlRunner::executeFile($pdo, __DIR__ . '/seed.sql', $prefix);

    if (!$seed['ok']) {
        return $fail('The tables were created, but the starting data could not be loaded.', $seed['errors']);
    }

    // --- Administrator ------------------------------------------------------
    Database::setInstance($db);

    try {
        $existing = $db->value('SELECT id FROM {users} WHERE username = ? OR email = ? LIMIT 1', [
            (string) $state['admin_username'],
            (string) $state['admin_email'],
        ]);

        if ($existing !== null) {
            $adminId = (int) $existing;
            $db->update('users', [
                'password_hash' => password_hash((string) $state['admin_password'], PASSWORD_DEFAULT),
                'role'          => 'admin',
                'is_active'     => 1,
            ], ['id' => $adminId]);
        } else {
            $adminId = $db->insert('users', [
                'username'            => (string) $state['admin_username'],
                'email'               => (string) $state['admin_email'],
                'password_hash'       => password_hash((string) $state['admin_password'], PASSWORD_DEFAULT),
                'first_name'          => (string) $state['admin_first_name'],
                'last_name'           => (string) $state['admin_last_name'],
                'role'                => 'admin',
                'job_title'           => 'Administrator',
                'is_active'           => 1,
                'timezone'            => (string) $state['timezone'],
                'password_changed_at' => Dates::nowUtc(),
                'created_at'          => Dates::nowUtc(),
            ]);
        }
    } catch (\Throwable $e) {
        return $fail('The administrator account could not be created: ' . $e->getMessage());
    }

    // --- Settings -----------------------------------------------------------
    $cronToken = Str::random(48);

    try {
        $settings = [
            'site_name'         => (string) $state['site_name'],
            'organization_name' => (string) $state['organization_name'],
            'timezone'          => (string) $state['timezone'],
            'mail_from_name'    => (string) $state['site_name'],
            'cron_token'        => $cronToken,
            'app_installed_at'  => Dates::nowUtc(),
            'schema_version'    => RIDELOG_VERSION,
        ];

        foreach ($settings as $key => $value) {
            $db->run(
                'UPDATE {settings} SET setting_value = ? WHERE setting_key = ?',
                [$value, $key]
            );
        }
    } catch (\Throwable $e) {
        $warnings[] = 'Some settings could not be saved: ' . $e->getMessage();
    }

    // --- Demo data ----------------------------------------------------------
    if (!empty($state['install_demo'])) {
        $demo = SqlRunner::executeFile($pdo, __DIR__ . '/demo.sql', $prefix, false);

        if (!$demo['ok']) {
            $warnings[] = 'The sample data was only partly loaded. This does not affect the application; '
                        . 'you can delete the sample records and enter your own.';
        }

        try {
            \App\Scheduler::recomputeAll();
        } catch (\Throwable $e) {
            // Not important enough to fail the install.
        }
    }

    // --- config.php ---------------------------------------------------------
    $config = build_config_file($state);

    if (@file_put_contents(CONFIG_PATH . '/config.php', $config) === false) {
        return $fail(
            'The database is ready, but config/config.php could not be written. '
            . 'Set the config folder to 755 in the cPanel File Manager and try again.'
        );
    }

    @chmod(CONFIG_PATH . '/config.php', 0640);

    // --- Lock ---------------------------------------------------------------
    $lockContents = "RideLog was installed on " . gmdate('Y-m-d H:i:s') . " UTC.\n"
                  . "Delete this file AND config/config.php to run the installer again.\n"
                  . "Doing so does NOT delete your data.\n";

    if (@file_put_contents(CONFIG_PATH . '/installed.lock', $lockContents) === false) {
        $warnings[] = 'The install lock file could not be written. Create an empty file at '
                    . 'config/installed.lock so the installer cannot be run again.';
    }

    // Block the installer directory from the web from here on.
    write_install_htaccess($warnings);

    // --- Audit --------------------------------------------------------------
    try {
        Config::load(require CONFIG_PATH . '/config.php');
        \App\Audit::record('install', 'system', null, 'RideLog ' . RIDELOG_VERSION . ' installed');
    } catch (\Throwable $e) {
        // The install itself succeeded; an audit row is optional.
    }

    $cronUrl = rtrim((string) $state['app_url'], '/') . '/cron.php?token=' . $cronToken;

    return ['ok' => true, 'error' => '', 'details' => [], 'cron_url' => $cronUrl, 'warnings' => $warnings];
}

/**
 * @param array<string, mixed> $state
 */
function build_config_file(array $state): string
{
    $values = [
        'app' => [
            'name'        => 'RideLog',
            'url'         => (string) $state['app_url'],
            'key'         => Str::random(64),
            'debug'       => false,
            'timezone'    => (string) $state['timezone'],
            'trust_proxy' => false,
        ],
        'db' => [
            'host'    => (string) $state['db_host'],
            'port'    => (int) $state['db_port'],
            'name'    => (string) $state['db_name'],
            'user'    => (string) $state['db_user'],
            'pass'    => (string) $state['db_pass'],
            'charset' => 'utf8mb4',
            'prefix'  => (string) $state['db_prefix'],
            'socket'  => '',
        ],
        'security' => [
            'session_name'    => 'ridelog_session',
            'bind_session_ip' => false,
        ],
    ];

    return "<?php\n\n"
        . "/**\n"
        . " * RideLog configuration.\n"
        . " *\n"
        . " * Written by the installer on " . gmdate('Y-m-d H:i:s') . " UTC.\n"
        . " * Keep a backup: it holds your database credentials and the application key.\n"
        . " * Changing app.key signs everybody out.\n"
        . " */\n\n"
        . "if (!defined('RIDELOG')) {\n"
        . "    die('No direct access');\n"
        . "}\n\n"
        . 'return ' . var_export($values, true) . ";\n";
}

/**
 * @param list<string> $warnings
 */
function write_install_htaccess(array &$warnings): void
{
    $deny = "# RideLog: the installer has run. This folder is no longer reachable.\n"
          . "# Delete the whole install/ directory when you are done.\n"
          . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
          . "<IfModule !mod_authz_core.c>\n    <IfModule mod_access_compat.c>\n"
          . "        Order allow,deny\n        Deny from all\n    </IfModule>\n</IfModule>\n";

    if (@file_put_contents(__DIR__ . '/.htaccess', $deny) === false) {
        $warnings[] = 'Could not lock the install folder automatically. Delete the install/ directory yourself.';
    }
}

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

function redirect_step(string $next): void
{
    header('Location: index.php?step=' . urlencode($next));
    exit;
}

function guess_app_url(): string
{
    $scheme = \App\Request::isSecure() ? 'https' : 'http';
    $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host   = preg_replace('/[^A-Za-z0-9\.\-:]/', '', $host) ?? 'localhost';

    // SCRIPT_NAME is /<app>/install/index.php, so drop the last two segments.
    $path = str_replace('\\', '/', dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'))));
    $path = rtrim($path === '/' || $path === '.' ? '' : $path, '/');

    return $scheme . '://' . $host . $path;
}

function esc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_locked(): void
{
    http_response_code(403);
    $title = 'RideLog is already installed';
    $body  = '<p>The installer will not run because <code>config/installed.lock</code> exists. '
           . 'This protects your data from being overwritten.</p>'
           . '<p><strong>You should delete the whole <code>install/</code> folder now.</strong> '
           . 'Everything works without it.</p>'
           . '<p>To install again from scratch, delete both <code>config/config.php</code> and '
           . '<code>config/installed.lock</code>. That does not delete your database.</p>'
           . '<p><a class="btn btn-primary" href="../index.php">Go to RideLog</a></p>';

    installer_page($title, $body, null);
}

/**
 * The installer's own shell. Self-contained apart from the app stylesheet,
 * because at this point there is no configured application to render with.
 */
function installer_page(string $title, string $body, ?string $currentStep, array $steps = []): void
{
    $base = str_replace('\\', '/', dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))));
    $base = rtrim($base === '/' || $base === '.' ? '' : $base, '/');
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex, nofollow">
<title><?= esc($title) ?> · RideLog Installer</title>
<link rel="icon" type="image/svg+xml" href="<?= esc($base) ?>/assets/img/favicon.svg">
<link rel="stylesheet" href="<?= esc($base) ?>/assets/css/app.css">
<script src="<?= esc($base) ?>/assets/js/theme-init.js"></script>
</head>
<body class="install-page">
<div class="install-shell">

    <header class="install-header">
        <span class="brand-mark" aria-hidden="true"><?php require APP_ROOT . '/assets/img/logo.svg'; ?></span>
        <div>
            <h1>RideLog</h1>
            <p>Maintenance log &amp; dashboard &middot; version <?= esc(RIDELOG_VERSION) ?></p>
        </div>
    </header>

    <?php if ($currentStep !== null && $steps !== []): ?>
        <?php
        $keys    = array_keys($steps);
        $current = array_search($currentStep, $keys, true);
        $current = $current === false ? 0 : (int) $current;
        ?>
        <ol class="install-steps">
            <?php foreach ($steps as $key => $label): ?>
                <?php
                $index = (int) array_search($key, $keys, true);
                $class = $index === $current ? 'is-current' : ($index < $current ? 'is-done' : '');
                ?>
                <li class="install-step <?= esc($class) ?>">
                    <span class="step-num"><?= $index < $current ? '&check;' : $index + 1 ?></span>
                    <span><?= esc($label) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?= $body ?>

    <p class="text-subtle text-sm text-center mt-5">
        Stuck? Open <code>docs/INSTALL.md</code> for a full cPanel walkthrough.
    </p>
</div>
<script src="<?= esc($base) ?>/assets/js/core.js" defer></script>
</body>
</html>
    <?php
}

/** Render one field with its error, in the installer's simpler markup. */
function field(string $name, string $label, string $type, $value, array $options = []): string
{
    global $errors;

    $error       = (string) ($errors[$name] ?? '');
    $hint        = (string) ($options['hint'] ?? '');
    $required    = !empty($options['required']);
    $placeholder = (string) ($options['placeholder'] ?? '');
    $autocomplete = (string) ($options['autocomplete'] ?? '');
    $id          = 'f_' . $name;

    $html  = '<div class="form-group' . ($error !== '' ? ' has-error' : '') . '">';
    $html .= '<label class="form-label" for="' . esc($id) . '">' . esc($label);

    if ($required) {
        $html .= '<span class="required">*</span>';
    }

    $html .= '</label>';

    if ($type === 'select') {
        $html .= '<select class="form-select" id="' . esc($id) . '" name="' . esc($name) . '"'
               . ($required ? ' required' : '') . '>';

        foreach (($options['choices'] ?? []) as $optValue => $optLabel) {
            if (is_array($optLabel)) {
                $html .= '<optgroup label="' . esc((string) $optValue) . '">';

                foreach ($optLabel as $subValue => $subLabel) {
                    $html .= '<option value="' . esc((string) $subValue) . '"'
                           . ((string) $subValue === (string) $value ? ' selected' : '') . '>'
                           . esc((string) $subLabel) . '</option>';
                }

                $html .= '</optgroup>';
                continue;
            }

            $html .= '<option value="' . esc((string) $optValue) . '"'
                   . ((string) $optValue === (string) $value ? ' selected' : '') . '>'
                   . esc((string) $optLabel) . '</option>';
        }

        $html .= '</select>';
    } elseif ($type === 'checkbox') {
        $html = '<div class="form-group">';
        $html .= '<label class="form-check" for="' . esc($id) . '">';
        $html .= '<input type="checkbox" id="' . esc($id) . '" name="' . esc($name) . '" value="1"'
               . ($value ? ' checked' : '') . '>';
        $html .= '<span class="form-check-label">' . esc($label);

        if ($hint !== '') {
            $html .= '<small>' . esc($hint) . '</small>';
        }

        $html .= '</span></label></div>';

        return $html;
    } else {
        $html .= '<input type="' . esc($type) . '" class="form-input" id="' . esc($id) . '" '
               . 'name="' . esc($name) . '" value="' . esc((string) $value) . '"'
               . ($required ? ' required' : '')
               . ($placeholder !== '' ? ' placeholder="' . esc($placeholder) . '"' : '')
               . ($autocomplete !== '' ? ' autocomplete="' . esc($autocomplete) . '"' : '')
               . (isset($options['minlength']) ? ' minlength="' . (int) $options['minlength'] . '"' : '')
               . '>';
    }

    if ($hint !== '' && $type !== 'checkbox') {
        $html .= '<div class="form-hint">' . esc($hint) . '</div>';
    }

    if ($error !== '') {
        $html .= '<div class="form-error"><span>' . esc($error) . '</span></div>';
    }

    return $html . '</div>';
}

function form_error_block(array $errors, array $details = []): string
{
    if (empty($errors['_form'])) {
        return '';
    }

    $html = '<div class="alert alert-error"><div class="alert-body">'
          . '<strong class="alert-title">That did not work</strong>'
          . '<p style="margin:4px 0 0">' . esc((string) $errors['_form']) . '</p>';

    if ($details !== []) {
        $html .= '<ul style="margin:8px 0 0;padding-left:18px;font-size:var(--text-sm)">';

        foreach (array_slice($details, 0, 6) as $line) {
            $html .= '<li><code>' . esc((string) $line) . '</code></li>';
        }

        $html .= '</ul>';
    }

    return $html . '</div></div>';
}

// -----------------------------------------------------------------------------
// Render the current step
// -----------------------------------------------------------------------------

ob_start();

switch ($step) {

    // -------------------------------------------------------------------------
    case 'welcome':
        ?>
        <div class="card">
            <div class="card-body">
                <h2>Welcome</h2>
                <p>This installer sets up RideLog: a maintenance log and dashboard for go-karts,
                   rides and equipment. It takes about five minutes.</p>

                <h3 class="mt-5">Before you start</h3>
                <p>Create a MySQL database and a user in cPanel, and assign the user to the database
                   with <strong>All Privileges</strong>. You will need three things from that screen:</p>
                <ul>
                    <li>the database name (something like <code>youracct_ridelog</code>)</li>
                    <li>the database username (something like <code>youracct_rluser</code>)</li>
                    <li>the password you set for that user</li>
                </ul>
                <p class="text-muted">cPanel adds your account name as a prefix to both. Copy them exactly
                   as cPanel shows them.</p>

                <h3 class="mt-5">What you will get</h3>
                <div class="grid grid-2 mt-3">
                    <div>
                        <ul>
                            <li>An asset register for every kart, ride and machine</li>
                            <li>Maintenance logs recording who did what, and when</li>
                            <li>Preventive maintenance schedules by date or meter</li>
                            <li>Daily inspection checklists, ready for phones</li>
                        </ul>
                    </div>
                    <div>
                        <ul>
                            <li>Work orders from report through to completion</li>
                            <li>Parts inventory with stock levels</li>
                            <li>Reports and CSV exports</li>
                            <li>Four roles, from read-only to administrator</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <span class="text-muted text-sm">Nothing is changed until the final step.</span>
                <a class="btn btn-primary" href="index.php?step=requirements">
                    Get started
                </a>
            </div>
        </div>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'requirements':
        $checks = requirement_checks();
        $canGo  = requirements_pass($checks);
        ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Server requirements</h2>
            </div>
            <div class="card-body">
                <?php if (!$canGo): ?>
                    <div class="alert alert-error">
                        <div class="alert-body">
                            <strong class="alert-title">Some requirements are not met</strong>
                            <p style="margin:4px 0 0">RideLog cannot be installed until the items marked
                            in red are fixed. Each one says what to do.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success">
                        <div class="alert-body">
                            <strong class="alert-title">Your server is ready</strong>
                            <p style="margin:4px 0 0">Anything marked amber is optional — you can install now
                            and address it later.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <ul class="requirement-list">
                    <?php foreach ($checks as $check): ?>
                        <li class="requirement is-<?= esc($check['status']) ?>">
                            <span class="requirement-status">
                                <?php if ($check['status'] === 'ok'): ?>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.5 5 5 10-10"/></svg>
                                <?php elseif ($check['status'] === 'warn'): ?>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 4.3 2.8 17.2A2 2 0 0 0 4.5 20.2h15a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v4"/><path d="M12 17h.01"/></svg>
                                <?php else: ?>
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                <?php endif; ?>
                            </span>
                            <span class="requirement-body">
                                <span class="requirement-name"><?= esc($check['name']) ?></span>
                                <span class="requirement-detail"><?= esc($check['detail']) ?></span>
                            </span>
                            <span class="requirement-value"><?= esc($check['value']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card-footer">
                <a class="btn btn-secondary" href="index.php?step=welcome">Back</a>
                <span class="flex-1"></span>
                <a class="btn btn-secondary" href="index.php?step=requirements">Re-check</a>
                <?php if ($canGo): ?>
                    <a class="btn btn-primary" href="index.php?step=database">Continue</a>
                <?php else: ?>
                    <button class="btn btn-primary" disabled>Continue</button>
                <?php endif; ?>
            </div>
        </div>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'database':
        ?>
        <form method="post" action="index.php?step=database">
            <input type="hidden" name="_token" value="<?= esc($token) ?>">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Database connection</h2>
                </div>
                <div class="card-body">
                    <?= form_error_block($errors) ?>

                    <p class="text-muted">Enter the details of the MySQL database you created in cPanel.
                    RideLog will create its tables there.</p>

                    <div class="form-row cols-2">
                        <?= field('db_host', 'Database host', 'text', $state['db_host'] ?? 'localhost', [
                            'required' => true,
                            'hint'     => 'Almost always "localhost" on cPanel.',
                        ]) ?>
                        <?= field('db_port', 'Port', 'text', $state['db_port'] ?? '3306', [
                            'hint' => 'Leave 3306 unless your host says otherwise.',
                        ]) ?>
                    </div>

                    <?= field('db_name', 'Database name', 'text', $state['db_name'] ?? '', [
                        'required'    => true,
                        'placeholder' => 'youracct_ridelog',
                        'hint'        => 'Include the account prefix exactly as cPanel shows it.',
                    ]) ?>

                    <div class="form-row cols-2">
                        <?= field('db_user', 'Database username', 'text', $state['db_user'] ?? '', [
                            'required'    => true,
                            'placeholder' => 'youracct_rluser',
                        ]) ?>
                        <?= field('db_pass', 'Database password', 'password', $state['db_pass'] ?? '', [
                            'autocomplete' => 'new-password',
                        ]) ?>
                    </div>

                    <?= field('db_prefix', 'Table prefix', 'text', $state['db_prefix'] ?? 'rl_', [
                        'hint' => 'Prefixes every table name, so several applications can share one database. '
                                . 'Leave as "rl_" unless you have a reason to change it. It cannot be changed later.',
                    ]) ?>

                    <?php if (!empty($state['existing_tables'])): ?>
                        <div class="form-check-card">
                            <input type="checkbox" id="confirm_overwrite" name="confirm_overwrite" value="1">
                            <label class="form-check-label" for="confirm_overwrite">
                                Install anyway
                                <small>RideLog tables with this prefix already exist. Continuing will reuse
                                them and keep any data they hold. If that is not what you want, change the
                                prefix above.</small>
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a class="btn btn-secondary" href="index.php?step=requirements">Back</a>
                    <span class="flex-1"></span>
                    <button type="submit" class="btn btn-primary">Test connection &amp; continue</button>
                </div>
            </div>
        </form>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'admin':
        ?>
        <form method="post" action="index.php?step=admin" data-validate>
            <input type="hidden" name="_token" value="<?= esc($token) ?>">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Administrator account</h2>
                </div>
                <div class="card-body">
                    <?= form_error_block($errors) ?>

                    <div class="alert alert-success">
                        <div class="alert-body">
                            <strong class="alert-title">Connected to <?= esc((string) ($state['db_name'] ?? '')) ?></strong>
                            <p style="margin:4px 0 0">MySQL <?= esc((string) ($state['db_version'] ?? '')) ?>.
                            Now create the account you will sign in with.</p>
                        </div>
                    </div>

                    <div class="form-row cols-2">
                        <?= field('admin_first_name', 'First name', 'text', $state['admin_first_name'] ?? '', ['required' => true]) ?>
                        <?= field('admin_last_name', 'Last name', 'text', $state['admin_last_name'] ?? '', ['required' => true]) ?>
                    </div>

                    <?= field('admin_username', 'Username', 'text', $state['admin_username'] ?? '', [
                        'required'     => true,
                        'hint'         => '3 to 64 characters. Letters, numbers, dots, dashes and underscores.',
                        'autocomplete' => 'username',
                    ]) ?>

                    <?= field('admin_email', 'Email address', 'email', $state['admin_email'] ?? '', [
                        'required' => true,
                        'hint'     => 'Used for password resets and notifications.',
                    ]) ?>

                    <div class="form-row cols-2">
                        <?= field('admin_password', 'Password', 'password', '', [
                            'required'     => true,
                            'minlength'    => 8,
                            'hint'         => 'At least 8 characters. A short phrase works well.',
                            'autocomplete' => 'new-password',
                        ]) ?>
                        <?= field('admin_password_confirmation', 'Confirm password', 'password', '', [
                            'required'     => true,
                            'autocomplete' => 'new-password',
                        ]) ?>
                    </div>

                    <p class="text-subtle text-sm">This account gets full access, including user
                    management and site settings. You can add colleagues with narrower roles afterwards.</p>
                </div>
                <div class="card-footer">
                    <a class="btn btn-secondary" href="index.php?step=database">Back</a>
                    <span class="flex-1"></span>
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>
            </div>
        </form>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'site':
        $zones = [];

        foreach (Dates::timezones() as $region => $identifiers) {
            $zones[$region] = $identifiers;
        }
        ?>
        <form method="post" action="index.php?step=site" data-validate>
            <input type="hidden" name="_token" value="<?= esc($token) ?>">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Site details</h2>
                </div>
                <div class="card-body">
                    <?= form_error_block($errors) ?>

                    <?= field('site_name', 'Site name', 'text', $state['site_name'] ?? 'Castle Fun Center Maintenance', [
                        'required' => true,
                        'hint'     => 'Shown in the header and the browser tab.',
                    ]) ?>

                    <?= field('organization_name', 'Organization name', 'text', $state['organization_name'] ?? 'Castle Fun Center', [
                        'hint' => 'Printed at the top of inspection sheets and reports.',
                    ]) ?>

                    <?= field('app_url', 'Web address', 'text', $state['app_url'] ?? guess_app_url(), [
                        'required' => true,
                        'hint'     => 'The full address of this installation, with no trailing slash. '
                                    . 'Password reset links and QR codes are built from it.',
                    ]) ?>

                    <?= field('timezone', 'Time zone', 'select', $state['timezone'] ?? 'America/New_York', [
                        'required' => true,
                        'choices'  => $zones,
                        'hint'     => 'All times are stored in UTC and displayed in this zone.',
                    ]) ?>

                    <div class="form-check-card mt-4">
                        <input type="checkbox" id="f_install_demo" name="install_demo" value="1"
                               <?= !empty($state['install_demo']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="f_install_demo">
                            Install sample data
                            <small>Adds a fictional kart fleet, a year of maintenance history, schedules,
                            work orders and parts, so you can see how everything fits together. Easy to
                            delete later. Leave this unticked if you are setting up for real use now.</small>
                        </label>
                    </div>
                </div>
                <div class="card-footer">
                    <a class="btn btn-secondary" href="index.php?step=admin">Back</a>
                    <span class="flex-1"></span>
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>
            </div>
        </form>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'install':
        ?>
        <form method="post" action="index.php?step=install">
            <input type="hidden" name="_token" value="<?= esc($token) ?>">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Ready to install</h2>
                </div>
                <div class="card-body">
                    <?= form_error_block($errors, $state['install_errors'] ?? []) ?>

                    <p class="text-muted">Check these over, then install.</p>

                    <dl class="detail-list mt-4">
                        <dt>Site name</dt>
                        <dd><?= esc((string) ($state['site_name'] ?? '')) ?></dd>

                        <dt>Web address</dt>
                        <dd><?= esc((string) ($state['app_url'] ?? '')) ?></dd>

                        <dt>Time zone</dt>
                        <dd><?= esc((string) ($state['timezone'] ?? '')) ?></dd>

                        <dt>Database</dt>
                        <dd><?= esc((string) ($state['db_name'] ?? '')) ?>
                            on <?= esc((string) ($state['db_host'] ?? '')) ?>
                            <span class="text-subtle">(tables prefixed <code><?= esc((string) ($state['db_prefix'] ?? '')) ?></code>)</span>
                        </dd>

                        <dt>Administrator</dt>
                        <dd><?= esc(trim((string) ($state['admin_first_name'] ?? '') . ' ' . (string) ($state['admin_last_name'] ?? ''))) ?>
                            &middot; <?= esc((string) ($state['admin_username'] ?? '')) ?>
                            &middot; <?= esc((string) ($state['admin_email'] ?? '')) ?>
                        </dd>

                        <dt>Sample data</dt>
                        <dd><?= !empty($state['install_demo']) ? 'Yes, install it' : 'No, start empty' ?></dd>
                    </dl>

                    <div class="alert alert-info mt-5">
                        <div class="alert-body">
                            <strong class="alert-title">What happens next</strong>
                            <p style="margin:4px 0 0">RideLog creates 24 tables, loads its starting data,
                            creates your account and writes <code>config/config.php</code>. It takes a few
                            seconds. Do not close the tab.</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a class="btn btn-secondary" href="index.php?step=site">Back</a>
                    <span class="flex-1"></span>
                    <button type="submit" class="btn btn-primary btn-lg">Install RideLog</button>
                </div>
            </div>
        </form>
        <?php
        break;

    // -------------------------------------------------------------------------
    case 'done':
        $done = $_SESSION['install_done'] ?? null;

        if (!is_array($done)) {
            header('Location: index.php');
            exit;
        }

        unset($_SESSION['install_done']);
        $appUrl = rtrim((string) $done['app_url'], '/');
        ?>
        <div class="card is-accent">
            <div class="card-body" style="text-align:center">
                <span class="empty-icon" style="background:var(--ok-bg);color:var(--ok);width:60px;height:60px">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.5 5 5 10-10"/></svg>
                </span>
                <h2>RideLog is installed</h2>
                <p class="text-muted">Sign in as <strong><?= esc((string) $done['username']) ?></strong> to get started.</p>
                <a class="btn btn-primary btn-lg mt-3" href="<?= esc($appUrl) ?>/login.php">Sign in</a>
            </div>
        </div>

        <?php if (!empty($done['warnings'])): ?>
            <div class="alert alert-warning">
                <div class="alert-body">
                    <strong class="alert-title">Installed, with a couple of notes</strong>
                    <ul style="margin:6px 0 0;padding-left:18px">
                        <?php foreach ($done['warnings'] as $warning): ?>
                            <li><?= esc((string) $warning) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="card is-danger">
            <div class="card-header">
                <h3 class="card-title">Do this now</h3>
            </div>
            <div class="card-body">
                <ol style="padding-left:20px;margin:0">
                    <li style="margin-bottom:10px">
                        <strong>Delete the <code>install/</code> folder.</strong>
                        In the cPanel File Manager, right-click it and choose Delete. The installer has
                        locked itself, but removing it entirely is better.
                    </li>
                    <li style="margin-bottom:10px">
                        <strong>Check <code>config/config.php</code> is not public.</strong>
                        Open <code><?= esc($appUrl) ?>/config/config.php</code> in a browser. You should see
                        a Forbidden error. If you see PHP code or a blank page, tell your host that
                        <code>.htaccess</code> overrides are disabled for your account.
                    </li>
                    <li style="margin-bottom:10px">
                        <strong>Turn on HTTPS.</strong>
                        cPanel &rarr; SSL/TLS Status &rarr; Run AutoSSL. Then uncomment the redirect block
                        in <code>.htaccess</code>.
                    </li>
                    <li>
                        <strong>Set up the scheduled task</strong> (below) so due maintenance is flagged
                        automatically.
                    </li>
                </ol>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Scheduled task</h3>
            </div>
            <div class="card-body">
                <p>RideLog raises notifications for maintenance that is due, clears expired sessions and
                prunes old records. It runs this work automatically when someone opens the dashboard, so
                <strong>this step is optional</strong> — but a real cron job means alerts arrive even on a
                quiet day.</p>

                <p class="mb-2"><strong>In cPanel &rarr; Cron Jobs</strong>, add a job that runs
                <em>Once Per Hour</em> with this command:</p>

                <pre style="white-space:pre-wrap;word-break:break-all"><code>curl -s "<?= esc((string) $done['cron_url']) ?>" &gt;/dev/null 2&gt;&amp;1</code></pre>

                <button type="button" class="btn btn-secondary btn-sm"
                        data-copy='curl -s "<?= esc((string) $done['cron_url']) ?>" >/dev/null 2>&1'>
                    Copy command
                </button>

                <p class="form-hint mt-3">That address contains a secret token. Treat it like a password —
                anyone with it can trigger the task. You can regenerate it in Settings &rarr; Security.</p>
            </div>
        </div>

        <?php if (!empty($done['demo'])): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">About the sample data</h3>
                </div>
                <div class="card-body">
                    <p>You installed the fictional fleet, so the dashboard has something to show. The
                    sample staff accounts (Mike Torres and colleagues) are <strong>deactivated and cannot
                    be signed into</strong> — they exist only so the history has authors.</p>
                    <p class="mb-0">When you are ready for real data, delete the sample assets from the
                    Assets screen. Deleting an asset removes its logs with it.</p>
                </div>
            </div>
        <?php endif; ?>
        <?php
        break;
}

$body = (string) ob_get_clean();

installer_page(
    $step === 'done' ? 'Installed' : $steps[$step] ?? 'Install',
    $body,
    $step === 'done' ? null : $step,
    $steps
);
