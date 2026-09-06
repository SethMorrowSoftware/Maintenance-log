<?php

declare(strict_types=1);

/**
 * RideLog bootstrap.
 *
 * Every entry point starts with:
 *
 *     require __DIR__ . '/app/bootstrap.php';
 *
 * In order this file: defines paths, registers the autoloader, loads helpers,
 * installs error handling, loads configuration (sending the visitor to the
 * installer if there is none), starts and validates the session, and sends the
 * security headers.
 */

use App\Auth;
use App\Config;
use App\Dates;
use App\Request;
use App\Response;
use App\Settings;
use App\View;

// -----------------------------------------------------------------------------
// 1. Guard and constants
// -----------------------------------------------------------------------------

if (defined('RIDELOG')) {
    // Already booted (a page required this twice). Nothing more to do.
    return;
}

/** Marker that config/config.php checks, so it cannot be requested directly. */
define('RIDELOG', true);

define('RIDELOG_VERSION', '1.0.0');

/** Absolute path of the application root — the folder uploaded to public_html. */
define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', __DIR__);
define('CONFIG_PATH', APP_ROOT . '/config');
define('STORAGE_PATH', APP_ROOT . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');

// All internal time is UTC. Display conversion happens in App\Dates.
date_default_timezone_set('UTC');

// Never expose the PHP version in a response header.
if (!headers_sent()) {
    header_remove('X-Powered-By');
}

mb_internal_encoding('UTF-8');

// -----------------------------------------------------------------------------
// 2. Autoloader
//
// PSR-4 style, App\ => app/. No Composer, because cPanel users cannot run it.
// -----------------------------------------------------------------------------

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    // Reject anything that could escape the app directory.
    if (strpos($relative, '.') !== false || strpos($relative, "\0") !== false) {
        return;
    }

    $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

// -----------------------------------------------------------------------------
// 3. Error handling
//
// Nothing is ever printed to the browser: PHP notices leak paths and query
// fragments. Everything goes to storage/logs/error-YYYY-MM-DD.log.
// -----------------------------------------------------------------------------

/** Are we running inside the installer? It boots without configuration. */
$rideLogInInstaller = (static function (): bool {
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));

    return strpos($script, '/install/') !== false || PHP_SAPI === 'cli';
})();

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    // Respect the @ operator and any error_reporting() the host applies.
    if (!(error_reporting() & $severity)) {
        return false;
    }

    log_error(sprintf('PHP %s: %s in %s:%d', rideLogSeverityName($severity), $message, $file, $line));

    // Let PHP's own handling continue for anything fatal.
    return true;
});

set_exception_handler(static function (Throwable $e): void {
    log_error(
        sprintf('Uncaught %s: %s in %s:%d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
        ['trace' => $e->getTraceAsString()]
    );

    $debug = (bool) Config::get('app.debug', false);

    $message = $debug
        ? get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : 'Something went wrong on our end. The error has been logged. Please try again, '
          . 'and tell an administrator if it keeps happening.';

    if (class_exists(Response::class)) {
        Response::abortPage(500, $message);
    }

    http_response_code(500);
    echo 'Internal server error.';
    exit;
});

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }

    log_error(sprintf(
        'Fatal: %s in %s:%d',
        $error['message'],
        $error['file'],
        $error['line']
    ));

    if (headers_sent()) {
        return;
    }

    $debug   = (bool) Config::get('app.debug', false);
    $message = $debug
        ? $error['message'] . ' (' . basename($error['file']) . ':' . $error['line'] . ')'
        : 'Something went wrong on our end. The error has been logged.';

    if (class_exists(Response::class)) {
        Response::abortPage(500, $message);
    }
});

if (!function_exists('rideLogSeverityName')) {
    function rideLogSeverityName(int $severity): string
    {
        $names = [
            E_ERROR             => 'Error',
            E_WARNING           => 'Warning',
            E_PARSE             => 'Parse error',
            E_NOTICE            => 'Notice',
            E_CORE_ERROR        => 'Core error',
            E_CORE_WARNING      => 'Core warning',
            E_COMPILE_ERROR     => 'Compile error',
            E_COMPILE_WARNING   => 'Compile warning',
            E_USER_ERROR        => 'User error',
            E_USER_WARNING      => 'User warning',
            E_USER_NOTICE       => 'User notice',
            E_RECOVERABLE_ERROR => 'Recoverable error',
            E_DEPRECATED        => 'Deprecated',
            E_USER_DEPRECATED   => 'User deprecated',
        ];

        return $names[$severity] ?? 'Unknown';
    }
}

// -----------------------------------------------------------------------------
// 4. Configuration
// -----------------------------------------------------------------------------

$rideLogConfigFile  = CONFIG_PATH . '/config.php';
$rideLogLockFile    = CONFIG_PATH . '/installed.lock';
$rideLogIsInstalled = is_file($rideLogConfigFile) && is_file($rideLogLockFile);

