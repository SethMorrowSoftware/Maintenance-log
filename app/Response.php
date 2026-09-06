<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Everything that finishes a request: JSON envelopes, redirects, file
 * downloads and error pages.
 *
 * Methods that end the request call exit(). They are annotated as returning
 * void rather than never so the code stays valid on PHP 8.0.
 */
final class Response
{
    /** @var array<int, string> */
    private const STATUS_TEXT = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        302 => 'Found',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        413 => 'Payload Too Large',
        419 => 'Session Expired',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // JSON
    // -------------------------------------------------------------------------

    /**
     * A successful JSON response in the standard envelope.
     *
     * @param mixed                $data
     * @param array<string, mixed> $meta
     */
    public static function json($data = null, array $meta = [], int $status = 200): void
    {
        $payload = ['ok' => true, 'data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        self::sendJson($payload, $status);
    }

    /**
     * A failed JSON response.
     *
     * @param array<string, string> $errors field => message
     */
    public static function error(
        string $message,
        string $code = 'error',
        int $status = 400,
        array $errors = []
    ): void {
        $payload = [
            'ok'    => false,
            'error' => $message,
            'code'  => $code,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        self::sendJson($payload, $status);
    }

    public static function noContent(): void
    {
        self::status(204);
        self::noStore();
        exit;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function sendJson(array $payload, int $status): void
    {
        if (!headers_sent()) {
            self::status($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            self::noStore();
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (Config::get('app.debug', false)) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $encoded = json_encode($payload, $flags);

        if ($encoded === false) {
            // Encoding failed — usually invalid UTF-8 from the database.
            $encoded = json_encode([
                'ok'    => false,
                'error' => 'The response could not be encoded.',
                'code'  => 'encoding_failed',
            ]);
        }

        echo (string) $encoded;
        exit;
    }

    // -------------------------------------------------------------------------
    // Status and headers
    // -------------------------------------------------------------------------

    public static function status(int $code): void
    {
        if (headers_sent()) {
            return;
        }

        $text = self::STATUS_TEXT[$code] ?? 'Unknown';

        http_response_code($code);
        header(sprintf('%s %d %s', (string) ($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1'), $code, $text), true, $code);
    }

    /** Authenticated pages must never sit in a shared or browser cache. */
    public static function noStore(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    // -------------------------------------------------------------------------
    // Redirects
    // -------------------------------------------------------------------------

    /**
     * Send the browser somewhere else and stop.
     *
     * Absolute URLs are only allowed when they point at this site, which closes
     * the open-redirect hole.
     */
    public static function redirect(string $to, int $status = 302): void
    {
        $target = self::sanitizeRedirect($to);

        if (!headers_sent()) {
            self::status($status);
            header('Location: ' . $target);
        }

        // A body for the rare client that ignores the header.
        $safe = htmlspecialchars($target, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><meta charset="utf-8"><title>Redirecting</title>'
           . '<p>Redirecting to <a href="' . $safe . '">' . $safe . '</a>.</p>';

        exit;
    }

    private static function sanitizeRedirect(string $to): string
    {
        $to = trim($to);

        if ($to === '') {
            return function_exists('url') ? url('index.php') : 'index.php';
        }

        // Strip control characters so a crafted value cannot inject extra
        // headers, or smuggle "//" past the checks below behind a tab.
        $to = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $to);

        if (preg_match('#^https?://#i', $to)) {
            $host        = parse_url($to, PHP_URL_HOST);
            $currentHost = parse_url(Request::baseUrl(), PHP_URL_HOST);

            if (!is_string($host) || !is_string($currentHost) || strcasecmp($host, $currentHost) !== 0) {
                return function_exists('url') ? url('index.php') : 'index.php';
            }

            return $to;
        }

        // Protocol-relative URLs point off-site.
        if (strpos($to, '//') === 0) {
            return function_exists('url') ? url('index.php') : 'index.php';
        }

        return $to;
    }

    /** Redirect back where the user came from, or to a fallback. */
    public static function back(string $fallback = 'index.php'): void
    {
        $referer = Request::safeReferer();

        self::redirect($referer ?? (function_exists('url') ? url($fallback) : $fallback));
    }

    // -------------------------------------------------------------------------
    // Files
    // -------------------------------------------------------------------------

    /**
     * Stream a file from disk to the browser.
     *
     * Supports HTTP range requests so a large photo or PDF can be scrubbed and
     * resumed. The caller is responsible for the authorisation check.
     */
    public static function file(
        string $path,
        string $downloadName,
        string $mime = 'application/octet-stream',
        bool $inline = true
    ): void {
        if (!is_file($path) || !is_readable($path)) {
            self::abortPage(404, 'That file is no longer available.');
        }

        $size = (int) filesize($path);

        // Never let the browser sniff a stored file into something executable.
        $safeMime = self::safeMime($mime);

        // Strip anything path-like or quote-like out of the download name.
        $downloadName = str_replace(['"', '\\', '/', "\r", "\n", "\0"], '', $downloadName);

        if ($downloadName === '') {
            $downloadName = 'download';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $disposition = $inline ? 'inline' : 'attachment';
        $start       = 0;
        $end         = $size - 1;
        $status      = 200;

        $range = (string) ($_SERVER['HTTP_RANGE'] ?? '');

        if ($range !== '' && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            $rangeStart = $m[1] === '' ? null : (int) $m[1];
            $rangeEnd   = $m[2] === '' ? null : (int) $m[2];

            if ($rangeStart === null && $rangeEnd !== null) {
                // Suffix range: the last N bytes.
                $start = max(0, $size - $rangeEnd);
            } else {
                $start = $rangeStart ?? 0;
                if ($rangeEnd !== null) {
                    $end = min($rangeEnd, $size - 1);
                }
            }

            if ($start > $end || $start >= $size) {
                self::status(416);
                header('Content-Range: bytes */' . $size);
                exit;
            }

            $status = 206;
        }

        $length = $end - $start + 1;

        self::status($status);
        header('Content-Type: ' . $safeMime);
        header('Content-Length: ' . $length);
        header('Content-Disposition: ' . $disposition . '; filename="' . $downloadName . '"; '
             . "filename*=UTF-8''" . rawurlencode($downloadName));
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'; sandbox');
        header('Cache-Control: private, max-age=3600');

        if ($status === 206) {
            header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            exit;
        }

        if ($start > 0) {
            fseek($handle, $start);
        }

        $remaining = $length;
        $chunkSize = 8192;

        while ($remaining > 0 && !feof($handle)) {
            $chunk = fread($handle, (int) min($chunkSize, $remaining));

            if ($chunk === false) {
                break;
            }

            echo $chunk;
            $remaining -= strlen($chunk);

            if (connection_aborted()) {
                break;
            }

            flush();
        }

        fclose($handle);
        exit;
    }

    /**
     * Downgrade any MIME type that a browser might execute.
     *
     * Uploaded files are user-controlled, so an HTML or SVG file served with
     * its natural type would run script in this origin.
     */
    private static function safeMime(string $mime): string
    {
        $mime = strtolower(trim($mime));

        $dangerous = [
            'text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml',
            'application/xml', 'application/javascript', 'text/javascript',
            'application/x-httpd-php', 'application/xhtml',
        ];

        if ($mime === '' || in_array($mime, $dangerous, true)) {
            return 'application/octet-stream';
        }

        if (!preg_match('#^[a-z0-9!\#$&^_.+\-]+/[a-z0-9!\#$&^_.+\-]+$#', $mime)) {
            return 'application/octet-stream';
        }

        return $mime;
    }

    /** Send a string as a downloadable file. */
    public static function download(string $content, string $filename, string $mime = 'text/plain'): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = str_replace(['"', '\\', '/', "\r", "\n", "\0"], '', $filename);

        self::status(200);
        header('Content-Type: ' . self::safeMime($mime) . '; charset=utf-8');
        header('Content-Length: ' . strlen($content));
        header('Content-Disposition: attachment; filename="' . $filename . '"; '
             . "filename*=UTF-8''" . rawurlencode($filename));
        header('X-Content-Type-Options: nosniff');
        self::noStore();

        echo $content;
        exit;
    }

    // -------------------------------------------------------------------------
    // Error pages
    // -------------------------------------------------------------------------

    /**
     * Render a friendly error page and stop.
     *
     * Under /api/ or an AJAX request this emits the JSON envelope instead, so
     * the client always gets a shape it understands.
     */
    public static function abortPage(int $code, string $message = ''): void
    {
        if ($message === '') {
            $message = self::defaultMessage($code);
        }

        if (Request::wantsJson()) {
            self::error($message, self::codeSlug($code), $code);
        }

        if (!headers_sent()) {
            self::status($code);
            header('Content-Type: text/html; charset=utf-8');
            self::noStore();
        }

        // Prefer the styled view. Fall back to inline HTML if the view is
        // missing or itself throws, because an error page must never fail.
        try {
            if (class_exists(View::class) && View::exists('errors/' . $code)) {
                View::render('errors/' . $code, [
                    'title'     => self::STATUS_TEXT[$code] ?? 'Error',
                    'code'      => $code,
                    'message'   => $message,
                    'bodyClass' => 'page-error',
                ], 'layout-bare');

                exit;
            }
        } catch (Throwable $e) {
            // Fall through to the plain page below.
        }

        echo self::plainErrorPage($code, $message);
        exit;
    }

    private static function defaultMessage(int $code): string
    {
        switch ($code) {
            case 400:
                return 'That request could not be understood.';
            case 401:
                return 'You need to sign in to view this page.';
            case 403:
                return 'You do not have permission to view this page. If you think you should, ask an administrator to check your role.';
            case 404:
                return 'That page or record does not exist. It may have been deleted.';
            case 405:
                return 'That action is not allowed on this page.';
            case 413:
                return 'That file is larger than the server will accept.';
            case 419:
                return 'Your session expired. Please reload the page and try again.';
            case 429:
                return 'Too many attempts. Please wait a few minutes and try again.';
            case 503:
                return 'The site is temporarily unavailable. Please try again shortly.';
            default:
                return 'Something went wrong. The error has been logged.';
        }
    }

    private static function codeSlug(int $code): string
    {
        switch ($code) {
            case 401:
                return 'unauthenticated';
            case 403:
                return 'forbidden';
            case 404:
                return 'not_found';
            case 419:
                return 'csrf_failed';
            case 422:
                return 'validation_failed';
            case 429:
                return 'rate_limited';
            default:
                return 'error';
        }
    }

    /**
     * A completely self-contained error page: no layout, no CSS file, no
     * database. It has to work when everything else is broken.
     */
    private static function plainErrorPage(int $code, string $message): string
    {
        $title    = self::STATUS_TEXT[$code] ?? 'Error';
        $safeMsg  = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeTtl  = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $home     = function_exists('url') ? url('index.php') : 'index.php';
        $safeHome = htmlspecialchars($home, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$code} — {$safeTtl}</title>
<style>
  :root { color-scheme: light dark; }
  * { box-sizing: border-box; }
  body {
    margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
    font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    background: #f6f7f9; color: #1f2430;
  }
  .box {
    max-width: 520px; width: 100%; background: #fff; border: 1px solid #e3e6ec;
    border-radius: 14px; padding: 40px 32px; text-align: center;
    box-shadow: 0 1px 3px rgba(16,24,40,.06), 0 8px 24px rgba(16,24,40,.05);
  }
  .code { font-size: 56px; font-weight: 700; letter-spacing: -.02em; color: #4f46e5; margin: 0 0 4px; }
  h1 { font-size: 20px; margin: 0 0 12px; }
  p { margin: 0 0 24px; color: #59616e; }
  a.btn {
    display: inline-block; padding: 10px 20px; border-radius: 8px; background: #4f46e5;
    color: #fff; text-decoration: none; font-weight: 600; font-size: 15px;
  }
  a.btn:hover { background: #4338ca; }
  @media (prefers-color-scheme: dark) {
    body { background: #12151c; color: #e6e9ef; }
    .box { background: #1a1f29; border-color: #2b3240; box-shadow: none; }
    p { color: #9aa3b2; }
  }
</style>
</head>
<body>
  <div class="box">
    <p class="code">{$code}</p>
    <h1>{$safeTtl}</h1>
    <p>{$safeMsg}</p>
    <a class="btn" href="{$safeHome}">Back to the dashboard</a>
  </div>
</body>
</html>
HTML;
    }
}
