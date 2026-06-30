<?php
declare(strict_types=1);

/**
 * cms/views/pages-preview-form.php — page-preview rendered from POST'd form values.
 *
 * Phase 21.x: counterpart to post-preview-form.php, but for the Pages CMS.
 * preview-tab-guard.js POSTs the body-form contents here whenever the user
 * flips to the Preview tab; we build a synthetic $preview_mock and route
 * the request through _pages/_layout/_page-shell.php so the marketing-page
 * shell renders the in-memory body instead of the stored mock.
 *
 * Cascade behaviour ($_is_staging inside the shell):
 *   - Page slug (about, coaching, …)  → preview_mock overrides the page body
 *   - Partial slug (header, footer)  → preview_mock overrides that partial
 *                                      on top of the `about` chrome
 *
 * Auth-gated. POST-only. No CSRF (matches post-preview-form.php).
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/pages.php';

Auth::require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Method not allowed — use POST.";
    return;
}

$slug      = trim((string)($_POST['slug'] ?? ''));
$body_html = (string)($_POST['body_html'] ?? '');
$style_css = (string)($_POST['style_css'] ?? '');

if ($slug === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing slug.";
    return;
}

// Validate slug points at a known page or partial — the Pages CMS keeps a
// manifest of editable files; anything outside it doesn't get to render.
$file_row = find_page_file($slug);
if ($file_row === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unknown page slug: " . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8');
    return;
}

// Synthetic mock — mirrors the shape `_page-shell.php` expects from the
// DB-lookup branch at line ~91. Setting $preview_mock before the shell
// short-circuits its `!$preview_mock` guard, so the shell uses ours.
// Preview-only fold: prepend the page-scoped style as a <style> block so the
// preview reflects it (browsers accept <style> in body). The publish-time fold
// writes it into the file properly; this is throwaway preview output.
if (trim($style_css) !== '') {
    $body_html = "<style>\n" . $style_css . "\n</style>\n" . $body_html;
}

$preview_mock = [
    'slug'      => $slug,
    'body_html' => $body_html,
];

// Partials (header/footer) preview against the /about/ chrome — they're
// not pages on their own. Page slugs preview against themselves.
$is_partial = ($file_row['kind'] === 'partial');
$body       = $is_partial ? 'about' : $slug;
$title       = (string)$file_row['filename'];
$description = '';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex,nofollow');

// For partial previews (header/footer), suppress the page body so the
// preview focuses on the partial itself. Setting $_body_html_override to ''
// before the shell runs prevents the body file from loading — the shell's
// `!== null` check short-circuits to echo '' instead.
// For partial previews, signal the shell to render in focus mode:
// header focus = tinted spacer body + no footer;
// footer focus = minimal spacer body + no header, no tint.
if ($is_partial) {
    $_preview_partial_focus = $slug;
}

$_root = dirname(__DIR__, 2);
$_shell_path = null;
foreach ([$_root . '/_pages/_layout/_page-shell.php', $_root . '/_layout/_page-shell.php'] as $_p) {
    if (is_file($_p)) { $_shell_path = $_p; break; }
}
if ($_shell_path === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Page shell not found.";
    return;
}

require $_shell_path;
