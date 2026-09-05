<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Dates;
use App\Models\Asset;
use App\Request;
use App\Status;
use App\Uploader;
use App\Validator;
use App\View;

Auth::requireLogin();

$id      = Request::int('id');
$editing = $id > 0;
$asset   = null;

// Where to go once a new machine is saved. A form with a machine picker sends
// people here with its own address, so they land back on it with the new
// machine already chosen. Only a same-site path is ever honoured.
$return = Request::safeRedirect('return');

// "Add another like this": a new machine that starts as a copy of one that
// exists, for entering the second of twenty identical karts.
$copyFrom = $editing ? 0 : Request::int('copy_from');
$template = $copyFrom > 0 ? Asset::find($copyFrom) : null;

if ($editing) {
    Acl::requirePermission('assets.edit');

    $asset = Asset::find($id);

    if ($asset === null) {
        abort(404, 'That ' . asset_word() . ' does not exist. It may have been deleted.');
    }
} else {
    Acl::requirePermission('assets.create');
}

// -----------------------------------------------------------------------------
// Save
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    // With meters switched off the form has no meter fields: keep whatever
    // is stored rather than failing "meter type is required".
    if (!feature_on('meters')) {
        $_POST['meter_type']    = $editing ? (string) $asset['meter_type'] : 'none';
        $_POST['meter_reading'] = $editing ? (string) $asset['meter_reading'] : '';
    }

    $rules = [
        'name'                => 'required|string|max:150',
        'asset_tag'           => 'required|string|max:60|unique:assets,asset_tag' . ($editing ? ',' . $id : ''),
        'category_id'         => 'nullable|int|exists:asset_categories,id',
        'location_id'         => 'nullable|int|exists:locations,id',
        'status'              => 'required|' . Status::rule('asset'),
        'criticality'         => 'required|' . Status::rule('criticality'),
        'manufacturer'        => 'nullable|string|max:120',
        'model'               => 'nullable|string|max:120',
        'serial_number'       => 'nullable|string|max:120',
        'vin'                 => 'nullable|string|max:60',
        'year_manufactured'   => 'nullable|int|between:1900,2100',
        'purchase_date'       => 'nullable|date',
        'purchase_cost'       => 'nullable|decimal|min:0|max:99999999',
        'warranty_expires'    => 'nullable|date',
        'engine_make'         => 'nullable|string|max:120',
        'engine_model'        => 'nullable|string|max:120',
        'engine_serial'       => 'nullable|string|max:120',
        'fuel_type'           => 'nullable|string|max:40',
        'tire_size'           => 'nullable|string|max:60',
        'capacity_passengers' => 'nullable|int|between:0|max:999',
        'meter_type'          => 'required|' . Status::rule('meter'),
        'meter_reading'       => 'nullable|decimal|min:0',
        'in_service_date'     => 'nullable|date',
        'description'         => 'nullable|text|max:2000',
        'notes'               => 'nullable|text|max:5000',
    ];

    // "between:0" above is malformed for a single bound; use max only.
    $rules['capacity_passengers'] = 'nullable|int|min:0|max:999';

    $validator = Validator::make($_POST, $rules, [], [
        'asset_tag'         => asset_word(false, true) . ' tag',
        'category_id'       => 'Category',
        'location_id'       => 'Location',
        'year_manufactured' => 'Year',
        'meter_type'        => 'Meter type',
        'meter_reading'     => 'Meter reading',
    ]);

    // The extra fields from Settings → Fields are checked against their own
    // definitions; values for fields since removed are carried across untouched.
    $customErrors = [];
    $customValues = \App\CustomFields::fromInput(
        $_POST,
        $editing ? \App\CustomFields::decode($asset['custom_data'] ?? null) : [],
        $customErrors
    );

    if ($validator->fails() || $customErrors !== []) {
        flash_errors($validator->errors() + $customErrors, $_POST);
        redirect(url('asset-edit.php', $editing ? ['id' => $id] : ['return' => $return, 'copy_from' => $copyFrom ?: null]));
    }

    $data = $validator->validated();

    if (\App\CustomFields::any()) {
        $data['custom_data'] = \App\CustomFields::encode($customValues);
    }

    // The purchase price is not on the form for somebody who cannot see money,
    // and an absent field must never wipe the one that is there.
    if (!costs_visible()) {
        unset($data['purchase_cost']);
    }

    // A meter reading only means something if the machine has a meter.
    if ($data['meter_type'] === 'none') {
        $data['meter_reading'] = 0;
    }

    $data['meter_reading'] = $data['meter_reading'] ?? 0;

    // Empty foreign keys must be NULL, not 0, or the constraint rejects them.
    foreach (['category_id', 'location_id'] as $key) {
        if (empty($data[$key])) {
            $data[$key] = null;
        }
    }

    try {
        if ($editing) {
            $previousMeter = (float) $asset['meter_reading'];
            $newMeter      = (float) $data['meter_reading'];

            // Let the meter machinery handle a change, so the reading is
            // logged and any meter-based service is re-checked.
            unset($data['meter_reading']);

            Asset::update($id, $data);

            if ($data['meter_type'] !== 'none' && abs($newMeter - $previousMeter) > 0.004) {
                // Editing the machine is the one place a meter is allowed to go
                // backwards: it is where you correct a replaced or reset unit.
                Asset::updateMeter($id, $newMeter, 'Set while editing the ' . asset_word(), 'manual', null, true);

                if ($newMeter < $previousMeter) {
                    flash('info', 'The meter has been set back to ' . decimal($newMeter)
                        . '. The change is recorded in the meter history.');
                }
            }

            $savedId = $id;
            flash('success', 'Saved.');
        } else {
            $savedId = Asset::create($data);
            flash('success', '“' . $data['name'] . '” has been added.');
        }

        // Photo, if one was chosen.
        $photo = Request::file('photo');

        if ($photo !== null) {
            $result = Uploader::handle($photo, 'asset', $savedId, Auth::id());

            if (!$result['ok']) {
                flash('warning', 'The ' . asset_word() . ' was saved, but the photo was not: ' . $result['error']);
            } elseif ((int) ($result['attachment']['is_image'] ?? 0) === 1) {
                db()->update('assets', ['image_path' => (string) $result['attachment']['file_path']], ['id' => $savedId]);
            }
        }

        // "Save and add another like it" keeps a fleet entry session moving:
        // the next form starts as a copy of the one just saved.
        if (Request::string('after') === 'new') {
            redirect(url('asset-edit.php', ['copy_from' => $savedId, 'return' => $return]));
        }

        // Back to the form that sent them here, with the new machine chosen.
        if (!$editing && $return !== null) {
            $to = strpos($return, '/') === 0 ? $return : url($return);

            redirect($to . (strpos($to, '?') === false ? '?' : '&') . 'asset_id=' . $savedId);
        }

        redirect(url('asset-view.php', ['id' => $savedId]));
    } catch (Throwable $e) {
        log_error(asset_word(false, true) . ' save failed: ' . $e->getMessage());
        flash('error', 'The ' . asset_word() . ' could not be saved. The error has been logged.');
        redirect(url('asset-edit.php', $editing ? ['id' => $id] : ['return' => $return, 'copy_from' => $copyFrom ?: null]));
    }
}

