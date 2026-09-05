<?php
/**
 * The dashboard.
 *
 * Ordered by what somebody walking into the shop at 8am needs to know:
 * what is broken, what is due, what is assigned to me — then the trends.
 */

use App\Dates;
use App\Settings;
use App\Status;
use App\View;

$currency = Settings::currency();
?>

<?php // ============================ Quick actions ============================ ?>
<div class="flex gap-2 flex-wrap mb-5 no-print">
    <?php if (can('logs.create')): ?>
        <a class="btn btn-primary" href="<?= e(url('log-edit.php')) ?>">
            <?= icon('plus', '', 17) ?> Log maintenance
        </a>
    <?php endif; ?>
    <?php if (can('inspections.perform')): ?>
        <a class="btn btn-secondary" href="<?= e(url('inspections.php', ['action' => 'start'])) ?>">
            <?= icon('clipboard-check', '', 17) ?> Run an inspection
        </a>
    <?php endif; ?>
    <?php if (can('workorders.create')): ?>
        <a class="btn btn-secondary" href="<?= e(url('workorder-edit.php')) ?>">
            <?= icon('work-order', '', 17) ?> Report an issue
        </a>
    <?php endif; ?>
    <?php if (can('assets.create')): ?>
        <a class="btn btn-secondary" href="<?= e(url('asset-edit.php')) ?>">
            <?= icon('assets', '', 17) ?> Add a machine
        </a>
    <?php endif; ?>
</div>

<?php // ============================== KPI tiles =============================== ?>
<div class="stat-grid">
    <?php View::partial('stat-card', [
        'label' => 'In service',
        'value' => num($counts['assets_in_service']),
        'sub'   => 'of ' . num($counts['assets_total']) . ' machines',
        'icon'  => 'check-circle',
        'tone'  => 'ok',
        'href'  => can('assets.view') ? url('assets.php', ['status' => 'in_service']) : '',
    ]); ?>

    <?php View::partial('stat-card', [
        'label' => 'Down or in the shop',
        'value' => num($counts['assets_down']),
        'sub'   => $counts['assets_down'] === 0 ? 'Everything is running' : 'Not earning',
        'icon'  => 'alert-triangle',
        'tone'  => $counts['assets_down'] > 0 ? 'danger' : 'muted',
        'href'  => can('assets.view') ? url('assets.php', ['status' => 'out_of_service']) : '',
    ]); ?>

    <?php
    $overdue = 0;

    foreach ($dueList as $due) {
        if ($due['due_state'] === 'overdue') {
            $overdue++;
        }
    }
    ?>
    <?php View::partial('stat-card', [
        'label' => 'Maintenance due',
        'value' => num(count($dueList)),
        'sub'   => $overdue > 0 ? $overdue . ' overdue' : 'Nothing overdue',
        'icon'  => 'calendar',
        'tone'  => $overdue > 0 ? 'danger' : (count($dueList) > 0 ? 'warn' : 'ok'),
        'href'  => can('schedules.view') ? url('schedules.php', ['due' => 'soon']) : '',
    ]); ?>

    <?php View::partial('stat-card', [
        'label' => 'Open work orders',
        'value' => num($counts['wo_open']),
        'sub'   => $counts['wo_urgent'] > 0 ? $counts['wo_urgent'] . ' urgent' : 'None urgent',
        'icon'  => 'work-order',
        'tone'  => $counts['wo_urgent'] > 0 ? 'danger' : ($counts['wo_open'] > 0 ? 'info' : 'muted'),
        'href'  => can('workorders.view') ? url('workorders.php', ['status' => 'open']) : '',
    ]); ?>

    <?php View::partial('stat-card', [
        'label' => 'Jobs logged',
        'value' => num($counts['logs_30d']),
        'sub'   => 'in the last 30 days',
        'icon'  => 'wrench',
        'tone'  => 'brand',
        'href'  => can('logs.view') ? url('logs.php', ['range' => 'last_30']) : '',
    ]); ?>

    <?php if (costs_visible()): ?>
        <?php
        $change = $costTrend['change_pct'];
        $trend  = $change === null ? '' : ($change > 2 ? 'up' : ($change < -2 ? 'down' : 'flat'));
        ?>
        <?php View::partial('stat-card', [
            'label'       => 'Maintenance spend',
            'value'       => money($costTrend['current']),
            'sub'         => 'in the last 30 days',
            'icon'        => 'dollar-sign',
            'tone'        => 'info',
            'href'        => url('reports.php', ['report' => 'cost']),
            'trend'       => $trend,
            'trendLabel'  => $change === null ? '' : abs($change) . '% vs previous 30 days',
            'trendInvert' => true,
        ]); ?>
    <?php endif; ?>
