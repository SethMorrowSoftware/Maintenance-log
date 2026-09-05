<?php

declare(strict_types=1);

/**
 * Categories and locations: the two short lists that make the site yours.
 *
 * Add, rename, reorder, recolour, switch off, merge and delete. Nothing here
 * ever loses a machine: deleting a category that still has machines means
 * choosing where they go first, and they go there in one move.
 */

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

/** Icons a category may use — the ones that look like something on a site plan. */
const CATEGORY_ICONS = [
    'kart', 'ride', 'wrench', 'tool', 'gauge', 'fuel', 'activity', 'sparkle', 'star',
    'package', 'box', 'truck', 'grid', 'list', 'folder', 'home', 'tag', 'map-pin',
    'camera', 'clipboard', 'checklist', 'shield', 'cog', 'sun', 'bell', 'image', 'archive',
];

$tab = Request::enum('tab', ['categories', 'locations'], 'categories');

// -----------------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $action  = Request::string('action');
    $kind    = Request::enum('kind', ['categories', 'locations'], 'categories');
    $table   = $kind === 'categories' ? 'asset_categories' : 'locations';
    $entity  = $kind === 'categories' ? 'category' : 'location';
    $noun    = $kind === 'categories' ? 'Category' : 'Location';
    $column  = $kind === 'categories' ? 'category_id' : 'location_id';
    $id      = Request::int('id');
    $back    = url('categories.php', ['tab' => $kind]);
    $row     = $id > 0 ? db()->find($table, $id) : null;

    // --------------------------------------------------------------- delete
    if ($action === 'delete') {
        $inUse = db()->count("SELECT COUNT(*) FROM {assets} WHERE {$column} = ? AND deleted_at IS NULL", [$id]);

        if ($inUse > 0) {
            flash('error', 'That still has ' . $inUse . ' ' . ($inUse === 1 ? asset_word() : asset_word(true))
                . ' in it. Use "Delete…" on its row to move them somewhere else first.');
            redirect($back);
        }

        if ($row !== null) {
            db()->delete($table, ['id' => $id]);
            audit('delete', $entity, $id, 'Deleted ' . strtolower($noun) . ' "' . (string) $row['name'] . '"');
            flash('success', $noun . ' deleted.');
        }

        redirect($back);
    }

    // --------------------------------------------------------------- merge
    // Everything in this one moves to another one, then this one goes. The
    // machines keep their history; only the label on them changes.
    if ($action === 'merge') {
        $targetId = Request::int('target_id');
        $target   = $targetId > 0 && $targetId !== $id ? db()->find($table, $targetId) : null;

        if ($row === null || $target === null) {
            flash('error', 'Choose where its ' . asset_word(true) . ' should go.');
            redirect($back);
        }

        $moved = db()->count("SELECT COUNT(*) FROM {assets} WHERE {$column} = ?", [$id]);

        db()->run("UPDATE {assets} SET {$column} = ? WHERE {$column} = ?", [$targetId, $id]);

        if ($kind === 'categories') {
            // Checklists aimed at the old category now aim at the new one.
            db()->run('UPDATE {checklists} SET category_id = ? WHERE category_id = ?', [$targetId, $id]);
        }

        db()->delete($table, ['id' => $id]);

        audit('delete', $entity, $id, 'Deleted ' . strtolower($noun) . ' "' . (string) $row['name']
            . '", moving ' . $moved . ' ' . asset_word(true) . ' to "' . (string) $target['name'] . '"');

        flash('success', 'Moved ' . $moved . ' ' . ($moved === 1 ? asset_word() : asset_word(true))
            . ' from “' . (string) $row['name'] . '” to “' . (string) $target['name'] . '” and deleted “'
            . (string) $row['name'] . '”.');

        redirect($back);
    }

    // --------------------------------------------------------------- move
    // Up or down one place. The whole list is renumbered in tens as it goes,
    // so every row has a place of its own whatever the numbers were before.
    if ($action === 'move') {
        $direction = Request::enum('dir', ['up', 'down'], 'down');
        $ids       = array_map('intval', db()->column('SELECT id FROM {' . $table . '} ORDER BY sort_order ASC, name ASC'));
        $position  = array_search($id, $ids, true);

        if ($position !== false && $row !== null) {
            $other = $direction === 'up' ? $position - 1 : $position + 1;

            if (isset($ids[$other])) {
                [$ids[$position], $ids[$other]] = [$ids[$other], $ids[$position]];
            }

            foreach ($ids as $index => $rowId) {
                db()->update($table, ['sort_order' => ($index + 1) * 10], ['id' => $rowId]);
            }

            audit('update', $entity, $id, 'Moved ' . strtolower($noun) . ' "' . (string) $row['name'] . '" ' . $direction);
        }

        redirect($back);
    }

    // --------------------------------------------------------------- toggle
    if ($action === 'toggle') {
        if ($row !== null) {
            $active = (int) $row['is_active'] === 1 ? 0 : 1;
            db()->update($table, ['is_active' => $active], ['id' => $id]);
            audit('update', $entity, $id, ($active === 1 ? 'Switched on ' : 'Switched off ') . (string) $row['name']);
            flash('success', $active === 1
                ? '“' . (string) $row['name'] . '” is available again.'
                : '“' . (string) $row['name'] . '” is hidden when adding ' . asset_word(true) . '. Existing ones keep it.');
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
        'is_active'   => Request::bool('is_active') ? 1 : 0,
    ];

    // The order box is optional: blank keeps the place it already has, and a
    // new one goes at the end.
    $orderTyped = trim((string) ($_POST['sort_order'] ?? ''));

    if ($orderTyped !== '') {
        $data['sort_order'] = max(0, min(9999, (int) $orderTyped));
    } elseif ($row === null) {
        $data['sort_order'] = 10 + (int) db()->value('SELECT COALESCE(MAX(sort_order), 0) FROM {' . $table . '}');
    }

    if ($kind === 'categories') {
        $meter = Request::string('default_meter_type', 'none');
        $icon  = trim(Request::string('icon'));

        $data['icon']               = in_array($icon, CATEGORY_ICONS, true) ? $icon : (string) ($row['icon'] ?? 'tool');
        $data['color']              = preg_match('/^#[0-9a-fA-F]{6}$/', Request::string('color')) === 1
            ? Request::string('color')
            : (string) ($row['color'] ?? '#4f46e5');
        $data['default_meter_type'] = Status::isValid($meter, 'meter') ? $meter : 'none';

        // The slug is an internal handle (the starting fleet finds its
        // categories by it), so renaming keeps it. A new one gets a unique one.
        if ($row === null) {
            $base = Str::slug($name) ?: 'category';
            $slug = $base;
            $n    = 2;

            while (db()->exists('asset_categories', ['slug' => $slug])) {
                $slug = $base . '-' . $n++;
            }

            $data['slug'] = $slug;
        }
    } else {
        $data['building'] = mb_substr(trim(Request::string('building')), 0, 120, 'UTF-8');
    }

    try {
        if ($row !== null) {
            db()->update($table, $data, ['id' => $id]);
            audit('update', $entity, $id, 'Updated ' . strtolower($noun) . ' "' . $name . '"'
                . ((string) $row['name'] !== $name ? ' (was "' . (string) $row['name'] . '")' : ''));
            flash('success', 'Saved.');
        } else {
            $newId = db()->insert($table, $data);
            audit('create', $entity, $newId, 'Added ' . strtolower($noun) . ' "' . $name . '"');
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
            (SELECT COUNT(*) FROM {assets} a WHERE a.category_id = c.id AND a.deleted_at IS NULL) AS asset_count,
            (SELECT COUNT(*) FROM {checklists} k WHERE k.category_id = c.id) AS checklist_count
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
    'title'       => 'Categories & Locations',
    'subtitle'    => 'How your ' . asset_word(true) . ' are grouped, and where they live',
    'activeNav'   => 'categories.php',
    'tab'         => $tab,
    'categories'  => $categories,
    'locations'   => $locations,
    'editing'     => $editing,
    'meterTypes'  => Status::options('meter'),
    'iconChoices' => CATEGORY_ICONS,
]);
