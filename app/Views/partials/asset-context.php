<?php
/**
 * "About this machine", beside a form.
 *
 * Somebody logging "brake noise" should see, without leaving the form, that
 * the pads were done a fortnight ago and that there is an open work order for
 * a squeal. This is that panel: status, meter, and the last few things that
 * happened, with a link to the full history.
 *
 * Rendered by the server for the machine the form opened with, and refilled
 * by core.js (initAssetContext) when a different machine is picked from the
 * list. The markup is always present, hidden when there is no machine yet,
 * so the script only ever swaps text in and out of it.
 *
 * Variables: $asset (row or null), $events (from Asset::timeline), $selectName
 */

use App\Dates;
use App\Status;

$asset      = $asset      ?? null;
$events     = $events     ?? [];
$selectName = $selectName ?? 'asset_id';
$hasMeter   = $asset !== null && (string) $asset['meter_type'] !== 'none';
?>
<div data-asset-context data-asset-context-for="<?= attr($selectName) ?>" <?= $asset === null ? 'hidden' : '' ?>>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">About this machine</h3>
        </div>
        <div class="card-body context-machine">
            <a class="context-machine-name" data-ctx-name
               href="<?= $asset === null ? '#' : e(url('asset-view.php', ['id' => (int) $asset['id']])) ?>">
                <?= $asset === null ? '' : e((string) $asset['name']) ?>
            </a>
            <div class="text-sm text-subtle" data-ctx-tag><?= $asset === null ? '' : e((string) $asset['asset_tag']) ?></div>
            <div class="context-facts">
                <span class="badge badge-<?= $asset === null ? 'muted' : e(Status::tone((string) $asset['status'], 'asset')) ?>" data-ctx-status>
                    <?= $asset === null ? '' : e(Status::label((string) $asset['status'], 'asset')) ?>
                </span>
                <span class="text-sm text-muted" data-ctx-meter <?= $hasMeter ? '' : 'hidden' ?>>
                    <?= icon('gauge', '', 14) ?>
                    <span data-ctx-meter-value><?= $hasMeter ? e(decimal($asset['meter_reading']) . ' ' . (string) $asset['meter_type']) : '' ?></span>
                </span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= icon('history', '', 17) ?> Recent work on it</h3>
        </div>
        <div class="card-body" data-ctx-empty <?= $asset !== null && $events === [] ? '' : 'hidden' ?>>
            <p class="text-sm text-muted" style="margin:0">Nothing recorded yet. You are first.</p>
        </div>
        <ul class="context-list" data-ctx-list <?= $events === [] ? 'hidden' : '' ?>>
            <?php foreach ($events as $event): ?>
                <li class="context-item tone-<?= e((string) $event['tone']) ?>">
                    <div class="context-when"><?= e(Dates::ago((string) $event['when'])) ?></div>
                    <div class="context-body">
                        <span class="context-label"><?= e((string) $event['label']) ?></span>
                        <?php if ((string) $event['url'] !== ''): ?>
                            <a href="<?= e((string) $event['url']) ?>"><?= e((string) $event['title']) ?></a>
                        <?php else: ?>
                            <?= e((string) $event['title']) ?>
                        <?php endif; ?>
                        <?php if ((string) $event['detail'] !== ''): ?>
                            <div class="context-detail"><?= e(str_limit(strtok((string) $event['detail'], "\n") ?: '', 110)) ?></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="card-footer" data-ctx-footer <?= $events === [] ? 'hidden' : '' ?>>
            <a class="btn btn-ghost btn-sm btn-block" data-ctx-history
               href="<?= $asset === null ? '#' : e(url('asset-view.php', ['id' => (int) $asset['id'], 'tab' => 'timeline'])) ?>">
                Full history <?= icon('chevron-right', '', 14) ?>
            </a>
        </div>
    </div>
</div>
