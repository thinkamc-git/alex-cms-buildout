<?php
/**
 * error.php — shared error page for 404 / 403 / 500.
 *
 * One template, the message swaps by HTTP code. The code is resolved from
 * (in priority order):
 *   1. $_error_code         — set by index.php's not-found handler (route miss)
 *   2. $_SERVER['REDIRECT_STATUS'] — set by Apache when it invokes ErrorDocument
 * and falls back to 404. Unknown codes collapse to 404.
 *
 * Design is the "lost-stack" speech-bubble layout (Phase 20.x). 404 keeps the
 * rotating quotes (a missing page is low-stakes); 403/500 show one calm line.
 *
 * P4a: per-code messages are hardcoded defaults below. The CMS "Error pages"
 * editing surface + DB-down static baking land in P4b.
 */
// Served directly by Apache as an ErrorDocument, so config.php (which defines
// APP_ENV) isn't loaded unless we're being included via index.php. Pull it in
// so the staging gate, the DB-driven nav, and the ?code preview all resolve.
if (!defined('APP_ENV')) {
    foreach ([__DIR__ . '/config/config.php', __DIR__ . '/../config/config.php'] as $_c) {
        if (is_file($_c)) { require_once $_c; break; }
    }
}
$_is_staging = defined('APP_ENV') && APP_ENV === 'staging';

// ── Resolve the HTTP code this page answers for ─────────────────────────
$_code = 404;
if (isset($_error_code)) {
    $_code = (int)$_error_code;
} elseif (isset($_SERVER['REDIRECT_STATUS'])) {
    $_code = (int)$_SERVER['REDIRECT_STATUS'];
}
if (!in_array($_code, [403, 404, 500], true)) {
    $_code = 404;
}
// Staging-only preview: /error.php?code=403 renders that variant in the browser
// so the design can be eyeballed without triggering a real error. Never on prod.
if ($_is_staging && isset($_GET['code']) && in_array((int)$_GET['code'], [403, 404, 500], true)) {
    $_code = (int)$_GET['code'];
}
http_response_code($_code);

// ── Per-code defaults (P4b moves these into the CMS) ────────────────────
// 'chrome' => whether to render the header/footer nav. 404/403 keep it (the
// site works, nav helps you back in). 500 drops it: the system is down, so
// links to other pages probably fail too — a menu into breakage is worse than
// none. Dropping it also keeps the 500 dependency-free (no DB nav) and trivial
// to bake to static (P4b).
$_messages = [
    404 => [
        'title'   => 'Lost',
        'label'   => '404 · Page Not Found',
        'message' => 'In the middle of nowhere lies the road to everywhere.',
        'quotes'  => true,
        'chrome'  => true,
        'action'  => ['label' => '← Back to alexmchong.ca', 'reload' => false],
    ],
    403 => [
        'title'   => 'Restricted',
        'label'   => '403 · Public Access Denied',
        'message' => 'This page is closed to the public.',
        'icon'    => 'denied',
        'quotes'  => false,
        'chrome'  => true,
        'action'  => ['label' => '← Back to alexmchong.ca', 'reload' => false],
    ],
    500 => [
        'title'   => 'Server error',
        'label'   => '500 · Server error',
        'message' => 'Let’s give the server a moment.',
        'icon'    => 'alert',
        'quotes'  => false,
        'chrome'  => false,
        'action'  => ['label' => 'Try again', 'reload' => true],
    ],
];
$_m = $_messages[$_code];

