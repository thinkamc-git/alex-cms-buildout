<?php
/**
 * cms/views/article-delete.php — hard-delete an article.
 *
 * Routed from site/index.php as POST /cms/articles/delete?id=N.
 * Phase 6a Decision: hard-delete with confirmation modal (handled in
 * the calling page's JS). No soft-delete in v1.
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
    header('Location: /cms/articles?flash=' . rawurlencode('Session expired — try again.'));
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /cms/articles');
    exit;
}

$article = get_article($id);
if ($article === null) {
    header('Location: /cms/articles?flash=' . rawurlencode('Already gone.'));
    exit;
}

// Phase 7: deleting a Published article requires the author to type the
// slug into a JS prompt; the typed value is round-tripped via the form's
// `typed_slug` hidden input. The JS gate is the primary UX, but checking
// server-side too means a missing JS layer can't bypass the safety.
$status = (string)($article['status'] ?? '');
$slug   = (string)($article['slug']   ?? '');
if ($status === 'published') {
    $typed = trim((string)($_POST['typed_slug'] ?? ''));
    if ($typed === '' || $typed !== $slug) {
        header('Location: /cms/articles/edit?id=' . (int)$id
            . '&flash=' . rawurlencode('Slug confirmation did not match — nothing deleted.'));
        exit;
    }
}

delete_article($id);

header('Location: /cms/articles?flash=' . rawurlencode('Article deleted.'));
exit;
