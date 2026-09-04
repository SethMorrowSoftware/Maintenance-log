<?php
/**
 * The universal form field.
 *
 * Every input in the application goes through here, so a label, a hint, the
 * required marker, the inline validation error and the repopulated old value
 * all behave identically on every screen.
 *
 * Variables
 *   $name        string   field name (required)
 *   $label       string   visible label; pass '' to omit
 *   $type        string   text|email|password|number|decimal|money|date|
 *                         datetime|time|select|textarea|checkbox|radio|
 *                         file|hidden|tel|url|search|color|meter
 *   $value       mixed    current value; old input wins automatically
 *   $options     array    value => label, for select and radio
 *   $groups      array    optgroup label => [value => label], for select
 *   $required    bool
 *   $hint        string   help text under the field
 *   $placeholder string
 *   $attrs       array    extra HTML attributes
 *   $rows        int      textarea rows
 *   $prefix      string   input-group text before the field (e.g. "$")
 *   $suffix      string   input-group text after the field (e.g. "hours")
 *   $empty       string   the blank choice label for a select
 *   $checkLabel  string   the text beside a checkbox
 *   $noOld       bool     do not repopulate from old input
 *   $wrapperClass string
 */

$name        = $name        ?? '';
$label       = $label       ?? '';
$type        = $type        ?? 'text';
$options     = $options     ?? [];
$groups      = $groups      ?? [];
$required    = $required    ?? false;
$hint        = $hint        ?? '';
$placeholder = $placeholder ?? '';
$attrs       = $attrs       ?? [];
$rows        = $rows        ?? 4;
$prefix      = $prefix      ?? '';
$suffix      = $suffix      ?? '';
$empty       = $empty       ?? null;
$checkLabel  = $checkLabel  ?? '';
$noOld       = $noOld       ?? false;
$wrapperClass = $wrapperClass ?? '';

if ($name === '') {
    return;
}

$value = $value ?? '';

// Old input wins after a rejected submission, so the user does not retype.
if (!$noOld) {
    $old = old($name, null);

    if ($old !== null && $old !== '') {
        $value = $old;
    } elseif ($old === '' && has_error($name)) {
        $value = '';
    }
}

$error   = error_for($name);
$hasErr  = $error !== '';
$id      = 'f_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
$descBy  = [];

if ($hint !== '')  { $descBy[] = $id . '_hint'; }
if ($hasErr)       { $descBy[] = $id . '_err'; }

/** Build the shared attribute string. */
$buildAttrs = static function (array $extra) use ($attrs, $descBy, $required, $hasErr): string {
    $merged = array_merge($extra, $attrs);
    $out    = '';

    foreach ($merged as $key => $val) {
        if ($val === null || $val === false) {
            continue;
        }

        if ($val === true) {
            $out .= ' ' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8');
            continue;
        }

        $out .= ' ' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8')
              . '="' . htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }

    if ($required) {
        $out .= ' required';
    }

    if ($descBy !== []) {
        $out .= ' aria-describedby="' . implode(' ', $descBy) . '"';
    }

    if ($hasErr) {
        $out .= ' aria-invalid="true"';
    }

    return $out;
};

// Hidden fields need none of the wrapper.
if ($type === 'hidden') {
    echo '<input type="hidden" name="' . attr($name) . '" value="' . attr($value) . '">';
    return;
}

/** Map our type names onto real HTML input types. */
$inputType = $type;
$inputMode = null;
$step      = null;

