<?php

declare(strict_types=1);

/**
 * Today's checks.
 *
 * Every check expected today, who did it and when, and what is still to do —
 * the board a manager glances at before opening and the list a member of
 * staff works from. History shows how reliably each list gets done.
 *
 * Somebody limited to an area (App\Scope) sees only its checks.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Checks;
use App\Csv;
use App\Dates;
use App\Request;
use App\Scope;
use App\View;

Auth::requireLogin();
Acl::requirePermission('inspections.view');

$tab   = Request::enum('tab', ['today', 'history'], 'today');
$today = Checks::today();
$user  = Auth::user();

$actions = '';

if (can('inspections.perform')) {
    $actions .= '<a class="btn btn-primary" href="' . e(url('inspection-run.php')) . '">'
        . icon('clipboard-check', '', 17) . ' Run a check</a>';
}

// -----------------------------------------------------------------------------
// History
// -----------------------------------------------------------------------------

if ($tab === 'history') {
    $range = Request::enum('range', ['7', '14', '30', '90'], '30');
    $from  = Dates::localNow(Checks::zone())->modify('-' . ((int) $range - 1) . ' days')->format(Dates::DB_DATE);

    $history = Checks::history($from, $today, $user);

    if (Request::string('export') === 'csv') {
        Acl::requirePermission('reports.export');

        audit('export', 'checklist', null, 'Exported check completion for the last ' . $range . ' days');

        Csv::stream(
            Csv::filename('checks-' . $range . '-days'),
            ['Checklist', 'Expected', 'Done', 'On time', 'Late', 'Missed', 'Still open', 'Done %', 'On time %'],
            $history['checklists'],
            static function (array $row): array {
                return [
                    $row['name'], $row['expected'], $row['done'], $row['on_time'], $row['late'],
                    $row['missed'], $row['open'], $row['done_rate'], $row['on_time_rate'],
                ];
            }
        );
    }

    if (can('reports.export')) {
        $actions .= '<a class="btn btn-secondary" href="'
            . e(url('checks.php', ['tab' => 'history', 'range' => $range, 'export' => 'csv'])) . '">'
            . icon('download', '', 17) . ' Export</a>';
    }

    View::render('checks/index', [
        'title'       => 'Checks',
        'subtitle'    => 'How reliably each list gets done',
        'activeNav'   => 'checks.php',
        'pageActions' => $actions,
        'tab'         => 'history',
        'range'       => $range,
        'history'     => $history,
        'limited'     => Scope::limited(),
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// One day
// -----------------------------------------------------------------------------

// The day being looked at: today unless another is asked for, never the future.
$date = Dates::toDate(Request::string('date')) ?? $today;

if ($date > $today) {
    $date = $today;
}

$board  = Checks::board($date, $user);
$totals = Checks::totals($board);

// Somebody limited to an area is told which one they are looking at.
$subtitle = 'Every check expected, who did it, and what is still to do';

if (Scope::limited()) {
    $areaNames = Scope::areaNames((int) $user['id']);
    $subtitle  = $areaNames !== [] ? 'For ' . implode(', ', $areaNames) : 'For your checklists';
}

View::render('checks/index', [
    'title'       => $date === $today ? "Today's checks" : 'Checks on ' . Dates::dateOnly($date),
    'subtitle'    => $subtitle,
    'activeNav'   => 'checks.php',
    'pageActions' => $actions,
    'tab'         => 'today',
    'date'        => $date,
    'today'       => $today,
    'board'       => $board,
    'totals'      => $totals,
    'limited'     => Scope::limited(),
]);