if ($rideLogIsInstalled) {
    if (!Config::loadFile($rideLogConfigFile)) {
        // The file exists but is broken — do not silently fall into the
        // installer, which would invite someone to reinstall over live data.
        log_error('config/config.php exists but did not return an array.');
        Response::abortPage(
            500,
            'The configuration file could not be read. Restore config/config.php from a backup, '
            . 'or delete it and config/installed.lock to run the installer again.'
        );
    }
} elseif (!$rideLogInInstaller) {
    // Not installed yet: send the visitor to the installer.
    $installerUrl = app_base_path() . '/install/index.php';

    if (!is_dir(APP_ROOT . '/install')) {
        Response::abortPage(
            503,
            'RideLog is not configured and the install folder is missing. '
            . 'Re-upload the install directory, or restore config/config.php from a backup.'
        );
    }

    if (!headers_sent()) {
        header('Location: ' . $installerUrl);
    }

    echo '<!doctype html><meta charset="utf-8"><title>RideLog</title>'
       . '<p>RideLog is not set up yet. <a href="' . e($installerUrl) . '">Run the installer</a>.</p>';
    exit;
}

// Debug mode is opt-in and should never be left on in production.
if (Config::get('app.debug', false)) {
    ini_set('display_errors', '1');
}

// -----------------------------------------------------------------------------
// 5. Security headers
//
// Also set in .htaccess, but repeated here so the app is protected on hosts
// without mod_headers. Sending a header twice is harmless.
// -----------------------------------------------------------------------------

if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "img-src 'self' data: blob:; "
        . "style-src 'self' 'unsafe-inline'; "
        . "script-src 'self'; "
        . "font-src 'self' data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "frame-ancestors 'self'; "
        . "base-uri 'self'; "
        . "form-action 'self'"
    );
}

// -----------------------------------------------------------------------------
// 6. Session
// -----------------------------------------------------------------------------

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $sessionName = (string) Config::get('security.session_name', 'ridelog_session');

    // A session name must be alphanumeric.
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $sessionName)) {
        $sessionName = 'ridelog_session';
    }

    $cookiePath = app_base_path() . '/';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    // Never shorter than the idle timeout the site owner chose, or the
    // host's garbage collector signs people out early without a word.
    $gcSeconds = 60 * 60 * 24 * 2;

    if ($rideLogIsInstalled) {
        try {
            $gcSeconds = max($gcSeconds, App\Settings::sessionTimeoutMinutes() * 60);
        } catch (Throwable $e) {
            // No database yet: the default will do.
        }
    }

    ini_set('session.gc_maxlifetime', (string) $gcSeconds);

    session_name($sessionName);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookiePath,
        'domain'   => '',
        'secure'   => Request::isSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // A session that cannot start (unwritable save path on a locked-down host)
    // must produce a clear message rather than a wall of warnings.
    if (@session_start() === false) {
        log_error('session_start() failed. Check that session.save_path is writable.');

        if (!$rideLogInInstaller) {
            Response::abortPage(
                503,
                'The session could not be started. Ask your host to check that PHP can write to its '
                . 'session directory, then try again.'
            );
        }
    }
}

// -----------------------------------------------------------------------------
// 7. Session validation and housekeeping
// -----------------------------------------------------------------------------

if ($rideLogIsInstalled && session_status() === PHP_SESSION_ACTIVE) {
    if (!Auth::sessionIsValid()) {
        $wasSignedIn = !empty($_SESSION['user_id']);

        Auth::destroySession();

        // A fresh, empty session straight away, so whatever renders next —
        // usually the login form — has a CSRF token to put in the page.
        @session_start();

        if ($wasSignedIn && !$rideLogInInstaller && Request::script() !== 'login.php') {
            if (Request::wantsJson()) {
                Response::error('Your session has expired. Please sign in again.', 'session_expired', 401);
            }

            App\Flash::warning('You were signed out because your session expired.');

            // Come back to the page, unless it was the sign-out page itself.
            $target = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $keep   = $target !== '' && strpos($target, 'logout.php') === false && strpos($target, 'login.php') === false;
            Response::redirect(url('login.php', $keep ? ['redirect' => $target] : []));
        }
    } else {
        Auth::touchActivity();
    }
}

// -----------------------------------------------------------------------------
// 8. Request-wide view data
// -----------------------------------------------------------------------------

if ($rideLogIsInstalled) {
    // Values every layout and partial can rely on without being passed them.
    View::shareMany([
        'appVersion'  => RIDELOG_VERSION,
        'siteName'    => Settings::siteName(),
        'currentUser' => null, // resolved lazily by user() in views
    ]);

    // A user's own timezone preference overrides the site default.
    Dates::resetZoneCache();
}

// -----------------------------------------------------------------------------
// 9. Oversized upload guard
//
// When a POST body exceeds post_max_size PHP empties $_POST and $_FILES, which
// otherwise looks like an empty form and produces baffling validation errors.
// -----------------------------------------------------------------------------

if ($rideLogIsInstalled && Request::exceededPostLimit()) {
    $limit = ini_get('post_max_size');

    if (Request::wantsJson()) {
        Response::error(
            'That upload is larger than the server accepts (' . $limit . ').',
            'payload_too_large',
            413
        );
    }

    App\Flash::error(
        'That upload was larger than this server accepts (' . $limit . '). '
        . 'Try a smaller file, or ask your host to raise upload_max_filesize and post_max_size.'
    );

    Response::redirect(Request::safeReferer(url('index.php')) ?? url('index.php'));
}

unset($rideLogConfigFile, $rideLogLockFile, $rideLogInInstaller);
