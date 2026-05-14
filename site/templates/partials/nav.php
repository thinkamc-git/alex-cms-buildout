<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * partials/nav.php — public-site top nav.
 *
 * Mirrors the topbar from site/_pages/_layout/landing-postcms.html (the
 * post-CMS landing canvas). The `Thoughts` / `Talks` / `Experiments` /
 * `Journal` links currently target their CMS index URLs (Phase 12 builds
 * the index views); for Phase 6b they are stubs pointing at `/writing`
 * etc. The Phase 9 cutover will swap these into the live nav across the
 * marketing pages too.
 */
?>
<nav class="layout-nav">
  <a class="layout-nav-logo" href="/">Alex M. Chong</a>
  <div class="layout-nav-links">
    <a href="/writing">Thoughts</a>
    <a href="/live-sessions">Talks</a>
    <a href="/experiments">Experiments</a>
    <a href="/journal">Journal</a>
    <a href="/about">About</a>
  </div>
</nav>
