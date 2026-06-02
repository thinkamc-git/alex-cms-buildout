<?php
declare(strict_types=1);

/**
 * cms/views/post-preview.php — live preview of an in-progress post row.
 *
 * Mirrors lib/render.php::render_content() but reads by id (not slug) and
 * does NOT filter on status='published'. That lets the Preview tab in
 * post edit views show drafts as they would look on the public site.
 *
 * Iframed by article-edit / journal-edit / live-session-edit /
 * experiment-edit when ?tab=preview. Auth-gated.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/author.php';
require_once __DIR__ . '/../../lib/render.php';
require_once __DIR__ . '/../../lib/folders.php';

Auth::require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Missing or invalid ?id";
    return;
}

$stmt = db()->prepare('SELECT * FROM content WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if ($row === false) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Content row not found: id=$id";
    return;
}

// Primary category (mirrors render_content). Falls back to null if none.
$cat = null;
$catStmt = db()->prepare(
    "SELECT c.value_slug, c.label, c.colour
     FROM content_categories cc
     JOIN categories c ON c.type = cc.type AND c.value_slug = cc.category
     WHERE cc.content_id = :id
     ORDER BY cc.is_primary DESC, cc.id ASC
     LIMIT 1"
);
$catStmt->execute([':id' => $id]);
$catRow = $catStmt->fetch();
if ($catRow !== false) $cat = $catRow;

// Series (mirrors render_content but tolerant of incomplete series rows).
$series = null;
if (!empty($row['series_id'])) {
    $sStmt = db()->prepare('SELECT id, name, slug FROM series WHERE id = :id LIMIT 1');
    $sStmt->execute([':id' => (int)$row['series_id']]);
    $sRow = $sStmt->fetch();
    if ($sRow !== false) {
        $cStmt = db()->prepare(
            'SELECT COUNT(*) AS n FROM content
              WHERE series_id = :id AND status = \'published\''
        );
        $cStmt->execute([':id' => (int)$row['series_id']]);
        $sRow['_count'] = (int)($cStmt->fetch()['n'] ?? 0);
        $myOrder = (int)($row['series_order'] ?? 0);
        $pStmt = db()->prepare(
            'SELECT COUNT(*) AS n FROM content
              WHERE series_id = :sid AND status = \'published\'
                AND series_order IS NOT NULL AND series_order <= :ord'
        );
        $pStmt->execute([':sid' => (int)$row['series_id'], ':ord' => $myOrder]);
        $sRow['_position'] = (int)($pStmt->fetch()['n'] ?? 0);
        $series = $sRow;
    }
}

$ctx = [
    'article'  => $row,
    'author'   => author_display(get_author()),
    'category' => $cat,
    'series'   => $series,
];

$template = (string)($row['template'] ?? '');
$bodyMode = (string)($row['body_mode'] ?? 'rtf');

// Phase 20.3: body_mode='html-swap' is a full-page passthrough — readfile()
// the selected source file as-is so the preview matches the public route.
// Placeholder when no file is selected or the file is missing.
if ($bodyMode === 'html-swap') {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex,nofollow');
    $slugRow = (string)($row['slug'] ?? '');
    $file    = (string)($row['source_file'] ?? '');
    $rowType = (string)($row['type'] ?? 'experiment');
    $path    = ($slugRow !== '' && $file !== '')
        ? folder_file_path($rowType, $slugRow, $file)
        : null;
    if ($path !== null) {
        readfile($path);
    } else {
        $folderPath = $slugRow !== '' ? '/content/' . htmlspecialchars($rowType, ENT_QUOTES, 'UTF-8') . '/' . htmlspecialchars($slugRow, ENT_QUOTES, 'UTF-8') . '/' : '/content/<type>/<slug>/';
        $expected   = $file !== '' ? htmlspecialchars($file, ENT_QUOTES, 'UTF-8') : '<no file selected>';
        echo '<!doctype html><meta charset="utf-8"><title>No HTML file</title>'
           . '<style>body{font-family:system-ui;padding:60px;color:#444;max-width:560px;margin:0 auto;line-height:1.55}'
           . 'code{background:#f3f3f3;padding:2px 6px;border-radius:3px;font-size:13px}'
           . 'h1{font-size:18px;margin:0 0 12px}'
           . '</style>'
           . '<h1>No HTML file to preview yet</h1>'
           . '<p>html-swap renders the file referenced in <code>source_file</code> directly. '
           . 'Drop a file into <code>' . $folderPath . '</code> via SSH/CloudMounter, '
           . 'then pick it from the Content Folder selector in the Edit tab.</p>'
           . '<p style="color:#888;font-size:13px">Currently selected: <code>' . $expected . '</code></p>';
    }
    return;
}

// Chrome dispatch (rtf + html-body both share the same chrome; the body
// block reads body_mode internally and routes to readfile() when html-body).
$known = [
    'article-standard' => 'article-standard.php',
    'article-series'   => 'article-standard.php',
    'journal-entry'    => 'journal-entry.php',
    'live-session'     => 'live-session.php',
    'experiment'       => 'experiment.php',
];
if (!isset($known[$template])) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unknown template: " . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
    return;
}

$tplFile = dirname(__DIR__, 2) . '/templates/' . $known[$template];
if (!is_file($tplFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Template file missing: " . htmlspecialchars($known[$template], ENT_QUOTES, 'UTF-8');
    return;
}

// Title/description mirror render_content's derivation.
$page_title = (string)($row['key_statement'] ?? '');
if ($page_title === '') $page_title = (string)($row['title'] ?? 'Untitled');
$page_description = (string)($row['summary'] ?? '');

if (!defined('TEMPLATE_OK')) define('TEMPLATE_OK', true);

// Phase 20.2: previews drop the public chrome (nav, footer, breadcrumb)
// so the editor sees just the rendered content surface.
$preview_no_chrome = true;

ob_start();
require $tplFile;
$body_slot = ob_get_clean();

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex,nofollow');
require dirname(__DIR__, 2) . '/templates/master-layout.php';
