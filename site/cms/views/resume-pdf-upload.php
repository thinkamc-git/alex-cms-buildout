<?php
/**
 * cms/views/resume-pdf-upload.php — PDF upload endpoint.
 *
 * POST /cms/library/resumes/pdf/upload
 *   csrf_token, pdf_file (file), last_modified_ms (int ms from JS File.lastModified)
 *   → JSON {ok:true, id, filename, pdf_date}
 *
 * Called by the drag-drop zone JS in resume-edit.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/resumes.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

Auth::require_login();

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

if (empty($_FILES['pdf_file'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No file received.']);
    exit;
}

try {
    $filename      = accept_resume_pdf_upload($_FILES['pdf_file']);
    $original_name = basename((string)$_FILES['pdf_file']['name']);
    $id            = create_resume_pdf($filename, $original_name, '', '');
    echo json_encode(['ok' => true, 'id' => $id, 'filename' => $filename]);
} catch (RuntimeException $ex) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
}
