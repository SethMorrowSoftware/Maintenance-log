<?php
/**
 * A delete control.
 *
 * Always a POST form, never a link: a GET that deletes can be triggered by a
 * prefetcher, a crawler, or an <img> tag on another site.
 *
 * On an edit page the button sits inside the edit form, and HTML does not
 * allow a form inside a form — the browser drops the inner one and the
 * button would save the record instead of deleting it. So the two halves
 * can be rendered apart: the button (with $buttonFor) where it belongs on
 * the page, and the form (with $formId) after the edit form closes.
 *
 * Variables: $url, $label, $message, $id, $buttonClass, $iconOnly, $extra (array),
 *            $formId (render the form only, with this id),
 *            $buttonFor (render the button only, submitting the form with this id)
 */

$url         = $url         ?? '';
$label       = $label       ?? 'Delete';
$message     = $message     ?? 'This cannot be undone. Are you sure?';
$id          = $id          ?? null;
$buttonClass = $buttonClass ?? 'btn btn-danger-outline btn-sm';
$iconOnly    = $iconOnly    ?? false;
$extra       = $extra       ?? [];
$formId      = $formId      ?? '';
$buttonFor   = $buttonFor   ?? '';

if ($buttonFor === '' && $url === '') {
    return;
}
?>
<?php if ($buttonFor === ''): ?>
<form method="post" action="<?= e($url) ?>" class="inline-form" style="display:inline"<?= $formId !== '' ? ' id="' . attr($formId) . '"' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <?php if ($id !== null): ?>
        <input type="hidden" name="id" value="<?= (int) $id ?>">
    <?php endif; ?>
    <?php foreach ($extra as $key => $value): ?>
        <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
    <?php endforeach; ?>
<?php endif; ?>
<?php if ($formId === ''): ?>
    <button type="submit" class="<?= e($buttonClass) ?>"
            <?= $buttonFor !== '' ? 'form="' . attr($buttonFor) . '"' : '' ?>
            data-confirm="<?= attr($message) ?>"
            data-confirm-title="<?= attr($label) ?>"
            data-confirm-danger="1"
            <?= $iconOnly ? 'aria-label="' . attr($label) . '" title="' . attr($label) . '"' : '' ?>>
        <?= icon('trash', '', 15) ?><?= $iconOnly ? '' : ' ' . e($label) ?>
    </button>
<?php endif; ?>
<?php if ($buttonFor === ''): ?>
</form>
<?php endif; ?>
