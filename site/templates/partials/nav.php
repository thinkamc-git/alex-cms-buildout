<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * partials/nav.php — public-site top nav (CMS-rendered pages).
 *
 * Phase 20.x: on staging, this partial sources its links from the
 * `nav_items` table via render_nav('header') — same path the marketing
 * pages take through _pages/_layout/header.php. The Navigation editor at
 * /cms/navigation is the single source of truth for both surfaces.
 *
 * Production stays on the frozen hardcoded link list until the Phase 29
 * cutover, matching the staging-only gate around the rest of the Phase 20
 * Pages CMS work. Once Phase 29 flips the gate, the prod branch below is
 * deleted in the same diff that flips the marketing cascade.
 *
 * Active-state is computed by the client-side script at the bottom (same
 * one used by _pages/_layout/header.php): prefix-match the current path
 * against each link's href, longest match wins.
 */

$_is_staging = defined('APP_ENV') && APP_ENV === 'staging';
?>
<nav class="layout-nav">
  <a class="layout-nav-logo" href="/" aria-label="Alex M. Chong — home">
    <img src="/_layout/logo.png" alt="Alex M. Chong" />
  </a>
  <div class="layout-nav-links">
<?php if ($_is_staging):
    if (!function_exists('render_nav')) {
        require_once __DIR__ . '/../../lib/nav.php';
    }
    render_nav('header');
else:
    // Frozen prod fallback — hand-maintained list, kept in lockstep with
    // _pages/_layout/header.html until the Phase 29 cutover replaces it.
    $links = [
        ['href' => '/ux2.0/how-we-got-here/', 'label' => "What's UX 2.0", 'dot' => true],
        ['href' => '/writing/',               'label' => 'Thoughts'],
        ['href' => '/live-sessions/',         'label' => 'Talks'],
        ['href' => '/work-with-me/',          'label' => 'Work with me'],
    ];
    foreach ($links as $l):
?>
    <a href="<?= htmlspecialchars((string)$l['href'], ENT_QUOTES, 'UTF-8') ?>" data-nav-key="<?= htmlspecialchars(trim((string)$l['href'], '/'), ENT_QUOTES, 'UTF-8') ?>"<?= !empty($l['dot']) ? ' style="position:relative"' : '' ?>>
      <?= htmlspecialchars((string)$l['label'], ENT_QUOTES, 'UTF-8') ?><?php if (!empty($l['dot'])): ?><span aria-hidden="true" style="position:absolute;top:-5px;right:-4px;width:10px;height:10px;background:#d63031;border-radius:50%"></span><?php endif; ?>
    </a>
<?php endforeach; endif; ?>
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
