<?php
/**
 * One asset: everything known about it, and everything that has happened to it.
 */

use App\Dates;
use App\Settings;
use App\Status;
use App\View;

$assetId = (int) $asset['id'];

$tabs = [
    'overview'    => ['label' => 'Overview',     'icon' => 'info',            'url' => url('asset-view.php', ['id' => $assetId])],
    'logs'        => ['label' => 'History',      'icon' => 'wrench',          'url' => url('asset-view.php', ['id' => $assetId, 'tab' => 'logs']),        'count' => $counts['logs']],
    'schedules'   => ['label' => 'Schedules',    'icon' => 'calendar',        'url' => url('asset-view.php', ['id' => $assetId, 'tab' => 'schedules']),   'count' => $counts['schedules']],
    'inspections' => ['label' => 'Inspections',  'icon' => 'clipboard-check', 'url' => url('asset-view.php', ['id' => $assetId, 'tab' => 'inspections']), 'count' => $counts['inspections']],
    'workorders'  => ['label' => 'Work orders',  'icon' => 'work-order',      'url' => url('asset-view.php', ['id' => $assetId, 'tab' => 'workorders']),  'count' => $counts['work_orders']],
    'files'       => ['label' => 'Files',        'icon' => 'paperclip',       'url' => url('asset-view.php', ['id' => $assetId, 'tab' => 'files']),       'count' => $counts['attachments']],
];

if ((string) $asset['meter_type'] !== 'none') {
    $tabs['meter'] = ['label' => 'Meter', 'icon' => 'gauge', 'url' => url('asset-view.php', ['id' => $assetId, 'tab' => 'meter']), 'count' => $counts['meter']];
}

if (can('audit.view')) {
    $tabs['history'] = ['label' => 'Changes', 'icon' => 'history', 'url' => url('asset-view.php', ['id' => $assetId, 'tab' => 'history'])];
}
?>

