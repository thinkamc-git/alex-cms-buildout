<?php
/**
 * cms/views/design-system.php — in-CMS Design System viewer.
 *
 * The CSS Library: four by-source-file slices (Root · Pages · Blocks · CMS)
 * shown in an iframe and rendered from the real stylesheets so they never
 * drift, plus a launch into the full public /_ds/ showcase.
 *
 * Chrome follows the standard CMS shell (topbar + sidebar + view-header) and
 * the canonical .cms-tabs component — same as every other CMS view. A full
 * native component-catalogue rebuild is deferred to the CMS defect-sweep.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

Auth::require_login();

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>CMS Design System — alexmchong.ca CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/_ds/css/system-cms.css">
<link rel="stylesheet" href="/cms/_assets/style-cms.css">
<style>
  /* Page-scoped iframe panes only — everything else now comes from the
     standard CMS shell + the canonical .cms-tabs component. */
  .ds-frame { display: none; width: 100%; height: calc(100vh - 184px); border: 0; background: var(--neutral); }
  .ds-frame.is-active { display: block; }
</style>
</head>
<body>

<?php
$breadcrumb = 'CMS Design System';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'design-system';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-design-system">
      <?php
      $title    = 'CMS Design System';
      $subtitle = 'The CSS Library — the design system by source file, rendered from the real stylesheets so it never drifts.';
      $actions  = '<a href="/_ds/" target="_blank" rel="noopener" class="btn-sec">Full Design System ↗</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="cms-tabs" role="tablist">
        <button class="cms-tab is-active" data-slice="root" role="tab">Root</button>
        <button class="cms-tab" data-slice="pages" role="tab">Pages</button>
        <button class="cms-tab" data-slice="blocks" role="tab">Blocks</button>
        <button class="cms-tab" data-slice="cms" role="tab">CMS</button>
      </div>

      <div class="content-area" style="padding:0">
        <div class="ds-frames">
          <iframe class="ds-frame is-active" data-pane="root"   src="/_ds/showcase/root.html"             title="Root slice"></iframe>
          <iframe class="ds-frame"           data-pane="pages"  data-src="/_ds/showcase/pages.html"       title="Pages slice"></iframe>
          <iframe class="ds-frame"           data-pane="blocks" data-src="/_ds/showcase/blocks.html"      title="Blocks slice"></iframe>
          <iframe class="ds-frame"           data-pane="cms"    data-src="/_ds/showcase/cms-classes.html" title="CMS slice"></iframe>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
  // CSS Library: switch slice (lazy-load the others on first open).
  (function () {
    var tabs   = document.querySelectorAll('.cms-tab[data-slice]');
    var frames = document.querySelectorAll('.ds-frame');
    tabs.forEach(function (t) {
      t.addEventListener('click', function () {
        var key = t.getAttribute('data-slice');
        tabs.forEach(function (x) { x.classList.toggle('is-active', x === t); });
        frames.forEach(function (f) {
          var on = f.getAttribute('data-pane') === key;
          f.classList.toggle('is-active', on);
          if (on && !f.getAttribute('src') && f.dataset.src) { f.setAttribute('src', f.dataset.src); }
        });
      });
    });
  })();
</script>

</body>
</html>
