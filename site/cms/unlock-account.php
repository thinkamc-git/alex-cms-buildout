<?php
/**
 * cms/unlock-account.php — staging-only self-serve account unlock.
 *
 * Phase 4 introduced a 5-strike / 15-minute lockout via auth.php. On
 * staging that bites every time we're testing login UX, and the only
 * way to clear it was an SSH/SQL round-trip. This handler exposes a
 * button on the staging login page that clears `locked_until` +
 * `failed_attempts` for all users. Single-author site → "all users" is
 * one row.
 *
 * Hard-gated to the staging host so prod is unaffected even if the
 * route is somehow probed. CSRF-protected via the same session token
 * the login form mints, so a CSRF attacker can't fire it without first
 * having a page on the staging origin (which is already basic-auth'd).
 *
 * Phase 12 (or whenever the public Author/Settings work lands) is the
 * right time to revisit and either: (a) remove this entirely, or
 * (b) replace it with a longer-lived admin-side "Reset lockout" action
 * inside the CMS itself. Until then it stays here as a staging crutch.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/db.php';

// Host gate — staging only. Anything else gets the same themed 404 the
// router would emit for an unknown route.
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
if (!str_contains($host, 'staging.alexmchong.ca')) {
    http_response_code(404);
    $page = dirname(__DIR__) . '/404.html';
    if (is_file($page)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($page);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo "404\n";
    }
    exit;
}

// POST only — GET shouldn't perform a state change.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /cms/login', true, 302);
    exit;
}

Auth::start_session();
if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
    header('Location: /cms/login?flash=' . rawurlencode('Form expired — try again.'), true, 302);
    exit;
}

$n = db()->exec('UPDATE users SET locked_until = NULL, failed_attempts = 0');

$msg = $n > 0
    ? 'Account unlocked. You can sign in now.'
    : 'Nothing to unlock — no users in the database.';

header('Location: /cms/login?flash=' . rawurlencode($msg), true, 302);
exit;