// -----------------------------------------------------------------------------
// Form
// -----------------------------------------------------------------------------

$categories = Asset::categoryOptions();
$prefillCat = Request::int('category_id');

$defaults = [
    'name'                => '',
    'asset_tag'           => Asset::suggestTag($prefillCat > 0 ? $prefillCat : null),
    'category_id'         => $prefillCat,
    'location_id'         => 0,
    'status'              => 'in_service',
    'criticality'         => 'medium',
    'manufacturer'        => '',
    'model'               => '',
    'serial_number'       => '',
    'vin'                 => '',
    'year_manufactured'   => '',
    'purchase_date'       => '',
    'purchase_cost'       => '',
    'warranty_expires'    => '',
    'engine_make'         => '',
    'engine_model'        => '',
    'engine_serial'       => '',
    'fuel_type'           => '',
    'tire_size'           => '',
    'capacity_passengers' => '',
    'meter_type'          => 'none',
    'meter_reading'       => '',
    'in_service_date'     => '',
    'description'         => '',
    'notes'               => '',
];

$values       = $editing ? array_merge($defaults, $asset) : $defaults;
$customValues = $editing ? \App\CustomFields::decode($asset['custom_data'] ?? null) : [];

if ($template !== null) {
    // Everything that tends to be the same across a batch. Not the things
    // that make a unit itself: serial numbers, VIN, meter reading, photo.
    foreach (['category_id', 'location_id', 'criticality', 'manufacturer', 'model',
              'year_manufactured', 'purchase_date', 'purchase_cost', 'warranty_expires',
              'engine_make', 'engine_model', 'fuel_type', 'tire_size', 'capacity_passengers',
              'meter_type', 'in_service_date', 'description', 'notes'] as $key) {
        $values[$key] = $template[$key] ?? $values[$key];
    }

    $values['name']      = Asset::nextName((string) $template['name']);
    $values['asset_tag'] = Asset::suggestTag((int) $template['category_id'] ?: null);
    $customValues        = \App\CustomFields::decode($template['custom_data'] ?? null);
}

View::render('assets/edit', [
    'title'       => $editing ? 'Edit ' . (string) $asset['name'] : 'Add ' . an_asset(),
    'subtitle'    => $editing ? (string) $asset['asset_tag'] : 'A kart, a ride, a vehicle or a piece of shop equipment',
    'activeNav'   => 'assets.php',
    'breadcrumbs' => [
        ['label' => asset_word(true, true), 'url' => url('assets.php')],
        $editing
            ? ['label' => (string) $asset['name'], 'url' => url('asset-view.php', ['id' => $id])]
            : ['label' => 'Add'],
        ['label' => $editing ? 'Edit' : 'New'],
    ],
    'editing'    => $editing,
    'asset'      => $asset,
    'values'     => $values,
    'categories' => $categories,
    'locations'  => Asset::locationOptions(),
    'customFields' => \App\CustomFields::all(),
    'customValues' => $customValues,
    'returnTo'     => $return,
    'template'     => $template,
]);
