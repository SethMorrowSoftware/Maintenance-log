<?php

declare(strict_types=1);

namespace App\Api;

use App\Acl;
use App\Models\Asset;
use App\Request;
use App\Response;
use App\Status;

/**
 * Machines, for the pickers and the meter widget.
 */
final class AssetsController
{
    /** @return list<string> */
    public static function routes(): array
    {
        return ['list', 'get', 'update_meter', 'due', 'history'];
    }

    /**
     * The actions that change something: POST only, with the token.
     *
     * @return list<string>
     */
    public static function writes(): array
    {
        return ['update_meter'];
    }

    /**
     * Machines matching a phrase, for a picker.
     *
     * @return array<string, mixed>
     */
    public static function list(): array
    {
        Acl::requirePermission('assets.view');

        $query = trim(Request::string('q'));
        $limit = max(1, min(50, Request::int('limit') ?: 20));

        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        if ($query !== '') {
            $like    = \App\Str::likeContains($query);
            $where[] = '(a.name LIKE ? OR a.asset_tag LIKE ?)';
            array_push($params, $like, $like);
        }

        if (Request::int('category_id') > 0) {
            $where[]  = 'a.category_id = ?';
            $params[] = Request::int('category_id');
        }

        if (!Request::bool('include_retired')) {
            $where[] = "a.status <> 'retired'";
        }

        $rows = db()->all(
            'SELECT a.id, a.name, a.asset_tag, a.status, a.meter_type, a.meter_reading,
                    c.name AS category
             FROM {assets} a
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.name ASC
             LIMIT ' . $limit,
            $params
        );

        $out = [];

        foreach ($rows as $row) {
            $out[] = [
                'id'            => (int) $row['id'],
                'label'         => (string) $row['name'],
                'meta'          => trim((string) $row['asset_tag'] . ' · ' . (string) ($row['category'] ?? '')),
                'url'           => 'asset-view.php?id=' . (int) $row['id'],
                'status'        => (string) $row['status'],
                'status_label'  => Status::label((string) $row['status'], 'asset'),
                'meter_type'    => (string) $row['meter_type'],
                'meter_reading' => (float) $row['meter_reading'],
            ];
        }

        return ['assets' => $out];
    }

    /**
     * One machine, with what the log form needs to know about it.
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        Acl::requirePermission('assets.view');

        $asset = Asset::find(Request::int('id'));

        if ($asset === null) {
            Response::error('That ' . asset_word() . ' does not exist.', 'not_found', 404);
        }

        return [
            'id'            => (int) $asset['id'],
            'name'          => (string) $asset['name'],
            'asset_tag'     => (string) $asset['asset_tag'],
            'status'        => (string) $asset['status'],
            'status_label'  => Status::label((string) $asset['status'], 'asset'),
            'meter_type'    => (string) $asset['meter_type'],
            'meter_reading' => (float) $asset['meter_reading'],
            'category'      => (string) ($asset['category_name'] ?? ''),
            'location'      => (string) ($asset['location_name'] ?? ''),
        ];
    }

    /**
     * Record a meter reading from the widget on the machine page.
     *
     * @return array<string, mixed>
     */
    public static function updateMeter(): array
    {
        Acl::requirePermission('assets.meter');

        $body    = Request::isJson() ? Request::json() : $_POST;
        $id      = (int) ($body['id'] ?? 0);
        $reading = $body['reading'] ?? '';

        if ($id <= 0 || $reading === '' || !is_numeric($reading)) {
            Response::error('Type the number on the meter.', 'validation_failed', 422, [
                'reading' => 'Type the number on the meter.',
            ]);
        }

        $result = Asset::updateMeter(
            $id,
            (float) $reading,
            (string) ($body['notes'] ?? ''),
            'manual'
        );

        if (!$result['ok']) {
            Response::error($result['error'], 'validation_failed', 422, ['reading' => $result['error']]);
        }

        $asset = Asset::find($id);

        return [
            'id'            => $id,
            'meter_reading' => $asset === null ? 0.0 : (float) $asset['meter_reading'],
            'meter_type'    => $asset === null ? '' : (string) $asset['meter_type'],
            'message'       => 'Meter updated.',
        ];
    }

    /**
     * The last few things that happened to a machine, for the panel beside a
     * form. Rebuilt on the page when somebody picks a different machine.
     *
     * @return array<string, mixed>
     */
    public static function history(): array
    {
        Acl::requirePermission('assets.view');

        $asset = Asset::find(Request::int('id'));

        if ($asset === null) {
            Response::error('That ' . asset_word() . ' does not exist.', 'not_found', 404);
        }

        $events = [];

        foreach (Asset::timeline((int) $asset['id'], '', 6) as $event) {
            $events[] = [
                'when'   => \App\Dates::ago((string) $event['when']),
                'label'  => (string) $event['label'],
                'title'  => (string) $event['title'],
                'detail' => \App\Str::limit(strtok((string) $event['detail'], "\n") ?: '', 110),
                'url'    => (string) $event['url'],
                'tone'   => (string) $event['tone'],
            ];
        }

        // What the log form can tie the job to on this machine: its active
        // schedules and its open work orders, as id => label pairs.
        $schedules  = [];
        $workOrders = [];

        if (feature_on('schedules') && can('schedules.view')) {
            foreach (db()->all('SELECT id, name FROM {maintenance_schedules} WHERE asset_id = ? AND is_active = 1 ORDER BY name', [(int) $asset['id']]) as $row) {
                $schedules[] = ['id' => (int) $row['id'], 'label' => (string) $row['name']];
            }
        }

        if (feature_on('work_orders') && can('workorders.view')) {
            foreach (db()->all(
                "SELECT id, wo_number, title FROM {work_orders}
                 WHERE asset_id = ? AND deleted_at IS NULL AND status NOT IN ('completed','cancelled')
                 ORDER BY created_at DESC",
                [(int) $asset['id']]
            ) as $row) {
                $workOrders[] = ['id' => (int) $row['id'], 'label' => (string) $row['wo_number'] . ' — ' . (string) $row['title']];
            }
        }

        return [
            'asset' => [
                'id'            => (int) $asset['id'],
                'name'          => (string) $asset['name'],
                'asset_tag'     => (string) $asset['asset_tag'],
                'status'        => (string) $asset['status'],
                'status_label'  => Status::label((string) $asset['status'], 'asset'),
                'status_tone'   => Status::tone((string) $asset['status'], 'asset'),
                'meter_type'    => feature_on('meters') ? (string) $asset['meter_type'] : 'none',
                'meter_reading' => decimal($asset['meter_reading']),
                'url'           => url('asset-view.php', ['id' => (int) $asset['id']]),
                'history_url'   => url('asset-view.php', ['id' => (int) $asset['id'], 'tab' => 'timeline']),
            ],
            'events'      => $events,
            'schedules'   => $schedules,
            'work_orders' => $workOrders,
        ];
    }

    /**
     * What is due on a machine, for the log form.
     *
     * @return array<string, mixed>
     */
    public static function due(): array
    {
        Acl::requirePermission('schedules.view');

        $assetId = Request::int('id');

        if ($assetId <= 0) {
            Response::error('Which ' . asset_word() . '?', 'validation_failed', 422);
        }

        return ['schedules' => \App\Scheduler::forAsset($assetId)];
    }
}
