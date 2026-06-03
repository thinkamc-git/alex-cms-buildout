<?php
declare(strict_types=1);

/**
 * lib/settings.php — site-wide settings (Phase 21).
 *
 * Flat key/value access to the `settings` table. Used by the public page
 * shell (cms-served defaults for title suffix, og tags, analytics, footer
 * copyright) and by the Settings CMS view.
 *
 * Reads are cached per-request to keep the page-shell from re-querying for
 * every meta tag. Writes invalidate the cache.
 *
 * Allowed keys are enumerated in SETTINGS_KEYS so a typo in a caller can't
 * silently insert a phantom row. The Settings view introspects this list
 * to render its form — adding a new setting is: add to the const, add a
 * seed row in the migration, render in the view.
 */

require_once __DIR__ . '/db.php';

const SETTINGS_KEYS = [
    'site_title',
    'site_tagline',
    'default_og_image',
    'default_og_type',
    'default_twitter_card',
    'footer_copyright',
    'analytics_script',
];

/** Per-request cache. Populated by the first list_settings() call. */
$GLOBALS['__settings_cache'] = null;

function list_settings(): array
{
    if (is_array($GLOBALS['__settings_cache'])) {
        return $GLOBALS['__settings_cache'];
    }
    $out = [];
    foreach (SETTINGS_KEYS as $k) {
        $out[$k] = '';
    }
    try {
        $rows = db()->query('SELECT `key`, `value` FROM settings')->fetchAll();
    } catch (Throwable $e) {
        $rows = [];
    }
    foreach ($rows as $r) {
        $k = (string)$r['key'];
        if (in_array($k, SETTINGS_KEYS, true)) {
            $out[$k] = (string)($r['value'] ?? '');
        }
    }
    $GLOBALS['__settings_cache'] = $out;
    return $out;
}

/**
 * Fetch a single setting. Returns the empty string when missing so callers
 * can use `?:` for fallback chains without juggling null.
 */
function get_setting(string $key, string $default = ''): string
{
    if (!in_array($key, SETTINGS_KEYS, true)) return $default;
    $all = list_settings();
    $v = $all[$key] ?? '';
    return $v !== '' ? $v : $default;
}

/**
 * Upsert a single setting. Silently ignores unknown keys — the Settings view
 * always submits the full known set, but a malformed POST shouldn't be able
 * to inject arbitrary rows.
 */
function set_setting(string $key, string $value): void
{
    if (!in_array($key, SETTINGS_KEYS, true)) return;
    $stmt = db()->prepare(
        'INSERT INTO settings (`key`, `value`) VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );
    $stmt->execute([':k' => $key, ':v' => $value]);
    $GLOBALS['__settings_cache'] = null;
}

/**
 * Bulk save. $values is an assoc of key=>value pairs; unknown keys are
 * skipped. Returns the count of rows written.
 */
function save_settings(array $values): int
{
    $n = 0;
    foreach ($values as $k => $v) {
        if (in_array($k, SETTINGS_KEYS, true)) {
            set_setting((string)$k, (string)$v);
            $n++;
        }
    }
    return $n;
}
