<?php
/**
 * cms/logout.php — POST-only logout endpoint.
 *
 * Per AUTH-SECURITY.md §7. GET returns 405 to prevent drive-by logout via
 * <img src> or third-party links. POST verifies CSRF, destroys the session,
 * and redirects to /cms/login.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

Auth::start_session();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Method Not Allowed. Logout must be a POST.\n";
    return;
}

if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Invalid CSRF token.\n";
    return;
}

Auth::logout();
header('Location: /cms/login', true, 302);
