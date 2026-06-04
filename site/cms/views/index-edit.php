<?php
/**
 * cms/views/index-edit.php — Index editor.
 *
 * Two layouts:
 *   - Editorial Page — composed of typed sections (Hero / Curated / Feed)
 *     stored in the index_sections table. Skeleton in this iteration:
 *     section list renders as collapsed .sec-card rows showing type +
 *     title + delete + expand chevron. Per-type editor bodies land in
 *     the next iteration.
 *   - Basic Listing — single filtered feed using the flat hero_/feed_/
 *     filter_mode columns on the indexes row. Form unchanged.
 *
 * Slug is permanent (URL contract).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/indexes.php';
require_once __DIR__ . '/../../lib/content.php';

Auth::require_login();
$csrf_token = Csrf::token();

$id    = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$index = $id > 0 ? get_index($id) : null;
if ($index === null) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo "Index not found.";
    exit;
}

$errors = [];

// POST — save the index row. Section CRUD will be wired in a follow-up
// iteration of Phase 21.7 once the skeleton renders cleanly.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $featuredRaw = (string)($_POST['featured_ids'] ?? '');
        $featuredArr = [];
        foreach (explode(',', $featuredRaw) as $piece) {
            $n = (int)trim($piece);
            if ($n > 0) $featuredArr[] = $n;
        }
        $typesArr = $_POST['feed_types'] ?? [];
        if (!is_array($typesArr)) $typesArr = [];

        $newLayout = (string)($_POST['layout'] ?? $index['layout']);
        if (!in_array($newLayout, INDEX_LAYOUTS, true)) $newLayout = 'listing';

        $data = [
            'id'              => $id,
            'layout'          => $newLayout,
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
        try {
            $res = save_index($data);
            if ($res['ok']) {
                header('Location: /cms/indexes/edit?id=' . $id . '&flash=' . rawurlencode('Saved.'));
                exit;
            }
            $errors[] = $res['error'];
        } catch (\Throwable $ex) {
            error_log('[index-edit] save_index threw: ' . $ex->getMessage());
            $errors[] = 'Could not save index: ' . $ex->getMessage();
        }
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

// Picklist for the legacy hero/featured selectors (Basic Listing).
$pickList = list_index_feed([
    'feed_types'      => null,
    'feed_sort'       => 'newest',
    'feed_rows_shown' => 'all',
]);
$pickById = [];
foreach ($pickList as $p) $pickById[(int)$p['id']] = $p;

// Decode JSON columns for the Basic Listing form.
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

$layout      = (string)$index['layout'];
$isEditorial = $layout === 'editorial';
$heroId      = (int)($index['hero_content_id'] ?? 0);
$showTitle   = !empty($index['show_title']);
$sort        = (string)($index['feed_sort']       ?? 'newest');
$rowsShown   = (string)($index['feed_rows_shown'] ?? 'all');
$filterMode  = (string)($index['filter_mode']     ?? 'categories');

// Section stack (Editorial only).
$sections = $isEditorial ? list_index_sections($id) : [];

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
    $title  = (string)($row['title'] ?? '(untitled)');
    $type   = (string)($row['type']  ?? '');
    $pub    = (string)($row['published_at'] ?? '');
    $date   = $pub !== '' ? date('Y-m-d', strtotime($pub)) : '';
    $tlabel = ucfirst(str_replace('-', ' ', $type));
    return $e($title) . ' — ' . $e($tlabel) . ($date !== '' ? ' · ' . $e($date) : '');
};

// UI labels for section types. The 'feed' DB value renders as "Filtered"
// in the UI per current preference.
$secTypeLabel = static function (string $t): string {
    if ($t === 'hero')    return 'Hero';
    if ($t === 'curated') return 'Curated';
    if ($t === 'feed')    return 'Filtered';
    return ucfirst($t);
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
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

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-index-edit">
      <?php
      $title    = '/' . (string)$index['slug'] . '/';
      $subtitle = $isEditorial
          ? 'Editorial Page'
          : 'Basic Listing';
      $actions  = '<a href="/' . $e((string)$index['slug']) . '/" target="_blank" rel="noopener" class="btn-sec">View</a>'
                . '<a href="/cms/indexes" class="btn-sec">All indexes</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $heading = "Couldn't save:";
      require __DIR__ . '/../partials/form-errors.php';
      require __DIR__ . '/../partials/flash.php';
      ?>

      <form method="post" action="/cms/indexes/edit" class="content-area" id="index-edit-form">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="layout" id="layout-input" value="<?= $e($layout) ?>">
        <input type="hidden" name="featured_ids" id="featured-ids-input" value="<?= $e(implode(',', $featuredIds)) ?>">

        <div class="field-group">
          <label class="field-label" for="title-input">Title</label>
          <input id="title-input" type="text" name="title" class="field-input" value="<?= $e((string)($index['title'] ?? '')) ?>" maxlength="500">
        </div>

        <div class="field-group">
          <label class="field-label" for="subtitle-input">Subtitle</label>
          <input id="subtitle-input" type="text" name="subtitle" class="field-input" value="<?= $e((string)($index['subtitle'] ?? '')) ?>" maxlength="500">
        </div>

        <div class="field-group">
          <label class="field-label">
            <input type="checkbox" name="show_title" value="1" <?= $showTitle ? 'checked' : '' ?>>
            Show title
          </label>
        </div>

        <div class="field-group">
          <label class="field-label">Layout</label>
          <div class="filter-bar">
            <div class="filter-group">
              <button type="button" class="filter-pill <?= $isEditorial ? 'active' : '' ?>" data-layout="editorial">Editorial Page</button>
              <button type="button" class="filter-pill <?= !$isEditorial ? 'active' : '' ?>" data-layout="listing">Basic Listing</button>
            </div>
          </div>
        </div>

<?php if ($isEditorial): /* ── Editorial: section stack (skeleton) ───── */ ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Sections</span>
              <span class="content-block-sublabel">drag to reorder</span>
            </div>
            <span class="content-block-count"><?= count($sections) ?></span>
          </div>

          <div class="sec-list" id="sec-list">
            <?php foreach ($sections as $s):
                $sid   = (int)$s['id'];
                $stype = (string)$s['section_type'];
                $stit  = trim((string)($s['title'] ?? ''));
                $name  = $stit !== '' ? $stit : '(no title)';
            ?>
              <div class="sec-card is-collapsed" data-section data-section-type="<?= $e($stype) ?>" data-section-id="<?= $sid ?>">
                <input type="hidden" name="sections[<?= $sid ?>][id]"   value="<?= $sid ?>">
                <input type="hidden" name="sections[<?= $sid ?>][type]" value="<?= $e($stype) ?>">
                <div class="sec-card-head" data-section-toggle>
                  <span class="content-block-label"><?= $e($secTypeLabel($stype)) ?></span>
                  <span class="filter-sep"></span>
                  <span class="kcard-title"><?= $e($name) ?></span>
                  <button type="button" class="btn-icon btn-icon-danger" title="Delete section" data-section-delete>
                    <svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  <button type="button" class="btn-icon" title="Expand">
                    <svg viewBox="0 0 14 14" fill="none"><path d="M3 5l4 4 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
                <div class="sec-card-body">
                  <p class="field-hint">Editor for this section type lands in the next iteration.</p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

