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
  /* Row-form pattern — same visual family as /cms/navigation. Each
     redirect is its own bordered box with grid-aligned columns. No
     drag-handle (redirects don't reorder). */
  .red-list { display:flex; flex-direction:column; gap:6px; }
  .red-row {
    display:grid;
    grid-template-columns:
      minmax(220px, 1.4fr)   /* from         */
      minmax(240px, 1.6fr)   /* to           */
      90px                   /* status       */
      auto                   /* save         */
      auto;                  /* delete       */
    gap:8px;
    align-items:center;
    padding:8px 10px;
    background:var(--surface);
    border:1px solid var(--border);
    border-radius:4px;
  }
  .red-row > form { display:contents; }
  .red-row input[type=text], .red-row select {
    padding:5px 7px; border:1px solid var(--border); border-radius:3px;
    font-size:12px; background:var(--surface); color:var(--ink);
    font-family:var(--font-mono); min-width:0; width:100%; box-sizing:border-box;
  }
  /* Save: hidden until dirty (universal CMS pattern). */
  .red-row [data-save-btn] { visibility:hidden; }
  .red-row [data-save-btn].btn-pri { visibility:visible; }
  /* Delete: only on row-hover so it doesn't always shout. */
  .red-row .btn-danger { visibility:hidden; }
  .red-row:hover .btn-danger,
  .red-row:focus-within .btn-danger { visibility:visible; }
  .red-add-row { background:var(--bg-soft); }
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

      <div class="content-area">
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Redirects</span>
              <span class="content-block-sublabel">From-path → to-path · 301 permanent, 302 temporary</span>
            </div>
            <span class="content-block-count"><?= count($rows) ?> redirect<?= count($rows)===1?'':'s' ?></span>
          </div>

          <div class="red-list">
            <?php foreach ($rows as $r): $code = (int)($r['status_code'] ?? 301); ?>
              <div class="red-row">
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
                <form method="post" action="/cms/redirects" style="display:inline" onsubmit="return confirm('Delete redirect &quot;<?= $e((string)$r['old_slug']) ?>&quot;?');">
                  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn-ghost btn-tiny btn-danger">Delete</button>
                </form>
              </div>
            <?php endforeach; ?>

            <!-- Add new redirect -->
            <div class="red-row red-add-row">
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

<script>
  // Universal Save-button dirty-flip pattern (mirrors /cms/navigation +
  // /cms/pages/edit). Each row's Save button starts hidden; first edit in
  // the row makes it visible + primary. Click → "Saved" → submit.
  document.querySelectorAll('.red-table tbody tr').forEach(tr => {
    const inputs = tr.querySelectorAll('input[type=text], select');
    const saveBtn = tr.querySelector('[data-save-btn]');
    if (!saveBtn || inputs.length === 0) return;
    inputs.forEach(el => {
      const evt = el.tagName === 'SELECT' ? 'change' : 'input';
      el.addEventListener(evt, () => {
        saveBtn.classList.remove('btn-ghost');
        saveBtn.classList.add('btn-pri');
      });
    });
    saveBtn.addEventListener('click', (e) => {
      if (!saveBtn.classList.contains('btn-pri')) return;
      e.preventDefault();
      saveBtn.textContent = 'Saved';
      saveBtn.disabled = true;
      const form = document.getElementById(saveBtn.getAttribute('form'));
      if (!form) return;
      // Programmatic submit() doesn't carry the submit button's
      // name/value, so inject the action explicitly.
      if (!form.querySelector('input[name=action][data-injected]')) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'action';
        inp.value = 'update';
        inp.setAttribute('data-injected', '');
        form.appendChild(inp);
      }
      setTimeout(() => form.submit(), 300);
    });
  });
</script>

</body>
</html>
