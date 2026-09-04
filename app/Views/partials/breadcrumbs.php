<?php
/**
 * Variables: $breadcrumbs — [['label' => ..., 'url' => ...], ...]
 * The last entry is rendered as the current page and is never a link.
 */

$breadcrumbs = $breadcrumbs ?? [];

if ($breadcrumbs === []) {
    return;
}

$last = count($breadcrumbs) - 1;
?>
<nav class="breadcrumbs no-print" aria-label="Breadcrumb">
    <?php foreach ($breadcrumbs as $index => $crumb): ?>
        <?php if ($index > 0): ?>
            <span class="sep" aria-hidden="true"><?= icon('chevron-right', '', 13) ?></span>
        <?php endif; ?>

        <?php if ($index === $last || empty($crumb['url'])): ?>
            <span class="current" aria-current="page"><?= e($crumb['label']) ?></span>
        <?php else: ?>
            <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
