<?php

declare(strict_types=1);

namespace App\Api;

use App\Request;
use App\Str;

/**
 * Search, for the box at the top of every page.
 */
final class SearchController
{
    /** @return list<string> */
    public static function routes(): array
    {
        return ['global'];
    }

    /**
     * Everything matching a phrase, grouped by what it is.
     *
     * The grouping is the point: "GK-003" should turn up the kart, its open
     * work orders and its recent jobs together, so somebody can jump straight
     * to whichever they meant.
     *
     * @return array<string, list<array{label: string, meta: string, url: string}>>
     */
    public static function global(): array
    {
        $query = trim(Request::string('q'));

        if (mb_strlen($query) < 2) {
            return [];
        }

        $like = Str::likeContains($query);
        $out  = [];

        if (can('assets.view')) {
            $rows = db()->all(
                "SELECT a.id, a.name, a.asset_tag, a.status, c.name AS category
                 FROM {assets} a
                 LEFT JOIN {asset_categories} c ON c.id = a.category_id
                 WHERE a.deleted_at IS NULL
                   AND (a.name LIKE ? OR a.asset_tag LIKE ? OR a.serial_number LIKE ?
                        OR a.model LIKE ? OR a.manufacturer LIKE ? OR a.custom_data LIKE ?)
                 ORDER BY a.name LIMIT 6",
                [$like, $like, $like, $like, $like, $like]
            );

            foreach ($rows as $row) {
                $out[asset_word(true, true)][] = [
                    'label' => (string) $row['name'],
                    'meta'  => trim((string) $row['asset_tag'] . ' · ' . (string) ($row['category'] ?? '')),
                    'url'   => 'asset-view.php?id=' . (int) $row['id'],
                ];
            }
        }

        if (can('logs.view')) {
            $rows = db()->all(
                "SELECT l.id, l.title, l.performed_at, a.name AS asset_name
                 FROM {maintenance_logs} l
                 INNER JOIN {assets} a ON a.id = l.asset_id
                 WHERE l.deleted_at IS NULL
                   AND (l.title LIKE ? OR l.description LIKE ? OR l.work_performed LIKE ?)
                 ORDER BY l.performed_at DESC LIMIT 6",
                [$like, $like, $like]
            );

            foreach ($rows as $row) {
                $out['Maintenance logs'][] = [
                    'label' => (string) $row['title'],
                    'meta'  => (string) $row['asset_name'] . ' · '
                        . \App\Dates::date((string) $row['performed_at'], ''),
                    'url'   => 'log-view.php?id=' . (int) $row['id'],
                ];
            }
        }

        if (can('workorders.view')) {
            $rows = db()->all(
                "SELECT w.id, w.wo_number, w.title, w.status, a.name AS asset_name
                 FROM {work_orders} w
                 LEFT JOIN {assets} a ON a.id = w.asset_id
                 WHERE w.deleted_at IS NULL
                   AND (w.title LIKE ? OR w.description LIKE ? OR w.wo_number LIKE ?)
                 ORDER BY w.created_at DESC LIMIT 6",
                [$like, $like, $like]
            );

            foreach ($rows as $row) {
                $out['Work orders'][] = [
                    'label' => (string) $row['wo_number'] . ' — ' . (string) $row['title'],
                    'meta'  => trim((string) ($row['asset_name'] ?? '') . ' · '
                        . \App\Status::label((string) $row['status'], 'work_order')),
                    'url'   => 'workorder-view.php?id=' . (int) $row['id'],
                ];
            }
        }

        if (can('parts.view')) {
            $rows = db()->all(
                "SELECT p.id, p.name, p.part_number, p.quantity_on_hand, p.unit_of_measure
                 FROM {parts} p
                 WHERE p.deleted_at IS NULL
                   AND (p.name LIKE ? OR p.part_number LIKE ? OR p.supplier LIKE ?)
                 ORDER BY p.name LIMIT 5",
                [$like, $like, $like]
            );

            foreach ($rows as $row) {
                $out['Parts'][] = [
                    'label' => (string) $row['name'],
                    'meta'  => (string) $row['part_number'] . ' · '
                        . decimal($row['quantity_on_hand']) . ' ' . (string) $row['unit_of_measure']
                        . ' on hand',
                    'url'   => 'part-view.php?id=' . (int) $row['id'],
                ];
            }
        }

        if (can('users.view')) {
            $rows = db()->all(
                "SELECT id, first_name, last_name, username, job_title
                 FROM {users}
                 WHERE deleted_at IS NULL
                   AND (first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)
                 ORDER BY first_name LIMIT 4",
                [$like, $like, $like, $like]
            );

            foreach ($rows as $row) {
                $out['People'][] = [
                    'label' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name'])
                        ?: (string) $row['username'],
                    'meta'  => (string) $row['job_title'],
                    // Only somebody who manages people can open the edit page.
                    'url'   => can('users.manage')
                        ? 'user-edit.php?id=' . (int) $row['id']
                        : 'users.php?q=' . rawurlencode((string) $row['username']),
                ];
            }
        }

        return $out;
    }
}