<?php // ===================== Status banner ===================== ?>
<?php if (Status::isDownStatus((string) $asset['status'])): ?>
    <div class="alert alert-<?= (string) $asset['status'] === 'out_of_service' ? 'error' : 'warning' ?>">
        <?= icon('alert-triangle', '', 18) ?>
        <div class="alert-body">
            <strong class="alert-title">
                This asset is <?= e(strtolower(Status::label((string) $asset['status'], 'asset'))) ?>
            </strong>
            <p style="margin:4px 0 0">It is not available to guests. Change the status when it is back in service.</p>
        </div>
        <?php if (can('assets.edit')): ?>
            <form method="post" action="<?= e(url('asset-view.php', ['id' => $assetId])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="status" value="in_service">
                <button type="submit" class="btn btn-success btn-sm"
                        data-confirm="Put “<?= attr((string) $asset['name']) ?>” back in service?">
                    <?= icon('check', '', 15) ?> Back in service
                </button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid grid-sidebar">
    <div>
        <?php View::partial('tabs', ['tabs' => $tabs, 'active' => $tab, 'mode' => 'link']); ?>

        <div class="tab-panel">

        <?php // ======================== OVERVIEW ======================== ?>
        <?php if ($tab === 'overview'): ?>

            <div class="stat-grid">
                <?php View::partial('stat-card', [
                    'label' => 'Jobs logged', 'value' => num($summary['log_count']),
                    'icon' => 'wrench', 'tone' => 'brand',
                    'href' => url('asset-view.php', ['id' => $assetId, 'tab' => 'logs']),
                ]); ?>
                <?php View::partial('stat-card', [
                    'label' => 'Spent in 12 months', 'value' => money($summary['cost_12m']),
                    'sub' => 'lifetime ' . money($summary['total_cost']),
                    'icon' => 'dollar-sign', 'tone' => 'info',
                ]); ?>
                <?php View::partial('stat-card', [
                    'label' => 'Labour hours', 'value' => decimal($summary['total_hours'], 1),
                    'icon' => 'clock', 'tone' => 'muted',
                ]); ?>
                <?php View::partial('stat-card', [
                    'label' => 'Unplanned repairs', 'value' => num($summary['unplanned']),
                    'sub' => $summary['log_count'] > 0
                        ? round(($summary['unplanned'] / max(1, $summary['log_count'])) * 100) . '% of all jobs'
                        : '',
                    'icon' => 'alert-triangle',
                    'tone' => $summary['unplanned'] > ($summary['log_count'] / 2) ? 'warn' : 'muted',
                ]); ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('info', '', 18) ?> Details</h2>
                </div>
                <div class="card-body">
                    <dl class="detail-list">
                        <dt>Status</dt>
                        <dd><?php View::partial('status-badge', ['value' => (string) $asset['status'], 'vocabulary' => 'asset', 'withIcon' => true]); ?></dd>

                        <dt>Importance</dt>
                        <dd><?php View::partial('status-badge', ['value' => (string) $asset['criticality'], 'vocabulary' => 'criticality']); ?></dd>

                        <dt>Category</dt>
                        <dd><?= e((string) ($asset['category_name'] ?? '—')) ?></dd>

                        <dt>Location</dt>
                        <dd><?= e((string) ($asset['location_name'] ?? '—')) ?></dd>

                        <?php if (!empty($asset['manufacturer']) || !empty($asset['model'])): ?>
                            <dt>Make and model</dt>
                            <dd><?= e(trim((string) $asset['manufacturer'] . ' ' . (string) $asset['model'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['serial_number'])): ?>
                            <dt>Serial number</dt>
                            <dd><code><?= e((string) $asset['serial_number']) ?></code></dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['vin'])): ?>
                            <dt>VIN</dt>
                            <dd><code><?= e((string) $asset['vin']) ?></code></dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['engine_make']) || !empty($asset['engine_model'])): ?>
                            <dt>Engine</dt>
                            <dd>
                                <?= e(trim((string) $asset['engine_make'] . ' ' . (string) $asset['engine_model'])) ?>
                                <?php if (!empty($asset['engine_serial'])): ?>
                                    <span class="text-subtle">(<?= e((string) $asset['engine_serial']) ?>)</span>
                                <?php endif; ?>
                            </dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['year_manufactured'])): ?>
                            <dt>Year built</dt>
                            <dd><?= (int) $asset['year_manufactured'] ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['capacity_passengers'])): ?>
                            <dt>Capacity</dt>
                            <dd><?= (int) $asset['capacity_passengers'] ?> rider<?= (int) $asset['capacity_passengers'] === 1 ? '' : 's' ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['tire_size'])): ?>
                            <dt>Tyre size</dt>
                            <dd><?= e((string) $asset['tire_size']) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['fuel_type'])): ?>
                            <dt>Fuel</dt>
                            <dd><?= e((string) $asset['fuel_type']) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['in_service_date'])): ?>
                            <dt>In service since</dt>
                            <dd><?= e(Dates::dateOnly((string) $asset['in_service_date'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['purchase_date']) || $asset['purchase_cost'] !== null): ?>
                            <dt>Purchased</dt>
                            <dd>
                                <?= e(Dates::dateOnly((string) $asset['purchase_date'])) ?>
                                <?php if ($asset['purchase_cost'] !== null): ?>
                                    for <?= e(money($asset['purchase_cost'])) ?>
                                <?php endif; ?>
                            </dd>
                        <?php endif; ?>

                        <?php if (!empty($asset['warranty_expires'])): ?>
                            <?php $warrantyDays = Dates::daysUntil((string) $asset['warranty_expires']); ?>
                            <dt>Warranty</dt>
                            <dd>
                                <?= e(Dates::dateOnly((string) $asset['warranty_expires'])) ?>
                                <?php if ($warrantyDays !== null && $warrantyDays < 0): ?>
                                    <span class="badge badge-muted">Expired</span>
                                <?php elseif ($warrantyDays !== null && $warrantyDays < 60): ?>
                                    <span class="badge badge-warn">Ends in <?= (int) $warrantyDays ?> days</span>
                                <?php else: ?>
                                    <span class="badge badge-ok">In warranty</span>
                                <?php endif; ?>
                            </dd>
                        <?php endif; ?>
                    </dl>

                    <?php if (!empty($asset['description'])): ?>
                        <hr>
                        <p><?= nl2br_e((string) $asset['description']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($asset['notes'])): ?>
                        <hr>
                        <h4>Maintenance notes</h4>
                        <p class="text-muted"><?= nl2br_e((string) $asset['notes']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($data['dueSchedules'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= icon('calendar', '', 18) ?> Scheduled maintenance</h2>
                        <?php if (can('schedules.manage')): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('schedule-edit.php', ['asset_id' => $assetId])) ?>">
                                <?= icon('plus', '', 15) ?> Add
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="table-wrap">
                        <table class="table is-stacked">
                            <thead><tr><th>Job</th><th>Every</th><th>Next due</th><th class="is-actions"></th></tr></thead>
                            <tbody>
                                <?php foreach ($data['dueSchedules'] as $schedule): ?>
                                    <tr>
                                        <td data-label="Job" class="is-row-title"><?= e((string) $schedule['name']) ?></td>
                                        <td data-label="Every">
                                            <?= e(Dates::describeInterval(
                                                (string) $schedule['frequency_type'],
                                                (int) $schedule['frequency_value'],
                                                $schedule['meter_interval'] === null ? null : (float) $schedule['meter_interval'],
                                                (string) $asset['meter_type']
                                            )) ?>
                                        </td>
                                        <td data-label="Next due">
                                            <?php View::partial('status-badge', ['value' => (string) $schedule['due_state'], 'vocabulary' => 'due']); ?>
                                            <span class="cell-secondary">
                                                <?php if (!empty($schedule['next_due_date'])): ?>
                                                    <?= e(Dates::dateOnly((string) $schedule['next_due_date'])) ?>
                                                <?php elseif ($schedule['next_due_meter'] !== null): ?>
                                                    at <?= e(decimal($schedule['next_due_meter'])) ?> <?= e((string) $asset['meter_type']) ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="is-actions">
                                            <?php if (can('logs.create')): ?>
                                                <a class="btn btn-secondary btn-sm"
                                                   href="<?= e(url('log-edit.php', ['schedule_id' => (int) $schedule['id']])) ?>">Log it</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($data['openWorkOrders'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= icon('work-order', '', 18) ?> Open work orders</h2>
                    </div>
                    <ul class="activity-list">
                        <?php foreach ($data['openWorkOrders'] as $wo): ?>
                            <li class="activity-item">
                                <span class="status-dot tone-<?= e(Status::tone((string) $wo['priority'], 'priority')) ?>" style="margin-top:7px"></span>
                                <span class="activity-body">
                                    <a href="<?= e(url('workorder-view.php', ['id' => (int) $wo['id']])) ?>"><?= e((string) $wo['title']) ?></a>
                                    <div class="text-sm text-muted"><?= e((string) $wo['wo_number']) ?></div>
                                </span>
                                <?php View::partial('status-badge', ['value' => (string) $wo['status'], 'vocabulary' => 'workorder']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('history', '', 18) ?> Recent maintenance</h2>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('asset-view.php', ['id' => $assetId, 'tab' => 'logs'])) ?>">
                        Full history <?= icon('chevron-right', '', 14) ?>
                    </a>
                </div>
                <?php if (empty($data['recentLogs'])): ?>
                    <?php View::partial('empty-state', [
                        'icon'        => 'wrench',
                        'title'       => 'Nothing logged yet',
                        'message'     => 'The first time somebody works on this, record it here.',
                        'actionLabel' => can('logs.create') ? 'Log maintenance' : '',
                        'actionUrl'   => can('logs.create') ? url('log-edit.php', ['asset_id' => $assetId]) : '',
                        'actionIcon'  => 'wrench',
                    ]); ?>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($data['recentLogs'] as $log): ?>
                            <li class="activity-item">
                                <?php View::partial('avatar', ['user' => $log, 'size' => 'sm']); ?>
                                <span class="activity-body">
                                    <a href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>"><?= e((string) $log['title']) ?></a>
                                    <div class="text-sm text-muted">
                                        <?php View::partial('status-badge', ['value' => (string) $log['log_type'], 'vocabulary' => 'log_type']); ?>
                                        <?= e(Dates::datetime((string) $log['performed_at'])) ?>
                                    </div>
                                </span>
                                <?php if ((float) $log['total_cost'] > 0): ?>
                                    <span class="text-sm tabular"><?= e(money($log['total_cost'])) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        <?php // ========================== LOGS ========================== ?>
        <?php elseif ($tab === 'logs'): ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Maintenance history</h2>
                    <?php if (can('logs.create')): ?>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('log-edit.php', ['asset_id' => $assetId])) ?>">
                            <?= icon('plus', '', 15) ?> Log maintenance
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (empty($data['logs'])): ?>
                    <?php View::partial('empty-state', ['icon' => 'wrench', 'title' => 'Nothing logged yet']); ?>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table is-stacked table-sortable">
                            <thead>
                                <tr>
                                    <th data-sort>When</th><th data-sort>What</th><th data-sort>Type</th>
                                    <th data-sort>Who</th><th class="is-numeric" data-sort>Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['logs'] as $log): ?>
                                    <tr data-row-href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>">
                                        <td data-label="When" data-value="<?= e((string) $log['performed_at']) ?>">
                                            <?= e(Dates::date((string) $log['performed_at'])) ?>
                                            <span class="cell-secondary"><?= e(Dates::time((string) $log['performed_at'])) ?></span>
                                        </td>
                                        <td data-label="What" class="is-row-title">
                                            <a href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>"><?= e((string) $log['title']) ?></a>
                                        </td>
                                        <td data-label="Type">
                                            <?php View::partial('status-badge', ['value' => (string) $log['log_type'], 'vocabulary' => 'log_type']); ?>
                                        </td>
                                        <td data-label="Who"><?php View::partial('user-chip', ['user' => $log]); ?></td>
                                        <td data-label="Cost" class="is-numeric" data-value="<?= e((string) $log['total_cost']) ?>">
                                            <?= e(money($log['total_cost'], true)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php // ======================== SCHEDULES ======================== ?>
        <?php elseif ($tab === 'schedules'): ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Preventive maintenance</h2>
                    <?php if (can('schedules.manage')): ?>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('schedule-edit.php', ['asset_id' => $assetId])) ?>">
                            <?= icon('plus', '', 15) ?> Add a schedule
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (empty($data['schedules'])): ?>
                    <?php View::partial('empty-state', [
                        'icon'        => 'calendar',
                        'title'       => 'No scheduled maintenance',
                        'message'     => 'Set up a schedule and this asset will appear on the dashboard when a service falls due.',
                        'actionLabel' => can('schedules.manage') ? 'Add a schedule' : '',
                        'actionUrl'   => can('schedules.manage') ? url('schedule-edit.php', ['asset_id' => $assetId]) : '',
                    ]); ?>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table is-stacked">
                            <thead><tr><th>Job</th><th>Every</th><th>Last done</th><th>Next due</th><th class="is-actions"></th></tr></thead>
                            <tbody>
                                <?php foreach ($data['schedules'] as $schedule): ?>
                                    <tr class="<?= $schedule['due_state'] === 'overdue' ? 'is-danger' : '' ?>">
                                        <td data-label="Job" class="is-row-title">
                                            <?= e((string) $schedule['name']) ?>
                                            <?php if (!(int) $schedule['is_active']): ?>
                                                <span class="badge badge-muted">Paused</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Every">
                                            <?= e(Dates::describeInterval(
                                                (string) $schedule['frequency_type'],
                                                (int) $schedule['frequency_value'],
                                                $schedule['meter_interval'] === null ? null : (float) $schedule['meter_interval'],
                                                (string) $asset['meter_type']
                                            )) ?>
                                        </td>
                                        <td data-label="Last done">
                                            <?= empty($schedule['last_performed_at'])
                                                ? '<span class="text-subtle">Never</span>'
                                                : e(Dates::date((string) $schedule['last_performed_at'])) ?>
                                        </td>
                                        <td data-label="Next due">
                                            <?php View::partial('status-badge', ['value' => (string) $schedule['due_state'], 'vocabulary' => 'due']); ?>
                                            <span class="cell-secondary">
                                                <?php if (!empty($schedule['next_due_date'])): ?>
                                                    <?= e(Dates::dateOnly((string) $schedule['next_due_date'])) ?>
                                                <?php elseif ($schedule['next_due_meter'] !== null): ?>
                                                    at <?= e(decimal($schedule['next_due_meter'])) ?>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="is-actions">
                                            <?php if (can('logs.create')): ?>
                                                <a class="btn btn-secondary btn-sm"
                                                   href="<?= e(url('log-edit.php', ['schedule_id' => (int) $schedule['id']])) ?>">Log it</a>
                                            <?php endif; ?>
                                            <?php if (can('schedules.manage')): ?>
                                                <a class="btn btn-ghost btn-sm btn-icon"
                                                   href="<?= e(url('schedule-edit.php', ['id' => (int) $schedule['id']])) ?>"
                                                   aria-label="Edit schedule"><?= icon('edit', '', 15) ?></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php // ======================= INSPECTIONS ======================= ?>
        <?php elseif ($tab === 'inspections'): ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Inspections</h2>
                    <?php if (can('inspections.perform')): ?>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('inspection-run.php', ['asset_id' => $assetId])) ?>">
                            <?= icon('clipboard-check', '', 15) ?> Run an inspection
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (empty($data['inspections'])): ?>
                    <?php View::partial('empty-state', ['icon' => 'clipboard-check', 'title' => 'No inspections recorded']); ?>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table is-stacked">
                            <thead><tr><th>When</th><th>Checklist</th><th>Result</th><th>By</th></tr></thead>
                            <tbody>
                                <?php foreach ($data['inspections'] as $inspection): ?>
                                    <tr data-row-href="<?= e(url('inspection-view.php', ['id' => (int) $inspection['id']])) ?>">
                                        <td data-label="When" class="is-row-title">
                                            <a href="<?= e(url('inspection-view.php', ['id' => (int) $inspection['id']])) ?>">
                                                <?= e(Dates::datetime((string) $inspection['started_at'])) ?>
                                            </a>
                                        </td>
                                        <td data-label="Checklist"><?= e((string) $inspection['checklist_name']) ?></td>
                                        <td data-label="Result">
                                            <?php View::partial('status-badge', ['value' => (string) $inspection['status'], 'vocabulary' => 'inspection']); ?>
                                            <?php if ((int) $inspection['failed_count'] > 0): ?>
                                                <span class="cell-secondary"><?= (int) $inspection['failed_count'] ?> failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="By"><?php View::partial('user-chip', ['user' => $inspection]); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php // ======================= WORK ORDERS ======================= ?>
        <?php elseif ($tab === 'workorders'): ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Work orders</h2>
                    <?php if (can('workorders.create')): ?>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('workorder-edit.php', ['asset_id' => $assetId])) ?>">
                            <?= icon('plus', '', 15) ?> Report an issue
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (empty($data['workOrders'])): ?>
                    <?php View::partial('empty-state', ['icon' => 'work-order', 'title' => 'No work orders']); ?>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table is-stacked">
                            <thead><tr><th>Work order</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Raised</th></tr></thead>
                            <tbody>
                                <?php foreach ($data['workOrders'] as $wo): ?>
                                    <tr data-row-href="<?= e(url('workorder-view.php', ['id' => (int) $wo['id']])) ?>">
                                        <td data-label="Work order" class="is-row-title">
                                            <a href="<?= e(url('workorder-view.php', ['id' => (int) $wo['id']])) ?>"><?= e((string) $wo['title']) ?></a>
                                            <span class="cell-secondary"><?= e((string) $wo['wo_number']) ?></span>
                                        </td>
                                        <td data-label="Priority"><?php View::partial('status-badge', ['value' => (string) $wo['priority'], 'vocabulary' => 'priority']); ?></td>
                                        <td data-label="Status"><?php View::partial('status-badge', ['value' => (string) $wo['status'], 'vocabulary' => 'workorder']); ?></td>
                                        <td data-label="Assigned"><?php View::partial('user-chip', ['user' => $wo]); ?></td>
                                        <td data-label="Raised"><?= e(Dates::date((string) $wo['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php // ========================== FILES ========================== ?>
        <?php elseif ($tab === 'files'): ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Photos and documents</h2>
                </div>
                <div class="card-body">
                    <?php View::partial('attachments', [
                        'attachments' => $data['attachments'],
                        'entityType'  => 'asset',
                        'entityId'    => $assetId,
                        'canUpload'   => can('assets.edit'),
                        'canDelete'   => can('assets.edit'),
                        'uploadUrl'   => url('asset-view.php', ['id' => $assetId, 'tab' => 'files']),
                    ]); ?>
                </div>
            </div>

        <?php // ========================== METER ========================== ?>
        <?php elseif ($tab === 'meter'): ?>

            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Meter history</h2>
                        <p class="card-subtitle">Every recorded reading, newest first</p>
                    </div>
                </div>
                <?php if (empty($data['readings'])): ?>
                    <?php View::partial('empty-state', ['icon' => 'gauge', 'title' => 'No readings recorded']); ?>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="table is-stacked">
                            <thead><tr><th>When</th><th class="is-numeric">Reading</th><th class="is-numeric">Change</th><th>Source</th><th>By</th></tr></thead>
                            <tbody>
                                <?php foreach ($data['readings'] as $reading): ?>
                                    <tr>
                                        <td data-label="When" class="is-row-title"><?= e(Dates::datetime((string) $reading['recorded_at'])) ?></td>
                                        <td data-label="Reading" class="is-numeric">
                                            <strong><?= e(decimal($reading['reading'])) ?></strong>
                                            <span class="text-subtle text-xs"><?= e((string) $asset['meter_type']) ?></span>
                                        </td>
                                        <td data-label="Change" class="is-numeric">
                                            <?php if ($reading['previous_reading'] !== null): ?>
                                                <?php $delta = (float) $reading['reading'] - (float) $reading['previous_reading']; ?>
                                                <span class="<?= $delta < 0 ? 'text-danger' : 'text-muted' ?>">
                                                    <?= $delta >= 0 ? '+' : '' ?><?= e(decimal($delta)) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-subtle">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Source"><?= e(App\Str::label((string) $reading['source'])) ?></td>
                                        <td data-label="By"><?php View::partial('user-chip', ['user' => $reading]); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php // ========================= HISTORY ========================= ?>
        <?php else: ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Change history</h2>
                </div>
                <?php if (empty($data['audit'])): ?>
                    <?php View::partial('empty-state', ['icon' => 'history', 'title' => 'No changes recorded']); ?>
                <?php else: ?>
                    <ul class="activity-list">
                        <?php foreach ($data['audit'] as $entry): ?>
                            <li class="activity-item">
                                <span class="status-dot tone-<?= e(App\Audit::actionTone((string) $entry['action'])) ?>" style="margin-top:7px"></span>
                                <span class="activity-body">
                                    <strong><?= e(App\Audit::actionLabel((string) $entry['action'])) ?></strong>
                                    <?php if (!empty($entry['description'])): ?>
                                        — <?= e((string) $entry['description']) ?>
                                    <?php endif; ?>
                                    <div class="text-sm text-muted">
                                        <?= e((string) $entry['user_name']) ?> &middot;
                                        <?= e(Dates::datetime((string) $entry['created_at'])) ?>
                                    </div>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        <?php endif; ?>
        </div>
    </div>

    <?php // ========================== SIDEBAR ========================== ?>
    <div>
        <?php if (!empty($asset['image_path'])): ?>
            <div class="card">
                <img class="asset-photo" style="border-radius:var(--radius-lg) var(--radius-lg) 0 0"
                     src="<?= e(url('file.php', ['asset_photo' => $assetId])) ?>"
                     alt="<?= attr((string) $asset['name']) ?>">
            </div>
        <?php endif; ?>

        <?php if ((string) $asset['meter_type'] !== 'none'): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= icon('gauge', '', 17) ?> Meter</h3>
                </div>
                <div class="card-body">
                    <?php View::partial('meter-widget', ['asset' => $asset]); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (can('assets.edit')): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Change status</h3>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= e(url('asset-view.php', ['id' => $assetId])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="status">
                        <input type="hidden" name="tab" value="<?= e($tab) ?>">

                        <?php View::partial('form-field', [
                            'name'    => 'status',
                            'label'   => '',
                            'type'    => 'select',
                            'value'   => (string) $asset['status'],
                            'options' => Status::options('asset'),
                            'noOld'   => true,
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'        => 'reason',
                            'label'       => 'Why? (optional)',
                            'type'        => 'text',
                            'value'       => '',
                            'placeholder' => 'Clutch replacement',
                            'noOld'       => true,
                            'attrs'       => ['maxlength' => 200],
                        ]); ?>

                        <button type="submit" class="btn btn-secondary btn-block">Update status</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Identification</h3>
            </div>
            <div class="card-body">
                <dl class="detail-list">
                    <dt>Asset tag</dt>
                    <dd>
                        <code><?= e((string) $asset['asset_tag']) ?></code>
                        <button type="button" class="btn btn-ghost btn-sm btn-icon no-print"
                                data-copy="<?= attr((string) $asset['asset_tag']) ?>"
                                aria-label="Copy asset tag" title="Copy"><?= icon('copy', '', 14) ?></button>
                    </dd>
                    <dt>Added</dt>
                    <dd><?= e(Dates::date((string) $asset['created_at'])) ?></dd>
                </dl>

                <a class="btn btn-secondary btn-block mt-3 no-print" href="<?= e(url('labels.php', ['id' => $assetId])) ?>">
                    <?= icon('qr-code', '', 16) ?> Print a label
                </a>
                <p class="form-hint">
                    Stick the label on the machine. Scanning it opens this page on a phone.
                </p>
            </div>
        </div>
    </div>
</div>
