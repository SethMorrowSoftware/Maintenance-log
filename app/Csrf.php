<?php

declare(strict_types=1);

namespace App;

/**
 * Cross-site request forgery protection.
 *
 * One token per session, checked on every state-changing request. Forms carry
 * it in a hidden field; the JSON API expects it in an X-CSRF-Token header.
 */
final class Csrf
{
    public const FIELD  = '_token';
    public const HEADER = 'X-CSRF-Token';

    private const SESSION_KEY = '_csrf_token';

    private function __construct()
    {
    }

    /** The current token, generated on first use. */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // No session (CLI, or a page that runs before session start).
            return '';
        }

        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    /** Issue a fresh token. Called on login and on privilege change. */
    public static function rotate(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return (string) $_SESSION[self::SESSION_KEY];
    }

    /** A ready-to-print hidden input. Put this in every POST form. */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="'
             . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }

    /** A <meta> tag so JavaScript can read the token. */
    public static function metaTag(): string
    {
        return '<meta name="csrf-token" content="'
             . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }

    /**
     * Is the supplied token valid? Pass null to have it read the request.
     */
    public static function check(?string $candidate = null): bool
    {
        $expected = self::token();

        if ($expected === '') {
            return false;
        }

        if ($candidate === null) {
            $candidate = self::fromRequest();
        }

        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    /** Pull the token out of the POST body or the request headers. */
    public static function fromRequest(): string
    {
        if (isset($_POST[self::FIELD]) && is_string($_POST[self::FIELD])) {
            return $_POST[self::FIELD];
        }

        // Apache and nginx expose custom headers as HTTP_X_CSRF_TOKEN.
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        if (isset($_SERVER['HTTP_X_XSRF_TOKEN']) && is_string($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_XSRF_TOKEN'];
        }

        // A JSON body may carry it too. Never the query string: a token in a
        // URL ends up in access logs, history and referrers.
        if (Request::isJson()) {
            $json = Request::json();

            if (isset($json[self::FIELD]) && is_string($json[self::FIELD])) {
                return $json[self::FIELD];
            }
        }

        return '';
    }

    /**
     * Enforce the token, ending the request if it fails.
     *
     * Safe methods are allowed through untouched — GET must never change state,
     * so it needs no token.
     */
    public static function verify(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        if (self::check()) {
            return;
        }

        self::fail();
    }

    /**
     * End the request with a clear explanation.
     *
     * The overwhelmingly common cause is a stale tab left open until the
     * session expired, so the message says so rather than crying "attack".
     */
    private static function fail(): void
    {
        $message = 'Your session expired or the form was stale, so the request was not '
                 . 'processed. Please go back, reload the page and try again.';

        if (function_exists('is_ajax') && is_ajax()) {
            Response::error($message, 'csrf_failed', 419);
        }

        Response::abortPage(419, $message);
    }
}
