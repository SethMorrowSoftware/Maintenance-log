<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use Throwable;

/**
 * Timed checks: what is expected today, what got done, and what to say about
 * the rest.
 *
 * A checklist with a due time is expected on each of its days. For an area
 * checklist that is one check; for a machine checklist it is one check per
 * in-service machine it covers. A check is done when a finished inspection of
 * that checklist, on that machine (or area), lands on that local day — done
 * late when it finished after the due time plus the grace period. A checklist
 * with no due time but a daily frequency is still expected ("any time today")
 * and still counted, it just never raises an alert.
 *
 * Alerts run from cron.php?job=checks every few minutes, and opportunistically
 * from page views in between. Every alert sent is recorded once per checklist
 * per day per kind, which is what makes running the job often harmless.
 */
final class Checks
{
    public const KINDS = ['reminder', 'missed', 'escalation'];

    /** How often the opportunistic tick may run, in seconds. */
    private const TICK_SECONDS = 300;

    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // When a checklist is due
    // -------------------------------------------------------------------------

    /** A check finished this long after its time still counts as on time. */
    public static function grace(): int
    {
        return Settings::int('checks_grace_minutes', 0, 0, 1440);
    }

    /**
     * The clock on the wall at the site. Due times belong to the place, not
     * to whoever happens to be signed in, so every day boundary and deadline
     * here uses the site's timezone rather than the viewer's.
     */
    public static function zone(): string
    {
        $site = trim((string) Settings::get('timezone', ''));

        if ($site === '') {
            $site = trim((string) Config::get('app.timezone', ''));
        }

        return $site !== '' ? $site : 'UTC';
    }

    /** Today's date at the site, as Y-m-d. */
    public static function today(): string
    {
        return Dates::today(self::zone());
    }

    /**
     * The UTC bounds of a site-local day: [start, end) or [null, null].
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function dayBounds(string $localDate): array
    {
        return Dates::rangeToUtc($localDate, $localDate, self::zone());
    }

    /**
     * Is the checklist a timed check on this local date?
     *
     * @param array<string, mixed> $checklist
     */
    public static function timedOn(array $checklist, string $localDate): bool
    {
        if (empty($checklist['due_time'])) {
            return false;
        }

        $day = Dates::parseDate($localDate);

        if ($day === null) {
            return false;
        }

        $days = (string) ($checklist['due_days'] ?? '1234567');

        return $days === '' || strpos($days, $day->format('N')) !== false;
    }

    /**
     * Is the checklist expected at all on this local date — timed that day, or
     * an untimed daily list?
     *
     * @param array<string, mixed> $checklist
     */
    public static function expectedOn(array $checklist, string $localDate): bool
    {
        if (self::timedOn($checklist, $localDate)) {
            return true;
        }

        return empty($checklist['due_time']) && (string) ($checklist['frequency'] ?? '') === 'daily';
    }

    /**
     * The UTC deadline of a checklist on a local date, or null when it has no
     * due time that day.
     *
     * @param array<string, mixed> $checklist
     */
    public static function dueAtFor(array $checklist, string $localDate): ?string
    {
        if (!self::timedOn($checklist, $localDate)) {
            return null;
        }

        return Dates::toUtc($localDate . ' ' . substr((string) $checklist['due_time'], 0, 5), self::zone());
    }

    /** "10:00" → "10:00 AM" in the site's time format. */
    public static function timeLabel(?string $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        $when = DateTimeImmutable::createFromFormat('!H:i', substr($time, 0, 5), Dates::displayZone());

        return $when === false ? substr($time, 0, 5) : $when->format(Dates::timeFormat());
    }

    /** "12345" → "Mon–Fri", "1234567" → "Every day", "6,7" → "Sat, Sun". */
    public static function daysLabel(?string $days): string
    {
        $days = (string) ($days ?? '1234567');

        if ($days === '' || $days === '1234567') {
            return 'Every day';
        }

        if ($days === '12345') {
            return 'Weekdays';
        }

        if ($days === '67') {
            return 'Weekends';
        }

        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $out   = [];

        foreach (str_split($days) as $digit) {
            if (isset($names[(int) $digit])) {
                $out[] = $names[(int) $digit];
            }
        }

        return implode(', ', $out);
    }

