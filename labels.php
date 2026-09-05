<?php

declare(strict_types=1);

/**
 * Printable machine labels.
 *
 * A sheet of stickers with a QR code on each. Pointing a phone at one opens
 * that machine's page, which is the shortest path there is from "this kart is
 * making a noise" to a logged job.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Models\Asset;
use App\Qr;
use App\Request;
use App\Settings;
use App\View;

Auth::requireLogin();
Acl::requirePermission('assets.view');

$size   = Request::enum('size', ['small', 'medium', 'large'], 'medium');
$action = Request::enum('go', ['view', 'log', 'inspect', 'issue'], 'view');
$ids    = array_filter(array_map('intval', Request::array('ids')));
$single = Request::int('id');

if ($single > 0) {
    $ids = [$single];
}

// Which machines are on the sheet.
$filters = [
    'category_id' => Request::int('category_id'),
    'location_id' => Request::int('location_id'),
];

if ($ids !== []) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $assets = db()->all(
        "SELECT a.*, c.name AS category_name, loc.name AS location_name
         FROM {assets} a
         LEFT JOIN {asset_categories} c ON c.id = a.category_id
         LEFT JOIN {locations} loc ON loc.id = a.location_id
         WHERE a.id IN ({$placeholders}) AND a.deleted_at IS NULL
         ORDER BY c.sort_order, a.name",
        $ids
    );
} else {
    $where  = ['a.deleted_at IS NULL', "a.status <> 'retired'"];
    $params = [];

    if ($filters['category_id'] > 0) {
        $where[]  = 'a.category_id = ?';
        $params[] = $filters['category_id'];
    }

    if ($filters['location_id'] > 0) {
        $where[]  = 'a.location_id = ?';
        $params[] = $filters['location_id'];
    }

    $assets = db()->all(
        'SELECT a.*, c.name AS category_name, loc.name AS location_name
         FROM {assets} a
         LEFT JOIN {asset_categories} c ON c.id = a.category_id
         LEFT JOIN {locations} loc ON loc.id = a.location_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.sort_order, c.name, a.sort_order, a.name
         LIMIT 500',
        $params
    );
}

// Render each code once, here, so the view stays a view.
$labels = [];

foreach ($assets as $asset) {
    $target = absolute_url('qr.php', array_filter([
        'c'  => (string) $asset['qr_slug'],
        'go' => $action === 'view' ? null : $action,
    ]));

    try {
        // The four-module quiet zone is not decoration: readers use it to find
        // the edge of the code, and trimming it to save label space is the
        // classic way to end up with stickers that only scan half the time.
        $svg = Qr::svg($target, 200, Qr::ECC_MEDIUM, 4);
    } catch (Throwable $e) {
        // A site installed at an absurdly long URL. Say so rather than
        // printing a sheet of blank squares.
        log_error('QR generation failed: ' . $e->getMessage());
        flash('error', 'The web address of this site is too long to fit in a QR code. '
            . 'Shorten it in config.php and try again.');
        redirect(url('assets.php'));
    }

    $labels[] = [
        'asset' => $asset,
        'svg'   => $svg,
        'url'   => $target,
    ];
}

if ($labels === []) {
    flash('warning', 'There is nothing to print labels for.');
    redirect(url('assets.php'));
}

audit('print', 'asset', null, 'Printed ' . count($labels) . ' machine label'
    . (count($labels) === 1 ? '' : 's'));

View::render('assets/labels', [
    'title'     => 'Machine labels',
    'docTitle'  => 'Machine labels',
    'printMeta' => [
        'Labels' => (string) count($labels),
        'Size'   => ucfirst($size),
    ],
    'autoPrint' => Request::bool('auto'),
    'labels'    => $labels,
    'size'      => $size,
    'action'    => $action,
    'filters'   => $filters,
    'categories' => Asset::categoryOptions(),
    'locations'  => Asset::locationOptions(),
    'siteName'   => Settings::organizationName(),
], 'layout-print');
