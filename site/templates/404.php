<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/404.php — themed 404 page (Phase 13).
 *
 * Rendered by site/index.php's not-found handler after resolve_redirect()
 * has been tried and missed. Staging-only until Phase 29 — prod still
 * serves the static /404.html. See the prod-freeze rule in BUILD-PLAN
 * §3.
 *
 * Wraps the master layout so the public nav + footer render around the
 * empty-state message. The body slot is composed inline below — we don't
 * need a per-content-type partial for this.
 */

$path = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = (string)(strtok($path, '?') ?: $path);
$e    = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

ob_start();
?>
<section class="content-section" style="padding:96px 24px 120px;text-align:center;max-width:680px;margin:0 auto">
  <p style="font-family:var(--font-mono);font-size:13px;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted);margin:0 0 18px">
    404 · Not found
  </p>
  <h1 style="font-family:var(--font-serif);font-style:italic;font-weight:400;font-size:clamp(40px,7vw,72px);line-height:1.1;margin:0 0 22px;color:var(--ink)">
    This page wandered off.
  </h1>
  <p style="font-size:18px;line-height:1.55;color:var(--ink-soft,#444);margin:0 0 36px">
    The URL <code style="font-family:var(--font-mono);font-size:0.92em;background:var(--bg-soft,#f5f3ee);padding:2px 8px;border-radius:4px"><?= $e($path) ?></code> doesn't match anything here. It may have moved when I reshaped the site — try the homepage and see if you can find what you were looking for.
  </p>
  <p style="margin:0">
    <a href="/" style="display:inline-block;padding:12px 26px;background:var(--ink);color:var(--paper,#fff);text-decoration:none;border-radius:6px;font-size:15px;font-weight:500">
      Back to the homepage
    </a>
  </p>
</section>
<?php
$body_slot         = (string)ob_get_clean();
$page_title        = 'Not found — alexmchong.ca';
$page_description  = 'That page is no longer here. Head back to the homepage to find what you were looking for.';

require __DIR__ . '/master-layout.php';
