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

// A joined row's "id" is the record's, not the person's; the person's id is
// aliased beside their name. Nobody there (an unassigned work order, a
// removed account) shows the placeholder, not "Unknown".
$chipUserId = null;

if ($user !== null) {
    foreach (['assignee_id', 'assigned_to', 'user_id', 'id'] as $idKey) {
        if (array_key_exists($idKey, $user)) {
            $chipUserId = $user[$idKey];
            break;
        }
    }
}

$name = $user === null ? '' : trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
$name = $name !== '' ? $name : (string) ($user['username'] ?? '');

if ($user === null || $chipUserId === null || (int) $chipUserId <= 0 || $name === '') {
    echo '<span class="text-subtle">' . e($empty) . '</span>';
    return;
}
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
