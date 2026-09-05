<?php

declare(strict_types=1);

namespace App;

/**
 * Role-based permissions.
 *
 * Four roles, one flat permission list, one matrix. Hiding a button is a
 * courtesy; the server-side check is the actual control, so every page and
 * every API action calls requirePermission().
 */
final class Acl
{
    public const ROLE_ADMIN      = 'admin';
    public const ROLE_MANAGER    = 'manager';
    public const ROLE_TECHNICIAN = 'technician';
    public const ROLE_VIEWER     = 'viewer';

    /** Least to most privileged. Used by atLeast(). */
    private const HIERARCHY = [
        self::ROLE_VIEWER     => 1,
        self::ROLE_TECHNICIAN => 2,
        self::ROLE_MANAGER    => 3,
        self::ROLE_ADMIN      => 4,
    ];

    /**
     * Every permission the application recognises, grouped for the docs and
     * the role-summary panel on the user edit screen.
     *
     * @var array<string, array<string, string>>
     */
    private const CATALOGUE = [
        'Machines' => [
            'assets.view'   => 'View machines',
            'assets.create' => 'Add machines',
            'assets.edit'   => 'Edit machines',
            'assets.delete' => 'Delete machines',
            'assets.meter'  => 'Update meter readings',
        ],
        'Maintenance logs' => [
            'logs.view'     => 'View maintenance logs',
            'logs.create'   => 'Add maintenance logs',
            'logs.edit_own' => 'Edit their own logs',
            'logs.edit_any' => 'Edit anyone\'s logs',
            'logs.delete'   => 'Delete maintenance logs',
        ],
        'Schedules' => [
            'schedules.view'   => 'See scheduled service',
            'schedules.manage' => 'Set up and edit scheduled service',
        ],
        'Checklists' => [
            'checklists.view'   => 'View checklist templates',
            'checklists.manage' => 'Create and edit checklist templates',
        ],
        'Inspections' => [
            'inspections.view'    => 'View inspections',
            'inspections.perform' => 'Carry out inspections',
            'inspections.delete'  => 'Delete inspections',
        ],
        'Work orders' => [
            'workorders.view'   => 'View work orders',
            'workorders.create' => 'Report issues / open work orders',
            'workorders.edit'   => 'Update work orders',
            'workorders.assign' => 'Assign work orders',
            'workorders.close'  => 'Complete and close work orders',
            'workorders.delete' => 'Delete work orders',
        ],
        'Parts' => [
            'parts.view'   => 'View parts inventory',
            'parts.adjust' => 'Take parts off the shelf and put them back',
            'parts.manage' => 'Add, edit and remove parts',
        ],
        'Reports' => [
            'reports.view'   => 'View reports',
            'reports.export' => 'Export reports to CSV',
        ],
        'Money' => [
            'costs.view' => 'See prices, costs and spend',
        ],
        'Administration' => [
            'users.view'      => 'View user accounts',
            'users.manage'    => 'Create and edit user accounts',
            'settings.manage' => 'Change site settings',
            'audit.view'      => 'View the audit log',
        ],
    ];

    /**
     * Role definitions. Each role inherits everything from the one below it.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        self::ROLE_VIEWER => [
            'assets.view',
            'logs.view',
            'schedules.view',
            'checklists.view',
            'inspections.view',
            'workorders.view',
            'parts.view',
            'reports.view',
        ],
        self::ROLE_TECHNICIAN => [
            'logs.create',
            'logs.edit_own',
            'assets.meter',
            'inspections.perform',
            'workorders.create',
            'workorders.edit',
            // Taking a part off the shelf is daily work for a mechanic. Adding
            // and removing parts from the list is not.
            'parts.adjust',
        ],
        self::ROLE_MANAGER => [
            'assets.create',
            'assets.edit',
            'assets.delete',
            'logs.edit_any',
            'logs.delete',
            'schedules.manage',
            'checklists.manage',
            'inspections.delete',
            'workorders.assign',
            'workorders.close',
            'workorders.delete',
            'parts.manage',
            'reports.export',
            'audit.view',
            'users.view',
        ],
        self::ROLE_ADMIN => [
            'users.manage',
            'settings.manage',
            // Money is an administrator's business. Everybody else records
            // hours and parts; the figures are worked out behind the scenes.
            'costs.view',
        ],
    ];

    /**
     * Where the Roles page keeps its changes: a JSON object of role => list of
     * permissions, for the roles that differ from the built-in defaults.
     */
    public const SETTING_KEY = 'role_permissions';

