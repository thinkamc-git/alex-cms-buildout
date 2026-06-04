<?php
/**
 * cms/views/index-upload-image.php — Editorial-section image upload.
 *
 * Routed from site/index.php as POST /cms/indexes/upload-image.
 *
 * The Hero section editor's "Upload" button posts a single `image`
 * file field + csrf_token here. We hand it to lib/uploads.php's
 * accept_upload() under uploads/indexes/, then return the public URL
 * as JSON. The CMS JS stuffs that URL into the section's hero_image_url
 * field.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
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

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing image field.']);
    exit;
}

$result = accept_upload($_FILES['image'], 'indexes');
if (!$result['ok']) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string)$result['error']]);
    exit;
}

echo json_encode(['ok' => true, 'url' => (string)$result['url']]);
