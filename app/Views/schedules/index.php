<?php

use App\Dates;
use App\Status;
use App\View;

$assetOptions = [];

foreach ($assets as $asset) {
    $assetOptions[(int) $asset['id']] = (string) $asset['name'] . ' — ' . (string) $asset['asset_tag'];
}
?>

<div class="stat-grid">
    <?php View::partial('stat-card', [
        'label' => 'Overdue', 'value' => num($counts['overdue']),
        'icon' => 'alert-triangle', 'tone' => $counts['overdue'] > 0 ? 'danger' : 'ok',
        'href' => url('schedules.php', ['due' => 'overdue']),
        'sub' => $counts['overdue'] > 0 ? 'Needs attention' : 'Nothing overdue',
    ]); ?>
    <?php View::partial('stat-card', [
        'label' => 'Due in 30 days', 'value' => num($counts['due_soon']),
        'icon' => 'calendar', 'tone' => $counts['due_soon'] > 0 ? 'warn' : 'muted',
        'href' => url('schedules.php', ['due' => 'soon']),
    ]); ?>
    <?php View::partial('stat-card', [
        'label' => 'All schedules', 'value' => num(count($schedules)),
        'icon' => 'list', 'tone' => 'brand',
        'href' => url('schedules.php'),
    ]); ?>
</div>

<div class="card">
    <div class="table-toolbar">
        <div class="btn-group">
            <?php foreach (['all' => 'Everything', 'soon' => 'Due soon', 'overdue' => 'Overdue only'] as $key => $label): ?>
                <a class="btn btn-secondary btn-sm<?= $show === $key ? ' is-active' : '' ?>"
                   href="<?= e(url('schedules.php', ['due' => $key, 'asset_id' => $assetId ?: null])) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>

        <form method="get" action="<?= e(url('schedules.php')) ?>" class="flex gap-2 items-center">
            <input type="hidden" name="due" value="<?= e($show) ?>">
            <label class="sr-only" for="asset-filter">Filter by <?= e(asset_word()) ?></label>
            <select class="form-select" id="asset-filter" name="asset_id" data-autosubmit style="min-width:200px">
                <option value="">All <?= e(asset_word(true)) ?></option>
                <?php foreach ($assetOptions as $value => $label): ?>
                    <option value="<?= (int) $value ?>"<?= selected($value, $assetId) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($schedules === []): ?>
        <?php View::partial('empty-state', [
            'icon'        => 'calendar',
            'title'       => $show === 'all' ? 'No schedules set up' : 'Nothing ' . ($show === 'overdue' ? 'overdue' : 'due soon'),
            'message'     => $show === 'all'
                ? 'A schedule tells the system when a service is due, so it appears on the dashboard instead of being remembered.'
                : 'Good news.',
            'actionLabel' => $show === 'all' && can('schedules.manage') ? 'Add your first schedule' : '',
            'actionUrl'   => can('schedules.manage') ? url('schedule-edit.php') : '',
        ]); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table is-stacked">
                <thead>
                    <tr>
                        <th><?= e(asset_word(false, true)) ?></th><th>Job</th><th>Every</th><th>Last done</th>
                        <th>Next due</th><th>Assigned</th><th class="is-actions no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $schedule): ?>
                        <tr class="<?= $schedule['due_state'] === 'overdue' ? 'is-danger' : ($schedule['due_state'] === 'due_soon' || $schedule['due_state'] === 'due' ? 'is-warn' : '') ?>">
                            <td data-label="<?= attr(asset_word(false, true)) ?>" class="is-row-title">
                                <a href="<?= e(url('asset-view.php', ['id' => (int) $schedule['asset_id']])) ?>">
                                    <?= e((string) $schedule['asset_name']) ?>
                                </a>
                                <span class="cell-secondary"><?= e((string) $schedule['asset_tag']) ?></span>
                            </td>
                            <td data-label="Job">
                                <?= e((string) $schedule['name']) ?>
                                <?php if (!(int) $schedule['is_active']): ?>
                                    <span class="badge badge-muted">Paused</span>
                                <?php endif; ?>
                                <?php if (!empty($schedule['checklist_name'])): ?>
                                    <span class="cell-secondary"><?= icon('clipboard-check', '', 12) ?> <?= e((string) $schedule['checklist_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Every">
                                <?= e(Dates::describeInterval(
                                    (string) $schedule['frequency_type'],
                                    (int) $schedule['frequency_value'],
                                    $schedule['meter_interval'] === null ? null : (float) $schedule['meter_interval'],
                                    (string) ($schedule['meter_type'] ?? 'hours')
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
                                        <?= e((string) ($schedule['meter_type'] ?? '')) ?>
                                        <?php if (isset($schedule['meter_reading'])): ?>
                                            (now <?= e(decimal($schedule['meter_reading'])) ?>)
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Assigned">
                                <?= empty($schedule['assignee_first'])
                                    ? '<span class="text-subtle">Anyone</span>'
                                    : e(trim((string) $schedule['assignee_first'] . ' ' . (string) $schedule['assignee_last'])) ?>
                            </td>
                            <td class="is-actions no-print">
                                <?php if (can('logs.create')): ?>
                                    <a class="btn btn-secondary btn-sm"
                                       href="<?= e(url('log-edit.php', ['schedule_id' => (int) $schedule['id']])) ?>">Log it</a>
                                <?php endif; ?>
                                <?php if (can('schedules.manage')): ?>
                                    <a class="btn btn-ghost btn-sm btn-icon"
                                       href="<?= e(url('schedule-edit.php', ['id' => (int) $schedule['id']])) ?>"
                                       aria-label="Edit"><?= icon('edit', '', 15) ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
