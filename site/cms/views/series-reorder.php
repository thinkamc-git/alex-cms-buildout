<?php
/**
 * cms/views/series-reorder.php — AJAX endpoint for drag-drop reordering
 * of the parts inside a single series (Phase 11 follow-on).
 *
 * Request:
 *   POST /cms/series/reorder
 *   Form fields:
 *     csrf_token  CSRF token (Csrf::verify)
 *     series_id   int
 *     article_ids[] ordered list of content.id values (position 0 is part 1)
 *
 * Response (application/json):
 *   { "ok": true }
 *   { "ok": false, "error": "..." }  — HTTP 400 or 403
 *
 * The drag handler in cms/views/series.php fires this. Reads the
 * existing parts for the given series and rejects any id not currently
 * in it (tamper-resistant). On success rewrites series_order to 1..N
 * in the supplied order.
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

$seriesId = (int)($_POST['series_id'] ?? 0);
$ids      = $_POST['article_ids'] ?? [];
if (!is_array($ids)) $ids = [];

$cleanIds = [];
foreach ($ids as $aid) {
    $aid = (int)$aid;
    if ($aid > 0) $cleanIds[] = $aid;
}

$res = reorder_series_parts($seriesId, $cleanIds);
if (!$res['ok']) {
    http_response_code(400);
}
echo json_encode($res);
