<?php
/**
 * cms/account.php — change-password form + POST handler.
 *
 * Per AUTH-SECURITY.md §8. Requires login. Validates current_password,
 * enforces the §8 strength rules on new_password, writes the new hash, and
 * sets password_changed_at (which also triggers setup.php self-delete via
 * Auth::change_password).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

Auth::require_login();

$error   = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
        $error = 'Form expired. Please try again.';
    } else {
        $cur     = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password_confirm'] ?? '');
        $res = Auth::change_password($cur, $new, $confirm);
        if ($res['ok']) {
            $success = 'Password updated.';
        } else {
            $error = (string)$res['error'];
        }
    }
}

$user  = Auth::current_user();
$email = htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$token = Csrf::token();

$error_html   = $error   !== '' ? '<p class="error">'   . htmlspecialchars($error,   ENT_QUOTES, 'UTF-8') . '</p>' : '';
$success_html = $success !== '' ? '<p class="success">' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . '</p>' : '';

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Account — alexmchong.ca CMS</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<!-- Phase 22.6: CMS auth pages now follow the CMS design system (was a dangling
     /_ds/system.css 404). Loads the system-cms.css barrel + style-cms.css, then a
     small layout-only block below. -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/_ds/css/system-cms.css">
<link rel="stylesheet" href="/cms/_assets/style-cms.css">
<style>
  /* Layout-only: centre a CMS card on the canvas. Components come from the DS. */
  body { min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; padding: var(--space-24); background: var(--canvas-bg); }
  .auth-card { width: 100%; max-width: 400px; background: var(--surface); border: var(--rule-faint); border-radius: var(--r-card); box-shadow: var(--shadow); padding: var(--space-32); }
  .auth-back { display: inline-block; font-family: var(--font-cond); font-size: var(--text-micro); font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); text-decoration: none; margin-bottom: var(--space-20); transition: color 0.15s; }
  .auth-back:hover { color: var(--primary); }
  .auth-brand { font-family: var(--font-serif); font-style: italic; font-size: 24px; color: var(--primary); line-height: 1; }
  .auth-brand em { font-style: normal; font-family: var(--font-cond); font-size: var(--text-pill); font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); vertical-align: middle; margin-left: var(--space-8); }
  .auth-eyebrow { font-family: var(--font-cond); font-size: var(--text-micro); font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted); margin: var(--space-4) 0 var(--space-8); }
  .auth-meta { font-size: var(--text-meta); color: var(--muted); margin: 0 0 var(--space-24); }
  .auth-meta strong { color: var(--secondary); font-weight: 600; }
  .auth-card .btn-pri { width: 100%; justify-content: center; margin-top: var(--space-8); padding: 9px 16px; }
  .error { font-size: var(--text-meta); color: var(--c-terracotta); background: color-mix(in srgb, var(--c-terracotta) 7%, transparent); border: 1px solid color-mix(in srgb, var(--c-terracotta) 25%, transparent); padding: var(--space-8) var(--space-12); border-radius: var(--r-pill); margin-bottom: var(--space-16); }
  .success { font-size: var(--text-meta); color: var(--stage-published); background: color-mix(in srgb, var(--stage-published) 10%, transparent); border: 1px solid color-mix(in srgb, var(--stage-published) 28%, transparent); padding: var(--space-8) var(--space-12); border-radius: var(--r-pill); margin-bottom: var(--space-16); }
  .rules { font-size: var(--text-tiny); color: var(--muted); line-height: 1.6; margin: 0 0 var(--space-16); }
</style>
</head>
<body>
<div class="auth-card">
  <a class="auth-back" href="/cms/">← Dashboard</a>
  <div class="auth-brand">alexmchong<em>cms</em></div>
  <div class="auth-eyebrow">Account</div>
  <p class="auth-meta">Signed in as <strong><?= $email ?></strong></p>
  <?= $error_html ?><?= $success_html ?>
  <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
    <div class="field-group">
      <label class="field-label" for="acct-current">Current password</label>
      <input class="field-input" id="acct-current" type="password" name="current_password" autocomplete="current-password" required>
    </div>
    <div class="field-group">
      <label class="field-label" for="acct-new">New password</label>
      <input class="field-input" id="acct-new" type="password" name="new_password" autocomplete="new-password" required minlength="12">
    </div>
    <div class="field-group">
      <label class="field-label" for="acct-confirm">Confirm new password</label>
      <input class="field-input" id="acct-confirm" type="password" name="new_password_confirm" autocomplete="new-password" required minlength="12">
    </div>
    <p class="rules">At least 12 characters, with at least one uppercase letter, one lowercase letter, and one digit.</p>
    <button type="submit" class="btn-pri">Change password</button>
  </form>
</div>
</body>
</html>
