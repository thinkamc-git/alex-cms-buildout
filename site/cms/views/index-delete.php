<?php
/**
 * cms/views/index-delete.php — hard-delete an index (Phase 12).
 *
 * Routed as POST /cms/indexes/delete?id=N. Confirmation handled inline
 * by the indexes-list JS (data-confirm attribute on the form). Deleting
 * a seeded section index (e.g. /writing/) is allowed — author can
 * recreate from /cms/indexes/new if they regret it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/indexes.php';

Auth::require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /cms/indexes?flash=' . rawurlencode('Session expired — try again.'));
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /cms/indexes');
    exit;
}

$idx = get_index($id);
if ($idx === null) {
    header('Location: /cms/indexes?flash=' . rawurlencode('Already gone.'));
    exit;
}

$res = delete_index($id);
$flash = $res['ok']
    ? 'Index /' . (string)$idx['slug'] . '/ deleted.'
    : ($res['error'] ?: 'Delete failed.');

header('Location: /cms/indexes?flash=' . rawurlencode($flash));
exit;
