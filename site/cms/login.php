<?php
/**
 * cms/login.php — login form + POST handler.
 *
 * Per AUTH-SECURITY.md §4. GET renders the form. POST verifies credentials and
 * redirects to ?next= (same-origin) or /cms/ on success. On failure, renders
 * the form again with a generic error.
 *
 * NOT protected by Auth::require_login — this IS the gate.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

Auth::start_session();

// Already logged in? Bounce to dashboard.
if (!empty($_SESSION['uid'])) {
    header('Location: /cms/', true, 302);
    exit;
}

$error = '';
$email = '';
$recoveryMode = isset($_GET['recovery']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
        $error = 'Form expired. Please try again.';
        $recoveryMode = isset($_POST['recovery_code']);
    } elseif (isset($_POST['recovery_code'])) {
        // Recovery-code sign-in. On success the session is flagged to force a
        // password change → land straight on the Account tab.
        $email = (string)($_POST['email'] ?? '');
        $res   = Auth::login_with_recovery_code($email, (string)$_POST['recovery_code']);
        if ($res['ok']) {
            header('Location: /cms/settings?tab=account&force=1', true, 302);
            exit;
        }
        $error        = (string)$res['error'];
        $recoveryMode = true;
    } else {
        $email = (string)($_POST['email'] ?? '');
        $pw    = (string)($_POST['password'] ?? '');
        $res   = Auth::login($email, $pw);
        if ($res['ok']) {
            $next = (string)($_GET['next'] ?? '/cms/');
            if ($next === '' || $next[0] !== '/') {
                $next = '/cms/';
            }
            header('Location: ' . $next, true, 302);
            exit;
        }
        $error = (string)$res['error'];
    }
}

$token = Csrf::token();
$email_attr = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$next_param = isset($_GET['next']) ? '&amp;next=' . rawurlencode((string)$_GET['next']) : '';
$error_html = $error !== '' ? '<p class="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';

// Staging-only chrome: the heading suffix and the self-serve unlock button
// only render when we're on the staging host. The unlock-account.php
// handler is also host-gated server-side, so even if this page is somehow
// rendered with the staging variant on prod, the route still 404s.
$is_staging = str_contains((string)($_SERVER['HTTP_HOST'] ?? ''), 'staging.alexmchong.ca');
$heading    = $is_staging ? 'CMS Login &ndash; Staging' : 'CMS Login';
$page_title = $is_staging ? 'CMS Login – Staging — alexmchong.ca' : 'CMS Login — alexmchong.ca';

// Flash message (e.g. "Account unlocked.") set by the unlock handler.
$flash      = (string)($_GET['flash'] ?? '');
$flash_html = $flash !== '' ? '<p class="flash">' . htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') . '</p>' : '';

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<!-- Phase 22.6: CMS auth pages now follow the CMS design system (was a dangling
     /_ds/system.css 404). Loads the system-cms.css barrel + style-cms.css — the
     same stack the admin views use — then a small layout-only block below. -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/_ds/css/system-cms.css">
<link rel="stylesheet" href="/cms/_assets/style-cms.css">
<style>
  /* Layout-only: centre a CMS card on the canvas. Components (.field-input,
     .btn-pri, .content-block-label) come from the CMS design system above. */
  /* Same dot-grid surface the rest of the CMS uses (base.css / .dot-surface). */
  body { min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; padding: var(--space-24); background-color: var(--neutral); background-image: var(--dot-grid); background-size: 4px 4px; }
  .auth-card { position: relative; width: 100%; max-width: 360px; background: var(--surface); border: var(--rule-faint); border-radius: var(--r-card); box-shadow: var(--shadow); padding: var(--space-32); }
  /* Mirrors the CMS topbar logo: sans "alexmchong" + small italic-serif "cms". */
  .auth-brand { font-family: var(--font); font-weight: 600; font-size: 22px; letter-spacing: -0.02em; color: var(--primary); line-height: 1; margin-bottom: var(--space-24); }
  .auth-brand em { font-family: var(--font-serif); font-style: italic; font-weight: 400; font-size: 1.1em; color: var(--muted); margin-left: 4px; }
  .auth-brand .topbar-env-pill { vertical-align: middle; }
  .auth-card .btn-pri { width: 100%; justify-content: center; margin-top: var(--space-8); padding: 9px 16px; }
  .error { font-size: var(--text-meta); color: var(--c-terracotta); background: color-mix(in srgb, var(--c-terracotta) 7%, transparent); border: 1px solid color-mix(in srgb, var(--c-terracotta) 25%, transparent); padding: var(--space-8) var(--space-12); border-radius: var(--r-pill); margin-bottom: var(--space-16); }
  .flash { font-size: var(--text-meta); color: var(--stage-published); background: color-mix(in srgb, var(--stage-published) 10%, transparent); border: 1px solid color-mix(in srgb, var(--stage-published) 28%, transparent); padding: var(--space-8) var(--space-12); border-radius: var(--r-pill); margin-bottom: var(--space-16); }
  .auth-alt { margin: var(--space-16) 0 0; text-align: center; }
  .auth-alt a { font-size: var(--text-meta); color: var(--muted); text-decoration: none; border-bottom: 1px solid var(--ink-18); padding-bottom: 1px; transition: color 0.15s; }
  .auth-alt a:hover { color: var(--primary); }
  .auth-staging { margin-top: var(--space-24); padding-top: var(--space-20); border-top: 1px dashed var(--ink-18); }
  .auth-staging .content-block-label { display: block; margin-bottom: var(--space-8); }
  .auth-staging p { font-size: var(--text-tiny); color: var(--muted); line-height: 1.6; margin-bottom: var(--space-12); }
  /* Env-switcher: pill (label) + Switch button + countdown status, sitting on
     the brand baseline. Button reuses .btn-sec.btn-tiny. */
  .env-switch { display: inline-flex; align-items: center; gap: var(--space-8); vertical-align: middle; }
  /* Switch control — an underline text link with a trailing arrow (matches the
     .auth-alt link convention used below). */
  .env-switch-link { display: inline-flex; align-items: center; gap: 4px; font-family: var(--font); font-size: var(--text-meta); font-weight: 500; color: var(--muted); text-decoration: none; border-bottom: 1px solid var(--ink-18); padding-bottom: 1px; transition: color 0.15s, border-color 0.15s; }
  .env-switch-link:hover { color: var(--primary); border-color: var(--primary); }
  .env-switch-arrow { font-size: 1.05em; line-height: 1; }
  /* "Switching…" overlay — a translucent white veil over the card with a
     centred spinner while the redirect to the other env loads. */
  .auth-switching { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: var(--space-12); background: color-mix(in srgb, var(--surface) 90%, transparent); border-radius: var(--r-card); z-index: 5; }
  .auth-switching[hidden] { display: none; }
  .auth-switching .spinner { width: 22px; height: 22px; border-width: 2.5px; margin: 0; color: var(--primary); }
  .auth-switching-label { font-size: var(--text-meta); color: var(--secondary); letter-spacing: 0.01em; }
