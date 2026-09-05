<?php

declare(strict_types=1);

/**
 * RideLog — upgrade runner.
 *
 * After replacing the application files with a newer version, an administrator
 * opens this page. It applies any migration in install/migrations/ that has not
 * run yet, then updates the recorded schema version.
 *
 * Migrations are plain .sql files named NNN_short_description.sql, applied in
 * filename order. They use the same {table} placeholders as schema.sql. Every
 * applied file is recorded in the settings table, so re-running is safe and a
 * partially-failed upgrade can be resumed.
 *
 * Only a signed-in administrator can run this. Unlike the installer, an
 * upgrade touches live data, so it is not something a stray URL should trigger.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Acl;
use App\Audit;
use App\Auth;
use App\Csrf;
use App\Settings;
use App\SqlRunner;

Auth::requireLogin();
Acl::requirePermission('settings.manage');

const APPLIED_KEY = 'applied_migrations';

/**
 * @return list<array{file: string, name: string, applied: bool}>
 */
function discover_migrations(): array
{
    $dir = __DIR__ . '/migrations';

    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.sql');

    if ($files === false) {
        return [];
    }

    sort($files, SORT_NATURAL);

    $applied = applied_migrations();
    $out     = [];

    foreach ($files as $path) {
        $name = basename($path);

        $out[] = [
            'file'    => $path,
            'name'    => $name,
            'applied' => in_array($name, $applied, true),
        ];
    }

    return $out;
}

/**
 * @return list<string>
 */
function applied_migrations(): array
{
    $raw = (string) Settings::get(APPLIED_KEY, '');

    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? array_map('strval', $decoded) : [];
}

function record_migration(string $name): void
{
    $applied = applied_migrations();

    if (!in_array($name, $applied, true)) {
        $applied[] = $name;
        Settings::set(APPLIED_KEY, json_encode(array_values($applied)));
    }
}

/**
 * Settings that seed.sql would add but the database does not have yet.
 *
 * A release can introduce settings without needing a migration — a new
 * section of the Settings screen, say. There is still something to do then,
 * and saying "nothing to do" would leave those settings permanently missing
 * because nobody would press the button.
 *
 * @return list<string>
 */
function missing_settings(): array
{
    $sql = @file_get_contents(__DIR__ . '/seed.sql');

    if ($sql === false) {
        return [];
    }

    // Only the settings INSERT: the other seeded tables have rows that look
    // similar. The column list carries no quotes, so it cannot match.
    if (preg_match('/INSERT\s+IGNORE\s+INTO\s+\{settings\}.*?;/is', $sql, $block) !== 1) {
        return [];
    }

    if (preg_match_all("/\(\s*'([a-z0-9_]+)'\s*,/i", $block[0], $found) < 1) {
        return [];
    }

    try {
        $have = db()->column('SELECT setting_key FROM {settings}');
    } catch (Throwable $e) {
        // Cannot tell, so do not claim there is work outstanding.
        return [];
    }

    return array_values(array_diff(array_unique($found[1]), $have));
}

$migrations = discover_migrations();
$pending    = array_values(array_filter($migrations, static function (array $m): bool {
    return !$m['applied'];
}));
$missingSettings = missing_settings();

$currentVersion = Settings::schemaVersion();
$targetVersion  = RIDELOG_VERSION;
$results        = [];
$ran            = false;

if (is_post()) {
    Csrf::verify();

    $ran = true;
    $pdo = db()->pdo();
    $prefix = db()->prefix();

    // Make sure the base schema is present and current. schema.sql uses
    // CREATE TABLE IF NOT EXISTS, so this adds any table a new version
    // introduced without disturbing existing ones.
    $base = SqlRunner::executeFile($pdo, __DIR__ . '/schema.sql', $prefix, false);

    $results[] = [
        'name'   => 'schema.sql (base tables)',
        'ok'     => $base['ok'],
        'detail' => $base['ok']
            ? $base['executed'] . ' statements applied'
            : implode('; ', array_slice($base['errors'], 0, 3)),
    ];

    // Reference data uses INSERT IGNORE, so this fills in settings a new
    // version added without overwriting anything the site owner has changed.
    $seed = SqlRunner::executeFile($pdo, __DIR__ . '/seed.sql', $prefix, false);

    $results[] = [
        'name'   => 'seed.sql (new reference data)',
        'ok'     => $seed['ok'],
        'detail' => $seed['ok']
            ? 'Up to date'
            : implode('; ', array_slice($seed['errors'], 0, 3)),
    ];

    foreach ($pending as $migration) {
        $outcome = SqlRunner::executeFile($pdo, $migration['file'], $prefix, true);

        $results[] = [
            'name'   => $migration['name'],
            'ok'     => $outcome['ok'],
            'detail' => $outcome['ok']
                ? $outcome['executed'] . ' statements applied'
                : implode('; ', array_slice($outcome['errors'], 0, 3)),
        ];

        if (!$outcome['ok']) {
            // Stop at the first failure: later migrations may depend on this one.
            break;
        }

        record_migration($migration['name']);
    }

    $allOk = true;

    foreach ($results as $result) {
        if (!$result['ok']) {
            $allOk = false;
            break;
        }
    }

    if ($allOk) {
        Settings::set('schema_version', $targetVersion);
        Settings::flush();

        Audit::record(
            'upgrade',
            'system',
            null,
            'Upgraded from ' . $currentVersion . ' to ' . $targetVersion
        );

        flash('success', 'RideLog is up to date at version ' . $targetVersion . '.');
    } else {
        flash('error', 'The upgrade stopped at the first problem. Nothing after it was applied.');
    }

    // Recompute the list for the summary below.
    $migrations = discover_migrations();
    $pending    = array_values(array_filter($migrations, static function (array $m): bool {
        return !$m['applied'];
    }));
    $missingSettings = missing_settings();
    $currentVersion  = Settings::schemaVersion();
}

