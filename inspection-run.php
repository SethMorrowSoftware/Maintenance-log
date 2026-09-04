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
    if ($assetId === 0) {
        // Nothing chosen yet: ask which asset.
        View::render('inspections/start', [
            'title'     => 'Run an inspection',
            'subtitle'  => 'Pick what you are checking',
            'activeNav' => 'inspections.php',
            'assets'    => Asset::options(),
        ]);
        exit;
    }

    $asset = Asset::find($assetId);

    if ($asset === null) {
        abort(404, 'That asset does not exist.');
    }

    $checklists = Inspection::checklistsFor($assetId);

    if ($checklists === []) {
        flash('error', 'There is no checklist set up for ' . (string) $asset['name']
            . '. Ask an administrator to create one under Checklists.');
        redirect(url('inspections.php'));
    }

    if ($checklistId === 0) {
        if (count($checklists) === 1) {
            // Only one applies, so do not make anyone choose from a list of one.
            $checklistId = (int) $checklists[0]['id'];
        } else {
            View::render('inspections/choose', [
                'title'      => 'Run an inspection',
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
        flash('error', 'That checklist could not be started.');
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

// Only the person who started it, or a manager, may fill it in.
if ((int) $inspection['user_id'] !== (int) Auth::id() && !can('inspections.delete')) {
    abort(403, 'This inspection was started by somebody else.');
}

if (is_post()) {
    Csrf::verify();

    $complete = Request::string('action') === 'complete';
    $answers  = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];

    $meta = [
        'notes'               => Request::string('notes'),
        'signature_name'      => Request::string('signature_name'),
        'meter_reading'       => Request::string('meter_reading'),
        'take_out_of_service' => Request::bool('take_out_of_service'),
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
        flash('error', 'Type your name to sign off the inspection. Your progress has been saved.');
        redirect(url('inspection-run.php', ['id' => $inspectionId]));
    }

    // A meter that goes backwards is a typo often enough that it is worth
    // stopping for, while the number is still on screen.
    if ($meta['meter_reading'] !== ''
        && (float) $meta['meter_reading'] < (float) $inspection['asset_meter'] - 0.004) {
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
        flash('success', 'Inspection passed. Nice one.');
    }

    redirect(url('inspection-view.php', ['id' => $inspectionId]));
}

$items = Inspection::items($inspectionId);

// Group by section so the page reads like a paper checklist.
$sections = [];

foreach ($items as $item) {
    $sections[(string) $item['section']][] = $item;
}

View::render('inspections/run', [
    'title'          => (string) $inspection['checklist_name'],
    'subtitle'       => (string) $inspection['asset_name'] . ' · ' . (string) $inspection['asset_tag'],
    'activeNav'      => 'inspections.php',
    'hidePageHeader' => false,
    'inspection'     => $inspection,
    'sections'       => $sections,
    'itemCount'      => count($items),
]);
