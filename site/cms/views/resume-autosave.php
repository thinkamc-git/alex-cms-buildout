<?php
/**
 * cms/views/resume-autosave.php — AJAX draft-save endpoint.
 *
 * POST /cms/library/resumes/autosave
 *   csrf_token, html, css → JSON {ok:true, saved_at:<epoch>}
 *
 * Called by rvSaveDraft() in resume-edit.php.
 * Mirrors page-autosave.php behaviour exactly.
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

$html = (string)($_POST['html'] ?? '');
$css  = (string)($_POST['css']  ?? '');

save_resume_draft($html, $css);

echo json_encode(['ok' => true, 'saved_at' => time()]);
