<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * The Castle Fun Center starting fleet: twenty go-karts, the rides, the zip
 * line, twelve bowling lanes, six axe-throw lanes and the indoor attractions,
 * each with its checklist and service schedule.
 *
 * The installer loads it on a fresh site. A site that was installed before it
 * existed, or that chose to start empty, can load it later from Settings →
 * System. The SQL matches machines by tag and everything else by name, so
 * whatever the site already has is left exactly as it is, and loading it a
 * second time adds nothing.
 */
final class Fleet
{
    /** Tags that only the starting fleet uses; all present means it is loaded. */
    private const MARKERS = ['GK-001', 'RD-001', 'ZL-001', 'BL-001', 'AX-001', 'CW-001'];

    private function __construct()
    {
    }

    public static function file(): string
    {
        return APP_ROOT . '/install/fleet.sql';
    }

    /** Is the fleet file on the server? The install folder is often deleted. */
    public static function available(): bool
    {
        return is_readable(self::file());
    }

    /** How many of the fleet's signature machines this site has. */
    public static function presentCount(): int
    {
        try {
            $marks = implode(',', array_fill(0, count(self::MARKERS), '?'));

            return db()->count(
                "SELECT COUNT(*) FROM {assets} WHERE asset_tag IN ({$marks}) AND deleted_at IS NULL",
                self::MARKERS
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function present(): bool
    {
        return self::presentCount() === count(self::MARKERS);
    }

    /**
     * Load the fleet into this site's database.
     *
     * @return array{ok: bool, machines: int, checklists: int, schedules: int, errors: list<string>}
     */
    public static function load(): array
    {
        if (!self::available()) {
            return [
                'ok'         => false,
                'machines'   => 0,
                'checklists' => 0,
                'schedules'  => 0,
                'errors'     => ['The fleet file (install/fleet.sql) is not on the server.'],
            ];
        }

        $before = self::counts();
        $result = SqlRunner::executeFile(db()->pdo(), self::file(), db()->prefix(), false);

        // Every new schedule starts one interval out from today.
        try {
            Scheduler::recomputeAll();
        } catch (Throwable $e) {
            log_error('Schedule recompute after loading the fleet failed: ' . $e->getMessage());
        }

        $after = self::counts();
        $added = [
            'machines'   => max(0, $after['machines'] - $before['machines']),
            'checklists' => max(0, $after['checklists'] - $before['checklists']),
            'schedules'  => max(0, $after['schedules'] - $before['schedules']),
        ];

        Audit::record(
            'fleet.load',
            'system',
            null,
            'Loaded the starting fleet: ' . $added['machines'] . ' ' . asset_word(true) . ', '
            . $added['checklists'] . ' checklists, ' . $added['schedules'] . ' schedules added'
            . ($result['ok'] ? '' : ' (with errors)')
        );

        return $added + [
            'ok'     => (bool) $result['ok'],
            'errors' => array_values(array_map('strval', (array) ($result['errors'] ?? []))),
        ];
    }

    /**
     * @return array{machines: int, checklists: int, schedules: int}
     */
    private static function counts(): array
    {
        try {
            return [
                'machines'   => db()->count('SELECT COUNT(*) FROM {assets} WHERE deleted_at IS NULL'),
                'checklists' => db()->count('SELECT COUNT(*) FROM {checklists}'),
                'schedules'  => db()->count('SELECT COUNT(*) FROM {maintenance_schedules}'),
            ];
        } catch (Throwable $e) {
            return ['machines' => 0, 'checklists' => 0, 'schedules' => 0];
        }
    }
}
