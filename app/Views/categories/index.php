<?php
/**
 * Categories and locations.
 *
 * Both are the same shape — a short list somebody adds to twice a year — so
 * they share a page with the form beside the list rather than a page of their
 * own each. Every row can be moved, renamed, switched off, merged into another
 * or deleted; nothing on this page can lose a machine.
 */

use App\Status;
use App\View;

$isCategories = $tab === 'categories';
$rows         = $isCategories ? $categories : $locations;
$editingRow   = $editing;
$noun         = $isCategories ? 'category' : 'location';
$iconChoices  = $iconChoices ?? [];
$last         = count($rows) - 1;
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
                            ? 'Go-karts, bumper boats, major rides — how the fleet is grouped. Rename, reorder, recolour or merge them to match your site.'
                            : 'The track, the arena, the workshop — where ' . an_asset() . ' lives. Rename, reorder or merge them to match your site.' ?>
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
                                <th class="is-numeric"><?= e(asset_word(true, true)) ?></th>
                                <th class="is-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $index => $row): ?>
                                <?php
                                $rowId    = (int) $row['id'];
                                $isActive = (int) $row['is_active'] === 1;
                                $count    = (int) $row['asset_count'];
                                $others   = array_filter($rows, static function (array $other) use ($rowId): bool {
                                    return (int) $other['id'] !== $rowId;
                                });
                                ?>
                                <tr<?= $isActive ? '' : ' class="is-dimmed"' ?> id="row-<?= $rowId ?>">
                                    <td data-label="Name" class="is-row-title">
                                        <span class="cell-primary">
                                            <?php if ($isCategories): ?>
                                                <span class="cat-mark" style="background:<?= e((string) $row['color']) ?>1f;color:<?= e((string) $row['color']) ?>" aria-hidden="true">
                                                    <?= icon((string) ($row['icon'] ?: 'tool'), '', 15) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?= e((string) $row['name']) ?>
                                        </span>
                                        <?php if (!$isActive): ?>
                                            <span class="badge badge-muted">Switched off</span>
                                        <?php endif; ?>
                                        <?php if ((string) $row['description'] !== ''): ?>
                                            <span class="cell-secondary"><?= e((string) $row['description']) ?></span>
                                        <?php endif; ?>
                                        <?php
                                        // The small print: meter type and checklist use for a
                                        // category, the building for a location.
                                        $meta = [];

                                        if ($isCategories) {
                                            if ((string) $row['default_meter_type'] !== 'none') {
                                                $meta[] = e(Status::label((string) $row['default_meter_type'], 'meter')) . ' meter';
                                            }

                                            $checklists = (int) ($row['checklist_count'] ?? 0);

                                            if ($checklists > 0) {
                                                $meta[] = $checklists . ($checklists === 1 ? ' checklist uses it' : ' checklists use it');
                                            }
                                        } elseif ((string) $row['building'] !== '') {
                                            $meta[] = e((string) $row['building']);
                                        }
                                        ?>
                                        <?php if ($meta !== []): ?>
                                            <span class="cell-secondary text-subtle"><?= implode(' &middot; ', $meta) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="<?= attr(asset_word(true, true)) ?>" class="is-numeric">
                                        <?php if ($count > 0): ?>
                                            <a class="count-link" href="<?= e(url('assets.php', [
                                                ($isCategories ? 'category_id' : 'location_id') => $rowId,
                                            ])) ?>" title="See them"><?= $count ?></a>
                                        <?php else: ?>
                                            <span class="text-subtle">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="is-actions">
                                        <div class="row-actions flex gap-1 justify-end items-center">
                                            <?php // Up and down, one place at a time. ?>
                                            <form method="post" action="<?= e(url('categories.php')) ?>" class="btn-group">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="kind" value="<?= e($tab) ?>">
                                                <input type="hidden" name="action" value="move">
                                                <input type="hidden" name="id" value="<?= $rowId ?>">
                                                <button type="submit" name="dir" value="up" class="btn btn-ghost btn-sm btn-icon"
                                                        <?= $index === 0 ? 'disabled' : '' ?>
                                                        aria-label="Move up" title="Move up"><?= icon('arrow-up', '', 15) ?></button>
                                                <button type="submit" name="dir" value="down" class="btn btn-ghost btn-sm btn-icon"
                                                        <?= $index === $last ? 'disabled' : '' ?>
                                                        aria-label="Move down" title="Move down"><?= icon('arrow-down', '', 15) ?></button>
                                            </form>

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

                                            <?php if ($count === 0): ?>
                                                <form method="post" action="<?= e(url('categories.php')) ?>" style="display:inline">
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
                                            <?php elseif ($others !== []): ?>
                                                <?php // In use: its machines have to go somewhere first, in one move. ?>
                                                <details class="merge-inline">
                                                    <summary class="btn btn-ghost btn-sm text-danger">
                                                        <?= icon('trash', '', 15) ?> Delete…
                                                    </summary>
                                                    <form method="post" action="<?= e(url('categories.php')) ?>" class="merge-form">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="kind" value="<?= e($tab) ?>">
                                                        <input type="hidden" name="action" value="merge">
                                                        <input type="hidden" name="id" value="<?= $rowId ?>">
                                                        <label class="form-label" for="merge-<?= $rowId ?>">
                                                            Move its <?= $count ?> <?= e($count === 1 ? asset_word() : asset_word(true)) ?> to
                                                        </label>
                                                        <select class="form-select" name="target_id" id="merge-<?= $rowId ?>" required>
                                                            <option value="">Choose…</option>
                                                            <?php foreach ($others as $other): ?>
                                                                <option value="<?= (int) $other['id'] ?>"><?= e((string) $other['name']) ?><?= (int) $other['is_active'] === 1 ? '' : ' (switched off)' ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-danger-outline btn-sm"
                                                                data-confirm="Move every <?= attr(asset_word()) ?> in “<?= attr((string) $row['name']) ?>” to the one you chose, then delete “<?= attr((string) $row['name']) ?>”? Their history is kept."
                                                                data-confirm-title="Move and delete"
                                                                data-confirm-text="Move and delete"
                                                                data-confirm-danger="1">
                                                            Move and delete
                                                        </button>
                                                    </form>
                                                </details>
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
                        <div class="form-group">
                            <label class="form-label">Icon</label>
                            <div class="icon-picker" role="radiogroup" aria-label="Icon">
                                <?php $currentIcon = (string) ($editingRow['icon'] ?? 'tool'); ?>
                                <?php foreach ($iconChoices as $choice): ?>
                                    <label class="icon-choice" title="<?= attr(ucwords(str_replace('-', ' ', $choice))) ?>">
                                        <input type="radio" name="icon" value="<?= attr($choice) ?>"
                                            <?= checked($currentIcon, $choice) ?>>
                                        <?= icon($choice, '', 20) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="form-hint">Shown beside the name on cards and on the machine list.</p>
                        </div>

                        <?php View::partial('form-field', [
                            'name'  => 'color',
                            'label' => 'Colour',
                            'type'  => 'color',
                            'value' => $editingRow['color'] ?? '#4f46e5',
                            'noOld' => true,
                            'hint'  => 'Used on charts and badges.',
                        ]); ?>

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
                        'name'        => 'sort_order',
                        'label'       => 'Order in the list',
                        'type'        => 'number',
                        'value'       => $editingRow['sort_order'] ?? '',
                        'noOld'       => true,
                        'placeholder' => $editingRow !== null ? '' : 'At the end',
                        'hint'        => 'Lowest first. Easier: use the arrows in the list.',
                        'attrs'       => ['step' => 1, 'min' => 0, 'max' => 9999],
                    ]); ?>

                    <label class="form-check" for="f_is_active">
                        <input type="checkbox" id="f_is_active" name="is_active" value="1"
                            <?= checked((int) ($editingRow['is_active'] ?? 1), 1) ?>>
                        <span class="form-check-label">
                            Offer this when adding <?= e(an_asset()) ?>
                            <small>Untick to retire it without touching the <?= e(asset_word(true)) ?> that use it.</small>
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

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= icon('info', '', 17) ?> Moving <?= e(asset_word(true)) ?> around</h3>
            </div>
            <div class="card-body">
                <ul class="setup-steps">
                    <li>To move one <?= e(asset_word()) ?>, edit it and pick a different <?= e($noun) ?>.</li>
                    <li>To move several at once, tick them on the <?= e(asset_word()) ?> list and choose where they go.</li>
                    <li>To empty <?= e($isCategories ? 'a category' : 'a location') ?> and get rid of it, use <strong>Delete…</strong> on its row:
                        everything in it moves to the one you choose, in one go.</li>
                </ul>
                <a class="btn btn-secondary btn-sm" href="<?= e(url('assets.php')) ?>">
                    <?= icon('list', '', 15) ?> Open the <?= e(asset_word()) ?> list
                </a>
            </div>
        </div>
    </div>
</div>
