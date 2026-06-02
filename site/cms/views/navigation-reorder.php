<?php
/**
 * cms/views/navigation-reorder.php — AJAX reorder endpoint (Phase 20).
 *
 * POST body: csrf_token, zone, ids=<comma-separated-id-list>
 * Returns: 204 on success, 4xx on auth/CSRF failure.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/nav.php';

Auth::require_login();

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo 'CSRF failed';
    return;
}

$zone = (string)($_POST['zone'] ?? '');
if (!in_array($zone, NAV_ZONES, true)) {
    http_response_code(400);
    echo 'Invalid zone';
    return;
}

$ids_raw = (string)($_POST['ids'] ?? '');
$ids = array_values(array_filter(array_map('intval', explode(',', $ids_raw))));

reorder_nav_items($zone, $ids);

http_response_code(204);
