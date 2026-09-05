<?php
/**
 * A searchable machine chooser.
 *
 * Renders a real <select> so the form works with JavaScript disabled; core.js
 * upgrades it into a type-ahead combobox when the list is long.
 *
 * Variables: $name, $value, $required, $label, $hint, $assets (optional
 *            pre-loaded list), $includeRetired, $emptyLabel
 */

use App\View;

$name           = $name           ?? 'asset_id';
$value          = $value          ?? '';
$required       = $required       ?? true;
$label          = $label          ?? asset_word(false, true);
$hint           = $hint           ?? '';
$includeRetired = $includeRetired ?? false;
$emptyLabel     = $emptyLabel     ?? 'Choose ' . an_asset() . '…';

if (!isset($assets)) {
    $sql = "SELECT a.id, a.name, a.asset_tag, a.status, c.name AS category_name
            FROM {assets} a
            LEFT JOIN {asset_categories} c ON c.id = a.category_id
            WHERE a.deleted_at IS NULL";

    if (!$includeRetired) {
        $sql .= " AND a.status <> 'retired'";
    }

    $sql .= ' ORDER BY c.sort_order ASC, c.name ASC, a.sort_order ASC, a.name ASC';

    $assets = db()->all($sql);
}

// Group by category so a long fleet stays navigable.
$grouped = [];

foreach ($assets as $asset) {
    $category = (string) ($asset['category_name'] ?? 'Uncategorised');
    $grouped[$category][(string) $asset['id']] =
        (string) $asset['name'] . ' — ' . (string) $asset['asset_tag'];
}

View::partial('form-field', [
    'name'     => $name,
    'label'    => $label,
    'type'     => 'select',
    'value'    => $value,
    'groups'   => $grouped,
    'required' => $required,
    'hint'     => $hint,
    'empty'    => $emptyLabel,
    'attrs'    => ['data-asset-picker' => '1'],
]);
