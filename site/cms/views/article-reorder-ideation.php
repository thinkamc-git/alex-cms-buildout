<?php
/**
 * cms/views/article-reorder-ideation.php — AJAX endpoint for Ideation drag.
 *
 * POST /cms/articles/reorder-ideation
 *
 * Body:
 *   csrf_token=<token>
 *   type=<article|journal|live-session|experiment|none>
 *   ids[]=<id>&ids[]=<id>...      (full new order of the target column,
 *                                  top-of-column first)
 *
 * Semantics: a single drop either reorders within a column (source and
 * target column are the same) or moves a card across columns (re-typing
 * it as a side-effect of the drop). The DROP TARGET column's order is
 * what arrives in `ids[]`; the source column's order is recomputed by
 * a second AJAX call from the client if the drop was cross-column.
 *
 * Type "none" maps to NULL (the "No type" column).
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

$typeRaw = (string)($_POST['type'] ?? '');
$type    = $typeRaw === 'none' || $typeRaw === '' ? null : $typeRaw;
if ($type !== null && !in_array($type, CONTENT_TYPES, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown type.']);
    exit;
}

$idsRaw = $_POST['ids'] ?? [];
if (!is_array($idsRaw)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ids must be an array.']);
    exit;
}
$ids = array_values(array_map('intval', $idsRaw));

// Step 1: for any id whose current type differs from the target, retype
// it (set_article_type guards: status must be 'idea'). This is the
// cross-column move side-effect.
foreach ($ids as $id) {
    $stmt = db()->prepare("SELECT type, status FROM content WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row === false) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Row #' . $id . ' not found.']);
        exit;
    }
    if ((string)($row['status'] ?? '') !== 'idea') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Only Idea-stage rows are draggable in Ideation.']);
        exit;
    }
    $cur = $row['type'] === null ? null : (string)$row['type'];
    if ($cur !== $type) {
        $res = set_article_type($id, $type);
        if (!$res['ok']) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $res['error']]);
            exit;
        }
    }
}

// Step 2: rewrite pipeline_order for the (status=idea, type=<target>) lane
// to the new order. After step 1 every id is in this lane, so reorder_lane's
// membership check will accept them.
$criteria = ['status' => 'idea', 'type' => $type];  // type may be null
$res = reorder_lane($criteria, $ids);
if (!$res['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $res['error']]);
    exit;
}

echo json_encode(['ok' => true]);