    /** @var array<string, list<string>>|null resolved matrix, built once */
    private static ?array $resolved = null;

    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // Roles
    // -------------------------------------------------------------------------

    /**
     * @return array<string, string> role => human label
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN      => 'Administrator',
            self::ROLE_MANAGER    => 'Maintenance Manager',
            self::ROLE_TECHNICIAN => 'Technician',
            self::ROLE_VIEWER     => 'Viewer',
        ];
    }

    /**
     * @return array<string, string> role => one-line description
     */
    public static function roleDescriptions(): array
    {
        return array_map([self::class, 'reword'], [
            self::ROLE_ADMIN      => 'Full access, including user accounts, site settings, and the only role that sees prices and costs.',
            self::ROLE_MANAGER    => 'Runs maintenance: manages machines, schedules, checklists, parts and work orders, and can edit or delete any record. Does not see prices or costs.',
            self::ROLE_TECHNICIAN => 'Does the work: logs maintenance, runs inspections, updates meters and work orders. Can edit their own logs.',
            self::ROLE_VIEWER     => 'Read-only. Sees records and reports but changes nothing.',
        ]);
    }

    public static function roleLabel(?string $role): string
    {
        $roles = self::roles();

        return $roles[(string) $role] ?? Str::label((string) $role);
    }

    public static function isValidRole(?string $role): bool
    {
        return isset(self::HIERARCHY[(string) $role]);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function catalogue(): array
    {
        // The catalogue is a constant, so the site's own word for its
        // machines is swapped in here rather than baked in.
        $out = [];

        foreach (self::CATALOGUE as $group => $permissions) {
            $out[self::reword($group)] = array_map([self::class, 'reword'], $permissions);
        }

        return $out;
    }

    /** "machines" → whatever Settings → General says the things are called. */
    private static function reword(string $text): string
    {
        return str_replace(
            ['Machines', 'machines', 'Machine', 'machine'],
            [asset_word(true, true), asset_word(true), asset_word(false, true), asset_word()],
            $text
        );
    }

    /**
     * Every known permission as a flat list.
     *
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        $out = [];

        foreach (self::CATALOGUE as $group) {
            foreach (array_keys($group) as $permission) {
                $out[] = $permission;
            }
        }

        return $out;
    }

    public static function permissionLabel(string $permission): string
    {
        foreach (self::CATALOGUE as $group) {
            if (isset($group[$permission])) {
                return self::reword($group[$permission]);
            }
        }

        return Str::humanize(str_replace('.', ' ', $permission));
    }

    // -------------------------------------------------------------------------
    // The matrix
    // -------------------------------------------------------------------------

    /**
     * The built-in matrix: each role gets its own grants plus everything from
     * the roles beneath it. This is what "Reset to defaults" goes back to.
     *
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        $order      = [self::ROLE_VIEWER, self::ROLE_TECHNICIAN, self::ROLE_MANAGER, self::ROLE_ADMIN];
        $known      = self::allPermissions();
        $cumulative = [];
        $matrix     = [];

        foreach ($order as $role) {
            $cumulative    = array_merge($cumulative, self::GRANTS[$role] ?? []);
            $matrix[$role] = array_values(array_intersect($known, $cumulative));
        }

        // The administrator gets everything, including any permission added to
        // the catalogue later but forgotten in the grant list.
        $matrix[self::ROLE_ADMIN] = $known;

        return $matrix;
    }

    /**
     * The matrix in force: the defaults, with whatever the Roles page changed
     * laid over the top. Administrators are never changed.
     *
     * @return array<string, list<string>>
     */
    private static function matrix(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $matrix = self::defaults();

        foreach (self::overrides() as $role => $permissions) {
            if ($role !== self::ROLE_ADMIN && isset($matrix[$role])) {
                $matrix[$role] = $permissions;
            }
        }

        self::$resolved = $matrix;

        return $matrix;
    }

    /**
     * What the Roles page saved, tidied: role => permissions, for the roles
     * that were changed. Empty when the site runs on the defaults.
     *
     * @return array<string, list<string>>
     */
    public static function overrides(): array
    {
        try {
            $raw = (string) Settings::get(self::SETTING_KEY, '');
        } catch (\Throwable $e) {
            // No database yet (the installer), or a table that predates this
            // setting. The defaults are always safe.
            return [];
        }

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [];
        }

        $out = [];

        foreach ($decoded as $role => $list) {
            $role = (string) $role;

            if ($role === self::ROLE_ADMIN || !self::isValidRole($role) || !is_array($list)) {
                continue;
            }

            $out[$role] = self::normalise($list);
        }

        return $out;
    }

