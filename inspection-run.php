<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Models\Asset;
use App\Models\Inspection;
use App\Request;
use App\Scope;
use App\Settings;
use App\View;

Auth::requireLogin();
Acl::requirePermission('inspections.perform');

$inspectionId = Request::int('id');
$assetId      = Request::int('asset_id');
$checklistId  = Request::int('checklist_id');

// ---------------------------------------------------------------------------
// Starting a new run
// ---------------------------------------------------------------------------

if ($inspectionId === 0) {
    // A checklist on its own, with no machine: an area check.
    if ($assetId === 0 && $checklistId > 0) {
        $checklist = db()->find('checklists', $checklistId);

        if ($checklist !== null && (string) $checklist['applies_to'] === 'location') {
            $inspectionId = Inspection::start(null, $checklistId);

            if ($inspectionId === 0) {
                flash('error', 'That check could not be started. It may be switched off, or not one of yours.');
                redirect(url('checks.php'));
            }

            redirect(url('inspection-run.php', ['id' => $inspectionId]));
        }
    }

    if ($assetId === 0) {
        // Nothing chosen yet: the area checks this person may run, then the
        // machines. Somebody limited to an area only sees its machines.
        [$scopeSql, $scopeParams] = Scope::assetFilter('a');

        $assets = db()->all(
            "SELECT a.id, a.name, a.asset_tag, a.status, a.meter_type, a.meter_reading,
                    c.name AS category_name
             FROM {assets} a
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             WHERE a.deleted_at IS NULL AND a.status <> 'retired'"
            . ($scopeSql !== null ? ' AND ' . $scopeSql : '')
            . ' ORDER BY c.sort_order ASC, c.name ASC, a.sort_order ASC, a.name ASC',
            $scopeParams
        );

        View::render('inspections/start', [
            'title'          => 'Run a check',
            'subtitle'       => 'Pick what you are checking',
            'activeNav'      => 'inspections.php',
            'assets'         => $assets,
            'areaChecklists' => Inspection::areaChecklists(),
        ]);
        exit;
    }

    $asset = Asset::find($assetId);

    if ($asset === null) {
        abort(404, 'That ' . asset_word() . ' does not exist.');
    }

    $checklists = array_values(array_filter(
        Inspection::checklistsFor($assetId),
        static fn (array $row): bool => Scope::allowsChecklist($row, $asset)
    ));

    if ($checklists === []) {
        // Somebody limited to an area is told no more than that — not the
        // name of a machine they are not meant to see.
        if (Scope::limited()) {
            flash('error', 'That ' . asset_word() . ' is not in your area.');
            redirect(url('checks.php'));
        }

        // Somebody has walked to the machine, or scanned the tag on it, and
        // there is nothing to run. Sending them to a list of other machines
        // wastes the trip, so land them on this one: its history is there,
        // and so are the buttons to log work or report a fault. An
        // administrator gets taken straight to writing the missing checklist.
        flash(
            'warning',
            'There is no checklist set up for ' . (string) $asset['name'] . '. '
            . (can('checklists.manage')
                ? 'You can write one now.'
                : 'You can still log work on it or report a problem, and ask an administrator for a checklist.')
        );

        redirect(can('checklists.manage')
            ? url('checklist-edit.php', ['asset_id' => $assetId])
            : url('asset-view.php', ['id' => $assetId]));
    }

    if ($checklistId === 0) {
        if (count($checklists) === 1) {
            // Only one applies, so do not make anyone choose from a list of one.
            $checklistId = (int) $checklists[0]['id'];
        } else {
            View::render('inspections/choose', [
                'title'      => 'Run a check',
                'subtitle'   => (string) $asset['name'],
                'activeNav'  => 'inspections.php',
                'asset'      => $asset,
                'checklists' => $checklists,
            ]);
            exit;
        }
    }

    $inspectionId = Inspection::start($assetId, $checklistId);

    if ($inspectionId === 0) {
        flash('error', 'That check could not be started.');
        redirect(url('inspections.php'));
    }

    redirect(url('inspection-run.php', ['id' => $inspectionId]));
}

// ---------------------------------------------------------------------------
// Running one
// ---------------------------------------------------------------------------

$inspection = Inspection::find($inspectionId);

