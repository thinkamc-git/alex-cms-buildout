<?php
/**
 * cms/views/article-reorder-pipeline.php — AJAX endpoint for Pipeline drag.
 *
 * POST /cms/articles/reorder-pipeline
 *
 * Body (application/x-www-form-urlencoded or JSON):
 *   csrf_token=<token>
 *   stage=<idea|concept|outline|draft|published>
 *   ids[]=<id>&ids[]=<id>...      (new order, top-of-lane first)
 *
 * On success returns 200 OK with JSON `{ok: true}`. On any rejection,
 * 4xx with `{ok: false, error: "..."}`. The drag-drop JS keeps the DOM
 * in the new order optimistically and only reverts on a non-2xx reply.
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
    echo json_encode(['ok' => false, 'error' => 'POST only.']);
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Session expired.']);
    exit;
}

$stage = (string)($_POST['stage'] ?? '');
if (stage_index($stage) < 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown stage.']);
    exit;
}

$idsRaw = $_POST['ids'] ?? [];
if (!is_array($idsRaw)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ids must be an array.']);
    exit;
}
$ids = array_values(array_map('intval', $idsRaw));

// Pipeline lane = (type='article', status=<stage>). Other types don't
// surface in Pipeline today, so reorder operates against the Articles
// view of Pipeline.
$res = reorder_lane(['type' => 'article', 'status' => $stage], $ids);
if (!$res['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $res['error']]);
    exit;
}

echo json_encode(['ok' => true]);
