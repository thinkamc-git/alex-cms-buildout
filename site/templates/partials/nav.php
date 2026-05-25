<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * partials/nav.php — public-site top nav (CMS-rendered pages).
 *
 * Mirrors site/_pages/_layout/header.html exactly so the nav looks the
 * same whether the visitor lands on a marketing page (about, coaching,
 * landing) or a CMS-rendered page (/writing/foo, /journal/bar, etc.).
 *
 * Active state is computed server-side here (we have $_SERVER) and
 * prefix-matched against the four nav slugs.
 */

$path = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = (string)(strtok($path, '?') ?: '/');
$path = rtrim($path, '/');
if ($path === '') $path = '/';

$links = [
    ['href' => '/ux2.0/how-we-got-here/', 'label' => "What's UX 2.0", 'match' => '/ux2.0', 'dot' => true],
    ['href' => '/writing/',               'label' => 'Thoughts',      'match' => '/writing'],
    ['href' => '/live-sessions/',         'label' => 'Talks',         'match' => '/live-sessions'],
    ['href' => '/work-with-me/',          'label' => 'Work with me',  'match' => '/work-with-me'],
];

$active = null;
$bestLen = -1;
foreach ($links as $i => $l) {
    $m = (string)$l['match'];
    if ($path === $m || str_starts_with($path . '/', $m . '/')) {
        if (strlen($m) > $bestLen) { $active = $i; $bestLen = strlen($m); }
    }
}
?>
<nav class="layout-nav">
  <a class="layout-nav-logo" href="/" aria-label="Alex M. Chong — home">
    <img src="/_layout/logo.png" alt="Alex M. Chong" />
  </a>
  <div class="layout-nav-links">
    <?php foreach ($links as $i => $l): ?>
      <a href="<?= htmlspecialchars((string)$l['href'], ENT_QUOTES, 'UTF-8') ?>"<?= $active === $i ? ' class="is-active"' : '' ?><?= !empty($l['dot']) ? ' style="position:relative"' : '' ?>>
        <?= htmlspecialchars((string)$l['label'], ENT_QUOTES, 'UTF-8') ?><?php if (!empty($l['dot'])): ?><span aria-hidden="true" style="position:absolute;top:-5px;right:-4px;width:10px;height:10px;background:#d63031;border-radius:50%"></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
