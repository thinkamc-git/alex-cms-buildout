<?php
/**
 * cms/views/index-edit.php — Editorial Index builder (Phase 12).
 *
 * Single view that handles both layouts via conditional sections. The
 * layout choice is editable post-create: flipping from listing → editorial
 * reveals hero/featured controls (which start empty); flipping back hides
 * them but preserves the underlying values until next save. Slug stays
 * permanent (it's the URL contract).
 *
 * For v1 the "+ Add Section" extension from CMS-STRUCTURE.md §16 is not
 * shipped — that lives in a future polish phase. The brief documents
 * this as the one piece deferred to keep Phase 12 in its calibrated
 * window.
 *
 * POST is a single save: builds the $data array, calls save_index(),
 * redirects on success.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/indexes.php';
require_once __DIR__ . '/../../lib/content.php';

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$index = $id > 0 ? get_index($id) : null;
if ($index === null) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Index not found.";
    exit;
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        // featured_ids arrives as a comma-separated string from the hidden
        // input that the drag-drop JS keeps in sync.
        $featuredRaw = (string)($_POST['featured_ids'] ?? '');
        $featuredArr = [];
        foreach (explode(',', $featuredRaw) as $piece) {
            $n = (int)trim($piece);
            if ($n > 0) $featuredArr[] = $n;
        }

        $typesArr = $_POST['feed_types'] ?? [];
        if (!is_array($typesArr)) $typesArr = [];

        $data = [
            'id'              => $id,
            'layout'          => (string)($_POST['layout']   ?? 'listing'),
            'title'           => (string)($_POST['title']    ?? ''),
            'subtitle'        => (string)($_POST['subtitle'] ?? ''),
            'show_title'      => !empty($_POST['show_title']),
            'hero_content_id' => (int)($_POST['hero_content_id'] ?? 0),
            'featured_ids'    => $featuredArr,
            'feed_types'      => $typesArr,
            'feed_sort'       => (string)($_POST['feed_sort']       ?? 'newest'),
            'feed_rows_shown' => (string)($_POST['feed_rows_shown'] ?? 'all'),
            'filter_mode'     => (string)($_POST['filter_mode']     ?? 'categories'),
        ];

        $res = save_index($data);
        if ($res['ok']) {
            header('Location: /cms/indexes/edit?id=' . $id . '&flash=' . rawurlencode('Saved.'));
            exit;
        }
        $errors[] = $res['error'];
        // Re-load to merge user input with current row (so dropdowns
        // stay populated correctly). Take the user's POST values for
        // edited fields, but keep $index for everything else.
        $index = array_merge($index, [
            'layout'          => $data['layout'],
            'title'           => $data['title'],
            'subtitle'        => $data['subtitle'],
            'show_title'      => $data['show_title'] ? 1 : 0,
            'hero_content_id' => $data['hero_content_id'],
            'featured_ids'    => json_encode($data['featured_ids']),
            'feed_types'      => json_encode($data['feed_types']),
            'feed_sort'       => $data['feed_sort'],
            'feed_rows_shown' => $data['feed_rows_shown'],
            'filter_mode'     => $data['filter_mode'],
        ]);
    }
}

$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

// Build the picklist for hero/featured: every published content row,
// across all types, newest first. Used by both the hero <select> and
// the featured-add dropdown. Single query covers both.
$pickList = list_index_feed([
    'feed_types'       => null,           // all types
    'feed_sort'        => 'newest',
    'feed_rows_shown'  => 'all',
]);

// Decode JSON columns for display.
$featuredIds = $index['featured_ids'] ?? null;
if (is_string($featuredIds)) {
    $decoded = json_decode($featuredIds, true);
    $featuredIds = is_array($decoded) ? $decoded : [];
}
if (!is_array($featuredIds)) $featuredIds = [];
$featuredIds = array_map('intval', $featuredIds);

$feedTypes = $index['feed_types'] ?? null;
if (is_string($feedTypes)) {
    $decoded = json_decode($feedTypes, true);
    $feedTypes = is_array($decoded) ? $decoded : [];
}
if (!is_array($feedTypes)) $feedTypes = [];

// Pickup-ordered map for featured rendering — preserve the saved order.
$pickById = [];
foreach ($pickList as $p) $pickById[(int)$p['id']] = $p;

$layout      = (string)$index['layout'];
$isEditorial = $layout === 'editorial';
$heroId      = (int)($index['hero_content_id'] ?? 0);
$showTitle   = !empty($index['show_title']);
$sort        = (string)($index['feed_sort']       ?? 'newest');
$rowsShown   = (string)($index['feed_rows_shown'] ?? 'all');
$filterMode  = (string)($index['filter_mode']     ?? 'categories');

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$typeLabels = [
    'article'      => 'Articles',
    'journal'      => 'Journals',
    'live-session' => 'Live Sessions',
    'experiment'   => 'Experiments',
];

$pickLabel = static function (array $row) use ($e): string {
    $type   = (string)($row['type']  ?? '');
    $title  = (string)($row['title'] ?? '(untitled)');
    $pub    = (string)($row['published_at'] ?? '');
    $date   = $pub !== '' ? date('Y-m-d', strtotime($pub)) : '';
    $tlabel = ucfirst(str_replace('-', ' ', $type));
    return $e($title) . ' — ' . $e($tlabel) . ($date !== '' ? ' · ' . $e($date) : '');
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= $e('/' . $index['slug'] . '/ — Edit Index') ?></title>
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
$breadcrumb = 'Indexes / ' . (string)$index['slug'];
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'indexes';
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-index-edit">
      <?php
      $title    = '/' . (string)$index['slug'] . '/';
      $subtitle = $isEditorial
          ? 'Editorial Page — configure the hero feature, curated picks, and content feed for this page.'
          : 'Basic Listing — title, optional description, and a content feed.';
      $actions  = '<a href="/' . $e((string)$index['slug']) . '/" target="_blank" rel="noopener" class="btn-ghost">View</a>'
                . '<a href="/cms/indexes" class="btn-ghost">All indexes</a>';
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

      <form method="post" action="/cms/indexes/edit" class="content-area" id="index-edit-form">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="featured_ids" id="featured-ids-input" value="<?= $e(implode(',', $featuredIds)) ?>">

        <!-- Layout switcher -->
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Layout</span>
              <span class="content-block-sublabel">Flip between the two layout types. Hero + featured config is preserved when switched off.</span>
            </div>
          </div>
          <div style="padding:var(--space-16) var(--space-20);display:flex;gap:8px">
            <label style="flex:1;display:block;padding:14px;border:1px solid var(--ink-18);border-radius:4px;cursor:pointer">
              <input type="radio" name="layout" value="listing" <?= !$isEditorial ? 'checked' : '' ?> style="margin-right:8px">
              <strong>Basic Listing</strong>
              <p class="field-hint" style="margin:6px 0 0">Title + content feed.</p>
            </label>
            <label style="flex:1;display:block;padding:14px;border:1px solid var(--ink-18);border-radius:4px;cursor:pointer">
              <input type="radio" name="layout" value="editorial" <?= $isEditorial ? 'checked' : '' ?> style="margin-right:8px">
              <strong>Editorial Page</strong>
              <p class="field-hint" style="margin:6px 0 0">Hero + featured + feed.</p>
            </label>
          </div>
        </div>

        <!-- Page Title -->
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Page title</span>
              <span class="content-block-sublabel">Shown at the top of the index page.</span>
            </div>
            <?php if ($isEditorial): ?>
              <label style="display:flex;align-items:center;gap:6px;font-size:var(--text-meta);color:var(--secondary)">
                <input type="checkbox" name="show_title" value="1" <?= $showTitle ? 'checked' : '' ?>> Show title
              </label>
            <?php else: ?>
              <input type="hidden" name="show_title" value="1">
              <span style="color:var(--muted);font-size:var(--text-micro)">Always shown on Basic Listing</span>
            <?php endif; ?>
          </div>
          <div style="padding:var(--space-16) var(--space-20)">
            <div class="field-group">
              <label class="field-label" for="title-input">Title</label>
              <input id="title-input" type="text" name="title" value="<?= $e((string)($index['title'] ?? '')) ?>" maxlength="500"
                     class="field-input" style="width:100%">
            </div>
            <div class="field-group" style="margin-bottom:0">
              <label class="field-label" for="subtitle-input">Subtitle / description <span style="font-weight:400;text-transform:none;color:var(--muted);font-size:var(--text-micro);font-family:var(--font)">optional</span></label>
              <input id="subtitle-input" type="text" name="subtitle" value="<?= $e((string)($index['subtitle'] ?? '')) ?>" maxlength="500"
                     class="field-input" style="width:100%">
            </div>
          </div>
        </div>

        <!-- Hero Feature (editorial only) -->
        <div class="content-block" id="block-hero" style="<?= $isEditorial ? '' : 'display:none' ?>">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Hero feature</span>
              <span class="content-block-sublabel">One published item to anchor the top of the page.</span>
            </div>
          </div>
          <div style="padding:var(--space-16) var(--space-20)">
            <select name="hero_content_id" class="field-input" style="width:100%;max-width:600px">
              <option value="0">— None —</option>
              <?php foreach ($pickList as $row): ?>
                <option value="<?= (int)$row['id'] ?>" <?= (int)$row['id'] === $heroId ? 'selected' : '' ?>><?= $pickLabel($row) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Featured Articles (editorial only) -->
        <div class="content-block" id="block-featured" style="<?= $isEditorial ? '' : 'display:none' ?>">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Featured</span>
              <span class="content-block-sublabel">Curated picks shown above the feed. Drag to reorder.</span>
            </div>
          </div>
          <div style="padding:var(--space-16) var(--space-20)">
            <div id="featured-list" class="series-parts series-parts-dnd" style="margin-bottom:var(--space-12)">
              <?php foreach ($featuredIds as $fid):
                $r = $pickById[$fid] ?? null;
                if ($r === null) continue;
              ?>
                <div class="series-part" draggable="true" data-id="<?= (int)$r['id'] ?>">
                  <div class="part-drag" style="cursor:grab;color:var(--muted);user-select:none;padding-right:2px" title="Drag to reorder">⠿</div>
                  <div class="part-title" style="flex:1"><?= $pickLabel($r) ?></div>
                  <button type="button" class="featured-remove" title="Remove" aria-label="Remove" style="background:none;border:none;color:var(--muted);cursor:pointer;padding:0 4px;font-size:14px;line-height:1">×</button>
                </div>
              <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:6px;align-items:center">
              <select id="featured-add" class="field-input" style="flex:1">
                <option value="">+ Add to featured…</option>
                <?php foreach ($pickList as $row): ?>
                  <option value="<?= (int)$row['id'] ?>" data-label="<?= $pickLabel($row) ?>"><?= $pickLabel($row) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" id="featured-add-btn" class="btn-row-action" style="font-size:11px">Add</button>
            </div>
          </div>
        </div>

        <!-- Content Feed -->
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Content feed</span>
              <span class="content-block-sublabel">The main grid of cards. Type chips are OR — pick any combination. Empty = all types.</span>
            </div>
          </div>
          <div style="padding:var(--space-16) var(--space-20)">
            <div class="field-group">
              <label class="field-label">Types</label>
              <div style="display:flex;flex-wrap:wrap;gap:6px">
                <?php foreach ($typeLabels as $slug => $label):
                  $on = in_array($slug, $feedTypes, true);
                ?>
                  <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--ink-18);border-radius:999px;cursor:pointer;background:<?= $on ? 'var(--primary)' : 'var(--surface)' ?>;color:<?= $on ? 'var(--white)' : 'var(--primary)' ?>">
                    <input type="checkbox" name="feed_types[]" value="<?= $e($slug) ?>" <?= $on ? 'checked' : '' ?> style="margin:0">
                    <?= $e($label) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="field-group">
              <label class="field-label">Sort</label>
              <div style="display:flex;gap:6px">
                <?php foreach ([
                    'newest' => 'Newest first',
                    'oldest' => 'Oldest first',
                ] as $val => $label):
                  $on = $sort === $val;
                ?>
                  <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--ink-18);border-radius:999px;cursor:pointer;background:<?= $on ? 'var(--primary)' : 'var(--surface)' ?>;color:<?= $on ? 'var(--white)' : 'var(--primary)' ?>">
                    <input type="radio" name="feed_sort" value="<?= $e($val) ?>" <?= $on ? 'checked' : '' ?> style="margin:0">
                    <?= $e($label) ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="field-hint">Manual sort is reserved for a later phase — choose Newest or Oldest for now.</p>
            </div>

            <div class="field-group">
              <label class="field-label">Rows shown</label>
              <div style="display:flex;gap:6px">
                <?php foreach (['1', '2', '3', '4', 'all'] as $val):
                  $on = $rowsShown === $val;
                ?>
                  <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border:1px solid var(--ink-18);border-radius:4px;cursor:pointer;background:<?= $on ? 'var(--primary)' : 'var(--surface)' ?>;color:<?= $on ? 'var(--white)' : 'var(--primary)' ?>">
                    <input type="radio" name="feed_rows_shown" value="<?= $e($val) ?>" <?= $on ? 'checked' : '' ?> style="display:none">
                    <?= $e($val === 'all' ? 'All' : $val) ?>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="field-hint">One row ≈ 4 cards. Beyond the cap, items are simply hidden.</p>
            </div>

            <div class="field-group" style="margin-bottom:0">
              <label class="field-label">Filter pills</label>
              <div style="display:flex;gap:6px">
                <?php foreach ([
                    'categories' => ['Categories', 'One pill per category appearing in the feed.'],
                    'types'      => ['Content types', 'One pill per feed type (Articles · Journals · Talks · Experiments).'],
                    'none'       => ['None', 'No filter row.'],
                ] as $val => $meta):
                  $on = $filterMode === $val;
                ?>
                  <label style="flex:1;display:block;padding:10px 12px;border:1px solid var(--ink-18);border-radius:4px;cursor:pointer;background:<?= $on ? 'var(--canvas-raised)' : 'var(--surface)' ?>">
                    <input type="radio" name="filter_mode" value="<?= $e($val) ?>" <?= $on ? 'checked' : '' ?> style="margin-right:6px">
                    <strong><?= $e($meta[0]) ?></strong>
                    <span style="display:block;color:var(--muted);font-size:var(--text-micro);margin-top:4px"><?= $e($meta[1]) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="field-hint">Filtering is client-side — pills hide/show cards without reloading. The "All" pill is rendered automatically.</p>
            </div>
          </div>
        </div>

        <div class="content-block" style="background:transparent;border:none;padding:0">
          <div style="display:flex;gap:8px;justify-content:flex-end;padding:var(--space-16) var(--space-20)">
            <a href="/cms/indexes" class="btn-ghost">Cancel</a>
            <button type="submit" class="btn-pri">Save</button>
          </div>
        </div>
      </form>
    </div>
  </main>
</div>

<script>
(function () {
  'use strict';

  // Layout switch: show/hide editorial-only blocks.
  var layoutRadios = document.querySelectorAll('input[name="layout"]');
  var blockHero    = document.getElementById('block-hero');
  var blockFeat    = document.getElementById('block-featured');
  function syncLayout() {
    var v = document.querySelector('input[name="layout"]:checked');
    var isEd = v && v.value === 'editorial';
    if (blockHero) blockHero.style.display = isEd ? '' : 'none';
    if (blockFeat) blockFeat.style.display = isEd ? '' : 'none';
  }
  layoutRadios.forEach(function (r) { r.addEventListener('change', syncLayout); });

  // Featured list: drag-reorder + remove + add.
  var list   = document.getElementById('featured-list');
  var hidden = document.getElementById('featured-ids-input');
  var addSel = document.getElementById('featured-add');
  var addBtn = document.getElementById('featured-add-btn');

  function serialize() {
    if (!list || !hidden) return;
    var ids = [];
    list.querySelectorAll('.series-part').forEach(function (el) {
      var id = el.getAttribute('data-id');
      if (id) ids.push(id);
    });
    hidden.value = ids.join(',');
  }

  // Drag-drop on .featured-list, mirroring series.php pattern but local
  // (no AJAX persist — the hidden input is rewritten and submitted with
  // the form on Save).
  if (list) {
    var dragged = null;
    list.addEventListener('dragstart', function (e) {
      var el = e.target.closest('.series-part');
      if (!el) return;
      dragged = el;
      el.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragend', function (e) {
      if (dragged) dragged.classList.remove('dragging');
      dragged = null;
      serialize();
    });
    list.addEventListener('dragover', function (e) {
      if (!dragged) return;
      e.preventDefault();
      var after = null;
      var els = Array.prototype.slice.call(list.querySelectorAll('.series-part:not(.dragging)'));
      for (var i = 0; i < els.length; i++) {
        var box = els[i].getBoundingClientRect();
        if (e.clientY < box.top + box.height / 2) { after = els[i]; break; }
      }
      if (after === null) list.appendChild(dragged);
      else list.insertBefore(dragged, after);
    });

    list.addEventListener('click', function (e) {
      var btn = e.target.closest('.featured-remove');
      if (!btn) return;
      var item = btn.closest('.series-part');
      if (item) item.parentNode.removeChild(item);
      serialize();
    });
  }

  if (addBtn && addSel && list) {
    addBtn.addEventListener('click', function () {
      var id = addSel.value;
      if (!id || id === '0') return;
      // Prevent duplicates.
      if (list.querySelector('.series-part[data-id="' + id + '"]')) {
        addSel.value = '';
        return;
      }
      var opt = addSel.options[addSel.selectedIndex];
      var label = opt ? (opt.getAttribute('data-label') || opt.textContent) : id;
      var row = document.createElement('div');
      row.className = 'series-part';
      row.draggable = true;
      row.setAttribute('data-id', id);
      row.innerHTML = '<div class="part-drag" style="cursor:grab;color:var(--muted);user-select:none;padding-right:2px" title="Drag to reorder">⠿</div>'
                    + '<div class="part-title" style="flex:1"></div>'
                    + '<button type="button" class="featured-remove" title="Remove" aria-label="Remove" style="background:none;border:none;color:var(--muted);cursor:pointer;padding:0 4px;font-size:14px;line-height:1">×</button>';
      row.querySelector('.part-title').textContent = label;
      list.appendChild(row);
      addSel.value = '';
      serialize();
    });
  }

  // Type/sort chip visuals — flip the background when the underlying
  // input changes (the form is submitted directly, no JS state).
  document.querySelectorAll('input[name="feed_types[]"]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var lbl = cb.closest('label');
      if (!lbl) return;
      if (cb.checked) { lbl.style.background = 'var(--primary)'; lbl.style.color = 'var(--white)'; }
      else             { lbl.style.background = 'var(--surface)'; lbl.style.color = 'var(--primary)'; }
    });
  });
  document.querySelectorAll('input[name="feed_sort"], input[name="feed_rows_shown"]').forEach(function (rb) {
    rb.addEventListener('change', function () {
      var group = rb.name;
      document.querySelectorAll('input[name="' + group + '"]').forEach(function (sibling) {
        var lbl = sibling.closest('label');
        if (!lbl) return;
        if (sibling.checked) { lbl.style.background = 'var(--primary)'; lbl.style.color = 'var(--white)'; }
        else                 { lbl.style.background = 'var(--surface)'; lbl.style.color = 'var(--primary)'; }
      });
    });
  });
})();
</script>

</body>
</html>
