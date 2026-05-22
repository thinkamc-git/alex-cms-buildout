<?php
declare(strict_types=1);

/**
 * lib/folders.php — Custom HTML Folder System (Phase 10).
 *
 * Owns the per-experiment content folder lifecycle for the `experiment-html`
 * template. Storage layout (server-side, never committed):
 *
 *   <DOCUMENT_ROOT>/content/<type>/<slug>/
 *       main.html
 *       v2-interactive.html
 *       assets/...
 *
 * The CMS only knows the *filename* (stored in `content.source_file`) — the
 * full path is derived at render time. Files arrive via SSH/CloudMounter, not
 * a CMS uploader. PHP only creates the folder; humans drop files into it.
 *
 * Per CMS-STRUCTURE.md §12:
 *   - Set Up Folder creates /content/<type>/<slug>/ server-side.
 *   - Refresh re-scans for FTP/SFTP drops.
 *   - Delete Folder requires confirmation; only allowed when empty.
 *
 * All operations are confined to /content/ under DOCUMENT_ROOT — any caller
 * that tries to escape via .. or absolute paths gets rejected before touching
 * the filesystem.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Root folder for all custom-HTML content. Configurable via CONTENT_ROOT
 * constant (set in config.{env}.php) so staging/prod can point at different
 * paths if needed. Defaults to <DOCUMENT_ROOT>/content/.
 */
function content_root(): string
{
    if (defined('CONTENT_ROOT') && is_string(CONTENT_ROOT) && CONTENT_ROOT !== '') {
        return rtrim(CONTENT_ROOT, '/');
    }
    $doc = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($doc === '') {
        // CLI / odd SAPI fallback — resolve relative to this file.
        $doc = dirname(__DIR__);
    }
    return rtrim($doc, '/') . '/content';
}

/**
 * Validate the (type, slug) pair before any filesystem op. Rejects anything
 * that could escape the content root (path traversal, absolute paths, NUL
 * bytes, unexpected characters).
 *
 * Returns ['ok' => bool, 'error' => string].
 */
function _folder_validate(string $type, string $slug): array
{
    if (!in_array($type, ['experiment'], true)) {
        return ['ok' => false, 'error' => 'Custom folders are only supported for experiments.'];
    }
    if ($slug === '') {
        return ['ok' => false, 'error' => 'Slug is required before setting up a folder.'];
    }
    if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,199}$/', $slug)) {
        return ['ok' => false, 'error' => 'Slug must be lowercase letters, numbers, and hyphens only.'];
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * Resolve the absolute folder path for a (type, slug). Returns '' if the
 * pair is invalid — callers should call _folder_validate() first to surface
 * the specific error.
 */
function folder_path(string $type, string $slug): string
{
    $v = _folder_validate($type, $slug);
    if (!$v['ok']) return '';
    return content_root() . '/' . $type . '/' . $slug;
}

/**
 * Whether the folder exists on disk for this (type, slug).
 */
function folder_exists(string $type, string $slug): bool
{
    $p = folder_path($type, $slug);
    return $p !== '' && is_dir($p);
}

/**
 * Create /content/<type>/<slug>/ if it doesn't exist yet. Idempotent:
 * returns ['ok' => true] both when the folder is created and when it was
 * already there. Creates the parent /content/<type>/ on demand.
 *
 * The folder is mkdir'd 0755 so the web user can read; PHP runs as the
 * same user on DreamHost shared hosting, so writes happen as the owner.
 */
function folder_setup(string $type, string $slug): array
{
    $v = _folder_validate($type, $slug);
    if (!$v['ok']) return $v;
    $path = folder_path($type, $slug);
    if ($path === '') return ['ok' => false, 'error' => 'Could not resolve folder path.'];
    if (is_dir($path)) return ['ok' => true, 'error' => '', 'path' => $path, 'created' => false];
    // mkdir -p (recursive) so /content/ and /content/experiment/ are created
    // on first call. @-suppress and check the result so a permission failure
    // surfaces a friendly message rather than a warning blob.
    if (!@mkdir($path, 0755, true) && !is_dir($path)) {
        return ['ok' => false, 'error' => 'Could not create folder. Check server permissions on /content/.'];
    }
    return ['ok' => true, 'error' => '', 'path' => $path, 'created' => true];
}

/**
 * List .html files in /content/<type>/<slug>/, alphabetically. Returns
 * just the filenames (e.g. ['main.html', 'v2.html']). Excludes hidden
 * files and anything that isn't a regular .html file.
 *
 * Returns an empty array if the folder doesn't exist yet — that's how the
 * picker shows the "Set Up Folder" CTA instead of an empty dropdown.
 */
function folder_scan(string $type, string $slug): array
{
    $path = folder_path($type, $slug);
    if ($path === '' || !is_dir($path)) return [];
    $out = [];
    $dh = @opendir($path);
    if ($dh === false) return [];
    while (($entry = readdir($dh)) !== false) {
        if ($entry === '.' || $entry === '..') continue;
        if ($entry[0] === '.') continue;
        if (!is_file($path . '/' . $entry)) continue;
        if (!preg_match('/\.html?$/i', $entry)) continue;
        $out[] = $entry;
    }
    closedir($dh);
    sort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

/**
 * Remove the folder for this (type, slug) if it exists AND is empty.
 * Returns ok=true if there's nothing to delete or the empty folder was
 * removed; returns ok=false if the folder has contents (the caller is
 * expected to surface "delete the files first" in the UI).
 */
function folder_delete(string $type, string $slug): array
{
    $v = _folder_validate($type, $slug);
    if (!$v['ok']) return $v;
    $path = folder_path($type, $slug);
    if ($path === '' || !is_dir($path)) {
        return ['ok' => true, 'error' => '', 'removed' => false];
    }
    // Non-empty check: any visible entry blocks deletion.
    $dh = @opendir($path);
    if ($dh === false) {
        return ['ok' => false, 'error' => 'Could not open folder for inspection.'];
    }
    $hasFiles = false;
    while (($entry = readdir($dh)) !== false) {
        if ($entry === '.' || $entry === '..') continue;
        $hasFiles = true;
        break;
    }
    closedir($dh);
    if ($hasFiles) {
        return ['ok' => false, 'error' => 'Folder is not empty. Remove its files first.'];
    }
    if (!@rmdir($path)) {
        return ['ok' => false, 'error' => 'Could not remove folder. Check server permissions.'];
    }
    return ['ok' => true, 'error' => '', 'removed' => true];
}

/**
 * Resolve an absolute path for a specific (type, slug, filename), or null
 * if the file doesn't exist or the filename tries to escape the folder.
 *
 * Used by templates/experiment-html.php to readfile() the selected HTML.
 */
function folder_file_path(string $type, string $slug, string $filename): ?string
{
    if ($filename === '') return null;
    // Reject any path component — only bare filenames are valid here.
    if (strpos($filename, '/') !== false || strpos($filename, '\\') !== false) return null;
    if (strpos($filename, "\0") !== false) return null;
    if ($filename === '.' || $filename === '..') return null;
    $folder = folder_path($type, $slug);
    if ($folder === '') return null;
    $path = $folder . '/' . $filename;
    if (!is_file($path)) return null;
    // Belt-and-braces: resolved path must stay under the folder.
    $real      = realpath($path);
    $realRoot  = realpath($folder);
    if ($real === false || $realRoot === false) return null;
    if (strpos($real, $realRoot . '/') !== 0) return null;
    return $real;
}
