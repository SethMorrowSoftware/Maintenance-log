<?php
/**
 * Variables: $tabs — [key => ['label' => ..., 'icon' => ..., 'count' => ..., 'url' => ...]]
 *            $active — the current key
 *            $mode — 'link' (page reload) or 'panel' (client-side switching)
 */

$tabs   = $tabs   ?? [];
$active = $active ?? array_key_first($tabs);
$mode   = $mode   ?? 'link';

if ($tabs === []) {
    return;
}
?>
<div class="tabs" role="tablist">
    <?php foreach ($tabs as $key => $tab): ?>
        <?php
        $isActive = (string) $key === (string) $active;
        $label    = (string) ($tab['label'] ?? $key);
        $count    = $tab['count'] ?? null;
        ?>
        <?php if ($mode === 'link'): ?>
            <a class="tab<?= $isActive ? ' is-active' : '' ?>"
               href="<?= e((string) ($tab['url'] ?? '#')) ?>"
               role="tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>">
                <?php if (!empty($tab['icon'])): ?><?= icon((string) $tab['icon'], '', 16) ?><?php endif; ?>
                <?= e($label) ?>
                <?php if ($count !== null): ?><span class="tab-count"><?= (int) $count ?></span><?php endif; ?>
            </a>
        <?php else: ?>
            <button type="button" class="tab<?= $isActive ? ' is-active' : '' ?>"
                    data-toggle="tab" data-target="<?= e('#tab-' . $key) ?>"
                    role="tab" aria-selected="<?= $isActive ? 'true' : 'false' ?>"
                    aria-controls="<?= e('tab-' . $key) ?>">
                <?php if (!empty($tab['icon'])): ?><?= icon((string) $tab['icon'], '', 16) ?><?php endif; ?>
                <?= e($label) ?>
                <?php if ($count !== null): ?><span class="tab-count"><?= (int) $count ?></span><?php endif; ?>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