</div>

<div class="grid grid-sidebar">

    <?php // ========================== Main column ========================== ?>
    <div>

        <?php // ---------- Machines down: the most urgent thing on the page ---------- ?>
        <?php if ($assetsDown !== []): ?>
            <div class="card is-danger">
                <div class="card-header">
                    <h2 class="card-title">
                        <?= icon('alert-triangle', '', 18) ?> Not available to guests
                    </h2>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('assets.php', ['status' => 'out_of_service'])) ?>">
                        View all <?= icon('chevron-right', '', 14) ?>
                    </a>
                </div>
                <div class="table-wrap">
                    <table class="table is-stacked">
                        <thead>
                            <tr>
                                <th>Machine</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Since</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assetsDown as $asset): ?>
                                <tr data-row-href="<?= e(url('asset-view.php', ['id' => (int) $asset['id']])) ?>">
                                    <td data-label="Machine" class="is-row-title">
                                        <a href="<?= e(url('asset-view.php', ['id' => (int) $asset['id']])) ?>">
                                            <?= e((string) $asset['name']) ?>
                                        </a>
                                        <span class="cell-secondary"><?= e((string) $asset['asset_tag']) ?></span>
                                    </td>
                                    <td data-label="Status">
                                        <?php View::partial('status-badge', ['value' => (string) $asset['status'], 'vocabulary' => 'asset']); ?>
                                    </td>
                                    <td data-label="Location"><?= e((string) ($asset['location_name'] ?? '—')) ?></td>
                                    <td data-label="Since"><?= e(Dates::ago((string) $asset['updated_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php // ---------------------- Maintenance due ---------------------- ?>
        <?php if (can('schedules.view')): ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= icon('calendar', '', 18) ?> Maintenance due</h2>
                        <p class="card-subtitle">
                            Next <?= (int) Settings::int('dashboard_pm_lookahead_days', 14) ?> days, most urgent first
                        </p>
                    </div>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('schedules.php')) ?>">
                        All schedules <?= icon('chevron-right', '', 14) ?>
                    </a>
                </div>

                <?php if ($dueList === []): ?>
                    <?php View::partial('empty-state', [
                        'icon'    => 'check-circle',
                        'title'   => 'Nothing due',
                        'message' => 'No scheduled service falls due in the next couple of weeks.',
                    ]); ?>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table is-stacked">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Job</th>
                                    <th>Due</th>
                                    <th>Assigned</th>
                                    <th class="is-actions"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dueList as $due): ?>
                                    <?php $state = (string) $due['due_state']; ?>
                                    <tr class="<?= $state === 'overdue' ? 'is-danger' : '' ?>">
                                        <td data-label="Machine" class="is-row-title">
                                            <a href="<?= e(url('asset-view.php', ['id' => (int) $due['asset_id']])) ?>">
                                                <?= e((string) $due['asset_name']) ?>
                                            </a>
                                            <span class="cell-secondary"><?= e((string) $due['asset_tag']) ?></span>
                                        </td>
                                        <td data-label="Job"><?= e((string) $due['name']) ?></td>
                                        <td data-label="Due">
                                            <?php View::partial('status-badge', ['value' => $state, 'vocabulary' => 'due']); ?>
                                            <span class="cell-secondary">
                                                <?php if (!empty($due['next_due_date'])): ?>
                                                    <?= e(Dates::dateOnly((string) $due['next_due_date'])) ?>
                                                <?php elseif (!empty($due['next_due_meter'])): ?>
                                                    at <?= e(decimal($due['next_due_meter'])) ?> <?= e((string) $due['meter_type']) ?>
                                                    <span class="text-subtle">(now <?= e(decimal($due['meter_reading'])) ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td data-label="Assigned">
                                            <?php if (!empty($due['assignee_first'])): ?>
                                                <?= e(trim((string) $due['assignee_first'] . ' ' . (string) $due['assignee_last'])) ?>
                                            <?php else: ?>
                                                <span class="text-subtle">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="is-actions">
                                            <?php if (can('logs.create')): ?>
                                                <a class="btn btn-secondary btn-sm"
                                                   href="<?= e(url('log-edit.php', ['schedule_id' => (int) $due['id']])) ?>">
                                                    Log it
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php // -------------------- Inspections due today -------------------- ?>
        <?php if (can('inspections.view') && $inspections !== []): ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= icon('clipboard-check', '', 18) ?> Daily inspections outstanding</h2>
                        <p class="card-subtitle">In-service machines with no completed check today</p>
                    </div>
                </div>
                <div class="card-body is-tight">
                    <div class="flex gap-2 flex-wrap">
                        <?php foreach ($inspections as $item): ?>
                            <a class="chip" style="padding:6px 12px"
                               title="<?= attr((string) $item['checklist_name']) ?>"
                               href="<?= e(url('inspection-run.php', [
                                   'asset_id'     => (int) $item['id'],
                                   'checklist_id' => (int) $item['checklist_id'],
                               ])) ?>">
                                <?= icon('clipboard', '', 14) ?>
                                <?= e((string) $item['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php // ------------------------ Work orders ------------------------ ?>
        <?php if (can('workorders.view')): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('work-order', '', 18) ?> Open work orders</h2>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('workorders.php')) ?>">
                        View all <?= icon('chevron-right', '', 14) ?>
                    </a>
                </div>

                <?php if ($workOrders === []): ?>
                    <?php View::partial('empty-state', [
                        'icon'    => 'check-circle',
                        'title'   => 'No open work orders',
                        'message' => 'Everything reported has been dealt with.',
                    ]); ?>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table is-stacked">
                            <thead>
                                <tr>
                                    <th>Work order</th>
                                    <th>Machine</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Assigned</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($workOrders as $wo): ?>
                                    <tr class="<?= (string) $wo['priority'] === 'urgent' ? 'is-warn' : '' ?>"
                                        data-row-href="<?= e(url('workorder-view.php', ['id' => (int) $wo['id']])) ?>">
                                        <td data-label="Work order" class="is-row-title">
                                            <a href="<?= e(url('workorder-view.php', ['id' => (int) $wo['id']])) ?>">
                                                <?= e((string) $wo['title']) ?>
                                            </a>
                                            <span class="cell-secondary"><?= e((string) $wo['wo_number']) ?></span>
                                        </td>
                                        <td data-label="Machine"><?= e((string) ($wo['asset_name'] ?? '—')) ?></td>
                                        <td data-label="Priority">
                                            <?php View::partial('status-badge', ['value' => (string) $wo['priority'], 'vocabulary' => 'priority']); ?>
                                        </td>
                                        <td data-label="Status">
                                            <?php View::partial('status-badge', ['value' => (string) $wo['status'], 'vocabulary' => 'workorder']); ?>
                                        </td>
                                        <td data-label="Assigned">
                                            <?php if (!empty($wo['assignee_id'])): ?>
                                                <?php View::partial('user-chip', ['user' => [
                                                    'id'          => $wo['assignee_id'],
                                                    'first_name'  => $wo['first_name'],
                                                    'last_name'   => $wo['last_name'],
                                                    'username'    => $wo['username'],
                                                    'avatar_path' => $wo['avatar_path'],
                                                ]]); ?>
                                            <?php else: ?>
                                                <span class="text-subtle">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php // --------------------- Maintenance chart --------------------- ?>
        <?php if (can('logs.view') && $logsChart['labels'] !== []): ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= icon('chart-bar', '', 18) ?> Maintenance activity</h2>
                        <p class="card-subtitle">
                            Planned versus unplanned work, by month. A rising unplanned share means
                            things are breaking faster than they are being serviced.
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    <div data-chart="bar" data-chart-src="chart-logs" data-chart-height="250"
                         data-chart-title="Maintenance logs by month"></div>
                    <script type="application/json" id="chart-logs"><?= js($logsChart) ?></script>
                </div>
            </div>
        <?php endif; ?>

        <?php // ------------------------ Cost chart ------------------------ ?>
        <?php if (costs_visible() && $costChart['labels'] !== []): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('trending-up', '', 18) ?> Maintenance spend</h2>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('reports.php', ['report' => 'cost'])) ?>">
                        Cost report <?= icon('chevron-right', '', 14) ?>
                    </a>
                </div>
                <div class="card-body">
                    <div data-chart="line" data-chart-src="chart-cost" data-chart-height="220"
                         data-chart-format="money" data-chart-title="Maintenance cost by month"></div>
                    <script type="application/json" id="chart-cost"><?= js($costChart) ?></script>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php // =========================== Sidebar =========================== ?>
    <div>

        <?php // ------------------------- My work ------------------------- ?>
        <?php if ($myWork['count'] > 0): ?>
            <div class="card is-accent">
                <div class="card-header">
                    <h3 class="card-title"><?= icon('user', '', 17) ?> Assigned to you</h3>
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
                                    <?= e((string) ($wo['asset_name'] ?? 'No machine')) ?>
                                    <?php if (!empty($wo['due_date'])): ?>
                                        &middot; due <?= e(Dates::dateOnly((string) $wo['due_date'])) ?>
                                    <?php endif; ?>
                                </div>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="card-footer">
                    <a class="btn btn-secondary btn-sm btn-block"
                       href="<?= e(url('workorders.php', ['assigned_to' => (int) user()['id']])) ?>">
                        View all your work
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php // ----------------------- Fleet status ----------------------- ?>
        <?php if (can('assets.view') && $statusChart !== []): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Fleet status</h3>
                </div>
                <div class="card-body">
                    <div data-chart="donut" data-chart-src="chart-status" data-chart-size="190"
                         data-chart-centre-label="Machines" data-chart-title="Machines by status"></div>
                    <script type="application/json" id="chart-status"><?= js(['slices' => $statusChart]) ?></script>
                </div>
            </div>
        <?php endif; ?>

        <?php // ------------------------ Follow-ups ------------------------ ?>
        <?php if ($followUps !== []): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= icon('alert-circle', '', 17) ?> Needs a follow-up</h3>
                </div>
                <ul class="activity-list">
                    <?php foreach ($followUps as $log): ?>
                        <li class="activity-item">
                            <span class="activity-body">
                                <a href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>">
                                    <?= e((string) $log['title']) ?>
                                </a>
                                <div class="text-sm text-muted">
                                    <?= e((string) $log['asset_name']) ?>
                                </div>
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

        <?php // ------------------------ Low stock ------------------------ ?>
        <?php if (can('parts.view') && $lowStock !== []): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= icon('package', '', 17) ?> Low stock</h3>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('parts.php', ['stock' => 'low'])) ?>">All</a>
                </div>
                <ul class="activity-list">
                    <?php foreach ($lowStock as $part): ?>
                        <li class="activity-item">
                            <span class="activity-body">
                                <a href="<?= e(url('part-view.php', ['id' => (int) $part['id']])) ?>">
                                    <?= e((string) $part['name']) ?>
                                </a>
                                <div class="text-sm text-muted">
                                    <?= e(decimal($part['quantity_on_hand'])) ?> <?= e((string) $part['unit_of_measure']) ?>
                                    left &middot; reorder at <?= e(decimal($part['reorder_level'])) ?>
                                </div>
                            </span>
                            <?php View::partial('status-badge', [
                                'value'      => Status::stockState($part),
                                'vocabulary' => 'stock',
                            ]); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php // ---------------------- Recent activity ---------------------- ?>
        <?php if (can('logs.view')): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= icon('history', '', 17) ?> Recent work</h3>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('logs.php')) ?>">All</a>
                </div>

                <?php if ($recentLogs === []): ?>
                    <?php View::partial('empty-state', [
                        'icon'        => 'wrench',
                        'title'       => 'No maintenance logged yet',
                        'message'     => 'The first time somebody works on a kart or a ride, record it here.',
                        'actionLabel' => can('logs.create') ? 'Log maintenance' : '',
                        'actionUrl'   => can('logs.create') ? url('log-edit.php') : '',
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

        <?php // ------------------------- Downtime ------------------------- ?>
        <?php if ($downtime > 0): ?>
            <div class="card">
                <div class="card-body">
                    <span class="stat-label">Recorded downtime</span>
                    <span class="stat-value"><?= e(Dates::humanDuration($downtime)) ?></span>
                    <span class="stat-sub">across the last 30 days</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