    /** Has anybody changed a role away from the defaults? */
    public static function isCustomised(): bool
    {
        return self::overrides() !== [];
    }

    /**
     * Tidy a submitted list of permissions: known ones only, in catalogue
     * order, and anything ticked in a section brings "view" for that section
     * with it — a role that can add logs but not see them makes no sense.
     *
     * @param  array<mixed> $list
     * @return list<string>
     */
    public static function normalise(array $list): array
    {
        $known = self::allPermissions();
        $have  = [];

        foreach ($list as $permission) {
            $permission = (string) $permission;

            if (in_array($permission, $known, true)) {
                $have[$permission] = true;
            }
        }

        foreach (array_keys($have) as $permission) {
            $view = (string) strstr($permission, '.', true) . '.view';

            if (in_array($view, $known, true)) {
                $have[$view] = true;
            }
        }

        return array_values(array_intersect($known, array_keys($have)));
    }

    /**
     * Save a matrix from the Roles page. Only the three changeable roles are
     * read; when what was sent matches the defaults, nothing is stored, so the
     * page can honestly say the site runs on the defaults.
     *
     * @param array<string, array<mixed>> $matrix role => permissions
     */
    public static function saveMatrix(array $matrix): void
    {
        $defaults = self::defaults();
        $store    = [];
        $changed  = false;

        foreach ([self::ROLE_VIEWER, self::ROLE_TECHNICIAN, self::ROLE_MANAGER] as $role) {
            $store[$role] = self::normalise((array) ($matrix[$role] ?? []));

            if ($store[$role] !== $defaults[$role]) {
                $changed = true;
            }
        }

        Settings::set(self::SETTING_KEY, $changed ? $store : '');
        self::$resolved = null;
    }

    /** Back to the built-in matrix. */
    public static function resetMatrix(): void
    {
        Settings::set(self::SETTING_KEY, '');
        self::$resolved = null;
    }

    /**
     * @return list<string>
     */
    public static function permissionsFor(?string $role): array
    {
        $matrix = self::matrix();

        return $matrix[(string) $role] ?? [];
    }

