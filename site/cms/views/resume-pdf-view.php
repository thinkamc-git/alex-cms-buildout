<?php
/**
 * cms/views/resume-pdf-view.php — serve a PDF inline in the browser.
 *
 * GET /cms/library/resumes/pdf/<id>/view
 *   Content-Disposition: inline — lets the browser render rather than download.
 *   Auth-gated.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/resumes.php';

Auth::require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit; }

$stmt = db()->prepare('SELECT filename, original_name FROM resume_pdfs WHERE id = ? AND resume_id = 1');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { http_response_code(404); exit; }

$path = dirname(__DIR__, 2) . '/uploads/resumes/' . basename((string)$row['filename']);
if (!is_file($path)) { http_response_code(404); exit; }

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . addslashes((string)($row['original_name'] ?: basename((string)$row['filename']))) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-cache');
readfile($path);
exit;
