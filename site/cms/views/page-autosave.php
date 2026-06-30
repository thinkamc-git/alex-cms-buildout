<?php
/**
 * cms/views/page-autosave.php — AJAX autosave endpoint for page drafts.
 *
 * POST body: csrf_token, id (mock id), body_html
 * Returns JSON: { ok: bool, saved_at: ISO8601 } | { ok: false, error: string }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/pages.php';

Auth::require_login();
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    return;
}
if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    echo json_encode(['ok' => false, 'error' => 'CSRF token invalid — reload and try again']);
    return;
}
$id    = (int)($_POST['id'] ?? 0);
$body  = (string)($_POST['body_html'] ?? '');
// Style override (P2): present in POST = save it (may be ''); absent = leave unchanged.
$style = array_key_exists('style_css', $_POST) ? (string)$_POST['style_css'] : null;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid draft id']);
    return;
}
update_page_mock($id, $body, null, [], $style);
echo json_encode(['ok' => true, 'saved_at' => date('c')]);
