<?php

declare(strict_types=1);

namespace App\Models;

use App\Audit;
use App\Auth;
use App\Dates;
use App\Database;
use App\Notifier;
use App\Settings;
use Throwable;

/**
 * Inspections: a run of a checklist against one machine.
 *
 * Item text is copied onto the inspection when it is answered, not referenced.
 * Editing a checklist template months later must not silently rewrite what a
 * technician signed for last spring.
 */
final class Inspection
{
    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return db()->one(
            'SELECT i.*, a.name AS asset_name, a.asset_tag, a.meter_type, a.status AS asset_status,
                    a.meter_reading AS asset_meter,
                    c.name AS category_name, loc.name AS location_name,
                    u.first_name, u.last_name, u.username, u.avatar_path, u.id AS user_id,
                    cl.name AS current_checklist_name,
                    cl.require_signature, cl.require_meter
             FROM {inspections} i
             INNER JOIN {assets} a ON a.id = i.asset_id
             LEFT JOIN {asset_categories} c ON c.id = a.category_id
             LEFT JOIN {locations} loc ON loc.id = a.location_id
             LEFT JOIN {users} u ON u.id = i.user_id
             LEFT JOIN {checklists} cl ON cl.id = i.checklist_id
             WHERE i.id = ? LIMIT 1',
            [$id]
        );
    }

    /**
     * Checklists that apply to a machine, most specific first.
     *
     * @return list<array<string, mixed>>
     */
    public static function checklistsFor(int $assetId): array
    {
        return db()->all(
            "SELECT c.*,
                    CASE c.applies_to WHEN 'asset' THEN 3 WHEN 'category' THEN 2 ELSE 1 END AS specificity
             FROM {checklists} c
             INNER JOIN {assets} a ON a.id = ?
             WHERE c.is_active = 1
               AND (
                    c.applies_to = 'all'
                 OR (c.applies_to = 'category' AND c.category_id = a.category_id)
                 OR (c.applies_to = 'asset' AND c.asset_id = a.id)
               )
             ORDER BY specificity DESC, c.frequency ASC, c.name ASC",
            [$assetId]
        );
    }

    /**
     * The items of a checklist, in order.
     *
     * @return list<array<string, mixed>>
     */
    public static function checklistItems(int $checklistId): array
    {
        return db()->all(
            'SELECT * FROM {checklist_items} WHERE checklist_id = ? ORDER BY sort_order ASC, id ASC',
            [$checklistId]
        );
    }

    /**
     * The answers recorded on an inspection.
     *
     * @return list<array<string, mixed>>
     */
    public static function items(int $inspectionId): array
    {
        // Whether an item may be left blank lives on the template. An item
        // whose template line has since been deleted is treated as required,
        // which is the safe way round.
        return db()->all(
            'SELECT ii.*, COALESCE(ci.is_required, 1) AS is_required
             FROM {inspection_items} ii
             LEFT JOIN {checklist_items} ci ON ci.id = ii.checklist_item_id
             WHERE ii.inspection_id = ?
             ORDER BY ii.sort_order ASC, ii.id ASC',
            [$inspectionId]
        );
    }

    /**
     * Start an inspection, copying the template's items onto it.
     *
     * Returns an existing in-progress run for the same machine, checklist and
     * person rather than starting a second one — a technician who loses signal
     * mid-inspection and comes back should not start over.
     */
    public static function start(int $assetId, int $checklistId): int
    {
        $userId = Auth::id();

        $existing = db()->value(
            "SELECT id FROM {inspections}
             WHERE asset_id = ? AND checklist_id = ? AND user_id = ? AND status = 'in_progress'
             ORDER BY id DESC LIMIT 1",
            [$assetId, $checklistId, $userId]
        );

        if ($existing !== null) {
            return (int) $existing;
        }

        $checklist = db()->find('checklists', $checklistId);

        if ($checklist === null) {
            return 0;
        }

        return db()->transaction(static function (Database $db) use ($assetId, $checklistId, $checklist, $userId): int {
            $inspectionId = $db->insert('inspections', [
                'checklist_id'   => $checklistId,
                'asset_id'       => $assetId,
                'user_id'        => $userId,
                'checklist_name' => (string) $checklist['name'],
                'status'         => 'in_progress',
                'started_at'     => Dates::nowUtc(),
                'created_at'     => Dates::nowUtc(),
            ]);

            foreach (self::checklistItems($checklistId) as $item) {
                $db->insert('inspection_items', [
                    'inspection_id'     => $inspectionId,
                    'checklist_item_id' => (int) $item['id'],
                    'section'           => (string) $item['section'],
                    'item_text'         => (string) $item['item_text'],
                    'response_type'     => (string) $item['response_type'],
                    'response'          => '',
                    'is_critical'       => (int) $item['is_critical'],
                    'sort_order'        => (int) $item['sort_order'],
                    'created_at'        => Dates::nowUtc(),
                ]);
            }

            return $inspectionId;
        });
    }

    /**
     * Save answers. Called both for "save progress" and for "complete".
     *
     * This only ever records what was answered. Deciding that an inspection is
     * finished belongs to finalise(), so a run that is rejected — unanswered
     * items, no signature, a meter reading that went backwards — stays open
     * instead of being filed as complete behind the technician's back.
     *
     * @param  array<int, array<string, mixed>> $answers keyed by inspection_item id
     * @return array{ok: bool, missing: int, failed: int, critical: bool}
     */
    public static function saveAnswers(int $inspectionId, array $answers, array $meta = []): array
    {
        $items = self::items($inspectionId);

        $passed     = 0;
        $failed     = 0;
        $na         = 0;
        $missing    = 0;
        $critical   = false;
        $meterOnList = null;

        foreach ($items as $item) {
            $itemId = (int) $item['id'];
            $answer = $answers[$itemId] ?? [];

            $response    = (string) ($answer['response'] ?? '');
            $valueText   = trim((string) ($answer['value_text'] ?? ''));
            $valueNumber = $answer['value_number'] ?? null;
            $notes       = trim((string) ($answer['notes'] ?? ''));

            $type = (string) $item['response_type'];

            // Only accept responses that belong to this item's type.
            $allowed = self::allowedResponses($type);

            if ($response !== '' && !in_array($response, $allowed, true)) {
                $response = '';
            }

            if ($valueNumber !== null && $valueNumber !== '') {
                $valueNumber = (float) preg_replace('/[^0-9.\-]/', '', (string) $valueNumber);
            } else {
                $valueNumber = null;
            }

            db()->update('inspection_items', [
                'response'     => $response,
                'value_text'   => mb_substr($valueText, 0, 500, 'UTF-8'),
                'value_number' => $valueNumber,
                'notes'        => mb_substr($notes, 0, 500, 'UTF-8'),
            ], ['id' => $itemId]);

            // A checklist that asks for the hour meter should not make somebody
            // type it again at the bottom of the page.
            if ($type === 'meter' && $valueNumber !== null) {
                $meterOnList = $valueNumber;
            }

            // Tally.
            if (in_array($response, ['pass', 'yes'], true)) {
                $passed++;
            } elseif (in_array($response, ['fail', 'no'], true)) {
                $failed++;

                if ((int) $item['is_critical'] === 1) {
                    $critical = true;
                }
            } elseif ($response === 'na') {
                $na++;
            } elseif (in_array($type, ['text', 'number', 'meter'], true)) {
                // A value counts as answered for these types. An optional one
                // — "tyre pressure, if you measured it" — may be left blank
                // without holding up the sign-off.
                if ($valueText !== '' || $valueNumber !== null) {
                    $passed++;
                } elseif ((int) ($item['is_required'] ?? 1) === 1) {
                    $missing++;
                }
            } elseif ((int) ($item['is_required'] ?? 1) === 1) {
                $missing++;
            }
        }

        $update = [
            'passed_count'    => $passed,
            'failed_count'    => $failed,
            'na_count'        => $na,
            'critical_failed' => $critical ? 1 : 0,
            'notes'           => mb_substr((string) ($meta['notes'] ?? ''), 0, 5000, 'UTF-8'),
            'signature_name'  => mb_substr((string) ($meta['signature_name'] ?? ''), 0, 120, 'UTF-8'),
        ];

        if (isset($meta['meter_reading']) && $meta['meter_reading'] !== '') {
            $update['meter_reading'] = (float) $meta['meter_reading'];
        } elseif ($meterOnList !== null) {
            $update['meter_reading'] = $meterOnList;
        }

        db()->update('inspections', $update, ['id' => $inspectionId]);

        return ['ok' => true, 'missing' => $missing, 'failed' => $failed, 'critical' => $critical];
    }

    /**
     * @return list<string>
     */
    public static function allowedResponses(string $responseType): array
    {
        switch ($responseType) {
            case 'pass_fail':
                return ['pass', 'fail'];
            case 'pass_fail_na':
                return ['pass', 'fail', 'na'];
            case 'yes_no':
                return ['yes', 'no'];
            default:
                return [];
        }
    }

    /**
     * Everything that follows from completing an inspection.
     *
     * @return array{work_order_id: int|null}
     */
    public static function finalise(int $inspectionId, bool $takeOutOfService): array
    {
        $inspection = self::find($inspectionId);

        if ($inspection === null) {
            return ['work_order_id' => null];
        }

        // Close the run. Until this point it was still in progress, whatever
        // the technician pressed.
        if ((string) $inspection['status'] === 'in_progress') {
            db()->update('inspections', [
                'status'              => (int) $inspection['failed_count'] > 0 ? 'failed' : 'passed',
                'completed_at'        => Dates::nowUtc(),
                'duration_minutes'    => Dates::diffMinutes((string) $inspection['started_at'], Dates::nowUtc()),
                'took_out_of_service' => $takeOutOfService ? 1 : 0,
            ], ['id' => $inspectionId]);

            $inspection = self::find($inspectionId);

            if ($inspection === null) {
                return ['work_order_id' => null];
            }
        }

        $assetId      = (int) $inspection['asset_id'];
        $workOrderId  = null;
        $criticalFail = (int) $inspection['critical_failed'] === 1;

        // A meter reading captured on the inspection is still a meter reading.
        if ($inspection['meter_reading'] !== null && (string) $inspection['meter_type'] !== 'none') {
            try {
                $meterResult = Asset::updateMeter(
                    $assetId,
                    (float) $inspection['meter_reading'],
                    'Recorded during an inspection',
                    'inspection',
                    $inspectionId
                );

                if (!$meterResult['ok']) {
                    flash('warning', 'The inspection was saved, but the meter reading was not applied. '
                        . $meterResult['error']);
                }
            } catch (Throwable $e) {
                log_error('Inspection meter update failed: ' . $e->getMessage());
            }
        }

        // A failed critical item means the machine should not carry guests.
        if ($takeOutOfService) {
            try {
                Asset::changeStatus(
                    $assetId,
                    'out_of_service',
                    'Failed inspection on ' . Dates::date((string) $inspection['completed_at'])
                );
            } catch (Throwable $e) {
                log_error('Inspection status change failed: ' . $e->getMessage());
            }
        }

        // Raise a work order for the failures, so somebody owns them.
        if ((int) $inspection['failed_count'] > 0 && Settings::bool('inspection_fail_opens_wo', true)) {
            try {
                $failedItems = db()->all(
                    "SELECT item_text, notes FROM {inspection_items}
                     WHERE inspection_id = ? AND response IN ('fail', 'no')
                     ORDER BY is_critical DESC, sort_order ASC",
                    [$inspectionId]
                );

                $lines = [];

                foreach ($failedItems as $item) {
                    $lines[] = '• ' . (string) $item['item_text']
                        . ((string) $item['notes'] !== '' ? ' — ' . (string) $item['notes'] : '');
                }

                $workOrderId = WorkOrder::create([
                    'asset_id'            => $assetId,
                    'title'               => ($criticalFail ? 'Failed safety check: ' : 'Failed inspection: ')
                                             . (string) $inspection['asset_name'],
                    'description'         => "The following items failed the "
                                             . (string) $inspection['checklist_name'] . ":\n\n"
                                             . implode("\n", $lines),
                    'priority'            => $criticalFail ? 'urgent' : 'high',
                    'status'              => 'open',
                    'source'              => 'inspection',
                    'inspection_id'       => $inspectionId,
                    'is_safety_issue'     => $criticalFail ? 1 : 0,
                    'took_out_of_service' => $takeOutOfService ? 1 : 0,
                ]);

                db()->update('inspections', ['work_order_id' => $workOrderId], ['id' => $inspectionId]);
            } catch (Throwable $e) {
                log_error('Inspection work order failed: ' . $e->getMessage());
            }

            try {
                Notifier::inspectionFailed($inspection);
            } catch (Throwable $e) {
                log_error('Inspection notification failed: ' . $e->getMessage());
            }
        }

        if ((int) $inspection['failed_count'] > 0) {
            \App\Slack::inspectionFailed($inspection, $takeOutOfService);
        }

        Audit::record(
            'inspection.complete',
            'inspection',
            $inspectionId,
            (string) $inspection['checklist_name'] . ' on ' . (string) $inspection['asset_name']
            . ': ' . (int) $inspection['passed_count'] . ' passed, '
            . (int) $inspection['failed_count'] . ' failed'
            . ($criticalFail ? ' (including a safety-critical item)' : '')
        );

        return ['work_order_id' => $workOrderId];
    }

    /**
     * @param  array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private static function buildFilter(array $filters): array
    {
        $where  = ['a.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['asset_id'])) {
            $where[]  = 'i.asset_id = ?';
            $params[] = (int) $filters['asset_id'];
        }

        if (!empty($filters['user_id'])) {
            $where[]  = 'i.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['checklist_id'])) {
            $where[]  = 'i.checklist_id = ?';
            $params[] = (int) $filters['checklist_id'];
        }

        $status = (string) ($filters['status'] ?? '');

        if ($status === 'failed') {
            $where[] = "i.status = 'failed'";
        } elseif ($status === 'in_progress') {
            $where[] = "i.status = 'in_progress'";
        } elseif ($status === 'passed') {
            $where[] = "i.status = 'passed'";
        }

        [$from, $to] = Dates::rangeToUtc(
            (string) ($filters['from'] ?? ''),
            (string) ($filters['to'] ?? '')
        );

        if ($from !== null) {
            $where[]  = 'i.started_at >= ?';
            $params[] = $from;
        }

        if ($to !== null) {
            $where[]  = 'i.started_at < ?';
            $params[] = $to;
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public static function count(array $filters = []): int
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->count(
            'SELECT COUNT(*) FROM {inspections} i INNER JOIN {assets} a ON a.id = i.asset_id WHERE ' . $where,
            $params
        );
    }

    /**
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->all(
            "SELECT i.*, a.name AS asset_name, a.asset_tag,
                    u.first_name, u.last_name, u.username, u.avatar_path, u.id AS user_id
             FROM {inspections} i
             INNER JOIN {assets} a ON a.id = i.asset_id
             LEFT JOIN {users} u ON u.id = i.user_id
             WHERE {$where}
             ORDER BY i.started_at DESC, i.id DESC
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset,
            $params
        );
    }

    /**
     * @param  array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public static function forExport(array $filters): array
    {
        [$where, $params] = self::buildFilter($filters);

        return db()->all(
            "SELECT i.started_at, i.completed_at, a.asset_tag, a.name AS asset_name,
                    i.checklist_name, i.status, i.passed_count, i.failed_count, i.na_count,
                    i.critical_failed, i.meter_reading, i.duration_minutes, i.signature_name, i.notes,
                    CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS inspector
             FROM {inspections} i
             INNER JOIN {assets} a ON a.id = i.asset_id
             LEFT JOIN {users} u ON u.id = i.user_id
             WHERE {$where}
             ORDER BY i.started_at DESC",
            $params
        );
    }
}
