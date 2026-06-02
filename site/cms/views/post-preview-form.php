<?php
declare(strict_types=1);

/**
 * cms/views/post-preview-form.php — preview rendered from POST'd form values.
 *
 * Mirrors post-preview.php (DB-driven) but uses $_POST values on top of the
 * existing DB row so the Preview tab reflects the editor's current in-memory
 * state without persisting anything. Called by preview-tab-guard.js when the
 * user toggles to Preview.
 *
 * Strategy: load the DB row by ?id, then patch fields the form sent over the
 * top. Anything the form didn't send (e.g. category, series, author config)
 * falls back to the DB value. This way the iframe shows live form changes
 * for the editable surface, while inherited context (joins) stays consistent.
 *
 * Auth-gated. POST-only.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';
require_once __DIR__ . '/../../lib/author.php';
require_once __DIR__ . '/../../lib/render.php';
require_once __DIR__ . '/../../lib/folders.php';

Auth::require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Method not allowed — use POST.";
    return;
}

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

// Editable surface — fields the post-edit forms can change. Anything else
// (status, slug, template type, series_id) stays as the saved DB value.
// Some fields use different POST names than column names; map deliberately.
$editable = [
    'title', 'summary', 'body', 'key_statement', 'hero_image', 'hero_caption',
    'hero_size', 'special_tag', 'read_time', 'tags', 'location', 'cost_pill',
    'custom_pill', 'attendance', 'event_start', 'journal_number',
    // Phase 20.3: body_mode + source_file flow with the form so toggling
    // RTF↔HTML in the edit panel re-renders the preview correctly.
    'body_mode', 'source_file',
];
foreach ($editable as $col) {
    if (array_key_exists($col, $_POST)) {
        // Empty strings should clear the field (turn into NULL-like falsy);
        // pass through as-is, the public templates already handle blanks.
        $row[$col] = (string)$_POST[$col];
    }
}

// show_author / show_author_bio — checkbox semantics (present = on).
$row['show_author']     = isset($_POST['show_author']) ? 1 : (int)($row['show_author'] ?? 1);
$row['show_author_bio'] = isset($_POST['show_author_bio']) ? 1 : (int)($row['show_author_bio'] ?? 1);

// Category override: form may post a primary category slug to swap the join.
$cat = null;
$catSlug = (string)($_POST['primary_category'] ?? '');
if ($catSlug !== '') {
    $cStmt = db()->prepare(
        "SELECT value_slug, label, colour FROM categories
         WHERE type = :type AND value_slug = :slug LIMIT 1"
    );
    $cStmt->execute([':type' => (string)$row['type'], ':slug' => $catSlug]);
    $cRow = $cStmt->fetch();
    if ($cRow !== false) $cat = $cRow;
}
if ($cat === null) {
    // Fall back to the row's saved primary category (same join as post-preview.php).
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
}

// Series — keep saved value (no inline editor for series_id in post-edit).
$series = null;
if (!empty($row['series_id'])) {
    $sStmt = db()->prepare('SELECT id, name, slug FROM series WHERE id = :id LIMIT 1');
    $sStmt->execute([':id' => (int)$row['series_id']]);
    $sRow = $sStmt->fetch();
    if ($sRow !== false) {
        $cStmt = db()->prepare("SELECT COUNT(*) AS n FROM content WHERE series_id = :id AND status='published'");
        $cStmt->execute([':id' => (int)$row['series_id']]);
        $sRow['_count'] = (int)($cStmt->fetch()['n'] ?? 0);
        $myOrder = (int)($row['series_order'] ?? 0);
        $pStmt = db()->prepare(
            "SELECT COUNT(*) AS n FROM content
              WHERE series_id = :sid AND status='published'
                AND series_order IS NOT NULL AND series_order <= :ord"
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

// Phase 20.3: body_mode='html-swap' previews the readfile() output as-is.
// The POST'd source_file + body_mode take precedence over the DB row,
// so toggling RTF↔HTML in the edit panel re-renders the preview correctly.
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
           . 'h1{font-size:18px;margin:0 0 12px}</style>'
           . '<h1>No HTML file to preview yet</h1>'
           . '<p>html-swap renders the file referenced in <code>source_file</code> directly. '
           . 'Drop a file into <code>' . $folderPath . '</code> via SSH/CloudMounter, '
           . 'then pick it from the Content Folder selector in the Edit tab.</p>'
           . '<p style="color:#888;font-size:13px">Currently selected: <code>' . $expected . '</code></p>';
    }
    return;
}

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
