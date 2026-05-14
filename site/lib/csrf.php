<?php
/**
 * lib/csrf.php — per-session CSRF tokens.
 *
 * Per AUTH-SECURITY.md §5. One token per session, regenerated on login. Use
 * Csrf::token() in form output and Csrf::verify($_POST['csrf_token']) at the
 * top of every POST handler in /cms/*.
 *
 * Requires that session_start() has already been called (lib/auth.php does
 * this via Auth::start_session()).
 */

declare(strict_types=1);

final class Csrf
{
    /**
     * Get the current session's CSRF token, generating one if missing.
     * Returns a 43-char URL-safe base64 string (32 random bytes).
     */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Csrf::token() called before session_start().');
        }
        if (empty($_SESSION['csrf'])) {
            self::rotate();
        }
        return $_SESSION['csrf'];
    }

    /**
     * Verify a posted token against the session token in constant time.
     * Returns true on match, false otherwise. Never throws.
     */
    public static function verify(?string $posted): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if (!is_string($posted) || $posted === '' || empty($_SESSION['csrf'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf'], $posted);
    }

    /**
     * Generate a fresh token. Called on login (§4 step 6) to defeat any
     * pre-login token capture, and on first access via token() if missing.
     */
    public static function rotate(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Csrf::rotate() called before session_start().');
        }
        $_SESSION['csrf'] = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
