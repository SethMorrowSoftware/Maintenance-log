<?php

declare(strict_types=1);

/**
 * Where a scanned sticker lands.
 *
 * A phone camera pointed at the label on a kart opens this, and this sends it
 * on to the right page. It is deliberately the shortest, dumbest thing in the
 * application: look up the code, send them where they were going.
 *
 * Somebody not signed in is sent to the login page and then bounced straight
 * back here, so scanning still works as the first thing you do in the morning.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Models\Asset;
use App\Request;

// Sign in first, then come straight back here — requireLogin carries the whole
// scanned address forward, so nothing is lost.
Auth::requireLogin();
require_feature('labels');

// Somebody who cannot see machines at all — checks-only staff — can still
// scan a sticker to start a check; anything else about the machine stays
// out of reach, and a label they cannot use sends them back to their checks.
$seesMachines = can('assets.view');
$home         = $seesMachines ? 'assets.php' : 'checks.php';

if (!$seesMachines && !can('inspections.perform')) {
    abort(403, 'You do not have permission to look up ' . asset_word(true) . '.');
}

$code = trim(Request::string('c'));

if ($code === '') {
    $code = trim(Request::string('qr'));
}

if ($code === '') {
    flash('error', 'That code could not be read. Try scanning it again'
        . ($seesMachines ? ', or find the ' . asset_word() . ' on the ' . asset_word(true) . ' list.' : '.'));
    redirect(url($home));
}

$asset = Asset::findByTagOrSlug($code);

if ($asset === null) {
    flash('warning', 'No ' . asset_word() . ' matches the code on that label. It may have been '
        . 'retired, or the label may belong to a different system.');
    redirect(url($home, $seesMachines ? ['q' => $code] : []));
}

$assetId = (int) $asset['id'];

if (!$seesMachines) {
    // The only thing this person can do with a machine is check it; the
    // runner decides whether it is in their area.
    redirect(url('inspection-run.php', ['asset_id' => $assetId]));
}

audit('qr.scan', 'asset', $assetId, 'Scanned the label on ' . (string) $asset['name']);

// Somebody who scans a sticker is nearly always standing next to the machine
// about to do something to it, so honour "what next" when the label says so.
switch (Request::string('go')) {
    case 'log':
        if (can('logs.create')) {
            redirect(url('log-edit.php', ['asset_id' => $assetId]));
        }
        break;

    case 'inspect':
        if (can('inspections.perform')) {
            redirect(url('inspection-run.php', ['asset_id' => $assetId]));
        }
        break;

    case 'issue':
        if (can('workorders.create')) {
            redirect(url('workorder-edit.php', ['asset_id' => $assetId]));
        }
        break;
}

redirect(url('asset-view.php', ['id' => $assetId]));
