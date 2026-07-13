<?php
/**
 * cms/views/resume-public.php — public résumé route handler.
 *
 * GET /resume/ — serves published_html directly as a full document.
 * No CMS shell, no design system — the HTML is self-contained.
 * Returns 404 if not yet published.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/resumes.php';

$resume = get_resume();

if (!$resume || !(bool)$resume['is_published'] || trim((string)$resume['published_html']) === '') {
    http_response_code(404);
    // Serve the shared error page if it exists.
    $err = __DIR__ . '/../../error.php';
    if (is_file($err)) {
        $_error_code = 404;
        require $err;
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 — résumé not published.";
    }
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo (string)$resume['published_html'];
