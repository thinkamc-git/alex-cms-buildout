<?php
/**
 * cms/views/journal-delete.php — hard-delete a journal.
 *
 * POST /cms/journals/delete?id=N. Typed-slug confirm for Published rows
 * (front-end JS gate + server-side check, same shape as article-delete).
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
    header('Location: /cms/journals?flash=' . rawurlencode('Session expired — try again.'));
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /cms/journals');
    exit;
}

$journal = get_journal($id);
if ($journal === null) {
    header('Location: /cms/journals?flash=' . rawurlencode('Already gone.'));
    exit;
}

$status = (string)($journal['status'] ?? '');
$slug   = (string)($journal['slug']   ?? '');
if ($status === 'published') {
    $typed = trim((string)($_POST['typed_slug'] ?? ''));
    if ($typed === '' || $typed !== $slug) {
        header('Location: /cms/journals/edit?id=' . (int)$id
            . '&flash=' . rawurlencode('Slug confirmation did not match — nothing deleted.'));
        exit;
    }
}

delete_journal($id);

header('Location: /cms/journals?flash=' . rawurlencode('Journal deleted.'));
exit;
