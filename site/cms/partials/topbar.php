<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/topbar.php — fixed top bar (logo · breadcrumb · log out).
 *
 * Inputs (set before include):
 *   $breadcrumb  string  Plain text breadcrumb (e.g. "Pipeline", "Articles / New").
 *                        Defaults to "Pipeline" if unset.
 *   $csrf_token  string  CSRF token for the logout form. Required.
 *
 * The Log out button is a real <form method="post" action="/cms/logout"> per
 * AUTH-SECURITY.md §7 (state-changing requests are POST + CSRF). The mockup
 * uses a static <button class="btn-ghost">; this partial keeps the same
 * class so styling is identical.
 */

$breadcrumb = isset($breadcrumb) ? (string)$breadcrumb : 'Pipeline';
$csrf_token = (string)($csrf_token ?? '');
?>
<div class="topbar dot-surface">
  <div class="topbar-logo">alexmchong<span class="topbar-logo-sep"></span><em>cms</em></div>
  <div class="topbar-divider"></div>
  <div class="topbar-breadcrumb" id="breadcrumb"><span class="crumb-active"><?= htmlspecialchars($breadcrumb, ENT_QUOTES, 'UTF-8') ?></span></div>
  <div class="topbar-right">
    <form method="post" action="/cms/logout" style="display:inline">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn-ghost">Log out</button>
    </form>
  </div>
</div>
