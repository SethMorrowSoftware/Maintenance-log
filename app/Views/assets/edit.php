<?php
/**
 * Add or edit a machine.
 *
 * Six fields are all anyone needs to get a kart into the system. Everything
 * else — engine details, purchase records, notes — sits behind collapsed
 * sections, so the form is not a wall of empty boxes on first use.
 */

use App\Dates;
use App\Status;
use App\Uploader;
use App\View;
?>

<?php
$returnTo = $returnTo ?? null;
$template = $template ?? null;
?>
<form method="post" action="<?= e(url('asset-edit.php', $editing ? ['id' => (int) $asset['id']] : ['return' => $returnTo])) ?>"
      enctype="multipart/form-data" data-validate data-guard>
    <?= csrf_field() ?>
    <?php if ($returnTo !== null): ?>
        <input type="hidden" name="return" value="<?= attr($returnTo) ?>">
    <?php endif; ?>

    <?php if ($template !== null): ?>
        <div class="alert alert-info">
            <?= icon('copy', '', 18) ?>
            <div class="alert-body">
                Started as a copy of <strong><?= e((string) $template['name']) ?></strong>.
                The name and tag are new; change whatever else differs and save.
            </div>
        </div>
    <?php elseif ($returnTo !== null): ?>
        <div class="alert alert-info">
            <?= icon('info', '', 18) ?>
            <div class="alert-body">
                Save this <?= e(asset_word()) ?> and you go straight back to the form you came from,
                with it already chosen.
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-sidebar">
        <div>

            <?php // ======================= The basics ======================= ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('assets', '', 18) ?> The basics</h2>
                </div>
                <div class="card-body">

                    <?php View::partial('form-field', [
                        'name'        => 'name',
                        'label'       => 'What is it called?',
                        'type'        => 'text',
                        'value'       => $values['name'],
                        'required'    => true,
                        'placeholder' => 'Go-Kart #9',
                        'hint'        => 'The name your team uses for it.',
                        'attrs'       => ['autofocus' => true, 'maxlength' => 150],
                    ]); ?>

                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name'     => 'asset_tag',
                            'label'    => asset_word(false, true) . ' tag',
                            'type'     => 'text',
                            'value'    => $values['asset_tag'],
                            'required' => true,
                            'hint'     => 'A short unique code. We have suggested the next one in the series.',
                            'attrs'    => ['maxlength' => 60, 'autocapitalize' => 'characters'],
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'    => 'category_id',
                            'label'   => 'Category',
                            'type'    => 'select',
                            'value'   => $values['category_id'],
                            'options' => $categories,
                            'empty'   => 'Choose a category…',
                            'hint'    => 'Groups it on reports, and decides which daily checklist applies.',
                        ]); ?>
                    </div>

                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name'    => 'location_id',
                            'label'   => 'Where does it live?',
                            'type'    => 'select',
                            'value'   => $values['location_id'],
                            'options' => $locations,
                            'empty'   => 'Not set',
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'     => 'status',
                            'label'    => 'Current status',
                            'type'     => 'select',
                            'value'    => $values['status'],
                            'options'  => Status::options('asset'),
                            'required' => true,
                        ]); ?>
                    </div>

                    <?php View::partial('form-field', [
                        'name'     => 'criticality',
                        'label'    => 'How important is it?',
                        'type'     => 'select',
                        'value'    => $values['criticality'],
                        'options'  => Status::options('criticality'),
                        'required' => true,
                        'hint'     => 'Critical means guests are at risk if it fails. Used to sort work when several things need attention at once.',
                    ]); ?>
                </div>
            </div>

            <?php // ======================== The meter ======================== ?>
            <?php if (feature_on('meters')): ?>
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="card-title"><?= icon('gauge', '', 18) ?> Meter</h2>
                        <p class="card-subtitle">
                            If it has an hour meter or a lap counter, servicing can be scheduled off it
                            instead of the calendar.
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name'     => 'meter_type',
                            'label'    => 'Meter type',
                            'type'     => 'select',
                            'value'    => $values['meter_type'],
                            'options'  => Status::options('meter'),
                            'required' => true,
                        ]); ?>

                        <?php View::partial('form-field', [
                            'name'  => 'meter_reading',
                            'label' => 'Current reading',
                            'type'  => 'meter',
                            'value' => $values['meter_reading'],
                            'hint'  => 'Leave blank if there is no meter.',
                            'attrs' => ['min' => '0'],
                        ]); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php // ================== Make and model (optional) ================== ?>
            <details class="card" <?= $editing && ($values['manufacturer'] !== '' || $values['serial_number'] !== '') ? 'open' : '' ?>>
                <summary class="card-header" style="cursor:pointer;list-style:none">
                    <h2 class="card-title"><?= icon('tag', '', 18) ?> Make, model and serial numbers</h2>
                    <span class="text-sm text-muted">Optional <?= icon('chevron-down', '', 15) ?></span>
                </summary>
                <div class="card-body">
                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name' => 'manufacturer', 'label' => 'Manufacturer', 'type' => 'text',
                            'value' => $values['manufacturer'], 'placeholder' => 'J&J Amusements',
                            'attrs' => ['maxlength' => 120],
                        ]); ?>
                        <?php View::partial('form-field', [
                            'name' => 'model', 'label' => 'Model', 'type' => 'text',
                            'value' => $values['model'], 'placeholder' => 'Sprint 200',
                            'attrs' => ['maxlength' => 120],
                        ]); ?>
                    </div>

                    <div class="form-row cols-3">
                        <?php View::partial('form-field', [
                            'name' => 'serial_number', 'label' => 'Serial number', 'type' => 'text',
                            'value' => $values['serial_number'], 'attrs' => ['maxlength' => 120],
                        ]); ?>
                        <?php View::partial('form-field', [
                            'name' => 'vin', 'label' => 'VIN', 'type' => 'text',
                            'value' => $values['vin'], 'hint' => 'For road-registered vehicles.',
                            'attrs' => ['maxlength' => 60],
                        ]); ?>
                        <?php View::partial('form-field', [
                            'name' => 'year_manufactured', 'label' => 'Year built', 'type' => 'number',
                            'value' => $values['year_manufactured'],
                            'attrs' => ['min' => 1900, 'max' => 2100],
                        ]); ?>
                    </div>

                    <div class="form-row cols-3">
                        <?php View::partial('form-field', [
                            'name' => 'engine_make', 'label' => 'Engine make', 'type' => 'text',
                            'value' => $values['engine_make'], 'placeholder' => 'Honda',
                            'attrs' => ['maxlength' => 120],
                        ]); ?>
                        <?php View::partial('form-field', [
                            'name' => 'engine_model', 'label' => 'Engine model', 'type' => 'text',
                            'value' => $values['engine_model'], 'placeholder' => 'GX200',
                            'attrs' => ['maxlength' => 120],
                        ]); ?>
                        <?php View::partial('form-field', [
                            'name' => 'engine_serial', 'label' => 'Engine serial', 'type' => 'text',
                            'value' => $values['engine_serial'], 'attrs' => ['maxlength' => 120],
                        ]); ?>
                    </div>

                    <div class="form-row cols-3">
                        <?php View::partial('form-field', [
                            'name' => 'fuel_type', 'label' => 'Fuel', 'type' => 'text',
                            'value' => $values['fuel_type'], 'placeholder' => 'Gasoline',
                            'attrs' => ['maxlength' => 40, 'list' => 'fuel-types'],
                        ]); ?>
                        <?php View::partial('form-field', [
                            'name' => 'tire_size', 'label' => 'Tyre size', 'type' => 'text',
                            'value' => $values['tire_size'], 'placeholder' => '11x6.00-5',
                            'attrs' => ['maxlength' => 60],
                        ]); ?>
                        <?php View::partial('form-field', [
                            'name' => 'capacity_passengers', 'label' => 'Seats / riders', 'type' => 'number',
                            'value' => $values['capacity_passengers'], 'attrs' => ['min' => 0, 'max' => 999],
                        ]); ?>
                    </div>

                    <datalist id="fuel-types">
                        <option value="Gasoline"></option>
                        <option value="Diesel"></option>
                        <option value="Electric"></option>
                        <option value="Propane"></option>
                        <option value="Battery"></option>
                    </datalist>
                </div>
            </details>

            <?php // ================ Purchase and warranty (optional) ================ ?>
            <details class="card" <?= $editing && ($values['purchase_date'] || $values['purchase_cost']) ? 'open' : '' ?>>
                <summary class="card-header" style="cursor:pointer;list-style:none">
                    <h2 class="card-title"><?= icon('calendar', '', 18) ?> Purchase and warranty</h2>
                    <span class="text-sm text-muted">Optional <?= icon('chevron-down', '', 15) ?></span>
                </summary>
                <div class="card-body">
                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name' => 'purchase_date', 'label' => 'Purchased on', 'type' => 'date',
                            'value' => Dates::inputDateOnly((string) $values['purchase_date']),
                        ]); ?>
                        <?php if (costs_visible()): ?>
                            <?php View::partial('form-field', [
                                'name' => 'purchase_cost', 'label' => 'Purchase price', 'type' => 'money',
                                'value' => $values['purchase_cost'], 'prefix' => (string) App\Settings::currency(),
                                'attrs' => ['min' => '0'],
                            ]); ?>
                        <?php endif; ?>
                    </div>
                    <div class="form-row cols-2">
                        <?php View::partial('form-field', [
                            'name' => 'warranty_expires', 'label' => 'Warranty ends', 'type' => 'date',
                            'value' => Dates::inputDateOnly((string) $values['warranty_expires']),
                        ]); ?>
                        <?php View::partial('form-field', [
                            'name' => 'in_service_date', 'label' => 'In service since', 'type' => 'date',
                            'value' => Dates::inputDateOnly((string) $values['in_service_date']),
                        ]); ?>
                    </div>
                </div>
            </details>

            <?php // ============ Extra fields from Settings → Fields ============ ?>
            <?php $customFields = $customFields ?? []; $customValues = $customValues ?? []; ?>
            <?php if ($customFields !== []): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><?= icon('list', '', 18) ?> More details</h2>
                    </div>
                    <div class="card-body">
                        <div class="form-row cols-2">
                            <?php foreach ($customFields as $field): ?>
                                <?php
                                $fieldName  = \App\CustomFields::inputName((string) $field['key']);
                                $fieldValue = $customValues[(string) $field['key']] ?? '';
                                $args       = [
                                    'name'  => $fieldName,
                                    'label' => (string) $field['label'],
                                    'value' => $fieldValue,
                                    'hint'  => (string) $field['hint'],
                                ];

                                switch ((string) $field['type']) {
                                    case 'number':
                                        $args['type']  = 'number';
                                        $args['attrs'] = ['step' => 'any', 'inputmode' => 'decimal'];
                                        break;
                                    case 'date':
                                        $args['type'] = 'date';
                                        break;
                                    case 'yesno':
                                        $args['type']    = 'select';
                                        $args['options'] = ['1' => 'Yes', '0' => 'No'];
                                        $args['empty']   = 'Not filled in';
                                        break;
                                    case 'choice':
                                        $args['type']    = 'select';
                                        $args['options'] = array_combine($field['options'], $field['options']) ?: [];
                                        $args['empty']   = 'Choose…';
                                        break;
                                    default:
                                        $args['type']  = 'text';
                                        $args['attrs'] = ['maxlength' => \App\CustomFields::MAX_TEXT];
                                }
                                ?>
                                <?php View::partial('form-field', $args); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php // ========================= Notes ========================= ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= icon('file-text', '', 18) ?> Notes</h2>
                </div>
                <div class="card-body">
                    <?php View::partial('form-field', [
                        'name'  => 'description',
                        'label' => 'Description',
                        'type'  => 'textarea',
                        'value' => $values['description'],
                        'rows'  => 2,
                        'hint'  => 'A line about what it is, for anyone who does not know it by name.',
                        'attrs' => ['maxlength' => 2000, 'data-autogrow' => true],
                    ]); ?>

                    <?php View::partial('form-field', [
                        'name'  => 'notes',
                        'label' => 'Maintenance notes',
                        'type'  => 'textarea',
                        'value' => $values['notes'],
                        'rows'  => 4,
                        'hint'  => 'Quirks worth knowing: the things you would tell a new starter.',
                        'attrs' => ['maxlength' => 5000, 'data-autogrow' => true],
                    ]); ?>
                </div>
            </div>
        </div>

        <?php // ========================== Sidebar ========================== ?>
        <div>
            <?php if (feature_on('photos')): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= icon('camera', '', 17) ?> Photo</h3>
                </div>
                <div class="card-body">
                    <?php if ($editing && !empty($asset['image_path'])): ?>
                        <img class="asset-photo mb-3"
                             src="<?= e(url('file.php', ['asset_photo' => (int) $asset['id']])) ?>"
                             alt="<?= attr((string) $asset['name']) ?>">
                    <?php else: ?>
                        <div class="asset-photo-placeholder mb-3"><?= icon('image', '', 30) ?></div>
                    <?php endif; ?>

                    <label class="btn btn-secondary btn-block" style="cursor:pointer">
                        <?= icon('upload', '', 16) ?>
                        <?= $editing && !empty($asset['image_path']) ? 'Replace photo' : 'Choose a photo' ?>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                               class="sr-only">
                    </label>
                    <p class="form-hint">
                        A photo makes <?= e(an_asset()) ?> easy to identify on a phone. Up to
                        <?= (int) round(App\Settings::maxUploadBytes() / 1048576) ?> MB.
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <?= icon('save', '', 18) ?>
                        <?= $editing ? 'Save changes' : 'Add ' . asset_word() ?>
                    </button>

                    <?php if (!$editing): ?>
                        <button type="submit" name="after" value="new" class="btn btn-secondary btn-block mt-2"
                                title="Saves this one, then opens a new form already filled in like it">
                            <?= icon('plus', '', 16) ?> Save and add another like it
                        </button>
                    <?php endif; ?>

                    <a class="btn btn-ghost btn-block mt-2" data-no-guard
                       href="<?= e($editing ? url('asset-view.php', ['id' => (int) $asset['id']]) : ($returnTo !== null && strpos($returnTo, '/') === 0 ? $returnTo : url('assets.php'))) ?>">
                        Cancel
                    </a>
                </div>
            </div>

            <?php if ($editing && can('assets.delete')): ?>
                <div class="card is-danger">
                    <div class="card-header">
                        <h3 class="card-title">Danger zone</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-sm text-muted mb-3">
                            Deleting hides the <?= e(asset_word()) ?> from every list. Its maintenance history is kept,
                            so past reports stay accurate.
                        </p>
                        <?php View::partial('confirm-delete', [
                            'buttonFor'   => 'delete-record',
                            'label'       => 'Delete this ' . asset_word(),
                            'message'     => 'Delete “' . (string) $asset['name'] . '”? It will disappear from lists, '
                                           . 'but its maintenance history will be kept.',
                            'buttonClass' => 'btn btn-danger-outline btn-block',
                        ]); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php // The delete form lives outside the edit form: a form cannot sit inside a form. ?>
<?php if ($editing && can('assets.delete')): ?>
    <?php View::partial('confirm-delete', [
        'formId' => 'delete-record',
        'url'    => url('assets.php'),
        'id'     => (int) $asset['id'],
    ]); ?>
<?php endif; ?>