if ($inspection === null) {
    abort(404, 'That inspection does not exist.');
}

if ((string) $inspection['status'] !== 'in_progress') {
    redirect(url('inspection-view.php', ['id' => $inspectionId]));
}

// Only the person who started it, or a manager, may fill it in — and only
// somebody whose area it is.
if (!Scope::allowsInspection($inspection)) {
    abort(403, 'This check is outside your area.');
}

if ((int) $inspection['user_id'] !== (int) Auth::id() && !can('inspections.delete')) {
    abort(403, 'This inspection was started by somebody else.');
}

$isArea = $inspection['asset_id'] === null;

if (is_post()) {
    Csrf::verify();

    $complete = Request::string('action') === 'complete';
    $answers  = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];

    $meta = [
        'notes'               => Request::string('notes'),
        'signature_name'      => Request::string('signature_name'),
        'meter_reading'       => $isArea ? '' : Request::string('meter_reading'),
        'take_out_of_service' => !$isArea && Request::bool('take_out_of_service'),
    ];

    $result = Inspection::saveAnswers($inspectionId, $answers, $meta);

    if (!$complete) {
        flash('success', 'Progress saved. Come back to it whenever you like.');
        redirect(url('inspection-run.php', ['id' => $inspectionId]));
    }

    // Completing requires every required item to be answered.
    if ($result['missing'] > 0) {
        flash('error', $result['missing'] . ' item' . ($result['missing'] === 1 ? ' still needs' : 's still need')
            . ' an answer before you can finish. Your progress has been saved.');
        redirect(url('inspection-run.php', ['id' => $inspectionId]));
    }

    // The checklist decides whether it has to be signed. A checklist whose
    // template has since been deleted falls back to the site-wide default.
    $needsSignature = $inspection['require_signature'] === null
        ? Settings::bool('inspection_signature_required', true)
        : (int) $inspection['require_signature'] === 1;

    if ($needsSignature && $meta['signature_name'] === '') {
        flash('error', 'Type your name to sign off the check. Your progress has been saved.');
        redirect(url('inspection-run.php', ['id' => $inspectionId]));
    }

    // A meter that goes backwards is a typo often enough that it is worth
    // stopping for, while the number is still on screen — whether it was
    // typed in the box at the bottom or on a "meter reading" line.
    if (!$isArea && $result['meter'] !== null
        && (float) $result['meter'] < (float) $inspection['asset_meter'] - 0.004) {
        flash('error', 'The meter on ' . (string) $inspection['asset_name'] . ' reads '
            . decimal($inspection['asset_meter']) . ' ' . (string) $inspection['meter_type']
            . '. A reading cannot go backwards — check the number. Your progress has been saved.');
        redirect(url('inspection-run.php', ['id' => $inspectionId]));
    }

    $outcome = Inspection::finalise($inspectionId, $meta['take_out_of_service']);

    if ($result['critical']) {
        flash('warning', 'A safety-critical item failed. '
            . ($outcome['work_order_id'] !== null ? 'A high-priority work order has been raised.' : ''));
    } elseif ($result['failed'] > 0) {
        flash('warning', $result['failed'] . ' item' . ($result['failed'] === 1 ? '' : 's') . ' failed. '
            . ($outcome['work_order_id'] !== null ? 'A work order has been raised.' : ''));
    } else {
        flash('success', 'Check passed. Nice one.');
    }

    redirect(url('inspection-view.php', ['id' => $inspectionId]));
}

$items = Inspection::items($inspectionId);

// Group by section so the page reads like a paper checklist.
$sections = [];

foreach ($items as $item) {
    $sections[(string) $item['section']][] = $item;
}

$subtitle = Inspection::subject($inspection);

if (!$isArea && (string) $inspection['asset_tag'] !== '') {
    $subtitle .= ' · ' . (string) $inspection['asset_tag'];
}

if (!empty($inspection['due_at'])) {
    $subtitle .= ' · due by ' . \App\Dates::time((string) $inspection['due_at']);
}

View::render('inspections/run', [
    'title'          => (string) $inspection['checklist_name'],
    'subtitle'       => $subtitle,
    'activeNav'      => 'inspections.php',
    'hidePageHeader' => false,
    'inspection'     => $inspection,
    'sections'       => $sections,
    'itemCount'      => count($items),
]);
