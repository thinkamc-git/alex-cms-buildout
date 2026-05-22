<?php
/**
 * cms/views/experiment-upload-image.php — Tiptap inline image upload for
 * the article-format experiment editor (Phase 10).
 *
 * Mirrors article-upload-image.php. The endpoint is type-specific so the
 * upload destination (`content/experiment/<slug>/inline`) is co-located
 * with the experiment's other content, matching the per-type uploads
 * convention in CMS-STRUCTURE.md §13.
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

$posted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!Csrf::verify($posted)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF failed.']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$experiment = $id > 0 ? get_experiment($id) : null;
if ($experiment === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Experiment not found.']);
    exit;
}

$slug = (string)($experiment['slug'] ?? '');
if ($slug === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Experiment has no slug.']);
    exit;
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing image field.']);
    exit;
}

$result = accept_upload($_FILES['image'], 'content/experiment/' . $slug . '/inline');
if (!$result['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string)$result['error']]);
    exit;
}

echo json_encode(['ok' => true, 'url' => (string)$result['url']]);
