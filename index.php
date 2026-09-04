<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Models\Dashboard;
use App\Scheduler;
use App\Settings;
use App\View;

Auth::requireLogin();

/**
 * Opportunistic scheduled work.
 *
 * Not every cPanel account has cron, and asking a maintenance manager to
 * configure one before the software is useful is the wrong trade. So the
 * dashboard runs the same housekeeping itself, at most once an hour, tracked
 * by a marker file. A real cron job still works and is still better, because
 * it fires even on a day nobody opens the site.
 */
run_hourly_tasks();

$counts    = Dashboard::counts();
$costTrend = Dashboard::costTrend(30);

View::render('dashboard', [
    'title'       => 'Dashboard',
    'subtitle'    => greeting() . ', ' . first_name() . '.',
    'activeNav'   => 'index.php',
    'counts'      => $counts,
    'costTrend'   => $costTrend,
    'downtime'    => Dashboard::downtimeMinutes(30),
    'dueList'     => can('schedules.view') ? Dashboard::dueMaintenance(8) : [],
    'workOrders'  => can('workorders.view') ? Dashboard::openWorkOrders(6) : [],
    'myWork'      => Dashboard::myWork(5),
    'assetsDown'  => can('assets.view') ? Dashboard::assetsDown(6) : [],
    'inspections' => can('inspections.view') ? Dashboard::inspectionsDueToday(8) : [],
    'recentLogs'  => can('logs.view') ? Dashboard::recentLogs(8) : [],
    'lowStock'    => can('parts.view') ? Dashboard::lowStock(5) : [],
    'followUps'   => can('logs.view') ? Dashboard::followUps(4) : [],
    'statusChart' => can('assets.view') ? Dashboard::statusBreakdown() : [],
    'logsChart'   => can('logs.view') ? Dashboard::logsByMonth(12) : ['labels' => [], 'series' => []],
    'costChart'   => can('reports.view') ? Dashboard::costByMonth(12) : ['labels' => [], 'series' => []],
]);

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

function first_name(): string
{
    $me   = user();
    $name = trim((string) ($me['first_name'] ?? ''));

    return $name !== '' ? $name : (string) ($me['username'] ?? 'there');
}

function greeting(): string
{
    $hour = (int) App\Dates::localNow()->format('G');

    if ($hour < 12) {
        return 'Good morning';
    }

    if ($hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

/**
 * Run the scheduled maintenance tasks at most once an hour.
 *
 * Wrapped in its own try/catch: a failure in housekeeping must never stop
 * somebody seeing the dashboard.
 */
function run_hourly_tasks(): void
{
    $marker = STORAGE_PATH . '/cache/last-run.txt';

    try {
        $last = is_file($marker) ? (int) @file_get_contents($marker) : 0;

        if ((time() - $last) < 3600) {
            return;
        }

        // Claim the slot before doing the work, so two simultaneous visitors
        // do not both run it.
        if (!is_dir(dirname($marker))) {
            @mkdir(dirname($marker), 0775, true);
        }

        if (@file_put_contents($marker, (string) time(), LOCK_EX) === false) {
            return;
        }

        Scheduler::raiseDueNotifications();
        Auth::pruneRememberTokens();
        Auth::pruneResets();

        Settings::set('last_cron_run', App\Dates::nowUtc());
    } catch (Throwable $e) {
        log_error('Opportunistic scheduled task failed: ' . $e->getMessage());
    }
}
