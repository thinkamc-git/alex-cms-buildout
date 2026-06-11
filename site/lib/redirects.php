<?php
declare(strict_types=1);

/**
 * lib/redirects.php — site-wide redirect table (Phase 13).
 *
 * The `redirects` table maps a request path (old_slug) to a target URL
 * (new_slug) and an HTTP status code (status_code). The router calls
 * resolve_redirect() before falling through to the themed 404, so any
 * row that matches the incoming path short-circuits with a header
 * redirect.
 *
 * Shape conventions (kept simple on purpose — see CMS-STRUCTURE.md §14):
 *   - old_slug: leading slash, no trailing slash, no query string.
 *               e.g. '/portfolioforhire' (matches both /portfolioforhire
 *               and /portfolioforhire/ — the resolver normalizes).
 *   - new_slug: either a path on this site ('/resume/') or an absolute
 *               URL ('https://example.com/foo'). The resolver does NOT
 *               rewrite either form.
 *   - status_code: 301 (permanent, default for CMS-created slug renames)
 *               or 302 (temporary, used for third-party destinations that
 *               might move — Webflow, Notion, Calendly).
 *
 * The CMS view (`cms/views/redirects.php`) is the only place the table
 * is mutated outside migrations.
 */

require_once __DIR__ . '/db.php';

/**
 * The two status codes the CMS dropdown offers. Anything else stored in
 * the column from a hand-edit still renders correctly — this is just
 * the UI-side allowlist.
 */
const REDIRECT_STATUS_CODES = [301, 302];

/**
 * Normalize an incoming request path to the form stored in `old_slug`.
 * Strips trailing slash, drops the query string, collapses leading slashes
 * to one. The empty path becomes '/' (the root).
 */
function normalize_redirect_path(string $path): string
{
    $path = (string)(strtok($path, '?') ?: $path);
    $path = '/' . ltrim($path, '/');
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
    return $path;
}

/**
 * Look up a redirect row by request path. Returns NULL if no match.
 * Tries the exact path and the path-with-trailing-slash so authors
 * don't have to remember which form they stored.
 */
function resolve_redirect(string $path): ?array
{
    $needle = normalize_redirect_path($path);

    $stmt = db()->prepare(
        'SELECT id, old_slug, new_slug, status_code
           FROM redirects
          WHERE old_slug = :a OR old_slug = :b
          LIMIT 1'
    );
    $stmt->execute([
        ':a' => $needle,
        ':b' => $needle === '/' ? '/' : $needle . '/',
    ]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Emit a redirect response from a row returned by resolve_redirect().
 * Calls exit() so the front controller doesn't continue dispatching.
 */
function emit_redirect(array $row): never
{
    $code = (int)($row['status_code'] ?? 301);
    if (!in_array($code, [301, 302, 303, 307, 308], true)) {
        $code = 301;
    }
    http_response_code($code);
    header('Location: ' . (string)$row['new_slug']);
    header('Cache-Control: no-cache');
    exit;
}

/**
 * Full list for the CMS view. Newest-first so freshly-added rows show
 * at the top.
 */
function list_redirects(): array
{
    return db()->query(
        'SELECT id, old_slug, new_slug, status_code, created_at, updated_at
           FROM redirects
       ORDER BY updated_at DESC, id DESC'
    )->fetchAll() ?: [];
}

function get_redirect(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM redirects WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Insert a new redirect or update an existing one. Returns the row id on
 * success, or NULL on validation failure (caller is responsible for
 * surfacing the user-facing error — this just guards the DB).
 *
 * Validation:
 *   - old_slug must start with '/' (after normalization)
 *   - new_slug must be non-empty
 *   - old_slug must not equal new_slug (would loop)
 *   - status_code must be one of REDIRECT_STATUS_CODES
 *   - old_slug must be unique except when updating its own row
 */
function save_redirect(array $data, ?int $id = null): ?int
{
    $old = normalize_redirect_path((string)($data['old_slug'] ?? ''));
    $new = trim((string)($data['new_slug'] ?? ''));
    $code = (int)($data['status_code'] ?? 301);

    if ($old === '' || $old === '/' && $new === '/') return null;
    if ($new === '') return null;
    if ($old === $new) return null;
    if (!in_array($code, REDIRECT_STATUS_CODES, true)) return null;

    $stmt = db()->prepare(
        'SELECT id FROM redirects WHERE old_slug = :old AND id <> :id LIMIT 1'
    );
    $stmt->execute([':old' => $old, ':id' => $id ?? 0]);
    if ($stmt->fetch() !== false) return null;

    if ($id === null) {
        $stmt = db()->prepare(
            'INSERT INTO redirects (old_slug, new_slug, status_code)
                  VALUES (:old, :new, :code)'
        );
        $stmt->execute([':old' => $old, ':new' => $new, ':code' => $code]);
        return (int)db()->lastInsertId();
    }

    $stmt = db()->prepare(
        'UPDATE redirects
            SET old_slug = :old, new_slug = :new, status_code = :code
          WHERE id = :id'
    );
    $stmt->execute([
        ':old'  => $old,
        ':new'  => $new,
        ':code' => $code,
        ':id'   => $id,
    ]);
    return $id;
}

function delete_redirect(int $id): bool
{
    $stmt = db()->prepare('DELETE FROM redirects WHERE id = :id');
    return $stmt->execute([':id' => $id]);
}
