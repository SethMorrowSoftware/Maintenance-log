<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Authentication: sign in, sign out, "remember me", password resets and
 * brute-force throttling.
 *
 * Session contract (set in login(), enforced in bootstrap):
 *   user_id       int
 *   login_time    UTC datetime string, for the absolute session cap
 *   last_activity unix timestamp, for the idle timeout
 *   ip_hash       hash of the client IP at sign-in
 *   ua_hash       hash of the user agent at sign-in
 */
final class Auth
{
    /** Failed sign-ins allowed for one username before the account locks. */
    public const MAX_USER_ATTEMPTS = 5;

    /** Failed sign-ins allowed from one IP before it is throttled. */
    public const MAX_IP_ATTEMPTS = 20;

    /** Minutes the counters look back, and how long a lock lasts. */
    public const LOCKOUT_MINUTES = 15;

    /** Days a "remember me" cookie stays valid. */
    public const REMEMBER_DAYS = 30;

    /** Minutes a password reset link stays valid. */
    public const RESET_MINUTES = 60;

    /** Absolute session lifetime, however active the user is. */
    public const ABSOLUTE_SESSION_DAYS = 30;

    public const REMEMBER_COOKIE = 'ridelog_remember';

    /** @var array<string, mixed>|null cached current user row */
    private static ?array $user = null;

    private static bool $resolved = false;

    private function __construct()
    {
    }

    // -------------------------------------------------------------------------
    // Current user
    // -------------------------------------------------------------------------

