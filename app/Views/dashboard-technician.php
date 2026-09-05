<?php
/**
 * The home page for a technician.
 *
 * A mechanic opening this at eight in the morning, probably on a phone, wants
 * three things: a way to log what they are about to do, whatever is broken or
 * due today, and whatever has their name on it. That is the whole page. The
 * fleet figures, trends and stock warnings that a manager wants live on the
 * other dashboard.
 */

use App\Dates;
use App\Settings;
use App\Status;
use App\View;

$overdue = 0;

foreach ($dueList as $due) {
    if ($due['due_state'] === 'overdue') {
        $overdue++;
    }
}
?>

<?php // ============================ The three buttons ============================ ?>
<div class="action-grid no-print">
    <?php if (can('logs.create')): ?>
        <a class="action-tile is-primary" href="<?= e(url('log-edit.php')) ?>">
            <span class="action-icon"><?= icon('wrench', '', 26) ?></span>
            <span class="action-text">
                <strong>Log maintenance</strong>
                <small>Record a job you have just done</small>
            </span>
            <?= icon('chevron-right', 'action-arrow', 20) ?>
        </a>
    <?php endif; ?>

    <?php if (can('inspections.perform')): ?>
        <a class="action-tile" href="<?= e(url('inspection-run.php')) ?>">
            <span class="action-icon"><?= icon('clipboard-check', '', 26) ?></span>
            <span class="action-text">
                <strong>Run today's check</strong>
                <small><?= $inspections === []
                    ? 'Every ' . asset_word() . ' is checked for today'
                    : count($inspections) . ' ' . (count($inspections) === 1 ? asset_word() : asset_word(true)) . ' still to check' ?></small>
            </span>
            <?= icon('chevron-right', 'action-arrow', 20) ?>
        </a>
    <?php endif; ?>

    <?php if (can('workorders.create')): ?>
        <a class="action-tile" href="<?= e(url('workorder-edit.php')) ?>">
            <span class="action-icon"><?= icon('alert-circle', '', 26) ?></span>
            <span class="action-text">
                <strong>Report a problem</strong>
                <small>Something wrong you are not fixing right now</small>
            </span>
            <?= icon('chevron-right', 'action-arrow', 20) ?>
        </a>
    <?php endif; ?>
</div>

