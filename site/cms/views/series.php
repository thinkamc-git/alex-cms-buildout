<?php
/**
 * cms/views/series.php — Series admin (Phase 11).
 *
 * Card grid of every series. Each card is one form with two submit
 * buttons (Save / Delete). Slug is permanent — set on creation and
 * never editable here (it's part of /series/[slug]/ public URLs).
 *
 * Phase 12 will iterate over `series` to auto-create matching
 * Editorial Page indexes at /series/[slug]/. Phase 11 just stores
 * the row — no index side-effect.
 *
 * POST actions:
 *   add     → INSERT (name + optional slug + description)
 *   update  → UPDATE name + description for existing id
 *   delete  → DELETE (blocked at the DB layer if parts_count > 0)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();

$errors = [];
$flash  = '';
$showNew = false;  // re-show the +New form on error so the user doesn't lose input
$newDefaults = ['name' => '', 'slug' => '', 'description' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add') {
            $newDefaults = [
                'name'        => (string)($_POST['name']        ?? ''),
                'slug'        => (string)($_POST['slug']        ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
            ];
            $res = save_series($newDefaults);
            if ($res['ok']) {
                $flash = 'Series created.';
            } else {
                $errors[] = $res['error'];
                $showNew = true;
            }
        } elseif ($action === 'update') {
            $res = save_series([
                'id'          => (int)($_POST['id']          ?? 0),
                'name'        => (string)($_POST['name']        ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
            ]);
            $flash = $res['ok'] ? 'Series updated.' : '';
            if (!$res['ok']) $errors[] = $res['error'];
        } elseif ($action === 'delete') {
            $res = delete_series((int)($_POST['id'] ?? 0));
            $flash = $res['ok'] ? 'Series deleted.' : '';
            if (!$res['ok']) $errors[] = $res['error'];
        } elseif ($action === 'add_part') {
            $res = add_article_to_series(
                (int)($_POST['article_id'] ?? 0),
                (int)($_POST['id']         ?? 0)
            );
            $flash = $res['ok'] ? 'Article added to series.' : '';
            if (!$res['ok']) $errors[] = $res['error'];
        } elseif ($action === 'remove_part') {
            $res = remove_article_from_series((int)($_POST['article_id'] ?? 0));
            $flash = $res['ok'] ? 'Article removed from series.' : '';
            if (!$res['ok']) $errors[] = $res['error'];
        } else {
            $errors[] = 'Unknown action.';
        }

        if (count($errors) === 0) {
            header('Location: /cms/series?flash=' . rawurlencode($flash));
            exit;
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// Pull parts list per series so each card shows what's inside without
// the author having to click through. Includes drafts/concepts/etc. so
// the in-progress weight of each series is visible.
$series           = list_series();
$unassignedPicks  = list_unassigned_articles();
$partsByID = [];
if (count($series) > 0) {
    $ids = array_map(static fn($s) => (int)$s['id'], $series);
    // Query is small (single-author CMS); listing all parts in one shot.
    $stmt = db()->prepare(
        "SELECT id, slug, title, type, status, series_id, series_order, published_at
           FROM content
          WHERE series_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
          ORDER BY series_id ASC, series_order ASC, updated_at DESC"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $part) {
        $partsByID[(int)$part['series_id']][] = $part;
    }
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$stagePill = static function (string $status) use ($e): string {
    $status = strtolower($status);
    return '<span class="pill pill-' . $e($status) . '" style="font-size:10px;padding:1px 5px">'
         . ($status === 'published' ? '<span class="live-dot"></span>Live' : $e(ucfirst($status)))
         . '</span>';
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Series — alexmchong.ca CMS</title>
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
</head>
<body>

<?php
$breadcrumb = 'Series';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'series';
  $nav_counts    = ['series' => count($series)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-series">
      <?php
      $title    = 'Series';
      $subtitle = 'Ordered groups of articles. Slugs are permanent — set on creation and used in /series/[slug]/ URLs. Phase 12 generates the matching editorial index page.';
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

      <div class="series-grid">
        <?php foreach ($series as $s):
          $sid       = (int)$s['id'];
          $partsCount = (int)($s['parts_count'] ?? 0);
          $parts     = $partsByID[$sid] ?? [];
          $canDelete = $partsCount === 0;
        ?>
        <div class="series-card">
          <form method="post" action="/cms/series">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="id" value="<?= $sid ?>">

            <div class="series-card-hd">
              <input class="series-card-title cat-input" name="name" value="<?= $e((string)$s['name']) ?>" maxlength="255" required style="flex:1">
              <span class="series-card-count"><?= $partsCount ?> part<?= $partsCount === 1 ? '' : 's' ?></span>
            </div>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:var(--space-12)">
              <span class="val-pill"><?= $e((string)$s['slug']) ?></span>
              <span style="color:var(--muted);font-size:var(--text-micro);font-family:var(--font-mono)">/series/<?= $e((string)$s['slug']) ?>/</span>
              <a href="/series/<?= $e((string)$s['slug']) ?>/" target="_blank" rel="noopener" class="btn-ghost btn-tiny" style="margin-left:auto" title="Open the live series index">Launch ↗</a>
            </div>

            <textarea name="description" rows="2" placeholder="Optional description — shown on the series index page."
                      style="width:100%;padding:8px;font-family:var(--font);font-size:var(--text-meta);color:var(--primary);border:1px solid var(--ink-18);border-radius:4px;background:var(--canvas-raised);resize:vertical;margin-bottom:var(--space-8)"><?= $e((string)($s['description'] ?? '')) ?></textarea>

            <div style="display:flex;justify-content:flex-end;margin-bottom:var(--space-4)">
              <button type="submit" name="action" value="update" class="btn-row-action" style="font-size:11px">Save name &amp; description</button>
            </div>
          </form>

          <?php if (count($parts) > 0): ?>
            <div class="series-parts series-parts-dnd" data-series-id="<?= $sid ?>" style="margin:var(--space-12) 0">
              <?php foreach ($parts as $i => $part):
                $num = (int)($part['series_order'] ?? 0);
                if ($num === 0) $num = $i + 1;
                $date = !empty($part['published_at']) ? date('M j', strtotime((string)$part['published_at'])) : '—';
              ?>
              <div class="series-part" draggable="true" data-id="<?= (int)$part['id'] ?>">
                <div class="part-drag" style="cursor:grab;color:var(--muted);user-select:none;padding-right:2px" title="Drag to reorder">⠿</div>
                <div class="part-num"><?= str_pad((string)$num, 2, '0', STR_PAD_LEFT) ?></div>
                <div class="part-title" style="flex:1"><?= $e((string)$part['title']) ?></div>
                <?= $stagePill((string)$part['status']) ?>
                <span class="part-date" style="color:var(--muted);font-family:var(--font-mono);font-size:10px"><?= $e($date) ?></span>
                <form method="post" action="/cms/series" style="display:inline;margin:0;padding:0" onsubmit="return confirm('Remove &quot;<?= $e((string)$part['title']) ?>&quot; from this series?');">
                  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                  <input type="hidden" name="action" value="remove_part">
                  <input type="hidden" name="article_id" value="<?= (int)$part['id'] ?>">
                  <button type="submit" title="Remove from series" aria-label="Remove" style="background:none;border:none;color:var(--muted);cursor:pointer;padding:0 2px;font-size:14px;line-height:1">×</button>
                </form>
              </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div style="color:var(--muted);font-size:var(--text-meta);font-style:italic;margin:var(--space-12) 0">No parts yet — add an article below.</div>
          <?php endif; ?>

          <form method="post" action="/cms/series" style="display:flex;gap:6px;align-items:center;margin-bottom:var(--space-12)">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="add_part">
            <input type="hidden" name="id" value="<?= $sid ?>">
            <select name="article_id" required style="flex:1;padding:5px 6px;font-family:var(--font);font-size:var(--text-tiny);color:var(--primary);border:1px solid var(--ink-18);border-radius:3px;background:var(--surface)">
              <option value="">+ Add article…</option>
              <?php foreach ($unassignedPicks as $ua):
                $st = (string)($ua['status'] ?? '');
              ?>
                <option value="<?= (int)$ua['id'] ?>"><?= $e((string)$ua['title']) ?> · <?= $e(ucfirst($st)) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-row-action" style="font-size:11px">Add</button>
          </form>

          <form method="post" action="/cms/series" style="display:flex;gap:8px;justify-content:flex-end">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $sid ?>">
            <?php if ($canDelete): ?>
              <button type="submit" class="btn-row-action" style="color:var(--c-terracotta)" onclick="return confirm('Delete series &quot;<?= $e((string)$s['name']) ?>&quot;?');">Delete series</button>
            <?php else: ?>
              <button type="button" class="btn-row-action" disabled title="Cannot delete — unassign all parts first" style="opacity:0.5;cursor:not-allowed">Delete series</button>
            <?php endif; ?>
          </form>
        </div>
        <?php endforeach; ?>

        <!-- New series form rendered as the last card in the grid so the
             grid is always populated and creation lives next to the
             existing cards (matches the dashed "+ New Series" affordance
             in docs/design-mockups/cms-ui.html). -->
        <div class="series-card" id="new-series" style="border-style:dashed">
          <form method="post" action="/cms/series">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="add">

            <div class="series-card-hd">
              <div class="series-card-title" style="color:var(--muted);font-style:italic">+ New series</div>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px">
              <input type="text" name="name" value="<?= $e($newDefaults['name']) ?>" maxlength="255" required placeholder="Series name (required)"
                     style="padding:8px;font-family:var(--font);font-size:var(--text-meta);color:var(--primary);border:1px solid var(--ink-18);border-radius:4px;background:var(--surface)">
              <input type="text" name="slug" value="<?= $e($newDefaults['slug']) ?>" maxlength="200" pattern="[a-z0-9\-]*" placeholder="slug (optional — auto from name)"
                     style="padding:8px;font-family:var(--font-mono);font-size:var(--text-micro);color:var(--secondary);border:1px solid var(--ink-18);border-radius:4px;background:var(--surface)">
              <textarea name="description" rows="2" placeholder="Optional description"
                        style="padding:8px;font-family:var(--font);font-size:var(--text-meta);color:var(--primary);border:1px solid var(--ink-18);border-radius:4px;background:var(--surface);resize:vertical"><?= $e($newDefaults['description']) ?></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;margin-top:var(--space-12)">
              <button type="submit" class="btn-pri">Create series</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
(function () {
  'use strict';
  var csrf = '<?= $e($csrf_token) ?>';

  // Each .series-parts-dnd container is its own independent list. Re-init
  // per container so dragging within one card never crosses into another.
  document.querySelectorAll('.series-parts-dnd').forEach(function (list) {
    var seriesId = list.getAttribute('data-series-id');
    if (!seriesId) return;

    function snapshot() {
      return Array.prototype.slice.call(list.querySelectorAll('.series-part'));
    }
    var prevOrder = snapshot();

    function renumber() {
      // Rewrite the visible "01 / 02 / 03…" labels client-side after a
      // successful drop so the user sees the new numbering immediately.
      list.querySelectorAll('.series-part').forEach(function (el, i) {
        var num = el.querySelector('.part-num');
        if (num) num.textContent = String(i + 1).padStart(2, '0');
      });
    }

    function persist() {
      var ids = Array.prototype.map.call(
        list.querySelectorAll('.series-part'),
        function (el) { return el.getAttribute('data-id'); }
      );
      var body = new FormData();
      body.append('csrf_token', csrf);
      body.append('series_id', seriesId);
      ids.forEach(function (id) { body.append('article_ids[]', id); });
      return fetch('/cms/series/reorder', {
        method: 'POST',
        body: body,
        credentials: 'same-origin'
      }).then(function (r) {
        return r.json().then(function (j) {
          if (!r.ok || !j.ok) throw new Error(j.error || ('HTTP ' + r.status));
          return j;
        });
      });
    }

    list.addEventListener('dragstart', function (e) {
      var part = e.target.closest('.series-part');
      if (!part) return;
      prevOrder = snapshot();
      part.classList.add('dragging');
      if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
    });

    list.addEventListener('dragend', function (e) {
      var part = e.target.closest('.series-part');
      if (part) part.classList.remove('dragging');
    });

    list.addEventListener('dragover', function (e) {
      e.preventDefault();
      var dragging = list.querySelector('.series-part.dragging');
      if (!dragging) return;

      // Find the closest sibling under the cursor and insert before it
      // (or at the end if past the last sibling). Standard pattern.
      var siblings = Array.prototype.slice.call(
        list.querySelectorAll('.series-part:not(.dragging)')
      );
      var after = siblings.find(function (sib) {
        var box = sib.getBoundingClientRect();
        return e.clientY < box.top + box.height / 2;
      });
      if (after) {
        list.insertBefore(dragging, after);
      } else {
        list.appendChild(dragging);
      }
    });

    list.addEventListener('drop', function (e) {
      e.preventDefault();
      renumber();
      persist().catch(function (err) {
        // Revert DOM on failure so the displayed order matches the DB.
        prevOrder.forEach(function (el) { list.appendChild(el); });
        renumber();
        alert('Reorder failed: ' + (err && err.message ? err.message : 'unknown error'));
      });
    });
  });
})();
</script>

</body>
</html>
