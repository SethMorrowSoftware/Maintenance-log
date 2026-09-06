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
     * The actions that change something: POST only, with the token.
     *
     * @return list<string>
     */
    public static function writes(): array
    {
        return ['read', 'read_all'];
    }

    /** The bell goes with the module: switched off means these answer 404 too. */
    private static function guard(): void
    {
        if (!\App\Features::on('notifications')) {
            \App\Response::error('Notifications are switched off on this site.', 'feature_off', 404);
        }
    }

    /**
     * Just the count. Polled every minute, so it stays as small as possible.
     *
     * @return array<string, int>
     */
    public static function unread(): array
    {
        self::guard();

        return ['count' => Notifier::unreadCount()];
    }

    /**
     * The latest few, for the dropdown.
     *
     * @return array<string, mixed>
     */
    public static function recent(): array
    {
        self::guard();

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
        self::guard();

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
        self::guard();

        $marked = Notifier::markAllRead((int) Auth::id());

        return ['marked' => $marked, 'count' => Notifier::unreadCount()];
    }
}