    /**
     * A role => bool map for one permission, used to render the matrix in docs
     * and on the role picker.
     *
     * @return array<string, bool>
     */
    public static function rolesWith(string $permission): array
    {
        $out = [];

        foreach (array_keys(self::roles()) as $role) {
            $out[$role] = in_array($permission, self::permissionsFor($role), true);
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Checks
    // -------------------------------------------------------------------------

    /**
     * Does this user hold the permission? Defaults to the signed-in user.
     *
     * @param array<string, mixed>|null $user
     */
    public static function can(string $permission, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();

        if ($user === null) {
            return false;
        }

        // A deactivated account holds nothing, even mid-session.
        if (isset($user['is_active']) && !(int) $user['is_active']) {
            return false;
        }

        $role = (string) ($user['role'] ?? '');

        if (!self::isValidRole($role)) {
            return false;
        }

        // A permission for a module that is switched off is held by nobody,
        // so every button and page behind it disappears together.
        $module = Features::forPermission($permission);

        if ($module !== null && !Features::on($module)) {
            return false;
        }

        return in_array($permission, self::permissionsFor($role), true);
    }

    /**
     * Does the user hold every listed permission?
     *
     * @param list<string>              $permissions
     * @param array<string, mixed>|null $user
     */
    public static function canAll(array $permissions, ?array $user = null): bool
    {
        foreach ($permissions as $permission) {
            if (!self::can($permission, $user)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Does the user hold at least one of the listed permissions?
     *
     * @param list<string>              $permissions
     * @param array<string, mixed>|null $user
     */
    public static function canAny(array $permissions, ?array $user = null): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stop the request unless the user holds the permission.
     *
     * Signed-out visitors are sent to the login page with a return path;
     * signed-in users without the right get a 403.
     */
    public static function requirePermission(string $permission): void
    {
        if (!Auth::check()) {
            Auth::requireLogin();
        }

        if (self::can($permission)) {
            return;
        }

        // Not a matter of rights at all: the whole module is switched off.
        $module = Features::forPermission($permission);

        if ($module !== null && !Features::on($module)) {
            Response::abortPage(404, Features::offMessage($module));
        }

        Audit::record(
            'access.denied',
            'permission',
            null,
            'Denied access requiring "' . $permission . '"'
        );

        Response::abortPage(
            403,
            'You do not have permission to do that. Your role is '
            . self::roleLabel(Auth::role())
            . '. Ask an administrator if you need access.'
        );
    }

    /** Require any one of several permissions. */
    public static function requireAny(array $permissions): void
    {
        if (!Auth::check()) {
            Auth::requireLogin();
        }

        if (self::canAny($permissions)) {
            return;
        }

        Response::abortPage(403, 'You do not have permission to do that.');
    }

    public static function isAdmin(?array $user = null): bool
    {
        $user = $user ?? Auth::user();

        return $user !== null && (string) ($user['role'] ?? '') === self::ROLE_ADMIN;
    }

    /**
     * Is the user at or above a role in the hierarchy?
     *
     * @param array<string, mixed>|null $user
     */
    public static function atLeast(string $role, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();

        if ($user === null) {
            return false;
        }

        $have = self::HIERARCHY[(string) ($user['role'] ?? '')] ?? 0;
        $need = self::HIERARCHY[$role] ?? PHP_INT_MAX;

        return $have >= $need;
    }

    // -------------------------------------------------------------------------
    // Record-level rules
    // -------------------------------------------------------------------------

    /**
     * Can this user edit this maintenance log?
     *
     * Technicians may edit logs they wrote; managers and above may edit any.
     *
     * @param array<string, mixed>      $log
     * @param array<string, mixed>|null $user
     */
    public static function canEditLog(array $log, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();

        if ($user === null) {
            return false;
        }

        if (self::can('logs.edit_any', $user)) {
            return true;
        }

        if (!self::can('logs.edit_own', $user)) {
            return false;
        }

        $userId = (int) ($user['id'] ?? 0);
        $author = (int) ($log['user_id'] ?? 0);
        $entrer = (int) ($log['created_by'] ?? 0);

        return $userId > 0 && ($author === $userId || $entrer === $userId);
    }

    /**
     * Can this user edit this work order?
     *
     * The reporter and the assignee can always update their own; otherwise the
     * generic workorders.edit permission applies.
     *
     * @param array<string, mixed>      $workOrder
     * @param array<string, mixed>|null $user
     */
    public static function canEditWorkOrder(array $workOrder, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();

        if ($user === null) {
            return false;
        }

        if (self::can('workorders.assign', $user)) {
            return true;
        }

        if (!self::can('workorders.edit', $user)) {
            return false;
        }

        $userId = (int) ($user['id'] ?? 0);

        return $userId > 0 && (
            (int) ($workOrder['assigned_to'] ?? 0) === $userId
            || (int) ($workOrder['reported_by'] ?? 0) === $userId
        );
    }

    /**
     * Users may always edit their own profile; only admins edit other people.
     *
     * @param array<string, mixed>|null $user
     */
    public static function canEditUser(int $targetUserId, ?array $user = null): bool
    {
        $user = $user ?? Auth::user();

        if ($user === null) {
            return false;
        }

        if ((int) ($user['id'] ?? 0) === $targetUserId) {
            return true;
        }

        return self::can('users.manage', $user);
    }

    /**
     * Guard against an administrator locking everyone out by demoting or
     * deactivating the last remaining active admin.
     */
    public static function isLastActiveAdmin(int $userId): bool
    {
        $count = db()->count(
            "SELECT COUNT(*) FROM {users}
             WHERE role = 'admin' AND is_active = 1 AND deleted_at IS NULL AND id <> ?",
            [$userId]
        );

        return $count === 0;
    }
}
