<?php $_is_staging = defined('APP_ENV') && APP_ENV === 'staging'; ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Lost — Alex M. Chong</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/_layout/style-pages.css" />
  <link rel="icon" type="image/png" href="/_layout/favicon<?= $_is_staging ? '-stage' : '' ?>.png" />

  <style>
    /* The 404 leads with the speech bubble alone; the nav + footer chrome
       fades in after a 2s pause to keep "you wandered off" as the first
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
      font-family: var(--font-cond);
      font-size: var(--text-label);
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--muted);
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

    @media (max-width: 720px) {
      .lost-bubble { font-size: var(--text-h4); padding: var(--space-20) var(--space-24); }
    }
  </style>
  <script src="/_layout/analytics.js" async></script>
</head>
<body>

  <nav class="layout-nav">
    <a href="/" class="layout-nav-logo" aria-label="Alex M. Chong — home">
      <img src="/_layout/logo.png" alt="Alex M. Chong" />
    </a>
    <div class="layout-nav-links">
      <a href="/ux2.0/how-we-got-here/" data-nav-key="ux2" style="position:relative">What's UX 2.0<span aria-hidden="true" style="position:absolute;top:-5px;right:-4px;width:10px;height:10px;background:#d63031;border-radius:50%"></span></a>
      <a href="/writing/" data-nav-key="writing">Thoughts</a>
      <a href="/live-sessions/" data-nav-key="talks">Talks</a>
      <a href="/work-with-me/" data-nav-key="work">Work with me</a>
    </div>
  </nav>

  <main class="lost-stack">
    <span class="lost-code">404 · You wandered off the path</span>

    <p class="lost-bubble" id="lost-quote">In the middle of nowhere lies the road to everywhere.</p>

    <div class="profile-circle is-lg lost-spin">
      <img class="profile-circle-img" src="/_layout/profile-bw.png" alt="Alex M. Chong"
           onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
      <span class="profile-circle-initials" aria-hidden="true">AC</span>
    </div>

    <a class="lost-back" href="/">Back to alexmchong.ca →</a>
  </main>

  <footer class="layout-footer">
    <span class="layout-footer-left">© <?= date('Y') ?> alex m. chong</span>
    <div class="layout-footer-right">
      <a href="/about/">About</a>
      <a href="/coaching/">Coaching</a>
      <a href="/work-with-me/">Services</a>
      <a href="/resume/">Resume</a>
    </div>
  </footer>

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

</body>
</html>
