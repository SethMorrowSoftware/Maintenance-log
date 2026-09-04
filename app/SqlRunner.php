<?php

declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

/**
 * Loads .sql files during installation and upgrades.
 *
 * cPanel accounts rarely have shell access, so the application has to execute
 * schema.sql itself. That means splitting a file into statements without a
 * MySQL client, which has to be done properly: a naive explode on ";" breaks
 * the moment a maintenance note or a checklist item contains one.
 *
 * The splitter tracks single quotes, double quotes, backticks, line comments
 * and block comments, so a semicolon inside any of them is left alone.
 */
final class SqlRunner
{
    private function __construct()
    {
    }

    /**
     * Split a SQL script into executable statements.
     *
     * Comments are stripped. Empty statements are dropped.
     *
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $current    = '';
        $length     = strlen($sql);

        $inSingle    = false;
        $inDouble    = false;
        $inBacktick  = false;
        $inLineNote  = false;
        $inBlockNote = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // --- Inside a line comment: run to end of line -------------------
            if ($inLineNote) {
                if ($char === "\n") {
                    $inLineNote = false;
                    $current   .= "\n";
                }
                continue;
            }

            // --- Inside a block comment: run to the closing marker -----------
            if ($inBlockNote) {
                if ($char === '*' && $next === '/') {
                    $inBlockNote = false;
                    $i++;
                }
                continue;
            }

            // --- Comment openers, only outside a quoted string ---------------
            if (!$inSingle && !$inDouble && !$inBacktick) {
                // "-- " or "--" at end of line, and "#"
                if ($char === '-' && $next === '-') {
                    $after = $i + 2 < $length ? $sql[$i + 2] : "\n";

                    if ($after === ' ' || $after === "\t" || $after === "\n" || $after === "\r") {
                        $inLineNote = true;
                        $i++;
                        continue;
                    }
                }

                if ($char === '#') {
                    $inLineNote = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $inBlockNote = true;
                    $i++;
                    continue;
                }
            }

            // --- Quote state -------------------------------------------------
            if ($char === "'" && !$inDouble && !$inBacktick) {
                if ($inSingle) {
                    // A doubled quote ('') is a literal quote, not a terminator.
                    if ($next === "'") {
                        $current .= "''";
                        $i++;
                        continue;
                    }

                    // A backslash-escaped quote is also literal. Count the run
                    // of backslashes: an odd number means this quote is escaped.
                    if (self::precededByOddBackslashes($current)) {
                        $current .= $char;
                        continue;
                    }

                    $inSingle = false;
                } else {
                    $inSingle = true;
                }

                $current .= $char;
                continue;
            }

            if ($char === '"' && !$inSingle && !$inBacktick) {
                if ($inDouble) {
                    if ($next === '"') {
                        $current .= '""';
                        $i++;
                        continue;
                    }

                    if (self::precededByOddBackslashes($current)) {
                        $current .= $char;
                        continue;
                    }

                    $inDouble = false;
                } else {
                    $inDouble = true;
                }

                $current .= $char;
                continue;
            }

            if ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
                $current   .= $char;
                continue;
            }

            // --- Statement terminator ---------------------------------------
            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $trimmed = trim($current);

                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }

                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trailing = trim($current);

        if ($trailing !== '') {
            $statements[] = $trailing;
        }

        return $statements;
    }

    /**
     * Does the accumulated text end in an odd number of backslashes?
     * If so, the quote that follows is escaped.
     */
    private static function precededByOddBackslashes(string $text): bool
    {
        $count  = 0;
        $length = strlen($text);

        for ($i = $length - 1; $i >= 0; $i--) {
            if ($text[$i] !== '\\') {
                break;
            }

            $count++;
        }

        return ($count % 2) === 1;
    }

    /**
     * Replace {table} placeholders with the configured prefix.
     */
    public static function applyPrefix(string $sql, string $prefix): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z_][a-z0-9_]*)\}/',
            static function (array $m) use ($prefix): string {
                return $prefix . $m[1];
            },
            $sql
        );
    }

    /**
     * Execute a SQL script against a connection.
     *
     * Returns a report rather than throwing, so the installer can show the
     * site owner exactly which statement failed and why.
     *
     * @return array{ok: bool, executed: int, failed: int, errors: list<string>}
     */
    public static function execute(PDO $pdo, string $sql, string $prefix = '', bool $stopOnError = true): array
    {
        $statements = self::split(self::applyPrefix($sql, $prefix));

        $executed = 0;
        $failed   = 0;
        $errors   = [];

        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
                $executed++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = self::describeFailure($statement, $e);

                if ($stopOnError) {
                    break;
                }
            }
        }

        return [
            'ok'       => $failed === 0,
            'executed' => $executed,
            'failed'   => $failed,
            'errors'   => $errors,
        ];
    }

    /**
     * @return array{ok: bool, executed: int, failed: int, errors: list<string>}
     */
    public static function executeFile(PDO $pdo, string $path, string $prefix = '', bool $stopOnError = true): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [
                'ok'       => false,
                'executed' => 0,
                'failed'   => 1,
                'errors'   => ['Could not read ' . basename($path) . '. Re-upload the install folder.'],
            ];
        }

        $sql = file_get_contents($path);

        if ($sql === false) {
            return [
                'ok'       => false,
                'executed' => 0,
                'failed'   => 1,
                'errors'   => ['Could not read ' . basename($path) . '.'],
            ];
        }

        return self::execute($pdo, $sql, $prefix, $stopOnError);
    }

    /**
     * Turn a PDO exception into a line a site owner can act on.
     */
    private static function describeFailure(string $statement, Throwable $e): string
    {
        // The first meaningful words identify the statement.
        $summary = trim((string) preg_replace('/\s+/', ' ', substr($statement, 0, 90)));
        $message = $e->getMessage();

        // Strip the SQLSTATE noise from the front.
        $message = (string) preg_replace('/^SQLSTATE\[\w+\]:?\s*/', '', $message);
        $message = (string) preg_replace('/^[^:]*: \d+ /', '', $message);

        $hint = '';

        if (stripos($message, 'access denied') !== false) {
            $hint = ' — the database user needs ALL PRIVILEGES on this database.';
        } elseif (stripos($message, 'already exists') !== false) {
            $hint = ' — this table already exists. If you are reinstalling, drop the old tables first or choose a different table prefix.';
        } elseif (stripos($message, 'specified key was too long') !== false) {
            $hint = ' — your MySQL version has a short index limit. Ask your host to enable innodb_large_prefix, or upgrade to MySQL 5.7 or later.';
        } elseif (stripos($message, 'row size too large') !== false) {
            $hint = ' — ask your host to set innodb_file_format to Barracuda.';
        }

        return $summary . '… → ' . $message . $hint;
    }

    /**
     * A quick sanity check that a database is empty enough to install into.
     *
     * @return list<string> names of RideLog tables that already exist
     */
    public static function existingTables(PDO $pdo, string $prefix): array
    {
        $expected = [
            'users', 'assets', 'maintenance_logs', 'settings', 'work_orders',
            'parts', 'checklists', 'inspections',
        ];

        $found = [];

        foreach ($expected as $table) {
            try {
                $stmt = $pdo->query('SELECT 1 FROM `' . $prefix . $table . '` LIMIT 1');

                if ($stmt !== false) {
                    $found[] = $prefix . $table;
                }
            } catch (Throwable $e) {
                // Missing table: exactly what we want.
            }
        }

        return $found;
    }
}
