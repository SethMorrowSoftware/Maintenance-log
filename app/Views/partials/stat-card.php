<?php
/**
 * A dashboard KPI tile.
 *
 * Variables: $label, $value, $icon, $tone (ok|warn|danger|info|muted|brand),
 *            $href, $sub, $trend ('up'|'down'|'flat'), $trendLabel,
 *            $trendInvert (bool)
 *
 * $trendInvert flips which direction is good news. For most figures a rise is
 * positive, but for cost and downtime a fall is what you want to see, and
 * painting a 47% saving in red is simply wrong.
 */

$label       = $label       ?? '';
$value       = $value       ?? '0';
$iconName    = $icon        ?? 'activity';
$tone        = $tone        ?? 'brand';
$href        = $href        ?? '';
$sub         = $sub         ?? '';
$trend       = $trend       ?? '';
$trendLabel  = $trendLabel  ?? '';
$trendInvert = $trendInvert ?? false;

// The arrow always shows the real direction; only the colour flips.
$trendClass = $trend;

if ($trendInvert && $trend === 'up') {
    $trendClass = 'down';
} elseif ($trendInvert && $trend === 'down') {
    $trendClass = 'up';
}

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
            <span class="stat-trend is-<?= e($trendClass) ?>">
                <?= icon($trend === 'up' ? 'trending-up' : ($trend === 'down' ? 'trending-down' : 'minus'), '', 13) ?>
                <?= e($trendLabel) ?>
            </span>
        <?php endif; ?>
    </span>
</<?= $tag ?>>
