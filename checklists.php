<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Request;
use App\View;

Auth::requireLogin();
Acl::requirePermission('checklists.view');

// -----------------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();
    Acl::requirePermission('checklists.manage');

    $action = Request::string('action');
    $id     = Request::int('id');
    $row    = $id > 0 ? db()->find('checklists', $id) : null;

    if ($row === null) {
        flash('error', 'That checklist no longer exists.');
        redirect(url('checklists.php'));
    }

    if ($action === 'toggle') {
        $active = (int) $row['is_active'] === 1 ? 0 : 1;

        db()->update('checklists', ['is_active' => $active, 'updated_by' => Auth::id()], ['id' => $id]);
        audit('update', 'checklist', $id,
            ($active === 1 ? 'Turned on ' : 'Turned off ') . (string) $row['name']);

        flash('success', $active === 1
            ? '“' . (string) $row['name'] . '” is back in use.'
            : '“' . (string) $row['name'] . '” is switched off. Inspections already recorded are untouched.');
    }

    if ($action === 'duplicate') {
        $newId = db()->transaction(static function () use ($row): int {
            $copy = $row;
            unset($copy['id'], $copy['created_at'], $copy['updated_at']);

            $copy['name']       = mb_substr((string) $row['name'] . ' (copy)', 0, 191, 'UTF-8');
            $copy['is_active']  = 0;
            $copy['created_by'] = Auth::id();
            $copy['updated_by'] = Auth::id();

            $newId = db()->insert('checklists', $copy);

            $items = db()->all(
                'SELECT * FROM {checklist_items} WHERE checklist_id = ? ORDER BY sort_order ASC, id ASC',
                [(int) $row['id']]
            );

            foreach ($items as $item) {
                unset($item['id'], $item['created_at'], $item['updated_at']);
                $item['checklist_id'] = $newId;
                db()->insert('checklist_items', $item);
            }

            return $newId;
        });

        audit('create', 'checklist', $newId, 'Copied ' . (string) $row['name']);
        flash('success', 'Copied. Give it a name and switch it on when it is ready.');
        redirect(url('checklist-edit.php', ['id' => $newId]));
    }

    if ($action === 'delete') {
        // Inspections keep their own copy of the item text, so history survives.
        db()->delete('checklists', ['id' => $id]);
        audit('delete', 'checklist', $id, 'Deleted ' . (string) $row['name']);
        flash('success', 'Checklist deleted. Inspections already carried out keep their record.');
    }

    redirect(url('checklists.php'));
}

// -----------------------------------------------------------------------------
// List
// -----------------------------------------------------------------------------

$rows = db()->all(
    "SELECT c.*,
            cat.name AS category_name,
            a.name   AS asset_name,
            loc.name AS location_name,
            (SELECT COUNT(*) FROM {checklist_items} ci WHERE ci.checklist_id = c.id) AS item_count,
            (SELECT COUNT(*) FROM {checklist_items} ci WHERE ci.checklist_id = c.id AND ci.is_critical = 1)
                AS critical_count,
            (SELECT COUNT(*) FROM {inspections} i WHERE i.checklist_id = c.id) AS run_count,
            (SELECT MAX(i.started_at) FROM {inspections} i WHERE i.checklist_id = c.id) AS last_run
     FROM {checklists} c
     LEFT JOIN {asset_categories} cat ON cat.id = c.category_id
     LEFT JOIN {assets} a ON a.id = c.asset_id
     LEFT JOIN {locations} loc ON loc.id = c.location_id
     ORDER BY c.is_active DESC, c.due_time IS NULL, c.due_time ASC, c.name ASC"
);

$actions = '';

if (can('checklists.manage')) {
    $actions = '<a class="btn btn-primary" href="' . e(url('checklist-edit.php')) . '">'
        . icon('plus', '', 17) . ' New checklist</a>';
}

View::render('checklists/index', [
    'title'       => 'Checklists',
    'subtitle'    => 'The lists your team works through when they inspect a kart or a ride',
    'activeNav'   => 'checklists.php',
    'pageActions' => $actions,
    'rows'        => $rows,
]);
