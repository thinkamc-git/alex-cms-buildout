<?php
/**
 * cms/views/redirects.php — Redirects admin (Phase 13).
 *
 * Single-page table: each row binds via HTML5 form="red-row-N" to a
 * hidden <form> rendered before the table (same pattern as
 * cms/views/categories.php). Three POST actions:
 *   action=add     → insert a new row from the bottom "Add" form
 *   action=update  → save edits to an existing row
 *   action=delete  → remove a row
 *
 * The legacy /portfolio, /research, etc. redirects were migrated out of
 * .htaccess by migration 0011 — this view is now the only place to
 * manage them. New rows default to 301 (permanent); use 302 for any
 * destination that might move (third-party services, A/B tests, etc.).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/redirects.php';

Auth::require_login();
$csrf_token = Csrf::token();

$errors = [];
$flash  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add') {
            $id = save_redirect([
                'old_slug'    => (string)($_POST['old_slug']    ?? ''),
                'new_slug'    => (string)($_POST['new_slug']    ?? ''),
                'status_code' => (int)   ($_POST['status_code'] ?? 301),
            ], null);
            if ($id === null) {
                $errors[] = 'Could not add. Check for blank fields, duplicate old path, or old = new (would loop).';
            } else {
                $flash = 'Redirect added.';
            }
        } elseif ($action === 'update') {
            $id = save_redirect([
                'old_slug'    => (string)($_POST['old_slug']    ?? ''),
                'new_slug'    => (string)($_POST['new_slug']    ?? ''),
                'status_code' => (int)   ($_POST['status_code'] ?? 301),
            ], (int)($_POST['id'] ?? 0));
            if ($id === null) {
                $errors[] = 'Could not update. Check for blank fields, duplicate old path, or old = new.';
            } else {
                $flash = 'Redirect updated.';
            }
        } elseif ($action === 'delete') {
            delete_redirect((int)($_POST['id'] ?? 0));
            $flash = 'Redirect deleted.';
        } else {
            $errors[] = 'Unknown action.';
        }

        if (count($errors) === 0) {
            header('Location: /cms/redirects?flash=' . rawurlencode($flash));
            exit;
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

$rows = list_redirects();

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Redirects — alexmchong.ca CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/_ds/css/tokens.css">
<link rel="stylesheet" href="/_ds/css/base.css">
<link rel="stylesheet" href="/_ds/css/typography.css">
<link rel="stylesheet" href="/_ds/css/shell.css">
<link rel="stylesheet" href="/_ds/css/components.css">
<link rel="stylesheet" href="/_ds/css/tables.css">
<link rel="stylesheet" href="/_ds/css/status.css">
<link rel="stylesheet" href="/_ds/css/views.css">
<link rel="stylesheet" href="/cms/_assets/style-cms.css">
<style>
  .red-table { width: 100%; border-collapse: collapse; }
  .red-table th, .red-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-subtle); vertical-align: middle; }
  .red-table th { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); text-align: left; font-weight: 600; }
  .red-table input[type="text"] { width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-family: var(--font-mono); font-size: 12px; background: var(--surface); color: var(--ink); }
  .red-table select { padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px; background: var(--surface); color: var(--ink); }
  .red-add { background: var(--bg-soft); }
  .red-add td { padding-top: 14px; padding-bottom: 14px; }
  .btn-row-action { padding: 4px 10px; font-size: 11px; border: 1px solid var(--border); background: var(--surface); border-radius: 4px; cursor: pointer; }
  .btn-row-action:hover { background: var(--bg-soft); }
  .btn-row-del { background: none; border: none; cursor: pointer; color: var(--muted); padding: 4px; line-height: 0; }
  .btn-row-del:hover { color: var(--c-danger, #c44); }
  .btn-row-del svg { width: 14px; height: 14px; }
</style>
</head>
<body>

<?php
$breadcrumb = 'Redirects';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'redirects';
  $nav_counts    = ['redirects' => count($rows)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-redirects">
      <?php
      $title    = 'Redirects';
      $subtitle = 'Map an old URL to a new one. Default is 301 (permanent, browsers cache it). Use 302 when the destination might move — third-party services, A/B tests, or anything not yet stable.';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php if (count($errors) > 0): ?>
        <div class="form-errors" role="alert" style="margin:var(--space-16) var(--space-24) 0">
          <strong>Couldn't save:</strong>
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?= $e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($flash !== ''): ?>
        <div class="flash-success" role="status" style="margin:var(--space-16) var(--space-24) 0"><?= $e($flash) ?></div>
      <?php endif; ?>

      <?php
      // Per-row forms (one per existing row), rendered before the table.
      foreach ($rows as $r):
        $rid = 'red-row-' . (int)$r['id'];
      ?>
        <form id="<?= $e($rid) ?>" method="post" action="/cms/redirects" style="display:none">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        </form>
      <?php endforeach; ?>

      <div style="padding:0 var(--space-24)">
      <table class="red-table">
        <thead>
          <tr>
            <th style="width:36%">From (path on this site)</th>
            <th style="width:42%">To (path or absolute URL)</th>
            <th style="width:8%">Status</th>
            <th style="width:14%;text-align:right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($rows) === 0): ?>
            <tr><td colspan="4" style="color:var(--muted);font-style:italic;padding:var(--space-12)">No redirects yet — add one below.</td></tr>
          <?php endif; ?>

          <?php foreach ($rows as $r):
            $rid = 'red-row-' . (int)$r['id'];
            $code = (int)($r['status_code'] ?? 301);
          ?>
            <tr>
              <td><input type="text" name="old_slug" form="<?= $e($rid) ?>" value="<?= $e((string)$r['old_slug']) ?>" required></td>
              <td><input type="text" name="new_slug" form="<?= $e($rid) ?>" value="<?= $e((string)$r['new_slug']) ?>" required></td>
              <td>
                <select name="status_code" form="<?= $e($rid) ?>">
                  <option value="301"<?= $code === 301 ? ' selected' : '' ?>>301</option>
                  <option value="302"<?= $code === 302 ? ' selected' : '' ?>>302</option>
                </select>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <button type="submit" name="action" value="update" form="<?= $e($rid) ?>" class="btn-row-action">Save</button>
                <button type="submit" name="action" value="delete" form="<?= $e($rid) ?>" class="btn-row-del" title="Delete" aria-label="Delete" onclick="return confirm('Delete redirect &quot;<?= $e((string)$r['old_slug']) ?>&quot;?');">
                  <svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>

          <tr class="red-add">
            <form method="post" action="/cms/redirects" id="red-add-form" style="display:none">
              <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
              <input type="hidden" name="action" value="add">
            </form>
            <td><input type="text" name="old_slug" form="red-add-form" placeholder="/old-path" required></td>
            <td><input type="text" name="new_slug" form="red-add-form" placeholder="/new-path or https://example.com/foo" required></td>
            <td>
              <select name="status_code" form="red-add-form">
                <option value="301" selected>301</option>
                <option value="302">302</option>
              </select>
            </td>
            <td style="text-align:right">
              <button type="submit" form="red-add-form" class="btn-row-action">Add</button>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>
  </main>
</div>

</body>
</html>
