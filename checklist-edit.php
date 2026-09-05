<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Models\Asset;
use App\Models\Inspection;
use App\Request;
use App\Settings;
use App\Status;
use App\Validator;
use App\View;

Auth::requireLogin();
Acl::requirePermission('checklists.manage');

$id        = Request::int('id');
$editing   = $id > 0;
$checklist = null;

if ($editing) {
    $checklist = db()->find('checklists', $id);

    if ($checklist === null) {
        abort(404, 'That checklist does not exist.');
    }
}

// -----------------------------------------------------------------------------
// Save
// -----------------------------------------------------------------------------

if (is_post()) {
    Csrf::verify();

    $validator = Validator::make($_POST, [
        'name'              => 'required|string|max:191',
        'description'       => 'nullable|text|max:2000',
        'applies_to'        => 'required|in:all,category,asset',
        'category_id'       => 'nullable|int',
        'asset_id'          => 'nullable|int',
        'frequency'         => 'required|in:daily,weekly,monthly,quarterly,annual,preseason,adhoc',
        'estimated_minutes' => 'nullable|int|min:0|max:1440',
    ], [
        'name.required' => 'Give the checklist a name, such as “Daily go-kart check”.',
    ], [
        'estimated_minutes' => 'How long it takes',
    ]);

    $items = [];

    foreach (is_array($_POST['items'] ?? null) ? $_POST['items'] : [] as $row) {
        if (!is_array($row)) {
            continue;
        }

        $text = trim((string) ($row['item_text'] ?? ''));

        if ($text === '') {
            continue; // A blank line is somebody who changed their mind.
        }

        $type = (string) ($row['response_type'] ?? 'pass_fail_na');

        if (!in_array($type, ['pass_fail', 'pass_fail_na', 'yes_no', 'text', 'number', 'meter'], true)) {
            $type = 'pass_fail_na';
        }

        $min = trim((string) ($row['min_value'] ?? ''));
        $max = trim((string) ($row['max_value'] ?? ''));

        $items[] = [
            'id'             => (int) ($row['id'] ?? 0),
            'section'        => mb_substr(trim((string) ($row['section'] ?? '')), 0, 120, 'UTF-8'),
            'item_text'      => mb_substr($text, 0, 255, 'UTF-8'),
            'description'    => mb_substr(trim((string) ($row['description'] ?? '')), 0, 500, 'UTF-8'),
            'response_type'  => $type,
            'is_required'    => empty($row['is_required']) ? 0 : 1,
            'is_critical'    => empty($row['is_critical']) ? 0 : 1,
            'allow_photo'    => 1,
            'expected_value' => mb_substr(trim((string) ($row['expected_value'] ?? '')), 0, 100, 'UTF-8'),
            'unit'           => mb_substr(trim((string) ($row['unit'] ?? '')), 0, 20, 'UTF-8'),
            'min_value'      => $min === '' ? null : (float) $min,
            'max_value'      => $max === '' ? null : (float) $max,
        ];
    }

    if ($items === []) {
        $validator->addError('items', 'A checklist needs at least one thing to check.');
    }

    if ($validator->fails()) {
        flash_errors($validator->errors(), $_POST);
        redirect(url('checklist-edit.php', $editing ? ['id' => $id] : []));
    }

    $data = $validator->validated();

    // Scope only means something for the matching kind.
    $data['category_id'] = (string) $data['applies_to'] === 'category' && !empty($data['category_id'])
        ? (int) $data['category_id']
        : null;
    $data['asset_id'] = (string) $data['applies_to'] === 'asset' && !empty($data['asset_id'])
        ? (int) $data['asset_id']
        : null;

    if ((string) $data['applies_to'] === 'category' && $data['category_id'] === null) {
        flash_errors(['category_id' => 'Choose which kind of ' . asset_word() . ' this checklist is for.'], $_POST);
        redirect(url('checklist-edit.php', $editing ? ['id' => $id] : []));
    }

    if ((string) $data['applies_to'] === 'asset' && $data['asset_id'] === null) {
        flash_errors(['asset_id' => 'Choose which ' . asset_word() . ' this checklist is for.'], $_POST);
        redirect(url('checklist-edit.php', $editing ? ['id' => $id] : []));
    }

    if (($data['estimated_minutes'] ?? '') === '') {
        $data['estimated_minutes'] = null;
    }

    $data['require_signature'] = Request::bool('require_signature') ? 1 : 0;
    $data['require_meter']     = Request::bool('require_meter') ? 1 : 0;
    $data['is_active']         = Request::bool('is_active') ? 1 : 0;
    $data['updated_by']        = Auth::id();

    try {
        $savedId = db()->transaction(static function () use ($data, $items, $editing, $id): int {
            if ($editing) {
                db()->update('checklists', $data, ['id' => $id]);
                $savedId = $id;
            } else {
                $data['created_by'] = Auth::id();
                $savedId = db()->insert('checklists', $data);
            }

            $keep  = [];
            $order = 0;

            foreach ($items as $item) {
                $itemId = (int) $item['id'];
                unset($item['id']);

                $item['sort_order'] = $order++;

                // An id that does not belong to this checklist is treated as new.
                $belongs = $itemId > 0 && db()->exists('checklist_items', [
                    'id'           => $itemId,
                    'checklist_id' => $savedId,
                ]);

                if ($belongs) {
                    db()->update('checklist_items', $item, ['id' => $itemId]);
                    $keep[] = $itemId;
                } else {
                    $item['checklist_id'] = $savedId;
                    $keep[] = db()->insert('checklist_items', $item);
                }
            }

            // Anything the user removed from the form goes.
            $placeholders = implode(',', array_fill(0, count($keep), '?'));

            db()->run(
                'DELETE FROM {checklist_items} WHERE checklist_id = ? AND id NOT IN (' . $placeholders . ')',
                array_merge([$savedId], $keep)
            );

            return $savedId;
        });

        audit($editing ? 'update' : 'create', 'checklist', $savedId,
            ($editing ? 'Updated ' : 'Created ') . (string) $data['name']
            . ' (' . count($items) . ' item' . (count($items) === 1 ? '' : 's') . ')');

        flash('success', $editing
            ? 'Checklist saved.'
            : 'Checklist created. It will show up when somebody inspects a matching ' . asset_word() . '.');

        redirect(url('checklists.php'));
    } catch (Throwable $e) {
        log_error('Checklist save failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        flash('error', 'The checklist could not be saved. The error has been recorded.');
        redirect(url('checklist-edit.php', $editing ? ['id' => $id] : []));
    }
}

// -----------------------------------------------------------------------------
// Form
// -----------------------------------------------------------------------------

$defaults = [
    'name'              => '',
    'description'       => '',
    'applies_to'        => 'category',
    'category_id'       => '',
    'asset_id'          => '',
    'frequency'         => 'daily',
    'estimated_minutes' => '',
    // A new checklist starts from whatever the site-wide default says; from
    // then on the checklist itself is the authority.
    'require_signature' => Settings::bool('inspection_signature_required', true) ? 1 : 0,
    'require_meter'     => 1,
    'is_active'         => 1,
];

$values = $editing ? array_merge($defaults, $checklist) : $defaults;
$items  = $editing ? Inspection::checklistItems($id) : [];

$assetOptions = [];

foreach (Asset::options(true) as $asset) {
    $assetOptions[(int) $asset['id']] = (string) $asset['name'] . ' — ' . (string) $asset['asset_tag'];
}

// The sections already in use, so people reuse headings instead of inventing them.
$sectionSuggestions = db()->column(
    "SELECT DISTINCT section FROM {checklist_items} WHERE section <> '' ORDER BY section"
);

View::render('checklists/edit', [
    'title'       => $editing ? 'Edit checklist' : 'New checklist',
    'subtitle'    => $editing ? (string) $checklist['name'] : 'Build the list your team works through',
    'activeNav'   => 'checklists.php',
    'breadcrumbs' => [
        ['label' => 'Checklists', 'url' => url('checklists.php')],
        ['label' => $editing ? 'Edit' : 'New'],
    ],
    'editing'    => $editing,
    'checklist'  => $checklist,
    'values'     => $values,
    'items'      => $items,
    'categories' => db()->pairs('SELECT id, name FROM {asset_categories} ORDER BY sort_order, name'),
    'assets'     => $assetOptions,
    // Only the frequencies the checklists table actually accepts, not the
    // wider set the PM schedules use.
    'frequencies' => array_intersect_key(
        Status::options('frequency'),
        array_flip(['daily', 'weekly', 'monthly', 'quarterly', 'annual', 'preseason', 'adhoc'])
    ),
    'responseTypes' => Status::options('response_type'),
    'sections'   => $sectionSuggestions,
]);
