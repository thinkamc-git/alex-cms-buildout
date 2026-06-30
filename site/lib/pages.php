<?php
/**
 * lib/pages.php — Pages CMS data layer (Phase 20).
 *
 * Two concerns:
 *
 *   1. Marketing pages — files under _pages/*.php are canonical. The CMS
 *      lets the author save named MOCK versions in page_mock_versions
 *      that are previewable via ?_preview=<id>. Files are never written
 *      back from the CMS.
 *
 *   2. Layout partials — header.php / footer.php under _pages/_layout/.
 *      Same mock-versioning UI, plus a "Publish this version" action.
 *      When a partial mock is published, _page-shell.php prefers it over
 *      the file at runtime (token-substituted via render_partial_body).
 *
 * The publish capability is gated to partial slugs (header / footer).
 * Marketing-page mocks stay preview-only.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Relative time rendered as two spans: full form ("31 min ago") shown by default,
 * short form ("31m") shown via container query when the cell is tight.
 * CSS in style-cms.css handles the toggle.
 */
function rel_time_html(int $epoch): string
{
    if ($epoch <= 0) return '<span class="muted">—</span>';
    $diff = time() - $epoch;
    if ($diff < 60) {
        return '<span class="rel-time"><span class="rel-full">just now</span><span class="rel-short" aria-hidden="true">now</span></span>';
    }
    if ($diff < 3600) {
        $n = (int)floor($diff / 60);
        return '<span class="rel-time"><span class="rel-full">' . $n . ' min ago</span><span class="rel-short" aria-hidden="true">' . $n . 'm</span></span>';
    }
    if ($diff < 86400) {
        $n = (int)floor($diff / 3600);
        return '<span class="rel-time"><span class="rel-full">' . $n . ' hr ago</span><span class="rel-short" aria-hidden="true">' . $n . 'h</span></span>';
    }
    if ($diff < 86400 * 30) {
        $n = (int)floor($diff / 86400);
        return '<span class="rel-time"><span class="rel-full">' . $n . ' days ago</span><span class="rel-short" aria-hidden="true">' . $n . 'd</span></span>';
    }
    $label = date('M j', $epoch);
    return '<span class="muted">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

// Layout partial slugs that may be PUBLISHED (file is overridden at runtime).
// Marketing pages are mock-only — files remain canonical.
const PAGES_PUBLISHABLE_SLUGS = ['header', 'footer'];

// ── Page types + canonical public URL (single source of truth) ─────────
// Page types are FIXED (not user-editable). Default is 'standard' (→ /slug/).
// Home, error, and partial slugs are the only exceptions. See docs/PAGES-SYSTEM.md.
const PAGE_TYPE_OVERRIDES = [
    'landing' => 'home',     // served at the site root, not /landing/
    'error'   => 'error',    // shared 404/403/500 template, no standalone URL
    'header'  => 'partial',  // embedded into every page
    'footer'  => 'partial',
];

/** The fixed type for a slug: 'standard' | 'home' | 'error' | 'partial'. */
function page_type(string $slug): string
{
    return PAGE_TYPE_OVERRIDES[$slug] ?? 'standard';
}

/**
 * Canonical public URL for a page slug — the ONE place this is decided.
 *   home     → '/'
 *   standard → '/<slug>/'
 * Partials and error pages have no standalone public URL of their own; callers
 * that need a "live" link for those handle them specially (e.g. partials render
 * inside /about/?_partial_focus=). Replaces the scattered '/'.$slug.'/' literals
 * and the inline ['landing' => '/'] map that disagreed across views.
 */
function page_public_url(string $slug): string
{
    return page_type($slug) === 'home' ? '/' : '/' . rawurlencode($slug) . '/';
}

/**
 * Human-facing display name for a page — what the CMS shows instead of the raw
 * filename. "Homepage" for the home page; otherwise the bare slug (no .html).
 */
function page_display_name(string $slug): string
{
    switch (page_type($slug)) {
        case 'home':    return 'Homepage';
        case 'error':   return '404 / 403 / 500';      // one error template, several codes
        case 'partial': return ucfirst($slug);         // Header / Footer — embedded, no URL
        default:        return page_public_url($slug);  // /about/
    }
}

/**
 * Epoch of the last *actual* publish for a slug — the created_at of the most
 * recent snapshot (snapshots are written at publish time, when the file is
 * overwritten). NULL if never published through the CMS. Deliberately NOT the
 * file mtime, which a deploy/rsync touches on every CMS update.
 */
function page_last_published_at(string $slug): ?int
{
    $stmt = db()->prepare(
        "SELECT created_at FROM page_mock_versions
          WHERE slug = ? AND kind = 'snapshot'
          ORDER BY created_at DESC, id DESC LIMIT 1"
    );
    $stmt->execute([$slug]);
    $v = $stmt->fetchColumn();
    return $v ? strtotime((string)$v) : null;
}

// Filesystem roots — local source ships marketing pages under site/_pages/
// and partials under site/_pages/_layout/. bin/deploy.sh flattens to:
//   webroot/*.php             ← marketing pages (alongside index.php, etc.)
//   webroot/_layout/*.php     ← partials
// Both layouts must work because the CMS runs on staging (deployed) and
// can also be exercised locally (source).
function _pages_root(): string {
    $src = dirname(__DIR__) . '/_pages';
    return is_dir($src) ? $src : dirname(__DIR__);
}
function _layout_root(): string {
    $src = dirname(__DIR__) . '/_pages/_layout';
    return is_dir($src) ? $src : (dirname(__DIR__) . '/_layout');
}

// ── File scan ─────────────────────────────────────────────────────────

/**
 * Scan _pages/ for the editable files. Returns one row per file with:
 *   slug         string  — filename without extension
 *   kind         string  — 'page' | 'error' | 'partial'
 *   filename     string  — basename with extension
 *   path         string  — absolute path to the file
 *   exists       bool    — true if path is a real file
 *   modified_at  int     — filemtime() epoch, or 0
 *
 * Ordering: marketing pages, then error pages, then layout partials.
 * Each section sorted alphabetically by slug.
 */
function list_pages_files(): array
{
    // Slugs that are error pages rather than marketing pages.
    $error_slugs = ['error'];
    // Filenames at the pages root that AREN'T editable surfaces.
    $skip = ['_page-shell.php', 'index.php', 'setup.php'];

    $pages = [];
    $errors = [];

    // The top-level *.php files are page assemblers (4-line shells that
    // set $title + $body and require _page-shell.php). The actual
    // editable body content for marketing pages lives at
    // _bodies/<slug>.html — that's what we surface to the editor.
    foreach (glob(_pages_root() . '/*.php') ?: [] as $assembler_path) {
        $assembler_filename = basename($assembler_path);
        if (in_array($assembler_filename, $skip, true)) continue;
        $slug = substr($assembler_filename, 0, -4);
        $is_error = in_array($slug, $error_slugs, true);

        if ($is_error) {
            // Error pages are standalone PHP — show the whole file.
            $row_path     = $assembler_path;
            $row_filename = $assembler_filename;
        } else {
            // Marketing pages — editable surface is the body HTML.
            $row_path     = _pages_root() . '/_bodies/' . $slug . '.html';
            $row_filename = $slug . '.html';
        }
        $row = [
            'slug'        => $slug,
            'kind'        => $is_error ? 'error' : 'page',
            'filename'    => $row_filename,
            'path'        => $row_path,
            'exists'      => is_file($row_path),
            'modified_at' => is_file($row_path) ? (filemtime($row_path) ?: 0) : 0,
        ];
        if ($is_error) $errors[] = $row;
        else           $pages[]  = $row;
    }
    usort($pages,  fn($a, $b) => strcmp($a['slug'], $b['slug']));
    usort($errors, fn($a, $b) => strcmp($a['slug'], $b['slug']));

    // Layout partials live under _layout/.
    $partials = [];
    foreach (['header.php', 'footer.php'] as $filename) {
        $path = _layout_root() . '/' . $filename;
        $slug = substr($filename, 0, -4);
        $partials[] = [
            'slug'        => $slug,
            'kind'        => 'partial',
            'filename'    => $filename,
            'path'        => $path,
            'exists'      => is_file($path),
            'modified_at' => is_file($path) ? (filemtime($path) ?: 0) : 0,
        ];
    }

    return array_merge($pages, $errors, $partials);
}

/**
 * Read the canonical file content for a slug. Returns NULL if no file
 * exists (e.g. partial not yet created in this phase).
 */
function read_page_file(string $slug): ?string
{
    foreach (list_pages_files() as $row) {
        if ($row['slug'] === $slug && $row['exists']) {
            $contents = @file_get_contents($row['path']);
            return $contents !== false ? $contents : null;
        }
    }
    return null;
}

/**
 * Returns the file row for the given slug, or NULL if no such editable
 * file exists.
 */
function find_page_file(string $slug): ?array
{
    foreach (list_pages_files() as $row) {
        if ($row['slug'] === $slug) return $row;
    }
    return null;
}

// ── Mock version CRUD ─────────────────────────────────────────────────

/**
 * List mocks for a slug, newest first.
 */
function list_page_mocks(string $slug): array
{
    $stmt = db()->prepare(
        'SELECT id, slug, kind, name, body_html, style_css, meta_title, meta_description,
                og_image, og_type, twitter_card, is_published,
                created_at, updated_at
           FROM page_mock_versions
          WHERE slug = ? AND kind = ?
          ORDER BY updated_at DESC, id DESC'
    );
    $stmt->execute([$slug, 'draft']);
    return $stmt->fetchAll();
}

function list_page_snapshots(string $slug): array
{
    $stmt = db()->prepare(
        'SELECT id, slug, kind, name, LENGTH(body_html) AS body_len,
                created_at
           FROM page_mock_versions
          WHERE slug = ? AND kind = ?
          ORDER BY id DESC'
    );
    $stmt->execute([$slug, 'snapshot']);
    return $stmt->fetchAll();
}

function restore_snapshot_to_draft(int $snapshot_id, int $current_draft_id): bool
{
    $snap = get_page_mock($snapshot_id);
    if ($snap === null || (string)$snap['kind'] !== 'snapshot') return false;
    // Restore body AND style together so the draft reflects that version exactly,
    // clearing any style override that was sitting on the draft.
    db()->prepare('UPDATE page_mock_versions SET body_html = ?, style_css = ?, updated_at = NOW() WHERE id = ? AND kind = ?')
        ->execute([(string)$snap['body_html'], $snap['style_css'] ?? null, $current_draft_id, 'draft']);
    return true;
}

function get_page_mock(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, slug, kind, name, body_html, style_css, meta_title, meta_description,
                og_image, og_type, twitter_card, is_published,
                created_at, updated_at
           FROM page_mock_versions
          WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_published_mock(string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT id, slug, name, body_html, style_css, meta_title, meta_description,
                og_image, og_type, twitter_card, is_published,
                created_at, updated_at
           FROM page_mock_versions
          WHERE slug = ? AND is_published = 1
          ORDER BY updated_at DESC
          LIMIT 1'
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Create a new mock. Returns the new id.
 * $meta keys: meta_title, meta_description, og_image, og_type, twitter_card
 */
function create_page_mock(string $slug, string $name, string $body, array $meta = [], ?string $style_css = null): int
{
    $stmt = db()->prepare(
        'INSERT INTO page_mock_versions
           (slug, name, body_html, style_css, meta_title, meta_description,
            og_image, og_type, twitter_card)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $slug,
        $name,
        $body,
        $style_css,
        $meta['meta_title']       ?? null,
        $meta['meta_description'] ?? null,
        $meta['og_image']         ?? null,
        $meta['og_type']          ?? 'website',
        $meta['twitter_card']     ?? 'summary_large_image',
    ]);
    return (int)db()->lastInsertId();
}

function update_page_mock(int $id, string $body, ?string $name = null, array $meta = [], ?string $style_css = null): void
{
    $sets   = ['body_html = ?'];
    $params = [$body];
    if ($style_css !== null) {       // null = leave unchanged; '' = clear the override
        $sets[] = 'style_css = ?';
        $params[] = $style_css;
    }
    if ($name !== null) {
        $sets[] = 'name = ?';
        $params[] = $name;
    }
    foreach (['meta_title', 'meta_description', 'og_image', 'og_type', 'twitter_card'] as $k) {
        if (array_key_exists($k, $meta)) {
            $sets[] = "$k = ?";
            $params[] = $meta[$k];
        }
    }
    $params[] = $id;
    $sql = 'UPDATE page_mock_versions SET ' . implode(', ', $sets) . ' WHERE id = ?';
    db()->prepare($sql)->execute($params);
}

function rename_page_mock(int $id, string $name): void
{
    db()->prepare('UPDATE page_mock_versions SET name = ? WHERE id = ?')
        ->execute([$name, $id]);
}

function duplicate_page_mock(int $id, string $new_name): ?int
{
    $src = get_page_mock($id);
    if ($src === null) return null;
    return create_page_mock($src['slug'], $new_name, (string)$src['body_html'], [
        'meta_title'       => $src['meta_title'],
        'meta_description' => $src['meta_description'],
        'og_image'         => $src['og_image'],
        'og_type'          => $src['og_type'],
        'twitter_card'     => $src['twitter_card'],
    ], $src['style_css'] ?? null);
}

function delete_page_mock(int $id): void
{
    db()->prepare('DELETE FROM page_mock_versions WHERE id = ?')->execute([$id]);
}

// ── Archives (convention-based) ───────────────────────────────────────
//
// An "archive" is any mock whose name starts with "Archive " (space). The
// convention lets us snapshot the live page at a point in time without
// adding a schema column. Archives are preview-only — they live in
// page_mock_versions like any other mock, but the Pages list surfaces
// them under a dedicated "Archives" filter and the preview path serves
// the body raw (the body is expected to be a self-contained HTML doc).
const PAGES_ARCHIVE_PREFIX = 'Archive ';

function is_archive_mock_name(string $name): bool
{
    return str_starts_with(ltrim($name), PAGES_ARCHIVE_PREFIX);
}

/**
 * All archive mocks across every slug, newest-named first.
 */
function list_archive_mocks(): array
{
    $stmt = db()->prepare(
        'SELECT id, slug, name, body_html, meta_title, meta_description,
                og_image, og_type, twitter_card, is_published,
                created_at, updated_at
           FROM page_mock_versions
          WHERE name LIKE ?
          ORDER BY name DESC, id DESC'
    );
    $stmt->execute([PAGES_ARCHIVE_PREFIX . '%']);
    return $stmt->fetchAll();
}

/**
 * Mark a mock as published. Only allowed for slugs in
 * PAGES_PUBLISHABLE_SLUGS (header / footer). Atomically un-publishes any
 * other mock for the same slug so only one wins.
 */
function publish_page_mock(int $id): bool
{
    $row = get_page_mock($id);
    if ($row === null) return false;
    if (!in_array($row['slug'], PAGES_PUBLISHABLE_SLUGS, true)) return false;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE page_mock_versions SET is_published = 0 WHERE slug = ?')
            ->execute([$row['slug']]);
        $pdo->prepare('UPDATE page_mock_versions SET is_published = 1 WHERE id = ?')
            ->execute([$id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
    return true;
}

function unpublish_page_mock(int $id): void
{
    db()->prepare('UPDATE page_mock_versions SET is_published = 0 WHERE id = ?')
        ->execute([$id]);
}

/**
 * "Revert to file" — clears the published flag for ALL mocks of this slug.
 * After this returns, _page-shell.php falls back to the on-disk file.
 */
function unpublish_all_for_slug(string $slug): void
{
    db()->prepare('UPDATE page_mock_versions SET is_published = 0 WHERE slug = ?')
        ->execute([$slug]);
}

/**
 * Write a mock's body_html to its canonical _bodies/<slug>.html file.
 * Only works for marketing pages (kind = 'page') — not partials or error pages.
 * Returns true on success, false if the mock or target path can't be resolved.
 */
function publish_mock_to_file(int $id): bool
{
    $row = get_page_mock($id);
    if ($row === null) return false;
    $slug = (string)$row['slug'];

    $file_row = find_page_file($slug);
    if ($file_row === null || $file_row['kind'] !== 'page') return false;

    $target = _pages_root() . '/_bodies/' . $slug . '.html';

    // Snapshot the OUTGOING published version before overwriting — with body AND
    // style stored SEPARATELY, so it can be restored faithfully into the editor's
    // HTML + Style panes. Prefer the currently-published mock (already separated);
    // fall back to the on-disk file (body-only) for pages never CMS-published.
    $prev = get_published_mock($slug);
    if ($prev !== null) {
        $snap_body  = (string)$prev['body_html'];
        $snap_style = $prev['style_css'] ?? null;
    } else {
        $snap_body  = read_page_file($slug);
        $snap_style = null;
    }
    if ($snap_body !== null && trim($snap_body) !== '') {
        db()->prepare(
            'INSERT INTO page_mock_versions (slug, kind, name, body_html, style_css, is_published, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW())'
        )->execute([$slug, 'snapshot', 'Published ' . date('Y-m-d'), $snap_body, $snap_style]);
    }

    // Fold the page-scoped style override into the written file (P2). body_html
    // and style_css are stored separately (source of truth); publish generates
    // one self-contained file. Empty style → body written as-is (no <style>).
    $style  = (string)($row['style_css'] ?? '');
    $folded = trim($style) !== ''
        ? "<style>\n" . $style . "\n</style>\n" . (string)$row['body_html']
        : (string)$row['body_html'];
    if (file_put_contents($target, $folded) === false) return false;

    // Mark this mock as the published version.
    $pdo = db();
    $pdo->prepare('UPDATE page_mock_versions SET is_published = 0 WHERE slug = ?')->execute([$slug]);
    $pdo->prepare('UPDATE page_mock_versions SET is_published = 1 WHERE id = ?')->execute([$id]);
    return true;
}

// ── Per-page metadata (slug-level, independent of mocks) ──────────────

/**
 * Fetch metadata for a slug. Returns NULL if no row exists yet (caller
 * should fall back to defaults).
 */
function get_page_metadata(string $slug): ?array
{
    $stmt = db()->prepare(
        'SELECT slug, meta_title, meta_description, og_image, og_type,
                twitter_card, created_at, updated_at
           FROM page_metadata
          WHERE slug = ?'
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Upsert metadata for a slug. $data may contain any of:
 *   meta_title, meta_description, og_image, og_type, twitter_card
 */
function upsert_page_metadata(string $slug, array $data): void
{
    $stmt = db()->prepare(
        'INSERT INTO page_metadata
           (slug, meta_title, meta_description, og_image, og_type, twitter_card)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           meta_title       = VALUES(meta_title),
           meta_description = VALUES(meta_description),
           og_image         = VALUES(og_image),
           og_type          = VALUES(og_type),
           twitter_card     = VALUES(twitter_card)'
    );
    $stmt->execute([
        $slug,
        $data['meta_title']       ?? null,
        $data['meta_description'] ?? null,
        $data['og_image']         ?? null,
        $data['og_type']          ?? 'website',
        $data['twitter_card']     ?? 'summary_large_image',
    ]);
}

// ── Page registry (active / archived status) ──────────────────────────

/**
 * Returns the registry row for a slug, or null if not in the table (= active).
 */
function get_page_registry(string $slug): ?array
{
    $stmt = db()->prepare('SELECT slug, status, archived_at FROM page_registry WHERE slug = ?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function is_page_archived(string $slug): bool
{
    $row = get_page_registry($slug);
    return $row !== null && $row['status'] === 'archived';
}

/**
 * Archive a page: upsert registry row as archived. Returns true on success.
 */
function archive_page(string $slug): bool
{
    $stmt = db()->prepare(
        'INSERT INTO page_registry (slug, status, archived_at)
         VALUES (?, "archived", NOW())
         ON DUPLICATE KEY UPDATE status = "archived", archived_at = NOW()'
    );
    return $stmt->execute([$slug]);
}

/**
 * Restore a page: set registry status back to active.
 */
function restore_page(string $slug): bool
{
    $stmt = db()->prepare(
        'INSERT INTO page_registry (slug, status, archived_at)
         VALUES (?, "active", NULL)
         ON DUPLICATE KEY UPDATE status = "active", archived_at = NULL'
    );
    return $stmt->execute([$slug]);
}

// ── Pending drafts (Claude Code → CMS import) ─────────────────────────
//
// Claude Code writes changes to _bodies/_pending/<slug>-<YYYYMMDD>.html
// instead of editing the live file directly. The CMS detects these files
// and lets the author import them as named mocks for review before publish.
// After import the file is moved to _bodies/_pending/_imported/ as an audit
// trail. Multiple pending files for the same slug are shown newest-date first.

function _pending_root(): string
{
    return _pages_root() . '/_bodies/_pending';
}

function _imported_root(): string
{
    return _pending_root() . '/_imported';
}

/**
 * Scan _pending/ for files matching <slug>-<YYYYMMDD>.html.
 * Returns array of rows, each: slug, filename, date (YYYYMMDD string), path.
 * Sorted newest date first within each slug.
 */
function list_pending_drafts(): array
{
    $dir = _pending_root();
    $rows = [];
    foreach (glob($dir . '/*.html') ?: [] as $path) {
        $filename = basename($path);
        if (!preg_match('/^([a-z0-9-]+)-(\d{8})\.html$/', $filename, $m)) continue;
        $rows[] = [
            'slug'     => $m[1],
            'filename' => $filename,
            'date'     => $m[2],
            'path'     => $path,
        ];
    }
    usort($rows, fn($a, $b) => strcmp($b['date'], $a['date']));
    return $rows;
}

/**
 * Pending drafts for a specific slug, newest first.
 */
function list_pending_drafts_for_slug(string $slug): array
{
    return array_values(array_filter(list_pending_drafts(), fn($r) => $r['slug'] === $slug));
}

/**
 * Import a pending draft: create a mock from its content, then move the
 * file to _imported/ so it no longer shows as pending.
 * Returns the new mock id on success, null on failure.
 */
function import_pending_draft(string $filename): ?int
{
    $path = _pending_root() . '/' . $filename;
    if (!is_file($path)) return null;
    if (!preg_match('/^([a-z0-9-]+)-(\d{8})\.html$/', $filename, $m)) return null;

    $slug = $m[1];
    $date = $m[2];
    $body = file_get_contents($path);
    if ($body === false) return null;

    $year  = substr($date, 0, 4);
    $month = substr($date, 4, 2);
    $day   = substr($date, 6, 2);
    $name  = 'Claude — ' . $year . '-' . $month . '-' . $day;

    $id = create_page_mock($slug, $name, $body);

    $dest_dir = _imported_root();
    if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
    rename($path, $dest_dir . '/' . $filename);

    return $id;
}

// ── Token substitution at preview / runtime ───────────────────────────

/**
 * Render a mock body string for preview / publish output.
 *
 * Two passes:
 *   1. Substitute known tokens — currently just render_nav('header'|'footer').
 *      These resolve to the live <a> list rendered by lib/nav.php.
 *   2. Strip every remaining PHP open/close block. Mock bodies are seeded
 *      from the on-disk PHP files (so users see real source in CodeMirror),
 *      but the runtime NEVER eval()s admin content. Any PHP that isn't a
 *      known token is silently removed at render time so the browser
 *      doesn't show it as junk text.
 *
 * The function is shared by:
 *   - _pages/_layout/_page-shell.php (header/footer cascade + body override)
 *   - site/index.php's not-found handler (404 preview)
 *
 * Pure HTML bodies (the marketing-page case) pass through unchanged.
 */
function render_partial_body(string $body): string
{
    // 1. Short echo: date('Y') → current year.
    $body = preg_replace('/<\?=\s*date\s*\(\s*[\'"]Y[\'"]\s*\)\s*\?>/', (string)date('Y'), $body);

    // 2. PHP blocks that assign a variable via get_setting() — resolve the
    //    value and drop the assignment block, then substitute the echo.
    $_rpb_vars = [];
    $body = preg_replace_callback(
        '/<\?php\b[^?]*\$(\w+)\s*=(?:[^?]|\?(?!>))*get_setting\(\s*\'([^\']+)\'\s*,\s*\'([^\']*)\'\s*\)(?:[^?]|\?(?!>))*\?>/',
        static function (array $m) use (&$_rpb_vars): string {
            $_rpb_vars[$m[1]] = function_exists('get_setting')
                ? (string)get_setting($m[2], $m[3])
                : $m[3];
            return '';
        },
        $body
    );
    $body = preg_replace_callback(
        '/<\?=\s*htmlspecialchars\(\s*\$(\w+)\s*,\s*ENT_QUOTES\s*,\s*\'UTF-8\'\s*\)\s*\?>/',
        static function (array $m) use ($_rpb_vars): string {
            return isset($_rpb_vars[$m[1]])
                ? htmlspecialchars($_rpb_vars[$m[1]], ENT_QUOTES, 'UTF-8')
                : '';
        },
        $body
    );

    // 3. Known token: render_nav('header'|'footer')
    $body = preg_replace_callback(
        "/<\\?php\\s+render_nav\\s*\\(\\s*['\"](header|footer)['\"]\\s*\\)\\s*;?\\s*\\?>/",
        static function (array $m): string {
            $zone = $m[1];
            if (!function_exists('render_nav')) {
                require_once __DIR__ . '/nav.php';
            }
            ob_start();
            render_nav($zone);
            return (string)ob_get_clean();
        },
        $body
    );

    // 4. Strip any remaining PHP open/close blocks (php + short-echo).
    $body = preg_replace('/<\\?(?:php|=).*?\\?>/s', '', (string)$body);
    return (string)($body ?? '');
}