switch ($type) {
    case 'decimal':
    case 'money':
        $inputType = 'number';
        $step      = '0.01';
        $inputMode = 'decimal';
        break;
    case 'meter':
        $inputType = 'number';
        $step      = '0.01';
        $inputMode = 'decimal';
        break;
    case 'number':
        $inputMode = 'numeric';
        break;
    case 'datetime':
        $inputType = 'datetime-local';
        break;
    case 'tel':
        $inputMode = 'tel';
        break;
}
?>
<div class="form-group<?= $hasErr ? ' has-error' : '' ?><?= $wrapperClass !== '' ? ' ' . e($wrapperClass) : '' ?>">

    <?php if ($type === 'checkbox'): ?>

        <label class="form-check" for="<?= e($id) ?>">
            <input type="hidden" name="<?= e($name) ?>" value="0">
            <input type="checkbox" id="<?= e($id) ?>" name="<?= e($name) ?>" value="1"
                   <?= $value ? 'checked' : '' ?><?= $buildAttrs([]) ?>>
            <span class="form-check-label">
                <?= e($checkLabel !== '' ? $checkLabel : $label) ?>
                <?php if ($hint !== ''): ?><small id="<?= e($id) ?>_hint"><?= e($hint) ?></small><?php endif; ?>
            </span>
        </label>

    <?php else: ?>

        <?php if ($label !== ''): ?>
            <label class="form-label" for="<?= e($id) ?>">
                <?= e($label) ?><?php if ($required): ?><span class="required" aria-hidden="true">*</span><?php endif; ?>
            </label>
        <?php endif; ?>

        <?php if ($type === 'select'): ?>

            <select class="form-select" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $buildAttrs([]) ?>>
                <?php if ($empty !== null): ?>
                    <option value=""><?= e($empty) ?></option>
                <?php endif; ?>

                <?php foreach ($groups as $groupLabel => $groupOptions): ?>
                    <optgroup label="<?= attr($groupLabel) ?>">
                        <?php foreach ($groupOptions as $optValue => $optLabel): ?>
                            <option value="<?= attr($optValue) ?>"<?= selected($optValue, $value) ?>><?= e($optLabel) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>

                <?php foreach ($options as $optValue => $optLabel): ?>
                    <option value="<?= attr($optValue) ?>"<?= selected($optValue, $value) ?>><?= e($optLabel) ?></option>
                <?php endforeach; ?>
            </select>

        <?php elseif ($type === 'textarea'): ?>

            <textarea class="form-textarea" id="<?= e($id) ?>" name="<?= e($name) ?>"
                      rows="<?= (int) $rows ?>"
                      <?= $placeholder !== '' ? 'placeholder="' . attr($placeholder) . '"' : '' ?>
                      <?= $buildAttrs([]) ?>><?= e($value) ?></textarea>

        <?php elseif ($type === 'radio'): ?>

            <div role="radiogroup"<?= $label !== '' ? ' aria-label="' . attr($label) . '"' : '' ?>>
                <?php foreach ($options as $optValue => $optLabel): ?>
                    <label class="form-check" for="<?= e($id . '_' . $optValue) ?>">
                        <input type="radio" id="<?= e($id . '_' . $optValue) ?>" name="<?= e($name) ?>"
                               value="<?= attr($optValue) ?>"<?= checked($optValue, $value) ?>
                               <?= $required ? 'required' : '' ?>>
                        <span class="form-check-label"><?= e($optLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

        <?php elseif ($type === 'file'): ?>

            <input type="file" class="form-input" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $buildAttrs([]) ?>>

        <?php elseif ($prefix !== '' || $suffix !== ''): ?>

            <div class="input-group">
                <?php if ($prefix !== ''): ?>
                    <span class="input-addon"><?= e($prefix) ?></span>
                <?php endif; ?>

                <input type="<?= e($inputType) ?>" class="form-input" id="<?= e($id) ?>"
                       name="<?= e($name) ?>" value="<?= attr($value) ?>"
                       <?= $placeholder !== '' ? 'placeholder="' . attr($placeholder) . '"' : '' ?>
                       <?= $step !== null ? 'step="' . attr($step) . '"' : '' ?>
                       <?= $inputMode !== null ? 'inputmode="' . attr($inputMode) . '"' : '' ?>
                       <?= $buildAttrs([]) ?>>

                <?php if ($suffix !== ''): ?>
                    <span class="input-addon"><?= e($suffix) ?></span>
                <?php endif; ?>
            </div>

        <?php elseif ($type === 'password'): ?>

            <div class="password-field">
                <input type="password" class="form-input" id="<?= e($id) ?>" name="<?= e($name) ?>"
                       value="<?= attr($value) ?>" autocomplete="<?= e((string) ($attrs['autocomplete'] ?? 'current-password')) ?>"
                       <?= $placeholder !== '' ? 'placeholder="' . attr($placeholder) . '"' : '' ?>
                       <?= $buildAttrs([]) ?>>
                <button type="button" class="password-toggle" data-password-toggle
                        aria-label="Show password" title="Show password">
                    <?= icon('eye', '', 17) ?>
                </button>
            </div>

        <?php else: ?>

            <input type="<?= e($inputType) ?>" class="form-input" id="<?= e($id) ?>"
                   name="<?= e($name) ?>" value="<?= attr($value) ?>"
                   <?= $placeholder !== '' ? 'placeholder="' . attr($placeholder) . '"' : '' ?>
                   <?= $step !== null ? 'step="' . attr($step) . '"' : '' ?>
                   <?= $inputMode !== null ? 'inputmode="' . attr($inputMode) . '"' : '' ?>
                   <?= $buildAttrs([]) ?>>

        <?php endif; ?>

        <?php if ($hint !== ''): ?>
            <div class="form-hint" id="<?= e($id) ?>_hint"><?= e($hint) ?></div>
        <?php endif; ?>

    <?php endif; ?>

    <?php if ($hasErr): ?>
        <div class="form-error" id="<?= e($id) ?>_err">
            <?= icon('alert-circle', '', 14) ?>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
</div>
