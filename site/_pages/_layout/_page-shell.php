<?php
/**
 * _page-shell.php — shared chrome for the marketing pages.
 *
 * Each per-page assembler (about.php, coaching.php, …) sets these
 * variables and then requires this file:
 *
 *   $title         string  required — <title> contents
 *   $body          string  required — slug of the file in _bodies/<slug>.html
 *   $description   string  optional — <meta name="description"> content
 *   $noindex       bool    optional — when true, emits noindex meta
 *   $preview_mock  array   optional — set by site/index.php's preview hook
 *                                     when ?_preview=<id> is in scope
 *
 * Phase 20 cascade for header / footer (staging only, env-gated):
 *
 *   1. Preview mock for 'header' / 'footer' in scope    → render its body
 *   2. Published mock for 'header' / 'footer' in DB     → render its body
 *   3. New file _layout/header.php / footer.php exists  → require it
 *   4. Fallback — the legacy static _layout/header.html / footer.html
 *
 * Production stays on layer 4 (the frozen static files) until Phase 29
 * cutover removes the env gate.
 */
$title        = $title        ?? 'Alex M. Chong';
$description  = $description  ?? '';
$noindex      = $noindex      ?? false;
$body         = $body         ?? '';
$preview_mock = $preview_mock ?? null;

$_e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

// Marketing pages (about.php, …) are served directly by Apache and don't
// otherwise load config.php — without it APP_ENV is undefined and the
// staging-only cascade below collapses to the prod fallback. Pull it in
// here so every marketing-page request gets the right env constants.
foreach ([__DIR__ . '/../config/config.php', __DIR__ . '/../../config/config.php'] as $_cfg) {
    if (is_file($_cfg)) { require_once $_cfg; break; }
}

$_is_staging = defined('APP_ENV') && APP_ENV === 'staging';

// Tolerate two layouts. Post-deploy: webroot/_layout → webroot/lib
// (../lib). Source: site/_pages/_layout → site/lib (../../lib).
if (!function_exists('_pageshell_require_lib')) {
    function _pageshell_require_lib(string $file): bool {
        foreach ([__DIR__ . '/../lib/' . $file, __DIR__ . '/../../lib/' . $file] as $p) {
            if (is_file($p)) { require_once $p; return true; }
        }
        return false;
    }
}

// Preview integration: a logged-in CMS user previewing a mock hits the
// destination URL with ?_preview=<id>. The shell loads the mock from the
// DB and applies it either as a body override (page slug match) or as
// a partial override (header / footer). Unauthenticated requests with
// ?_preview ignore the query string and render the real page.
if ($_is_staging && isset($_GET['_preview']) && !$preview_mock) {
    $_preview_id = (int)($_GET['_preview']);
    if ($_preview_id > 0) {
        _pageshell_require_lib('auth.php');
        _pageshell_require_lib('pages.php');
        if (class_exists('Auth') && Auth::current_user() !== null && function_exists('get_page_mock')) {
            $_pm = get_page_mock($_preview_id);
            if ($_pm !== null) {
                // Accept partial previews always, or body previews when the
                // mock's slug matches the page's body.
                $_slug = (string)$_pm['slug'];
                if (in_array($_slug, ['header','footer'], true) || $_slug === $body) {
                    $preview_mock = $_pm;
                }
            }
        }
    }
}

/**
 * Render the layout partial for $zone ('header' | 'footer') honouring the
 * preview → published → file → legacy-html cascade.
 */
$_render_partial = static function (string $zone) use ($preview_mock, $_is_staging): void {
    if ($_is_staging) {
        // 1. Preview override targeting this zone.
        if ($preview_mock !== null && ($preview_mock['slug'] ?? '') === $zone) {
            if (!function_exists('render_partial_body')) {
                _pageshell_require_lib('pages.php');
            }
            echo render_partial_body((string)$preview_mock['body_html']);
            return;
        }
        // 2. Published mock for this zone.
        if (!function_exists('get_published_mock')) {
            _pageshell_require_lib('pages.php');
        }
        try {
            $mock = get_published_mock($zone);
        } catch (Throwable $e) {
            $mock = null;
        }
        if ($mock !== null) {
            echo render_partial_body((string)$mock['body_html']);
            return;
        }
        // 3. New PHP partial.
        $php = __DIR__ . '/' . $zone . '.php';
        if (is_file($php)) {
            require $php;
            return;
        }
    }
    // 4. Frozen static fallback — also the prod path.
    require __DIR__ . '/' . $zone . '.html';
};

// Preview override applied to the page-body slot when the previewed
// mock targets the current page's body (not a partial).
$_body_html_override = null;
if ($preview_mock !== null && ($preview_mock['slug'] ?? '') === $body) {
    $_body_html_override = (string)$preview_mock['body_html'];
    if (!empty($preview_mock['meta_title']))       $title       = (string)$preview_mock['meta_title'];
    if (!empty($preview_mock['meta_description'])) $description = (string)$preview_mock['meta_description'];
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $_e($title) ?></title>
<?php if ($description !== ''): ?>
  <meta name="description" content="<?= $_e($description) ?>" />
<?php endif; ?>
<?php if ($noindex): ?>
  <meta name="robots" content="noindex" />
<?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/_layout/style-pages.css" />
  <link rel="icon" type="image/png" href="/_layout/favicon<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png" />
  <script src="/_layout/analytics.js" async></script>
</head>
<body>

<?php $_render_partial('header'); ?>

<?php
if ($_body_html_override !== null) {
    // Preview-mock body content. Echoed as-is (HTML is trusted CMS content).
    echo $_body_html_override;
} else {
    $_body_file = __DIR__ . '/../_bodies/' . $body . '.html';
    if ($body !== '' && is_file($_body_file)) {
        require $_body_file;
    }
}
?>

<?php $_render_partial('footer'); ?>

</body>
</html>
