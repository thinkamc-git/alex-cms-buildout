<?php
/**
 * cms/views/resume-preview.php — draft preview endpoint.
 *
 * POST /cms/library/resumes/preview
 *   csrf_token, html, css → renders HTML+CSS in-memory (full document)
 *
 * Iframed from the Draft Preview pane in resume-edit.php.
 * Returns a full HTML document (not just a fragment) because the
 * résumé is itself a standalone page.
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

$html = (string)($_POST['html'] ?? '');
$css  = (string)($_POST['css']  ?? '');

// Fold the CSS into the document and stream it as the iframe's srcdoc source.
$output = _resume_inject_style($html, $css);

header('Content-Type: text/html; charset=utf-8');
echo $output;
