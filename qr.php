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

$code = trim(Request::string('c'));

if ($code === '') {
    $code = trim(Request::string('qr'));
}

if ($code === '') {
    flash('error', 'That code could not be read. Try scanning it again, or find the '
        . 'machine on the assets list.');
    redirect(url('assets.php'));
}

$asset = Asset::findByTagOrSlug($code);

if ($asset === null) {
    flash('warning', 'No machine matches the code on that label. It may have been '
        . 'retired, or the label may belong to a different system.');
    redirect(url('assets.php', ['q' => $code]));
}

$assetId = (int) $asset['id'];

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
