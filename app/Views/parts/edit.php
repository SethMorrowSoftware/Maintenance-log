<?php
/**
 * Add or edit a part.
 *
 * Two fields are required — what it is called and its part number. Everything
 * else is worth filling in eventually, and none of it should stop somebody
 * getting a part onto the shelf now.
 */

use App\View;

$partId = $editing ? (int) $part['id'] : 0;
?>

<form method="post" action="<?= e(url('part-edit.php', $editing ? ['id' => $partId] : [])) ?>" data-guard>
    <?= csrf_field() ?>

    <div class="grid grid-sidebar">
        <div>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('package', '', 18) ?> What is it?</h2>
                </div>
                <div class="card-body">
                    <?php View::partial('form-field', [
                        'name'        => 'name',
                        'label'       => 'Name',
                        'value'       => $values['name'],
                        'required'    => true,
                        'placeholder' => 'Front brake pad set',
                        'attrs'       => ['maxlength' => 191, 'autofocus' => !$editing],
                    ]); ?>

                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name'        => 'part_number',
                            'label'       => 'Part number',
                            'value'       => $values['part_number'],
                            'required'    => true,
                            'placeholder' => 'BRK-PAD-FR',
                            'hint'        => 'Yours, not the supplier’s. It has to be unique.',
                            'attrs'       => ['maxlength' => 100],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'        => 'category',
                            'label'       => 'Category',
                            'value'       => $values['category'],
                            'placeholder' => 'Brakes',
                            'hint'        => 'Used to group the list. Anything you like.',
                            'attrs'       => ['maxlength' => 100, 'list' => 'part-categories'],
                        ]); ?>
                    </div>

                    <datalist id="part-categories">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= attr((string) $category) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>

                    <?php View::partial('form-field', [
                        'name'        => 'description',
                        'label'       => 'Description',
                        'type'        => 'textarea',
                        'value'       => $values['description'],
                        'rows'        => 2,
                        'placeholder' => 'Fits every 2019-onward kart. Sold in pairs.',
                        'attrs'       => ['maxlength' => 2000, 'data-autogrow' => true],
                    ]); ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('box', '', 18) ?> Stock</h2>
                </div>
                <div class="card-body">
                    <div class="form-row cols-3">
                        <?php View::partial('form-field', [
                            'name'   => 'quantity_on_hand',
                            'label'  => $editing ? 'Counted on the shelf' : 'How many on hand',
                            'type'   => 'decimal',
                            'value'  => $values['quantity_on_hand'],
                            'hint'   => $editing
                                ? 'Change this to correct the count. The difference is recorded.'
                                : 'The opening count. Leave blank for none.',
                            'attrs'  => ['step' => '0.01', 'inputmode' => 'decimal'],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'        => 'unit_of_measure',
                            'label'       => 'Counted in',
                            'value'       => $values['unit_of_measure'],
                            'placeholder' => 'each',
                            'hint'        => 'each, pair, litre, metre…',
                            'attrs'       => ['maxlength' => 20],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'   => 'unit_cost',
                            'label'  => 'Cost each',
                            'type'   => 'money',
                            'value'  => $values['unit_cost'],
                            'prefix' => $currency,
                            'hint'   => 'Fills in the cost when the part is used on a job.',
                            'attrs'  => ['min' => '0', 'step' => '0.01'],
                        ]); ?>
                    </div>

                    <div class="form-row cols-3">
                        <?php View::partial('form-field', [
                            'name'  => 'reorder_level',
                            'label' => 'Tell me when it drops to',
                            'type'  => 'decimal',
                            'value' => $values['reorder_level'],
                            'hint'  => 'Leave at 0 for no warning.',
                            'attrs' => ['min' => '0', 'step' => '0.01'],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'  => 'reorder_quantity',
                            'label' => 'Usually order',
                            'type'  => 'decimal',
                            'value' => $values['reorder_quantity'],
                            'hint'  => 'How many you buy at a time.',
                            'attrs' => ['min' => '0', 'step' => '0.01'],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'        => 'location_bin',
                            'label'       => 'Where it lives',
                            'value'       => $values['location_bin'],
                            'placeholder' => 'Shelf B, bin 4',
                            'attrs'       => ['maxlength' => 60],
                        ]); ?>
                    </div>
                </div>
            </div>

            <details class="card">
                <summary class="card-header">
                    <h2 class="card-title"><?= icon('truck', '', 18) ?> Where it comes from</h2>
                    <span class="text-sm text-muted">Optional <?= icon('chevron-down', '', 15) ?></span>
                </summary>
                <div class="card-body">
                    <div class="form-row cols-3">
                        <?php View::partial('form-field', [
                            'name'  => 'manufacturer',
                            'label' => 'Made by',
                            'value' => $values['manufacturer'],
                            'attrs' => ['maxlength' => 120],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'  => 'supplier',
                            'label' => 'Bought from',
                            'value' => $values['supplier'],
                            'attrs' => ['maxlength' => 120],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'  => 'supplier_part_number',
                            'label' => 'Their part number',
                            'value' => $values['supplier_part_number'],
                            'hint'  => 'What to quote when you ring them up.',
                            'attrs' => ['maxlength' => 100],
                        ]); ?>
                    </div>

                    <?php View::partial('form-field', [
                        'name'  => 'notes',
                        'label' => 'Notes',
                        'type'  => 'textarea',
                        'value' => $values['notes'],
                        'rows'  => 3,
                        'attrs' => ['maxlength' => 5000, 'data-autogrow' => true],
                    ]); ?>
                </div>
            </details>
        </div>

        <div>
            <div class="card">
                <div class="card-header"><h3 class="card-title">Status</h3></div>
                <div class="card-body">
                    <label class="form-check" for="f_is_active">
                        <input type="checkbox" id="f_is_active" name="is_active" value="1"
                            <?= checked((int) $values['is_active'], 1) ?>>
                        <span class="form-check-label">
                            Still in use
                            <small>Untick for a part you no longer stock. It stays on old jobs.</small>
                        </span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">
                    <?= icon('save', '', 17) ?>
                    <?= $editing ? 'Save changes' : 'Add the part' ?>
                </button>
                <a class="btn btn-ghost btn-block" data-no-guard
                   href="<?= e($editing ? url('part-view.php', ['id' => $partId]) : url('parts.php')) ?>">
                    Cancel
                </a>
            </div>

            <?php if ($editing && can('parts.manage')): ?>
                <div class="card is-danger">
                    <div class="card-header"><h3 class="card-title">Danger zone</h3></div>
                    <div class="card-body">
                        <p class="text-sm text-muted mb-3">
                            Deleting takes the part off the list. Jobs that used it keep their record
                            and their cost.
                        </p>
                        <?php View::partial('confirm-delete', [
                            'url'         => url('parts.php'),
                            'id'          => $partId,
                            'label'       => 'Delete this part',
                            'message'     => 'Delete “' . (string) $part['name'] . '” from the parts list?',
                            'buttonClass' => 'btn btn-danger-outline btn-block',
                        ]); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>
