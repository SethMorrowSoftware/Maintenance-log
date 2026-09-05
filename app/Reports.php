<?php

declare(strict_types=1);

namespace App;

/**
 * The reports.
 *
 * Every report is one method returning the same shape, so reports.php can show
 * it on screen, hand it to Csv, or print it without knowing anything about the
 * report itself:
 *
 *   [
 *     'columns' => [ ['key' =>, 'label' =>, 'align' =>, 'format' =>], ... ],
 *     'rows'    => [ ['key' => value, ...], ... ],
 *     'totals'  => ['key' => value, ...]   // optional footer row
 *     'chart'   => ['type' =>, 'labels' =>, 'series' => [...]]  // optional
 *     'empty'   => 'What to say when there is nothing'
 *   ]
 *
 * 'format' names a helper the view applies: money, decimal, number, date,
 * datetime, duration, hours, percent, text. Formatting lives in the view, not
 * here, so the CSV gets the raw value and a spreadsheet can still add it up.
 */
final class Reports
{
    /**
     * The report catalogue. Order is the order of the tabs.
     *
     * @var array<string, array{label: string, blurb: string, icon: string}>
     */
    public const CATALOGUE = [
        'history' => [
            'label' => 'Maintenance history',
            'blurb' => 'Every job in the period, newest first.',
            'icon'  => 'wrench',
        ],
        'cost' => [
            'label' => 'What it cost',
            'blurb' => 'Money spent per machine — parts, labour and everything else.',
            'icon'  => 'dollar-sign',
        ],
        'monthly' => [
            'label' => 'Month by month',
            'blurb' => 'Jobs and spend per month, so you can see a trend.',
            'icon'  => 'chart-line',
        ],
        'downtime' => [
            'label' => 'Downtime',
            'blurb' => 'How long each machine was out of service, and how often.',
            'icon'  => 'clock',
        ],
        'compliance' => [
            'label' => 'Inspection record',
            'blurb' => 'Who checked what, how often, and what failed.',
            'icon'  => 'clipboard-check',
        ],
        'inventory' => [
            'label' => 'Machine list',
            'blurb' => 'Every machine with its meter, status and lifetime cost.',
            'icon'  => 'assets',
        ],
        'parts' => [
            'label' => 'Parts used',
            'blurb' => 'What came off the shelf, how much of it and what it cost.',
            'icon'  => 'package',
        ],
        'technicians' => [
            'label' => 'Who did the work',
            'blurb' => 'Jobs, hours and inspections per person.',
            'icon'  => 'users',
        ],
    ];

    private function __construct()
    {
    }

    /**
     * Run one report.
     *
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function run(string $key, array $filters): array
    {
        switch ($key) {
            case 'cost':        $result = self::cost($filters); break;
            case 'monthly':     $result = self::monthly($filters); break;
            case 'downtime':    $result = self::downtime($filters); break;
            case 'compliance':  $result = self::compliance($filters); break;
            case 'inventory':   $result = self::inventory($filters); break;
            case 'parts':       $result = self::parts($filters); break;
            case 'technicians': $result = self::technicians($filters); break;
            case 'history':
            default:            $result = self::history($filters);
        }

        return costs_visible() ? $result : self::withoutMoney($result);
    }

    /**
     * The reports somebody is allowed to open. Money reports need costs.view.
     *
     * @return array<string, array{label: string, blurb: string, icon: string}>
     */
    public static function available(): array
    {
        $catalogue = self::CATALOGUE;

        // The catalogue is a constant; the site's word for its machines is not.
        foreach ($catalogue as $key => $entry) {
            foreach (['label', 'blurb'] as $field) {
                $catalogue[$key][$field] = str_replace(
                    ['Machines', 'machines', 'Machine', 'machine'],
                    [asset_word(true, true), asset_word(true), asset_word(false, true), asset_word()],
                    (string) ($entry[$field] ?? '')
                );
            }
        }

        // Reports about a module that is switched off are not offered.
        foreach (['parts' => 'parts', 'compliance' => 'inspections', 'downtime' => 'downtime'] as $report => $module) {
            if (!feature_on($module)) {
                unset($catalogue[$report]);
            }
        }

        if (!costs_visible()) {
            unset($catalogue['cost']);

            // The one-line descriptions must not promise columns that are
            // about to be taken out. Only for reports still on offer: writing
            // to an entry that was just dropped would resurrect a half-built one.
            $plain = [
                'monthly'   => 'Jobs, hours and downtime per month, so you can see a trend.',
                'inventory' => 'Every ' . asset_word() . ' with its ' . (feature_on('meters') ? 'meter, ' : '')
                    . 'status and last service.',
                'parts'     => 'What came off the shelf, and how much of it.',
            ];

            foreach ($plain as $report => $blurb) {
                if (isset($catalogue[$report])) {
                    $catalogue[$report]['blurb'] = $blurb;
                }
            }
        }

        return $catalogue;
    }

