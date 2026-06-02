<?php
declare(strict_types=1);

/**
 * cms/views/post-template-preview.php — live template preview.
 *
 * Renders the actual `templates/<slug>.php` against a fully-populated
 * synthetic $ctx (lib/preview_data.php). Wrapped in the real
 * master-layout.php so the preview is exactly what the public site emits
 * when the template is rendered for a published row — only the data is
 * synthetic. Iframed by the Preview tab in /cms/post-template.
 *
 * Auth-gated: live editorial chrome leaks no data, but the route lives
 * under /cms/ so it stays behind login for consistency with the rest of
 * the admin surface.
 *
 * Special cases:
 *   - tpl=master         → renders article-standard with every block
 *                          populated (the comprehensive showcase).
 *   - tpl=experiment-html → renders the experiment.php variant (the
 *                          html-passthrough template bypasses master-layout
 *                          and readfiles a /content folder we don't have
 *                          in preview; the chrome we want IS experiment.php).
 *   - tpl=article-series → renders article-standard.php (series.php is
 *                          folded into article-standard via the topstrip).
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/render.php';
require_once __DIR__ . '/../../lib/preview_data.php';

Auth::require_login();

$tpl = (string)($_GET['tpl'] ?? '');
$validTpl = [
    'master',
    'article-standard',
    'article-html-body',     // Phase 20.3
    'article-series',
    'journal-entry',
    'live-session',
    'experiment',
    'experiment-html-body',  // Phase 20.3
    'experiment-html',       // legacy alias for the html-swap variant
];
if (!in_array($tpl, $validTpl, true)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unknown template: " . htmlspecialchars($tpl, ENT_QUOTES, 'UTF-8');
    return;
}

$ctx = preview_ctx($tpl);
if ($ctx === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "preview_ctx returned null for: " . htmlspecialchars($tpl, ENT_QUOTES, 'UTF-8');
    return;
}

// Phase 20.3: experiment-html (html-swap mode) bypasses master-layout and
// would readfile() a real /content file. In synthetic preview there is no
// file — show a placeholder explaining that swap-mode previews depend on a
// real file in /content/<type>/<slug>/.
if ($tpl === 'experiment-html') {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex,nofollow');
    echo '<!doctype html><meta charset="utf-8"><title>HTML swap preview</title>'
       . '<style>body{font-family:system-ui;padding:60px;color:#444;max-width:560px;margin:0 auto;line-height:1.55}'
       . 'code{background:#f3f3f3;padding:2px 6px;border-radius:3px;font-size:13px}'
       . 'h1{font-size:18px;margin:0 0 12px}</style>'
       . '<h1>HTML-swap preview needs a real file</h1>'
       . '<p>The html-swap body mode renders the file at <code>/content/experiment/&lt;slug&gt;/&lt;source_file&gt;</code> '
       . 'directly, bypassing the article chrome. There is no synthetic stand-in for that file in the Post Templates view; '
       . 'open a real experiment-html draft from <code>/cms/experiments</code> and use its Preview tab to see the full passthrough.</p>';
    return;
}

// Chrome dispatch. Master + article-series + article-html-body fold to
// article-standard's chrome. Experiment-html-body folds to experiment.php.
$tplFileMap = [
    'master'                => 'article-standard.php',
    'article-standard'      => 'article-standard.php',
    'article-html-body'     => 'article-standard.php',
    'article-series'        => 'article-standard.php',
    'journal-entry'         => 'journal-entry.php',
    'live-session'          => 'live-session.php',
    'experiment'            => 'experiment.php',
    'experiment-html-body'  => 'experiment.php',
];
$tplFile = dirname(__DIR__, 2) . '/templates/' . $tplFileMap[$tpl];

if (!is_file($tplFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Template file missing: " . htmlspecialchars($tplFileMap[$tpl], ENT_QUOTES, 'UTF-8');
    return;
}

// Mirror render_content()'s title/description derivation — journals
// surface key_statement; everything else uses title + summary.
$row = $ctx['article'];
$page_title = (string)($row['key_statement'] ?? '');
if ($page_title === '') $page_title = (string)($row['title'] ?? 'Untitled');
$page_description = (string)($row['summary'] ?? '');

if (!defined('TEMPLATE_OK')) define('TEMPLATE_OK', true);

// Phase 20.2: drop nav/footer/breadcrumb in the template preview so the
// editor sees just the rendered content surface.
$preview_no_chrome = true;

ob_start();
require $tplFile;
$body_slot = ob_get_clean();

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex,nofollow');
require dirname(__DIR__, 2) . '/templates/master-layout.php';
