<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

use App\Auth;
use App\Csrf;
use App\Notifier;
use App\Paginator;
use App\Request;
use App\View;

Auth::requireLogin();

$userId = (int) Auth::id();

if (is_post()) {
    Csrf::verify();

    $action = Request::string('action');
    $id     = Request::int('id');

    if ($action === 'read' && $id > 0) {
        Notifier::markRead($id, $userId);
    }

    if ($action === 'read_all') {
        $count = Notifier::markAllRead($userId);
        flash('success', $count === 0
            ? 'Nothing was waiting.'
            : $count . ' marked as read.');
    }

    if ($action === 'delete' && $id > 0) {
        Notifier::delete($id, $userId);
    }

    if ($action === 'clear_read') {
        $count = Notifier::clearRead($userId);
        flash('success', $count . ' old notification' . ($count === 1 ? '' : 's') . ' cleared.');
    }

    redirect(url('notifications.php', $_GET));
}

$onlyUnread = Request::string('show') === 'unread';

$where  = ['n.user_id = ?'];
$params = [$userId];

if ($onlyUnread) {
    $where[] = 'n.is_read = 0';
}

$whereSql = implode(' AND ', $where);

$total     = db()->count("SELECT COUNT(*) FROM {notifications} n WHERE {$whereSql}", $params);
$paginator = Paginator::fromRequest($total, 30, 'notifications.php');

$rows = db()->all(
    "SELECT n.* FROM {notifications} n
     WHERE {$whereSql}
     ORDER BY n.is_read ASC, n.created_at DESC, n.id DESC
     LIMIT " . $paginator->limit() . ' OFFSET ' . $paginator->offset(),
    $params
);

$unread = Notifier::unreadCount($userId);

View::render('notifications/index', [
    'title'      => 'Notifications',
    'subtitle'   => $unread === 0
        ? 'Nothing needs your attention'
        : $unread . ' thing' . ($unread === 1 ? '' : 's') . ' waiting for you',
    'activeNav'  => 'notifications.php',
    'rows'       => $rows,
    'paginator'  => $paginator,
    'onlyUnread' => $onlyUnread,
    'unread'     => $unread,
]);
