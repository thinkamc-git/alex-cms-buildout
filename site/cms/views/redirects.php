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
                $errors[] = 'Could not add. Check for blank fields, a duplicate old-path, or an old-path that equals the new-path (which would loop).';
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
  /* Redirects row-form — shared mechanics (grid container, dirty-flip,
     hover-reveal delete, add-row tint) live in style-cms.css under
     .rowform-*. View-specific bits: the 5-column grid template. No drag
     handle — redirects don't reorder. */
  .red-list {
    --rowform-cols:
      minmax(220px, 1.4fr)   /* from   */
      minmax(240px, 1.6fr)   /* to     */
      90px                   /* status */
      auto                   /* save   */
      auto;                  /* delete */
  }
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
      $subtitle = 'Map an old URL to a new one. 301 is the default (permanent — browsers cache it). Use 302 when the destination might move — third-party links, A/B tests, anything not yet stable.';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $heading = "Couldn't save:";
      require __DIR__ . '/../partials/form-errors.php';
      require __DIR__ . '/../partials/flash.php';
      ?>

      <div class="content-area">
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Redirects</span>
              <span class="content-block-sublabel">Map old paths to new ones · 301 permanent, 302 temporary</span>
            </div>
            <span class="content-block-count"><?= count($rows) ?> redirect<?= count($rows)===1?'':'s' ?></span>
          </div>

          <div class="rowform-list red-list">
            <?php foreach ($rows as $r): $code = (int)($r['status_code'] ?? 301); ?>
              <div class="rowform-row red-row">
                <form method="post" action="/cms/redirects" style="display:contents">
                  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="text" name="old_slug" value="<?= $e((string)$r['old_slug']) ?>" placeholder="/old-path" required>
                  <input type="text" name="new_slug" value="<?= $e((string)$r['new_slug']) ?>" placeholder="/new-path or https://…" required>
                  <select name="status_code">
                    <option value="301"<?= $code === 301 ? ' selected' : '' ?>>301</option>
                    <option value="302"<?= $code === 302 ? ' selected' : '' ?>>302</option>
                  </select>
                  <button type="submit" class="btn-ghost btn-tiny" data-save-btn>Save</button>
                </form>
                <form method="post" action="/cms/redirects" style="display:inline" onsubmit="return confirm('Delete redirect &quot;<?= $e((string)$r['old_slug']) ?>&quot;? Anyone hitting that URL will get a 404 unless you re-add it.');">
                  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn-ghost btn-tiny btn-danger">Delete</button>
                </form>
              </div>
            <?php endforeach; ?>

            <!-- Add new redirect -->
            <div class="rowform-row rowform-add-row red-row red-add-row">
              <form method="post" action="/cms/redirects" style="display:contents">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                <input type="hidden" name="action" value="add">
                <input type="text" name="old_slug" placeholder="/old-path" required>
                <input type="text" name="new_slug" placeholder="/new-path or https://example.com/foo" required>
                <select name="status_code">
                  <option value="301" selected>301</option>
                  <option value="302">302</option>
                </select>
                <button type="submit" class="btn-ghost btn-tiny">Add</button>
                <span></span><!-- delete-column placeholder so grid stays aligned -->
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Universal Save-button dirty-flip pattern (mirrors /cms/navigation +
     /cms/pages/edit) lives in the shared cms/_assets/dirty-flip.js module.
     Each row's Save button binds via form="row-N"; the module reads that
     attribute and injects the button's name="action" value as a hidden
     input before programmatic submit. -->
<script src="/cms/_assets/dirty-flip.js" defer></script>

</body>
</html>
