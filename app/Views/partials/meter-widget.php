<?php
/**
 * The current meter reading for an asset, with a quick-update control.
 *
 * Variables: $asset (array), $canUpdate (bool), $compact (bool)
 */

use App\Dates;

$asset     = $asset     ?? [];
$canUpdate = $canUpdate ?? can('assets.meter');
$compact   = $compact   ?? false;

$meterType = (string) ($asset['meter_type'] ?? 'none');

if ($meterType === 'none') {
    if (!$compact) {
        echo '<span class="text-subtle">No meter fitted</span>';
    }
    return;
}

$reading = (float) ($asset['meter_reading'] ?? 0);
$updated = $asset['meter_updated_at'] ?? null;
?>
<div class="flex items-center gap-3 flex-wrap">
    <div>
        <span class="stat-value" style="font-size:var(--text-xl)"><?= e(decimal($reading)) ?></span>
        <span class="text-muted"><?= e($meterType) ?></span>
        <?php if (!$compact): ?>
            <div class="text-subtle text-xs">
                <?php if ($updated !== null): ?>
                    Updated <?= e(Dates::ago((string) $updated)) ?>
                <?php else: ?>
                    Never recorded
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canUpdate && !empty($asset['id'])): ?>
        <button type="button" class="btn btn-secondary btn-sm no-print"
                data-meter-update="<?= (int) $asset['id'] ?>"
                data-meter-current="<?= e((string) $reading) ?>"
                data-meter-unit="<?= e($meterType) ?>"
                data-meter-asset="<?= attr((string) ($asset['name'] ?? '')) ?>">
            <?= icon('gauge', '', 15) ?> Update
        </button>
    <?php endif; ?>
</div>
