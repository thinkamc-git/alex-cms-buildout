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
require_once __DIR__ . '/indexes.php';

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
        // Slug guard (Phase 11): before 404'ing, check redirects on the
        // full request path. Stored as `old_slug` in `redirects` so each
        // row encodes the full source URL (e.g. /writing/old-name) rather
        // than just the slug component — that lets a redirect cross
        // content types (/writing/foo → /journal/bar). Phase 13 adds the
        // status_code column; until then every redirect is 301 Permanent.
        require_once __DIR__ . '/content.php';
        $path  = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
        $redir = lookup_redirect($path);
        if ($redir !== null && $redir !== '' && $redir !== $path) {
            header('Location: ' . $redir, true, 301);
            return;
        }
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
    //
    // "Part N of M" must count published-only on BOTH sides, otherwise a
    // draft in the middle of the series produces "Part 3 of 2" — the row's
    // raw series_order keeps the unfiltered position while the count
    // filters to published. Computing position via "rank by series_order
    // among published rows" keeps both numbers consistent.
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

            // Published-only position: how many published rows in this
            // series have a series_order at or before mine. Same series_id,
            // status='published', and series_order <= my own.
            $myOrder = (int)($row['series_order'] ?? 0);
            $pStmt = db()->prepare(
                'SELECT COUNT(*) AS n FROM content
                  WHERE series_id = :sid
                    AND status = \'published\'
                    AND series_order IS NOT NULL
                    AND series_order <= :ord'
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

    // Phase 20.3: `template` picks the chrome; `body_mode` picks where the
    // body comes from. Article-series shares article-standard's chrome.
    $template = (string)($row['template'] ?? '');
    $bodyMode = (string)($row['body_mode'] ?? 'rtf');
    $known = [
        'article-standard' => 'article-standard.php',
        'article-series'   => 'article-standard.php',
        'journal-entry'    => 'journal-entry.php',
        'live-session'     => 'live-session.php',
        'experiment'       => 'experiment.php',
    ];
    if (!isset($known[$template])) {
        render_404();
        return;
    }

    // Raw HTML passthrough: body_mode='html-swap' renders the referenced
    // file directly with no master-layout, no CMS chrome. See
    // CMS-STRUCTURE.md §12. Only `experiment` rows are allowed to swap
    // (the html-swap mode wraps experiment-html.php's existing logic).
    if ($bodyMode === 'html-swap') {
        if (!defined('TEMPLATE_OK')) define('TEMPLATE_OK', true);
        require_once __DIR__ . '/folders.php';
        $ctx = ['article' => $row];
        require dirname(__DIR__) . '/templates/experiment-html.php';
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
 * Resolve a top-level index slug (e.g. 'writing', 'digital-garden') to a
 * row in the `indexes` table and render it. Mirrors render_content() in
 * shape — own slug guard, own template-picker, own master-layout wrap.
 *
 * Series indexes are intentionally NOT looked up here — they're handled
 * by render_series_index() and dispatched directly from the router.
 */
function render_index(string $slug): void
{
    $slug = trim($slug);
    if ($slug === '') {
        render_404();
        return;
    }

    $idx = get_index_by_slug($slug);
    if ($idx === null) {
        // Slug guard: same redirects-table check as render_content() so a
        // renamed index URL (e.g. /thoughts → /writing) still resolves.
        require_once __DIR__ . '/content.php';
        $path  = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
        $redir = lookup_redirect($path);
        if ($redir !== null && $redir !== '' && $redir !== $path) {
            header('Location: ' . $redir, true, 301);
            return;
        }
        render_404();
        return;
    }

    // Build the feed once. For editorial, exclude the hero + featured ids
    // so the same row doesn't appear twice on the page.
    $exclude = [];
    $heroCard      = null;
    $featuredCards = [];
    if ((string)$idx['layout'] === 'editorial') {
        $heroId = (int)($idx['hero_content_id'] ?? 0);
        if ($heroId > 0) {
            $heroCard = get_index_content_card($heroId);
            if ($heroCard !== null) $exclude[] = $heroId;
        }
        $featuredIds = $idx['featured_ids'] ?? null;
        if (is_string($featuredIds)) {
            $decoded = json_decode($featuredIds, true);
            $featuredIds = is_array($decoded) ? $decoded : [];
        }
        if (is_array($featuredIds)) {
            foreach ($featuredIds as $fid) {
                $card = get_index_content_card((int)$fid);
                if ($card !== null) {
                    $featuredCards[] = $card;
                    $exclude[] = (int)$fid;
                }
            }
        }
    }

    $feedRows = list_index_feed([
        'feed_types'       => $idx['feed_types'],
        'feed_sort'        => $idx['feed_sort'],
        'feed_rows_shown'  => $idx['feed_rows_shown'],
    ], $exclude);

    $ctx = [
        'index'          => $idx,
        'hero_card'      => $heroCard,
        'featured_cards' => $featuredCards,
        'feed_rows'      => $feedRows,
        'is_series'      => false,
    ];

    $page_title       = (string)($idx['title']    ?? $slug);
    $page_description = (string)($idx['subtitle'] ?? '');

    if (!defined('TEMPLATE_OK')) define('TEMPLATE_OK', true);

    $tpl = (string)$idx['layout'] === 'editorial'
        ? 'index-editorial.php'
        : 'index-listing.php';

    ob_start();
    require dirname(__DIR__) . '/templates/' . $tpl;
    $body_slot = ob_get_clean();

    header('Content-Type: text/html; charset=utf-8');
    require dirname(__DIR__) . '/templates/master-layout.php';
}

/**
 * Render /series/[slug]/ from the synthetic series_auto_index(). Always
 * editorial layout — hero is the latest published part, feed is the rest
 * of the series in series_order. Series are never rows in the indexes
 * table; the data flows directly from `series` + `content`.
 */
function render_series_index(string $slug): void
{
    $slug = trim($slug);
    if ($slug === '') {
        render_404();
        return;
    }

    $idx = series_auto_index($slug);
    if ($idx === null) {
        require_once __DIR__ . '/content.php';
        $path  = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '';
        $redir = lookup_redirect($path);
        if ($redir !== null && $redir !== '' && $redir !== $path) {
            header('Location: ' . $redir, true, 301);
            return;
        }
        render_404();
        return;
    }

    $ctx = [
        'index'          => $idx,
        'hero_card'      => $idx['hero_card']      ?? null,
        'featured_cards' => $idx['featured_cards'] ?? [],
        'feed_rows'      => $idx['feed_rows']      ?? [],
        'is_series'      => true,
    ];

    $page_title       = (string)($idx['title']    ?? $slug);
    $page_description = (string)($idx['subtitle'] ?? '');

    if (!defined('TEMPLATE_OK')) define('TEMPLATE_OK', true);

    ob_start();
    require dirname(__DIR__) . '/templates/index-editorial.php';
    $body_slot = ob_get_clean();

    header('Content-Type: text/html; charset=utf-8');
    require dirname(__DIR__) . '/templates/master-layout.php';
}

/**
 * Themed 404 — same source the router uses for unrouted paths. Keeping
 * the two paths converged means a wrong /writing/[bad-slug] looks the
 * same as a wrong /[anything-else].
 *
 * Serves the deployed /404.php (speech-bubble themed page). dirname(__DIR__)
 * from lib/ is the webroot — same path resolution site/index.php's
 * not-found handler uses.
 */
function render_404(): void
{
    http_response_code(404);
    $page = dirname(__DIR__) . '/404.php';
    if (is_file($page)) {
        header('Content-Type: text/html; charset=utf-8');
        require $page;
        return;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 — content not found\n";
}