    /**
     * The signed-in user's row, or null.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        self::$user     = null;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($userId <= 0) {
            // No session — but there may be a valid "remember me" cookie.
            $remembered = self::attemptRemember();

            if ($remembered !== null) {
                self::$user = $remembered;
            }

            return self::$user;
        }

        try {
            $row = db()->one(
                'SELECT * FROM {users} WHERE id = ? AND deleted_at IS NULL LIMIT 1',
                [$userId]
            );
        } catch (Throwable $e) {
            return null;
        }

        if ($row === null || !(int) $row['is_active']) {
            // Deleted or deactivated while signed in.
            self::destroySession();

            return null;
        }

        self::$user = $row;

        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function role(): ?string
    {
        $user = self::user();

        return $user === null ? null : (string) $user['role'];
    }

    /** "Mike Torres", falling back to the username. */
    public static function name(?array $user = null): string
    {
        $user = $user ?? self::user();

        if ($user === null) {
            return 'System';
        }

        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));

        return $name !== '' ? $name : (string) ($user['username'] ?? 'Unknown');
    }

    /** Drop the cached row so the next read hits the database. */
    public static function forgetCache(): void
    {
        self::$user     = null;
        self::$resolved = false;
    }

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    /**
     * Send anonymous visitors to the login page, remembering where they wanted
     * to go. Also enforces a forced password change.
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            if (Request::wantsJson()) {
                Response::error('You need to sign in to do that.', 'unauthenticated', 401);
            }

            $target = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $query  = [];

            // Only carry a same-site relative path forward.
            if ($target !== '' && strpos($target, '//') !== 0 && strpos($target, 'login.php') === false) {
                $query['redirect'] = $target;
            }

            Response::redirect(url('login.php', $query));
        }

        self::enforcePasswordChange();
    }

    /** Keep signed-in users away from the login and password-reset pages. */
    public static function requireGuest(): void
    {
        if (self::check()) {
            Response::redirect(url('index.php'));
        }
    }

    /**
     * If the account is flagged, force the user onto the profile page until
     * they set a new password.
     */
    private static function enforcePasswordChange(): void
    {
        $user = self::user();

        if ($user === null || !(int) ($user['must_change_password'] ?? 0)) {
            return;
        }

        $script  = Request::script();
        $allowed = ['profile.php', 'logout.php'];

        if (in_array($script, $allowed, true) || Request::isApiPath()) {
            return;
        }

        Flash::warning('Please choose a new password before continuing.');
        Response::redirect(url('profile.php', ['tab' => 'password', 'forced' => 1]));
    }

    // -------------------------------------------------------------------------
    // Sign in
    // -------------------------------------------------------------------------

    /**
     * Try to sign a user in.
     *
     * Returns ['ok' => bool, 'error' => string, 'user' => array|null]. The
     * error text is deliberately vague about which half was wrong.
     *
     * @return array{ok: bool, error: string, user: array<string, mixed>|null}
     */
    public static function attempt(string $identifier, string $password, bool $remember = false): array
    {
        $identifier = trim($identifier);
        $ip         = Request::ip();

        if ($identifier === '' || $password === '') {
            return self::failure('Enter both your username and your password.');
        }

        // Throttle the IP first: this is the cheap check and it protects
        // against someone spraying many usernames from one place.
        if (self::ipIsThrottled($ip)) {
            self::recordAttempt($identifier, false);

            return self::failure(
                'Too many failed sign-in attempts from this connection. '
                . 'Please wait ' . self::LOCKOUT_MINUTES . ' minutes and try again.'
            );
        }

        $user = self::findByIdentifier($identifier);

        // Always spend roughly the same time whether or not the account exists,
        // so response timing does not reveal which usernames are real.
        if ($user === null) {
            password_verify($password, '$2y$10$usesomesillystringforsalt0uJ8xQ0m1rP9sT4vW7yZ2aB5cD8eF12');
            self::recordAttempt($identifier, false);

            return self::failure('That username or password is not correct.');
        }

        // Locked out?
        $lockedUntil = (string) ($user['locked_until'] ?? '');

        if ($lockedUntil !== '' && $lockedUntil > Dates::nowUtc()) {
            self::recordAttempt($identifier, false);
            $minutes = max(1, (int) ceil(((int) (Dates::diffMinutes(Dates::nowUtc(), $lockedUntil) ?? 1))));

            return self::failure(
                'This account is temporarily locked after too many failed attempts. '
                . 'Try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.'
            );
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            self::recordAttempt($identifier, false);
            self::registerFailure($user);

            return self::failure('That username or password is not correct.');
        }

        if (!(int) $user['is_active']) {
            self::recordAttempt($identifier, false);

            return self::failure('That account has been deactivated. Please contact an administrator.');
        }

        // Success.
        self::recordAttempt($identifier, true);
        self::clearFailures((int) $user['id']);

        // Upgrade the stored hash if PHP's default cost or algorithm moved on.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            try {
                db()->update('users', ['password_hash' => self::hash($password)], ['id' => (int) $user['id']]);
            } catch (Throwable $e) {
                // Not fatal — the sign-in still succeeds.
            }
        }

        self::login($user, $remember);

        return ['ok' => true, 'error' => '', 'user' => $user];
    }

    /**
     * @return array{ok: bool, error: string, user: null}
     */
    private static function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'user' => null];
    }

    /**
     * Look a user up by username or email address.
     *
     * @return array<string, mixed>|null
     */
    public static function findByIdentifier(string $identifier): ?array
    {
        return db()->one(
            'SELECT * FROM {users} WHERE (username = ? OR email = ?) AND deleted_at IS NULL LIMIT 1',
            [$identifier, $identifier]
        );
    }

    /**
     * Establish the session for a user row.
     *
     * @param array<string, mixed> $user
     */
    public static function login(array $user, bool $remember = false): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // New session id on privilege change, so a fixated id is worthless.
        session_regenerate_id(true);

        $_SESSION['user_id']       = (int) $user['id'];
        $_SESSION['login_time']    = Dates::nowUtc();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_hash']       = self::fingerprint(Request::ip());
        $_SESSION['ua_hash']       = self::fingerprint(Request::userAgent());

        Csrf::rotate();

        self::$user     = $user;
        self::$resolved = true;

        self::touchLastLogin((int) $user['id']);

        if ($remember) {
            self::issueRememberToken((int) $user['id']);
        }

        // A user's own timezone may differ from the site default.
        Dates::resetZoneCache();

        Audit::record('auth.login', 'user', (int) $user['id'], self::name($user) . ' signed in');
    }

    /** Sign out, clearing the session and any remember-me cookie. */
    public static function logout(): void
    {
        $user = self::user();

        if ($user !== null) {
            Audit::record('auth.logout', 'user', (int) $user['id'], self::name($user) . ' signed out');
        }

        self::revokeRememberCookie();
        self::destroySession();
    }

    /** Tear the session down completely. */
    public static function destroySession(): void
    {
        self::$user     = null;
        self::$resolved = true;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?? '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => (bool) ($params['secure'] ?? false),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }

        session_destroy();
    }

    private static function touchLastLogin(int $userId): void
    {
        try {
            db()->update('users', [
                'last_login_at' => Dates::nowUtc(),
                'last_login_ip' => Request::ip(),
            ], ['id' => $userId]);
        } catch (Throwable $e) {
            // Never block a sign-in over bookkeeping.
        }
    }

    /**
     * Hash a client attribute for session binding. Salted with the app key so
     * the value is useless outside this install.
     */
    private static function fingerprint(string $value): string
    {
        return hash('sha256', $value . '|' . (string) Config::get('app.key', 'ridelog'));
    }

    /**
     * Is this session still bound to the client that created it?
     * Called from bootstrap on every request.
     */
    public static function sessionIsValid(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
            return true;
        }

        // Idle timeout.
        $timeout = Settings::sessionTimeoutMinutes() * 60;
        $last    = (int) ($_SESSION['last_activity'] ?? 0);

        if ($last > 0 && (time() - $last) > $timeout) {
            return false;
        }

        // Absolute cap.
        $loginTime = (string) ($_SESSION['login_time'] ?? '');

        if ($loginTime !== '') {
            $minutes = Dates::diffMinutes($loginTime, Dates::nowUtc());

            if ($minutes !== null && $minutes > self::ABSOLUTE_SESSION_DAYS * 24 * 60) {
                return false;
            }
        }

        // Binding. The user agent must not change; the IP is checked only when
        // the site opts in, because mobile technicians roam between networks.
        $uaHash = (string) ($_SESSION['ua_hash'] ?? '');

        if ($uaHash !== '' && !hash_equals($uaHash, self::fingerprint(Request::userAgent()))) {
            return false;
        }

        if (Config::get('security.bind_session_ip', false)) {
            $ipHash = (string) ($_SESSION['ip_hash'] ?? '');

            if ($ipHash !== '' && !hash_equals($ipHash, self::fingerprint(Request::ip()))) {
                return false;
            }
        }

        return true;
    }

    /** Refresh the idle-timeout marker. Called once per request. */
    public static function touchActivity(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
            $_SESSION['last_activity'] = time();
        }
    }

    // -------------------------------------------------------------------------
    // Passwords
    // -------------------------------------------------------------------------

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Check a password against the site's rules.
     *
     * Length is the only hard requirement — arbitrary composition rules push
     * people toward worse passwords. Obvious choices are rejected outright.
     *
     * @return string empty when acceptable, otherwise the reason
     */
    public static function validatePassword(string $password, ?array $user = null): string
    {
        $min = Settings::passwordMinLength();

        if (strlen($password) < $min) {
            return 'Your password must be at least ' . $min . ' characters long.';
        }

        if (strlen($password) > 200) {
            return 'That password is too long. Please use 200 characters or fewer.';
        }

        $weak = [
            'password', 'password1', 'password123', '12345678', '123456789',
            'qwerty123', 'letmein1', 'welcome1', 'admin123', 'changeme',
            'maintenance', 'ridelog1', 'castlefun',
        ];

        if (in_array(strtolower($password), $weak, true)) {
            return 'That password is too easy to guess. Please choose something else.';
        }

        if ($user !== null) {
            foreach (['username', 'email', 'first_name', 'last_name'] as $field) {
                $value = trim((string) ($user[$field] ?? ''));

                if ($value !== '' && strlen($value) >= 4 && stripos($password, $value) !== false) {
                    return 'Your password must not contain your name, username or email address.';
                }
            }
        }

        return '';
    }

    /**
     * Set a new password, clear the forced-change flag and revoke every
     * remember-me token so other devices have to sign in again.
     */
    public static function changePassword(int $userId, string $newPassword): void
    {
        db()->update('users', [
            'password_hash'        => self::hash($newPassword),
            'password_changed_at'  => Dates::nowUtc(),
            'must_change_password' => 0,
            'failed_login_count'   => 0,
            'locked_until'         => null,
        ], ['id' => $userId]);

        // Any outstanding reset links are now void.
        db()->run('DELETE FROM {password_resets} WHERE user_id = ?', [$userId]);
        db()->run('DELETE FROM {remember_tokens} WHERE user_id = ?', [$userId]);

        Audit::record('auth.password_changed', 'user', $userId, 'Password changed');
    }

    // -------------------------------------------------------------------------
    // Rate limiting
    // -------------------------------------------------------------------------

    private static function recordAttempt(string $username, bool $success): void
    {
        try {
            db()->insert('login_attempts', [
                'username'   => mb_substr($username, 0, 191, 'UTF-8'),
                'ip_address' => Request::ip(),
                'success'    => $success ? 1 : 0,
                'user_agent' => Request::userAgent(),
                'created_at' => Dates::nowUtc(),
            ]);
        } catch (Throwable $e) {
            // Logging a sign-in attempt must never break signing in.
        }
    }

    private static function windowStart(): string
    {
        return gmdate(Dates::DB_FORMAT, time() - (self::LOCKOUT_MINUTES * 60));
    }

    private static function ipIsThrottled(string $ip): bool
    {
        try {
            $count = db()->count(
                'SELECT COUNT(*) FROM {login_attempts}
                 WHERE ip_address = ? AND success = 0 AND created_at > ?',
                [$ip, self::windowStart()]
            );
        } catch (Throwable $e) {
            return false;
        }

        return $count >= self::MAX_IP_ATTEMPTS;
    }

    /**
     * Count this failure against the account and lock it if the limit is hit.
     *
     * @param array<string, mixed> $user
     */
    private static function registerFailure(array $user): void
    {
        $userId = (int) $user['id'];

        try {
            $recent = db()->count(
                'SELECT COUNT(*) FROM {login_attempts}
                 WHERE username IN (?, ?) AND success = 0 AND created_at > ?',
                [(string) $user['username'], (string) $user['email'], self::windowStart()]
            );

            $data = ['failed_login_count' => $recent];

            if ($recent >= self::MAX_USER_ATTEMPTS) {
                $data['locked_until'] = gmdate(Dates::DB_FORMAT, time() + (self::LOCKOUT_MINUTES * 60));

                Audit::record(
                    'auth.locked',
                    'user',
                    $userId,
                    'Account locked for ' . self::LOCKOUT_MINUTES . ' minutes after ' . $recent . ' failed sign-in attempts'
                );
            }

            db()->update('users', $data, ['id' => $userId]);
        } catch (Throwable $e) {
            // Ignore: throttling is best-effort, never a hard dependency.
        }
    }

    private static function clearFailures(int $userId): void
    {
        try {
            db()->update('users', [
                'failed_login_count' => 0,
                'locked_until'       => null,
            ], ['id' => $userId]);
        } catch (Throwable $e) {
            // Ignore.
        }
    }

    /** Delete throttling rows older than the window. Called by cron.php. */
    public static function pruneLoginAttempts(int $keepDays = 30): int
    {
        try {
            return db()->run(
                'DELETE FROM {login_attempts} WHERE created_at < ?',
                [gmdate(Dates::DB_FORMAT, time() - ($keepDays * 86400))]
            )->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // Remember me
    //
    // Selector/validator: the cookie holds "selector:validator". Only a SHA-256
    // hash of the validator is stored, so a stolen database cannot be replayed.
    // -------------------------------------------------------------------------

    private static function issueRememberToken(int $userId): void
    {
        try {
            $selector  = bin2hex(random_bytes(12));
            $validator = bin2hex(random_bytes(32));
            $expires   = gmdate(Dates::DB_FORMAT, time() + (self::REMEMBER_DAYS * 86400));

            db()->insert('remember_tokens', [
                'user_id'        => $userId,
                'selector'       => $selector,
                'validator_hash' => hash('sha256', $validator),
                'expires_at'     => $expires,
                'user_agent'     => Request::userAgent(),
                'ip_address'     => Request::ip(),
                'created_at'     => Dates::nowUtc(),
            ]);

            self::setRememberCookie($selector . ':' . $validator, time() + (self::REMEMBER_DAYS * 86400));
        } catch (Throwable $e) {
            // A failed remember-me must not break the sign-in.
        }
    }

    private static function setRememberCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => self::cookiePath(),
            'secure'   => Request::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function cookiePath(): string
    {
        $base = (string) Config::get('app.url', '');
        $path = is_string(parse_url($base, PHP_URL_PATH)) ? (string) parse_url($base, PHP_URL_PATH) : '/';

        return rtrim($path, '/') . '/';
    }

    /**
     * Try to restore a session from the remember-me cookie.
     *
     * The token is rotated on every use, so a stolen cookie stops working as
     * soon as the real user visits.
     *
     * @return array<string, mixed>|null
     */
    private static function attemptRemember(): ?array
    {
        if (empty($_COOKIE[self::REMEMBER_COOKIE]) || !is_string($_COOKIE[self::REMEMBER_COOKIE])) {
            return null;
        }

        $parts = explode(':', $_COOKIE[self::REMEMBER_COOKIE], 2);

        if (count($parts) !== 2) {
            self::revokeRememberCookie();

            return null;
        }

        [$selector, $validator] = $parts;

        try {
            $token = db()->one(
                'SELECT * FROM {remember_tokens} WHERE selector = ? LIMIT 1',
                [$selector]
            );
        } catch (Throwable $e) {
            return null;
        }

        if ($token === null) {
            self::revokeRememberCookie();

            return null;
        }

        if ((string) $token['expires_at'] <= Dates::nowUtc()) {
            self::deleteRememberToken((int) $token['id']);
            self::revokeRememberCookie();

            return null;
        }

        if (!hash_equals((string) $token['validator_hash'], hash('sha256', $validator))) {
            // The selector matched but the validator did not: the cookie has
            // probably been tampered with or stolen. Revoke every token for
            // this user so the real one has to sign in again.
            try {
                db()->run('DELETE FROM {remember_tokens} WHERE user_id = ?', [(int) $token['user_id']]);
                Audit::record(
                    'auth.remember_mismatch',
                    'user',
                    (int) $token['user_id'],
                    'Remember-me token failed validation; all tokens for this account were revoked'
                );
            } catch (Throwable $e) {
                // Ignore.
            }

            self::revokeRememberCookie();

            return null;
        }

        $user = db()->one(
            'SELECT * FROM {users} WHERE id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            [(int) $token['user_id']]
        );

        if ($user === null) {
            self::deleteRememberToken((int) $token['id']);
            self::revokeRememberCookie();

            return null;
        }

        // Rotate: retire this token and issue a fresh one.
        self::deleteRememberToken((int) $token['id']);
        self::login($user, true);

        return $user;
    }

    private static function deleteRememberToken(int $id): void
    {
        try {
            db()->delete('remember_tokens', ['id' => $id]);
        } catch (Throwable $e) {
            // Ignore.
        }
    }

    private static function revokeRememberCookie(): void
    {
        if (!empty($_COOKIE[self::REMEMBER_COOKIE]) && is_string($_COOKIE[self::REMEMBER_COOKIE])) {
            $parts = explode(':', $_COOKIE[self::REMEMBER_COOKIE], 2);

            if (count($parts) === 2) {
                try {
                    db()->delete('remember_tokens', ['selector' => $parts[0]]);
                } catch (Throwable $e) {
                    // Ignore.
                }
            }
        }

        unset($_COOKIE[self::REMEMBER_COOKIE]);
        self::setRememberCookie('', time() - 42000);
    }

    /** Sign a user out everywhere. Used when an admin resets their password. */
    public static function revokeAllTokens(int $userId): void
    {
        try {
            db()->run('DELETE FROM {remember_tokens} WHERE user_id = ?', [$userId]);
        } catch (Throwable $e) {
            // Ignore.
        }
    }

    /** Delete expired remember tokens. Called by cron.php. */
    public static function pruneRememberTokens(): int
    {
        try {
            return db()->run('DELETE FROM {remember_tokens} WHERE expires_at < ?', [Dates::nowUtc()])->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // Password resets
    // -------------------------------------------------------------------------

    /**
     * Create a reset link for an email address.
     *
     * Returns the full URL when a matching active account exists, and null
     * otherwise. The CALLER must not reveal which happened.
     */
    public static function createPasswordReset(string $email): ?string
    {
        $email = trim($email);

        if ($email === '') {
            return null;
        }

        $user = db()->one(
            'SELECT * FROM {users} WHERE (email = ? OR username = ?) AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            [$email, $email]
        );

        if ($user === null) {
            return null;
        }

        $userId = (int) $user['id'];

        // Only one live link at a time.
        db()->run('DELETE FROM {password_resets} WHERE user_id = ?', [$userId]);

        $selector = bin2hex(random_bytes(12));
        $token    = bin2hex(random_bytes(32));

        db()->insert('password_resets', [
            'user_id'    => $userId,
            'selector'   => $selector,
            'token_hash' => hash('sha256', $token),
            'expires_at' => gmdate(Dates::DB_FORMAT, time() + (self::RESET_MINUTES * 60)),
            'ip_address' => Request::ip(),
            'created_at' => Dates::nowUtc(),
        ]);

        Audit::record('auth.reset_requested', 'user', $userId, 'Password reset requested');

        return url('reset-password.php', ['selector' => $selector, 'token' => $token]);
    }

    /**
     * Validate a reset link and return the user it belongs to.
     *
     * @return array<string, mixed>|null
     */
    public static function validateResetToken(string $selector, string $token): ?array
    {
        if ($selector === '' || $token === '') {
            return null;
        }

        $reset = db()->one(
            'SELECT * FROM {password_resets} WHERE selector = ? LIMIT 1',
            [$selector]
        );

        if ($reset === null) {
            return null;
        }

        if ($reset['used_at'] !== null || (string) $reset['expires_at'] <= Dates::nowUtc()) {
            return null;
        }

        if (!hash_equals((string) $reset['token_hash'], hash('sha256', $token))) {
            return null;
        }

        return db()->one(
            'SELECT * FROM {users} WHERE id = ? AND is_active = 1 AND deleted_at IS NULL LIMIT 1',
            [(int) $reset['user_id']]
        );
    }

    /**
     * Complete a reset: set the password and burn the token.
     */
    public static function consumeReset(string $selector, string $token, string $newPassword): bool
    {
        $user = self::validateResetToken($selector, $token);

        if ($user === null) {
            return false;
        }

        db()->transaction(static function (Database $db) use ($selector, $user, $newPassword): void {
            $db->update('password_resets', ['used_at' => Dates::nowUtc()], ['selector' => $selector]);

            $db->update('users', [
                'password_hash'        => self::hash($newPassword),
                'password_changed_at'  => Dates::nowUtc(),
                'must_change_password' => 0,
                'failed_login_count'   => 0,
                'locked_until'         => null,
            ], ['id' => (int) $user['id']]);

            $db->run('DELETE FROM {remember_tokens} WHERE user_id = ?', [(int) $user['id']]);
        });

        Audit::record('auth.password_reset', 'user', (int) $user['id'], 'Password reset completed');

        return true;
    }

    /** Delete expired reset rows. Called by cron.php. */
    public static function pruneResets(): int
    {
        try {
            return db()->run('DELETE FROM {password_resets} WHERE expires_at < ?', [Dates::nowUtc()])->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
