<?php

declare(strict_types=1);

namespace App;

/**
 * One-shot session messages and form state.
 *
 * Three separate bags:
 *   - messages     toast/alert notices that survive exactly one redirect
 *   - errors       per-field validation errors
 *   - old input    what the user typed, so a rejected form can be repopulated
 *
 * All three follow the POST-Redirect-Get pattern: written before a redirect,
 * read once on the next page load, then gone.
 */
final class Flash
{
    public const SUCCESS = 'success';
    public const ERROR   = 'error';
    public const WARNING = 'warning';
    public const INFO    = 'info';

    private const KEY_MESSAGES = '_flash_messages';
    private const KEY_ERRORS   = '_flash_errors';
    private const KEY_OLD      = '_flash_old';
    private const KEY_DRAFTS   = '_flash_drafts';

    /** @var list<array{type: string, message: string}>|null read-once buffer */
    private static ?array $readMessages = null;

    /** @var list<string>|null */
    private static ?array $readDrafts = null;

    /** @var array<string, string>|null */
    private static ?array $readErrors = null;

    /** @var array<string, mixed>|null */
    private static ?array $readOld = null;

    private function __construct()
    {
    }

    private static function active(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    // -------------------------------------------------------------------------
    // Messages
    // -------------------------------------------------------------------------

    public static function add(string $type, string $message): void
    {
        if (!self::active() || $message === '') {
            return;
        }

        if (!isset($_SESSION[self::KEY_MESSAGES]) || !is_array($_SESSION[self::KEY_MESSAGES])) {
            $_SESSION[self::KEY_MESSAGES] = [];
        }

        $allowed = [self::SUCCESS, self::ERROR, self::WARNING, self::INFO];

        $_SESSION[self::KEY_MESSAGES][] = [
            'type'    => in_array($type, $allowed, true) ? $type : self::INFO,
            'message' => $message,
        ];
    }

    public static function success(string $message): void
    {
        self::add(self::SUCCESS, $message);
    }

    public static function error(string $message): void
    {
        self::add(self::ERROR, $message);
    }

    public static function warning(string $message): void
    {
        self::add(self::WARNING, $message);
    }

    public static function info(string $message): void
    {
        self::add(self::INFO, $message);
    }

    /**
     * Every pending message, consumed. Calling twice in one request returns the
     * same list rather than an empty one, so the layout and a partial can both
     * ask without racing.
     *
     * @return list<array{type: string, message: string}>
     */
    public static function messages(): array
    {
        if (self::$readMessages !== null) {
            return self::$readMessages;
        }

        $messages = [];

        if (self::active() && isset($_SESSION[self::KEY_MESSAGES]) && is_array($_SESSION[self::KEY_MESSAGES])) {
            foreach ($_SESSION[self::KEY_MESSAGES] as $entry) {
                if (is_array($entry) && isset($entry['type'], $entry['message'])) {
                    $messages[] = [
                        'type'    => (string) $entry['type'],
                        'message' => (string) $entry['message'],
                    ];
                }
            }

            unset($_SESSION[self::KEY_MESSAGES]);
        }

        self::$readMessages = $messages;

        return $messages;
    }

    public static function hasMessages(): bool
    {
        return self::messages() !== [];
    }

    // -------------------------------------------------------------------------
    // Validation errors
    // -------------------------------------------------------------------------

    /**
     * @param array<string, string> $errors field => message
     */
    public static function setErrors(array $errors): void
    {
        if (!self::active()) {
            return;
        }

        $_SESSION[self::KEY_ERRORS] = $errors;
    }

    /**
     * @return array<string, string>
     */
    public static function errors(): array
    {
        if (self::$readErrors !== null) {
            return self::$readErrors;
        }

        $errors = [];

        if (self::active() && isset($_SESSION[self::KEY_ERRORS]) && is_array($_SESSION[self::KEY_ERRORS])) {
            foreach ($_SESSION[self::KEY_ERRORS] as $field => $message) {
                $errors[(string) $field] = (string) $message;
            }

            unset($_SESSION[self::KEY_ERRORS]);
        }

        self::$readErrors = $errors;

        return $errors;
    }

    public static function hasError(string $field): bool
    {
        $errors = self::errors();

        return isset($errors[$field]);
    }

    public static function errorFor(string $field): string
    {
        $errors = self::errors();

        return (string) ($errors[$field] ?? '');
    }

    public static function hasErrors(): bool
    {
        return self::errors() !== [];
    }

    // -------------------------------------------------------------------------
    // Old input
    // -------------------------------------------------------------------------

    /**
     * Remember what the user submitted so the form can be redrawn with it.
     * Password fields are dropped: they must never survive a redirect.
     *
     * @param array<string, mixed> $input
     */
    public static function setOld(array $input): void
    {
        if (!self::active()) {
            return;
        }

        foreach (array_keys($input) as $key) {
            if (preg_match('/pass|token|secret/i', (string) $key)) {
                unset($input[$key]);
            }
        }

        $_SESSION[self::KEY_OLD] = $input;
    }

    /**
     * @return array<string, mixed>
     */
    public static function old(): array
    {
        if (self::$readOld !== null) {
            return self::$readOld;
        }

        $old = [];

        if (self::active() && isset($_SESSION[self::KEY_OLD]) && is_array($_SESSION[self::KEY_OLD])) {
            $old = $_SESSION[self::KEY_OLD];
            unset($_SESSION[self::KEY_OLD]);
        }

        self::$readOld = $old;

        return $old;
    }

    /**
     * A single remembered value, supporting "parts[0][name]" style keys.
     *
     * @param  mixed $default
     * @return mixed
     */
    public static function oldValue(string $key, $default = '')
    {
        $old = self::old();

        if (array_key_exists($key, $old)) {
            return $old[$key];
        }

        // Walk bracketed paths: parts[0][part_name]
        if (strpos($key, '[') !== false && preg_match_all('/([^\[\]]+)/', $key, $m)) {
            $value = $old;

            foreach ($m[1] as $segment) {
                if (!is_array($value) || !array_key_exists($segment, $value)) {
                    return $default;
                }

                $value = $value[$segment];
            }

            return $value;
        }

        return $default;
    }

    /**
     * Store errors and old input together, the usual response to a failed form.
     *
     * @param array<string, string> $errors
     * @param array<string, mixed>  $input
     */
    public static function reject(array $errors, array $input, ?string $message = null): void
    {
        self::setErrors($errors);
        self::setOld($input);

        if ($message !== null) {
            self::error($message);
        } elseif ($errors !== []) {
            $count = count($errors);
            self::error($count === 1
                ? 'There is a problem with one field. Please check it and try again.'
                : 'There are problems with ' . $count . ' fields. Please check them and try again.');
        }
    }

    // -------------------------------------------------------------------------
    // Browser drafts
    // -------------------------------------------------------------------------

    /**
     * The long forms keep a draft in the browser as they are typed (core.js,
     * initDrafts). Only the server knows when a save actually succeeded, so
     * it names the draft here and the next page tells the browser to drop it.
     */
    public static function clearDraft(string $key): void
    {
        if (!self::active() || $key === '') {
            return;
        }

        if (!isset($_SESSION[self::KEY_DRAFTS]) || !is_array($_SESSION[self::KEY_DRAFTS])) {
            $_SESSION[self::KEY_DRAFTS] = [];
        }

        $_SESSION[self::KEY_DRAFTS][] = $key;
    }

    /**
     * Draft keys the browser should forget, consumed.
     *
     * @return list<string>
     */
    public static function draftsToClear(): array
    {
        if (self::$readDrafts !== null) {
            return self::$readDrafts;
        }

        $keys = [];

        if (self::active() && isset($_SESSION[self::KEY_DRAFTS]) && is_array($_SESSION[self::KEY_DRAFTS])) {
            foreach ($_SESSION[self::KEY_DRAFTS] as $key) {
                if (is_string($key) && $key !== '') {
                    $keys[] = $key;
                }
            }

            unset($_SESSION[self::KEY_DRAFTS]);
        }

        self::$readDrafts = array_values(array_unique($keys));

        return self::$readDrafts;
    }

    /** Drop everything. Used on logout. */
    public static function clear(): void
    {
        if (!self::active()) {
            return;
        }

        unset(
            $_SESSION[self::KEY_MESSAGES],
            $_SESSION[self::KEY_ERRORS],
            $_SESSION[self::KEY_OLD],
            $_SESSION[self::KEY_DRAFTS]
        );

        self::$readMessages = null;
        self::$readErrors   = null;
        self::$readOld      = null;
        self::$readDrafts   = null;
    }
}