// Phase 20.x: pull the nav from the DB on staging so the Navigation editor
// drives the error chrome too. Prod stays on the hardcoded fallback below.
if ($_is_staging && !function_exists('render_nav')) {
    foreach ([__DIR__ . '/../lib/nav.php', __DIR__ . '/lib/nav.php'] as $_p) {
        if (is_file($_p)) { require_once $_p; break; }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($_m['title'], ENT_QUOTES, 'UTF-8') ?> — Alex M. Chong</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <!-- Phase 22.6: public design system barrel (replaces deleted style-pages.css). -->
  <link rel="stylesheet" href="/_ds/css/system-public.css" />
  <link rel="icon" type="image/png" href="/_layout/favicon<?= $_is_staging ? '-stage' : '' ?>.png" />

  <style>
    /* The error page leads with the speech bubble alone; the nav + footer
       chrome fades in after a 2s pause to keep the message as the first
       focal beat. The body itself is a column flex container so the
       chrome animates around the centered lost-stack without shifting it. */

    body { min-height: 100vh; display: flex; flex-direction: column; margin: 0; }

    .lost-stack {
      opacity: 0;
      animation: lost-fade-in 0.5s ease-out forwards;
    }
    .layout-nav, .layout-footer {
      opacity: 0;
      animation: lost-fade-in 1.5s ease-out 2s forwards;
    }
    @keyframes lost-fade-in { to { opacity: 1; } }
    @media (prefers-reduced-motion: reduce) {
      .lost-stack, .layout-nav, .layout-footer { opacity: 1; animation: none; }
    }

    .lost-stack {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: var(--space-32);
      padding: var(--space-48) var(--space-24);
      text-align: center;
    }

    .lost-code {
      display: inline-flex;
      align-items: center;
      gap: var(--space-8);
      font-family: var(--font-cond);
      font-size: var(--text-label);
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--muted);
    }
    .lost-code-icon {
      width: 1.1em;
      height: 1.1em;
      flex: none;
    }

    .lost-bubble {
      position: relative;
      max-width: 460px;
      padding: var(--space-24) var(--space-32);
      background: var(--surface);
      border: var(--rule-30);
      border-radius: 14px;
      font-family: var(--font-serif);
      font-style: italic;
      font-weight: 400;
      font-size: var(--text-h3);
      line-height: 1.35;
      color: var(--primary);
    }
    /* Downward-pointing tail. Two stacked triangles: ::before is the
       outer triangle in the rule color, ::after is the inner triangle
       in the surface color, slightly smaller, so a 1px border peeks
       around the tail's edge. */
    .lost-bubble::before,
    .lost-bubble::after {
      content: "";
      position: absolute;
      top: 100%;
      left: 50%;
      width: 0;
      height: 0;
      transform: translateX(-50%);
      border-style: solid;
      border-color: transparent;
    }
    .lost-bubble::before {
      border-width: 16px 14px 0 14px;
      border-top-color: var(--ink-30);
    }
    .lost-bubble::after {
      border-width: 14px 12px 0 12px;
      border-top-color: var(--surface);
    }

    .lost-spin {
      animation: lost-spin-kf 6s linear infinite;
    }
    @keyframes lost-spin-kf {
      from { transform: rotate(0deg); }
      to   { transform: rotate(360deg); }
    }
    /* Respect users who've asked the OS to minimize motion. */
    @media (prefers-reduced-motion: reduce) {
      .lost-spin { animation: none; }
    }

    .lost-back {
      font-family: var(--font-cond);
      font-size: var(--text-label);
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--secondary);
      text-decoration: none;
      border-bottom: 1px solid var(--ink-30);
      padding-bottom: 2px;
      transition: color 0.18s ease, border-color 0.18s ease;
    }
    .lost-back:hover {
      color: var(--primary);
      border-color: var(--primary);
    }

    /* Reload variant (500): static refresh glyph + label, same underline. */
    .lost-back-reload {
      display: inline-flex;
      align-items: center;
      gap: var(--space-8);
    }
    .lost-refresh-icon {
      width: 0.9em;
      height: 0.9em;
    }

    @media (max-width: 720px) {
      .lost-bubble { font-size: var(--text-h4); padding: var(--space-20) var(--space-24); }
    }
  </style>
  <script src="/_layout/analytics.js" async></script>
</head>
<body>

