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
<link rel="stylesheet" href="/_ds/system.css">
<style>
  body { max-width: 28rem; margin: 4rem auto; padding: 0 1.5rem; font-family: var(--font-body, system-ui, sans-serif); }
  h1 { font-size: 1.5rem; margin-bottom: 1.5rem; }
  label { display: block; margin-top: 1rem; font-size: 0.875rem; }
  input[type=email], input[type=password] { display: block; width: 100%; padding: 0.5rem 0.75rem; margin-top: 0.25rem; border: 1px solid #999; border-radius: 4px; font-size: 1rem; }
  button { margin-top: 1.5rem; padding: 0.625rem 1.25rem; border: 0; border-radius: 4px; background: #111; color: #fff; font-size: 1rem; cursor: pointer; }
  .error { color: #a00; background: #fee; padding: 0.5rem 0.75rem; border-radius: 4px; margin-bottom: 1rem; }
  .flash { color: #0a5; background: #eaf7ef; padding: 0.5rem 0.75rem; border-radius: 4px; margin-bottom: 1rem; }
  .staging-tools { margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px dashed #ccc; }
  .staging-tools h2 { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #777; margin-bottom: 0.75rem; }
  .staging-tools p { font-size: 0.8125rem; color: #666; margin-bottom: 0.75rem; line-height: 1.5; }
  .staging-tools button { margin-top: 0; padding: 0.5rem 1rem; background: #5a5a5a; font-size: 0.875rem; }
</style>
</head>
<body>
<h1><?= $heading ?></h1>
<?= $flash_html ?>
<?= $error_html ?>
<form method="post" action="">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
  <label>Email
    <input type="email" name="email" autocomplete="username" required value="<?= $email_attr ?>">
  </label>
  <label>Password
    <input type="password" name="password" autocomplete="current-password" required>
  </label>
  <button type="submit">Sign in</button>
</form>

<?php if ($is_staging): ?>
<div class="staging-tools">
  <h2>Staging tools</h2>
  <p>Locked out after too many bad guesses? Clear it and try again. Staging only — the prod login never shows this button.</p>
  <form method="post" action="/cms/unlock-account">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">Unlock account</button>
  </form>
</div>
<?php endif; ?>
</body>
</html>
