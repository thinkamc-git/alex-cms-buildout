<?php
/**
 * cms/views/categories-reorder.php — AJAX endpoint for drag-drop reordering
 * of categories within a type.
 *
 * Request:
 *   POST /cms/categories/reorder
 *   Form fields:
 *     csrf_token  CSRF token
 *     type        category type (article | journal | live-session | experiment)
 *     ids[]       ordered list of category.id values (position 0 → sort_order 10)
 *
 * Response (application/json):
 *   { "ok": true }
 *   { "ok": false, "error": "…" }  — HTTP 400 or 403
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';

Auth::require_login();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired. Reload the page.']);
    exit;
}

$type = (string)($_POST['type'] ?? '');
$ids  = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];

$res = reorder_categories($type, $ids);
if (!$res['ok']) {
    http_response_code(400);
}
echo json_encode($res);
