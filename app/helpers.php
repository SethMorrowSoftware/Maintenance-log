<?php

declare(strict_types=1);

/**
 * Global helper functions.
 *
 * Short names used constantly across pages and views. Every one is guarded
 * with function_exists() so a second include is harmless.
 *
 * Loaded by app/bootstrap.php.
 */

use App\Acl;
use App\Audit;
use App\Auth;
use App\Config;
use App\Csrf;
use App\Database;
use App\Dates;
use App\Flash;
use App\Icon;
use App\Request;
use App\Response;
use App\Settings;
use App\Status;
use App\Str;

// -----------------------------------------------------------------------------
// Escaping — used on every dynamic value that reaches the browser
// -----------------------------------------------------------------------------

if (!function_exists('e')) {
    /**
     * Escape for HTML. The single most important function in the project:
     * every dynamic value printed into a page goes through it.
     *
     * @param mixed $value
     */
    function e($value): string
    {
        if ($value === null || is_bool($value) || is_array($value) || is_object($value)) {
            $value = is_bool($value) ? ($value ? '1' : '') : (is_scalar($value) ? $value : '');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('attr')) {
    /**
     * Escape for an HTML attribute value. Same rules as e(), named separately
     * so the intent reads clearly in templates.
     *
     * @param mixed $value
     */
    function attr($value): string
    {
        return e($value);
    }
}

if (!function_exists('js')) {
    /**
     * Encode a value for embedding in a JSON script block or a data attribute.
     * The HEX flags stop the output from ever closing a tag or breaking out.
     *
     * @param mixed $value
     */
    function js($value): string
    {
        $encoded = json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $encoded === false ? 'null' : $encoded;
    }
}

if (!function_exists('nl2br_e')) {
    /** Escape, then convert newlines to <br>. Safe to print directly. */
    function nl2br_e(?string $value): string
    {
        return Str::nl2brEscaped($value);
    }
}

// -----------------------------------------------------------------------------
// Core services
// -----------------------------------------------------------------------------

if (!function_exists('db')) {
    /** The shared database connection. */
    function db(): Database
    {
        return Database::instance();
    }
}

if (!function_exists('config')) {
    /**
     * Read application configuration with dot notation.
     *
     * @param  mixed $default
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('setting')) {
    /**
     * Read a site setting.
     *
     * @param  mixed $default
     * @return mixed
     */
    function setting(string $key, $default = null)
    {
        return Settings::get($key, $default);
    }
}

if (!function_exists('user')) {
    /**
     * The signed-in user's row, or null.
     *
     * @return array<string, mixed>|null
     */
    function user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('user_id')) {
    function user_id(): ?int
    {
        return Auth::id();
    }
}

if (!function_exists('user_name')) {
    /**
     * @param array<string, mixed>|null $row
     */
    function user_name(?array $row = null): string
    {
        return Auth::name($row);
    }
}

if (!function_exists('can')) {
    /** Does the signed-in user hold this permission? */
    function can(string $permission): bool
    {
        return Acl::can($permission);
    }
}

if (!function_exists('can_any')) {
    /**
     * @param list<string> $permissions
     */
    function can_any(array $permissions): bool
    {
        return Acl::canAny($permissions);
    }
}

// -----------------------------------------------------------------------------
// URLs
// -----------------------------------------------------------------------------

if (!function_exists('app_base_path')) {
    /**
     * The URL path the application is installed under, with no trailing slash.
     *
     * Returns '' at a domain root and '/maintenance' in a subfolder. Works
     * from any depth, so api/index.php and install/index.php both resolve to
     * the same base.
     */
    function app_base_path(): string
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        $configured = (string) Config::get('app.url', '');

        if ($configured !== '') {
            $path = parse_url($configured, PHP_URL_PATH);

            if (is_string($path)) {
                $cached = rtrim($path, '/');

                return $cached;
            }
        }

        // Derive it: work out how deep the running script sits below the app
        // root on disk, then strip that many segments off the URL path.
        $scriptFile = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $root       = rtrim(str_replace('\\', '/', defined('APP_ROOT') ? APP_ROOT : ''), '/');

        $urlDir = rtrim(dirname($scriptName), '/');

        if ($root !== '' && $scriptFile !== '') {
            $scriptDir = rtrim(dirname($scriptFile), '/');

            if (strpos($scriptDir, $root) === 0) {
                $relative = trim(substr($scriptDir, strlen($root)), '/');

                if ($relative !== '') {
                    foreach (explode('/', $relative) as $ignored) {
                        $parent = rtrim(dirname($urlDir), '/');
                        $urlDir = $parent === '.' ? '' : $parent;
                    }
                }
            }
        }

        if ($urlDir === '/' || $urlDir === '.' || $urlDir === '\\') {
            $urlDir = '';
        }

        $cached = $urlDir;

        return $cached;
    }
}

if (!function_exists('url')) {
    /**
     * Build an application URL.
     *
     *     url('assets.php')                       -> /maintenance/assets.php
     *     url('asset-view.php', ['id' => 3])      -> /maintenance/asset-view.php?id=3
     *     url()                                    -> /maintenance/
     *
     * @param array<string, mixed> $query
     */
    function url(string $path = '', array $query = []): string
    {
        $path = ltrim($path, '/');
        $base = app_base_path();

        $out = $base . '/' . $path;

        if ($query !== []) {
            // Drop nulls so callers can remove a parameter by passing null.
            $filtered = [];

            foreach ($query as $key => $value) {
                if ($value !== null && $value !== '') {
                    $filtered[$key] = $value;
                }
            }

            if ($filtered !== []) {
                $out .= (strpos($out, '?') === false ? '?' : '&') . http_build_query($filtered);
            }
        }

        return $out;
    }
}

if (!function_exists('absolute_url')) {
    /**
     * The same URL with a scheme and host on the front.
     *
     * Anything that leaves the application — an email, a QR code — needs one of
     * these, because a browser opening a link from a mail client has no idea
     * what "/maintenance/logs.php" is relative to. It comes from app.url in
     * config.php, which the installer writes, falling back to the current host
     * so a half-configured site still produces something that works.
     *
     * @param array<string, mixed> $query
     */
    function absolute_url(string $path = '', array $query = []): string
    {
        $configured = rtrim((string) Config::get('app.url', ''), '/');

        if ($configured !== '' && preg_match('#^https?://#i', $configured) === 1) {
            $parts = parse_url($configured);
            $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
                . (isset($parts['port']) ? ':' . $parts['port'] : '');
        } else {
            $https  = (string) ($_SERVER['HTTPS'] ?? '') !== ''
                && strtolower((string) $_SERVER['HTTPS']) !== 'off';
            $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $origin = ($https ? 'https://' : 'http://') . $host;
        }

        return $origin . url($path, $query);
    }
}

if (!function_exists('asset_url')) {
    /**
     * URL for a file under assets/, with a cache-busting version stamp.
     */
    function asset_url(string $path): string
    {
        $path = ltrim($path, '/');
        $url  = app_base_path() . '/' . $path;

        $file = (defined('APP_ROOT') ? APP_ROOT : '') . '/' . $path;

        if (is_file($file)) {
            $url .= '?v=' . substr((string) filemtime($file), -8);
        }

        return $url;
    }
}

if (!function_exists('current_url')) {
    /** The current URL including its query string. */
    function current_url(): string
    {
        return (string) ($_SERVER['REQUEST_URI'] ?? url());
    }
}

if (!function_exists('request_path')) {
    function request_path(): string
    {
        return Request::path();
    }
}

if (!function_exists('query_string')) {
    /**
     * The current query string with some parameters replaced. Pass null as a
     * value to drop a parameter.
     *
     * @param array<string, mixed> $overrides
     */
    function query_string(array $overrides = []): string
    {
        return Request::queryString($overrides);
    }
}

if (!function_exists('redirect')) {
    /** Send the browser elsewhere and stop. */
    function redirect(string $to, int $status = 302): void
    {
        Response::redirect($to, $status);
    }
}

if (!function_exists('abort'))
{
    /** End the request with an error page or JSON envelope. */
    function abort(int $code, string $message = ''): void
    {
        Response::abortPage($code, $message);
    }
}

if (!function_exists('is_active_nav')) {
    /** Is this the page currently being viewed? Used for nav highlighting. */
    function is_active_nav(string $script): bool
    {
        return Request::script() === $script;
    }
}

// -----------------------------------------------------------------------------
// Request shortcuts
// -----------------------------------------------------------------------------

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return Request::isPost();
    }
}