    /**
     * The same report with every money column, total and chart taken out.
     *
     * Columns carry a 'format', so this is not a list of field names to keep
     * in step with each report — anything formatted as money goes, and a chart
     * whose only series is money goes with it.
     *
     * @param  array<string, mixed> $result
     * @return array<string, mixed>
     */
    private static function withoutMoney(array $result): array
    {
        $kept = [];

        foreach ($result['columns'] as $column) {
            if (($column['format'] ?? '') === 'money') {
                unset($result['totals'][$column['key']]);
                continue;
            }

            $kept[] = $column;
        }

        $result['columns'] = $kept;

        if (isset($result['chart']['series'])) {
            $result['chart']['series'] = array_values(array_filter(
                $result['chart']['series'],
                static fn (array $series): bool => ($series['format'] ?? '') !== 'money'
            ));

            if ($result['chart']['series'] === []) {
                unset($result['chart']);
            }
        }

        return $result;
    }

    /**
     * The date window and the asset/category narrowing shared by most reports.
     *
     * Dates arrive as local calendar days and are converted to the UTC instants
     * that bracket them, so "1 to 31 March" means what a person in the park
     * thinks it means.
     *
     * @param  array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private static function window(array $filters, string $column = 'l.performed_at'): array
    {
        $where  = [];
        $params = [];

        [$from, $to] = Dates::rangeToUtc(
            (string) ($filters['from'] ?? ''),
            (string) ($filters['to'] ?? '')
        );

        if ($from !== null) {
            $where[]  = $column . ' >= ?';
            $params[] = $from;
        }

        if ($to !== null) {
            // rangeToUtc hands back midnight at the start of the following day,
            // so the bound is exclusive: "to 31 March" includes all of the 31st.
            $where[]  = $column . ' < ?';
            $params[] = $to;
        }

        if (!empty($filters['asset_id'])) {
            $where[]  = 'a.id = ?';
            $params[] = (int) $filters['asset_id'];
        }

        if (!empty($filters['category_id'])) {
            $where[]  = 'a.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }

        if (!empty($filters['location_id'])) {
            $where[]  = 'a.location_id = ?';
            $params[] = (int) $filters['location_id'];
        }

        return [$where === [] ? '1' : implode(' AND ', $where), $params];
    }

    // -------------------------------------------------------------------------
    // Maintenance history
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private static function history(array $filters): array
    {
        [$where, $params] = self::window($filters);

        if (!empty($filters['log_type'])) {
            $where   .= ' AND l.log_type = ?';
            $params[] = (string) $filters['log_type'];
        }

        if (!empty($filters['user_id'])) {
            $where   .= ' AND l.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        $rows = db()->all(
            "SELECT l.performed_at, l.log_type, l.title, l.labor_hours, l.downtime_minutes,
                    l.parts_cost, l.labor_cost, l.other_cost, l.total_cost,
                    a.asset_tag, a.name AS asset_name, c.name AS category_name,
                    TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS technician
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE l.deleted_at IS NULL AND {$where}
             ORDER BY l.performed_at DESC
             LIMIT 5000",
            $params
        );

        // The totals come from the database, not from the rows on the page:
        // the list is capped, and a footer that only added up the newest
        // five thousand jobs would be quietly wrong.
        $sums = db()->one(
            "SELECT COUNT(*) AS n,
                    COALESCE(SUM(l.total_cost), 0)       AS total_cost,
                    COALESCE(SUM(l.labor_hours), 0)      AS labor_hours,
                    COALESCE(SUM(l.downtime_minutes), 0) AS downtime_minutes
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {users} u ON u.id = l.user_id
             WHERE l.deleted_at IS NULL AND {$where}",
            $params
        ) ?? [];

        $jobs   = (int) ($sums['n'] ?? count($rows));
        $totals = [
            'total_cost'       => (float) ($sums['total_cost'] ?? 0),
            'labor_hours'      => (float) ($sums['labor_hours'] ?? 0),
            'downtime_minutes' => (int) ($sums['downtime_minutes'] ?? 0),
        ];

        $totals['title'] = num($jobs) . ' job' . ($jobs === 1 ? '' : 's')
            . ($jobs > count($rows) ? ' (newest ' . num(count($rows)) . ' listed)' : '');

        return [
            'columns' => [
                ['key' => 'performed_at', 'label' => 'When',      'format' => 'datetime'],
                ['key' => 'asset_name',   'label' => asset_word(false, true),     'format' => 'text'],
                ['key' => 'asset_tag',    'label' => 'Tag',       'format' => 'text'],
                ['key' => 'log_type',     'label' => 'Type',      'format' => 'log_type'],
                ['key' => 'title',        'label' => 'Job',       'format' => 'text'],
                ['key' => 'technician',   'label' => 'Who',       'format' => 'text'],
                ['key' => 'labor_hours',  'label' => 'Hours',     'format' => 'hours',    'align' => 'right'],
                ['key' => 'downtime_minutes', 'label' => 'Down',  'format' => 'duration', 'align' => 'right'],
                ['key' => 'total_cost',   'label' => 'Cost',      'format' => 'money',    'align' => 'right'],
            ],
            'rows'   => $rows,
            'totals' => $totals,
            'empty'  => 'No maintenance was recorded in that period.',
        ];
    }

    // -------------------------------------------------------------------------
    // Cost per machine
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private static function cost(array $filters): array
    {
        [$where, $params] = self::window($filters);

        $rows = db()->all(
            "SELECT a.asset_tag, a.name AS asset_name, c.name AS category_name,
                    COUNT(l.id) AS jobs,
                    COALESCE(SUM(l.labor_hours), 0) AS labor_hours,
                    COALESCE(SUM(l.parts_cost), 0)  AS parts_cost,
                    COALESCE(SUM(l.labor_cost), 0)  AS labor_cost,
                    COALESCE(SUM(l.other_cost), 0)  AS other_cost,
                    COALESCE(SUM(l.total_cost), 0)  AS total_cost
             FROM {assets} a
             INNER JOIN {maintenance_logs} l ON l.asset_id = a.id AND l.deleted_at IS NULL
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             WHERE a.deleted_at IS NULL AND {$where}
             GROUP BY a.id, a.asset_tag, a.name, c.name
             ORDER BY total_cost DESC",
            $params
        );

        $totals = ['jobs' => 0, 'labor_hours' => 0.0, 'parts_cost' => 0.0,
                   'labor_cost' => 0.0, 'other_cost' => 0.0, 'total_cost' => 0.0];

        foreach ($rows as $row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (float) $row[$key];
            }
        }

        $totals['asset_name'] = 'All ' . count($rows) . ' ' . asset_word(true);

        // The ten dearest, for the chart.
        $chartRows = array_slice($rows, 0, 10);

        return [
            'columns' => [
                ['key' => 'asset_name',    'label' => asset_word(false, true),    'format' => 'text'],
                ['key' => 'asset_tag',     'label' => 'Tag',      'format' => 'text'],
                ['key' => 'category_name', 'label' => 'Category', 'format' => 'text'],
                ['key' => 'jobs',          'label' => 'Jobs',     'format' => 'number', 'align' => 'right'],
                ['key' => 'labor_hours',   'label' => 'Hours',    'format' => 'hours',  'align' => 'right'],
                ['key' => 'parts_cost',    'label' => 'Parts',    'format' => 'money',  'align' => 'right'],
                ['key' => 'labor_cost',    'label' => 'Labour',   'format' => 'money',  'align' => 'right'],
                ['key' => 'other_cost',    'label' => 'Other',    'format' => 'money',  'align' => 'right'],
                ['key' => 'total_cost',    'label' => 'Total',    'format' => 'money',  'align' => 'right'],
            ],
            'rows'   => $rows,
            'totals' => $totals,
            'chart'  => [
                'type'   => 'bar',
                'title'  => 'The ten most expensive ' . asset_word(true),
                'labels' => array_column($chartRows, 'asset_name'),
                'series' => [[
                    'label'  => 'Total cost',
                    'values' => array_map('floatval', array_column($chartRows, 'total_cost')),
                    'format' => 'money',
                ]],
            ],
            'empty' => 'Nothing was spent in that period.',
        ];
    }

    // -------------------------------------------------------------------------
    // Month by month
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private static function monthly(array $filters): array
    {
        [$where, $params] = self::window($filters);

        // DATE_FORMAT on a UTC column groups by UTC month. For a park that is
        // near enough — a job at 8pm on the 31st is a rounding error against a
        // month's spend, and the alternative is a timezone conversion MySQL 5.7
        // cannot do without the timezone tables loaded.
        $rows = db()->all(
            "SELECT DATE_FORMAT(l.performed_at, '%Y-%m') AS month,
                    COUNT(l.id) AS jobs,
                    COALESCE(SUM(l.labor_hours), 0) AS labor_hours,
                    COALESCE(SUM(l.downtime_minutes), 0) AS downtime_minutes,
                    COALESCE(SUM(l.parts_cost), 0) AS parts_cost,
                    COALESCE(SUM(l.labor_cost), 0) AS labor_cost,
                    COALESCE(SUM(l.total_cost), 0) AS total_cost
             FROM {maintenance_logs} l
             INNER JOIN {assets} a ON a.id = l.asset_id
             WHERE l.deleted_at IS NULL AND {$where}
             GROUP BY DATE_FORMAT(l.performed_at, '%Y-%m')
             ORDER BY month ASC",
            $params
        );

        $totals = ['jobs' => 0, 'labor_hours' => 0.0, 'downtime_minutes' => 0,
                   'parts_cost' => 0.0, 'labor_cost' => 0.0, 'total_cost' => 0.0];

        foreach ($rows as &$row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (float) $row[$key];
            }

            // "2026-03" reads badly in a table.
            $row['month_label'] = Dates::monthLabel((string) $row['month']);
        }

        unset($row);

        $totals['month_label'] = count($rows) . ' month' . (count($rows) === 1 ? '' : 's');

        return [
            'columns' => [
                ['key' => 'month_label',      'label' => 'Month',    'format' => 'text'],
                ['key' => 'jobs',             'label' => 'Jobs',     'format' => 'number',   'align' => 'right'],
                ['key' => 'labor_hours',      'label' => 'Hours',    'format' => 'hours',    'align' => 'right'],
                ['key' => 'downtime_minutes', 'label' => 'Downtime', 'format' => 'duration', 'align' => 'right'],
                ['key' => 'parts_cost',       'label' => 'Parts',    'format' => 'money',    'align' => 'right'],
                ['key' => 'labor_cost',       'label' => 'Labour',   'format' => 'money',    'align' => 'right'],
                ['key' => 'total_cost',       'label' => 'Total',    'format' => 'money',    'align' => 'right'],
            ],
            'rows'   => $rows,
            'totals' => $totals,
            'chart'  => [
                'type'   => 'line',
                'title'  => 'Spend by month',
                'labels' => array_column($rows, 'month_label'),
                'series' => [[
                    'label'  => 'Total cost',
                    'values' => array_map('floatval', array_column($rows, 'total_cost')),
                    'format' => 'money',
                ]],
            ],
            'empty' => 'No maintenance was recorded in that period.',
        ];
    }

    // -------------------------------------------------------------------------
    // Downtime
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private static function downtime(array $filters): array
    {
        [$where, $params] = self::window($filters);

        $rows = db()->all(
            "SELECT a.asset_tag, a.name AS asset_name, c.name AS category_name,
                    a.status,
                    COUNT(l.id) AS incidents,
                    COALESCE(SUM(l.downtime_minutes), 0) AS downtime_minutes,
                    COALESCE(MAX(l.downtime_minutes), 0) AS worst_minutes,
                    COALESCE(AVG(l.downtime_minutes), 0) AS average_minutes
             FROM {assets} a
             INNER JOIN {maintenance_logs} l
                 ON l.asset_id = a.id AND l.deleted_at IS NULL AND l.downtime_minutes > 0
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             WHERE a.deleted_at IS NULL AND {$where}
             GROUP BY a.id, a.asset_tag, a.name, c.name, a.status
             ORDER BY downtime_minutes DESC",
            $params
        );

        $totals = ['incidents' => 0, 'downtime_minutes' => 0];

        foreach ($rows as $row) {
            $totals['incidents']        += (int) $row['incidents'];
            $totals['downtime_minutes'] += (int) $row['downtime_minutes'];
        }

        $totals['asset_name'] = count($rows) . ' ' . (count($rows) === 1 ? asset_word() : asset_word(true));

        $chartRows = array_slice($rows, 0, 10);

        return [
            'columns' => [
                ['key' => 'asset_name',       'label' => asset_word(false, true),        'format' => 'text'],
                ['key' => 'asset_tag',        'label' => 'Tag',          'format' => 'text'],
                ['key' => 'status',           'label' => 'Status now',   'format' => 'asset_status'],
                ['key' => 'incidents',        'label' => 'Times down',   'format' => 'number',   'align' => 'right'],
                ['key' => 'downtime_minutes', 'label' => 'Total down',   'format' => 'duration', 'align' => 'right'],
                ['key' => 'average_minutes',  'label' => 'Average',      'format' => 'duration', 'align' => 'right'],
                ['key' => 'worst_minutes',    'label' => 'Worst',        'format' => 'duration', 'align' => 'right'],
            ],
            'rows'   => $rows,
            'totals' => $totals,
            'chart'  => [
                'type'   => 'bar',
                'title'  => 'Hours out of service',
                'labels' => array_column($chartRows, 'asset_name'),
                'series' => [[
                    'label'  => 'Hours down',
                    'values' => array_map(static function ($row): float {
                        return round((float) $row['downtime_minutes'] / 60, 1);
                    }, $chartRows),
                    'format' => 'number',
                ]],
            ],
            'empty' => 'Nothing was recorded as out of service in that period. '
                     . 'Downtime comes from the "out of service for" box on a maintenance log.',
        ];
    }

    // -------------------------------------------------------------------------
    // Inspection compliance
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private static function compliance(array $filters): array
    {
        [$where, $params] = self::window($filters, 'i.started_at');

        $rows = db()->all(
            "SELECT a.asset_tag, a.name AS asset_name,
                    COUNT(i.id) AS runs,
                    SUM(i.status = 'passed') AS passed,
                    SUM(i.status = 'failed') AS failed,
                    SUM(i.critical_failed = 1) AS critical,
                    SUM(i.status = 'in_progress') AS unfinished,
                    MAX(i.started_at) AS last_run
             FROM {assets} a
             INNER JOIN {inspections} i ON i.asset_id = a.id
             WHERE a.deleted_at IS NULL AND {$where}
             GROUP BY a.id, a.asset_tag, a.name
             ORDER BY failed DESC, runs DESC",
            $params
        );

        $totals = ['runs' => 0, 'passed' => 0, 'failed' => 0, 'critical' => 0, 'unfinished' => 0];

        foreach ($rows as &$row) {
            foreach (array_keys($totals) as $key) {
                $totals[$key] += (int) $row[$key];
            }

            $finished          = (int) $row['passed'] + (int) $row['failed'];
            $row['pass_rate']  = $finished === 0 ? null : round((int) $row['passed'] / $finished * 100, 1);
        }

        unset($row);

        $finishedTotal      = $totals['passed'] + $totals['failed'];
        $totals['pass_rate'] = $finishedTotal === 0 ? null : round($totals['passed'] / $finishedTotal * 100, 1);
        $totals['asset_name'] = count($rows) . ' ' . (count($rows) === 1 ? asset_word() : asset_word(true));

        return [
            'columns' => [
                ['key' => 'asset_name', 'label' => asset_word(false, true),      'format' => 'text'],
                ['key' => 'asset_tag',  'label' => 'Tag',        'format' => 'text'],
                ['key' => 'runs',       'label' => 'Checks',     'format' => 'number',  'align' => 'right'],
                ['key' => 'passed',     'label' => 'Passed',     'format' => 'number',  'align' => 'right'],
                ['key' => 'failed',     'label' => 'Failed',     'format' => 'number',  'align' => 'right'],
                ['key' => 'critical',   'label' => 'Safety fails', 'format' => 'number', 'align' => 'right'],
                ['key' => 'unfinished', 'label' => 'Unfinished', 'format' => 'number',  'align' => 'right'],
                ['key' => 'pass_rate',  'label' => 'Pass rate',  'format' => 'percent', 'align' => 'right'],
                ['key' => 'last_run',   'label' => 'Last check', 'format' => 'date'],
            ],
            'rows'   => $rows,
            'totals' => $totals,
            'empty'  => 'No inspections were carried out in that period.',
        ];
    }

    // -------------------------------------------------------------------------
    // Machine inventory
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private static function inventory(array $filters): array
    {
        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[]  = 'a.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }

        if (!empty($filters['location_id'])) {
            $where[]  = 'a.location_id = ?';
            $params[] = (int) $filters['location_id'];
        }

        if (!empty($filters['status'])) {
            $where[]  = 'a.status = ?';
            $params[] = (string) $filters['status'];
        }

        $whereSql = implode(' AND ', $where);

        $rows = db()->all(
            "SELECT a.asset_tag, a.name AS asset_name, c.name AS category_name,
                    loc.name AS location_name, a.status, a.criticality,
                    a.manufacturer, a.model, a.year_manufactured, a.serial_number,
                    a.meter_type, a.meter_reading, a.in_service_date, a.purchase_cost,
                    (SELECT COALESCE(SUM(l.total_cost), 0) FROM {maintenance_logs} l
                      WHERE l.asset_id = a.id AND l.deleted_at IS NULL) AS lifetime_cost,
                    (SELECT MAX(l.performed_at) FROM {maintenance_logs} l
                      WHERE l.asset_id = a.id AND l.deleted_at IS NULL) AS last_service
             FROM {assets} a
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} loc ON loc.id = a.location_id
             WHERE {$whereSql}
             ORDER BY c.sort_order ASC, c.name ASC, a.name ASC",
            $params
        );

        $totals = ['purchase_cost' => 0.0, 'lifetime_cost' => 0.0];

        foreach ($rows as $row) {
            $totals['purchase_cost'] += (float) $row['purchase_cost'];
            $totals['lifetime_cost'] += (float) $row['lifetime_cost'];
        }

        $totals['asset_name'] = count($rows) . ' ' . (count($rows) === 1 ? asset_word() : asset_word(true));

        return [
            'columns' => [
                ['key' => 'asset_name',    'label' => asset_word(false, true),        'format' => 'text'],
                ['key' => 'asset_tag',     'label' => 'Tag',          'format' => 'text'],
                ['key' => 'category_name', 'label' => 'Category',     'format' => 'text'],
                ['key' => 'location_name', 'label' => 'Where',        'format' => 'text'],
                ['key' => 'status',        'label' => 'Status',       'format' => 'asset_status'],
                ['key' => 'meter_reading', 'label' => 'Meter',        'format' => 'meter', 'align' => 'right'],
                ['key' => 'in_service_date', 'label' => 'In service since', 'format' => 'date_only'],
                ['key' => 'last_service',  'label' => 'Last serviced', 'format' => 'date'],
                ['key' => 'purchase_cost', 'label' => 'Cost new',     'format' => 'money', 'align' => 'right'],
                ['key' => 'lifetime_cost', 'label' => 'Spent since',  'format' => 'money', 'align' => 'right'],
            ],
            'rows'   => $rows,
            'totals' => $totals,
            'empty'  => 'No ' . asset_word(true) . ' match that.',
        ];
    }

    // -------------------------------------------------------------------------
    // Parts usage
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private static function parts(array $filters): array
    {
        [$where, $params] = self::window($filters);

        $rows = db()->all(
            "SELECT mp.part_name, mp.part_number,
                    COALESCE(p.category, '') AS category,
                    COALESCE(p.quantity_on_hand, 0) AS on_hand,
                    COALESCE(p.reorder_level, 0) AS reorder_level,
                    COUNT(DISTINCT mp.log_id) AS times_used,
                    COALESCE(SUM(mp.quantity), 0) AS quantity,
                    COALESCE(SUM(mp.total_cost), 0) AS total_cost
             FROM {maintenance_log_parts} mp
             INNER JOIN {maintenance_logs} l ON l.id = mp.log_id AND l.deleted_at IS NULL
             INNER JOIN {assets} a ON a.id = l.asset_id
             LEFT JOIN {parts} p ON p.id = mp.part_id
             WHERE {$where}
             GROUP BY mp.part_name, mp.part_number, p.category, p.quantity_on_hand, p.reorder_level
             ORDER BY total_cost DESC",
            $params
        );

        $totals = ['times_used' => 0, 'quantity' => 0.0, 'total_cost' => 0.0];

        foreach ($rows as $row) {
            $totals['times_used'] += (int) $row['times_used'];
            $totals['quantity']   += (float) $row['quantity'];
            $totals['total_cost'] += (float) $row['total_cost'];
        }

        $totals['part_name'] = count($rows) . ' different part' . (count($rows) === 1 ? '' : 's');

        $chartRows = array_slice($rows, 0, 10);

        return [
            'columns' => [
                ['key' => 'part_name',     'label' => 'Part',        'format' => 'text'],
                ['key' => 'part_number',   'label' => 'Number',      'format' => 'text'],
                ['key' => 'category',      'label' => 'Category',    'format' => 'text'],
                ['key' => 'times_used',    'label' => 'Jobs',        'format' => 'number',  'align' => 'right'],
                ['key' => 'quantity',      'label' => 'Used',        'format' => 'decimal', 'align' => 'right'],
                ['key' => 'total_cost',    'label' => 'Cost',        'format' => 'money',   'align' => 'right'],
                ['key' => 'on_hand',       'label' => 'On hand now',  'format' => 'decimal', 'align' => 'right'],
                ['key' => 'reorder_level', 'label' => 'Reorder at',  'format' => 'decimal', 'align' => 'right'],
            ],
            'rows'   => $rows,
            'totals' => $totals,
            'chart'  => [
                'type'   => 'bar',
                'title'  => 'The ten dearest parts',
                'labels' => array_column($chartRows, 'part_name'),
                'series' => [[
                    'label'  => 'Spent',
                    'values' => array_map('floatval', array_column($chartRows, 'total_cost')),
                    'format' => 'money',
                ]],
            ],
            'empty' => 'No parts were recorded on a job in that period.',
        ];
    }

    // -------------------------------------------------------------------------
    // Technician activity
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private static function technicians(array $filters): array
    {
        [$where, $params] = self::window($filters);

        $rows = db()->all(
            "SELECT u.id AS user_id,
                    TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS person,
                    u.username, u.role,
                    COUNT(l.id) AS jobs,
                    COALESCE(SUM(l.labor_hours), 0) AS labor_hours,
                    COALESCE(SUM(l.total_cost), 0) AS total_cost,
                    MAX(l.performed_at) AS last_job
             FROM {users} u
             INNER JOIN {maintenance_logs} l ON l.user_id = u.id AND l.deleted_at IS NULL
             INNER JOIN {assets} a ON a.id = l.asset_id
             WHERE {$where}
             GROUP BY u.id, u.first_name, u.last_name, u.username, u.role
             ORDER BY jobs DESC",
            $params
        );

        // Inspections are work too, and they are counted separately.
        [$inspWhere, $inspParams] = self::window($filters, 'i.started_at');

        $inspections = db()->pairs(
            "SELECT i.user_id, COUNT(*) FROM {inspections} i
             INNER JOIN {assets} a ON a.id = i.asset_id
             WHERE i.user_id IS NOT NULL AND {$inspWhere}
             GROUP BY i.user_id",
            $inspParams
        );

        $totals = ['jobs' => 0, 'labor_hours' => 0.0, 'total_cost' => 0.0, 'inspections' => 0];

        foreach ($rows as &$row) {
            $row['person']      = (string) $row['person'] !== '' ? $row['person'] : $row['username'];
            $row['inspections'] = (int) ($inspections[(int) $row['user_id']] ?? 0);

            $totals['jobs']        += (int) $row['jobs'];
            $totals['labor_hours'] += (float) $row['labor_hours'];
            $totals['total_cost']  += (float) $row['total_cost'];
            $totals['inspections'] += $row['inspections'];
        }

        unset($row);

        $totals['person'] = count($rows) . ' ' . (count($rows) === 1 ? 'person' : 'people');

        return [
            'columns' => [
                ['key' => 'person',      'label' => 'Who',          'format' => 'text'],
                ['key' => 'role',        'label' => 'Role',         'format' => 'role'],
                ['key' => 'jobs',        'label' => 'Jobs logged',  'format' => 'number', 'align' => 'right'],
                ['key' => 'labor_hours', 'label' => 'Hours',        'format' => 'hours',  'align' => 'right'],
                ['key' => 'inspections', 'label' => 'Inspections',  'format' => 'number', 'align' => 'right'],
                ['key' => 'total_cost',  'label' => 'Cost of work', 'format' => 'money',  'align' => 'right'],
                ['key' => 'last_job',    'label' => 'Last job',     'format' => 'date'],
            ],
            'rows'   => $rows,
            'totals' => $totals,
            'empty'  => 'Nobody logged any work in that period.',
        ];
    }
}