<?php // ============================ Assigned to you ============================ ?>
<?php if ($myWork['count'] > 0): ?>
    <div class="card is-accent">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= icon('user', '', 18) ?> Your jobs</h2>
                <p class="card-subtitle">Work orders with your name on them, most urgent first</p>
            </div>
            <span class="badge badge-brand"><?= (int) $myWork['count'] ?></span>
        </div>
        <ul class="activity-list">
            <?php foreach ($myWork['work_orders'] as $wo): ?>
                <li class="activity-item">
                    <span class="status-dot tone-<?= e(Status::tone((string) $wo['priority'], 'priority')) ?>"
                          style="margin-top:7px"></span>
                    <span class="activity-body">
                        <a href="<?= e(url('workorder-view.php', ['id' => (int) $wo['id']])) ?>">
                            <?= e((string) $wo['title']) ?>
                        </a>
                        <div class="text-sm text-muted">
                            <?= e((string) ($wo['asset_name'] ?? 'No ' . asset_word())) ?>
                            &middot; <?= e(Status::label((string) $wo['priority'], 'priority')) ?>
                            <?php if (!empty($wo['due_date'])): ?>
                                &middot; due <?= e(Dates::dateOnly((string) $wo['due_date'])) ?>
                            <?php endif; ?>
                        </div>
                    </span>
                    <?php if (can('logs.create')): ?>
                        <a class="btn btn-secondary btn-sm"
                           href="<?= e(url('log-edit.php', ['work_order_id' => (int) $wo['id']])) ?>">
                            Log the work
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($myWork['count'] > count($myWork['work_orders'])): ?>
            <div class="card-footer">
                <a class="btn btn-ghost btn-sm btn-block"
                   href="<?= e(url('workorders.php', ['assigned_to' => (int) user()['id']])) ?>">
                    All <?= (int) $myWork['count'] ?> of your jobs
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php // ============================ Not running ============================ ?>
<?php if ($assetsDown !== []): ?>
    <div class="card is-danger">
        <div class="card-header">
            <h2 class="card-title"><?= icon('alert-triangle', '', 18) ?> Not running</h2>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('assets.php', ['status' => 'out_of_service'])) ?>">
                All <?= icon('chevron-right', '', 14) ?>
            </a>
        </div>
        <ul class="activity-list">
            <?php foreach ($assetsDown as $asset): ?>
                <li class="activity-item">
                    <span class="activity-body">
                        <a href="<?= e(url('asset-view.php', ['id' => (int) $asset['id']])) ?>">
                            <?= e((string) $asset['name']) ?>
                        </a>
                        <div class="text-sm text-muted">
                            <?= e(Status::label((string) $asset['status'], 'asset')) ?>
                            <?php if (!empty($asset['location_name'])): ?>
                                &middot; <?= e((string) $asset['location_name']) ?>
                            <?php endif; ?>
                            &middot; since <?= e(Dates::ago((string) $asset['updated_at'])) ?>
                        </div>
                    </span>
                    <?php if (can('logs.create')): ?>
                        <a class="btn btn-secondary btn-sm"
                           href="<?= e(url('log-edit.php', ['asset_id' => (int) $asset['id']])) ?>">
                            Log work
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php // ============================ Today's checks ============================ ?>
<?php if (can('inspections.perform') && $inspections !== []): ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= icon('clipboard-check', '', 18) ?> Still to check today</h2>
                <p class="card-subtitle">Tap <?= e(an_asset()) ?> to start its check</p>
            </div>
        </div>
        <div class="card-body is-tight">
            <div class="flex gap-2 flex-wrap">
                <?php foreach ($inspections as $item): ?>
                    <a class="chip chip-lg"
                       title="<?= attr((string) $item['checklist_name']) ?>"
                       href="<?= e(url('inspection-run.php', [
                           'asset_id'     => (int) $item['id'],
                           'checklist_id' => (int) $item['checklist_id'],
                       ])) ?>">
                        <?= icon('clipboard', '', 15) ?>
                        <?= e((string) $item['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php // ============================ Service due ============================ ?>
<?php if (can('schedules.view')): ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= icon('calendar', '', 18) ?> Service due</h2>
                <p class="card-subtitle">
                    <?= $overdue > 0
                        ? $overdue . ' overdue, most urgent first'
                        : 'Next ' . (int) Settings::int('dashboard_pm_lookahead_days', 14) . ' days' ?>
                </p>
            </div>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('schedules.php')) ?>">
                All <?= icon('chevron-right', '', 14) ?>
            </a>
        </div>

        <?php if ($dueList === []): ?>
            <?php View::partial('empty-state', [
                'icon'    => 'check-circle',
                'title'   => 'Nothing due',
                'message' => 'No service falls due in the next couple of weeks.',
            ]); ?>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($dueList as $due): ?>
                    <?php $state = (string) $due['due_state']; ?>
                    <li class="activity-item">
                        <span class="status-dot tone-<?= e(Status::tone($state, 'due')) ?>" style="margin-top:7px"></span>
                        <span class="activity-body">
                            <a href="<?= e(url('asset-view.php', ['id' => (int) $due['asset_id']])) ?>">
                                <?= e((string) $due['asset_name']) ?>
                            </a>
                            &mdash; <?= e((string) $due['name']) ?>
                            <div class="text-sm text-muted">
                                <?= e(Status::label($state, 'due')) ?>
                                <?php if (!empty($due['next_due_date'])): ?>
                                    &middot; <?= e(Dates::dateOnly((string) $due['next_due_date'])) ?>
                                <?php elseif (!empty($due['next_due_meter'])): ?>
                                    &middot; at <?= e(decimal($due['next_due_meter'])) ?> <?= e((string) $due['meter_type']) ?>
                                    (now <?= e(decimal($due['meter_reading'])) ?>)
                                <?php endif; ?>
                                <?php if (!empty($due['assignee_first'])): ?>
                                    &middot; <?= e(trim((string) $due['assignee_first'] . ' ' . (string) $due['assignee_last'])) ?>
                                <?php endif; ?>
                            </div>
                        </span>
                        <?php if (can('logs.create')): ?>
                            <a class="btn btn-secondary btn-sm"
                               href="<?= e(url('log-edit.php', ['schedule_id' => (int) $due['id']])) ?>">
                                Log it
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid grid-2">
    <?php // ============================ Open problems ============================ ?>
    <?php if (can('workorders.view')): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('work-order', '', 18) ?> Open problems</h2>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('workorders.php')) ?>">
                    All <?= icon('chevron-right', '', 14) ?>
                </a>
            </div>
            <?php if ($workOrders === []): ?>
                <?php View::partial('empty-state', [
                    'icon'    => 'check-circle',
                    'title'   => 'Nothing open',
                    'message' => 'Everything reported has been dealt with.',
                ]); ?>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($workOrders as $wo): ?>
                        <li class="activity-item">
                            <span class="status-dot tone-<?= e(Status::tone((string) $wo['priority'], 'priority')) ?>"
                                  style="margin-top:7px"></span>
                            <span class="activity-body">
                                <a href="<?= e(url('workorder-view.php', ['id' => (int) $wo['id']])) ?>">
                                    <?= e((string) $wo['title']) ?>
                                </a>
                                <div class="text-sm text-muted">
                                    <?= e((string) ($wo['asset_name'] ?? 'No ' . asset_word())) ?>
                                    &middot;
                                    <?php if (!empty($wo['assignee_id'])): ?>
                                        <?= e(trim((string) $wo['first_name'] . ' ' . (string) $wo['last_name'])) ?>
                                    <?php else: ?>
                                        nobody on it yet
                                    <?php endif; ?>
                                </div>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php // ============================ Recent work ============================ ?>
    <?php if (can('logs.view')): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title"><?= icon('history', '', 18) ?> Recent work</h2>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('logs.php')) ?>">All</a>
            </div>
            <?php if ($recentLogs === []): ?>
                <?php View::partial('empty-state', [
                    'icon'    => 'wrench',
                    'title'   => 'Nothing logged yet',
                    'message' => 'The first job somebody does shows up here.',
                ]); ?>
            <?php else: ?>
                <ul class="activity-list">
                    <?php foreach ($recentLogs as $log): ?>
                        <li class="activity-item">
                            <?php View::partial('avatar', [
                                'user' => [
                                    'id'          => $log['user_id'],
                                    'first_name'  => $log['first_name'],
                                    'last_name'   => $log['last_name'],
                                    'username'    => $log['username'],
                                    'avatar_path' => $log['avatar_path'],
                                ],
                                'size' => 'sm',
                            ]); ?>
                            <span class="activity-body">
                                <a href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>">
                                    <?= e(str_limit((string) $log['title'], 60)) ?>
                                </a>
                                <div class="text-sm text-muted">
                                    <?= e((string) $log['asset_name']) ?>
                                    &middot; <?= e(Dates::ago((string) $log['performed_at'])) ?>
                                </div>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php // ============================ Follow-ups ============================ ?>
<?php if ($followUps !== []): ?>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><?= icon('alert-circle', '', 18) ?> Needs going back to</h2>
        </div>
        <ul class="activity-list">
            <?php foreach ($followUps as $log): ?>
                <li class="activity-item">
                    <span class="activity-body">
                        <a href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>">
                            <?= e((string) $log['title']) ?>
                        </a>
                        <div class="text-sm text-muted"><?= e((string) $log['asset_name']) ?></div>
                        <?php if (!empty($log['followup_notes'])): ?>
                            <div class="text-sm" style="color:var(--warn)">
                                <?= e(str_limit((string) $log['followup_notes'], 110)) ?>
                            </div>
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
