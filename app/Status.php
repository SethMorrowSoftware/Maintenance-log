<?php

declare(strict_types=1);

namespace App;

/**
 * The controlled vocabularies, in one place.
 *
 * Every status, type and priority value stored in the database is a lowercase
 * snake_case string. This class maps them to human labels, badge tones and
 * icons, so a work order's status looks the same on the dashboard, in a list,
 * on a detail page and in an export.
 *
 * Tones map onto the CSS badge classes: ok, warn, danger, info, muted.
 */
final class Status
{
    /**
     * vocabulary => [value => [label, tone, icon]]
     *
     * @var array<string, array<string, array{0: string, 1: string, 2: string}>>
     */
    private const MAP = [
        'asset' => [
            'in_service'     => ['In service',     'ok',     'check-circle'],
            'maintenance'    => ['In maintenance', 'warn',   'wrench'],
            'out_of_service' => ['Out of service', 'danger', 'alert-triangle'],
            'retired'        => ['Retired',        'muted',  'archive'],
        ],
        'criticality' => [
            'low'      => ['Low',      'muted',  'chevron-down'],
            'medium'   => ['Medium',   'info',   'minus'],
            'high'     => ['High',     'warn',   'chevron-up'],
            'critical' => ['Critical', 'danger', 'alert-triangle'],
        ],
        'meter' => [
            'none'   => ['No meter', 'muted', 'minus'],
            'hours'  => ['Hours',    'info',  'clock'],
            'miles'  => ['Miles',    'info',  'gauge'],
            'cycles' => ['Cycles',   'info',  'refresh'],
            'laps'   => ['Laps',     'info',  'refresh'],
        ],
        'log_type' => [
            'preventive'   => ['Preventive',   'ok',     'calendar'],
            'corrective'   => ['Corrective',   'warn',   'tool'],
            'repair'       => ['Repair',       'danger', 'wrench'],
            'inspection'   => ['Inspection',   'info',   'clipboard-check'],
            'daily_check'  => ['Daily check',  'info',   'checklist'],
            'cleaning'     => ['Cleaning',     'muted',  'sparkle'],
            'modification' => ['Modification', 'info',   'edit'],
            'safety'       => ['Safety',       'danger', 'shield'],
            'other'        => ['Other',        'muted',  'more-vertical'],
        ],
        'workorder' => [
            'open'        => ['Open',        'info',   'circle'],
            'assigned'    => ['Assigned',    'info',   'user'],
            'in_progress' => ['In progress', 'warn',   'play'],
            'on_hold'     => ['On hold',     'muted',  'pause'],
            'completed'   => ['Completed',   'ok',     'check-circle'],
            'cancelled'   => ['Cancelled',   'muted',  'x'],
        ],
        'priority' => [
            'low'    => ['Low',    'muted',  'chevron-down'],
            'normal' => ['Normal', 'info',   'minus'],
            'high'   => ['High',   'warn',   'chevron-up'],
            'urgent' => ['Urgent', 'danger', 'alert-triangle'],
        ],
        'wo_source' => [
            'operator_report' => ['Operator report', 'info',  'user'],
            'inspection'      => ['Inspection',      'info',  'clipboard-check'],
            'preventive'      => ['Scheduled service', 'ok',  'calendar'],
            'breakdown'       => ['Breakdown',       'danger', 'alert-triangle'],
            'other'           => ['Other',           'muted', 'more-vertical'],
        ],
        'inspection' => [
            'in_progress' => ['In progress', 'warn',   'edit'],
            'passed'      => ['Passed',      'ok',     'check-circle'],
            'failed'      => ['Failed',      'danger', 'alert-triangle'],
            'completed'   => ['Completed',   'info',   'check'],
        ],
        'response' => [
            'pass' => ['Pass', 'ok',     'check'],
            'fail' => ['Fail', 'danger', 'x'],
            'na'   => ['N/A',  'muted',  'minus'],
            'yes'  => ['Yes',  'ok',     'check'],
            'no'   => ['No',   'danger', 'x'],
            ''     => ['Not answered', 'muted', 'help-circle'],
        ],
        'response_type' => [
            'pass_fail'    => ['Pass / Fail',        'info', 'check'],
            'pass_fail_na' => ['Pass / Fail / N/A',  'info', 'check'],
            'yes_no'       => ['Yes / No',           'info', 'check'],
            'text'         => ['Text note',          'info', 'file-text'],
            'number'       => ['Number',             'info', 'gauge'],
            'meter'        => ['Meter reading',      'info', 'gauge'],
        ],
        'frequency' => [
            'daily'      => ['Daily',        'info', 'calendar'],
            'weekly'     => ['Weekly',       'info', 'calendar'],
            'monthly'    => ['Monthly',      'info', 'calendar'],
            'quarterly'  => ['Quarterly',    'info', 'calendar'],
            'semiannual' => ['Every 6 months', 'info', 'calendar'],
            'annual'     => ['Annually',     'info', 'calendar'],
            'preseason'  => ['Pre-season',   'info', 'calendar'],
            'adhoc'      => ['As needed',    'muted', 'calendar'],
            'days'       => ['Every N days', 'info', 'calendar'],
            'weeks'      => ['Every N weeks', 'info', 'calendar'],
            'months'     => ['Every N months', 'info', 'calendar'],
            'meter'      => ['By meter',     'info', 'gauge'],
        ],
        'due' => [
            'overdue'  => ['Overdue',   'danger', 'alert-triangle'],
            'due'      => ['Due now',   'warn',   'clock'],
            'due_soon' => ['Due soon',  'warn',   'clock'],
            'ok'       => ['On track',  'ok',     'check-circle'],
            'inactive' => ['Paused',    'muted',  'pause'],
            'none'     => ['Not scheduled', 'muted', 'minus'],
        ],
        'part_tx' => [
            'in'     => ['Received',   'ok',     'arrow-down'],
            'out'    => ['Used',       'warn',   'arrow-up'],
            'adjust' => ['Adjustment', 'info',   'edit'],
        ],
        'stock' => [
            'ok'       => ['In stock',    'ok',     'check-circle'],
            'low'      => ['Low stock',   'warn',   'alert-triangle'],
            'out'      => ['Out of stock', 'danger', 'alert-circle'],
            'untracked'=> ['Not tracked', 'muted',  'minus'],
        ],
        'notification' => [
            'pm_due'            => ['Maintenance due',     'warn',   'calendar'],
            'pm_overdue'        => ['Maintenance overdue', 'danger', 'alert-triangle'],
            'wo_assigned'       => ['Work order assigned', 'info',   'work-order'],
            'wo_updated'        => ['Work order updated',  'info',   'work-order'],
            'inspection_failed' => ['Inspection failed',   'danger', 'alert-triangle'],
            'low_stock'         => ['Low stock',           'warn',   'package'],
            'system'            => ['System',              'muted',  'info'],
        ],
        'role' => [
            'admin'      => ['Administrator',       'danger', 'shield'],
            'manager'    => ['Maintenance Manager', 'warn',   'users'],
            'technician' => ['Technician',          'info',   'wrench'],
            'viewer'     => ['Viewer',              'muted',  'eye'],
        ],
        'entity' => [
            'asset'           => ['Machine',           'info',  'assets'],
            'maintenance_log' => ['Maintenance log', 'info',  'wrench'],
            'work_order'      => ['Work order',      'info',  'work-order'],
            'inspection'      => ['Inspection',      'info',  'clipboard-check'],
            'part'            => ['Part',            'info',  'package'],
            'user'            => ['User',            'info',  'user'],
        ],
    ];

