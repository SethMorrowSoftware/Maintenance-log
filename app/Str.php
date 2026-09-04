<?php

declare(strict_types=1);

namespace App;

/**
 * String helpers. Multibyte-safe wherever it matters.
 */
final class Str
{
    private function __construct()
    {
    }

    /** "Go-Kart #3" becomes "go-kart-3". */
    public static function slug(string $value, string $separator = '-'): string
    {
        $value = self::ascii($value);
        $value = strtolower($value);
        $value = (string) preg_replace('/[^a-z0-9]+/', $separator, $value);

        return trim($value, $separator);
    }

    /** Best-effort transliteration to plain ASCII. */
    public static function ascii(string $value): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return (string) preg_replace('/[^\x20-\x7E]/', '', $value);
    }

    /** "work_orders" becomes "WorkOrders". */
    public static function studly(string $value): string
    {
        $value = str_replace(['-', '_', '.'], ' ', $value);

        return str_replace(' ', '', ucwords($value));
    }

    /** "work_orders" becomes "workOrders". */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /** "WorkOrders" becomes "work_orders". */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        if (ctype_lower($value)) {
            return $value;
        }

        $value = (string) preg_replace('/\s+/u', '', ucwords($value));
        $value = (string) preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value);

        return mb_strtolower($value, 'UTF-8');
    }

    /** "asset_tag" becomes "Asset tag" — for validation messages and labels. */
    public static function humanize(string $value): string
    {
        $value = str_replace(['_', '-', '.'], ' ', $value);
        $value = (string) preg_replace('/\[\d*\]/', '', $value);
        $value = trim((string) preg_replace('/\s+/', ' ', $value));

        return ucfirst($value);
    }

    /** "in_service" becomes "In service". Vocabulary values are snake_case. */
    public static function label(string $value): string
    {
        return ucfirst(str_replace('_', ' ', $value));
    }

    /** Cryptographically strong random hex string. */
    public static function random(int $length = 32): string
    {
        if ($length < 1) {
            $length = 1;
        }

        $bytes = random_bytes((int) ceil($length / 2));

        return substr(bin2hex($bytes), 0, $length);
    }

    /** URL-safe random token (no ambiguous characters). */
    public static function token(int $length = 40): string
    {
        $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max      = strlen($alphabet) - 1;
        $out      = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * A starting password somebody has to read off a screen and type once.
     *
     * No l/1/I/O/0, because it will be read aloud across a workshop and typed
     * wrong otherwise. It always contains a capital, a lower case letter, a
     * digit and a symbol, so it satisfies whatever the password policy is set
     * to before anybody sees it.
     */
    public static function password(int $length = 12): string
    {
        $length  = max(10, $length);
        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghijkmnopqrstuvwxyz';
        $digits  = '23456789';
        $symbols = '!@#$%&*?';

        $pick = static function (string $set): string {
            return $set[random_int(0, strlen($set) - 1)];
        };

        $chars = [$pick($upper), $pick($lower), $pick($digits), $pick($symbols)];
        $all   = $upper . $lower . $digits . $symbols;

        while (count($chars) < $length) {
            $chars[] = $pick($all);
        }

        // Fisher-Yates, so the guaranteed characters are not always in front.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /** "Mike Torres" becomes "MT". */
    public static function initials(string $name, int $max = 2): string
    {
        $parts    = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');

            if (mb_strlen($initials, 'UTF-8') >= $max) {
                break;
            }
        }

        return $initials !== '' ? $initials : '?';
    }

    /** Truncate on a word boundary and append an ellipsis. */
    public static function limit(string $value, int $limit = 100, string $end = '…'): string
    {
        $value = trim($value);

        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        $truncated = mb_substr($value, 0, $limit, 'UTF-8');
        $lastSpace = mb_strrpos($truncated, ' ', 0, 'UTF-8');

        if ($lastSpace !== false && $lastSpace > (int) ($limit * 0.6)) {
            $truncated = mb_substr($truncated, 0, $lastSpace, 'UTF-8');
        }

        return rtrim($truncated) . $end;
    }

    /** "someone@example.com" becomes "s******e@example.com". */
    public static function maskEmail(string $email): string
    {
        $at = strpos($email, '@');

        if ($at === false || $at < 1) {
            return str_repeat('*', max(3, strlen($email)));
        }

        $local  = substr($email, 0, $at);
        $domain = substr($email, $at);
        $length = strlen($local);

        if ($length <= 2) {
            return str_repeat('*', $length) . $domain;
        }

        return $local[0] . str_repeat('*', $length - 2) . $local[$length - 1] . $domain;
    }

    /** 1536 becomes "1.5 KB". */
    public static function formatBytes(int $bytes, int $precision = 1): string
    {
        if ($bytes < 0) {
            $bytes = 0;
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, $i === 0 ? 0 : $precision) . ' ' . $units[$i];
    }

    /**
     * Split a search box value into terms, honouring "quoted phrases".
     *
     * @return list<string>
     */
    public static function parseSearch(string $query, int $maxTerms = 6): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $terms = [];

        if (preg_match_all('/"([^"]+)"|(\S+)/u', $query, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $term = trim($match[1] !== '' ? $match[1] : ($match[2] ?? ''));

                if ($term !== '' && mb_strlen($term, 'UTF-8') >= 1) {
                    $terms[] = $term;
                }

                if (count($terms) >= $maxTerms) {
                    break;
                }
            }
        }

        return $terms;
    }

    /**
     * Escape the wildcard characters in a LIKE operand.
     *
     * Always pair with a bound parameter — this escapes wildcards, it is not a
     * substitute for parameter binding.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /** Wrap a search term for a "contains" LIKE. */
    public static function likeContains(string $value): string
    {
        return '%' . self::escapeLike($value) . '%';
    }

    /** Escape for HTML, then turn newlines into <br>. Output is safe to print. */
    public static function nl2brEscaped(?string $value): string
    {
        $escaped = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return nl2br($escaped, false);
    }

    /** Does the string start with the prefix? */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    /** Does the string end with the suffix? */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }

    /**
     * Collapse whitespace and trim. Used on every text input before storage so
     * "  Go-Kart   #3 " and "Go-Kart #3" are the same value.
     */
    public static function tidy(?string $value): string
    {
        $value = (string) $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = (string) preg_replace('/[ \t]+/', ' ', $value);

        return trim($value);
    }

    /**
     * Tidy a multi-line block: normalise line endings, strip trailing spaces,
     * and collapse runs of blank lines.
     */
    public static function tidyBlock(?string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $value = (string) preg_replace('/[ \t]+\n/', "\n", $value);
        $value = (string) preg_replace('/\n{3,}/', "\n\n", $value);

        return trim($value);
    }

    /**
     * A deterministic pleasant colour for a label, used for avatars and chips.
     */
    public static function colorFor(string $seed): string
    {
        $palette = [
            '#4f46e5', '#0ea5e9', '#0891b2', '#059669', '#65a30d',
            '#ca8a04', '#ea580c', '#dc2626', '#db2777', '#7c3aed',
        ];

        $index = abs(crc32($seed)) % count($palette);

        return $palette[$index];
    }

    /**
     * Pad a sequence number, e.g. sequence(7, 'WO-', 6) gives "WO-000007".
     */
    public static function sequence(int $number, string $prefix = '', int $pad = 6): string
    {
        return $prefix . str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Pull the numeric tail out of a formatted sequence number.
     * "WO-000042" becomes 42. Returns 0 when there is no number.
     */
    public static function sequenceNumber(string $value): int
    {
        if (preg_match('/(\d+)\s*$/', $value, $m)) {
            return (int) $m[1];
        }

        return 0;
    }
}
