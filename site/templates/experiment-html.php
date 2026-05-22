<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/experiment-html.php — raw HTML passthrough (Phase 10).
 *
 * Bypasses master-layout entirely. The selected file is streamed via
 * readfile() with no chrome, no nav, no wrapper — the experiment author
 * controls everything inside <html>. See CMS-STRUCTURE.md §12.
 *
 * Expects $ctx (set by render_content):
 *   $ctx['article']   row from `content` (template='experiment-html')
 *
 * Source resolution:
 *   source_file holds just the filename (e.g. "main.html"); the folder is
 *   /content/experiment/<slug>/. folder_file_path() guards against any
 *   attempt to escape the folder via traversal or absolute paths.
 *
 * Failure modes:
 *   - source_file unset: 404 (the row is published but the author hasn't
 *     picked a file yet — treat that as not-yet-live).
 *   - file missing on disk: 404 (the folder was set up but the file was
 *     never uploaded, or was deleted out of band).
 */
$row    = $ctx['article'] ?? [];
$slug   = (string)($row['slug']        ?? '');
$file   = (string)($row['source_file'] ?? '');

if ($slug === '' || $file === '') {
    render_404();
    return;
}

$path = folder_file_path('experiment', $slug, $file);
if ($path === null) {
    render_404();
    return;
}

header('Content-Type: text/html; charset=utf-8');
readfile($path);