if (!function_exists('is_ajax')) {
    function is_ajax(): bool
    {
        return Request::isAjax() || Request::wantsJson();
    }
}

if (!function_exists('input')) {
    /**
     * A raw request value.
     *
     * @param  mixed $default
     * @return mixed
     */
    function input(string $key, $default = null)
    {
        return Request::input($key, $default);
    }
}

// -----------------------------------------------------------------------------
// CSRF
// -----------------------------------------------------------------------------

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    /** The hidden input every POST form must contain. */
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_verify')) {
    /** Enforce the CSRF token on a state-changing request. */
    function csrf_verify(): void
    {
        Csrf::verify();
    }
}

// -----------------------------------------------------------------------------
// Flash messages, validation errors and old input
// -----------------------------------------------------------------------------

if (!function_exists('flash')) {
    /** Queue a message for the next page load. */
    function flash(string $type, string $message): void
    {
        Flash::add($type, $message);
    }
}

if (!function_exists('flash_errors')) {
    /**
     * Store validation errors and the submitted values, then let the caller
     * redirect back to the form.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed>  $input
     */
    function flash_errors(array $errors, array $input = [], ?string $message = null): void
    {
        Flash::reject($errors, $input === [] ? $_POST : $input, $message);
    }
}

if (!function_exists('has_error')) {
    function has_error(string $field): bool
    {
        return Flash::hasError($field);
    }
}

