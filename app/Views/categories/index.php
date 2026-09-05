<?php
/**
 * Categories and locations.
 *
 * Both are the same shape — a short list somebody adds to twice a year — so
 * they share a page with the form beside the list rather than a page of their
 * own each.
 */

use App\Status;
use App\View;

$isCategories = $tab === 'categories';
$rows         = $isCategories ? $categories : $locations;
$editingRow   = $editing;
$noun         = $isCategories ? 'category' : 'location';
?>

<?php View::partial('tabs', [
    'tabs' => [
        'categories' => ['label' => 'Categories', 'url' => url('categories.php', ['tab' => 'categories'])],
        'locations'  => ['label' => 'Locations',  'url' => url('categories.php', ['tab' => 'locations'])],
    ],
    'active' => $tab,
]); ?>

<div class="grid grid-sidebar">
    <div>
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">
                        <?= icon($isCategories ? 'tag' : 'map-pin', '', 18) ?>
                        <?= $isCategories ? 'Categories' : 'Locations' ?>
                    </h2>
                    <p class="card-subtitle">
                        <?= $isCategories
                            ? 'Go-karts, bumper boats, major rides — how the fleet is grouped.'
                            : 'The track, the arena, the workshop — where a machine lives.' ?>
                    </p>
                </div>
            </div>

            <?php if ($rows === []): ?>
                <div class="card-body">
                    <p class="text-muted" style="margin:0">
                        Nothing here yet. Add one with the form.
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table is-stacked">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <?php if ($isCategories): ?>
                                    <th>Meter</th>
                                <?php else: ?>
                                    <th>Building</th>
                                <?php endif; ?>
                                <th class="is-numeric">Machines</th>
                                <th class="is-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $rowId    = (int) $row['id'];
                                $isActive = (int) $row['is_active'] === 1;
                                ?>
                                <tr<?= $isActive ? '' : ' class="is-dimmed"' ?>>
                                    <td data-label="Name">
                                        <span class="cell-primary">
                                            <?php if ($isCategories): ?>
                                                <span class="swatch"
                                                      style="background:<?= e((string) $row['color']) ?>"
                                                      aria-hidden="true"></span>
                                            <?php endif; ?>
                                            <?= e((string) $row['name']) ?>
                                        </span>
                                        <?php if (!$isActive): ?>
                                            <span class="badge badge-muted">Switched off</span>
                                        <?php endif; ?>
                                        <?php if ((string) $row['description'] !== ''): ?>
                                            <span class="cell-secondary"><?= e((string) $row['description']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($isCategories): ?>
                                        <td data-label="Meter">
                                            <?= e(Status::label((string) $row['default_meter_type'], 'meter')) ?>
                                        </td>
                                    <?php else: ?>
                                        <td data-label="Building">
                                            <?= (string) $row['building'] !== ''
                                                ? e((string) $row['building'])
                                                : '<span class="text-subtle">&mdash;</span>' ?>
                                        </td>
                                    <?php endif; ?>
                                    <td data-label="Machines" class="is-numeric">
                                        <?php if ((int) $row['asset_count'] > 0): ?>
                                            <a href="<?= e(url('assets.php', [
                                                ($isCategories ? 'category_id' : 'location_id') => $rowId,
                                            ])) ?>"><?= (int) $row['asset_count'] ?></a>
                                        <?php else: ?>
                                            <span class="text-subtle">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="is-actions">
                                        <div class="flex gap-1 justify-end flex-wrap">
                                            <a class="btn btn-ghost btn-sm"
                                               href="<?= e(url('categories.php', ['tab' => $tab, 'edit' => $rowId])) ?>#edit-form">
                                                <?= icon('edit', '', 15) ?> Edit
                                            </a>

                                            <form method="post" action="<?= e(url('categories.php')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="kind" value="<?= e($tab) ?>">
                                                <input type="hidden" name="action" value="toggle">
                                                <input type="hidden" name="id" value="<?= $rowId ?>">
                                                <button type="submit" class="btn btn-ghost btn-sm">
                                                    <?= $isActive ? 'Switch off' : 'Switch on' ?>
                                                </button>
                                            </form>

                                            <?php if ((int) $row['asset_count'] === 0): ?>
                                                <form method="post" action="<?= e(url('categories.php')) ?>"
                                                      style="display:inline">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="kind" value="<?= e($tab) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $rowId ?>">
                                                    <button type="submit" class="btn btn-ghost btn-sm text-danger"
                                                            data-confirm="Delete “<?= attr((string) $row['name']) ?>”?"
                                                            data-confirm-title="Delete"
                                                            data-confirm-danger="1">
                                                        <?= icon('trash', '', 15) ?> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php // ==================== Add / edit ==================== ?>
    <div>
        <form method="post" action="<?= e(url('categories.php')) ?>" id="edit-form">
            <?= csrf_field() ?>
            <input type="hidden" name="kind" value="<?= e($tab) ?>">
            <?php if ($editingRow !== null): ?>
                <input type="hidden" name="id" value="<?= (int) $editingRow['id'] ?>">
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?= $editingRow !== null
                            ? 'Edit ' . e($noun)
                            : 'Add a ' . e($noun) ?>
                    </h3>
                </div>
                <div class="card-body">
                    <?php View::partial('form-field', [
                        'name'        => 'name',
                        'label'       => 'Name',
                        'value'       => $editingRow['name'] ?? '',
                        'required'    => true,
                        'noOld'       => true,
                        'placeholder' => $isCategories ? 'Go-Kart' : 'Main track',
                        'attrs'       => ['maxlength' => 120],
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'  => 'description',
                        'label' => 'Description',
                        'value' => $editingRow['description'] ?? '',
                        'noOld' => true,
                        'attrs' => ['maxlength' => 255],
                    ]); ?>

                    <?php if ($isCategories): ?>
                        <?php View::partial('form-field', [
                            'name'    => 'default_meter_type',
                            'label'   => 'Meter these usually have',
                            'type'    => 'select',
                            'value'   => $editingRow['default_meter_type'] ?? 'none',
                            'options' => $meterTypes,
                            'empty'   => null,
                            'noOld'   => true,
                            'hint'    => 'Fills in automatically when somebody adds one of these.',
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'  => 'color',
                            'label' => 'Colour',
                            'type'  => 'color',
                            'value' => $editingRow['color'] ?? '#4f46e5',
                            'noOld' => true,
                            'hint'  => 'Used on charts and badges.',
                        ]); ?>
                    <?php else: ?>
                        <?php View::partial('form-field', [
                            'name'        => 'building',
                            'label'       => 'Building',
                            'value'       => $editingRow['building'] ?? '',
                            'noOld'       => true,
                            'placeholder' => 'Outdoor',
                            'attrs'       => ['maxlength' => 120],
                        ]); ?>
                    <?php endif; ?>

                    <?php View::partial('form-field', [
                        'name'  => 'sort_order',
                        'label' => 'Order in the list',
                        'type'  => 'number',
                        'value' => $editingRow['sort_order'] ?? 0,
                        'noOld' => true,
                        'hint'  => 'Lowest first. Leave at 0 to sort by name.',
                        'attrs' => ['step' => 1, 'min' => 0, 'max' => 999],
                    ]); ?>

                    <label class="form-check" for="f_is_active">
                        <input type="checkbox" id="f_is_active" name="is_active" value="1"
                            <?= checked((int) ($editingRow['is_active'] ?? 1), 1) ?>>
                        <span class="form-check-label">
                            Offer this when adding a machine
                            <small>Untick to retire it without touching the machines that use it.</small>
                        </span>
                    </label>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary btn-block">
                        <?= icon('save', '', 17) ?>
                        <?= $editingRow !== null ? 'Save changes' : 'Add it' ?>
                    </button>
                    <?php if ($editingRow !== null): ?>
                        <a class="btn btn-ghost btn-block mt-2"
                           href="<?= e(url('categories.php', ['tab' => $tab])) ?>">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
