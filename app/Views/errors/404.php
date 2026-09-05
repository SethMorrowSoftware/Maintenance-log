<?php
/** Error page: 404 Page not found */
$code    = $code    ?? 404;
$message = $message ?? 'That page or record does not exist. It may have been deleted, or the link may be out of date.';
?>
<div style="text-align:center">
    <span class="empty-icon" style="width:64px;height:64px;margin-bottom:var(--space-4)">
        <?= icon('search', '', 28) ?>
    </span>
    <p style="font-size:44px;font-weight:700;letter-spacing:-.03em;color:var(--brand-600);margin:0 0 4px;line-height:1">
        <?= (int) $code ?>
    </p>
    <h1 style="font-size:var(--text-lg);margin:0 0 var(--space-3)">Page not found</h1>
    <p class="text-muted" style="margin-bottom:var(--space-6)"><?= e($message) ?></p>
    <div class="flex gap-2 justify-center flex-wrap">
        <a class="btn btn-primary" href="<?= e(url('index.php')) ?>">
            <?= icon('home', '', 17) ?> Dashboard
        </a>
        <a class="btn btn-secondary" href="<?= e(back_url()) ?>">Go back</a>
    </div>
</div>
