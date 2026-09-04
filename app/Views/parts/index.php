<?php
/**
 * The parts shelf.
 *
 * The thing a mechanic wants from this page is "have we got one?", so stock on
 * hand is the loudest column and the "took one" control is right there on the
 * row instead of two clicks away.
 */

use App\Status;
use App\View;

$canAdjust = can('parts.adjust');
$canManage = can('parts.manage');
?>

<div class="stat-grid mb-5">
    <?php View::partial('stat-card', [
        'label' => 'Parts on the list',
        'value' => num($summary['count']),
        'icon'  => 'package',
        'tone'  => 'info',
    ]); ?>
    <?php View::partial('stat-card', [
        'label' => 'Value on the shelf',
        'value' => money($summary['value']),
        'icon'  => 'dollar-sign',
        'tone'  => 'muted',
    ]); ?>
    <?php View::partial('stat-card', [
        'label' => 'Running low',
        'value' => num($summary['low']),
        'icon'  => 'alert-triangle',
        'tone'  => $summary['low'] > 0 ? 'warn' : 'ok',
        'href'  => url('parts.php', ['stock' => 'low']),
    ]); ?>
    <?php View::partial('stat-card', [
        'label' => 'Out of stock',
        'value' => num($summary['out_of_stock']),
        'icon'  => 'x',
        'tone'  => $summary['out_of_stock'] > 0 ? 'danger' : 'ok',
        'href'  => url('parts.php', ['stock' => 'out']),
    ]); ?>
</div>

<?php View::partial('filter-bar', [
    'action'  => 'parts.php',
    'filters' => [
        'q' => [
            'label'       => 'Search',
            'value'       => $filters['q'],
            'placeholder' => 'Name, part number, supplier…',
        ],
        'category' => [
            'label'   => 'Category',
            'type'    => 'select',
            'value'   => $filters['category'],
            'options' => $categories,
        ],
        'stock' => [
            'label'   => 'Stock',
            'type'    => 'select',
            'value'   => $filters['stock'],
            'options' => [
                'in'  => 'In stock',
                'low' => 'Running low',
                'out' => 'Out of stock',
            ],
            'empty'   => 'Any',
        ],
        'active' => [
            'label'   => 'Show',
            'type'    => 'select',
            'value'   => $filters['active'],
            'options' => ['1' => 'In use', 'all' => 'Including retired'],
            'empty'   => null,
        ],
    ],
]); ?>

<?php if ($parts === []): ?>
    <?php View::partial('empty-state', [
        'icon'        => 'package',
        'title'       => ($filters['q'] !== '' || $filters['category'] !== '' || $filters['stock'] !== '')
                            ? 'Nothing matches that'
                            : 'No parts on the list yet',
        'message'     => ($filters['q'] !== '' || $filters['category'] !== '' || $filters['stock'] !== '')
                            ? 'Try a shorter search, or clear the filters.'
                            : 'Add the things you keep on the shelf — brake pads, chains, belts, filters. '
                              . 'Once they are here, using one on a job takes it off the count by itself.',
        'actionLabel' => $canManage ? 'Add a part' : '',
        'actionUrl'   => $canManage ? url('part-edit.php') : '',
    ]); ?>
<?php else: ?>
    <div class="card">
        <div class="table-wrap">
            <table class="table is-stacked table-sortable">
                <thead>
                    <tr>
                        <th><?= sort_link('name', 'Part', $sort, $direction) ?></th>
                        <th><?= sort_link('number', 'Part number', $sort, $direction) ?></th>
                        <th class="is-numeric"><?= sort_link('stock', 'On hand', $sort, $direction) ?></th>
                        <th class="is-numeric"><?= sort_link('cost', 'Each', $sort, $direction) ?></th>
                        <th>Where</th>
                        <?php if ($canAdjust): ?>
                            <th class="is-actions">Took / put back</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parts as $part): ?>
                        <?php
                        $partId   = (int) $part['id'];
                        $onHand   = (float) $part['quantity_on_hand'];
                        $level    = (float) $part['reorder_level'];
                        $state    = Status::stockState($part);
                        $unit     = (string) $part['unit_of_measure'];
                        ?>
                        <tr>
                            <td data-label="Part">
                                <a class="cell-primary" href="<?= e(url('part-view.php', ['id' => $partId])) ?>">
                                    <?= e((string) $part['name']) ?>
                                </a>
                                <?php if ((string) $part['category'] !== ''): ?>
                                    <span class="cell-secondary"><?= e((string) $part['category']) ?></span>
                                <?php endif; ?>
                                <?php if ((int) $part['is_active'] === 0): ?>
                                    <span class="badge badge-muted">Retired</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Part number">
                                <code><?= e((string) $part['part_number']) ?></code>
                                <?php if ((string) $part['supplier'] !== ''): ?>
                                    <span class="cell-secondary"><?= e((string) $part['supplier']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="On hand" class="is-numeric">
                                <span class="stock-count tone-<?= e($state) ?>">
                                    <?= e(decimal($onHand)) ?>
                                </span>
                                <span class="cell-secondary"><?= e($unit) ?></span>
                                <?php if ($state === 'low'): ?>
                                    <span class="cell-secondary text-warn">
                                        reorder at <?= e(decimal($level)) ?>
                                    </span>
                                <?php elseif ($state === 'out'): ?>
                                    <span class="cell-secondary text-danger">none left</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Each" class="is-numeric"><?= e(money($part['unit_cost'])) ?></td>
                            <td data-label="Where">
                                <?= (string) $part['location_bin'] !== ''
                                    ? e((string) $part['location_bin'])
                                    : '<span class="text-subtle">&mdash;</span>' ?>
                            </td>
                            <?php if ($canAdjust): ?>
                                <td class="is-actions">
                                    <form method="post" action="<?= e(url('parts.php', $_GET)) ?>"
                                          class="stock-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="adjust">
                                        <input type="hidden" name="id" value="<?= $partId ?>">
                                        <input type="number" step="0.01" min="0.01" class="form-input"
                                               name="amount" placeholder="0"
                                               inputmode="decimal"
                                               aria-label="How many <?= attr((string) $part['name']) ?>">
                                        <button type="submit" name="way" value="out"
                                                class="btn btn-secondary btn-sm"
                                                title="Take some off the shelf">Took</button>
                                        <button type="submit" name="way" value="in"
                                                class="btn btn-ghost btn-sm"
                                                title="Put some back">Back</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php View::partial('pagination', ['paginator' => $paginator, 'singular' => 'part', 'plural' => 'parts']); ?>
<?php endif; ?>