    /**
     * Tidy a submitted set of weekdays into the stored form: unique digits 1–7
     * in order, "1234567" when nothing usable was sent.
     *
     * @param array<mixed> $days
     */
    public static function cleanDays(array $days): string
    {
        $keep = [];

        foreach ($days as $day) {
            $n = (int) $day;

            if ($n >= 1 && $n <= 7) {
                $keep[$n] = true;
            }
        }

        ksort($keep);

        return $keep === [] ? '1234567' : implode('', array_keys($keep));
    }

    // -------------------------------------------------------------------------
    // What is expected, and what happened
    // -------------------------------------------------------------------------

    /**
     * The active checklists that can be expected on some day, with their area.
     *
     * @return list<array<string, mixed>>
     */
    private static function candidateChecklists(): array
    {
        return db()->all(
            "SELECT c.*, loc.name AS location_name
             FROM {checklists} c
             LEFT JOIN {locations} loc ON loc.id = c.location_id
             WHERE c.is_active = 1
               AND (c.due_time IS NOT NULL OR c.frequency = 'daily')
             ORDER BY c.due_time IS NULL, c.due_time ASC, c.name ASC"
        );
    }

    /**
     * The machines the user may see, with what is needed to match them to
     * checklists. Every live machine comes back, with its status: one that is
     * out of service is only expected on a day it was actually checked, so a
     * kart that failed its morning check stays on that day's board.
     *
     * @param  array<string, mixed>|null $user
     * @return list<array<string, mixed>>
     */
    private static function machines(?array $user): array
    {
        [$scopeSql, $scopeParams] = Scope::assetFilter('a', $user);

        return db()->all(
            "SELECT a.id, a.name, a.asset_tag, a.category_id, a.location_id, a.status,
                    loc.name AS location_name
             FROM {assets} a
             LEFT JOIN {locations} loc ON loc.id = a.location_id
             WHERE a.deleted_at IS NULL AND a.status <> 'retired'"
            . ($scopeSql !== null ? ' AND ' . $scopeSql : '')
            . ' ORDER BY a.sort_order ASC, a.name ASC',
            $scopeParams
        );
    }

    /**
     * Every finished or in-progress inspection whose day falls in the range,
     * keyed by "checklist:asset" (or "checklist:area:location"), best first:
     * a finished run beats an unfinished one, and the first run finished that
     * day is the one that counts — a re-check in the afternoon does not make
     * a check that was done by ten o'clock late.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function completions(string $startUtc, string $endUtc, array $checklistIds): array
    {
        if ($checklistIds === []) {
            return [];
        }

        $rows = db()->all(
            'SELECT i.id, i.checklist_id, i.asset_id, i.location_id, i.user_id, i.status,
                    i.started_at, i.completed_at, i.due_at, i.was_late, i.failed_count, i.critical_failed,
                    u.first_name, u.last_name, u.username, u.avatar_path
             FROM {inspections} i
             LEFT JOIN {users} u ON u.id = i.user_id
             WHERE ' . self::dayPredicate() . '
               AND i.checklist_id IN (' . implode(',', array_fill(0, count($checklistIds), '?')) . ')
             ORDER BY (i.status = \'in_progress\') ASC, COALESCE(i.completed_at, i.started_at) ASC',
            array_merge([$startUtc, $endUtc, $startUtc, $endUtc], $checklistIds)
        );

        $out = [];

        foreach ($rows as $row) {
            $key = self::key((int) $row['checklist_id'], $row['asset_id'] === null ? null : (int) $row['asset_id'], (int) ($row['location_id'] ?? 0));

            if (!isset($out[$key])) {
                $out[$key] = $row;
            }
        }

        return $out;
    }

    /**
     * "Falls on the day": by completed_at when finished, by started_at when
     * not. Written so the two indexes can be used. Binds start, end, start, end.
     */
    private static function dayPredicate(): string
    {
        return '((i.completed_at IS NOT NULL AND i.completed_at >= ? AND i.completed_at < ?)'
            . ' OR (i.completed_at IS NULL AND i.started_at >= ? AND i.started_at < ?))';
    }

