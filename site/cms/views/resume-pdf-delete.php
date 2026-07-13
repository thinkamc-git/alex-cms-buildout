<?php
/**
 * cms/views/resume-pdf-delete.php — PDF delete endpoint.
 *
 * POST /cms/library/resumes/pdf/delete
 *   csrf_token, id → redirect back to /cms/library/resumes?tab=pdfs
 *
 * Wired via confirm.js's data-confirm form in resume-edit.php.
 * Deletes DB row + file from disk via delete_resume_pdf().
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/resumes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

Auth::require_login();

if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    delete_resume_pdf($id);
}

header('Location: /cms/resumes?tab=pdfs&flash=' . rawurlencode('PDF deleted.'));
exit;
