<?php
/**
 * cms/index.php — placeholder dashboard (post-login landing).
 *
 * Phase 4 ships this minimal page just to prove the login flow lands somewhere
 * authenticated. Phase 5 replaces it with the real CMS chrome (sidebar + topbar
 * + view container) ported from docs/design-mockups/cms-ui.html.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

Auth::require_login();
$user  = Auth::current_user();
$email = htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$token = Csrf::token();

header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CMS — alexmchong.ca</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link rel="stylesheet" href="/_ds/system.css">
<style>
  body { max-width: 56rem; margin: 3rem auto; padding: 0 1.5rem; font-family: var(--font-body, system-ui, sans-serif); }
  header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd; }
  h1 { font-size: 1.5rem; margin: 0; }
  .meta { color: #555; font-size: 0.875rem; }
  form.logout { display: inline; }
  form.logout button { background: none; border: 0; color: #06c; cursor: pointer; font: inherit; padding: 0; text-decoration: underline; }
  nav a { margin-right: 1rem; }
  .placeholder { color: #555; padding: 2rem; border: 1px dashed #ccc; border-radius: 4px; }
</style>
</head>
<body>
<header>
  <h1>alexmchong.ca CMS</h1>
  <div class="meta">
    <span><?= $email ?></span>
    &nbsp;·&nbsp;
    <a href="/cms/account">Account</a>
    &nbsp;·&nbsp;
    <form method="post" action="/cms/logout" class="logout">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit">Log out</button>
    </form>
  </div>
</header>
<div class="placeholder">
  Dashboard placeholder. Phase 5 ports the real CMS chrome here (sidebar + topbar + content views).
</div>
</body>
</html>
