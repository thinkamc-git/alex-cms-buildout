<?php
/**
 * cms/views/series-edit.php — edit (or create) a single Series.
 *
 * Routed from site/index.php:
 *   GET  /cms/series/edit?id=N    — render the edit form for series N
 *   GET  /cms/series/edit?id=new  — render the create form (blank shell)
 *   POST /cms/series/edit?id=...  — save / add_part / remove_part / delete
 *
 * POST actions (via $_POST['action']):
 *   save        — upsert name + description (create when id=new)
 *   add_part    — attach an article to this series (same shape as the
 *                 original /cms/series add_part action)
 *   remove_part — detach an article from this series
 *   delete      — hard-delete the series (only when parts_count = 0)
 *
 * Drag-reorder of parts continues to POST to /cms/series/reorder; that
 * endpoint is untouched. Only the JS markup hook changed (now
 * .parts-list / .rowform-row instead of the old .series-parts-dnd /
 * .series-part class pair).
 *
 * Numbering: part numbers are render-time only. Walk parts ordered by
 * series_order ASC and number only published rows (1, 2, 3…). Non-
 * published rows render an empty number cell. No DB mutation needed
 * on publish/unpublish — the next render re-derives the sequence.
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

$idParam = (string)($_GET['id'] ?? '');
$isNew   = ($idParam === 'new');
$id      = $isNew ? 0 : (int)$idParam;

$series = null;
if (!$isNew) {
    if ($id <= 0) {
        header('Location: /cms/series');
        exit;
    }
    $series = get_series($id);
    if ($series === null) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Series not found.\n";
        exit;
    }
}

$errors = [];
$flash  = '';

// Form scratch — populated from the DB row (or blanks on create), then
// overlaid with POST values on a failed save so the author doesn't lose
// what they typed.
$form = [
    'name'        => (string)($series['name']        ?? ''),
    'slug'        => (string)($series['slug']        ?? ''),
    'description' => (string)($series['description'] ?? ''),
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'save');

        if ($action === 'save') {
            $form['name']        = trim((string)($_POST['name']        ?? ''));
            $form['description'] = trim((string)($_POST['description'] ?? ''));
            if ($isNew) {
                $form['slug'] = trim((string)($_POST['slug'] ?? ''));
            }

            if ($isNew) {
                $res = save_series([
                    'name'        => $form['name'],
                    'slug'        => $form['slug'],
                    'description' => $form['description'],
                ]);
                if ($res['ok']) {
                    header('Location: /cms/series/edit?id=' . (int)$res['id']
                        . '&flash=' . rawurlencode('Series created.'));
                    exit;
                }
                $errors[] = $res['error'];
            } else {
                $res = save_series([
                    'id'          => $id,
                    'name'        => $form['name'],
                    'description' => $form['description'],
                ]);
                if ($res['ok']) {
                    header('Location: /cms/series/edit?id=' . $id
                        . '&flash=' . rawurlencode('Saved.'));
                    exit;
                }
                $errors[] = $res['error'];
            }
        } elseif ($action === 'add_part' && !$isNew) {
            $res = add_article_to_series(
                (int)($_POST['article_id'] ?? 0),
                $id
            );
            if ($res['ok']) {
                header('Location: /cms/series/edit?id=' . $id
                    . '&flash=' . rawurlencode('Article added to series.'));
                exit;
            }
            $errors[] = $res['error'];
        } elseif ($action === 'remove_part' && !$isNew) {
            $res = remove_article_from_series((int)($_POST['article_id'] ?? 0));
            if ($res['ok']) {
                header('Location: /cms/series/edit?id=' . $id
                    . '&flash=' . rawurlencode('Article removed from series.'));
                exit;
            }
            $errors[] = $res['error'];
        } elseif ($action === 'delete' && !$isNew) {
            $res = delete_series($id);
            if ($res['ok']) {
                header('Location: /cms/series?flash=' . rawurlencode('Series deleted.'));
                exit;
            }
            $errors[] = $res['error'];
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// Load parts + unassigned articles only for existing series. On the
// create form the Parts block is hidden — parts can only be added once
// the series has an id.
$parts            = [];
$unassignedPicks  = [];
$publishedCount   = 0;
if (!$isNew) {
    $stmt = db()->prepare(
        "SELECT id, slug, title, type, status, published_status, series_id, series_order, published_at
           FROM content
          WHERE series_id = :sid
          ORDER BY series_id ASC, series_order ASC, updated_at DESC"
    );
    $stmt->execute([':sid' => $id]);
    $parts = $stmt->fetchAll();
    $unassignedPicks = list_unassigned_articles();
    foreach ($parts as $p) {
        if ((string)($p['status'] ?? '') === 'published'
            && ((string)($p['published_status'] ?? '') === '' || (string)($p['published_status'] ?? '') === 'live')) {
            $publishedCount++;
        }
    }
}
$partsCount = count($parts);

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . '/../../lib/pills.php';

$pageTitle    = $isNew ? 'New series' : (string)$form['name'];
if ($pageTitle === '') $pageTitle = 'Untitled series';

$slugForUrl   = (string)$form['slug'];
$liveHref     = $slugForUrl !== '' ? ('/series/' . $slugForUrl . '/') : '';
$showLiveBtn  = !$isNew && $publishedCount > 0 && $slugForUrl !== '';
$canDelete    = !$isNew && $partsCount === 0;
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title><?= $e($isNew ? 'New series' : 'Edit: ' . $pageTitle) ?> — alexmchong.ca CMS</title>
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
$breadcrumb = 'Series → ' . ($isNew ? 'New' : 'Edit');
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'series';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-series-edit">
      <?php
      $title    = $pageTitle;
      $subtitle = $isNew
          ? 'Pick a name and slug. You can add parts and edit the description after creating.'
          : '/series/' . $slugForUrl . '/ · Edit name, description, and parts.';
      $actionsHtml  = '<a href="/cms/series" class="btn-sec">← Back to Series</a>';
      if ($showLiveBtn) {
          $actionsHtml .= ' <a href="' . $e($liveHref) . '" target="_blank" rel="noopener" class="btn-sec">Live ↗</a>';
      }
      $actions = $actionsHtml;
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php
        require __DIR__ . '/../partials/flash.php';
        $heading = "Couldn’t save:";
        require __DIR__ . '/../partials/form-errors.php';
        ?>

        <form method="post"
              action="/cms/series/edit?id=<?= $isNew ? 'new' : (int)$id ?>"
              class="cms-form cms-form-wide reveal-page">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <input type="hidden" name="action" value="save">

          <div class="field-group">
            <label class="field-label" for="series-name">Series Name <span class="field-req">required</span></label>
            <input
              type="text"
              class="field-input large"
              id="series-name"
              name="name"
              value="<?= $e($form['name']) ?>"
              maxlength="255"
              required
              <?= $isNew ? 'autofocus' : '' ?>>
          </div>

          <div class="field-group">
            <label class="field-label" for="series-slug">Slug</label>
            <?php if ($isNew): ?>
              <input
                type="text"
                class="field-input"
                id="series-slug"
                name="slug"
                value="<?= $e($form['slug']) ?>"
                maxlength="200"
                pattern="[a-z0-9\-]*"
                placeholder="auto-from-name">
              <p class="field-hint">Lowercase letters, numbers, and hyphens. Becomes part of <code>/series/&lt;slug&gt;/</code> and is permanent once set.</p>
            <?php else: ?>
              <input
                type="text"
                class="field-input"
                id="series-slug"
                value="<?= $e($form['slug']) ?>"
                readonly>
              <p class="field-hint">Permanent — used in the public <code>/series/<?= $e($form['slug']) ?>/</code> URL.</p>
            <?php endif; ?>
          </div>

          <div class="field-group">
            <label class="field-label" for="series-description">Description</label>
            <textarea
              id="series-description"
              class="field-input"
              name="description"
              rows="3"
              maxlength="1000"
              placeholder="One- to two-sentence intro for the series index page."><?= $e($form['description']) ?></textarea>
            <p class="field-hint">Shown on <code>/series/<?= $e($form['slug'] !== '' ? $form['slug'] : '<slug>') ?>/</code> as the intro paragraph.</p>
          </div>

          <?php if (!$isNew): ?>
            <div class="content-block">
              <div class="content-block-header">
                <div>
                  <span class="content-block-label">Parts</span>
                  <span class="content-block-sublabel">Drag to reorder · numbers reflect published order</span>
                </div>
                <span class="content-block-count"><?= (int)$partsCount ?> <?= $partsCount === 1 ? 'part' : 'parts' ?></span>
              </div>

              <div class="rowform-list parts-list series-parts-dnd"
                   data-series-id="<?= (int)$id ?>"
                   data-csrf="<?= $e($csrf_token) ?>"
                   style="--rowform-cols: 18px 40px minmax(0,1fr) auto auto auto">
                <?php
                if ($partsCount === 0) {
                    // Plain centered note inside the list; not a .rowform-row so
                    // it doesn't pick up the grid template. Matches the table
                    // partial's empty-text convention.
                    echo '<div style="text-align:center;color:var(--muted);padding:var(--space-16) var(--space-24);background:var(--canvas-bg);border:1px dashed var(--border);border-radius:4px">'
                       . 'No parts yet — add an article below.'
                       . '</div>';
                }
                // Render-time numbering: walk parts in series_order and
                // assign sequential numbers to PUBLISHED rows only. Non-
                // published rows get an empty number cell.
                $partNum = 0;
                foreach ($parts as $part):
                    $isPublished = ((string)($part['status'] ?? '') === 'published'
                        && ((string)($part['published_status'] ?? '') === ''
                            || (string)($part['published_status'] ?? '') === 'live'));
                    $isScheduled = ((string)($part['status'] ?? '') === 'published'
                        && (string)($part['published_status'] ?? '') === 'scheduled');
                    if ($isPublished) $partNum++;
                    $numHtml = $isPublished
                        ? '<span class="val-pill">' . str_pad((string)$partNum, 2, '0', STR_PAD_LEFT) . '</span>'
                        : '<span></span>';

                    // Date cell: published_at when the row is live or
                    // scheduled, em-dash otherwise. Display in the same
                    // short form the mockup uses.
                    $dateRaw = (string)($part['published_at'] ?? '');
                    $dateOut = $dateRaw !== '' ? date('Y-m-d', strtotime($dateRaw)) : '—';

                    // Status pill — use the canonical helper. Scheduled
                    // gets its own slug so the existing .pill-scheduled
                    // CSS picks it up.
                    $statusForPill = $isScheduled ? 'scheduled' : (string)($part['status'] ?? 'draft');
                    $pillHtml      = cms_pill_stage($statusForPill);

                    $rowClass = 'rowform-row' . ($isPublished ? ' is-published' : '');
                ?>
                  <div class="<?= $rowClass ?>" draggable="true" data-id="<?= (int)$part['id'] ?>">
                    <div class="grip" title="Drag to reorder">⠿</div>
                    <?= $numHtml ?>
                    <div class="part-title"><?= $e((string)$part['title']) ?></div>
                    <span class="part-date"><?= $e($dateOut) ?></span>
                    <?= $pillHtml ?>
                    <form method="post"
                          action="/cms/series/edit?id=<?= (int)$id ?>"
                          class="series-part-remove-form"
                          data-confirm="Remove &quot;<?= $e((string)$part['title']) ?>&quot; from this series? The article stays — only the series link is removed.">
                      <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                      <input type="hidden" name="action" value="remove_part">
                      <input type="hidden" name="article_id" value="<?= (int)$part['id'] ?>">
                      <button type="submit" class="btn-icon btn-icon-danger" title="Remove from series" aria-label="Remove">
                        <svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                      </button>
                    </form>
                  </div>
                <?php endforeach; ?>

                <!-- Add-part row — own form, same POST shape as the
                     original add_part action. Lives inside the rowform-list
                     so it shares the row visual treatment. -->
                <div class="rowform-row rowform-add-row">
                  <span></span>
                  <span></span>
                  <form method="post"
                        action="/cms/series/edit?id=<?= (int)$id ?>"
                        class="series-add-part-form"
                        style="display:contents">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                    <input type="hidden" name="action" value="add_part">
                    <select name="article_id" required class="field-select">
                      <option value="">+ Add article…</option>
                      <?php foreach ($unassignedPicks as $ua):
                        $st = (string)($ua['status'] ?? '');
                      ?>
                        <option value="<?= (int)$ua['id'] ?>"><?= $e((string)$ua['title']) ?> · <?= $e(ucfirst($st)) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <span></span>
                    <span></span>
                    <button type="submit" class="btn-sec btn-tiny" data-add-part-btn>Add</button>
                  </form>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <div class="form-actions form-actions-sticky">
            <?php if ($isNew): ?>
              <button type="submit" name="action" value="save" class="btn-pri">Create series</button>
            <?php else: ?>
              <button type="submit" name="action" value="save" class="btn-sec" data-save-btn>Publish</button>
            <?php endif; ?>
            <a href="/cms/series" class="btn-sec">Cancel</a>
            <?php if (!$isNew && $canDelete): ?>
              <button type="submit"
                      form="series-delete-form"
                      class="btn-sec btn-danger btn-actions-end"
                      data-confirm="Delete series &quot;<?= $e($form['name']) ?>&quot;? This can&#039;t be undone. The articles in it are not deleted.">Delete series</button>
            <?php elseif (!$isNew): ?>
              <span class="btn-actions-end" style="color:var(--muted);font-size:var(--text-micro)" title="Remove all parts first">Cannot delete — series has <?= (int)$partsCount ?> part<?= $partsCount === 1 ? '' : 's' ?></span>
            <?php endif; ?>
          </div>
        </form>

        <?php if (!$isNew && $canDelete): ?>
          <!-- Standalone delete form posted to by the sticky-bar button
               above (via the form= attribute). Kept hidden because the
               main save form would otherwise also catch the click. -->
          <form id="series-delete-form"
                method="post"
                action="/cms/series/edit?id=<?= (int)$id ?>"
                hidden>
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="delete">
          </form>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<?php if (!$isNew && $partsCount > 0): ?>
<script src="/cms/_assets/reorder.js"></script>
<script>
(function () {
  'use strict';
  var csrf = '<?= $e($csrf_token) ?>';

  // Drag-reorder for the parts list. Same DOM hook as the original
  // series view (.series-parts-dnd / data-series-id), so the JS body
  // below is a near-direct port — only the row selector changed from
  // .series-part to .rowform-row[data-id].
  var list = document.querySelector('.series-parts-dnd');
  if (!list) return;

  var seriesId = list.getAttribute('data-series-id');
  if (!seriesId) return;

  function renumber() {
    // Re-derive the published-only number sequence client-side so the
    // user sees the new numbering immediately after a drop, without a
    // page reload. Mirrors the server-side render logic.
    var n = 0;
    list.querySelectorAll('.rowform-row[data-id]').forEach(function (row) {
      var pill = row.querySelector('.val-pill');
      var isPub = row.classList.contains('is-published');
      if (isPub) {
        n++;
        if (pill) pill.textContent = String(n).padStart(2, '0');
      } else if (pill) {
        // Non-published rows shouldn't have a pill at all; if one
        // exists from an earlier render path, clear it.
        pill.textContent = '';
      }
    });
  }

  function persist() {
    var ids = Array.prototype.map.call(
      list.querySelectorAll('.rowform-row[data-id]'),
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

  // Drag mechanics (single drop-line indicator + element move) are handled by
  // the shared CmsReorder helper; this view supplies the persistence, the
  // published-number renumber, and the Publish-button save pulse via onDrop.

  // Narrate the auto-save through the Publish button so the user sees
  // a clear "saving → saved" pulse instead of the previous silent
  // fetch (matches the dirty-flip vocabulary used elsewhere).
  function publishBtn() { return document.querySelector('[data-save-btn]'); }
  function pulseSaving() {
    var btn = publishBtn();
    if (!btn) return;
    btn.dataset.origText = btn.dataset.origText || btn.textContent;
    btn.classList.remove('btn-sec');
    btn.classList.remove('btn-ghost');
    btn.classList.add('btn-pri');
    btn.textContent = 'Saving…';
  }
  function pulseSaved() {
    var btn = publishBtn();
    if (!btn) return;
    btn.textContent = 'Saved';
    setTimeout(function () {
      btn.textContent = btn.dataset.origText || 'Publish';
      btn.classList.remove('btn-pri');
      btn.classList.add('btn-sec');
    }, 1000);
  }
  function pulseError(msg) {
    var btn = publishBtn();
    if (btn) {
      btn.textContent = btn.dataset.origText || 'Publish';
      btn.classList.remove('btn-pri');
      btn.classList.add('btn-sec');
    }
    alert('Reorder failed: ' + msg);
  }

  CmsReorder.wire({
    container: list,
    itemSelector: '.rowform-row[data-id]',
    tailSelector: '.rowform-add-row',
    onDrop: function (info) {
      renumber();
      pulseSaving();
      persist().then(function () {
        pulseSaved();
      }).catch(function (err) {
        info.revert();   // helper restores the pre-drag order
        renumber();
        pulseError(err && err.message ? err.message : 'unknown error');
      });
    }
  });
})();
</script>
<?php endif; ?>

<?php if (!$isNew): ?>
<script>
// Add-part button: promote to .btn-pri once an article is picked. Mirrors
// the same affordance the original series view had on its add-row.
(function () {
  var form = document.querySelector('.series-add-part-form');
  if (!form) return;
  var sel = form.querySelector('select[name="article_id"]');
  var btn = form.querySelector('[data-add-part-btn]');
  if (!sel || !btn) return;
  function sync() {
    var ready = !!sel.value;
    btn.classList.toggle('btn-pri', ready);
    btn.classList.toggle('btn-sec', !ready);
  }
  sel.addEventListener('change', sync);
  sync();
})();
</script>
<?php endif; ?>

<script src="/cms/_assets/scroll-actions.js" defer></script>
<script src="/cms/_assets/dirty-flip.js" defer></script>
<script src="/cms/_assets/confirm.js" defer></script>

</body>
</html>
