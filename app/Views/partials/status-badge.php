<?php
/**
 * Variables: $value, $vocabulary (asset|workorder|priority|inspection|...),
 *            $withIcon (bool), $large (bool)
 */

use App\Status;

$value      = (string) ($value ?? '');
$vocabulary = $vocabulary ?? 'asset';
$withIcon   = $withIcon   ?? false;
$large      = $large      ?? false;

if ($value === '') {
    echo '<span class="text-subtle">—</span>';
    return;
}

$tone  = Status::tone($value, $vocabulary);
$label = Status::label($value, $vocabulary);
?>
<span class="badge badge-<?= e($tone) ?><?= $large ? ' badge-lg' : '' ?>">
    <?php if ($withIcon): ?><?= icon(Status::icon($value, $vocabulary), 'badge-icon', 13) ?><?php endif; ?>
    <?= e($label) ?>
</span>
