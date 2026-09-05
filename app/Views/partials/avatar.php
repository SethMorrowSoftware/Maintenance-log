<?php
/**
 * A user avatar: their uploaded picture, or their initials on a colour
 * derived from their name so the same person is always the same colour.
 *
 * Variables: $user (array|null), $size ('sm'|''|'lg'|'xl'), $showTitle (bool)
 */

use App\Str;

$user      = $user      ?? null;
$size      = $size      ?? '';
$showTitle = $showTitle ?? false;

$class = 'avatar' . ($size !== '' ? ' avatar-' . $size : '');

if ($user === null) {
    echo '<span class="' . e($class) . '" aria-hidden="true">?</span>';
    return;
}

$name   = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
$name   = $name !== '' ? $name : (string) ($user['username'] ?? 'Unknown');
$avatar = (string) ($user['avatar_path'] ?? '');
$title  = $showTitle ? ' title="' . attr($name) . '"' : '';

// Most callers pass a joined row — a log, a work order, a comment — whose
// "id" is the record's, not the person's. The queries alias the person's id
// beside their name; use that when it is there.
$avatarUserId = 0;

foreach (['assignee_id', 'assigned_to', 'user_id', 'id'] as $idKey) {
    if (array_key_exists($idKey, $user)) {
        $avatarUserId = (int) ($user[$idKey] ?? 0);
        break;
    }
}
?>
<?php if ($avatar !== '' && $avatarUserId > 0): ?>
    <span class="<?= e($class) ?>"<?= $title ?>>
        <img src="<?= e(url('file.php', ['avatar' => $avatarUserId])) ?>" alt="" loading="lazy">
    </span>
<?php else: ?>
    <span class="<?= e($class) ?>"<?= $title ?>
          style="background:<?= e(Str::colorFor($name)) ?>1f;color:<?= e(Str::colorFor($name)) ?>">
        <?= e(Str::initials($name)) ?>
    </span>
<?php endif; ?>
