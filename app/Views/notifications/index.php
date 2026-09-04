<?php
/**
 * The notification list.
 *
 * Everything here is something the system decided somebody should know about:
 * maintenance falling due, a work order landing on you, a failed inspection, a
 * part running out. Reading one takes you to the thing itself.
 */

use App\Dates;
use App\Status;
use App\View;
?>

<div class="flex items-center justify-between gap-3 flex-wrap mb-4">
    <div class="tabs" role="tablist">
        <a class="tab<?= $onlyUnread ? '' : ' is-active' ?>"
           href="<?= e(url('notifications.php')) ?>" role="tab"
           aria-selected="<?= $onlyUnread ? 'false' : 'true' ?>">
            Everything
        </a>
        <a class="tab<?= $onlyUnread ? ' is-active' : '' ?>"
           href="<?= e(url('notifications.php', ['show' => 'unread'])) ?>" role="tab"
           aria-selected="<?= $onlyUnread ? 'true' : 'false' ?>">
            Unread
            <?php if ($unread > 0): ?><span class="tab-count"><?= (int) $unread ?></span><?php endif; ?>
        </a>
    </div>

    <div class="flex gap-2">
        <?php if ($unread > 0): ?>
            <form method="post" action="<?= e(url('notifications.php', $_GET)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="read_all">
                <button type="submit" class="btn btn-secondary btn-sm">
                    <?= icon('check', '', 15) ?> Mark everything read
                </button>
            </form>
        <?php endif; ?>

        <form method="post" action="<?= e(url('notifications.php', $_GET)) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear_read">
            <button type="submit" class="btn btn-ghost btn-sm"
                    data-confirm="Clear out everything you have already read?"
                    data-confirm-title="Clear read notifications">
                <?= icon('archive', '', 15) ?> Clear read
            </button>
        </form>
    </div>
</div>

<?php if ($rows === []): ?>
    <?php View::partial('empty-state', [
        'icon'    => 'bell',
        'title'   => $onlyUnread ? 'You are all caught up' : 'Nothing here',
        'message' => $onlyUnread
            ? 'Nothing is waiting for you.'
            : 'Notifications turn up when maintenance falls due, a work order is assigned '
              . 'to you, an inspection fails, or a part runs low.',
    ]); ?>
<?php else: ?>
    <div class="card">
        <ul class="notification-list">
            <?php foreach ($rows as $row): ?>
                <?php
                $rowId  = (int) $row['id'];
                $type   = (string) $row['type'];
                $isRead = (int) $row['is_read'] === 1;
                $link   = (string) $row['link'];
                ?>
                <li class="notification<?= $isRead ? ' is-read' : '' ?>">
                    <span class="notification-icon tone-<?= e(Status::tone($type, 'notification')) ?>">
                        <?= icon(Status::icon($type, 'notification'), '', 18) ?>
                    </span>

                    <div class="notification-body">
                        <div class="notification-title">
                            <?php if ($link !== ''): ?>
                                <a href="<?= e(url($link)) ?>"><?= e((string) $row['title']) ?></a>
                            <?php else: ?>
                                <?= e((string) $row['title']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ((string) $row['message'] !== ''): ?>
                            <p class="notification-message"><?= e((string) $row['message']) ?></p>
                        <?php endif; ?>
                        <div class="notification-meta">
                            <?= e(Status::label($type, 'notification')) ?>
                            &middot; <?= e(Dates::ago((string) $row['created_at'])) ?>
                        </div>
                    </div>

                    <div class="notification-actions">
                        <?php if (!$isRead): ?>
                            <form method="post" action="<?= e(url('notifications.php', $_GET)) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="read">
                                <input type="hidden" name="id" value="<?= $rowId ?>">
                                <button type="submit" class="btn btn-ghost btn-sm"
                                        title="Mark as read" aria-label="Mark as read">
                                    <?= icon('check', '', 16) ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <form method="post" action="<?= e(url('notifications.php', $_GET)) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $rowId ?>">
                            <button type="submit" class="btn btn-ghost btn-sm"
                                    title="Remove" aria-label="Remove">
                                <?= icon('x', '', 16) ?>
                            </button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php View::partial('pagination', [
        'paginator' => $paginator,
        'singular'  => 'notification',
        'plural'    => 'notifications',
    ]); ?>
<?php endif; ?>
