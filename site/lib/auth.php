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
 * Lockout is per-user (counter resets on success). Sliding 14-day session
 * keyed off $_SESSION['last_seen'].
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';

final class Auth
{
    public const SESSION_NAME      = 'amc_sid';
    public const SESSION_TTL_SECS  = 14 * 24 * 60 * 60; // 14 days, sliding
    public const LOCKOUT_THRESHOLD = 5;
    public const LOCKOUT_MINUTES   = 15;
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
        $_SESSION['last_seen'] = $now;
    }

    /**
     * Redirect to /cms/login?next=<current_path> if no session. Halts the
     * request via exit on redirect.
     */
    public static function require_login(): void
    {
        self::start_session();
        if (!empty($_SESSION['uid'])) {
            return;
        }
        $next = $_SERVER['REQUEST_URI'] ?? '/cms/';
        // Only forward same-origin paths; treat anything weird as the root.
        if (!is_string($next) || $next === '' || $next[0] !== '/') {
            $next = '/cms/';
        }
        header('Location: /cms/login?next=' . urlencode($next), true, 302);
        exit;
    }

    /**
     * Attempt a login. Returns ['ok'=>true,'uid'=>n] on success or
     * ['ok'=>false,'error'=>'message'] on failure. The caller decides what to
     * render; this function only mutates DB + session state.
     */
    public static function login(string $email, string $password): array
    {
        self::start_session();
        $email = trim($email);

        if ($email === '' || $password === '') {
            // Still burn a hash to keep timing flat.
            password_verify($password, self::DUMMY_HASH);
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        if (!$row) {
            password_verify($password, self::DUMMY_HASH);
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        // Lockout check (does NOT increment counter).
        if (!empty($row['locked_until'])) {
            $until = strtotime((string)$row['locked_until']);
            if ($until !== false && $until > time()) {
                $mins = max(1, (int)ceil(($until - time()) / 60));
                return ['ok' => false, 'error' => "Account temporarily locked. Try again in {$mins} minute" . ($mins === 1 ? '' : 's') . '.'];
            }
        }

        if (!password_verify($password, (string)$row['password_hash'])) {
            self::record_failed_attempt((int)$row['id'], (int)$row['failed_attempts']);
            return ['ok' => false, 'error' => 'Invalid email or password.'];
        }

        // Success. Reset counters, refresh last_login, regenerate session, rotate CSRF.
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
        $_SESSION['last_seen'] = time();
        return ['ok' => true, 'uid' => (int)$row['id']];
    }

    private static function record_failed_attempt(int $uid, int $current_attempts): void
    {
        $new_attempts = $current_attempts + 1;
        if ($new_attempts >= self::LOCKOUT_THRESHOLD) {
            $stmt = db()->prepare(
                'UPDATE users
                    SET failed_attempts = 0,
                        locked_until    = DATE_ADD(NOW(), INTERVAL :mins MINUTE)
                  WHERE id = :id'
            );
            $stmt->execute([':mins' => self::LOCKOUT_MINUTES, ':id' => $uid]);
            return;
        }
        $stmt = db()->prepare('UPDATE users SET failed_attempts = :n WHERE id = :id');
        $stmt->execute([':n' => $new_attempts, ':id' => $uid]);
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

        // Trigger setup.php self-delete (AUTH-SECURITY.md §10).
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
