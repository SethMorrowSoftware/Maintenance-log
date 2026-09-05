<?php
/**
 * A sheet of machine labels.
 *
 * Sized for ordinary self-adhesive label stock. The controls at the top do not
 * print, so what comes out of the printer is stickers and nothing else.
 */

use App\View;

$sizes = [
    'small'  => 'Small — 30 to a sheet (Avery 5160)',
    'medium' => 'Medium — 10 to a sheet (Avery 5163)',
    'large'  => 'Large — 6 to a sheet (Avery 5164)',
];

$actions = [
    'view'    => 'Open the machine’s page',
    'log'     => 'Start a maintenance log',
    'inspect' => 'Start an inspection',
    'issue'   => 'Report a problem',
];
?>

<div class="no-print card mb-5">
    <div class="card-header">
        <div>
            <h2 class="card-title"><?= icon('qr-code', '', 18) ?> Labels to print</h2>
            <p class="card-subtitle">
                Stick one on each machine. Pointing a phone camera at it opens that
                machine here — no typing, no hunting through a list.
            </p>
        </div>
    </div>
    <form method="get" action="<?= e(url('labels.php')) ?>" class="filter-bar">
        <?php View::partial('form-field', [
            'name'    => 'category_id',
            'label'   => 'Category',
            'type'    => 'select',
            'value'   => $filters['category_id'],
            'options' => $categories,
            'empty'   => 'All',
            'noOld'   => true,
        ]); ?>

        <?php View::partial('form-field', [
            'name'    => 'location_id',
            'label'   => 'Where',
            'type'    => 'select',
            'value'   => $filters['location_id'],
            'options' => $locations,
            'empty'   => 'Everywhere',
            'noOld'   => true,
        ]); ?>

        <?php View::partial('form-field', [
            'name'    => 'size',
            'label'   => 'Label size',
            'type'    => 'select',
            'value'   => $size,
            'options' => $sizes,
            'empty'   => null,
            'noOld'   => true,
        ]); ?>

        <?php View::partial('form-field', [
            'name'    => 'go',
            'label'   => 'Scanning it should',
            'type'    => 'select',
            'value'   => $action,
            'options' => $actions,
            'empty'   => null,
            'noOld'   => true,
            'hint'    => 'Whoever scans still has to be signed in.',
        ]); ?>

        <div class="filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">
                <?= icon('refresh', '', 15) ?> Update
            </button>
            <button type="button" class="btn btn-secondary btn-sm" data-print>
                <?= icon('printer', '', 15) ?> Print
            </button>
        </div>
    </form>
</div>

<div class="label-sheet is-<?= e($size) ?>">
    <?php foreach ($labels as $label): ?>
        <?php $asset = $label['asset']; ?>
        <div class="label">
            <div class="label-code"><?= $label['svg'] // built by App\Qr, not user input ?></div>
            <div class="label-body">
                <div class="label-tag"><?= e((string) $asset['asset_tag']) ?></div>
                <div class="label-name"><?= e((string) $asset['name']) ?></div>
                <?php if (!empty($asset['location_name'])): ?>
                    <div class="label-meta"><?= e((string) $asset['location_name']) ?></div>
                <?php endif; ?>
                <div class="label-org"><?= e($siteName) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
