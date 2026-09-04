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

foreach ($filters as $config) {
    if (($config['value'] ?? '') !== '') {
        $hasActive = true;
        break;
    }
}
?>
<form method="get" action="<?= e(url($action)) ?>" class="filter-bar no-print" data-filter-form>
    <?php foreach ($hidden as $key => $val): ?>
        <input type="hidden" name="<?= e($key) ?>" value="<?= e($val) ?>">
    <?php endforeach; ?>

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
