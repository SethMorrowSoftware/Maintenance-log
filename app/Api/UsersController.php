<?php

declare(strict_types=1);

namespace App\Api;

use App\Acl;
use App\Auth;
use App\Request;
use App\Response;
use App\Str;

/**
 * The small bits of user state the pages set for themselves.
 */
final class UsersController
{
    /** @return list<string> */
    public static function routes(): array
    {
        return ['set_theme', 'list'];
    }

    /**
     * Remember the light/dark choice against the account, so it follows
     * somebody from the workshop tablet to the office desktop.
     *
     * @return array<string, string>
     */
    public static function setTheme(): array
    {
        $body  = Request::isJson() ? Request::json() : $_POST;
        $theme = (string) ($body['theme'] ?? 'system');

        if (!in_array($theme, ['system', 'light', 'dark'], true)) {
            Response::error('That is not a theme.', 'validation_failed', 422);
        }

        db()->update('users', ['theme' => $theme], ['id' => (int) Auth::id()]);
        Auth::forgetCache();

        return ['theme' => $theme];
    }

    /**
     * People, for an assignee picker.
     *
     * @return array<string, mixed>
     */
    public static function list(): array
    {
        Acl::requirePermission('users.view');

        $query  = trim(Request::string('q'));
        $where  = ['u.deleted_at IS NULL', 'u.is_active = 1'];
        $params = [];

        if ($query !== '') {
            $like    = Str::likeContains($query);
            $where[] = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $rows = db()->all(
            'SELECT u.id, u.first_name, u.last_name, u.username, u.job_title, u.role
             FROM {users} u
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY u.first_name, u.last_name
             LIMIT 25',
            $params
        );

        $out = [];

        foreach ($rows as $row) {
            $out[] = [
                'id'    => (int) $row['id'],
                'label' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name'])
                    ?: (string) $row['username'],
                'meta'  => (string) $row['job_title'] ?: Acl::roleLabel((string) $row['role']),
            ];
        }

        return ['users' => $out];
    }
}
