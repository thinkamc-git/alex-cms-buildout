<?php
/**
 * cms/views/article-upload-image.php — Tiptap inline image upload endpoint.
 *
 * Routed from site/index.php as POST /cms/articles/upload-image?id=N.
 *
 * The Tiptap toolbar's "Image" button fires a multipart POST here with a
 * single file field named `image` and a CSRF header. We hand the upload to
 * accept_upload() and return the public URL as JSON. The editor inserts
 * <img src="..."> at the current selection.
 *
 * ENGINEERING.md §9 forbids AJAX form submission "unless explicitly called
 * for in BUILD-PLAN.md" — Phase 6a calls it for: "button → upload to
 * lib/uploads.php → URL inserted as <img src=…>". This is the documented
 * exception, scoped to the editor's inline-image button only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';
require_once __DIR__ . '/../../lib/uploads.php';

Auth::require_login();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST only.']);
    exit;
}

// CSRF lives in the request header (X-CSRF-Token) since Tiptap doesn't post
// a normal form. lib/csrf.php's Csrf::verify() compares against $_SESSION.
$posted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!Csrf::verify($posted)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF failed.']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$article = $id > 0 ? get_article($id) : null;
if ($article === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Article not found.']);
    exit;
}

$slug = (string)($article['slug'] ?? '');
if ($slug === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Article has no slug.']);
    exit;
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing image field.']);
    exit;
}

$result = accept_upload($_FILES['image'], 'content/article/' . $slug . '/inline');
if (!$result['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string)$result['error']]);
    exit;
}

echo json_encode(['ok' => true, 'url' => (string)$result['url']]);
