<?php

declare(strict_types=1);

namespace App\Api;

use App\Auth;
use App\Dates;
use App\Notifier;
use App\Request;
use App\Status;

/**
 * Notifications, for the bell in the header.
 */
final class NotificationsController
{
    /** @return list<string> */
    public static function routes(): array
    {
        return ['unread', 'recent', 'read', 'read_all'];
    }

    /**
     * Just the count. Polled every minute, so it stays as small as possible.
     *
     * @return array<string, int>
     */
    public static function unread(): array
    {
        return ['count' => Notifier::unreadCount()];
    }

    /**
     * The latest few, for the dropdown.
     *
     * @return array<string, mixed>
     */
    public static function recent(): array
    {
        $rows = Notifier::forUser((int) Auth::id(), false, 10);
        $out  = [];

        foreach ($rows as $row) {
            $out[] = [
                'id'      => (int) $row['id'],
                'title'   => (string) $row['title'],
                'message' => (string) $row['message'],
                'type'    => (string) $row['type'],
                'tone'    => Status::tone((string) $row['type'], 'notification'),
                'icon'    => Status::icon((string) $row['type'], 'notification'),
                'url'     => (string) $row['link'],
                'is_read' => (int) $row['is_read'] === 1,
                'when'    => Dates::ago((string) $row['created_at']),
            ];
        }

        return ['notifications' => $out, 'count' => Notifier::unreadCount()];
    }

    /**
     * @return array<string, mixed>
     */
    public static function read(): array
    {
        $body = Request::isJson() ? Request::json() : $_POST;
        $id   = (int) ($body['id'] ?? 0);

        Notifier::markRead($id, (int) Auth::id());

        return ['count' => Notifier::unreadCount()];
    }

    /**
     * @return array<string, mixed>
     */
    public static function readAll(): array
    {
        $marked = Notifier::markAllRead((int) Auth::id());

        return ['marked' => $marked, 'count' => Notifier::unreadCount()];
    }
}
