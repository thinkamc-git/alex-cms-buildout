<?php
declare(strict_types=1);

/**
 * lib/render.php — public-site content render entry point.
 *
 * `render_content($slug)` resolves a slug to a published row, joins the
 * primary category, hydrates the author config, picks the template by
 * the row's `template` enum, and emits a full HTML response wrapped in
 * master-layout.php. Outputs a themed 404 when no published row matches.
 *
 * The TEMPLATE_OK gate keeps template + partial files from being served
 * directly via HTTP: a stray request bypassing the front controller hits
 * the http_response_code(404) line at the top of each template.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/author.php';

/**
 * Include a block partial in a clean scope — only $ctx and $slug are
 * visible inside the partial. Missing block files are silently skipped
 * so the template stays resilient when a block hasn't been built yet
 * (Phase 6b ships 13 of the 19; later phases fill in the rest).
 */
function render_block(string $slug, array $ctx): void
{
    $path = dirname(__DIR__) . '/templates/partials/block-' . $slug . '.php';
    if (!is_file($path)) return;
    include $path;
}

/**
 * Resolve a slug to a published row, render it, or emit a themed 404.
 * The status gate keeps drafts/concepts/outlines/ideas off the public
 * site even if their slug is guessed.
 */
function render_content(string $slug): void
{
    $slug = trim($slug);
    if ($slug === '') {
        render_404();
        return;
    }

    $stmt = db()->prepare(
        "SELECT * FROM content
         WHERE slug = :slug
           AND status = 'published'
           AND (published_status IS NULL OR published_status = 'live')
         LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    if ($row === false) {
        render_404();
        return;
    }

    // Primary category, if any. Schema stores the slug-shaped value in
    // content_categories.category and the human label + colour in the
    // categories lookup. content_categories.is_primary picks the
    // breadcrumb category when a row has more than one.
    $cat = null;
    $catStmt = db()->prepare(
        "SELECT c.value_slug, c.label, c.colour
         FROM content_categories cc
         JOIN categories c ON c.type = cc.type AND c.value_slug = cc.category
         WHERE cc.content_id = :id
         ORDER BY cc.is_primary DESC, cc.id ASC
         LIMIT 1"
    );
    $catStmt->execute([':id' => (int)$row['id']]);
    $catRow = $catStmt->fetch();
    if ($catRow !== false) $cat = $catRow;

    // Series (article-series template only — left null otherwise). We
    // still try the join for article-standard since the spec allows an
    // optional series there; the partial no-ops on null.
    $series = null;
    if (!empty($row['series_id'])) {
        $sStmt = db()->prepare('SELECT id, name, slug FROM series WHERE id = :id LIMIT 1');
        $sStmt->execute([':id' => (int)$row['series_id']]);
        $sRow = $sStmt->fetch();
        if ($sRow !== false) {
            // Count members for the "Part N of M" line. Cheap enough to do inline.
            $cStmt = db()->prepare('SELECT COUNT(*) AS n FROM content WHERE series_id = :id AND status = \'published\'');
            $cStmt->execute([':id' => (int)$row['series_id']]);
            $cntRow = $cStmt->fetch();
            $sRow['_count'] = (int)($cntRow['n'] ?? 0);
            $series = $sRow;
        }
    }

    $ctx = [
        'article'  => $row,
        'author'   => author_display(get_author()),
        'category' => $cat,
        'series'   => $series,
    ];

    // Pick template. Phase 6b ships article-standard; later phases add
    // article-series (8a), journal-entry (8), live-session (9),
    // experiment/experiment-html (10). Unknown templates 404 so the
    // public site never renders a partially-built type.
    $template = (string)($row['template'] ?? '');
    $known = [
        'article-standard' => 'article-standard.php',
        'journal-entry'    => 'journal-entry.php',
    ];
    if (!isset($known[$template])) {
        render_404();
        return;
    }

    // Journals don't render a Title block publicly — key_statement is the
    // visible "headline". Use it for the <title> when present.
    $page_title = (string)($row['key_statement'] ?? '');
    if ($page_title === '') $page_title = (string)($row['title'] ?? 'Untitled');
    $page_description = (string)($row['summary'] ?? '');

    if (!defined('TEMPLATE_OK')) define('TEMPLATE_OK', true);

    ob_start();
    require dirname(__DIR__) . '/templates/' . $known[$template];
    $body_slot = ob_get_clean();

    header('Content-Type: text/html; charset=utf-8');
    require dirname(__DIR__) . '/templates/master-layout.php';
}

/**
 * Themed 404 — same source the router uses for unrouted paths. Keeping
 * the two paths converged means a wrong /writing/[bad-slug] looks the
 * same as a wrong /[anything-else].
 */
function render_404(): void
{
    http_response_code(404);
    $page = dirname(__DIR__) . '/404.html';
    if (is_file($page)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($page);
        return;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 — content not found\n";
}