<?php if ($_m['chrome']): ?>
  <nav class="layout-nav">
    <a href="/" class="layout-nav-logo" aria-label="Alex M. Chong — home">
      <img src="/_layout/logo.png" alt="Alex M. Chong" />
    </a>
    <div class="layout-nav-links">
<?php if ($_is_staging && function_exists('render_nav')): ?>
<?php render_nav('header'); ?>
<?php else: ?>
      <a href="/ux2.0/how-we-got-here/" data-nav-key="ux2" style="position:relative">What's UX 2.0<span aria-hidden="true" style="position:absolute;top:-5px;right:-4px;width:10px;height:10px;background:#d63031;border-radius:50%"></span></a>
      <a href="/writing/" data-nav-key="writing">Thoughts</a>
      <a href="/live-sessions/" data-nav-key="talks">Talks</a>
      <a href="/work-with-me/" data-nav-key="work">Work with me</a>
<?php endif; ?>
    </div>
  </nav>
<?php endif; ?>

  <main class="lost-stack">
    <span class="lost-code">
<?php if (($_m['icon'] ?? '') === 'denied'): ?>
      <svg class="lost-code-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
      </svg>
<?php elseif (($_m['icon'] ?? '') === 'alert'): ?>
      <svg class="lost-code-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
        <line x1="12" y1="9" x2="12" y2="13"></line>
        <line x1="12" y1="17" x2="12.01" y2="17"></line>
      </svg>
<?php endif; ?>
      <?= htmlspecialchars($_m['label'], ENT_QUOTES, 'UTF-8') ?>
    </span>

    <p class="lost-bubble" id="lost-quote"><?= htmlspecialchars($_m['message'], ENT_QUOTES, 'UTF-8') ?></p>

    <div class="profile-circle is-lg lost-spin">
      <img class="profile-circle-img" src="/_layout/profile-bw.png" alt="Alex M. Chong"
           onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
      <span class="profile-circle-initials" aria-hidden="true">AC</span>
    </div>

<?php if ($_m['action']['reload']): ?>
    <a class="lost-back lost-back-reload" href="/" onclick="location.reload();return false;">
      <svg class="lost-refresh-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="23 4 23 10 17 10"></polyline>
        <polyline points="1 20 1 14 7 14"></polyline>
        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
      </svg>
      <?= htmlspecialchars($_m['action']['label'], ENT_QUOTES, 'UTF-8') ?>
    </a>
<?php else: ?>
    <a class="lost-back" href="/"><?= htmlspecialchars($_m['action']['label'], ENT_QUOTES, 'UTF-8') ?></a>
<?php endif; ?>
  </main>

<?php if ($_m['chrome']): ?>
  <footer class="layout-footer">
    <span class="layout-footer-left">© <?= date('Y') ?> alex m. chong</span>
    <div class="layout-footer-right">
<?php if ($_is_staging && function_exists('render_nav')): ?>
<?php render_nav('footer'); ?>
<?php else: ?>
      <a href="/about/">About</a>
      <a href="/coaching/">Coaching</a>
      <a href="/work-with-me/">Services</a>
      <a href="/resume/">Resume</a>
<?php endif; ?>
    </div>
  </footer>
<?php endif; ?>

<?php if ($_m['quotes']): ?>
  <script>
    (function () {
      var quotes = [
        "I saw that my life was a vast glowing empty page and I could do anything I wanted.",
        "You don’t have to stay anywhere forever.",
        "Try again. Fail again. Fail better.",
        "You are free and that is why you are lost.",
        "In the middle of nowhere lies the road to everywhere.",
        "Lost in thought, I found endless possibilities.",
        "To be lost is to begin anew.",
        "Lost, I discovered parts of myself I never knew existed.",
        "To be lost is to open yourself to new directions.",
        "The wrong destination often brings the right lessons."
      ];
      var el = document.getElementById("lost-quote");
      if (el) el.textContent = quotes[Math.floor(Math.random() * quotes.length)];
    })();
  </script>
<?php endif; ?>

</body>
</html>
