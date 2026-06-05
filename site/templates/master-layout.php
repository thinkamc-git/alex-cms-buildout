<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/master-layout.php — outer shell for every public CMS-rendered page.
 *
 * Inputs (set by render_content() before this is required):
 *   $page_title       string  Browser tab title.
 *   $page_description string  meta name="description" for SEO + cards.
 *   $body_slot        string  Pre-rendered inner content (already escaped
 *                             where appropriate by the inner template).
 *
 * Head wiring per Phase 6b Decisions: viewport meta + favicon + font links
 * + canonical design-system tokens/base/typography from /_ds/css/* +
 * article-template stylesheet from /_templates/. Loaded in cascade order
 * so style-articles.css wins where its rules overlap.
 */
$pt = (string)($page_title ?? 'alexmchong.ca');
$pd = (string)($page_description ?? '');
$bs = (string)($body_slot ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pt, ENT_QUOTES, 'UTF-8') ?></title>
  <?php if ($pd !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($pd, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <link rel="icon" type="image/png" href="/_layout/favicon<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <?php
  // Cache-buster: stamp each stylesheet with its on-disk mtime so a redeploy
  // of an existing CSS file invalidates the browser cache automatically. The
  // file_exists guards keep this safe in any environment.
  $cssVer = static function (string $rel): string {
      $full = $_SERVER['DOCUMENT_ROOT'] . $rel;
      return is_file($full) ? '?v=' . (int)filemtime($full) : '';
  };
  ?>
  <link rel="stylesheet" href="/_ds/css/tokens.css<?= $cssVer('/_ds/css/tokens.css') ?>">
  <link rel="stylesheet" href="/_ds/css/base.css<?= $cssVer('/_ds/css/base.css') ?>">
  <link rel="stylesheet" href="/_ds/css/typography.css<?= $cssVer('/_ds/css/typography.css') ?>">
  <link rel="stylesheet" href="/_ds/css/components.css<?= $cssVer('/_ds/css/components.css') ?>">
  <link rel="stylesheet" href="/_ds/css/status.css<?= $cssVer('/_ds/css/status.css') ?>">
  <link rel="stylesheet" href="/_ds/css/views.css<?= $cssVer('/_ds/css/views.css') ?>">
  <!-- Phase 22.6 (DS v2.1 sunset): Blocks slice is now the sole article stylesheet —
       style-articles.css deleted, its rules live here verbatim. Linked DIRECTLY
       (not via system-public.css) so pages.css marketing chrome doesn't bleed
       onto articles. -->
  <link rel="stylesheet" href="/_ds/css/public/blocks.css<?= $cssVer('/_ds/css/public/blocks.css') ?>">
  <script src="/_layout/analytics.js" async></script>
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <?php if (empty($preview_no_chrome)) require __DIR__ . '/partials/nav.php'; ?>
  <main class="layout-main" id="main" tabindex="-1">
<?= $bs ?>
  </main>
  <?php if (empty($preview_no_chrome)) require __DIR__ . '/partials/footer.php'; ?>
</body>
</html>
