<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\Csrf;
use App\Models\Part;
use App\Request;
use App\Settings;
use App\View;

Auth::requireLogin();
Acl::requirePermission('parts.view');

$id   = Request::int('id');
$part = Part::find($id);

if ($part === null) {
    abort(404, 'That part does not exist. It may have been deleted.');
}

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');

    if ($action === 'delete') {
        Acl::requirePermission('parts.manage');

        if (Part::delete($id)) {
            flash('success', 'Part removed from the list. Past maintenance logs still show it.');
            redirect(url('parts.php'));
        }

        flash('error', 'That part could not be deleted.');
    }

    if ($action === 'adjust') {
        Acl::requirePermission('parts.adjust');

        $amount = (float) str_replace(',', '', Request::string('amount'));
        $way    = Request::string('way');

        if ($amount <= 0) {
            flash('error', 'Type how many you took or put back.');
        } else {
            $delta  = $way === 'out' ? -$amount : $amount;
            $result = Part::adjustStock(
                $id,
                $delta,
                $way === 'out' ? 'out' : 'in',
                'manual',
                null,
                Request::string('notes')
            );

            audit('stock.adjust', 'part', $id,
                (string) $part['name'] . ': ' . ($delta > 0 ? '+' : '') . decimal($delta)
                . ' → ' . decimal($result['balance']) . ' on hand');

            flash('success', 'Now ' . decimal($result['balance']) . ' '
                . (string) $part['unit_of_measure'] . ' on hand.');
        }
    }

    redirect(url('part-view.php', ['id' => $id]));
}

$actions = '';

if (can('parts.manage')) {
    $actions .= '<a class="btn btn-secondary" href="' . e(url('part-edit.php', ['id' => $id])) . '">'
        . icon('edit', '', 17) . ' Edit</a>';
}

View::render('parts/view', [
    'title'       => (string) $part['name'],
    'subtitle'    => (string) $part['part_number'],
    'activeNav'   => 'parts.php',
    'pageActions' => $actions,
    'breadcrumbs' => [
        ['label' => 'Parts', 'url' => url('parts.php')],
        ['label' => (string) $part['name']],
    ],
    'part'         => $part,
    'transactions' => Part::transactions($id, 60),
    'usage'        => Part::usage($id, 30),
    'currency'     => Settings::currency(),
]);
