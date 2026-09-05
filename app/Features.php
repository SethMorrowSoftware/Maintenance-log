<?php

declare(strict_types=1);

namespace App;

/**
 * Which parts of RideLog this site uses.
 *
 * A go-kart track wants all of it. A place tracking six appliances wants a
 * log book and nothing else. Every module is a switch under Settings →
 * Features: off means it disappears from the menu, its pages say who can
 * turn it back on, the forms lose its fields, and the nightly job and Slack
 * leave it alone. Nothing is deleted — switching it back on brings the
 * records straight back.
 *
 * The switch is enforced in one place: Acl::can() answers "no" for any
 * permission belonging to a module that is off, so every button that hides
 * behind a permission and every page that requires one follows for free.
 * The few things that hide behind no permission check feature_on() directly.
 */
final class Features
{
    /**
     * key => [label, what it covers, icon]
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    public const ALL = [
        'work_orders'   => ['Work orders',              'Reporting problems, assigning them, closing them, and the alerts about them.', 'work-order'],
        'schedules'     => ['Scheduled service',        'Recurring service by date or by meter, the due list, and the reminders.', 'calendar'],
        'inspections'   => ['Checklists and inspections', 'Daily checks, checklist templates, and the "still to check today" list.', 'clipboard-check'],
        'parts'         => ['Parts',                    'Stock on the shelf, parts used on jobs, and low-stock warnings.', 'package'],
        'meters'        => ['Meters',                   'Hour meters, lap counters, meter readings and meter-based service.', 'gauge'],
        'downtime'      => ['Downtime',                 'Time out of service on jobs and problems, and the downtime report.', 'clock'],
        'costs'         => ['Money',                    'Prices, costs and spend, for the people allowed to see them. Off hides money from everybody.', 'dollar-sign'],
        'photos'        => ['Photos and files',         'Attachments on machines, jobs, problems and inspections.', 'camera'],
        'labels'        => ['QR labels',                'Printable labels that open a machine on a phone.', 'qr-code'],
        'reports'       => ['Reports',                  'The reports page and CSV exports.', 'chart-bar'],
        'notifications' => ['In-app notifications',     'The bell in the header and the notifications page. Email and Slack have their own switches.', 'bell'],
        'audit'         => ['Audit log',                'Who changed what, when.', 'history'],
        'drafts'        => ['Form drafts',              'Keeping a draft of the log and report forms on the device as they are typed.', 'save'],
    ];

    /** Permission prefixes that belong to a module. */
    private const PERMISSION_MODULES = [
        'workorders'  => 'work_orders',
        'schedules'   => 'schedules',
        'checklists'  => 'inspections',
        'inspections' => 'inspections',
        'parts'       => 'parts',
        'reports'     => 'reports',
        'audit'       => 'audit',
        'costs'       => 'costs',
    ];

    private function __construct()
    {
    }

    /** Is the module switched on? Anything not in the list is always on. */
    public static function on(string $key): bool
    {
        if (!isset(self::ALL[$key])) {
            return true;
        }

        return Settings::bool('feature_' . $key, true);
    }

    /** The module a permission belongs to, or null if it is always available. */
    public static function forPermission(string $permission): ?string
    {
        if ($permission === 'assets.meter') {
            return 'meters';
        }

        $prefix = (string) strstr($permission, '.', true);

        return self::PERMISSION_MODULES[$prefix] ?? null;
    }

    /** Stop the page with a plain explanation if the module is off. */
    public static function require(string $key): void
    {
        if (self::on($key)) {
            return;
        }

        Response::abortPage(404, self::offMessage($key));
    }

    public static function label(string $key): string
    {
        return str_replace('machine', asset_word(), self::ALL[$key][0] ?? Str::label($key));
    }

    public static function offMessage(string $key): string
    {
        return 'The "' . self::label($key) . '" feature is switched off on this site. '
            . 'An administrator can turn it on under Settings → Features.';
    }
}
