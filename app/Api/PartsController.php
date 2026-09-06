<?php

declare(strict_types=1);

namespace App\Api;

use App\Acl;
use App\Models\Part;
use App\Request;
use App\Response;
use App\Status;

/**
 * Parts, for the picker on the maintenance log form.
 */
final class PartsController
{
    /** @return list<string> */
    public static function routes(): array
    {
        return ['list', 'adjust'];
    }

    /**
     * The actions that change something: POST only, with the token.
     *
     * @return list<string>
     */
    public static function writes(): array
    {
        return ['adjust'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function list(): array
    {
        Acl::requirePermission('parts.view');

        $query  = trim(Request::string('q'));
        $where  = ['p.deleted_at IS NULL', 'p.is_active = 1'];
        $params = [];

        if ($query !== '') {
            $like    = \App\Str::likeContains($query);
            $where[] = '(p.name LIKE ? OR p.part_number LIKE ?)';
            array_push($params, $like, $like);
        }

        $rows = db()->all(
            'SELECT p.id, p.name, p.part_number, p.unit_cost, p.quantity_on_hand,
                    p.unit_of_measure, p.reorder_level
             FROM {parts} p
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.name ASC
             LIMIT 25',
            $params
        );

        $out       = [];
        $showMoney = costs_visible();

        foreach ($rows as $row) {
            $item = [
                'id'          => (int) $row['id'],
                'label'       => (string) $row['name'],
                'part_number' => (string) $row['part_number'],
                'meta'        => (string) $row['part_number'] . ' · '
                    . decimal($row['quantity_on_hand']) . ' ' . (string) $row['unit_of_measure']
                    . ' on hand',
                'on_hand'     => (float) $row['quantity_on_hand'],
                'unit'        => (string) $row['unit_of_measure'],
                'stock_state' => Status::stockState($row),
            ];

            // The price is only in the response for somebody allowed to see it.
            if ($showMoney) {
                $item['unit_cost'] = (float) $row['unit_cost'];
            }

            $out[] = $item;
        }

        return ['parts' => $out];
    }

    /**
     * Move stock from the part page without a full reload.
     *
     * @return array<string, mixed>
     */
    public static function adjust(): array
    {
        Acl::requirePermission('parts.adjust');

        $body   = Request::isJson() ? Request::json() : $_POST;
        $id     = (int) ($body['id'] ?? 0);
        $amount = (float) ($body['amount'] ?? 0);
        $way    = is_string($body['way'] ?? null) ? (string) $body['way'] : 'out';

        if (!in_array($way, ['in', 'out'], true)) {
            Response::error('Say whether stock went in or out.', 'validation_failed', 422, ['way' => 'Say whether stock went in or out.']);
        }

        $part = Part::find($id);

        if ($part === null) {
            Response::error('That part does not exist.', 'not_found', 404);
        }

        if ($amount <= 0) {
            Response::error('How many?', 'validation_failed', 422, [
                'amount' => 'Type how many you took or put back.',
            ]);
        }

        $tooMany = $way === 'out' ? Part::cannotTake($part, $amount) : null;

        if ($tooMany !== null) {
            Response::error($tooMany, 'validation_failed', 422, ['amount' => $tooMany]);
        }

        $delta  = $way === 'out' ? -$amount : $amount;
        $result = Part::adjustStock($id, $delta, $way, 'manual', null,
            is_string($body['notes'] ?? null) ? (string) $body['notes'] : '');

        audit('stock.adjust', 'part', $id,
            (string) $part['name'] . ': ' . ($delta > 0 ? '+' : '') . decimal($delta)
            . ' → ' . decimal($result['balance']) . ' on hand');

        return [
            'id'      => $id,
            'on_hand' => $result['balance'],
            'unit'    => (string) $part['unit_of_measure'],
            'message' => 'Now ' . decimal($result['balance']) . ' '
                . (string) $part['unit_of_measure'] . ' on hand.',
        ];
    }
}
