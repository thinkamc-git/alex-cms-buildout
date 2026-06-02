<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-body.php — body content slot.
 *
 * Body source depends on body_mode (Phase 20.3):
 *   - 'rtf'       → echo content.body (Tiptap output, already sanitized
 *                    on save by lib/sanitize.php; emit raw).
 *   - 'html-body' → readfile() the file referenced by content.source_file
 *                    inside /content/<content.type>/<slug>/. The author owns
 *                    everything inside the file; no sanitization runs.
 *   - 'html-swap' → never reaches here. lib/render.php intercepts and
 *                    renders the file as the whole document, bypassing
 *                    master-layout entirely.
 *
 * The .article-prose wrapper carries the typographic rules from
 * style-articles.css §5 — both modes share it so an html-body file's
 * paragraphs/headings inherit the same scale as Tiptap output.
 */
$row      = $ctx['article'] ?? [];
$bodyMode = (string)($row['body_mode'] ?? 'rtf');

if ($bodyMode === 'html-body') {
    require_once dirname(__DIR__, 2) . '/lib/folders.php';
    $type = (string)($row['type'] ?? '');
    $slug = (string)($row['slug'] ?? '');
    $file = (string)($row['source_file'] ?? '');
    if ($type === '' || $slug === '' || $file === '') return;
    $path = folder_file_path($type, $slug, $file);
    if ($path === null) return;
    ?>
    <div class="article-prose" data-block="body" data-body-mode="html-body">
    <?php readfile($path); ?>
    </div>
    <?php
    return;
}

// Default: RTF mode.
$body = (string)($row['body'] ?? '');
if (trim($body) === '') return;
?>
<div class="article-prose" data-block="body">
<?= $body ?>
</div>