    private function __construct()
    {
    }

    /** Human label for a value, falling back to a tidied version of it. */
    public static function label(string $value, string $vocabulary = 'asset'): string
    {
        // The kind of record called "asset" is named by the site itself.
        if ($vocabulary === 'entity' && $value === 'asset') {
            return asset_word(false, true);
        }

        $entry = self::entry($value, $vocabulary);

        return $entry === null ? Str::label($value) : $entry[0];
    }

    /** Badge tone: ok, warn, danger, info or muted. */
    public static function tone(string $value, string $vocabulary = 'asset'): string
    {
        $entry = self::entry($value, $vocabulary);

        return $entry === null ? 'muted' : $entry[1];
    }

    /** Icon name for the value. */
    public static function icon(string $value, string $vocabulary = 'asset'): string
    {
        $entry = self::entry($value, $vocabulary);

        return $entry === null ? 'circle' : $entry[2];
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null
     */
    private static function entry(string $value, string $vocabulary): ?array
    {
        return self::MAP[$vocabulary][$value] ?? null;
    }

    /**
     * Rendered badge markup, escaped and safe to print.
     */
    public static function badge(string $value, string $vocabulary = 'asset', bool $withIcon = false): string
    {
        $label = self::label($value, $vocabulary);
        $tone  = self::tone($value, $vocabulary);

        $iconHtml = $withIcon ? Icon::render(self::icon($value, $vocabulary), 'badge-icon', 14) : '';

        return '<span class="badge badge-' . $tone . '">' . $iconHtml
             . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</span>';
    }

    /**
     * All values of a vocabulary as value => label, for a <select>.
     *
     * @return array<string, string>
     */
    public static function options(string $vocabulary): array
    {
        $out = [];

        foreach (self::MAP[$vocabulary] ?? [] as $value => $entry) {
            if ($value === '') {
                continue;
            }

            $out[$value] = $entry[0];
        }

        return $out;
    }

    /**
     * The valid values of a vocabulary, for validation "in:" rules.
     *
     * @return list<string>
     */
    public static function values(string $vocabulary): array
    {
        $values = array_keys(self::MAP[$vocabulary] ?? []);

        return array_values(array_filter($values, static function ($v): bool {
            return $v !== '';
        }));
    }

    /** A comma-joined value list, ready to append to an "in:" rule. */
    public static function rule(string $vocabulary): string
    {
        return 'in:' . implode(',', self::values($vocabulary));
    }

    public static function isValid(string $value, string $vocabulary): bool
    {
        return isset(self::MAP[$vocabulary][$value]) && $value !== '';
    }

    // -------------------------------------------------------------------------
    // Derived states
    // -------------------------------------------------------------------------

    /**
     * Work out how a scheduled maintenance item is tracking.
     *
     * Returns one of: overdue, due, due_soon, ok, inactive, none.
     *
     * @param array<string, mixed> $schedule a maintenance_schedules row
     * @param float|null           $meter    the machine's current meter reading
     */
    public static function dueState(array $schedule, ?float $meter = null): string
    {
        if (!(int) ($schedule['is_active'] ?? 1)) {
            return 'inactive';
        }

        $leadDays = (int) ($schedule['lead_time_days'] ?? 7);
        $states   = [];

        // Calendar component.
        $dueDate = $schedule['next_due_date'] ?? null;

        if (is_string($dueDate) && $dueDate !== '') {
            $days = Dates::daysUntil($dueDate);

            if ($days !== null) {
                if ($days < 0) {
                    $states[] = 'overdue';
                } elseif ($days === 0) {
                    $states[] = 'due';
                } elseif ($days <= $leadDays) {
                    $states[] = 'due_soon';
                } else {
                    $states[] = 'ok';
                }
            }
        }

        // Meter component.
        $dueMeter = $schedule['next_due_meter'] ?? null;

        if ($meter !== null && $dueMeter !== null && $dueMeter !== '') {
            $target   = (float) $dueMeter;
            $interval = (float) ($schedule['meter_interval'] ?? 0);
            $warnAt   = $interval > 0 ? $target - ($interval * 0.1) : $target;

            if ($meter >= $target) {
                $states[] = 'overdue';
            } elseif ($meter >= $warnAt) {
                $states[] = 'due_soon';
            } else {
                $states[] = 'ok';
            }
        }

        if ($states === []) {
            return 'none';
        }

        // The most urgent component wins.
        foreach (['overdue', 'due', 'due_soon', 'ok'] as $rank) {
            if (in_array($rank, $states, true)) {
                return $rank;
            }
        }

        return 'ok';
    }

    /**
     * Stock state for a part row: out, low, ok or untracked.
     *
     * @param array<string, mixed> $part
     */
    public static function stockState(array $part): string
    {
        $onHand = (float) ($part['quantity_on_hand'] ?? 0);
        $level  = (float) ($part['reorder_level'] ?? 0);

        if ($onHand <= 0) {
            return 'out';
        }

        if ($level <= 0) {
            return 'untracked';
        }

        return $onHand <= $level ? 'low' : 'ok';
    }

    /**
     * Which work order statuses may follow the current one.
     *
     * @return array<string, string> status => label
     */
    public static function workOrderTransitions(string $current): array
    {
        $all = self::options('workorder');

        switch ($current) {
            case 'open':
                $allowed = ['assigned', 'in_progress', 'on_hold', 'completed', 'cancelled'];
                break;
            case 'assigned':
                $allowed = ['in_progress', 'on_hold', 'open', 'completed', 'cancelled'];
                break;
            case 'in_progress':
                $allowed = ['on_hold', 'completed', 'cancelled'];
                break;
            case 'on_hold':
                $allowed = ['in_progress', 'assigned', 'completed', 'cancelled'];
                break;
            case 'completed':
                $allowed = ['in_progress'];
                break;
            case 'cancelled':
                $allowed = ['open'];
                break;
            default:
                $allowed = array_keys($all);
                break;
        }

        $out = [];

        foreach ($allowed as $status) {
            if (isset($all[$status])) {
                $out[$status] = $all[$status];
            }
        }

        return $out;
    }

    /** Is this a work order status that means "finished"? */
    public static function isClosedWorkOrder(string $status): bool
    {
        return in_array($status, ['completed', 'cancelled'], true);
    }

    /** Is this a machine status that means "not earning money"? */
    public static function isDownStatus(string $status): bool
    {
        return in_array($status, ['out_of_service', 'maintenance'], true);
    }

    /**
     * Meter unit label for a machine, e.g. "hours" or "cycles".
     *
     * @param array<string, mixed> $asset
     */
    public static function meterUnit(array $asset): string
    {
        $type = (string) ($asset['meter_type'] ?? 'none');

        return $type === 'none' ? '' : $type;
    }
}
