<?php
/**
 * lib/auth.php — session + login + lockout + password change.
 *
 * Implements AUTH-SECURITY.md §3–§8. The single entry points used by
 * /cms/* handlers:
 *   - Auth::start_session()      Boot the session with hardened cookie params.
 *   - Auth::require_login()      Redirect to /cms/login if not authenticated.
 *   - Auth::login($email, $pw)   Returns ['ok'=>true] or ['ok'=>false,'error'=>…].
 *   - Auth::logout()             Destroy the session.
 *   - Auth::current_user()       Returns the user row (or null).
 *   - Auth::change_password($current, $new, $confirm)  → result array.
 *
 * Brute-force defense is an adaptive per-IP throttle (see LoginThrottle /
 * AUTH-SECURITY.md §6) — NOT a per-account lockout, which would let an
 * attacker lock the single legitimate user out. Sliding 14-day session
 * keyed off $_SESSION['last_seen'].
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/login_throttle.php';

final class Auth
{
    public const SESSION_NAME      = 'amc_sid';
    public const SESSION_TTL_SECS  = 14 * 24 * 60 * 60; // 14 days, sliding
    public const MIN_PW_LENGTH     = 12;
    private const DUMMY_HASH       = '$2y$10$abcdefghijklmnopqrstuu0jpHZcLU/zlWvKtN9JzCWvtfTjsB7Ka';

    /**
     * Boot the session with hardened cookie params. Idempotent — safe to call
     * more than once. Must be called BEFORE any output is written.
     */
    public static function start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
        self::enforce_idle_timeout();
    }

    /**
     * Sliding 14-day idle timeout. If $_SESSION['last_seen'] is older than
     * SESSION_TTL_SECS, destroy the session. Otherwise refresh it.
     */
    private static function enforce_idle_timeout(): void
    {
        if (!isset($_SESSION['uid'])) {
            return;
        }
        $now  = time();
        $seen = (int)($_SESSION['last_seen'] ?? 0);
        if ($seen > 0 && ($now - $seen) > self::SESSION_TTL_SECS) {
            self::logout();
            return;
        }
        // Force-All-Logout: a session whose stamped epoch no longer matches
        // the user's current epoch was invalidated remotely — destroy it.
        if (!self::session_epoch_valid()) {
            self::logout();
            return;
        }
        $_SESSION['last_seen'] = $now;
    }

    /**
     * Compare the session's stamped epoch against the user's current epoch.
     * Pre-existing sessions (no stamp yet) adopt the current epoch rather
     * than being kicked, so a deploy doesn't log everyone out spuriously.
     */
    private static function session_epoch_valid(): bool
    {
        $stmt = db()->prepare('SELECT session_epoch FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$_SESSION['uid']]);
        $cur = $stmt->fetchColumn();
        if ($cur === false) {
            return false; // user row gone
        }
        if (!isset($_SESSION['epoch'])) {
            $_SESSION['epoch'] = (int)$cur;
            return true;
        }
        return (int)$cur === (int)$_SESSION['epoch'];
    }

    /**
     * Sign out every OTHER session (all other devices), keeping the current
     * one. Bumps the user's session epoch — invalidating every existing
     * session on its next request — then re-stamps THIS session with the new
     * epoch so it survives. Safe: the device clicking this is the trusted one.
     */
    public static function logout_other_sessions(): void
    {
        $user = self::current_user();
        if (!$user) {
            return;
        }
        $new = (int)$user['session_epoch'] + 1;
        db()->prepare('UPDATE users SET session_epoch = :e WHERE id = :id')
            ->execute([':e' => $new, ':id' => (int)$user['id']]);
        $_SESSION['epoch'] = $new; // keep this session valid
    }

    /**
     * Redirect to /cms/login?next=<current_path> if no session. Halts the
     * request via exit on redirect.
     */
    public static function require_login(): void
    {
        self::start_session();
        if (empty($_SESSION['uid'])) {
            $next = $_SERVER['REQUEST_URI'] ?? '/cms/';
            // Only forward same-origin paths; treat anything weird as the root.
            if (!is_string($next) || $next === '' || $next[0] !== '/') {
                $next = '/cms/';
            }
            header('Location: /cms/login?next=' . urlencode($next), true, 302);
            exit;
        }
        // Forced password change after a recovery-code login: confine the
        // session to the account surface until a new password is set.
        if (!empty($_SESSION['must_change_pw'])) {
            $path  = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
            $allow = ['/cms/settings', '/cms/account', '/cms/logout'];
            if (!in_array($path, $allow, true)) {
                header('Location: /cms/settings?tab=account&force=1', true, 302);
                exit;
            }
        }
    }

    /**
     * Attempt a login. Returns ['ok'=>true,'uid'=>n] on success or
     * ['ok'=>false,'error'=>'message'] on failure. The caller decides what to
     * render; this function only mutates DB + session state.
     */
    public static function login(string $email, string $password): array
    {
        self::start_session();
        $ip    = LoginThrottle::client_ip();
        $email = trim($email);

        // Throttle gate — decide before doing any password work. A correct
        // password is never blocked because we only reach here when allowed.
        $thr = LoginThrottle::check($ip);
        if (!$thr['allowed']) {
            return self::throttle_error($thr);
        }

        if ($email === '' || $password === '') {
            // Burn a hash to keep timing flat; don't count empty submits.
            password_verify($password, self::DUMMY_HASH);
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        if (!$row) {
            password_verify($password, self::DUMMY_HASH);
            LoginThrottle::record($ip, false, $email);
            return self::after_failed_attempt($ip);
        }

        if (!password_verify($password, (string)$row['password_hash'])) {
            LoginThrottle::record($ip, false, $email);
            return self::after_failed_attempt($ip);
        }

        // Success. Mark the IP trusted, refresh last_login, regenerate session,
        // rotate CSRF, stamp the session epoch (for Force-All-Logout).
        LoginThrottle::record($ip, true, $email);
        $update = db()->prepare(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = :id'
        );
        $update->execute([':id' => (int)$row['id']]);

        if (password_needs_rehash((string)$row['password_hash'], PASSWORD_DEFAULT)) {
            $new = password_hash($password, PASSWORD_DEFAULT);
            $rh  = db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
            $rh->execute([':h' => $new, ':id' => (int)$row['id']]);
        }

        session_regenerate_id(true);
        Csrf::rotate();
        $_SESSION['uid']       = (int)$row['id'];
        $_SESSION['epoch']     = (int)($row['session_epoch'] ?? 0);
        $_SESSION['last_seen'] = time();
        return ['ok' => true, 'uid' => (int)$row['id']];
    }

    /**
     * Sign in with a one-time recovery code instead of a password. On success
     * the code is consumed and the session is flagged to force a password
     * change (see require_login). Subject to the same throttle as password
     * login. Returns the same result shape as login().
     */
    public static function login_with_recovery_code(string $email, string $code): array
    {
        self::start_session();
        $ip    = LoginThrottle::client_ip();
        $email = trim($email);

        $thr = LoginThrottle::check($ip);
        if (!$thr['allowed']) {
            return self::throttle_error($thr);
        }
        if ($email === '' || $code === '') {
            return ['ok' => false, 'error' => 'Invalid email or recovery code.'];
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        if (!$row) {
            LoginThrottle::record($ip, false, $email);
            return self::after_failed_attempt($ip, 'Invalid email or recovery code.');
        }

        require_once __DIR__ . '/recovery_codes.php';
        if (!RecoveryCodes::verify_and_consume((int)$row['id'], $code)) {
            LoginThrottle::record($ip, false, $email);
            return self::after_failed_attempt($ip, 'Invalid email or recovery code.');
        }

        // Success — establish session and force a password reset.
        LoginThrottle::record($ip, true, $email);
        db()->prepare('UPDATE users SET last_login = NOW() WHERE id = :id')
            ->execute([':id' => (int)$row['id']]);
        session_regenerate_id(true);
        Csrf::rotate();
        $_SESSION['uid']            = (int)$row['id'];
        $_SESSION['epoch']          = (int)($row['session_epoch'] ?? 0);
        $_SESSION['last_seen']      = time();
        $_SESSION['must_change_pw'] = true;
        return ['ok' => true, 'uid' => (int)$row['id'], 'must_change_pw' => true];
    }

    /**
     * After recording a failed attempt, re-check the throttle: if this very
     * failure crossed the IP's budget, surface the throttle message now
     * (friendlier than waiting for the next submit). Otherwise $generic.
     */
    private static function after_failed_attempt(string $ip, string $generic = 'Invalid email or password.'): array
    {
        $thr = LoginThrottle::check($ip);
        if (!$thr['allowed']) {
            return self::throttle_error($thr);
        }
        return ['ok' => false, 'error' => $generic];
    }

    /** Build the throttle error result from a LoginThrottle::check() array. */
    private static function throttle_error(array $thr): array
    {
        $secs = (int)($thr['retry_after_secs'] ?? 0);
        $mins = max(1, (int)ceil($secs / 60));
        $msg  = 'Too many failed attempts from your location. For your security, '
              . 'sign-in from here is paused for about ' . $mins . ' minute'
              . ($mins === 1 ? '' : 's') . '. If this is you, use a recovery code '
              . 'below, or reset your password via SSH.';
        if (!empty($thr['elevated'])) {
            $msg = 'Heightened protection is active due to unusual sign-in activity. ' . $msg;
        }
        return [
            'ok'          => false,
            'error'       => $msg,
            'throttled'   => true,
            'retry_after' => $secs,
            'elevated'    => !empty($thr['elevated']),
        ];
    }

    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start_session();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Returns the current user row (assoc) or null if not logged in.
     */
    public static function current_user(): ?array
    {
        self::start_session();
        if (empty($_SESSION['uid'])) {
            return null;
        }
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => (int)$_SESSION['uid']]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Change the current user's password. Returns ['ok'=>true] or
     * ['ok'=>false,'error'=>…]. Must be called from an authenticated request.
     */
    public static function change_password(string $current, string $new, string $confirm): array
    {
        $user = self::current_user();
        if (!$user) {
            return ['ok' => false, 'error' => 'Not logged in.'];
        }
        if (!password_verify($current, (string)$user['password_hash'])) {
            return ['ok' => false, 'error' => 'Current password is incorrect.'];
        }
        if ($new !== $confirm) {
            return ['ok' => false, 'error' => 'New passwords do not match.'];
        }
        $err = self::validate_password_strength($new);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
            'UPDATE users SET password_hash = :h, password_changed_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':h' => $hash, ':id' => (int)$user['id']]);
        session_regenerate_id(true);
        unset($_SESSION['must_change_pw']); // recovery-code reset satisfied

        // Trigger setup.php self-delete (AUTH-SECURITY.md §10).
        $setup = dirname(__DIR__) . '/setup.php';
        if (is_file($setup)) {
            @unlink($setup);
        }
        return ['ok' => true];
    }

    /**
     * Set a new password WITHOUT the current one — only valid immediately
     * after a recovery-code login (guarded by $_SESSION['must_change_pw']).
     * A recovery user can't supply the old password, so this is the path the
     * forced-change form uses in that state. Returns the same result shape.
     */
    public static function set_password_after_recovery(string $new, string $confirm): array
    {
        self::start_session();
        if (empty($_SESSION['must_change_pw'])) {
            // Not in a recovery flow — refuse; the normal change path applies.
            return ['ok' => false, 'error' => 'Current password is required.'];
        }
        $user = self::current_user();
        if (!$user) {
            return ['ok' => false, 'error' => 'Not logged in.'];
        }
        if ($new !== $confirm) {
            return ['ok' => false, 'error' => 'New passwords do not match.'];
        }
        $err = self::validate_password_strength($new);
        if ($err !== null) {
            return ['ok' => false, 'error' => $err];
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password_hash = :h, password_changed_at = NOW() WHERE id = :id')
            ->execute([':h' => $hash, ':id' => (int)$user['id']]);
        session_regenerate_id(true);
        unset($_SESSION['must_change_pw']);

        $setup = dirname(__DIR__) . '/setup.php';
        if (is_file($setup)) {
            @unlink($setup);
        }
        return ['ok' => true];
    }

    /**
     * Returns an error string if the password fails the §8 rules, else null.
     * Rules: ≥12 chars, ≥1 uppercase, ≥1 lowercase, ≥1 digit.
     */
    public static function validate_password_strength(string $pw): ?string
    {
        if (strlen($pw) < self::MIN_PW_LENGTH) {
            return 'Password must be at least ' . self::MIN_PW_LENGTH . ' characters.';
        }
        if (!preg_match('/[a-z]/', $pw)) {
            return 'Password must include a lowercase letter.';
        }
        if (!preg_match('/[A-Z]/', $pw)) {
            return 'Password must include an uppercase letter.';
        }
        if (!preg_match('/\d/', $pw)) {
            return 'Password must include a digit.';
        }
        return null;
    }
}
