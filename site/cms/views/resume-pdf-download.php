<?php
/**
 * cms/views/resume-pdf-download.php — serve a stored PDF for download.
 *
 * GET /cms/library/resumes/pdf/<id>/download
 *   Streams the file with Content-Disposition: attachment.
 *   Auth-gated — CMS login required.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/resumes.php';

Auth::require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit;
}

$stmt = db()->prepare('SELECT filename, original_name FROM resume_pdfs WHERE id = ? AND resume_id = 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit;
}

$path = dirname(__DIR__, 2) . '/uploads/resumes/' . basename((string)$row['filename']);
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$download_name = $row['original_name'] ?: basename((string)$row['filename']);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . addslashes((string)$download_name) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-cache');
readfile($path);
exit;
