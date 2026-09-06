<?php
/**
 * A responsive filter form built from a config array.
 *
 * Variables:
 *   $filters — [name => ['label' =>, 'type' => 'text|select|date',
 *                        'options' =>, 'value' =>, 'placeholder' =>, 'empty' =>]]
 *   $action  — form target, defaults to the current script
 *   $hidden  — extra hidden fields to preserve
 *   $resetUrl — where "Clear" goes
 */

use App\Request;
use App\View;

$filters  = $filters  ?? [];
$action   = $action   ?? Request::script();
$hidden   = $hidden   ?? [];
$resetUrl = $resetUrl ?? url(Request::script());

if ($filters === []) {
    return;
}

$hasActive = false;
$active    = 0;
$position  = 0;
$folded    = 0; // filters, beyond the first, that are set — they keep the bar open on a phone

foreach ($filters as $config) {
    $position++;
    $value = $config['value'] ?? '';

    // An unset id filter arrives as 0, which is "nothing chosen", not a choice.
    if ($value !== '' && $value !== null && $value !== 0 && $value !== '0') {
        $hasActive = true;
        $active++;

        // A preset date range is not somebody's choice; it does not hold the bar open.
        if ($position > 1 && (string) ($config['type'] ?? 'text') !== 'date') {
            $folded++;
        }
    }
}
?>
<form method="get" action="<?= e(url($action)) ?>" class="filter-bar no-print<?= $folded === 0 && count($filters) > 1 ? ' is-collapsed' : '' ?>" data-filter-form>
    <?php foreach ($hidden as $key => $val): ?>
        <input type="hidden" name="<?= e($key) ?>" value="<?= e($val) ?>">
    <?php endforeach; ?>

    <?php if (count($filters) > 1): ?>
        <button type="button" class="btn btn-secondary btn-sm filter-toggle" data-filter-toggle
                aria-expanded="<?= $folded === 0 ? 'false' : 'true' ?>">
            <span><?= icon('filter', '', 15) ?> Filters<?= $active > 0 ? ' (' . (int) $active . ' on)' : '' ?></span>
            <?= icon('chevron-down', '', 15) ?>
        </button>
    <?php endif; ?>

    <?php foreach ($filters as $fieldName => $config): ?>
        <?php
        View::partial('form-field', [
            'name'        => $fieldName,
            'label'       => (string) ($config['label'] ?? ''),
            'type'        => (string) ($config['type'] ?? 'text'),
            'value'       => $config['value'] ?? '',
            'options'     => $config['options'] ?? [],
            'placeholder' => (string) ($config['placeholder'] ?? ''),
            'empty'       => $config['empty'] ?? (($config['type'] ?? '') === 'select' ? 'All' : null),
            'noOld'       => true,
            'attrs'       => $config['attrs'] ?? [],
        ]);
        ?>
    <?php endforeach; ?>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary btn-sm">
            <?= icon('filter', '', 15) ?> Apply
        </button>
        <?php if ($hasActive): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($resetUrl) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