<?php else: /* ── Basic Listing: existing form (unchanged) ────────────── */ ?>

        <div class="content-block" id="block-hero">
          <div class="content-block-header"><div><span class="content-block-label">Hero feature</span></div></div>
          <div class="field-group">
            <select name="hero_content_id" class="field-input">
              <option value="0">— None —</option>
              <?php foreach ($pickList as $row): ?>
                <option value="<?= (int)$row['id'] ?>" <?= (int)$row['id'] === $heroId ? 'selected' : '' ?>><?= $pickLabel($row) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="content-block" id="block-featured">
          <div class="content-block-header"><div><span class="content-block-label">Featured</span></div></div>
          <div id="featured-list">
            <?php foreach ($featuredIds as $fid):
              $r = $pickById[$fid] ?? null;
              if ($r === null) continue;
            ?>
              <div class="rowform-row" draggable="true" data-id="<?= (int)$r['id'] ?>">
                <span><?= $pickLabel($r) ?></span>
                <button type="button" class="featured-remove btn-icon btn-icon-danger" title="Remove">
                  <svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="field-group">
            <select id="featured-add" class="field-input">
              <option value="">+ Add to featured…</option>
              <?php foreach ($pickList as $row): ?>
                <option value="<?= (int)$row['id'] ?>" data-label="<?= $pickLabel($row) ?>"><?= $pickLabel($row) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" id="featured-add-btn" class="btn-sec btn-tiny">Add</button>
          </div>
        </div>

        <div class="content-block">
          <div class="content-block-header"><div><span class="content-block-label">Content feed</span></div></div>

          <div class="field-group">
            <label class="field-label">Types</label>
            <div class="filter-bar">
              <div class="filter-group">
                <?php foreach ($typeLabels as $slug => $label):
                  $on = in_array($slug, $feedTypes, true);
                ?>
                  <label class="filter-pill <?= $on ? 'active' : '' ?>">
                    <input type="checkbox" name="feed_types[]" value="<?= $e($slug) ?>" <?= $on ? 'checked' : '' ?>>
                    <?= $e($label) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="field-group">
            <label class="field-label">Sort</label>
            <div class="filter-bar">
              <div class="filter-group">
                <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first'] as $val => $label):
                  $on = $sort === $val;
                ?>
                  <label class="filter-pill <?= $on ? 'active' : '' ?>">
                    <input type="radio" name="feed_sort" value="<?= $e($val) ?>" <?= $on ? 'checked' : '' ?>>
                    <?= $e($label) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="field-group">
            <label class="field-label">Rows shown</label>
            <div class="filter-bar">
              <div class="filter-group">
                <?php foreach (['1', '2', '3', '4', 'all'] as $val):
                  $on = $rowsShown === $val;
                ?>
                  <label class="filter-pill <?= $on ? 'active' : '' ?>">
                    <input type="radio" name="feed_rows_shown" value="<?= $e($val) ?>" <?= $on ? 'checked' : '' ?>>
                    <?= $e($val === 'all' ? 'All' : $val) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="field-group">
            <label class="field-label">Filter pills</label>
            <div class="filter-bar">
              <div class="filter-group">
                <?php foreach (['categories' => 'Categories', 'types' => 'Types', 'none' => 'None'] as $val => $label):
                  $on = $filterMode === $val;
                ?>
                  <label class="filter-pill <?= $on ? 'active' : '' ?>">
                    <input type="radio" name="filter_mode" value="<?= $e($val) ?>" <?= $on ? 'checked' : '' ?>>
                    <?= $e($label) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

