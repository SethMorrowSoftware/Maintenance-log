<?php
/**
 * A dashboard KPI tile.
 *
 * Variables: $label, $value, $icon, $tone (ok|warn|danger|info|muted|brand),
 *            $href, $sub, $trend ('up'|'down'|'flat'), $trendLabel
 */

$label      = $label      ?? '';
$value      = $value      ?? '0';
$iconName   = $icon       ?? 'activity';
$tone       = $tone       ?? 'brand';
$href       = $href       ?? '';
$sub        = $sub        ?? '';
$trend      = $trend      ?? '';
$trendLabel = $trendLabel ?? '';

$tag   = $href !== '' ? 'a' : 'div';
$attrs = $href !== '' ? ' href="' . attr($href) . '"' : '';
?>
<<?= $tag ?> class="stat-card"<?= $attrs ?>>
    <span class="stat-icon tone-<?= e($tone) ?>"><?= icon($iconName, '', 21) ?></span>
    <span class="stat-content">
        <span class="stat-value"><?= is_string($value) ? e($value) : e((string) $value) ?></span>
        <span class="stat-label"><?= e($label) ?></span>
        <?php if ($sub !== ''): ?>
            <span class="stat-sub"><?= e($sub) ?></span>
        <?php endif; ?>
        <?php if ($trend !== '' && $trendLabel !== ''): ?>
            <span class="stat-trend is-<?= e($trend) ?>">
                <?= icon($trend === 'up' ? 'trending-up' : ($trend === 'down' ? 'trending-down' : 'minus'), '', 13) ?>
                <?= e($trendLabel) ?>
            </span>
        <?php endif; ?>
    </span>
</<?= $tag ?>>
