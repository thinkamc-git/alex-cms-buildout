<?php
/**
 * _layout/header.php — DB-driven marketing nav (Phase 20, staging).
 *
 * This file is the LIVE VERSION shown in the Pages CMS editor for the
 * "header" partial. The CMS may also publish a mock that overrides this
 * file at runtime (see _page-shell.php and lib/pages.php).
 *
 * The wrapping <nav>, the logo <a>, and the active-link <script> are
 * chrome that stays in code. The inner link list is emitted by
 * render_nav('header'), which reads the nav_items table.
 *
 * Production keeps using the static header.html until the Phase 29
 * cutover — see bin/deploy.sh prod-freeze.
 */
if (!function_exists('render_nav')) {
    // Post-deploy: webroot/_layout → webroot/lib (../lib). Source:
    // site/_pages/_layout → site/lib (../../lib). Try both.
    foreach ([__DIR__ . '/../lib/nav.php', __DIR__ . '/../../lib/nav.php'] as $_p) {
        if (is_file($_p)) { require_once $_p; break; }
    }
}
?>
<nav class="layout-nav">
  <a href="/" class="layout-nav-logo" aria-label="Alex M. Chong — home">
    <img src="/_layout/logo.png" alt="Alex M. Chong" />
  </a>
  <div class="layout-nav-links">
<?php render_nav('header'); ?>
  </div>
</nav>
<script>
  // Adds .is-active to the nav link matching the current URL. Uses prefix
  // matching so /writing/foo highlights the "Thoughts" link, /live-sessions/x
  // highlights "Talks", etc. Exact path match wins when present.
  (function () {
    var path = location.pathname.replace(/\/$/, '');
    if (path === '') path = '/';
    var links = document.querySelectorAll('.layout-nav a[data-nav-key]');
    var bestEl = null;
    var bestLen = -1;
    for (var i = 0; i < links.length; i++) {
      var href = links[i].getAttribute('href').replace(/\/$/, '');
      if (href === '') continue;
      if (path === href || path.indexOf(href + '/') === 0) {
        if (href.length > bestLen) { bestEl = links[i]; bestLen = href.length; }
      }
    }
    if (bestEl) bestEl.classList.add('is-active');
  })();
</script>
