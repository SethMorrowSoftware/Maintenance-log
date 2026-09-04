<?php

declare(strict_types=1);

namespace App;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Every date and time in RideLog passes through here.
 *
 * The rule: DATETIME columns hold UTC. Nothing else. PHP's default timezone is
 * set to UTC in bootstrap, and the connection runs at +00:00, so a naive
 * date('Y-m-d H:i:s') is already correct for storage.
 *
 * DATE columns (due dates, purchase dates) hold a local calendar date and are
 * never timezone-shifted — 12 March is 12 March regardless of the clock.
 */
final class Dates
{
    public const DB_FORMAT   = 'Y-m-d H:i:s';
    public const DB_DATE     = 'Y-m-d';
    public const FALLBACK_TZ = 'America/New_York';

    private static ?DateTimeZone $displayZone = null;

    private static ?DateTimeZone $utcZone = null;

    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // Zones
    // -------------------------------------------------------------------------

    public static function utcZone(): DateTimeZone
    {
        if (self::$utcZone === null) {
            self::$utcZone = new DateTimeZone('UTC');
        }

        return self::$utcZone;
    }

    /**
     * The zone the site displays times in.
     *
     * Order of preference: the signed-in user's own timezone, the site setting,
     * the app config, then a sane default. Wrapped in try/catch because this is
     * called during installation when settings do not exist yet.
     */
    public static function displayZone(?string $override = null): DateTimeZone
    {
        if ($override !== null && $override !== '') {
            $zone = self::makeZone($override);
            if ($zone !== null) {
                return $zone;
            }
        }

        if (self::$displayZone instanceof DateTimeZone) {
            return self::$displayZone;
        }

        $name = '';

        try {
            $user = function_exists('user') ? user() : null;
            if (is_array($user) && !empty($user['timezone'])) {
                $name = (string) $user['timezone'];
            }
        } catch (Throwable $e) {
            $name = '';
        }

        if ($name === '') {
            try {
                $name = (string) Settings::get('timezone', '');
            } catch (Throwable $e) {
                $name = '';
            }
        }

        if ($name === '') {
            $name = (string) Config::get('app.timezone', self::FALLBACK_TZ);
        }

        $zone = self::makeZone($name) ?? new DateTimeZone(self::FALLBACK_TZ);

        // Only cache the site-wide answer, never a per-call override.
        self::$displayZone = $zone;

        return $zone;
    }

    /**
     * Forget the cached display zone — call after the timezone setting changes
     * or after a user signs in.
     */
    public static function resetZoneCache(): void
    {
        self::$displayZone = null;
    }