$upToDate = $pending === []
    && $missingSettings === []
    && version_compare($currentVersion, $targetVersion, '>=');

ob_start();
?>
<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?= icon('refresh', '', 18) ?> Upgrade RideLog</h2>
            <p class="card-subtitle">Applies database changes after you replace the application files.</p>
        </div>
    </div>
    <div class="card-body">

        <dl class="detail-list mb-5">
            <dt>Database version</dt>
            <dd><code><?= e($currentVersion) ?></code></dd>

            <dt>Application version</dt>
            <dd><code><?= e($targetVersion) ?></code></dd>

            <dt>Pending migrations</dt>
            <dd><?= count($pending) === 0 ? 'None' : count($pending) ?></dd>
        </dl>

        <?php if ($results !== []): ?>
            <h3>Results</h3>
            <div class="table-wrap mb-5">
                <table class="table">
                    <thead>
                        <tr><th>Step</th><th>Outcome</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td><code><?= e($result['name']) ?></code></td>
                                <td>
                                    <?php if ($result['ok']): ?>
                                        <span class="badge badge-ok">Applied</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Failed</span>
                                    <?php endif; ?>
                                    <div class="text-sm text-muted"><?= e($result['detail']) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($upToDate && !$ran): ?>
            <div class="alert alert-success">
                <?= icon('check-circle', '', 18) ?>
                <div class="alert-body">
                    <strong class="alert-title">Nothing to do</strong>
                    <p style="margin:4px 0 0">Your database matches this version of RideLog.</p>
                </div>
            </div>
        <?php elseif (!$upToDate): ?>
            <div class="alert alert-warning">
                <?= icon('alert-triangle', '', 18) ?>
                <div class="alert-body">
                    <strong class="alert-title">Back up your database first</strong>
                    <p style="margin:4px 0 0">
                        In cPanel, open <strong>phpMyAdmin</strong>, select
                        <code><?= e((string) config('db.name')) ?></code>, choose <strong>Export</strong>
                        and save the file. An upgrade changes your live data and there is no undo.
                    </p>
                </div>
            </div>

            <?php if ($pending !== [] || $missingSettings !== []): ?>
                <h3>Will be applied</h3>
                <ul>
                    <?php foreach ($pending as $migration): ?>
                        <li><code><?= e($migration['name']) ?></code></li>
                    <?php endforeach; ?>
                    <?php if ($missingSettings !== []): ?>
                        <li>
                            <?= count($missingSettings) ?> new setting<?= count($missingSettings) === 1 ? '' : 's' ?>
                            this version added
                            <span class="text-sm text-muted">
                                (<?= e(implode(', ', array_slice($missingSettings, 0, 6))) ?><?= count($missingSettings) > 6 ? ', …' : '' ?>)
                            </span>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($migrations !== []): ?>
            <details class="mt-4">
                <summary class="text-muted text-sm" style="cursor:pointer">
                    Migration history (<?= count($migrations) ?> files)
                </summary>
                <div class="table-wrap mt-3">
                    <table class="table table-compact">
                        <thead><tr><th>File</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($migrations as $migration): ?>
                                <tr>
                                    <td><code><?= e($migration['name']) ?></code></td>
                                    <td>
                                        <?php if ($migration['applied']): ?>
                                            <span class="badge badge-ok">Applied</span>
                                        <?php else: ?>
                                            <span class="badge badge-muted">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
    </div>

    <div class="card-footer">
        <a class="btn btn-secondary" href="<?= e(url('settings.php')) ?>">Back to settings</a>
        <span class="flex-1"></span>
        <form method="post" action="<?= e(url('install/upgrade.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary"
                    data-confirm="This changes your live database. Have you taken a backup?"
                    data-confirm-title="Run the upgrade">
                <?= icon('refresh', '', 17) ?>
                <?= $upToDate ? 'Run anyway' : 'Run upgrade' ?>
            </button>
        </form>
    </div>
</div>
<?php
$content = (string) ob_get_clean();

App\View::render('raw', [
    'html'           => $content,
    'title'          => 'Upgrade RideLog',
    'activeNav'      => 'settings.php',
    'hidePageHeader' => true,
    'breadcrumbs'    => [
        ['label' => 'Settings', 'url' => url('settings.php')],
        ['label' => 'Upgrade'],
    ],
], 'layout');