if (!function_exists('error_for')) {
    function error_for(string $field): string
    {
        return Flash::errorFor($field);
    }
}

if (!function_exists('old')) {
    /**
     * Repopulate a form field after a rejected submission.
     *
     * @param  mixed $default
     * @return mixed
     */
    function old(string $key, $default = '')
    {
        return Flash::oldValue($key, $default);
    }
}

// -----------------------------------------------------------------------------
// Formatting
// -----------------------------------------------------------------------------

if (!function_exists('money')) {
    /**
     * Format a currency amount using the site's symbol.
     *
     * @param mixed $value
     */
    function money($value, bool $blankIfZero = false): string
    {
        if ($value === null || $value === '') {
            return $blankIfZero ? '' : Settings::currency() . '0.00';
        }

        $amount = (float) $value;

        if ($blankIfZero && abs($amount) < 0.005) {
            return '';
        }

        $symbol = Settings::currency();
        $sign   = $amount < 0 ? '-' : '';

        return $sign . $symbol . number_format(abs($amount), 2);
    }
}

if (!function_exists('num')) {
    /**
     * Format a number with thousands separators.
     *
     * @param mixed $value
     */
    function num($value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return number_format((float) $value, $decimals);
    }
}

if (!function_exists('decimal')) {
    /**
     * Format a decimal, dropping trailing zeros: 1250.00 becomes "1,250".
     *
     * @param mixed $value
     */
    function decimal($value, int $maxDecimals = 2): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $formatted = number_format((float) $value, $maxDecimals, '.', ',');

        if (strpos($formatted, '.') !== false) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $value, int $limit = 100, string $end = '…'): string
    {
        return Str::limit((string) $value, $limit, $end);
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        return Str::initials($name);
    }
}

if (!function_exists('file_size')) {
    function file_size(int $bytes): string
    {
        return Str::formatBytes($bytes);
    }
}

// -----------------------------------------------------------------------------
// Dates — thin wrappers so views read cleanly
// -----------------------------------------------------------------------------

if (!function_exists('fmt_datetime')) {
    function fmt_datetime(?string $utc, string $empty = '—'): string
    {
        return Dates::datetime($utc, $empty);
    }
}

if (!function_exists('fmt_date')) {
    function fmt_date(?string $utc, string $empty = '—'): string
    {
        return Dates::date($utc, $empty);
    }
}

if (!function_exists('fmt_date_only')) {
    /** Format a DATE column without timezone shifting. */
    function fmt_date_only(?string $date, string $empty = '—'): string
    {
        return Dates::dateOnly($date, $empty);
    }
}

if (!function_exists('fmt_time')) {
    function fmt_time(?string $utc, string $empty = '—'): string
    {
        return Dates::time($utc, $empty);
    }
}

if (!function_exists('fmt_ago')) {
    function fmt_ago(?string $utc, string $empty = '—'): string
    {
        return Dates::ago($utc, $empty);
    }
}

if (!function_exists('fmt_duration')) {
    function fmt_duration(?int $minutes, string $empty = '—'): string
    {
        return Dates::humanDuration($minutes, $empty);
    }
}

// -----------------------------------------------------------------------------
// UI
// -----------------------------------------------------------------------------

if (!function_exists('icon')) {
    /** An inline SVG icon. Safe to print directly. */
    function icon(string $name, string $class = '', int $size = 20): string
    {
        return Icon::render($name, $class, $size);
    }
}

if (!function_exists('badge')) {
    /**
     * A status pill for any of the controlled vocabularies.
     *
     *     badge('in_service', 'asset')
     *     badge('urgent', 'priority')
     */
    function badge(?string $value, string $vocabulary = 'asset'): string
    {
        return Status::badge((string) $value, $vocabulary);
    }
}

if (!function_exists('status_label')) {
    function status_label(?string $value, string $vocabulary = 'asset'): string
    {
        return Status::label((string) $value, $vocabulary);
    }
}

if (!function_exists('status_tone')) {
    function status_tone(?string $value, string $vocabulary = 'asset'): string
    {
        return Status::tone((string) $value, $vocabulary);
    }
}

