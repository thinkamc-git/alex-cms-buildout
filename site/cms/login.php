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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
        $error = 'Form expired. Please try again.';
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
  .auth-card { width: 100%; max-width: 360px; background: var(--surface); border: var(--rule-faint); border-radius: var(--r-card); box-shadow: var(--shadow); padding: var(--space-32); }
  /* Mirrors the CMS topbar logo: sans "alexmchong" + small italic-serif "cms". */
  .auth-brand { font-family: var(--font); font-weight: 600; font-size: 22px; letter-spacing: -0.02em; color: var(--primary); line-height: 1; margin-bottom: var(--space-24); }
  .auth-brand em { font-family: var(--font-serif); font-style: italic; font-weight: 400; font-size: 1.1em; color: var(--muted); margin-left: 4px; }
  .auth-brand .topbar-env-pill { vertical-align: middle; }
  .auth-card .btn-pri { width: 100%; justify-content: center; margin-top: var(--space-8); padding: 9px 16px; }
  .error { font-size: var(--text-meta); color: var(--c-terracotta); background: color-mix(in srgb, var(--c-terracotta) 7%, transparent); border: 1px solid color-mix(in srgb, var(--c-terracotta) 25%, transparent); padding: var(--space-8) var(--space-12); border-radius: var(--r-pill); margin-bottom: var(--space-16); }
  .flash { font-size: var(--text-meta); color: var(--stage-published); background: color-mix(in srgb, var(--stage-published) 10%, transparent); border: 1px solid color-mix(in srgb, var(--stage-published) 28%, transparent); padding: var(--space-8) var(--space-12); border-radius: var(--r-pill); margin-bottom: var(--space-16); }
  .auth-staging { margin-top: var(--space-24); padding-top: var(--space-20); border-top: 1px dashed var(--ink-18); }
  .auth-staging .content-block-label { display: block; margin-bottom: var(--space-8); }
  .auth-staging p { font-size: var(--text-tiny); color: var(--muted); line-height: 1.6; margin-bottom: var(--space-12); }
</style>
</head>
<body>
<div class="auth-card">
  <div class="auth-brand">alexmchong<em>cms</em><?php if ($is_staging): ?><span class="topbar-env-pill" title="Staging environment">staging</span><?php endif; ?></div>
  <?= $flash_html ?>
  <?= $error_html ?>
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

  <?php if ($is_staging): ?>
  <div class="auth-staging">
    <span class="content-block-label">Staging tools</span>
    <p>Locked out after too many bad guesses? Clear the lock and try again. Staging only — the production login never shows this button.</p>
    <form method="post" action="/cms/unlock-account">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn-sec">Unlock account</button>
    </form>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