    private static function key(int $checklistId, ?int $assetId, int $locationId): string
    {
        return $assetId === null ? $checklistId . ':area:' . $locationId : $checklistId . ':' . $assetId;
    }

    /**
     * Every check expected on a local date, and what happened to it.
     *
     * @param  array<string, mixed>|null $user  whose view (null = everything)
     * @return list<array<string, mixed>>
     */
    public static function occurrences(string $localDate, ?array $user = null): array
    {
        return self::occurrencesFrom($localDate, self::candidateChecklists(), self::machines($user), $user);
    }

    /**
     * The same, from context already loaded — the history report calls this
     * once per day without re-reading the tables.
     *
     * @param  list<array<string, mixed>>                 $checklists
     * @param  list<array<string, mixed>>                 $machines
     * @param  array<string, array<string, mixed>>|null   $completions pre-loaded, or null to load for the day
     * @return list<array<string, mixed>>
     */
    private static function occurrencesFrom(string $localDate, array $checklists, array $machines, ?array $user, ?array $completions = null): array
    {
        [$startUtc, $endUtc] = self::dayBounds($localDate);

        if ($startUtc === null || $endUtc === null) {
            return [];
        }

        $expected = [];

        foreach ($checklists as $checklist) {
            if (self::expectedOn($checklist, $localDate)) {
                $expected[] = $checklist;
            }
        }

        if ($expected === []) {
            return [];
        }

        if ($completions === null) {
            $completions = self::completions($startUtc, $endUtc, array_map(static fn (array $c): int => (int) $c['id'], $expected));
        }

        $nowUtc  = Dates::nowUtc();
        $isPast  = $localDate < self::today();
        $grace   = self::grace();
        $rows    = [];
        $byAsset = [];

        foreach ($expected as $checklist) {
            $dueAt = self::dueAtFor($checklist, $localDate);
            $timed = $dueAt !== null;

            if ((string) $checklist['applies_to'] === 'location') {
                if (!Scope::allowsChecklist($checklist, null, $user)) {
                    continue;
                }

                $rows[] = self::occurrence($checklist, null, $dueAt, $completions, $nowUtc, $isPast, $grace);
                continue;
            }

            foreach ($machines as $machine) {
                $matches = (string) $checklist['applies_to'] === 'all'
                    || ((string) $checklist['applies_to'] === 'category' && (int) $checklist['category_id'] === (int) $machine['category_id'])
                    || ((string) $checklist['applies_to'] === 'asset' && (int) $checklist['asset_id'] === (int) $machine['id']);

                if (!$matches || !Scope::allowsChecklist($checklist, $machine, $user)) {
                    continue;
                }

                // A machine that is down is not expected to be checked — unless
                // it was, which is usually the check that found the problem.
                if ((string) $machine['status'] !== 'in_service'
                    && !isset($completions[self::key((int) $checklist['id'], (int) $machine['id'], 0)])) {
                    continue;
                }

                $row = self::occurrence($checklist, $machine, $dueAt, $completions, $nowUtc, $isPast, $grace);

                if ($timed) {
                    $rows[] = $row;
                    continue;
                }

                // Untimed daily lists keep the old rule: one per machine, the
                // most specific wins, so nobody is told to check a kart twice.
                $rank    = ['asset' => 3, 'category' => 2, 'all' => 1];
                $score   = $rank[(string) $checklist['applies_to']] ?? 0;
                $assetId = (int) $machine['id'];

                if (!isset($byAsset[$assetId]) || $score > $byAsset[$assetId]['_score']) {
                    $row['_score']      = $score;
                    $byAsset[$assetId] = $row;
                }
            }
        }

        foreach ($byAsset as $row) {
            unset($row['_score']);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * One expected check with its outcome.
     *
     * @param array<string, mixed>                $checklist
     * @param array<string, mixed>|null           $machine
     * @param array<string, array<string, mixed>> $completions
     * @return array<string, mixed>
     */
    private static function occurrence(array $checklist, ?array $machine, ?string $dueAt, array $completions, string $nowUtc, bool $isPast, int $grace): array
    {
        $key        = self::key((int) $checklist['id'], $machine === null ? null : (int) $machine['id'], (int) ($checklist['location_id'] ?? 0));
        $inspection = $completions[$key] ?? null;
        $limit      = $dueAt === null ? null : self::plusMinutes($dueAt, $grace);

        if ($inspection !== null && (string) $inspection['status'] !== 'in_progress') {
            $finished = (string) ($inspection['completed_at'] ?? $inspection['started_at']);
            $status   = ($limit !== null && $finished > $limit) ? 'late' : 'done';
        } elseif ($inspection !== null) {
            $status = 'in_progress';
        } elseif ($isPast) {
            $status = 'missed';
        } elseif ($limit === null) {
            $status = 'anytime';
        } else {
            $status = $nowUtc > $limit ? 'overdue' : 'due';
        }

        return [
            'key'         => $key,
            'checklist'   => $checklist,
            'asset'       => $machine,
            'due_at'      => $dueAt,
            'status'      => $status,
            'inspection'  => $inspection,
        ];
    }

    private static function plusMinutes(string $utc, int $minutes): string
    {
        $when = Dates::parseUtc($utc);

        if ($when === null) {
            return $utc;
        }

        return $when->modify(($minutes >= 0 ? '+' : '') . $minutes . ' minutes')->format(Dates::DB_FORMAT);
    }

    // -------------------------------------------------------------------------
    // The board
    // -------------------------------------------------------------------------

    /**
     * The day's checks grouped by checklist, in the order they fall due.
     *
     * @param  array<string, mixed>|null $user
     * @return list<array<string, mixed>>
     */
    public static function board(string $localDate, ?array $user = null): array
    {
        $groups = [];

        foreach (self::occurrences($localDate, $user) as $row) {
            $id = (int) $row['checklist']['id'];

            if (!isset($groups[$id])) {
                $groups[$id] = [
                    'checklist'   => $row['checklist'],
                    'due_at'      => $row['due_at'],
                    'rows'        => [],
                    'total'       => 0,
                    'done'        => 0,
                    'late'        => 0,
                    'in_progress' => 0,
                    'missing'     => 0,
                    'alerts'      => [],
                ];
            }

            $groups[$id]['rows'][] = $row;
            $groups[$id]['total']++;

            switch ((string) $row['status']) {
                case 'done':
                    $groups[$id]['done']++;
                    break;
                case 'late':
                    $groups[$id]['done']++;
                    $groups[$id]['late']++;
                    break;
                case 'in_progress':
                    $groups[$id]['in_progress']++;
                    $groups[$id]['missing']++;
                    break;
                default:
                    $groups[$id]['missing']++;
            }
        }

        if ($groups === []) {
            return [];
        }

        // What the checks job has already said about each list today.
        try {
            $alerts = db()->all(
                'SELECT * FROM {checklist_alerts} WHERE due_date = ? AND checklist_id IN ('
                . implode(',', array_fill(0, count($groups), '?')) . ') ORDER BY sent_at ASC',
                array_merge([$localDate], array_keys($groups))
            );
        } catch (Throwable $e) {
            $alerts = [];
        }

        foreach ($alerts as $alert) {
            $groups[(int) $alert['checklist_id']]['alerts'][] = $alert;
        }

        foreach ($groups as &$group) {
            $group['status'] = self::groupStatus($group);
        }

        unset($group);

        // Timed lists first, by time; then the any-time lists.
        $groups = array_values($groups);

        usort($groups, static function (array $a, array $b): int {
            $ta = (string) ($a['checklist']['due_time'] ?? '');
            $tb = (string) ($b['checklist']['due_time'] ?? '');

            if (($ta === '') !== ($tb === '')) {
                return $ta === '' ? 1 : -1;
            }

            return [$ta, (string) $a['checklist']['name']] <=> [$tb, (string) $b['checklist']['name']];
        });

        return $groups;
    }

    /**
     * One word for a whole checklist's day.
     *
     * @param array<string, mixed> $group
     */
    private static function groupStatus(array $group): string
    {
        if ($group['missing'] === 0) {
            return $group['late'] > 0 ? 'late' : 'done';
        }

        $worst = null;
        $rank  = ['missed' => 5, 'overdue' => 4, 'in_progress' => 3, 'due' => 2, 'anytime' => 1];

        foreach ($group['rows'] as $row) {
            $status = (string) $row['status'];

            if (isset($rank[$status]) && ($worst === null || $rank[$status] > $rank[$worst])) {
                $worst = $status;
            }
        }

        return $worst ?? 'due';
    }

    /**
     * Headline numbers for a board.
     *
     * @param  list<array<string, mixed>> $board
     * @return array{total: int, done: int, late: int, missing: int, overdue: int}
     */
    public static function totals(array $board): array
    {
        $out = ['total' => 0, 'done' => 0, 'late' => 0, 'missing' => 0, 'overdue' => 0];

        foreach ($board as $group) {
            $out['total']   += $group['total'];
            $out['done']    += $group['done'];
            $out['late']    += $group['late'];
            $out['missing'] += $group['missing'];

            foreach ($group['rows'] as $row) {
                if (in_array((string) $row['status'], ['overdue', 'missed'], true)) {
                    $out['overdue']++;
                }
            }
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // History
    // -------------------------------------------------------------------------

    /**
     * Completion over a range of days: per checklist, per area, per person.
     *
     * @param  array<string, mixed>|null $user
     * @return array<string, mixed>
     */
    public static function history(string $fromDate, string $toDate, ?array $user = null): array
    {
        $from = Dates::parseDate($fromDate);
        $to   = Dates::parseDate($toDate);

        if ($from === null || $to === null || $from > $to) {
            return ['days' => 0, 'checklists' => [], 'areas' => [], 'people' => [], 'totals' => self::emptyTotals()];
        }

        // Three months is plenty for a screen; longer ranges are trimmed.
        if ((int) $from->diff($to)->days > 92) {
            $from = $to->modify('-92 days');
        }

        $checklists = self::candidateChecklists();
        $machines   = self::machines($user);

        [$startUtc] = self::dayBounds($from->format(Dates::DB_DATE));
        [, $endUtc] = self::dayBounds($to->format(Dates::DB_DATE));

        // Every relevant inspection in the range, then sliced per day in PHP.
        $all = $startUtc === null || $endUtc === null || $checklists === []
            ? []
            : db()->all(
                'SELECT i.id, i.checklist_id, i.asset_id, i.location_id, i.user_id, i.status,
                        i.started_at, i.completed_at, i.due_at, i.was_late, i.failed_count, i.critical_failed,
                        u.first_name, u.last_name, u.username, u.avatar_path
                 FROM {inspections} i
                 LEFT JOIN {users} u ON u.id = i.user_id
                 WHERE ' . self::dayPredicate() . '
                   AND i.checklist_id IN (' . implode(',', array_fill(0, count($checklists), '?')) . ')
                 ORDER BY (i.status = \'in_progress\') ASC, COALESCE(i.completed_at, i.started_at) ASC',
                array_merge([$startUtc, $endUtc, $startUtc, $endUtc], array_map(static fn (array $c): int => (int) $c['id'], $checklists))
            );

        // Bucket by local day.
        $byDay = [];

        foreach ($all as $row) {
            $local = Dates::toLocal((string) ($row['completed_at'] ?? $row['started_at']), self::zone());

            if ($local === null) {
                continue;
            }

            $day = $local->format(Dates::DB_DATE);
            $key = self::key((int) $row['checklist_id'], $row['asset_id'] === null ? null : (int) $row['asset_id'], (int) ($row['location_id'] ?? 0));

            if (!isset($byDay[$day][$key])) {
                $byDay[$day][$key] = $row;
            }
        }

        $perChecklist = [];
        $perArea      = [];
        $perPerson    = [];
        $totals       = self::emptyTotals();
        $today        = self::today();
        $days         = 0;

        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $date = $day->format(Dates::DB_DATE);

            if ($date > $today) {
                break;
            }

            $days++;

            foreach (self::occurrencesFrom($date, $checklists, $machines, $user, $byDay[$date] ?? []) as $row) {
                $status = (string) $row['status'];

                // Today's still-open checks are not misses yet.
                if (in_array($status, ['due', 'anytime', 'overdue', 'in_progress'], true)) {
                    if ($date === $today) {
                        $status = 'open';
                    } else {
                        $status = 'missed';
                    }
                }

                $checklistId = (int) $row['checklist']['id'];
                $areaName    = $row['asset'] !== null
                    ? (string) ($row['asset']['location_name'] ?? '')
                    : (string) ($row['checklist']['location_name'] ?? '');

                if ($areaName === '') {
                    $areaName = 'No area';
                }

                self::tallyInto($perChecklist, (string) $checklistId, (string) $row['checklist']['name'], $checklistId, $status);
                self::tallyInto($perArea, $areaName, $areaName, null, $status);
                self::tally($totals, $status);

                if (in_array($status, ['done', 'late'], true) && $row['inspection'] !== null) {
                    $personId = (int) ($row['inspection']['user_id'] ?? 0);

                    if (!isset($perPerson[$personId])) {
                        $perPerson[$personId] = [
                            'id'          => $personId,
                            'first_name'  => (string) ($row['inspection']['first_name'] ?? ''),
                            'last_name'   => (string) ($row['inspection']['last_name'] ?? ''),
                            'username'    => (string) ($row['inspection']['username'] ?? ''),
                            'avatar_path' => $row['inspection']['avatar_path'] ?? null,
                            'done'        => 0,
                            'late'        => 0,
                            'failed'      => 0,
                        ];
                    }

                    $perPerson[$personId]['done']++;

                    if ($status === 'late') {
                        $perPerson[$personId]['late']++;
                    }

                    if ((int) ($row['inspection']['failed_count'] ?? 0) > 0) {
                        $perPerson[$personId]['failed']++;
                    }
                }
            }
        }

        self::rates($totals);

        uasort($perPerson, static fn (array $a, array $b): int => $b['done'] <=> $a['done']);

        return [
            'days'       => $days,
            'from'       => $from->format(Dates::DB_DATE),
            'to'         => min($to->format(Dates::DB_DATE), $today),
            'checklists' => self::finishBuckets($perChecklist),
            'areas'      => self::finishBuckets($perArea),
            'people'     => array_values($perPerson),
            'totals'     => $totals,
        ];
    }

    /**
     * Count one check into a named bucket, creating the bucket on first use.
     *
     * @param array<string, array<string, mixed>> $buckets
     */
    private static function tallyInto(array &$buckets, string $key, string $name, ?int $id, string $status): void
    {
        if (!isset($buckets[$key])) {
            $buckets[$key] = self::emptyTotals() + ['name' => $name, 'id' => $id];
        }

        self::tally($buckets[$key], $status);
    }

    /**
     * Rates on every bucket, most missed first.
     *
     * @param  array<string, array<string, mixed>> $buckets
     * @return list<array<string, mixed>>
     */
    private static function finishBuckets(array $buckets): array
    {
        foreach ($buckets as &$entry) {
            self::rates($entry);
        }

        unset($entry);

        uasort($buckets, static fn (array $a, array $b): int => [$b['missed'], $b['late'], $a['name']] <=> [$a['missed'], $a['late'], $b['name']]);

        return array_values($buckets);
    }

    /**
     * @return array{expected: int, done: int, on_time: int, late: int, missed: int, open: int}
     */
    private static function emptyTotals(): array
    {
        return ['expected' => 0, 'done' => 0, 'on_time' => 0, 'late' => 0, 'missed' => 0, 'open' => 0];
    }

    /**
     * @param array<string, mixed> $bucket
     */
    private static function tally(array &$bucket, string $status): void
    {
        $bucket['expected']++;

        switch ($status) {
            case 'done':
                $bucket['done']++;
                $bucket['on_time']++;
                break;
            case 'late':
                $bucket['done']++;
                $bucket['late']++;
                break;
            case 'open':
                $bucket['open']++;
                break;
            default:
                $bucket['missed']++;
        }
    }

    /**
     * @param array<string, mixed> $bucket
     */
    private static function rates(array &$bucket): void
    {
        $closed = $bucket['expected'] - $bucket['open'];

        $bucket['done_rate']    = $closed > 0 ? round($bucket['done'] / $closed * 100, 1) : null;
        $bucket['on_time_rate'] = $closed > 0 ? round($bucket['on_time'] / $closed * 100, 1) : null;
    }

    // -------------------------------------------------------------------------
    // Alerts
    // -------------------------------------------------------------------------

    /**
     * Send whatever is due to be said about today's timed checks. Returns a
     * line for the cron output.
     */
    public static function runAlerts(): string
    {
        if (!Features::on('inspections')) {
            return 'checklists are switched off';
        }

        $today   = self::today();
        $nowUtc  = Dates::nowUtc();
        $grace   = self::grace();
        // The whole site's checks, whoever's page view happened to run this.
        $board   = self::board($today, Scope::everyone());
        $counts  = ['reminder' => 0, 'missed' => 0, 'escalation' => 0];
        $timed   = 0;

        $slackOn = Slack::enabled() && Settings::bool('slack_on_unfinished', true);
        $bellOn  = Settings::bool('checks_notify_managers', true);

        // Whoever ran this — cron, the nightly job or a page view — the health
        // page can now say when today's checks were last looked at.
        try {
            Settings::set('last_checks_run', $nowUtc);
        } catch (Throwable $e) {
            // Not worth stopping for.
        }

        foreach ($board as $group) {
            $checklist = $group['checklist'];
            $dueAt     = $group['due_at'];

            if ($dueAt === null) {
                continue;
            }

            $timed++;

            if ($group['missing'] === 0) {
                continue;
            }

            $sent = [];

            foreach ($group['alerts'] as $alert) {
                $sent[(string) $alert['kind']] = true;
            }

            $limit    = self::plusMinutes($dueAt, $grace);
            $missing  = array_values(array_filter($group['rows'], static fn (array $r): bool => !in_array((string) $r['status'], ['done', 'late'], true)));
            $wantsMsg = (int) ($checklist['alert_missed'] ?? 1) === 1;

            // Reminder, before the deadline.
            $remind = (int) ($checklist['remind_minutes'] ?? 0);

            if ($remind > 0 && !isset($sent['reminder']) && $nowUtc < $dueAt
                && $nowUtc >= self::plusMinutes($dueAt, -$remind)) {
                if ($wantsMsg && $slackOn) {
                    $result = Slack::checkAlert('reminder', $group, $missing);
                    self::record((int) $checklist['id'], $today, 'reminder', count($missing), $result);
                    $counts['reminder']++;
                }
            }

            // Not finished on time. Recorded only when somebody was actually
            // told, so the board never claims an alert that never went out.
            if (!isset($sent['missed']) && $nowUtc >= $limit) {
                $result = null;

                if ($wantsMsg && $slackOn) {
                    $result = Slack::checkAlert('missed', $group, $missing);
                }

                if ($bellOn) {
                    self::tellManagers($group, $missing, $today);
                    $result = $result ?? ['ok' => true, 'error' => '', 'channel' => ''];
                    $result['note'] = 'app';
                }

                $counts['missed']++;

                if ($result !== null) {
                    self::record((int) $checklist['id'], $today, 'missed', count($missing), $result);
                    $sent['missed'] = true;
                }
            }

            // Still not finished, a while later: say it again, louder.
            $escalate = (int) ($checklist['escalate_minutes'] ?? 0);

            if ($escalate > 0 && isset($sent['missed']) && !isset($sent['escalation'])
                && $nowUtc >= self::plusMinutes($limit, $escalate)) {
                if ($wantsMsg && $slackOn) {
                    $result = Slack::checkAlert('escalation', $group, $missing);
                    self::record((int) $checklist['id'], $today, 'escalation', count($missing), $result);
                    $counts['escalation']++;
                }
            }
        }

        return $timed . ' timed checklist' . ($timed === 1 ? '' : 's') . ' today, '
            . $counts['reminder'] . ' reminded, ' . $counts['missed'] . ' not finished on time, '
            . $counts['escalation'] . ' escalated';
    }

    /**
     * detail holds the Slack error when the post failed, otherwise "app" when
     * the people who manage checklists were told in the app.
     *
     * @param array{ok: bool, error: string, channel?: string, note?: string} $result
     */
    private static function record(int $checklistId, string $date, string $kind, int $missing, array $result): void
    {
        try {
            db()->run(
                'INSERT IGNORE INTO {checklist_alerts} (checklist_id, due_date, kind, missing_count, channel, ok, detail, sent_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $checklistId, $date, $kind, $missing,
                    mb_substr((string) ($result['channel'] ?? ''), 0, 80, 'UTF-8'),
                    $result['ok'] ? 1 : 0,
                    mb_substr($result['error'] !== '' ? $result['error'] : (string) ($result['note'] ?? ''), 0, 255, 'UTF-8'),
                    Dates::nowUtc(),
                ]
            );
        } catch (Throwable $e) {
            log_error('Could not record a check alert: ' . $e->getMessage());
        }
    }

    /**
     * The in-app notice to whoever looks after checklists.
     *
     * @param array<string, mixed>       $group
     * @param list<array<string, mixed>> $missing
     */
    private static function tellManagers(array $group, array $missing, string $date): void
    {
        $checklist = $group['checklist'];
        $names     = [];

        foreach (array_slice($missing, 0, 4) as $row) {
            $names[] = $row['asset'] !== null ? (string) $row['asset']['name'] : (string) ($checklist['location_name'] ?? 'the area');
        }

        $more = count($missing) - count($names);

        Notifier::pushToRole(
            'checklists.manage',
            'checklist_missed',
            'Not finished on time: ' . (string) $checklist['name'],
            'Due by ' . self::timeLabel((string) $checklist['due_time']) . '. '
            . count($missing) . ' of ' . $group['total'] . ' still to do'
            . ($names !== [] ? ': ' . implode(', ', $names) . ($more > 0 ? ' and ' . $more . ' more' : '') : '') . '.',
            'checks.php?date=' . $date,
            'checklist_day',
            // One notice per checklist per day: the entity id folds the date in.
            (int) $checklist['id'] * 100000 + (int) date('ymd', strtotime($date . ' UTC')) % 100000,
            true
        );
    }

    /**
     * Run the alerts from an ordinary page view, at most every few minutes, so
     * a site without a frequent cron still says something close to on time.
     */
    public static function tick(): void
    {
        $marker = STORAGE_PATH . '/cache/checks-tick.txt';

        try {
            $last = is_file($marker) ? (int) @file_get_contents($marker) : 0;

            if ((time() - $last) < self::TICK_SECONDS) {
                return;
            }

            if (!is_dir(dirname($marker))) {
                @mkdir(dirname($marker), 0775, true);
            }

            // Claim the slot first so two visitors do not both run it.
            if (@file_put_contents($marker, (string) time(), LOCK_EX) === false) {
                return;
            }

            self::runAlerts();
        } catch (Throwable $e) {
            log_error('Checks tick failed: ' . $e->getMessage());
        }
    }
}