if (!function_exists('sort_link')) {
    /**
     * A sortable column header link that flips direction on each click and
     * keeps every other filter in the query string.
     */
    function sort_link(string $column, string $label, string $currentSort, string $currentDir): string
    {
        $isCurrent = $currentSort === $column;
        $nextDir   = ($isCurrent && strtolower($currentDir) === 'asc') ? 'desc' : 'asc';

        $href = '?' . Request::queryString(['sort' => $column, 'dir' => $nextDir, 'page' => null]);

        $indicator = '';
        $ariaSort  = 'none';

        if ($isCurrent) {
            $ariaSort  = strtolower($currentDir) === 'asc' ? 'ascending' : 'descending';
            $indicator = Icon::render(strtolower($currentDir) === 'asc' ? 'chevron-up' : 'chevron-down', 'sort-indicator', 14);
        }

        return '<a class="sort-link' . ($isCurrent ? ' is-sorted' : '') . '" href="' . e($href)
             . '" aria-sort="' . $ariaSort . '">' . e($label) . $indicator . '</a>';
    }
}

if (!function_exists('selected')) {
    /**
     * Print selected="selected" when two values match.
     *
     * @param mixed $a
     * @param mixed $b
     */
    function selected($a, $b): string
    {
        return (string) $a === (string) $b ? ' selected' : '';
    }
}

if (!function_exists('checked')) {
    /**
     * Print checked when a value is truthy or two values match.
     *
     * @param mixed $a
     * @param mixed $b
     */
    function checked($a, $b = null): string
    {
        if ($b === null) {
            return $a ? ' checked' : '';
        }

        return (string) $a === (string) $b ? ' checked' : '';
    }
}

if (!function_exists('active_class')) {
    /** " is-active" when the condition holds, for nav and tab markup. */
    function active_class(bool $condition, string $class = 'is-active'): string
    {
        return $condition ? ' ' . $class : '';
    }
}

// -----------------------------------------------------------------------------
// Logging
// -----------------------------------------------------------------------------

if (!function_exists('log_error')) {
    /**
     * Append a line to today's error log. Never throws — logging must not be
     * able to break the request that is already going wrong.
     *
     * @param array<string, mixed> $context
     */
    function log_error(string $message, array $context = []): void
    {
        try {
            $dir = (defined('STORAGE_PATH') ? STORAGE_PATH : sys_get_temp_dir()) . '/logs';

            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            if (!is_dir($dir) || !is_writable($dir)) {
                error_log('RideLog: ' . $message);

                return;
            }

            $line = sprintf(
                "[%s] %s%s%s\n",
                gmdate('Y-m-d H:i:s'),
                $message,
                $context === [] ? '' : ' | ' . (string) json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                ' | ' . (string) ($_SERVER['REQUEST_URI'] ?? 'cli')
            );

            @file_put_contents($dir . '/error-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // Give up quietly.
        }
    }
}

if (!function_exists('audit')) {
    /**
     * Shorthand for Audit::record().
     *
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    function audit(
        string $action,
        string $entityType = '',
        ?int $entityId = null,
        string $description = '',
        ?array $old = null,
        ?array $new = null
    ): void {
        Audit::record($action, $entityType, $entityId, $description, $old, $new);
    }
}

// -----------------------------------------------------------------------------
// Small utilities
// -----------------------------------------------------------------------------

if (!function_exists('array_get')) {
    /**
     * Read a nested array value with dot notation.
     *
     * @param  array<string, mixed> $array
     * @param  mixed                $default
     * @return mixed
     */
    function array_get(array $array, string $key, $default = null)
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $value = $array;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('only_keys')) {
    /**
     * Keep only the listed keys of an array.
     *
     * @param  array<string, mixed> $array
     * @param  list<string>         $keys
     * @return array<string, mixed>
     */
    function only_keys(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }
}

if (!function_exists('sort_direction')) {
    /** Normalise a sort direction from the query string to ASC or DESC. */
    function sort_direction(?string $value, string $default = 'DESC'): string
    {
        return strtoupper((string) $value) === 'ASC' ? 'ASC' : (strtoupper((string) $value) === 'DESC' ? 'DESC' : $default);
    }
}

if (!function_exists('sort_column')) {
    /**
     * Resolve a user-supplied sort key against a whitelist.
     *
     * ORDER BY is the one place a bound parameter cannot help, so the column
     * must always come from a map the application controls.
     *
     * @param array<string, string> $allowed request key => SQL expression
     */
    function sort_column(?string $requested, array $allowed, string $default): string
    {
        $key = (string) $requested;

        return $allowed[$key] ?? ($allowed[$default] ?? $default);
    }
}
