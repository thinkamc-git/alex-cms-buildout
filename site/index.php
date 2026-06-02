<?php
/**
 * index.php — front controller for alexmchong.ca.
 *
 * Reached only for URLs that don't match a real file or directory in the
 * webroot — the .htaccess fallback rewrites everything else to index.php.
 *
 * Phase 3 shipped /hello as a DB-connectivity smoke test.
 * Phase 4 adds /cms/* (login, account, logout, dashboard).
 * Content routes (/writing/[slug] etc.) arrive in Phase 6b.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/router.php';
require_once __DIR__ . '/lib/render.php';
require_once __DIR__ . '/lib/redirects.php';
require_once __DIR__ . '/lib/subscribers.php';

$router = new Router();

// ── Smoke test (Phase 3) ────────────────────────────────────────────
$router->get('/hello', function (): void {
    header('Content-Type: text/html; charset=utf-8');
    try {
        $row = db()->query('SELECT NOW() AS now')->fetch();
        $now = htmlspecialchars((string)($row['now'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo "<!doctype html>\n";
        echo "<meta charset=\"utf-8\">\n";
        echo "<title>hello</title>\n";
        echo "<p>Database connected. Current time: $now</p>\n";
    } catch (Throwable $e) {
        http_response_code(500);
        echo "<!doctype html>\n";
        echo "<meta charset=\"utf-8\">\n";
        echo "<title>hello — DB error</title>\n";
        echo "<p>Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>\n";
    }
});

// ── CMS routes (Phase 4) ─────────────────────────────────────────────
// Each handler `require`s the matching cms/*.php file. The cms/ directory's
// own .htaccess (Require all denied) blocks direct HTTP access — everything
// has to come through this front controller.

$cms = static function (string $file): callable {
    return static function () use ($file): void {
        require __DIR__ . '/cms/' . $file;
    };
};

$router->get ('/cms',          $cms('views/pipeline.php'));
$router->get ('/cms/login',    $cms('login.php'));
$router->post('/cms/login',    $cms('login.php'));
$router->get ('/cms/logout',   $cms('logout.php'));   // returns 405 internally
$router->post('/cms/logout',   $cms('logout.php'));
$router->get ('/cms/account',  $cms('account.php'));
$router->post('/cms/account',  $cms('account.php'));
$router->get ('/cms/ideation', $cms('views/ideation.php'));

// Staging-only self-serve unlock. The handler is host-gated to staging,
// so a POST on prod 404s. See cms/unlock-account.php for the rationale
// (temporary crutch — revisit during Phase 12 Author/Settings work).
$router->post('/cms/unlock-account', $cms('unlock-account.php'));

// ── Articles (Phase 6a) ──────────────────────────────────────────────
// Edit / new / delete / upload — admin-only, gated by Auth::require_login()
// inside each view. URLs use query strings for record ids because the
// router is exact-match only; pretty paths land alongside the public
// /writing/[slug] route in Phase 6b.
$router->get ('/cms/articles',              $cms('views/articles.php'));
$router->get ('/cms/articles/new',          $cms('views/article-new.php'));
$router->post('/cms/articles/new',          $cms('views/article-new.php'));
$router->post('/cms/articles/new-idea',     $cms('views/article-new-idea.php'));
$router->get ('/cms/articles/edit',         $cms('views/article-edit.php'));
$router->post('/cms/articles/edit',         $cms('views/article-edit.php'));
$router->post('/cms/articles/delete',       $cms('views/article-delete.php'));
$router->post('/cms/articles/upload-image', $cms('views/article-upload-image.php'));

// Phase 7.6: AJAX endpoints for drag-drop reordering.
$router->post('/cms/articles/reorder-pipeline', $cms('views/article-reorder-pipeline.php'));
$router->post('/cms/articles/reorder-ideation', $cms('views/article-reorder-ideation.php'));

// Phase 8: Journals — admin CRUD.
$router->get ('/cms/journals',        $cms('views/journals.php'));
$router->get ('/cms/journals/new',    $cms('views/journal-new.php'));
$router->post('/cms/journals/new',    $cms('views/journal-new.php'));
$router->get ('/cms/journals/edit',   $cms('views/journal-edit.php'));
$router->post('/cms/journals/edit',   $cms('views/journal-edit.php'));
$router->post('/cms/journals/delete', $cms('views/journal-delete.php'));

// Phase 9: Live Sessions — admin CRUD.
$router->get ('/cms/live-sessions',        $cms('views/live-sessions.php'));
$router->get ('/cms/live-sessions/new',    $cms('views/live-session-new.php'));
$router->post('/cms/live-sessions/new',    $cms('views/live-session-new.php'));
$router->get ('/cms/live-sessions/edit',   $cms('views/live-session-edit.php'));
$router->post('/cms/live-sessions/edit',   $cms('views/live-session-edit.php'));
$router->post('/cms/live-sessions/delete', $cms('views/live-session-delete.php'));

// Phase 10: Experiments — admin CRUD + Custom HTML folder ops.
$router->get ('/cms/experiments',              $cms('views/experiments.php'));
$router->get ('/cms/experiments/new',          $cms('views/experiment-new.php'));
$router->post('/cms/experiments/new',          $cms('views/experiment-new.php'));
$router->get ('/cms/experiments/edit',         $cms('views/experiment-edit.php'));
$router->post('/cms/experiments/edit',         $cms('views/experiment-edit.php'));
$router->post('/cms/experiments/delete',       $cms('views/experiment-delete.php'));
$router->post('/cms/experiments/upload-image', $cms('views/experiment-upload-image.php'));

// Phase 11: Categories + Series admin. Both views handle their own
// POST dispatch (add/update/delete) via $_POST['action'] — one route
// per view, GET and POST share the handler. Series also has a JSON
// reorder endpoint hit by the drag-drop handler in the series cards.
$router->get ('/cms/categories',     $cms('views/categories.php'));
$router->post('/cms/categories',     $cms('views/categories.php'));
$router->get ('/cms/series',         $cms('views/series.php'));
$router->post('/cms/series',         $cms('views/series.php'));
$router->post('/cms/series/reorder', $cms('views/series-reorder.php'));

// Phase 12: Editorial Index admin (list / new / edit / delete).
$router->get ('/cms/indexes',        $cms('views/indexes.php'));
$router->get ('/cms/indexes/new',    $cms('views/index-new.php'));
$router->post('/cms/indexes/new',    $cms('views/index-new.php'));
$router->get ('/cms/indexes/edit',   $cms('views/index-edit.php'));
$router->post('/cms/indexes/edit',   $cms('views/index-edit.php'));
$router->post('/cms/indexes/delete', $cms('views/index-delete.php'));

// Phase 13: Redirects admin. Single view handles list + add + per-row
// update/delete via $_POST['action'] (same pattern as /cms/categories).
$router->get ('/cms/redirects', $cms('views/redirects.php'));
$router->post('/cms/redirects', $cms('views/redirects.php'));

// Phase 14: Newsletter subscribers admin. List/filter/unsubscribe/delete +
// CSV export (export=csv on GET short-circuits to text/csv). POST handles
// per-row actions; both verbs share the single view handler.
$router->get ('/cms/subscribers', $cms('views/subscribers.php'));
$router->post('/cms/subscribers', $cms('views/subscribers.php'));

// Phase 14.5: Content Template view. Master Template + 6 sub-templates,
// read-only port of the design mockup with Author info editable. GET
// handles all template/tab navigation via ?tpl=...&tab=... query params;
// POST handles the Author save action (CSRF-protected) inside the view.
$router->get ('/cms/post-template', $cms('views/post-template.php'));
$router->post('/cms/post-template', $cms('views/post-template.php'));
// Phase 20.1: live preview endpoint — renders templates/<slug>.php with
// synthetic $ctx so the Preview tab reflects any change to the actual
// template file. Iframed from /cms/post-template's Preview tab.
$router->get ('/cms/post-template/preview', $cms('views/post-template-preview.php'));
// Phase 20.2: per-post live preview — reads a content row by id (any
// status) and renders the matching public template inside master-layout.
// Iframed from article-edit / journal-edit / etc.'s Preview tab.
$router->get ('/cms/post/preview', $cms('views/post-preview.php'));
// Phase 20.2: form-driven preview — overlays POST'd form values on the
// DB row before rendering, so the iframe reflects unsaved editor changes.
// Called by the JS controller when the user toggles to the Preview tab.
$router->post('/cms/post/preview-form', $cms('views/post-preview-form.php'));

// Phase 20: Pages CMS (mock-versioning sandbox for the marketing pages
// and the layout partials header.php / footer.php). The editor uses
// CodeMirror; mock state lives in page_mock_versions. For partials a
// published mock is preferred over the file at runtime — see
// _pages/_layout/_page-shell.php.
$router->get ('/cms/pages',           $cms('views/pages.php'));
$router->get ('/cms/pages/edit',      $cms('views/page-edit.php'));
$router->post('/cms/pages/edit',      $cms('views/page-edit.php'));
// Phase 21.x — form-driven preview for the Pages CMS. Mirrors the
// /cms/post/preview-form endpoint but routes through _page-shell.php.
$router->post('/cms/pages/preview-form', $cms('views/pages-preview-form.php'));

// Phase 20: Navigation editor. Replaces the hardcoded <a> lists in the
// static header.html / footer.html with DB-managed nav_items. AJAX
// reorder is a tiny separate endpoint.
$router->get ('/cms/navigation',          $cms('views/navigation.php'));
$router->post('/cms/navigation',          $cms('views/navigation.php'));
$router->post('/cms/navigation/reorder',  $cms('views/navigation-reorder.php'));

// ── Public subscribe (Phase 14) ─────────────────────────────────────
// POST /subscribe handles the newsletter-form submission: honeypot,
// rate-limit (1/min, 10/day per IP), email validation, upsert.
// Outcome → redirect:
//   ok | honeypot → /subscribe/confirmed/   (honeypot is silent success)
//   rate          → /subscribe/?error=rate
//   invalid       → /subscribe/?error=invalid
//
// /subscribe/ and /subscribe/confirmed/ both render the existing static
// _pages bodies via _page-shell.php — the URL is the canonical home for
// the form going forward; /newsletter/ remains as a redirect (seeded in
// the redirects table if Alex wants the old URL to stick around).
$router->post('/subscribe', static function (): void {
    $result = subscribe_from_post('newsletter-page');
    // Silent-success statuses look identical to a bot — they can't tell
    // they were caught (honeypot filled, sub-2s submit). 'ok' is the
    // real path. Everything else surfaces an error back to the user.
    if (in_array($result, ['ok', 'honeypot', 'fast'], true)) {
        header('Location: /subscribe/confirmed/', true, 302);
        exit;
    }
    header('Location: /subscribe/?error=' . rawurlencode($result), true, 302);
    exit;
});

// bin/deploy.sh flattens _pages/*.php to webroot, so newsletter.php and
// newsletter-confirmed.php sit next to this file post-deploy.
$router->get('/subscribe', static function (): void {
    require __DIR__ . '/newsletter.php';
});
$router->get('/subscribe/confirmed', static function (): void {
    require __DIR__ . '/newsletter-confirmed.php';
});

// ── Public articles (Phase 6b) ───────────────────────────────────────
// First dynamic-segment route. The :slug param is single-segment so
// /writing/foo/bar won't match — that's intentional (no nested article
// paths in v1). Other content types extend the public route table from
// Phase 8 (/journal/:slug), Phase 9 (/live-sessions/:slug), Phase 10
// (/experiments/:slug) — each with its own render_<type>($slug) call.
$router->get('/writing/:slug', static function (array $p): void {
    render_content((string)($p['slug'] ?? ''));
});

// Phase 8: Journals public route. render_content() is type-agnostic;
// the template enum on the row routes to templates/journal-entry.php.
$router->get('/journal/:slug', static function (array $p): void {
    render_content((string)($p['slug'] ?? ''));
});

// Phase 9: Live Sessions public route. Same dispatch — the row's
// template='live-session' picks templates/live-session.php.
$router->get('/live-sessions/:slug', static function (array $p): void {
    render_content((string)($p['slug'] ?? ''));
});

// Phase 10: Experiments public route. Same type-agnostic dispatch — the
// row's template column picks experiment.php (article-format) vs
// experiment-html.php (raw passthrough).
$router->get('/experiments/:slug', static function (array $p): void {
    render_content((string)($p['slug'] ?? ''));
});

// Phase 12: Public index routes. The four built-in type indexes resolve
// from rows seeded by migration 0007 (slug = 'writing' | 'journal' |
// 'live-sessions' | 'experiments'); render_index() looks up the row and
// dispatches to the matching template. /series/[slug]/ is auto-generated
// from the `series` row + its parts (no row in `indexes`).
//
// Single-segment paths (/writing) and two-segment paths (/writing/:slug)
// are matched by segment count, so they coexist cleanly — declaration
// order doesn't matter.
//
// ⚠ Staging-only during the Phase 12–15 prod-freeze (see BUILD-PLAN §3).
// Prod requests to /writing/, /journal/, /live-sessions/, /experiments/,
// /series/[slug]/ should fall through to 404 until Phase 29 (Public
// Cutover) deletes this gate. The marketing-page nav on prod doesn't
// link here, so visitors never see a 404 in normal flow.
if (defined('APP_ENV') && APP_ENV === 'staging') {
    $router->get('/writing',       static function (): void { render_index('writing'); });
    $router->get('/journal',       static function (): void { render_index('journal'); });
    $router->get('/live-sessions', static function (): void { render_index('live-sessions'); });
    $router->get('/experiments',   static function (): void { render_index('experiments'); });
    $router->get('/series/:slug',  static function (array $p): void {
        render_series_index((string)($p['slug'] ?? ''));
    });
}

// Route-miss handler. First check for a DB-backed redirect (replaces
// the old .htaccess legacy block). If nothing matches, serve the
// shared static /404.php — the speech-bubble "wandered off the path"
// page is design-canonical for both prod and staging.
//
// Phase 20 preview integration: on staging, a logged-in CMS user
// hitting /404/?_preview=<id> with a mock for slug='404' sees the
// mock body instead of the file content. (404.php is a standalone
// page — no _page-shell.php — so the preview hook can't live there.)
$router->set_not_found(static function (string $method, string $path): void {
    $hit = resolve_redirect($path);
    if ($hit !== null) {
        emit_redirect($hit);
    }

    // Phase 20.3: custom Editorial / Listing indexes resolve here. The four
    // built-in indexes (writing / journal / live-sessions / experiments) get
    // their own static routes above; any other single-segment slug that
    // matches a row in the `indexes` table renders via render_index().
    // Staging-only — see prod-freeze note below the built-in block.
    if ($method === 'GET' && defined('APP_ENV') && APP_ENV === 'staging') {
        $cleanPath = trim((string)strtok($path, '?'), '/');
        if ($cleanPath !== '' && strpos($cleanPath, '/') === false && preg_match('/^[a-z0-9][a-z0-9\-]*$/', $cleanPath)) {
            require_once __DIR__ . '/lib/indexes.php';
            require_once __DIR__ . '/lib/render.php';
            $idx = get_index_by_slug($cleanPath);
            if ($idx !== null) {
                render_index($cleanPath);
                return;
            }
        }
    }

    http_response_code(404);

    if (isset($_GET['_preview']) && defined('APP_ENV') && APP_ENV === 'staging') {
        require_once __DIR__ . '/lib/auth.php';
        require_once __DIR__ . '/lib/pages.php';
        if (Auth::current_user() !== null) {
            $mock = get_page_mock((int)$_GET['_preview']);
            if ($mock !== null && (string)$mock['slug'] === '404') {
                header('Content-Type: text/html; charset=utf-8');
                echo render_partial_body((string)$mock['body_html']);
                return;
            }
        }
    }

    $page = __DIR__ . '/404.php';
    if (is_file($page)) {
        header('Content-Type: text/html; charset=utf-8');
        require $page;
        return;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo "404 — no route for $method $path\n";
});

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI']    ?? '/'
);
