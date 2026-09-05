<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Where somebody works.
 *
 * A person can be given areas (locations) and specific checklists on their
 * page. Anybody with either set sees only the checks that belong to those
 * areas or those checklists: on the board, on the inspections list, when
 * starting a check. Administrators are never limited. Somebody with nothing
 * set sees everything, which keeps the small site — one mechanic, one
 * manager — exactly as it was.
 *
 * A check belongs to an area when the machine being checked lives there, or
 * when the checklist is an area checklist for it. A check belongs to an
 * assigned checklist when it is a run of that checklist, wherever it is.
 */
final class Scope
{
    /** @var array<int, array{areas: list<int>, checklists: list<int>, error: bool}> */
    private static array $cache = [];

    private function __construct()
    {
    }

    /**
     * A stand-in for "the whole site": the checks job runs for everybody,
     * whoever's page view happened to trigger it. Passing null would mean the
     * signed-in user, so this is what to pass instead.
     *
     * @return array<string, mixed>
     */
    public static function everyone(): array
    {
        return ['id' => 0, 'role' => Acl::ROLE_ADMIN, 'is_active' => 1];
    }

    // -------------------------------------------------------------------------
    // Reading
    // -------------------------------------------------------------------------

    /**
     * @return array{areas: list<int>, checklists: list<int>}
     */
    public static function forUser(int $userId): array
    {
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        $out = ['areas' => [], 'checklists' => [], 'error' => false];

        if ($userId > 0) {
            try {
                $out['areas'] = array_map('intval', db()->column(
                    'SELECT location_id FROM {user_areas} WHERE user_id = ? ORDER BY location_id',
                    [$userId]
                ));
                $out['checklists'] = array_map('intval', db()->column(
                    'SELECT checklist_id FROM {user_checklists} WHERE user_id = ? ORDER BY checklist_id',
                    [$userId]
                ));
            } catch (Throwable $e) {
                // A database from before this feature, or a query that failed.
                // Nobody is limited — except checks-only staff, who are then
                // limited to nothing rather than shown everything.
                $out['error'] = true;
            }
        }

        self::$cache[$userId] = $out;

        return $out;
    }

    /**
     * @param  array<string, mixed>|null $user
     * @return list<int>
     */
    public static function areas(?array $user = null): array
    {
        $user = $user ?? Auth::user();

        return $user === null ? [] : self::forUser((int) $user['id'])['areas'];
    }

    /**
     * @param  array<string, mixed>|null $user
     * @return list<int>
     */
    public static function checklists(?array $user = null): array
    {
        $user = $user ?? Auth::user();

        return $user === null ? [] : self::forUser((int) $user['id'])['checklists'];
    }

    /**
     * Is this person limited to part of the site's checks?
     *
     * @param array<string, mixed>|null $user
     */
    public static function limited(?array $user = null): bool
    {
        $user = $user ?? Auth::user();

        if ($user === null || Acl::isAdmin($user)) {
            return false;
        }

        $scope = self::forUser((int) $user['id']);

        if ($scope['error']) {
            return Acl::isStaff($user);
        }

        return $scope['areas'] !== [] || $scope['checklists'] !== [];
    }

    /**
     * The names of somebody's areas, for lists and the user page.
     *
     * @return list<string>
     */
    public static function areaNames(int $userId): array
    {
        $ids = self::forUser($userId)['areas'];

        if ($ids === []) {
            return [];
        }

        return array_map('strval', db()->column(
            'SELECT name FROM {locations} WHERE id IN (' . self::marks($ids) . ') ORDER BY sort_order ASC, name ASC',
            $ids
        ));
    }

    // -------------------------------------------------------------------------
    // Writing
    // -------------------------------------------------------------------------

    /**
     * Replace somebody's areas and checklists with what the form sent.
     *
     * @param array<mixed> $areaIds
     * @param array<mixed> $checklistIds
     */
    public static function save(int $userId, array $areaIds, array $checklistIds): void
    {
        $areas      = self::cleanIds($areaIds, 'locations');
        $checklists = self::cleanIds($checklistIds, 'checklists');

        db()->transaction(static function (Database $db) use ($userId, $areas, $checklists): void {
            $db->delete('user_areas', ['user_id' => $userId]);
            $db->delete('user_checklists', ['user_id' => $userId]);

            foreach ($areas as $id) {
                $db->insert('user_areas', ['user_id' => $userId, 'location_id' => $id]);
            }

            foreach ($checklists as $id) {
                $db->insert('user_checklists', ['user_id' => $userId, 'checklist_id' => $id]);
            }
        });

        unset(self::$cache[$userId]);
    }

