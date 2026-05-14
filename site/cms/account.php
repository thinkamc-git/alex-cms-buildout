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
<link rel="stylesheet" href="/_ds/system.css">
<style>
  body { max-width: 32rem; margin: 4rem auto; padding: 0 1.5rem; font-family: var(--font-body, system-ui, sans-serif); }
  h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
  .meta { color: #555; margin-bottom: 1.5rem; font-size: 0.875rem; }
  label { display: block; margin-top: 1rem; font-size: 0.875rem; }
  input[type=password] { display: block; width: 100%; padding: 0.5rem 0.75rem; margin-top: 0.25rem; border: 1px solid #999; border-radius: 4px; font-size: 1rem; }
  button { margin-top: 1.5rem; padding: 0.625rem 1.25rem; border: 0; border-radius: 4px; background: #111; color: #fff; font-size: 1rem; cursor: pointer; }
  .error { color: #a00; background: #fee; padding: 0.5rem 0.75rem; border-radius: 4px; margin-bottom: 1rem; }
  .success { color: #060; background: #efe; padding: 0.5rem 0.75rem; border-radius: 4px; margin-bottom: 1rem; }
  .rules { font-size: 0.8125rem; color: #555; margin-top: 0.5rem; }
  nav a { margin-right: 1rem; font-size: 0.875rem; }
</style>
</head>
<body>
<nav><a href="/cms/">← Dashboard</a></nav>
<h1>Account</h1>
<p class="meta">Signed in as <strong><?= $email ?></strong></p>
<?= $error_html ?><?= $success_html ?>
<form method="post" action="">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
  <label>Current password
    <input type="password" name="current_password" autocomplete="current-password" required>
  </label>
  <label>New password
    <input type="password" name="new_password" autocomplete="new-password" required minlength="12">
  </label>
  <label>Confirm new password
    <input type="password" name="new_password_confirm" autocomplete="new-password" required minlength="12">
  </label>
  <p class="rules">At least 12 characters, with one uppercase, one lowercase, and one digit.</p>
  <button type="submit">Change password</button>
</form>
</body>
</html>
