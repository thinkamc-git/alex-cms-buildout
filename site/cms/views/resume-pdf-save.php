<?php
/**
 * cms/views/resume-pdf-save.php — inline PDF metadata save endpoint.
 *
 * POST /cms/library/resumes/pdf/save
 *   csrf_token, id, pdf_date, note → JSON {ok:true}
 *
 * Called on blur from the Date + Note inputs in the PDF table.
 * No dirty-flip involved — pure autosave with a brief "Saved" indicator.
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

$id       = (int)($_POST['id']       ?? 0);
$pdf_date = (string)($_POST['pdf_date'] ?? '');
$note     = (string)($_POST['note']     ?? '');

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid id.']);
    exit;
}

// Validate YYYY-MM format — allow blank (user may clear it).
if ($pdf_date !== '' && !preg_match('/^\d{4}-\d{2}$/', $pdf_date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Date must be YYYY-MM or blank.']);
    exit;
}

update_resume_pdf($id, $pdf_date, $note);
echo json_encode(['ok' => true]);
