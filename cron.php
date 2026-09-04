<?php

declare(strict_types=1);

/**
 * The nightly job.
 *
 * Set up once in cPanel and forgotten about. It works out what maintenance has
 * fallen due, warns about parts running out, and tidies away records nobody
 * needs any more.
 *
 * Two ways in:
 *
 *   From cron:  curl -s "https://example.com/cron.php?token=..."
 *   From SSH:   php /home/user/public_html/cron.php
 *
 * Over the web it needs the token from Settings > Security. Nothing here reads
 * any user input beyond that token, and it never prints anything a search
 * engine would want, but a job that can be triggered by anybody who guesses the
 * URL is still a job that can be used to hammer a shared server.
 *
 * Safe to run twice: everything it does is either idempotent or deduplicated.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Audit;
use App\Dates;
use App\Notifier;
use App\Request;
use App\Scheduler;
use App\Settings;
use App\Uploader;

$viaCli = PHP_SAPI === 'cli';

if (!$viaCli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');

    $expected = Settings::cronToken();
    $given    = Request::string('token');

    // A signed-in administrator can also run it by hand from the settings page.
    $byHand = Auth::check() && can('settings.manage');

    if (!$byHand && ($expected === '' || !hash_equals($expected, $given))) {
        http_response_code(403);
        echo "Forbidden.\n";
        exit;
    }
}

// This can take a while on a big site, and a cron run has nobody watching it.
@set_time_limit(300);
@ignore_user_abort(true);

$started = microtime(true);
$lines   = [];

/**
 * Run one step, and never let a failure in one stop the rest.
 */
$step = static function (string $label, callable $work) use (&$lines): void {
    try {
        $result  = $work();
        $lines[] = sprintf('  %-28s %s', $label, $result);
    } catch (Throwable $e) {
        log_error('Cron step "' . $label . '" failed: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);

        $lines[] = sprintf('  %-28s FAILED (recorded in the error log)', $label);
    }
};

$lines[] = 'RideLog nightly job — ' . Dates::datetime(Dates::nowUtc());
$lines[] = '';

// -----------------------------------------------------------------------------
// Maintenance that has fallen due
// -----------------------------------------------------------------------------

$step('Recompute due dates', static function (): string {
    $count = Scheduler::recomputeAll();

    return $count . ' schedule' . ($count === 1 ? '' : 's') . ' checked';
});

$step('Due and overdue notices', static function (): string {
    $result = Scheduler::raiseDueNotifications();

    return ($result['due'] ?? 0) . ' due, ' . ($result['overdue'] ?? 0) . ' overdue, '
        . ($result['notified'] ?? 0) . ' notified';
});

// -----------------------------------------------------------------------------
// Parts running out
// -----------------------------------------------------------------------------

$step('Low stock warnings', static function (): string {
    if (!Settings::bool('low_stock_alerts', true)) {
        return 'switched off in Settings';
    }

    $parts = db()->all(
        'SELECT * FROM {parts}
         WHERE deleted_at IS NULL AND is_active = 1
           AND reorder_level > 0 AND quantity_on_hand <= reorder_level'
    );

    $warned = 0;

    foreach ($parts as $part) {
        // Notifier deduplicates against the same part, so a part that has been
        // low for a fortnight does not produce a fortnight of identical notices.
        try {
            Notifier::lowStock($part);
            $warned++;
        } catch (Throwable $e) {
            log_error('Low stock notice failed: ' . $e->getMessage());
        }
    }

    return count($parts) . ' below the reorder point, ' . $warned . ' notified';
});

// -----------------------------------------------------------------------------
// Tidying up
// -----------------------------------------------------------------------------

$step('Expired login attempts', static function (): string {
    return Auth::pruneLoginAttempts(30) . ' removed';
});

$step('Expired remember tokens', static function (): string {
    return Auth::pruneRememberTokens() . ' removed';
});

$step('Expired password resets', static function (): string {
    return Auth::pruneResets() . ' removed';
});

$step('Read notifications', static function (): string {
    return Notifier::prune(60) . ' cleared';
});

$step('Old audit entries', static function (): string {
    $days = Settings::int('audit_retention_days', 365, 30, 3650);

    return Audit::prune($days) . ' removed (keeping ' . $days . ' days)';
});

$step('Orphaned uploads', static function (): string {
    $result = Uploader::pruneOrphans();

    return ($result['files'] ?? 0) . ' files, '
        . ($result['rows'] ?? 0) . ' records tidied';
});

// -----------------------------------------------------------------------------
// Done
// -----------------------------------------------------------------------------

Settings::set('last_cron_run', Dates::nowUtc());

$elapsed = round(microtime(true) - $started, 2);

$lines[] = '';
$lines[] = 'Finished in ' . $elapsed . 's.';

Audit::record('cron.run', 'system', null, 'Nightly job finished in ' . $elapsed . 's');

echo implode("\n", $lines) . "\n";