    private static function makeZone(string $name): ?DateTimeZone
    {
        if ($name === '') {
            return null;
        }

        try {
            return new DateTimeZone($name);
        } catch (Throwable $e) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Now
    // -------------------------------------------------------------------------

    /** Current UTC timestamp in database format. Use instead of MySQL NOW(). */
    public static function nowUtc(): string
    {
        return gmdate(self::DB_FORMAT);
    }

    /** Current instant as an immutable UTC value. */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::utcZone());
    }

    /** Today's calendar date in the display zone, as Y-m-d. */
    public static function today(?string $tz = null): string
    {
        return self::now()->setTimezone(self::displayZone($tz))->format(self::DB_DATE);
    }

    /** Local wall-clock time right now, as a DateTimeImmutable. */
    public static function localNow(?string $tz = null): DateTimeImmutable
    {
        return self::now()->setTimezone(self::displayZone($tz));
    }

    // -------------------------------------------------------------------------
    // Parsing and conversion
    // -------------------------------------------------------------------------

    /**
     * Turn something a user typed into a UTC database string.
     *
     * Accepts what the browser sends from <input type="datetime-local">
     * ("2026-09-04T14:30"), plus "Y-m-d H:i[:s]" and a bare "Y-m-d" (treated as
     * midnight local). Returns null for empty or unparseable input.
     */
    public static function toUtc(?string $local, ?string $tz = null): ?string
    {
        $local = trim((string) $local);

        if ($local === '') {
            return null;
        }

        $zone = self::displayZone($tz);

        // Normalise the datetime-local "T" separator.
        $normalised = str_replace('T', ' ', $local);

        // If it opens with a calendar date, that date must actually exist.
        // Checking here stops the lenient fallback below from turning
        // "2026-02-29 10:00" into 1 March without anyone noticing.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $normalised, $m) && self::parseDate($m[1]) === null) {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'];

        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat('!' . $format, $normalised, $zone);

            if ($dt instanceof DateTimeImmutable) {
                $errors = DateTimeImmutable::getLastErrors();
                if (!is_array($errors) || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0)) {
                    return $dt->setTimezone(self::utcZone())->format(self::DB_FORMAT);
                }
            }
        }

        // Last resort: let PHP interpret it in the display zone.
        try {
            $dt = new DateTimeImmutable($normalised, $zone);

            return $dt->setTimezone(self::utcZone())->format(self::DB_FORMAT);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Parse a UTC database string into an immutable value in the display zone.
     */
    public static function toLocal(?string $utc, ?string $tz = null): ?DateTimeImmutable
    {
        $parsed = self::parseUtc($utc);

        if ($parsed === null) {
            return null;
        }

        return $parsed->setTimezone(self::displayZone($tz));
    }

    /** Parse a UTC database string, staying in UTC. */
    public static function parseUtc(?string $utc): ?DateTimeImmutable
    {
        $utc = trim((string) $utc);

        if ($utc === '' || strpos($utc, '0000-00-00') === 0) {
            return null;
        }

        try {
            return new DateTimeImmutable($utc, self::utcZone());
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Parse a plain calendar date (no timezone maths). Returns null if invalid.
     *
     * Strict: PHP would happily turn "2026-02-29" into 1 March, which is how a
     * typo becomes a maintenance record dated a day after it happened. A date
     * that does not round-trip exactly is rejected instead.
     */
    public static function parseDate(?string $date): ?DateTimeImmutable
    {
        $date = trim((string) $date);

        if ($date === '' || strpos($date, '0000-00-00') === 0) {
            return null;
        }

        $candidate = substr($date, 0, 10);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $candidate, self::utcZone());

        if (!$dt instanceof DateTimeImmutable) {
            return null;
        }

        // Reject anything PHP silently rolled over (29 Feb in a common year).
        if ($dt->format('Y-m-d') !== $candidate) {
            return null;
        }

        return $dt;
    }

    /**
     * Normalise a date the user typed into Y-m-d, or null.
     */
    public static function toDate(?string $input): ?string
    {
        $input = trim((string) $input);

        if ($input === '') {
            return null;
        }

        $dt = self::parseDate($input);

        if ($dt instanceof DateTimeImmutable) {
            return $dt->format(self::DB_DATE);
        }

        // Something already shaped like Y-m-d that parseDate rejected is simply
        // an invalid date. Do not let the lenient parser below "fix" it.
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $input)) {
            return null;
        }

        try {
            return (new DateTimeImmutable($input, self::utcZone()))->format(self::DB_DATE);
        } catch (Throwable $e) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Display
    // -------------------------------------------------------------------------

    public static function dateFormat(): string
    {
        try {
            $format = (string) Settings::get('date_format', 'M j, Y');
        } catch (Throwable $e) {
            $format = 'M j, Y';
        }

        return $format !== '' ? $format : 'M j, Y';
    }

    public static function timeFormat(): string
    {
        try {
            $format = (string) Settings::get('time_format', 'g:i A');
        } catch (Throwable $e) {
            $format = 'g:i A';
        }

        return $format !== '' ? $format : 'g:i A';
    }

    /** "Sep 4, 2026 2:30 PM" */
    public static function datetime(?string $utc, string $empty = '—'): string
    {
        $dt = self::toLocal($utc);

        if ($dt === null) {
            return $empty;
        }

        return $dt->format(self::dateFormat() . ' ' . self::timeFormat());
    }

    /** "Sep 4, 2026" from a UTC datetime. */
    public static function date(?string $utc, string $empty = '—'): string
    {
        $dt = self::toLocal($utc);

        if ($dt === null) {
            return $empty;
        }

        return $dt->format(self::dateFormat());
    }

    /** "2:30 PM" from a UTC datetime. */
    public static function time(?string $utc, string $empty = '—'): string
    {
        $dt = self::toLocal($utc);

        if ($dt === null) {
            return $empty;
        }

        return $dt->format(self::timeFormat());
    }

    /** Format a plain DATE column without any timezone shifting. */
    public static function dateOnly(?string $date, string $empty = '—'): string
    {
        $dt = self::parseDate($date);

        return $dt === null ? $empty : $dt->format(self::dateFormat());
    }

    /** Arbitrary PHP format applied to a stored UTC value in the display zone. */
    public static function format(?string $utc, string $phpFormat, string $empty = '—'): string
    {
        $dt = self::toLocal($utc);

        return $dt === null ? $empty : $dt->format($phpFormat);
    }

    /** Value for <input type="datetime-local">. */
    public static function inputDatetime(?string $utc): string
    {
        $dt = self::toLocal($utc);

        return $dt === null ? '' : $dt->format('Y-m-d\TH:i');
    }

    /** Value for <input type="date"> from a UTC datetime. */
    public static function inputDate(?string $utc): string
    {
        $dt = self::toLocal($utc);

        return $dt === null ? '' : $dt->format(self::DB_DATE);
    }

    /** Value for <input type="date"> from a plain DATE column. */
    public static function inputDateOnly(?string $date): string
    {
        $dt = self::parseDate($date);

        return $dt === null ? '' : $dt->format(self::DB_DATE);
    }

    /** Machine-readable value for a <time datetime="..."> attribute. */
    public static function iso(?string $utc): string
    {
        $dt = self::parseUtc($utc);

        return $dt === null ? '' : $dt->format('c');
    }

    // -------------------------------------------------------------------------
    // Relative and human-friendly
    // -------------------------------------------------------------------------

    /** "3 hours ago", "in 2 days", "just now". */
    public static function ago(?string $utc, string $empty = '—'): string
    {
        $dt = self::parseUtc($utc);

        if ($dt === null) {
            return $empty;
        }

        $seconds = self::now()->getTimestamp() - $dt->getTimestamp();
        $future  = $seconds < 0;
        $seconds = abs($seconds);

        if ($seconds < 45) {
            return $future ? 'in a moment' : 'just now';
        }

        $units = [
            ['sec', 60, 1],
            ['min', 3600, 60],
            ['hour', 86400, 3600],
            ['day', 604800, 86400],
            ['week', 2629800, 604800],
            ['month', 31557600, 2629800],
            ['year', PHP_INT_MAX, 31557600],
        ];

        foreach ($units as [$label, $ceiling, $divisor]) {
            if ($seconds < $ceiling) {
                $value = (int) round($seconds / $divisor);
                $value = max(1, $value);
                $text  = $value . ' ' . $label . ($value === 1 ? '' : 's');

                return $future ? 'in ' . $text : $text . ' ago';
            }
        }

        return self::date($utc, $empty);
    }

    /** "Sep 4, 2026 2:30 PM (3 hours ago)" — for tooltips and detail views. */
    public static function full(?string $utc, string $empty = '—'): string
    {
        if (self::parseUtc($utc) === null) {
            return $empty;
        }

        return self::datetime($utc) . ' (' . self::ago($utc) . ')';
    }

    /** Turn minutes into "2h 15m". */
    public static function humanDuration(?int $minutes, string $empty = '—'): string
    {
        if ($minutes === null) {
            return $empty;
        }

        if ($minutes <= 0) {
            return '0m';
        }

        $days  = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins  = $minutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . 'd';
        }
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        if ($mins > 0 || $parts === []) {
            $parts[] = $mins . 'm';
        }

        return implode(' ', $parts);
    }

    /** Turn decimal hours into "1h 30m". */
    public static function humanHours(?float $hours, string $empty = '—'): string
    {
        if ($hours === null) {
            return $empty;
        }

        return self::humanDuration((int) round($hours * 60), $empty);
    }

    // -------------------------------------------------------------------------
    // Arithmetic — used by the PM scheduling engine
    // -------------------------------------------------------------------------

    /**
     * Whole minutes between two UTC datetimes ($b - $a). Null if either is bad.
     */
    public static function diffMinutes(?string $a, ?string $b): ?int
    {
        $start = self::parseUtc($a);
        $end   = self::parseUtc($b);

        if ($start === null || $end === null) {
            return null;
        }

        return (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60);
    }

    /**
     * Whole days from today (display zone) until a calendar date.
     * Negative means overdue. Null if the date is unparseable.
     */
    public static function daysUntil(?string $date): ?int
    {
        $target = self::parseDate($date);

        if ($target === null) {
            return null;
        }

        $today = self::parseDate(self::today());

        if ($today === null) {
            return null;
        }

        return (int) round(($target->getTimestamp() - $today->getTimestamp()) / 86400);
    }

    /** Is this calendar date before today? */
    public static function isPast(?string $date): bool
    {
        $days = self::daysUntil($date);

        return $days !== null && $days < 0;
    }

    /** Is this calendar date today? */
    public static function isToday(?string $date): bool
    {
        return self::daysUntil($date) === 0;
    }

    /**
     * Advance a calendar date by a schedule interval.
     *
     * Supported types match maintenance_schedules.frequency_type:
     * daily, weekly, monthly, quarterly, semiannual, annual, days, weeks, months.
     * "meter" has no calendar component and returns null.
     *
     * Month arithmetic clamps to the end of the month, so 31 January plus one
     * month is 28 February rather than PHP's default 3 March.
     */
    public static function addInterval(?string $date, string $frequencyType, int $value = 1): ?string
    {
        $start = self::parseDate($date);

        if ($start === null) {
            return null;
        }

        $value = max(1, $value);

        switch ($frequencyType) {
            case 'daily':
                return self::addDays($start, 1);
            case 'weekly':
                return self::addDays($start, 7);
            case 'monthly':
                return self::addMonths($start, 1);
            case 'quarterly':
                return self::addMonths($start, 3);
            case 'semiannual':
                return self::addMonths($start, 6);
            case 'annual':
                return self::addMonths($start, 12);
            case 'days':
                return self::addDays($start, $value);
            case 'weeks':
                return self::addDays($start, $value * 7);
            case 'months':
                return self::addMonths($start, $value);
            case 'meter':
            default:
                return null;
        }
    }

    private static function addDays(DateTimeImmutable $start, int $days): string
    {
        return $start->add(new DateInterval('P' . $days . 'D'))->format(self::DB_DATE);
    }

    /**
     * Add months, clamping the day to the last valid day of the target month.
     */
    private static function addMonths(DateTimeImmutable $start, int $months): string
    {
        $day      = (int) $start->format('j');
        $firstOf  = $start->modify('first day of this month');
        $target   = $firstOf->add(new DateInterval('P' . $months . 'M'));
        $lastDay  = (int) $target->format('t');
        $safeDay  = min($day, $lastDay);

        return $target->setDate(
            (int) $target->format('Y'),
            (int) $target->format('n'),
            $safeDay
        )->format(self::DB_DATE);
    }

    /**
     * A human label for an interval, e.g. "Every 3 months", "Every 50 hours".
     */
    public static function describeInterval(string $frequencyType, int $value = 1, ?float $meterInterval = null, string $meterUnit = 'hours'): string
    {
        switch ($frequencyType) {
            case 'daily':
                return 'Daily';
            case 'weekly':
                return 'Weekly';
            case 'monthly':
                return 'Monthly';
            case 'quarterly':
                return 'Quarterly';
            case 'semiannual':
                return 'Every 6 months';
            case 'annual':
                return 'Annually';
            case 'days':
                return $value === 1 ? 'Daily' : 'Every ' . $value . ' days';
            case 'weeks':
                return $value === 1 ? 'Weekly' : 'Every ' . $value . ' weeks';
            case 'months':
                return $value === 1 ? 'Monthly' : 'Every ' . $value . ' months';
            case 'meter':
                if ($meterInterval === null || $meterInterval <= 0) {
                    return 'By meter';
                }

                return 'Every ' . rtrim(rtrim(number_format($meterInterval, 2, '.', ','), '0'), '.') . ' ' . $meterUnit;
            default:
                return ucfirst(str_replace('_', ' ', $frequencyType));
        }
    }

    // -------------------------------------------------------------------------
    // Ranges — used by reports and dashboard charts
    // -------------------------------------------------------------------------

    /**
     * The last N calendar months, oldest first.
     *
     * @return list<array{key: string, label: string, start_utc: string, end_utc: string}>
     */
    public static function monthRange(int $months = 12, ?string $tz = null): array
    {
        $zone   = self::displayZone($tz);
        $cursor = self::now()->setTimezone($zone)->modify('first day of this month')->setTime(0, 0, 0);
        $out    = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = $cursor->sub(new DateInterval('P' . $i . 'M'));
            $end   = $start->add(new DateInterval('P1M'));

            $out[] = [
                'key'       => $start->format('Y-m'),
                'label'     => $start->format('M Y'),
                'short'     => $start->format('M'),
                'start_utc' => $start->setTimezone(self::utcZone())->format(self::DB_FORMAT),
                'end_utc'   => $end->setTimezone(self::utcZone())->format(self::DB_FORMAT),
            ];
        }

        return $out;
    }

    /**
     * "2026-03" as "March 2026". Used by reports that group by month.
     */
    public static function monthLabel(string $yearMonth): string
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', $yearMonth, $m) !== 1) {
            return $yearMonth;
        }

        $month = (int) $m[2];

        if ($month < 1 || $month > 12) {
            return $yearMonth;
        }

        $date = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $m[1] . '-' . $m[2] . '-01 00:00:00',
            self::utcZone()
        );

        return $date === false ? $yearMonth : $date->format('F Y');
    }

    /**
     * Convert a local date range (inclusive) into UTC datetime bounds suitable
     * for "performed_at >= ? AND performed_at < ?".
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function rangeToUtc(?string $fromDate, ?string $toDate, ?string $tz = null): array
    {
        $zone = self::displayZone($tz);
        $from = null;
        $to   = null;

        $fromParsed = self::parseDate($fromDate);
        if ($fromParsed !== null) {
            $local = new DateTimeImmutable($fromParsed->format(self::DB_DATE) . ' 00:00:00', $zone);
            $from  = $local->setTimezone(self::utcZone())->format(self::DB_FORMAT);
        }

        $toParsed = self::parseDate($toDate);
        if ($toParsed !== null) {
            // Exclusive upper bound: midnight at the start of the next day.
            $local = new DateTimeImmutable($toParsed->format(self::DB_DATE) . ' 00:00:00', $zone);
            $to    = $local->add(new DateInterval('P1D'))->setTimezone(self::utcZone())->format(self::DB_FORMAT);
        }

        return [$from, $to];
    }

    /**
     * Named report presets. Returns [fromDate, toDate] as local Y-m-d strings.
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function preset(string $name, ?string $tz = null): array
    {
        $today = self::now()->setTimezone(self::displayZone($tz))->setTime(0, 0, 0);
        $fmt   = self::DB_DATE;

        switch ($name) {
            case 'today':
                return [$today->format($fmt), $today->format($fmt)];
            case 'yesterday':
                $y = $today->sub(new DateInterval('P1D'));
                return [$y->format($fmt), $y->format($fmt)];
            case 'last_7':
                return [$today->sub(new DateInterval('P6D'))->format($fmt), $today->format($fmt)];
            case 'last_30':
                return [$today->sub(new DateInterval('P29D'))->format($fmt), $today->format($fmt)];
            case 'last_90':
                return [$today->sub(new DateInterval('P89D'))->format($fmt), $today->format($fmt)];
            case 'this_month':
                return [$today->modify('first day of this month')->format($fmt), $today->modify('last day of this month')->format($fmt)];
            case 'last_month':
                $prev = $today->modify('first day of last month');
                return [$prev->format($fmt), $prev->modify('last day of this month')->format($fmt)];
            case 'this_year':
                return [$today->format('Y') . '-01-01', $today->format('Y') . '-12-31'];
            case 'last_year':
                $year = (int) $today->format('Y') - 1;
                return [$year . '-01-01', $year . '-12-31'];
            case 'all':
            default:
                return [null, null];
        }
    }

    /**
     * @return array<string, string> preset key => label
     */
    public static function presets(): array
    {
        return [
            'today'      => 'Today',
            'yesterday'  => 'Yesterday',
            'last_7'     => 'Last 7 days',
            'last_30'    => 'Last 30 days',
            'last_90'    => 'Last 90 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_year'  => 'This year',
            'last_year'  => 'Last year',
            'all'        => 'All time',
        ];
    }

    // -------------------------------------------------------------------------
    // Timezone list for the settings screen
    // -------------------------------------------------------------------------

    /**
     * Timezone identifiers grouped by region, with the current UTC offset.
     *
     * @return array<string, array<string, string>> region => [identifier => label]
     */
    public static function timezones(): array
    {
        $regions = [
            'America'    => DateTimeZone::AMERICA,
            'US'         => DateTimeZone::PER_COUNTRY,
            'Europe'     => DateTimeZone::EUROPE,
            'Asia'       => DateTimeZone::ASIA,
            'Australia'  => DateTimeZone::AUSTRALIA,
            'Pacific'    => DateTimeZone::PACIFIC,
            'Africa'     => DateTimeZone::AFRICA,
            'Atlantic'   => DateTimeZone::ATLANTIC,
            'Indian'     => DateTimeZone::INDIAN,
            'UTC'        => DateTimeZone::UTC,
        ];

        $now = self::now();
        $out = [];

        foreach ($regions as $label => $mask) {
            if ($label === 'US') {
                continue;
            }

            try {
                $identifiers = DateTimeZone::listIdentifiers($mask);
            } catch (Throwable $e) {
                continue;
            }

            if (!is_array($identifiers)) {
                continue;
            }

            $group = [];

            foreach ($identifiers as $identifier) {
                $zone = self::makeZone($identifier);

                if ($zone === null) {
                    continue;
                }

                $offset  = $zone->getOffset($now);
                $sign    = $offset < 0 ? '-' : '+';
                $abs     = abs($offset);
                $display = sprintf('%s (UTC%s%02d:%02d)', str_replace('_', ' ', $identifier), $sign, intdiv($abs, 3600), intdiv($abs % 3600, 60));

                $group[$identifier] = $display;
            }

            if ($group !== []) {
                asort($group);
                $out[$label] = $group;
            }
        }

        return $out;
    }

    /**
     * Date format choices for the settings screen, rendered with today's date.
     *
     * @return array<string, string>
     */
    public static function dateFormatChoices(): array
    {
        $now     = self::localNow();
        $formats = ['M j, Y', 'j M Y', 'm/d/Y', 'd/m/Y', 'Y-m-d', 'D, M j, Y', 'l, F j, Y'];
        $out     = [];

        foreach ($formats as $format) {
            $out[$format] = $now->format($format);
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function timeFormatChoices(): array
    {
        $now     = self::localNow();
        $formats = ['g:i A', 'g:ia', 'H:i', 'H:i:s'];
        $out     = [];

        foreach ($formats as $format) {
            $out[$format] = $now->format($format);
        }

        return $out;
    }
}