</style>
</head>
<body>
<div class="auth-card">
  <div class="auth-brand">alexmchong<em>cms</em><?php
    // Environment pill (label) + a "Switch" button that hops to the OTHER
    // env's CMS — lands logged-in if a session exists there (it's /cms/, not
    // an auth page). Clicking shows a brief "Switching to …" countdown then
    // redirects (JS at the foot of the page).
    if (defined('APP_ENV') && (APP_ENV === 'staging' || APP_ENV === 'production')):
      $isProd   = APP_ENV === 'production';
      $pillCls  = $isProd ? 'topbar-env-pill topbar-env-pill--prod' : 'topbar-env-pill';
      $pillTxt  = $isProd ? 'prod' : 'staging';
      $swTarget = $isProd ? 'https://staging.alexmchong.ca/cms/' : 'https://alexmchong.ca/cms/';
      $swName   = $isProd ? 'Staging' : 'Production';
  ?><span class="env-switch">
      <span class="<?= $pillCls ?>" title="<?= ucfirst($pillTxt) ?> environment"><?= $pillTxt ?></span>
      <a href="<?= $swTarget ?>" class="env-switch-link" data-target="<?= $swTarget ?>" data-name="<?= $swName ?>">Switch <span class="env-switch-arrow" aria-hidden="true">&nearr;</span></a>
    </span><?php endif; ?></div>
  <?php if (isset($swName)): ?>
  <div class="auth-switching" hidden role="status" aria-live="polite">
    <span class="spinner" aria-hidden="true"></span>
    <span class="auth-switching-label">Switching to <?= $swName ?>&hellip;</span>
  </div>
  <?php endif; ?>
  <?= $flash_html ?>
  <?= $error_html ?>
  <?php if (!$recoveryMode): ?>
  <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
    <div class="field-group">
      <label class="field-label" for="login-email">Email</label>
      <input class="field-input" id="login-email" type="email" name="email" autocomplete="username" required value="<?= $email_attr ?>">
    </div>
    <div class="field-group">
      <label class="field-label" for="login-password">Password</label>
      <input class="field-input" id="login-password" type="password" name="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn-pri">Sign in</button>
  </form>
  <p class="auth-alt"><a href="?recovery=1<?= $next_param ?>">Lost access? Use a recovery code</a></p>
  <?php else: ?>
  <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
    <div class="field-group">
      <label class="field-label" for="login-email">Email</label>
      <input class="field-input" id="login-email" type="email" name="email" autocomplete="username" required value="<?= $email_attr ?>">
    </div>
    <div class="field-group">
      <label class="field-label" for="login-recovery">Recovery code</label>
      <input class="field-input" id="login-recovery" type="text" name="recovery_code" autocomplete="one-time-code" inputmode="text" placeholder="xxxx-xxxx" required>
    </div>
    <button type="submit" class="btn-pri">Sign in with recovery code</button>
  </form>
  <p class="auth-alt"><a href="/cms/login">← Back to password sign-in</a></p>
  <?php endif; ?>

  <?php if ($is_staging): ?>
  <div class="auth-staging">
    <span class="content-block-label">Staging tools</span>
    <p>Throttled after too many bad guesses while testing? Clear the login throttle and try again. Staging only — the production login never shows this button.</p>
    <form method="post" action="/cms/unlock-account">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn-sec">Clear login throttle</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<script>
  // Env switch: on click, veil the card with the white "Switching…" overlay +
  // spinner, then redirect to the other env's /cms/. The overlay stays up while
  // the target page loads. Progressive-enhancement: the link's href is the real
  // target, so it still works if JS is off (just without the overlay).
  document.querySelectorAll('.env-switch-link').forEach(function (link) {
    link.addEventListener('click', function (e) {
      e.preventDefault();
      var target = link.getAttribute('data-target');
      if (!target) return;
      var overlay = document.querySelector('.auth-switching');
      if (overlay) overlay.hidden = false;
      setTimeout(function () { window.location.href = target; }, 500);
    });
  });
</script>
</body>
</html>
