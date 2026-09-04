<?php

declare(strict_types=1);

namespace App;

/**
 * Application configuration.
 *
 * Holds the array returned by config/config.php, which the web installer
 * writes. Values are read with dot notation:
 *
 *     Config::get('db.host')
 *     Config::get('app.debug', false)
 *
 * Configuration is read-only at runtime apart from Config::set(), which only
 * affects the current request and is never persisted.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    private static bool $loaded = false;

    /** Not instantiable. */
    private function __construct()
    {
    }

    /**
     * Replace the whole configuration array.
     *
     * @param array<string, mixed> $items
     */
    public static function load(array $items): void
    {
        self::$items  = $items;
        self::$loaded = true;
    }

    /**
     * Load configuration from a PHP file that returns an array.
     *
     * Returns false when the file is missing or does not return an array,
     * which is how bootstrap decides to send the visitor to the installer.
     */
    public static function loadFile(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        /** @psalm-suppress UnresolvableInclude */
        $items = require $path;

        if (!is_array($items)) {
            return false;
        }

        self::load($items);

        return true;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }

    /**
     * Read a value using dot notation.
     *
     * @param  mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        if ($key === '') {
            return $default;
        }

        // Fast path: a top-level key.
        if (array_key_exists($key, self::$items)) {
            return self::$items[$key];
        }

        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function has(string $key): bool
    {
        $sentinel = "\0config-missing\0";

        return self::get($key, $sentinel) !== $sentinel;
    }

    /**
     * Set a value for the current request only. Never written to disk.
     *
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        $segments = explode('.', $key);
        $target   = &self::$items;

        while (count($segments) > 1) {
            $segment = array_shift($segments);

            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target[array_shift($segments)] = $value;
    }

    /**
     * The whole configuration array, with secrets redacted.
     *
     * Used by the diagnostics panel. Never expose the raw array to a template.
     *
     * @return array<string, mixed>
     */
    public static function all(bool $redact = true): array
    {
        if (!$redact) {
            return self::$items;
        }

        return self::redact(self::$items);
    }

    /**
     * @param  array<string, mixed> $items
     * @return array<string, mixed>
     */
    private static function redact(array $items): array
    {
        foreach ($items as $key => $value) {
            if (is_array($value)) {
                $items[$key] = self::redact($value);
                continue;
            }

            if (is_string($key) && preg_match('/pass|secret|key|token|salt/i', $key)) {
                $items[$key] = $value === '' ? '' : '********';
            }
        }

        return $items;
    }
}
