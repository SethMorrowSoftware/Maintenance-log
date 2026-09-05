<?php
/**
 * Maintenance log list.
 */

use App\Dates;
use App\Status;
use App\View;

$assetOptions = [];
$canSeeCosts  = costs_visible();

foreach ($assets as $asset) {
    $assetOptions[(int) $asset['id']] = (string) $asset['name'] . ' — ' . (string) $asset['asset_tag'];
}
?>

<?php // Totals for whatever is currently filtered, so the numbers answer the
      // question that was just asked rather than a fixed one. ?>
<div class="stat-grid">
    <?php View::partial('stat-card', [
        'label' => 'Jobs', 'value' => num($totals['count']), 'icon' => 'wrench', 'tone' => 'brand',
    ]); ?>
    <?php if ($canSeeCosts): ?>
        <?php View::partial('stat-card', [
            'label' => 'Total cost', 'value' => money($totals['cost']), 'icon' => 'dollar-sign', 'tone' => 'info',
        ]); ?>
    <?php endif; ?>
    <?php View::partial('stat-card', [
        'label' => 'Labour hours', 'value' => decimal($totals['hours'], 1), 'icon' => 'clock', 'tone' => 'muted',
    ]); ?>
    <?php View::partial('stat-card', [
        'label' => 'Downtime', 'value' => Dates::humanDuration($totals['downtime'], '0m'),
        'icon' => 'alert-triangle', 'tone' => $totals['downtime'] > 0 ? 'warn' : 'muted',
    ]); ?>
</div>

<div class="card">
    <div class="table-toolbar">
        <div class="btn-group">
            <?php foreach (['' => 'All time', 'last_7' => 'Last 7 days', 'last_30' => 'Last 30 days', 'this_month' => 'This month', 'this_year' => 'This year'] as $key => $label): ?>
                <a class="btn btn-secondary btn-sm<?= (string) $range === (string) $key ? ' is-active' : '' ?>"
                   href="<?= e(url('logs.php', array_merge(
                       array_diff_key($_GET, array_flip(['from', 'to', 'page'])),
                       ['range' => $key]
                   ))) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ((int) $filters['followup'] === 1): ?>
            <a class="btn btn-warn btn-sm" href="<?= e(url('logs.php', array_merge($_GET, ['followup' => null]))) ?>">
                Showing follow-ups only <?= icon('x', '', 14) ?>
            </a>
        <?php else: ?>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('logs.php', array_merge($_GET, ['followup' => 1]))) ?>">
                <?= icon('alert-circle', '', 15) ?> Needs follow-up
            </a>
        <?php endif; ?>
    </div>

    <?php View::partial('filter-bar', [
        'action'  => 'logs.php',
        'filters' => [
            'q'           => ['label' => 'Search', 'type' => 'text', 'value' => $filters['q'], 'placeholder' => 'Job, notes, ' . asset_word() . '…'],
            'asset_id'    => ['label' => asset_word(false, true), 'type' => 'select', 'value' => $filters['asset_id'], 'options' => $assetOptions, 'empty' => 'All ' . asset_word(true)],
            'category_id' => ['label' => 'Category', 'type' => 'select', 'value' => $filters['category_id'], 'options' => $categories, 'empty' => 'All'],
            'log_type'    => ['label' => 'Type', 'type' => 'select', 'value' => $filters['log_type'], 'options' => Status::options('log_type'), 'empty' => 'All types'],
            'user_id'     => ['label' => 'Technician', 'type' => 'select', 'value' => $filters['user_id'], 'options' => $technicians, 'empty' => 'Anyone'],
            'from'        => ['label' => 'From', 'type' => 'date', 'value' => $filters['from']],
            'to'          => ['label' => 'To', 'type' => 'date', 'value' => $filters['to']],
        ],
        'hidden'   => ['followup' => $filters['followup'] ?: ''],
        'resetUrl' => url('logs.php'),
    ]); ?>

    <?php if ($logs === []): ?>
        <?php View::partial('empty-state', [
            'icon'        => 'wrench',
            'title'       => $hasFilters ? 'No jobs match those filters' : 'No maintenance logged yet',
            'message'     => $hasFilters
                ? 'Try a wider date range, or clear the filters.'
                : 'Every time somebody works on a kart or a ride, record it here. It takes about twenty seconds.',
            'actionLabel' => $hasFilters ? 'Clear filters' : (can('logs.create') ? 'Log your first job' : ''),
            'actionUrl'   => $hasFilters ? url('logs.php') : (can('logs.create') ? url('log-edit.php') : ''),
            'actionIcon'  => $hasFilters ? 'x' : 'plus',
        ]); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table is-stacked">
                <thead>
                    <tr>
                        <th><?= sort_link('performed', 'When', $sort, $direction) ?></th>
                        <th><?= sort_link('asset', asset_word(false, true), $sort, $direction) ?></th>
                        <th><?= sort_link('title', 'Job', $sort, $direction) ?></th>
                        <th><?= sort_link('type', 'Type', $sort, $direction) ?></th>
                        <th><?= sort_link('user', 'Who', $sort, $direction) ?></th>
                        <?php if ($canSeeCosts): ?>
                            <th class="is-numeric"><?= sort_link('cost', 'Cost', $sort, $direction) ?></th>
                        <?php endif; ?>
                        <th class="is-actions no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr data-row-href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>">
                            <td data-label="When">
                                <?= e(Dates::date((string) $log['performed_at'])) ?>
                                <span class="cell-secondary"><?= e(Dates::time((string) $log['performed_at'])) ?></span>
                            </td>
                            <td data-label="<?= attr(asset_word(false, true)) ?>">
                                <a href="<?= e(url('asset-view.php', ['id' => (int) $log['asset_id']])) ?>">
                                    <?= e((string) $log['asset_name']) ?>
                                </a>
                                <span class="cell-secondary"><?= e((string) $log['asset_tag']) ?></span>
                            </td>
                            <td data-label="Job" class="is-row-title">
                                <a href="<?= e(url('log-view.php', ['id' => (int) $log['id']])) ?>">
                                    <?= e((string) $log['title']) ?>
                                </a>
                                <?php if ((int) $log['requires_followup'] === 1): ?>
                                    <span class="badge badge-warn">Follow-up</span>
                                <?php endif; ?>
                                <?php if ((int) $log['is_completed'] === 0): ?>
                                    <span class="badge badge-info">Unfinished</span>
                                <?php endif; ?>
                                <?php if ((int) $log['attachment_count'] > 0): ?>
                                    <span class="text-subtle text-xs">
                                        <?= icon('paperclip', '', 12) ?><?= (int) $log['attachment_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Type">
                                <?php View::partial('status-badge', ['value' => (string) $log['log_type'], 'vocabulary' => 'log_type']); ?>
                            </td>
                            <td data-label="Who"><?php View::partial('user-chip', ['user' => $log]); ?></td>
                            <?php if ($canSeeCosts): ?>
                                <td data-label="Cost" class="is-numeric"><?= e(money($log['total_cost'], true)) ?></td>
                            <?php endif; ?>
                            <td class="is-actions no-print">
                                <?php if (App\Acl::canEditLog($log)): ?>
                                    <a class="btn btn-ghost btn-sm btn-icon"
                                       href="<?= e(url('log-edit.php', ['id' => (int) $log['id']])) ?>"
                                       aria-label="Edit" title="Edit"><?= icon('edit', '', 15) ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php View::partial('pagination', ['paginator' => $paginator, 'singular' => 'job', 'plural' => 'jobs']); ?>
</div>
