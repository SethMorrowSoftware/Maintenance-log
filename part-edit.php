<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Models\Part;
use App\Request;
use App\Settings;
use App\Validator;
use App\View;

Auth::requireLogin();
Acl::requirePermission('parts.manage');

$id      = Request::int('id');
$editing = $id > 0;
$part    = null;

if ($editing) {
    $part = Part::find($id);

    if ($part === null) {
        abort(404, 'That part does not exist.');
    }
}

// -----------------------------------------------------------------------------
// Save
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $validator = Validator::make($_POST, [
        'name'                 => 'required|string|max:191',
        'part_number'          => 'required|string|max:100',
        'description'          => 'nullable|text|max:2000',
        'category'             => 'nullable|string|max:100',
        'manufacturer'         => 'nullable|string|max:120',
        'supplier'             => 'nullable|string|max:120',
        'supplier_part_number' => 'nullable|string|max:100',
        'unit_cost'            => 'nullable|decimal|min:0',
        'unit_of_measure'      => 'nullable|string|max:20',
        'quantity_on_hand'     => 'nullable|decimal',
        'reorder_level'        => 'nullable|decimal|min:0',
        'reorder_quantity'     => 'nullable|decimal|min:0',
        'location_bin'         => 'nullable|string|max:60',
        'notes'                => 'nullable|text|max:5000',
    ], [
        'name.required'        => 'Give the part a name somebody would recognise on a shelf.',
        'part_number.required' => 'A part number keeps two similar parts apart. '
                                . 'If it does not have one, make one up — "BRAKE-PAD-STD" is fine.',
    ], [
        'part_number'          => 'Part number',
        'quantity_on_hand'     => 'How many on hand',
        'reorder_level'        => 'Reorder at',
        'supplier_part_number' => 'Supplier’s number',
    ]);

    // Part numbers are unique, so say so plainly instead of showing a 500.
    $clash = db()->one(
        'SELECT id, name FROM {parts} WHERE part_number = ? AND deleted_at IS NULL'
        . ($editing ? ' AND id <> ?' : '') . ' LIMIT 1',
        $editing
            ? [Request::string('part_number'), $id]
            : [Request::string('part_number')]
    );

    if ($clash !== null) {
        $validator->addError('part_number',
            'That part number is already used by “' . (string) $clash['name'] . '”.');
    }

    if ($validator->fails()) {
        flash_errors($validator->errors(), $_POST);
        redirect(url('part-edit.php', $editing ? ['id' => $id] : []));
    }

    $data = $validator->validated();

    foreach (['unit_cost', 'reorder_level', 'reorder_quantity'] as $field) {
        $data[$field] = ($data[$field] ?? '') === '' ? 0 : (float) $data[$field];
    }

    $data['unit_of_measure'] = ($data['unit_of_measure'] ?? '') === ''
        ? 'each'
        : (string) $data['unit_of_measure'];

    $data['is_active'] = Request::bool('is_active') ? 1 : 0;

    try {
        if ($editing) {
            // Stock only moves through adjustStock(), so the running total
            // always has a movement behind it.
            $newQuantity = ($_POST['quantity_on_hand'] ?? '') === ''
                ? null
                : (float) $_POST['quantity_on_hand'];

            unset($data['quantity_on_hand']);

            Part::update($id, $data);

            if ($newQuantity !== null) {
                $difference = round($newQuantity - (float) $part['quantity_on_hand'], 2);

                if (abs($difference) > 0.004) {
                    Part::adjustStock($id, $difference, 'adjust', 'manual', null,
                        'Counted while editing the part');

                    flash('info', 'Stock corrected to ' . decimal($newQuantity) . '.');
                }
            }

            $savedId = $id;
            flash('success', 'Saved.');
        } else {
            $data['quantity_on_hand'] = ($data['quantity_on_hand'] ?? '') === ''
                ? 0
                : (float) $data['quantity_on_hand'];

            $savedId = Part::create($data);
            flash('success', '“' . (string) $data['name'] . '” has been added to the shelf.');
        }

        redirect(url('part-view.php', ['id' => $savedId]));
    } catch (Throwable $e) {
        log_error('Part save failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        flash('error', 'The part could not be saved. The error has been recorded.');
        redirect(url('part-edit.php', $editing ? ['id' => $id] : []));
    }
}

// -----------------------------------------------------------------------------
// Form
// -----------------------------------------------------------------------------

$defaults = [
    'name'                 => '',
    'part_number'          => '',
    'description'          => '',
    'category'             => '',
    'manufacturer'         => '',
    'supplier'             => '',
    'supplier_part_number' => '',
    'unit_cost'            => '',
    'unit_of_measure'      => 'each',
    'quantity_on_hand'     => '',
    'reorder_level'        => '',
    'reorder_quantity'     => '',
    'location_bin'         => '',
    'notes'                => '',
    'is_active'            => 1,
];

View::render('parts/edit', [
    'title'       => $editing ? 'Edit part' : 'Add a part',
    'subtitle'    => $editing
        ? (string) $part['name']
        : 'Something you keep on the shelf',
    'activeNav'   => 'parts.php',
    'breadcrumbs' => [
        ['label' => 'Parts Inventory', 'url' => url('parts.php')],
        ['label' => $editing ? 'Edit' : 'New'],
    ],
    'editing'    => $editing,
    'part'       => $part,
    'values'     => $editing ? array_merge($defaults, $part) : $defaults,
    'categories' => Part::categoryOptions(),
    'currency'   => Settings::currency(),
]);
