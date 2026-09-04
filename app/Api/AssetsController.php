<?php

declare(strict_types=1);

namespace App\Api;

use App\Acl;
use App\Models\Asset;
use App\Request;
use App\Response;
use App\Status;

/**
 * Assets, for the pickers and the meter widget.
 */
final class AssetsController
{
    /** @return list<string> */
    public static function routes(): array
    {
        return ['list', 'get', 'update_meter', 'due'];
    }

    /**
     * Assets matching a phrase, for a picker.
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
     * One asset, with what the log form needs to know about it.
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        Acl::requirePermission('assets.view');

        $asset = Asset::find(Request::int('id'));

        if ($asset === null) {
            Response::error('That asset does not exist.', 'not_found', 404);
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
     * Record a meter reading from the widget on the asset page.
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
     * What is due on an asset, for the log form.
     *
     * @return array<string, mixed>
     */
    public static function due(): array
    {
        Acl::requirePermission('schedules.view');

        $assetId = Request::int('id');

        if ($assetId <= 0) {
            Response::error('Which asset?', 'validation_failed', 422);
        }

        return ['schedules' => \App\Scheduler::forAsset($assetId)];
    }
}
