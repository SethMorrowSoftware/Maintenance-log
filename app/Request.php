<?php

declare(strict_types=1);

namespace App;

/**
 * Read-only access to the current HTTP request.
 *
 * Everything here returns a predictable type. Nothing here trusts the client.
 */
final class Request
{
    /** @var array<string, mixed>|null decoded JSON body */
    private static ?array $json = null;

    private static bool $jsonParsed = false;

    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // Method
    // -------------------------------------------------------------------------

    public static function method(): string
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        // Browsers can only send GET and POST, so forms may declare their real
        // intent with a hidden _method field.
        if ($method === 'POST' && isset($_POST['_method']) && is_string($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);

            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    public static function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    /** Is this a method that may change state? */
    public static function isWrite(): bool
    {
        return !in_array(self::method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    // -------------------------------------------------------------------------
    // Input
    // -------------------------------------------------------------------------

    /**
     * A value from POST, then GET, then the JSON body.
     *
     * @param  mixed $default
     * @return mixed
     */
    public static function input(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        $json = self::json();

        if (array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $default;
    }

    /**
     * Everything the request carried, POST winning over GET.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return array_merge(self::json(), $_GET, $_POST);
    }

    /**
     * Only the listed keys, with missing ones absent rather than null.
     *
     * @param  list<string> $keys
     * @return array<string, mixed>
     */
    public static function only(array $keys): array
    {
        $all = self::all();
        $out = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $out[$key] = $all[$key];
            }
        }

        return $out;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_POST)
            || array_key_exists($key, $_GET)
            || array_key_exists($key, self::json());
    }

    /** Present and not an empty string. */
    public static function filled(string $key): bool
    {
        $value = self::input($key);

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return $value !== null && $value !== [];
    }

    /** A trimmed string, guaranteed. Arrays become ''. */
    public static function string(string $key, string $default = ''): string
    {
        $value = self::input($key, $default);

        if (is_array($value) || is_object($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    /** An integer, guaranteed. Non-numeric input yields the default. */
    public static function int(string $key, int $default = 0): int
    {
        $value = self::input($key, null);

        if ($value === null || $value === '' || is_array($value)) {
            return $default;
        }

        if (!is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    /** A nullable integer — useful for optional foreign keys. */
    public static function intOrNull(string $key): ?int
    {
        $value = self::input($key, null);

        if ($value === null || $value === '' || is_array($value) || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /** A float, tolerating thousands separators and a currency symbol. */
    public static function decimal(string $key, float $default = 0.0): float
    {
        $value = self::input($key, null);

        if ($value === null || is_array($value)) {
            return $default;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        if ($clean === '' || $clean === null || !is_numeric($clean)) {
            return $default;
        }

        return (float) $clean;
    }

    public static function decimalOrNull(string $key): ?float
    {
        $value = self::input($key, null);

        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        if ($clean === '' || $clean === null || !is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    /** A checkbox. Unchecked boxes are simply absent, which reads as false. */
    public static function bool(string $key): bool
    {
        $value = self::input($key, null);

        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * An array value, guaranteed to be an array.
     *
     * @return array<int|string, mixed>
     */
    public static function array(string $key): array
    {
        $value = self::input($key, []);

        return is_array($value) ? $value : [];
    }

    /**
     * A value constrained to a whitelist. Anything else yields the default.
     *
     * @param list<string> $allowed
     */
    public static function enum(string $key, array $allowed, string $default = ''): string
    {
        $value = self::string($key);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    // -------------------------------------------------------------------------
    // Files
    // -------------------------------------------------------------------------

    /**
     * One uploaded file, or null when nothing was sent.
     *
     * @return array<string, mixed>|null
     */
    public static function file(string $key): ?array
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
            return null;
        }

        $file = $_FILES[$key];

        if (is_array($file['name'] ?? null)) {
            // A multi-file field was accessed as a single one.
            return null;
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    /**
     * A multi-file field, normalised into a list of single-file arrays.
     *
     * @return list<array<string, mixed>>
     */
    public static function files(string $key): array
    {
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key])) {
            return [];
        }

        $field = $_FILES[$key];

        if (!is_array($field['name'] ?? null)) {
            $single = self::file($key);

            return $single === null ? [] : [$single];
        }

        $out   = [];
        $count = count($field['name']);

        for ($i = 0; $i < $count; $i++) {
            if ((int) ($field['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $out[] = [
                'name'     => $field['name'][$i] ?? '',
                'type'     => $field['type'][$i] ?? '',
                'tmp_name' => $field['tmp_name'][$i] ?? '',
                'error'    => $field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $field['size'][$i] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * Did the browser send more data than PHP would accept?
     *
     * When post_max_size is exceeded PHP silently empties $_POST and $_FILES,
     * which otherwise looks like an empty form submission.
     */
    public static function exceededPostLimit(): bool
    {
        if (!self::isPost()) {
            return false;
        }

        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $limit  = Settings::iniToBytes((string) ini_get('post_max_size'));

        return $limit > 0 && $length > $limit && $_POST === [] && $_FILES === [];
    }

    // -------------------------------------------------------------------------
    // JSON body
    // -------------------------------------------------------------------------

    public static function isJson(): bool
    {
        $type = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');

        return stripos($type, 'application/json') !== false;
    }

    /**
     * The decoded JSON body, or an empty array.
     *
     * @return array<string, mixed>
     */
    public static function json(): array
    {
        if (self::$jsonParsed) {
            return self::$json ?? [];
        }

        self::$jsonParsed = true;
        self::$json       = [];

        if (!self::isJson()) {
            return [];
        }

        $raw = file_get_contents('php://input');

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            self::$json = $decoded;
        }

        return self::$json;
    }

    /** Does the caller want JSON back? */
    public static function wantsJson(): bool
    {
        if (self::isJson()) {
            return true;
        }

        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');

        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        return self::isAjax() || self::isApiPath();
    }

    public static function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public static function isApiPath(): bool
    {
        return strpos(self::path(), '/api/') !== false;
    }

    // -------------------------------------------------------------------------
    // Client and URL
    // -------------------------------------------------------------------------

    /**
     * The client IP.
     *
     * Forwarded headers are trusted ONLY when app.trust_proxy is enabled, since
     * anyone can set them. Rate limiting depends on this being honest.
     */
    public static function ip(): string
    {
        if (Config::get('app.trust_proxy', false)) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP'] as $header) {
                if (empty($_SERVER[$header])) {
                    continue;
                }

                // The trusted proxy appends the address it saw at the END of
                // X-Forwarded-For; anything before it was supplied by the client.
                $hops      = array_map('trim', explode(',', (string) $_SERVER[$header]));
                $candidate = (string) end($hops);

                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }

        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '0.0.0.0';
    }

    public static function userAgent(int $maxLength = 255): string
    {
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        return mb_substr($agent, 0, $maxLength, 'UTF-8');
    }

    /** The request path, without the query string. */
    public static function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $cut = strpos($uri, '?');

        return $cut === false ? $uri : substr($uri, 0, $cut);
    }

    /** The current script's file name, e.g. "assets.php". */
    public static function script(): string
    {
        return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    }

    /** The full current URL, query string included. */
    public static function fullUrl(): string
    {
        return self::baseUrl() . (string) ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public static function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        if (Config::get('app.trust_proxy', false)) {
            if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
                return true;
            }

            if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
                return true;
            }
        }

        return false;
    }

    /** Scheme and host, no trailing slash. */
    public static function baseUrl(): string
    {
        $scheme = self::isSecure() ? 'https' : 'http';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');

        // Strip anything that is not a valid host, since HTTP_HOST is attacker
        // controlled and ends up in generated URLs.
        $host = preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $host) ?? 'localhost';

        return $scheme . '://' . $host;
    }

    /**
     * Where the user came from, but only if it is a URL on this site.
     * Never redirect to an untrusted referer.
     */
    public static function safeReferer(?string $fallback = null): ?string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

        if ($referer === '') {
            return $fallback;
        }

        $parts = parse_url($referer);

        if ($parts === false || !isset($parts['host'])) {
            return $fallback;
        }

        $currentHost = parse_url(self::baseUrl(), PHP_URL_HOST);

        if (!is_string($currentHost) || strcasecmp($parts['host'], $currentHost) !== 0) {
            return $fallback;
        }

        return $referer;
    }

    /**
     * A redirect target supplied by the request, accepted only when it is a
     * relative path on this site. Blocks open-redirect attempts.
     */
    public static function safeRedirect(string $key = 'redirect'): ?string
    {
        $target = self::string($key);

        if ($target === '') {
            return null;
        }

        // Must be a site-relative path: no scheme, no host, no protocol-relative.
        // Control characters are out too: browsers strip a tab or newline
        // before parsing, which would turn "/<tab>/evil" into "//evil".
        if (strpos($target, '//') === 0 || strpos($target, '\\') !== false
            || preg_match('/[\x00-\x1F\x7F]/', $target)) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $target)) {
            return null;
        }

        if (strpos($target, '/') !== 0 && !preg_match('#^[A-Za-z0-9_\-]+\.php#', $target)) {
            return null;
        }

        return $target;
    }

    public static function page(): int
    {
        return max(1, self::int('page', 1));
    }

    public static function perPage(?int $default = null): int
    {
        $default = $default ?? Settings::perPage();
        $value   = self::int('per_page', $default);

        return max(5, min(200, $value));
    }

    /**
     * The current query string with some parameters replaced or removed.
     * Passing null as a value drops that parameter.
     *
     * @param array<string, mixed> $overrides
     */
    public static function queryString(array $overrides = []): string
    {
        $params = $_GET;

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($params[$key]);
            } else {
                $params[$key] = $value;
            }
        }

        // The CSRF token has no business being carried through pagination links.
        unset($params[Csrf::FIELD]);

        return http_build_query($params);
    }
}
