<?php
/**
 * A delete control.
 *
 * Always a POST form, never a link: a GET that deletes can be triggered by a
 * prefetcher, a crawler, or an <img> tag on another site.
 *
 * Variables: $url, $label, $message, $id, $buttonClass, $iconOnly, $extra (array)
 */

$url         = $url         ?? '';
$label       = $label       ?? 'Delete';
$message     = $message     ?? 'This cannot be undone. Are you sure?';
$id          = $id          ?? null;
$buttonClass = $buttonClass ?? 'btn btn-danger-outline btn-sm';
$iconOnly    = $iconOnly    ?? false;
$extra       = $extra       ?? [];

if ($url === '') {
    return;
}
?>
<form method="post" action="<?= e($url) ?>" class="inline-form" style="display:inline">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <?php if ($id !== null): ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php endif; ?>
    <?php foreach ($extra as $key => $value): ?>
        <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
    <?php endforeach; ?>
    <button type="submit" class="<?= e($buttonClass) ?>"
            data-confirm="<?= attr($message) ?>"
            data-confirm-title="<?= attr($label) ?>"
            data-confirm-danger="1"
            <?= $iconOnly ? 'aria-label="' . attr($label) . '" title="' . attr($label) . '"' : '' ?>>
        <?= icon('trash', '', 15) ?><?= $iconOnly ? '' : ' ' . e($label) ?>
    </button>
</form>
