<?php
/**
 * Variables: $events — [['title' =>, 'meta' =>, 'body' =>, 'tone' =>, 'url' =>], ...]
 */

$events = $events ?? [];

if ($events === []) {
    return;
}
?>
<ul class="timeline">
    <?php foreach ($events as $event): ?>
        <li class="timeline-item tone-<?= e((string) ($event['tone'] ?? 'muted')) ?>">
            <div class="timeline-title">
                <?php if (!empty($event['url'])): ?>
                    <a href="<?= e((string) $event['url']) ?>"><?= e((string) ($event['title'] ?? '')) ?></a>
                <?php else: ?>
                    <?= e((string) ($event['title'] ?? '')) ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($event['body'])): ?>
                <div class="text-muted"><?= e((string) $event['body']) ?></div>
            <?php endif; ?>
            <?php if (!empty($event['meta'])): ?>
                <div class="timeline-meta"><?= e((string) $event['meta']) ?></div>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
