<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Request;
use App\Status;
use App\Str;
use App\View;

Auth::requireLogin();
Acl::requirePermission('assets.edit');

$tab = Request::enum('tab', ['categories', 'locations'], 'categories');

// -----------------------------------------------------------------------------
// Save
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');
    $kind   = Request::enum('kind', ['categories', 'locations'], 'categories');
    $table  = $kind === 'categories' ? 'asset_categories' : 'locations';
    $noun   = $kind === 'categories' ? 'Category' : 'Location';
    $id     = Request::int('id');
    $back   = url('categories.php', ['tab' => $kind]);

    // --------------------------------------------------------------- delete
    if ($action === 'delete') {
        $column  = $kind === 'categories' ? 'category_id' : 'location_id';
        $inUse   = db()->count(
            "SELECT COUNT(*) FROM {assets} WHERE {$column} = ? AND deleted_at IS NULL",
            [$id]
        );

        if ($inUse > 0) {
            flash('error', 'That is still used by ' . $inUse . ' ' . ($inUse === 1 ? asset_word() : asset_word(true)) . '. '
                . 'Move them somewhere else first, or switch this off instead of deleting it.');
            redirect($back);
        }

        $row = db()->find($table, $id);

        if ($row !== null) {
            db()->delete($table, ['id' => $id]);
            audit('delete', $kind === 'categories' ? 'category' : 'location', $id,
                'Deleted ' . strtolower($noun) . ' "' . (string) $row['name'] . '"');
            flash('success', $noun . ' deleted.');
        }

        redirect($back);
    }

    // --------------------------------------------------------------- toggle
    if ($action === 'toggle') {
        $row = db()->find($table, $id);

        if ($row !== null) {
            $active = (int) $row['is_active'] === 1 ? 0 : 1;
            db()->update($table, ['is_active' => $active], ['id' => $id]);
            audit('update', $kind === 'categories' ? 'category' : 'location', $id,
                ($active === 1 ? 'Switched on ' : 'Switched off ') . (string) $row['name']);
            flash('success', $active === 1
                ? '“' . (string) $row['name'] . '” is available again.'
                : '“' . (string) $row['name'] . '” is hidden from new assets. Existing ones keep it.');
        }

        redirect($back);
    }

    // --------------------------------------------------------------- save
    $name = trim(Request::string('name'));

    if ($name === '') {
        flash('error', 'Give it a name.');
        redirect($back);
    }

    $clash = db()->one(
        'SELECT id FROM {' . $table . '} WHERE name = ?' . ($id > 0 ? ' AND id <> ?' : '') . ' LIMIT 1',
        $id > 0 ? [$name, $id] : [$name]
    );

    if ($clash !== null) {
        flash('error', 'There is already one called “' . $name . '”.');
        redirect($back);
    }

    $data = [
        'name'        => mb_substr($name, 0, 120, 'UTF-8'),
        'description' => mb_substr(trim(Request::string('description')), 0, 255, 'UTF-8'),
        'sort_order'  => Request::int('sort_order'),
        'is_active'   => Request::bool('is_active') ? 1 : 0,
    ];

    if ($kind === 'categories') {
        $meter = Request::string('default_meter_type', 'none');

        $data['slug']               = Str::slug($name);
        $data['icon']               = mb_substr(trim(Request::string('icon')) ?: 'tool', 0, 60, 'UTF-8');
        $data['color']              = preg_match('/^#[0-9a-fA-F]{6}$/', Request::string('color')) === 1
            ? Request::string('color')
            : '#4f46e5';
        $data['default_meter_type'] = Status::isValid($meter, 'meter') ? $meter : 'none';
    } else {
        $data['building'] = mb_substr(trim(Request::string('building')), 0, 120, 'UTF-8');
    }

    try {
        if ($id > 0) {
            db()->update($table, $data, ['id' => $id]);
            audit('update', $kind === 'categories' ? 'category' : 'location', $id,
                'Updated ' . strtolower($noun) . ' "' . $name . '"');
            flash('success', 'Saved.');
        } else {
            $newId = db()->insert($table, $data);
            audit('create', $kind === 'categories' ? 'category' : 'location', $newId,
                'Added ' . strtolower($noun) . ' "' . $name . '"');
            flash('success', '“' . $name . '” added.');
        }
    } catch (Throwable $e) {
        log_error('Category/location save failed: ' . $e->getMessage());
        flash('error', 'That could not be saved. The error has been recorded.');
    }

    redirect($back);
}

// -----------------------------------------------------------------------------
// Page
// -----------------------------------------------------------------------------

$categories = db()->all(
    'SELECT c.*,
            (SELECT COUNT(*) FROM {assets} a WHERE a.category_id = c.id AND a.deleted_at IS NULL) AS asset_count
     FROM {asset_categories} c
     ORDER BY c.sort_order ASC, c.name ASC'
);

$locations = db()->all(
    'SELECT l.*,
            (SELECT COUNT(*) FROM {assets} a WHERE a.location_id = l.id AND a.deleted_at IS NULL) AS asset_count
     FROM {locations} l
     ORDER BY l.sort_order ASC, l.name ASC'
);

$editing = null;
$editId  = Request::int('edit');

if ($editId > 0) {
    $editing = db()->find($tab === 'categories' ? 'asset_categories' : 'locations', $editId);
}

View::render('categories/index', [
    'title'      => 'Categories & Locations',
    'subtitle'   => 'How your ' . asset_word(true) . ' are grouped, and where they live',
    'activeNav'  => 'categories.php',
    'tab'        => $tab,
    'categories' => $categories,
    'locations'  => $locations,
    'editing'    => $editing,
    'meterTypes' => Status::options('meter'),
]);
