<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Checks;
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
        'applies_to'        => 'required|in:all,category,asset,location',
        'category_id'       => 'nullable|int',
        'asset_id'          => 'nullable|int',
        'location_id'       => 'nullable|int',
        'frequency'         => 'required|in:daily,weekly,monthly,quarterly,annual,preseason,adhoc',
        'estimated_minutes' => 'nullable|int|min:0|max:1440',
        'due_time'          => 'nullable|string|max:8',
        'remind_minutes'    => 'nullable|int|min:0|max:1440',
        'escalate_minutes'  => 'nullable|int|min:0|max:1440',
        'alert_channel'     => 'nullable|string|max:80',
        'alert_mention'     => 'nullable|string|max:80',
    ], [
        'name.required' => 'Give the checklist a name, such as “Daily go-kart check”.',
    ], [
        'estimated_minutes' => 'How long it takes',
        'due_time'          => 'Due by',
        'remind_minutes'    => 'Remind beforehand',
        'escalate_minutes'  => 'Escalate after',
        'alert_channel'     => 'Slack channel',
        'alert_mention'     => 'Who to alert',
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

    // An area checklist is for a place, not a machine.
    $data['location_id'] = (string) $data['applies_to'] === 'location' && !empty($data['location_id'])
        ? (int) $data['location_id']
        : null;

    if ((string) $data['applies_to'] === 'location') {
        if ($data['location_id'] === null || !db()->exists('locations', ['id' => $data['location_id']])) {
            flash_errors(['location_id' => 'Choose which area this checklist is for.'], $_POST);
            redirect(url('checklist-edit.php', $editing ? ['id' => $id] : []));
        }
    }

    if (($data['estimated_minutes'] ?? '') === '') {
        $data['estimated_minutes'] = null;
    }

    // When it should be done. A blank time means "whenever": the list is
    // recorded when it is run and never chased.
    $dueTime = trim((string) ($data['due_time'] ?? ''));

    if ($dueTime !== '') {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)/', $dueTime, $m) !== 1) {
            flash_errors(['due_time' => 'Type the time as hours and minutes, like 10:30.'], $_POST);
            redirect(url('checklist-edit.php', $editing ? ['id' => $id] : []));
        }

        $dueTime = $m[1] . ':' . $m[2] . ':00';
    }

    $days = is_array($_POST['due_days'] ?? null) ? $_POST['due_days'] : [];

    if ($dueTime !== '' && $days === []) {
        flash_errors(['due_days' => 'Tick at least one day it is due on.'], $_POST);
        redirect(url('checklist-edit.php', $editing ? ['id' => $id] : []));
    }

    $data['due_time']         = $dueTime === '' ? null : $dueTime;
    $data['due_days']         = Checks::cleanDays($days);
    $data['remind_minutes']   = (int) ($data['remind_minutes'] ?? 0) > 0 ? (int) $data['remind_minutes'] : null;
    $data['escalate_minutes'] = (int) ($data['escalate_minutes'] ?? 0) > 0 ? (int) $data['escalate_minutes'] : null;
    $data['alert_missed']     = Request::bool('alert_missed') ? 1 : 0;
    $data['alert_channel']    = trim((string) ($data['alert_channel'] ?? ''));
    $data['alert_mention']    = trim((string) ($data['alert_mention'] ?? ''));

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

        if ($editing) {
            flash('success', 'Checklist saved.');
        } elseif ((string) $data['applies_to'] === 'location') {
            flash('success', 'Checklist created. It is on the start-a-check page for that area'
                . ($data['due_time'] !== null ? ', and on Today\'s checks from ' . Checks::daysLabel($data['due_days']) . '.' : '.'));
        } else {
            flash('success', 'Checklist created. It will show up when somebody checks a matching ' . asset_word() . '.');
        }

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
    'location_id'       => '',
    'frequency'         => 'daily',
    'estimated_minutes' => '',
    'due_time'          => '',
    'due_days'          => '1234567',
    'remind_minutes'    => '',
    'alert_missed'      => 1,
    'alert_channel'     => '',
    'alert_mention'     => '',
    'escalate_minutes'  => '',
    // A new checklist starts from whatever the site-wide default says; from
    // then on the checklist itself is the authority.
    'require_signature' => Settings::bool('inspection_signature_required', true) ? 1 : 0,
    'require_meter'     => 1,
    'is_active'         => 1,
];

$values = $editing ? array_merge($defaults, $checklist) : $defaults;
$items  = $editing ? Inspection::checklistItems($id) : [];

// The time input wants HH:MM.
$values['due_time'] = substr((string) ($values['due_time'] ?? ''), 0, 5);

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
    'locations'  => Asset::locationOptions(false),
    'slackOn'    => \App\Slack::enabled() && Settings::bool('slack_on_unfinished', true),
    'bellOn'     => Settings::bool('checks_notify_managers', true),
    // Only the frequencies the checklists table actually accepts, not the
    // wider set the PM schedules use.
    'frequencies' => array_intersect_key(
        Status::options('frequency'),
        array_flip(['daily', 'weekly', 'monthly', 'quarterly', 'annual', 'preseason', 'adhoc'])
    ),
    // A meter line asks for something the site has switched off.
    'responseTypes' => feature_on('meters')
        ? Status::options('response_type')
        : array_diff_key(Status::options('response_type'), ['meter' => true]),
    'sections'   => $sectionSuggestions,
]);
