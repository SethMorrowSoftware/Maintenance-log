<?php
/**
 * Avatar plus name, the standard way a person appears in a row.
 *
 * Variables: $user (array|null), $showRole (bool), $size, $empty (string)
 */

use App\Acl;
use App\View;

$user     = $user     ?? null;
$showRole = $showRole ?? false;
$size     = $size     ?? 'sm';
$empty    = $empty    ?? '—';

if ($user === null || ($user['id'] ?? null) === null) {
    echo '<span class="text-subtle">' . e($empty) . '</span>';
    return;
}

$name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
$name = $name !== '' ? $name : (string) ($user['username'] ?? 'Unknown');
?>
<span class="user-chip">
    <?php View::partial('avatar', ['user' => $user, 'size' => $size]); ?>
    <span class="flex-1">
        <span class="name"><?= e($name) ?></span>
        <?php if ($showRole && !empty($user['role'])): ?>
            <span class="role"><?= e(Acl::roleLabel((string) $user['role'])) ?></span>
        <?php endif; ?>
    </span>
</span>
