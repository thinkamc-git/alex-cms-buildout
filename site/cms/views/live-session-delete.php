<?php
/**
 * cms/views/live-session-delete.php — hard-delete a live-session.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';

Auth::require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    header('Location: /cms/live-sessions?flash=' . rawurlencode('Session expired — try again.'));
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /cms/live-sessions');
    exit;
}

$session = get_live_session($id);
if ($session === null) {
    header('Location: /cms/live-sessions?flash=' . rawurlencode('Already gone.'));
    exit;
}

$status = (string)($session['status'] ?? '');
$slug   = (string)($session['slug']   ?? '');
if ($status === 'published') {
    $typed = trim((string)($_POST['typed_slug'] ?? ''));
    if ($typed === '' || $typed !== $slug) {
        header('Location: /cms/live-sessions/edit?id=' . (int)$id
            . '&flash=' . rawurlencode('Slug confirmation did not match — nothing deleted.'));
        exit;
    }
}

delete_live_session($id);

header('Location: /cms/live-sessions?flash=' . rawurlencode('Live session deleted.'));
exit;
