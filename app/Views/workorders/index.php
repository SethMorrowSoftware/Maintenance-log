<?php

use App\Dates;
use App\Status;
use App\View;

$open = 0;

foreach (['open', 'assigned', 'in_progress', 'on_hold'] as $key) {
    $open += (int) ($statusCounts[$key] ?? 0);
}
?>

<div class="card">
    <div class="table-toolbar">
        <div class="btn-group">
            <?php
            $strip = [
                ''            => ['label' => 'Open',        'count' => $open],
                'open'        => ['label' => 'Unstarted',   'count' => (int) ($statusCounts['open'] ?? 0)],
                'in_progress' => ['label' => 'In progress', 'count' => (int) ($statusCounts['in_progress'] ?? 0)],
                'on_hold'     => ['label' => 'On hold',     'count' => (int) ($statusCounts['on_hold'] ?? 0)],
                'closed'      => ['label' => 'Closed',      'count' => (int) ($statusCounts['completed'] ?? 0) + (int) ($statusCounts['cancelled'] ?? 0)],
                'all'         => ['label' => 'All',         'count' => array_sum($statusCounts)],
            ];
            ?>
            <?php foreach ($strip as $value => $tab): ?>
                <a class="btn btn-secondary btn-sm<?= (string) $filters['status'] === (string) $value ? ' is-active' : '' ?>"
                   href="<?= e(url('workorders.php', array_merge($_GET, ['status' => $value, 'page' => null]))) ?>">
                    <?= e($tab['label']) ?>
                    <span class="badge badge-muted" style="margin-left:4px"><?= (int) $tab['count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="flex gap-2">
            <a class="btn btn-ghost btn-sm<?= (int) $filters['unassigned'] === 1 ? ' is-active' : '' ?>"
               href="<?= e(url('workorders.php', array_merge($_GET, ['unassigned' => (int) $filters['unassigned'] === 1 ? null : 1]))) ?>">
                Unassigned
            </a>
            <a class="btn btn-ghost btn-sm<?= (int) $filters['overdue'] === 1 ? ' is-active' : '' ?>"
               href="<?= e(url('workorders.php', array_merge($_GET, ['overdue' => (int) $filters['overdue'] === 1 ? null : 1]))) ?>">
                Overdue
            </a>
        </div>
    </div>

    <?php View::partial('filter-bar', [
        'action'  => 'workorders.php',
        'filters' => [
            'q'           => ['label' => 'Search', 'type' => 'text', 'value' => $filters['q'], 'placeholder' => 'Title, number, ' . asset_word() . '…'],
            'asset_id'    => ['label' => asset_word(false, true), 'type' => 'select', 'value' => $filters['asset_id'], 'options' => $assets, 'empty' => 'All ' . asset_word(true)],
            'priority'    => ['label' => 'Priority', 'type' => 'select', 'value' => $filters['priority'], 'options' => Status::options('priority'), 'empty' => 'Any'],
            'assigned_to' => ['label' => 'Assigned to', 'type' => 'select', 'value' => $filters['assigned_to'], 'options' => $assignees, 'empty' => 'Anyone'],
        ],
        'hidden'   => ['status' => $filters['status']],
        'resetUrl' => url('workorders.php', ['status' => $filters['status']]),
    ]); ?>

    <?php if ($orders === []): ?>
        <?php View::partial('empty-state', [
            'icon'        => 'work-order',
            'title'       => $hasFilters ? 'Nothing matches those filters' : 'No open work orders',
            'message'     => $hasFilters
                ? 'Try clearing the filters.'
                : 'When somebody spots a problem with a kart or a ride, report it here so it does not get forgotten.',
            'actionLabel' => $hasFilters ? 'Clear filters' : (can('workorders.create') ? 'Report an issue' : ''),
            'actionUrl'   => $hasFilters ? url('workorders.php') : (can('workorders.create') ? url('workorder-edit.php') : ''),
            'actionIcon'  => $hasFilters ? 'x' : 'plus',
        ]); ?>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table is-stacked">
                <thead>
                    <tr>
                        <th><?= sort_link('title', 'Problem', $sort, $direction) ?></th>
                        <th><?= sort_link('asset', asset_word(false, true), $sort, $direction) ?></th>
                        <th><?= sort_link('priority', 'Priority', $sort, $direction) ?></th>
                        <th><?= sort_link('status', 'Status', $sort, $direction) ?></th>
                        <th><?= sort_link('assignee', 'Assigned', $sort, $direction) ?></th>
                        <th><?= sort_link('due', 'Due', $sort, $direction) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $wo): ?>
                        <?php
                        $overdue = !empty($wo['due_date'])
                            && Dates::isPast((string) $wo['due_date'])
                            && !Status::isClosedWorkOrder((string) $wo['status']);
                        ?>
                        <tr class="<?= (string) $wo['priority'] === 'urgent' && !Status::isClosedWorkOrder((string) $wo['status']) ? 'is-warn' : '' ?>"
                            data-row-href="<?= e(url('workorder-view.php', ['id' => (int) $wo['id']])) ?>">
                            <td data-label="Problem" class="is-row-title">
                                <a href="<?= e(url('workorder-view.php', ['id' => (int) $wo['id']])) ?>">
                                    <?= e((string) $wo['title']) ?>
                                </a>
                                <span class="cell-secondary">
                                    <?= e((string) $wo['wo_number']) ?>
                                    <?php if ((int) $wo['comment_count'] > 0): ?>
                                        &middot; <?= (int) $wo['comment_count'] ?> comment<?= (int) $wo['comment_count'] === 1 ? '' : 's' ?>
                                    <?php endif; ?>
                                    <?php if ((int) $wo['is_safety_issue'] === 1): ?>
                                        &middot; <span style="color:var(--danger)">Safety</span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="<?= attr(asset_word(false, true)) ?>">
                                <?php if (!empty($wo['asset_id'])): ?>
                                    <a href="<?= e(url('asset-view.php', ['id' => (int) $wo['asset_id']])) ?>">
                                        <?= e((string) $wo['asset_name']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-subtle">Not linked</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Priority">
                                <?php View::partial('status-badge', ['value' => (string) $wo['priority'], 'vocabulary' => 'priority']); ?>
                            </td>
                            <td data-label="Status">
                                <?php View::partial('status-badge', ['value' => (string) $wo['status'], 'vocabulary' => 'workorder']); ?>
                            </td>
                            <td data-label="Assigned">
                                <?php if (!empty($wo['assignee_id'])): ?>
                                    <?php View::partial('user-chip', ['user' => $wo]); ?>
                                <?php else: ?>
                                    <span class="text-subtle">Nobody yet</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Due">
                                <?php if (!empty($wo['due_date'])): ?>
                                    <span class="<?= $overdue ? 'text-danger font-semi' : '' ?>">
                                        <?= e(Dates::dateOnly((string) $wo['due_date'])) ?>
                                    </span>
                                    <?php if ($overdue): ?><span class="cell-secondary">Overdue</span><?php endif; ?>
                                <?php else: ?>
                                    <span class="text-subtle">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php View::partial('pagination', ['paginator' => $paginator, 'singular' => 'work order']); ?>
</div>
