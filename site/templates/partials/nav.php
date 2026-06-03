<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * partials/nav.php — public-site top nav (CMS-rendered pages).
 *
 * Sources its links from the `nav_items` table via render_nav('header')
 * — same path the marketing pages take through _pages/_layout/header.php.
 * The Navigation editor at /cms/navigation is the single source of truth.
 *
 * Active-state is computed by the client-side script at the bottom (same
 * one used by _pages/_layout/header.php): prefix-match the current path
 * against each link's href, longest match wins.
 */

if (!function_exists('render_nav')) {
    require_once __DIR__ . '/../../lib/nav.php';
}
?>
<nav class="layout-nav">
  <a class="layout-nav-logo" href="/" aria-label="Alex M. Chong — home">
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
  // Mirrors the same script in _pages/_layout/header.php.
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
