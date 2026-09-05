<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * CSV export.
 *
 * Two things matter here beyond writing commas:
 *
 *  1. Excel needs a UTF-8 byte-order mark or it mangles accented characters.
 *  2. A cell beginning with =, +, - or @ is treated as a formula by Excel and
 *     Google Sheets. A maintenance note that starts with "-" would be run as
 *     code on the accountant's laptop, so every such cell is neutralised.
 */
final class Csv
{
    /** Byte-order mark that tells Excel the file is UTF-8. */
    public const BOM = "\xEF\xBB\xBF";

    private function __construct()
    {
    }

    /**
     * Stream a CSV straight to the browser and end the request.
     *
     * Rows are written as they are produced, so exporting fifty thousand
     * maintenance logs does not need fifty thousand rows in memory.
     *
     * @param list<string>                                     $headers
     * @param iterable<array<string, mixed>>                   $rows
     * @param callable(array<string, mixed>): list<mixed>|null $mapper turns a row into cells
     */
    public static function stream(string $filename, array $headers, iterable $rows, ?callable $mapper = null): void
    {
        $filename = self::safeFilename($filename);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            Response::status(200);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"; '
                 . "filename*=UTF-8''" . rawurlencode($filename));
            header('X-Content-Type-Options: nosniff');
            Response::noStore();
        }

        $out = fopen('php://output', 'wb');

        if ($out === false) {
            exit;
        }

        fwrite($out, self::BOM);

        if ($headers !== []) {
            self::putRow($out, $headers);
        }

        foreach ($rows as $row) {
            $cells = $mapper === null ? array_values((array) $row) : $mapper($row);
            self::putRow($out, (array) $cells);

            if (connection_aborted()) {
                break;
            }
        }

        fclose($out);
        exit;
    }

    /**
     * Build a CSV in memory. Use for small exports or for emailing.
     *
     * @param list<string>                                     $headers
     * @param iterable<array<string, mixed>>                   $rows
     * @param callable(array<string, mixed>): list<mixed>|null $mapper
     */
    public static function toString(array $headers, iterable $rows, ?callable $mapper = null, bool $withBom = true): string
    {
        $handle = fopen('php://temp', 'r+b');

        if ($handle === false) {
            return '';
        }

        if ($withBom) {
            fwrite($handle, self::BOM);
        }

        if ($headers !== []) {
            self::putRow($handle, $headers);
        }

        foreach ($rows as $row) {
            $cells = $mapper === null ? array_values((array) $row) : $mapper($row);
            self::putRow($handle, (array) $cells);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content === false ? '' : $content;
    }

    /**
     * One row as a string, for callers writing their own file.
     *
     * @param array<int, mixed> $cells
     */
    public static function line(array $cells): string
    {
        $fields = [];

        foreach ($cells as $cell) {
            $fields[] = '"' . str_replace('"', '""', self::escapeCell($cell)) . '"';
        }

        return implode(',', $fields) . "\r\n";
    }

    /**
     * Write one row.
     *
     * Every field is quoted, not just the ones that strictly need it. That is
     * valid RFC 4180, and it means a cell neutralised against formula
     * injection stays neutralised: Excel treats a quoted leading apostrophe as
     * literal text, where an unquoted one is ambiguous between spreadsheet
     * programs.
     *
     * @param resource          $handle
     * @param array<int, mixed> $cells
     */
    private static function putRow($handle, array $cells): void
    {
        $fields = [];

        foreach ($cells as $cell) {
            $fields[] = '"' . str_replace('"', '""', self::escapeCell($cell)) . '"';
        }

        // CRLF is what Excel expects from a CSV.
        fwrite($handle, implode(',', $fields) . "\r\n");
    }

    /**
     * Neutralise formula injection and flatten non-scalars.
     *
     * @param mixed $value
     */
    public static function escapeCell($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $value = implode('; ', array_map(static function ($item): string {
                return is_scalar($item) ? (string) $item : '';
            }, $value));
        }

        $value = (string) $value;

        // Strip control characters that would corrupt the file.
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

        // A leading =, +, - or @ makes Excel treat the cell as a formula.
        // Tab and carriage return count too, because Excel trims them first.
        if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
            $value = "'" . $value;
        }

        return $value;
    }

    /**
     * Turn a report name into a safe, dated file name.
     */
    public static function filename(string $base, ?string $extension = 'csv'): string
    {
        $slug = Str::slug($base);

        if ($slug === '') {
            $slug = 'export';
        }

        $stamp = Dates::localNow()->format('Y-m-d');

        return $slug . '-' . $stamp . ($extension !== null ? '.' . $extension : '');
    }

    private static function safeFilename(string $filename): string
    {
        $filename = str_replace(['"', '\\', '/', "\r", "\n", "\0"], '', $filename);

        if ($filename === '') {
            return 'export.csv';
        }

        if (!Str::endsWith(strtolower($filename), '.csv')) {
            $filename .= '.csv';
        }

        return $filename;
    }

    /**
     * Read a CSV file into rows keyed by the header line.
     *
     * Used by the parts and asset importers. Returns an empty list when the
     * file cannot be read.
     *
     * @return list<array<string, string>>
     */
    public static function read(string $path, int $maxRows = 5000): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $rows    = [];
        $headers = null;
        $count   = 0;

        try {
            while (($cells = fgetcsv($handle)) !== false) {
                if ($cells === [null] || $cells === false) {
                    continue;
                }

                if ($headers === null) {
                    $headers = [];

                    foreach ($cells as $index => $cell) {
                        // Drop the BOM from the first header cell.
                        if ($index === 0 && is_string($cell)) {
                            $cell = preg_replace('/^\xEF\xBB\xBF/', '', $cell);
                        }

                        $headers[] = Str::snake(Str::tidy((string) $cell));
                    }

                    continue;
                }

                $row = [];

                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $row[$header] = Str::tidy((string) ($cells[$index] ?? ''));
                }

                if (implode('', $row) === '') {
                    continue; // blank line
                }

                $rows[] = $row;
                $count++;

                if ($count >= $maxRows) {
                    break;
                }
            }
        } catch (Throwable $e) {
            log_error('CSV read failed: ' . $e->getMessage(), ['path' => $path]);
        } finally {
            fclose($handle);
        }

        return $rows;
    }
}
