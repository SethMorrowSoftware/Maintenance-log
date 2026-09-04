<?php
/**
 * Variables: $icon, $title, $message, $actionLabel, $actionUrl, $actionIcon,
 *            $secondaryLabel, $secondaryUrl
 */

$icon           = $icon           ?? 'search';
$title          = $title          ?? 'Nothing here yet';
$message        = $message        ?? '';
$actionLabel    = $actionLabel    ?? '';
$actionUrl      = $actionUrl      ?? '';
$actionIcon     = $actionIcon     ?? 'plus';
$secondaryLabel = $secondaryLabel ?? '';
$secondaryUrl   = $secondaryUrl   ?? '';
?>
<div class="empty-state">
    <span class="empty-icon"><?= icon($icon, '', 26) ?></span>
    <h3 class="empty-title"><?= e($title) ?></h3>
    <?php if ($message !== ''): ?>
        <p class="empty-message"><?= e($message) ?></p>
    <?php endif; ?>
    <?php if ($actionLabel !== '' && $actionUrl !== ''): ?>
        <div class="flex gap-2 justify-center flex-wrap">
            <a class="btn btn-primary" href="<?= e($actionUrl) ?>">
                <?= icon($actionIcon, '', 17) ?> <?= e($actionLabel) ?>
            </a>
            <?php if ($secondaryLabel !== '' && $secondaryUrl !== ''): ?>
                <a class="btn btn-secondary" href="<?= e($secondaryUrl) ?>"><?= e($secondaryLabel) ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
