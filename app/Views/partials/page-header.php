<?php
/**
 * A standalone page header, for views that set $hidePageHeader on the layout
 * and want to draw their own.
 *
 * Variables: $title, $subtitle, $actions (HTML), $breadcrumbs
 */

use App\View;

$title       = $title       ?? '';
$subtitle    = $subtitle    ?? '';
$actions     = $actions     ?? '';
$breadcrumbs = $breadcrumbs ?? [];
?>
<?php if ($breadcrumbs !== []): ?>
    <?php View::partial('breadcrumbs', ['breadcrumbs' => $breadcrumbs]); ?>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($title) ?></h1>
        <?php if ($subtitle !== ''): ?>
            <p class="page-subtitle"><?= e($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($actions !== ''): ?>
        <div class="page-actions no-print"><?= $actions ?></div>
    <?php endif; ?>
</div>
