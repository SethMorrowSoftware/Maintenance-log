<?php
/**
 * Machine list.
 *
 * Two views: a table for a desk, and cards for a phone. The status strip at
 * the top answers "what's down?" without anyone having to build a filter.
 */

use App\Dates;
use App\Status;
use App\View;

$showMeters     = feature_on('meters');
$showWorkOrders = feature_on('work_orders');
$listFields     = \App\CustomFields::inList();

// Tick several machines on the table and move them together.
$canBulk = $view === 'table' && can('assets.edit') && $assets !== [];

$statusTabs = [
    ''               => ['label' => 'Active',         'count' => array_sum(array_intersect_key($statusCounts, array_flip(['in_service','maintenance','out_of_service'])))],
    'in_service'     => ['label' => 'In service',     'count' => (int) ($statusCounts['in_service'] ?? 0)],
    'down'           => ['label' => 'Down',           'count' => (int) ($statusCounts['maintenance'] ?? 0) + (int) ($statusCounts['out_of_service'] ?? 0)],
    'retired'        => ['label' => 'Retired',        'count' => (int) ($statusCounts['retired'] ?? 0)],
    'all'            => ['label' => 'All',            'count' => array_sum($statusCounts)],
];
?>

<div class="card">

    <?php // ---- Status strip: the question people actually ask ---- ?>
    <div class="table-toolbar">
        <div class="btn-group" role="tablist">
            <?php foreach ($statusTabs as $value => $tab): ?>
                <?php $isActive = (string) $filters['status'] === (string) $value; ?>
                <a class="btn btn-secondary btn-sm<?= $isActive ? ' is-active' : '' ?>"
                   href="<?= e(url('assets.php', array_merge($_GET, ['status' => $value, 'page' => null]))) ?>">
                    <?= e($tab['label']) ?>
                    <span class="badge badge-muted" style="margin-left:4px"><?= (int) $tab['count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="flex gap-2 items-center">
            <div class="btn-group no-print">
                <a class="btn btn-secondary btn-sm<?= $view === 'table' ? ' is-active' : '' ?>"
                   href="<?= e(url('assets.php', array_merge($_GET, ['view' => 'table']))) ?>"
                   aria-label="Table view" title="Table view"><?= icon('list', '', 15) ?></a>
                <a class="btn btn-secondary btn-sm<?= $view === 'cards' ? ' is-active' : '' ?>"
                   href="<?= e(url('assets.php', array_merge($_GET, ['view' => 'cards']))) ?>"
                   aria-label="Card view" title="Card view"><?= icon('grid', '', 15) ?></a>
            </div>
        </div>
    </div>

    <?php
    View::partial('filter-bar', [
        'action'  => 'assets.php',
        'filters' => [
            'q' => [
                'label'       => 'Search',
                'type'        => 'text',
                'value'       => $filters['q'],
                'placeholder' => 'Name, tag, serial…',
            ],
            'category_id' => [
                'label'   => 'Category',
                'type'    => 'select',
                'value'   => $filters['category_id'],
                'options' => $categories,
                'empty'   => 'All categories',
            ],
            'location_id' => [
                'label'   => 'Location',
                'type'    => 'select',
                'value'   => $filters['location_id'],
                'options' => $locations,
                'empty'   => 'All locations',
            ],
            'criticality' => [
                'label'   => 'Importance',
                'type'    => 'select',
                'value'   => $filters['criticality'],
                'options' => Status::options('criticality'),
                'empty'   => 'Any',
            ],
        ],
        'hidden'   => ['status' => $filters['status'], 'view' => $view],
        'resetUrl' => url('assets.php', ['status' => $filters['status'], 'view' => $view]),
    ]);
    ?>

    <?php if ($assets === []): ?>

        <?php View::partial('empty-state', [
            'icon'        => 'assets',
            'title'       => $hasFilters ? 'No ' . asset_word(true) . ' match those filters' : 'No ' . asset_word(true) . ' yet',
            'message'     => $hasFilters
                ? 'Try widening the search, or clear the filters to see everything.'
                : 'Add your karts, rides and ' . asset_word(true) . ' so their maintenance can be recorded against them.',
            'actionLabel' => $hasFilters ? 'Clear filters' : (can('assets.create') ? 'Add your first ' . asset_word() : ''),
            'actionUrl'   => $hasFilters ? url('assets.php') : (can('assets.create') ? url('asset-edit.php') : ''),
            'actionIcon'  => $hasFilters ? 'x' : 'plus',
        ]); ?>

    <?php elseif ($view === 'cards'): ?>

        <div class="card-body">
            <div class="grid grid-3">
                <?php foreach ($assets as $asset): ?>
                    <a class="card" style="margin:0;display:block"
                       href="<?= e(url('asset-view.php', ['id' => (int) $asset['id']])) ?>">
                        <div class="card-body">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <span class="stat-icon" style="width:34px;height:34px;background:<?= e((string) ($asset['category_color'] ?? '#4f46e5')) ?>1a;color:<?= e((string) ($asset['category_color'] ?? '#4f46e5')) ?>">
                                    <?= icon((string) ($asset['category_icon'] ?? 'tool'), '', 18) ?>
                                </span>
                                <?php View::partial('status-badge', ['value' => (string) $asset['status'], 'vocabulary' => 'asset']); ?>
                            </div>
                            <strong style="display:block"><?= e((string) $asset['name']) ?></strong>
                            <span class="text-sm text-muted"><?= e((string) $asset['asset_tag']) ?></span>
                            <div class="text-sm text-subtle mt-2">
                                <?= e((string) ($asset['category_name'] ?? 'Uncategorised')) ?>
                                <?php if (!empty($asset['location_name'])): ?>
                                    &middot; <?= e((string) $asset['location_name']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($showMeters && (string) $asset['meter_type'] !== 'none'): ?>
                                <div class="text-sm mt-2">
                                    <?= icon('gauge', '', 14) ?>
                                    <?= e(decimal($asset['meter_reading'])) ?> <?= e((string) $asset['meter_type']) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($showWorkOrders && (int) $asset['open_work_orders'] > 0): ?>
                                <div class="mt-2">
                                    <span class="badge badge-warn">
                                        <?= (int) $asset['open_work_orders'] ?> open work order<?= (int) $asset['open_work_orders'] === 1 ? '' : 's' ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>

        <?php if ($canBulk): ?>
            <?php // The bar lives outside the table; the row checkboxes belong to it by id. ?>
            <form method="post" action="<?= e(url('assets.php', $_GET)) ?>" id="bulk-bar" class="bulk-bar no-print">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="bulk_move">
                <span class="bulk-count"><strong data-bulk-count>0</strong> ticked. Move them to:</span>
                <label class="bulk-field">
                    <span class="sr-only">Category</span>
                    <select class="form-select" name="category_id" aria-label="Category">
                        <option value="">Same category</option>
                        <option value="none">No category</option>
                        <?php foreach ($categories as $catId => $catName): ?>
                            <option value="<?= (int) $catId ?>"><?= e((string) $catName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="bulk-field">
                    <span class="sr-only">Location</span>
                    <select class="form-select" name="location_id" aria-label="Location">
                        <option value="">Same location</option>
                        <option value="none">No location</option>
                        <?php foreach ($locations as $locId => $locName): ?>
                            <option value="<?= (int) $locId ?>"><?= e((string) $locName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary btn-sm"
                        data-confirm="Move the ticked <?= attr(asset_word(true)) ?>? Their history comes with them."
                        data-confirm-title="Move <?= attr(asset_word(true)) ?>"
                        data-confirm-text="Move them">
                    <?= icon('arrow-right', '', 15) ?> Move
                </button>
            </form>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="table is-stacked table-sortable">
                <thead>
                    <tr>
                        <?php if ($canBulk): ?>
                            <th class="is-check no-print">
                                <input type="checkbox" data-bulk-all data-bulk-bar="#bulk-bar" aria-label="Tick every <?= attr(asset_word()) ?> on this page">
                            </th>
                        <?php endif; ?>
                        <th data-sort><?= sort_link('name', asset_word(false, true), $sort, $direction) ?></th>
                        <th data-sort><?= sort_link('category', 'Category', $sort, $direction) ?></th>
                        <th data-sort><?= sort_link('location', 'Location', $sort, $direction) ?></th>
                        <th data-sort><?= sort_link('status', 'Status', $sort, $direction) ?></th>
                        <?php foreach ($listFields as $field): ?>
                            <th<?= $field['type'] === 'number' ? ' class="is-numeric"' : '' ?>><?= e((string) $field['label']) ?></th>
                        <?php endforeach; ?>
                        <?php if ($showMeters): ?>
                            <th class="is-numeric" data-sort><?= sort_link('meter', 'Meter', $sort, $direction) ?></th>
                        <?php endif; ?>
                        <th data-sort><?= sort_link('last_service', 'Last service', $sort, $direction) ?></th>
                        <th class="is-actions no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr data-row-href="<?= e(url('asset-view.php', ['id' => (int) $asset['id']])) ?>">
                            <?php if ($canBulk): ?>
                                <td class="is-check no-print">
                                    <input type="checkbox" name="ids[]" value="<?= (int) $asset['id'] ?>" form="bulk-bar" data-bulk-item
                                           aria-label="Tick <?= attr((string) $asset['name']) ?>">
                                </td>
                            <?php endif; ?>
                            <td data-label="<?= attr(asset_word(false, true)) ?>" class="is-row-title">
                                <a href="<?= e(url('asset-view.php', ['id' => (int) $asset['id']])) ?>" class="cell-primary">
                                    <?= e((string) $asset['name']) ?>
                                </a>
                                <span class="cell-secondary">
                                    <?= e((string) $asset['asset_tag']) ?>
                                    <?php if ($showWorkOrders && (int) $asset['open_work_orders'] > 0): ?>
                                        &middot; <span style="color:var(--warn)"><?= (int) $asset['open_work_orders'] ?> open</span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td data-label="Category"><?= e((string) ($asset['category_name'] ?? '—')) ?></td>
                            <td data-label="Location"><?= e((string) ($asset['location_name'] ?? '—')) ?></td>
                            <td data-label="Status">
                                <?php View::partial('status-badge', ['value' => (string) $asset['status'], 'vocabulary' => 'asset']); ?>
                            </td>
                            <?php foreach ($listFields as $field): ?>
                                <?php $customValue = \App\CustomFields::valueOn($field, $asset); ?>
                                <td data-label="<?= attr((string) $field['label']) ?>"<?= $field['type'] === 'number' ? ' class="is-numeric"' : '' ?>>
                                    <?php if ($customValue === ''): ?>
                                        <span class="text-subtle">—</span>
                                    <?php else: ?>
                                        <?= e($customValue) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <?php if ($showMeters): ?>
                                <td data-label="Meter" class="is-numeric"
                                    data-value="<?= e((string) $asset['meter_reading']) ?>">
                                    <?php if ((string) $asset['meter_type'] === 'none'): ?>
                                        <span class="text-subtle">—</span>
                                    <?php else: ?>
                                        <?= e(decimal($asset['meter_reading'])) ?>
                                        <span class="text-subtle text-xs"><?= e((string) $asset['meter_type']) ?></span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td data-label="Last service"
                                data-value="<?= e((string) ($asset['last_service'] ?? '')) ?>">
                                <?php if (!empty($asset['last_service'])): ?>
                                    <?= e(Dates::date((string) $asset['last_service'])) ?>
                                    <span class="cell-secondary"><?= e(Dates::ago((string) $asset['last_service'])) ?></span>
                                <?php else: ?>
                                    <span class="text-subtle">Never</span>
                                <?php endif; ?>
                            </td>
                            <td class="is-actions no-print">
                                <?php if (can('logs.create')): ?>
                                    <a class="btn btn-secondary btn-sm"
                                       href="<?= e(url('log-edit.php', ['asset_id' => (int) $asset['id']])) ?>"
                                       title="Log maintenance on this <?= attr(asset_word()) ?>">
                                        <?= icon('wrench', '', 15) ?> Log
                                    </a>
                                <?php endif; ?>
                                <?php if (can('assets.edit')): ?>
                                    <a class="btn btn-ghost btn-sm btn-icon"
                                       href="<?= e(url('asset-edit.php', ['id' => (int) $asset['id']])) ?>"
                                       aria-label="Edit <?= attr((string) $asset['name']) ?>" title="Edit">
                                        <?= icon('edit', '', 15) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

    <?php View::partial('pagination', ['paginator' => $paginator, 'singular' => 'asset']); ?>
</div>