<?php endif; ?>

        <div class="form-actions">
          <a href="/cms/indexes" class="btn-sec">Cancel</a>
          <button type="submit" class="btn-pri">Save</button>
        </div>

      </form>
    </div>
  </main>
</div>

<script>
// Skeleton interactions only — toggle collapse, layout pill, delete.
(function () {
  'use strict';
  var form = document.getElementById('index-edit-form');
  if (!form) return;

  // Collapse / expand a section card when its head is clicked.
  form.addEventListener('click', function (e) {
    if (e.target.closest('button, input, select, a')) return;
    var head = e.target.closest('[data-section-toggle]');
    if (!head) return;
    var card = head.closest('[data-section]');
    if (card) card.classList.toggle('is-collapsed');
  });

  // Delete a section (DOM-only for now; server dedupe lands with section CRUD).
  form.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-section-delete]');
    if (!btn) return;
    if (!confirm('Delete this section?')) return;
    var card = btn.closest('[data-section]');
    if (card) card.remove();
  });

  // Layout pill toggle — submit on change so the server re-renders the right form.
  form.addEventListener('click', function (e) {
    var p = e.target.closest('[data-layout]');
    if (!p) return;
    var hidden = document.getElementById('layout-input');
    if (!hidden) return;
    if (hidden.value === p.getAttribute('data-layout')) return;
    hidden.value = p.getAttribute('data-layout');
    form.submit();
  });
})();
</script>

</body>
</html>
