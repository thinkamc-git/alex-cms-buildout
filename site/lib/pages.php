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

// Layout partial slugs that may be PUBLISHED (file is overridden at runtime).
// Marketing pages are mock-only — files remain canonical.
const PAGES_PUBLISHABLE_SLUGS = ['header', 'footer'];

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
    $error_slugs = ['404'];
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
        'SELECT id, slug, name, body_html, meta_title, meta_description,
                og_image, og_type, twitter_card, is_published,
                created_at, updated_at
           FROM page_mock_versions
          WHERE slug = ?
          ORDER BY updated_at DESC, id DESC'
    );
    $stmt->execute([$slug]);
    return $stmt->fetchAll();
}

function get_page_mock(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, slug, name, body_html, meta_title, meta_description,
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
        'SELECT id, slug, name, body_html, meta_title, meta_description,
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
function create_page_mock(string $slug, string $name, string $body, array $meta = []): int
{
    $stmt = db()->prepare(
        'INSERT INTO page_mock_versions
           (slug, name, body_html, meta_title, meta_description,
            og_image, og_type, twitter_card)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $slug,
        $name,
        $body,
        $meta['meta_title']       ?? null,
        $meta['meta_description'] ?? null,
        $meta['og_image']         ?? null,
        $meta['og_type']          ?? 'website',
        $meta['twitter_card']     ?? 'summary_large_image',
    ]);
    return (int)db()->lastInsertId();
}

function update_page_mock(int $id, string $body, ?string $name = null, array $meta = []): void
{
    $sets   = ['body_html = ?'];
    $params = [$body];
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
    ]);
}

function delete_page_mock(int $id): void
{
    db()->prepare('DELETE FROM page_mock_versions WHERE id = ?')->execute([$id]);
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
    // 1. Known token: render_nav('header'|'footer')
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
    // 2. Strip any remaining PHP open/close blocks (php + short-echo).
    $body = preg_replace('/<\\?(?:php|=).*?\\?>/s', '', (string)$body);
    return (string)($body ?? '');
}