    /**
     * Positive, unique ids that exist in the table.
     *
     * @param  array<mixed> $raw
     * @return list<int>
     */
    private static function cleanIds(array $raw, string $table): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        return array_map('intval', db()->column(
            'SELECT id FROM {' . $table . '} WHERE id IN (' . self::marks($ids) . ')',
            $ids
        ));
    }

    // -------------------------------------------------------------------------
    // Filters
    // -------------------------------------------------------------------------

    /**
     * A WHERE fragment limiting inspections to what the user may see.
     *
     * $inspAlias is the inspections table, $assetAlias the LEFT JOINed assets
     * table. Returns [null, []] when the user is not limited.
     *
     * @param  array<string, mixed>|null $user
     * @return array{0: string|null, 1: list<mixed>}
     */
    public static function inspectionFilter(string $inspAlias = 'i', string $assetAlias = 'a', ?array $user = null): array
    {
        if (!self::limited($user)) {
            return [null, []];
        }

        $scope  = self::forUser((int) ($user ?? Auth::user())['id']);
        $parts  = [];
        $params = [];

        if ($scope['checklists'] !== []) {
            $parts[] = $inspAlias . '.checklist_id IN (' . self::marks($scope['checklists']) . ')';
            $params  = array_merge($params, $scope['checklists']);
        }

        if ($scope['areas'] !== []) {
            $marks   = self::marks($scope['areas']);
            $parts[] = $assetAlias . '.location_id IN (' . $marks . ')';
            $parts[] = $inspAlias . '.location_id IN (' . $marks . ')';
            $params  = array_merge($params, $scope['areas'], $scope['areas']);
        }

        if ($parts === []) {
            return ['0 = 1', []];
        }

        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    /**
     * A WHERE fragment limiting machines to the user's areas, plus any machine
     * one of their assigned checklists is written for.
     *
     * @param  array<string, mixed>|null $user
     * @return array{0: string|null, 1: list<mixed>}
     */
    public static function assetFilter(string $alias = 'a', ?array $user = null): array
    {
        if (!self::limited($user)) {
            return [null, []];
        }

        $scope  = self::forUser((int) ($user ?? Auth::user())['id']);
        $parts  = [];
        $params = [];

        if ($scope['areas'] !== []) {
            $parts[] = $alias . '.location_id IN (' . self::marks($scope['areas']) . ')';
            $params  = array_merge($params, $scope['areas']);
        }

        if ($scope['checklists'] !== []) {
            $marks = self::marks($scope['checklists']);

            $parts[] = $alias . '.id IN (SELECT asset_id FROM {checklists} WHERE id IN (' . $marks . ") AND applies_to = 'asset' AND asset_id IS NOT NULL)";
            $parts[] = $alias . '.category_id IN (SELECT category_id FROM {checklists} WHERE id IN (' . $marks . ") AND applies_to = 'category' AND category_id IS NOT NULL)";
            $parts[] = 'EXISTS (SELECT 1 FROM {checklists} WHERE id IN (' . $marks . ") AND applies_to = 'all')";
            $params  = array_merge($params, $scope['checklists'], $scope['checklists'], $scope['checklists']);
        }

        if ($parts === []) {
            return ['0 = 1', []];
        }

        return ['(' . implode(' OR ', $parts) . ')', $params];
    }

    /**
     * May the user run (or see) this checklist, here?
     *
     * $asset is the machine it would run against; null for an area checklist.
     *
     * @param array<string, mixed>      $checklist
     * @param array<string, mixed>|null $asset
     * @param array<string, mixed>|null $user
     */
    public static function allowsChecklist(array $checklist, ?array $asset = null, ?array $user = null): bool
    {
        if (!self::limited($user)) {
            return true;
        }

        $scope = self::forUser((int) ($user ?? Auth::user())['id']);

        if (in_array((int) $checklist['id'], $scope['checklists'], true)) {
            return true;
        }

        if ($scope['areas'] === []) {
            return false;
        }

        if ((string) ($checklist['applies_to'] ?? '') === 'location') {
            return in_array((int) ($checklist['location_id'] ?? 0), $scope['areas'], true);
        }

        return $asset !== null && in_array((int) ($asset['location_id'] ?? 0), $scope['areas'], true);
    }

    /**
     * May the user see this inspection? Expects a row from Inspection::find(),
     * which carries scope_location_id (the machine's area, or the area checked).
     *
     * @param array<string, mixed>      $inspection
     * @param array<string, mixed>|null $user
     */
    public static function allowsInspection(array $inspection, ?array $user = null): bool
    {
        if (!self::limited($user)) {
            return true;
        }

        $scope = self::forUser((int) ($user ?? Auth::user())['id']);

        if (!empty($inspection['checklist_id']) && in_array((int) $inspection['checklist_id'], $scope['checklists'], true)) {
            return true;
        }

        $location = (int) ($inspection['scope_location_id'] ?? 0);

        return $location > 0 && in_array($location, $scope['areas'], true);
    }

    /**
     * @param list<int> $ids
     */
    private static function marks(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }
}
