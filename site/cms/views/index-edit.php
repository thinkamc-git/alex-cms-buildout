<?php
/**
 * cms/views/index-edit.php — Index editor (Phase 21.7).
 *
 * Two layouts via a single view + a pill-toggle:
 *   - Editorial Page   → section-stack builder (Phase 21.7 model).
 *                        Each section is a .content-block with its own
 *                        type-specific form; drag-reorder, add, delete
 *                        all client-side; one Save submits the whole
 *                        stack via $_POST['sections'].
 *   - Basic Listing    → the existing flat hero/featured/feed form
 *                        (CMS-STRUCTURE.md §16 keeps the flat columns
 *                        authoritative for this layout). Untouched.
 *
 * Slug stays permanent (URL contract).
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

// ─── POST: save ─────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        // featured_ids arrives as a comma-separated string from the hidden
        // input the legacy drag-drop JS keeps in sync (Basic Listing only).
        $featuredRaw = (string)($_POST['featured_ids'] ?? '');
        $featuredArr = [];
        foreach (explode(',', $featuredRaw) as $piece) {
            $n = (int)trim($piece);
            if ($n > 0) $featuredArr[] = $n;
        }

        $typesArr = $_POST['feed_types'] ?? [];
        if (!is_array($typesArr)) $typesArr = [];

        $catsArr = $_POST['feed_categories'] ?? [];
        if (!is_array($catsArr)) $catsArr = [];

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
            'feed_categories' => $catsArr,
            'feed_sort'       => (string)($_POST['feed_sort']       ?? 'newest'),
            'feed_rows_shown' => (string)($_POST['feed_rows_shown'] ?? 'all'),
            'filter_mode'     => (string)($_POST['filter_mode']     ?? 'categories'),
        ];

        try {
            $res = save_index($data);
            if (!$res['ok']) {
                $errors[] = $res['error'];
            } else {
                // Editorial: persist the section stack ONLY when the form
                // explicitly carried it. The marker prevents a layout toggle
                // (Editorial → Basic → Editorial) from wiping all sections,
                // since the Basic branch's form has no sections[] data.
                if ($newLayout === 'editorial' && !empty($_POST['sections_submitted'])) {
                    save_editorial_sections_from_post($id, (array)($_POST['sections'] ?? []));
                }
                header('Location: /cms/indexes/edit?id=' . $id . '&flash=' . rawurlencode('Saved.'));
                exit;
            }
        } catch (\Throwable $ex) {
            error_log('[index-edit] save threw: ' . $ex->getMessage());
            $errors[] = 'Could not save index: ' . $ex->getMessage();
        }

        // Reload state from POST values so the form re-renders with what
        // the user typed when there's an error.
        $index = array_merge($index, [
            'layout'          => $data['layout'],
            'title'           => $data['title'],
            'subtitle'        => $data['subtitle'],
            'show_title'      => $data['show_title'] ? 1 : 0,
            'hero_content_id' => $data['hero_content_id'],
            'featured_ids'    => json_encode($data['featured_ids']),
            'feed_types'      => json_encode($data['feed_types']),
            'feed_categories' => json_encode($data['feed_categories']),
            'feed_sort'       => $data['feed_sort'],
            'feed_rows_shown' => $data['feed_rows_shown'],
            'filter_mode'     => $data['filter_mode'],
        ]);
    }
}

/**
 * Persist a section-stack POST. Iterates $_POST['sections'] in order,
 * upserts each section, then deletes any pre-existing sections whose
 * id wasn't seen in the POST. New sections have no `id` and get one.
 */
function save_editorial_sections_from_post(int $index_id, array $posted): void
{
    $existing = list_index_sections($index_id);
    $seenIds  = [];

    foreach (array_values($posted) as $position => $row) {
        if (!is_array($row)) continue;
        $type = (string)($row['type'] ?? '');
        if (!in_array($type, INDEX_SECTION_TYPES, true)) continue;

        // item_ids arrives as a comma-separated string of int ids.
        $itemIds = [];
        if (isset($row['item_ids'])) {
            foreach (explode(',', (string)$row['item_ids']) as $piece) {
                $n = (int)trim($piece);
                if ($n > 0) $itemIds[] = $n;
            }
        }

        $feedTypes = (array)($row['feed_types']      ?? []);
        $feedCats  = (array)($row['feed_categories'] ?? []);
        $filtOpts  = (array)($row['filter_options']  ?? []);

        $payload = [
            'id'              => (int)($row['id'] ?? 0),
            'index_id'        => $index_id,
            'position'        => $position,
            'section_type'    => $type,
            'title'           => (string)($row['title'] ?? ''),
            'header_style'    => (string)($row['header_style'] ?? 'small'),
            'hero_image_mode' => (string)($row['hero_image_mode'] ?? 'auto'),
            'hero_image_url'  => (string)($row['hero_image_url']  ?? ''),
            'hero_layout'     => (string)($row['hero_layout']     ?? 'within'),
            'hero_background' => (string)($row['hero_background'] ?? 'transparent'),
            'display_format'  => (string)($row['display_format'] ?? 'grid'),
            'item_limit'      => ($row['item_limit'] ?? '') !== '' ? (int)$row['item_limit'] : null,
            'grid_rows'       => (string)($row['grid_rows'] ?? 'all'),
            'see_more_label'  => (string)($row['see_more_label'] ?? ''),
            'see_more_target' => (string)($row['see_more_target'] ?? ''),
            'feed_types'      => $feedTypes,
            'feed_categories' => $feedCats,
            'feed_sort'       => (string)($row['feed_sort'] ?? 'newest'),
            'filter_show'     => !empty($row['filter_show']),
            'filter_by'       => (string)($row['filter_by'] ?? ''),
            'filter_options'  => $filtOpts,
            'item_ids'        => $itemIds,
        ];

        $res = save_index_section($payload);
        if ($res['ok']) $seenIds[] = (int)$res['id'];
    }

    // Delete any DB sections the user removed in the UI.
    foreach ($existing as $s) {
        $sid = (int)$s['id'];
        if (!in_array($sid, $seenIds, true)) {
            delete_index_section($sid);
        }
    }
}

$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

// All published content rows — drives Hero/Curated pickers.
$pickList = list_index_feed([
    'feed_types'      => null,
    'feed_sort'       => 'newest',
    'feed_rows_shown' => 'all',
]);
$pickById = [];
foreach ($pickList as $p) $pickById[(int)$p['id']] = $p;

// All categories (for the Filtered section's categories pill rail).
$allCats = list_categories();

// Bucket categories by type, in the same order the type pills render.
// Each entry: { slug, label, colour }. Consumed by index-edit.js for
// the dynamic per-type rebuild of category pill rails.
$catsByType = [];
foreach (['article', 'journal', 'live-session', 'experiment'] as $t) {
    $catsByType[$t] = [];
}
foreach ($allCats as $c) {
    $t = (string)$c['type'];
    if (!isset($catsByType[$t])) $catsByType[$t] = [];
    $catsByType[$t][] = [
        'slug'   => (string)$c['value_slug'],
        'label'  => (string)$c['label'],
        'colour' => (string)($c['colour'] ?? ''),
    ];
}

// Decode legacy JSON columns for the Basic Listing form.
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

$feedCats = $index['feed_categories'] ?? null;
if (is_string($feedCats)) {
    $decoded = json_decode($feedCats, true);
    $feedCats = is_array($decoded) ? $decoded : [];
}
if (!is_array($feedCats)) $feedCats = [];
$feedCats = array_map('strval', $feedCats);

$layout      = (string)$index['layout'];
$isEditorial = $layout === 'editorial';
$heroId      = (int)($index['hero_content_id'] ?? 0);
$showTitle   = !empty($index['show_title']);
$sort        = (string)($index['feed_sort']       ?? 'newest');
$rowsShown   = (string)($index['feed_rows_shown'] ?? 'all');
$filterMode  = (string)($index['filter_mode']     ?? 'categories');

// Existing section stack (Editorial only).
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
    $type   = (string)($row['type']  ?? '');
    $title  = (string)($row['title'] ?? '(untitled)');
    $pub    = (string)($row['published_at'] ?? '');
    $date   = $pub !== '' ? date('Y-m-d', strtotime($pub)) : '';
    $tlabel = ucfirst(str_replace('-', ' ', $type));
    return $e($title) . ' — ' . $e($tlabel) . ($date !== '' ? ' · ' . $e($date) : '');
};

/** Pretty UI labels for section types. 'feed' stores in the DB; 'Filtered' shows in the UI. */
$secTypeLabel = static function (string $t): string {
    if ($t === 'hero')    return 'Hero';
    if ($t === 'curated') return 'Curated';
    if ($t === 'feed')    return 'Filtered';
    return ucfirst($t);
};

/** Build a 1-line summary of a section's current config, for the
    collapsed header bar. Caller passes a normalized section row. */
$sectionSummary = static function (array $s) use ($typeLabels): string {
    $type = (string)$s['section_type'];
    $items = is_array($s['item_ids'] ?? null) ? $s['item_ids'] : [];
    if ($type === 'hero') {
        return $items !== [] ? '1 item picked' : 'no pick yet';
    }
    if ($type === 'curated') {
        $n = count($items);
        $rows = (string)($s['grid_rows'] ?? 'all');
        $fmt  = (string)($s['display_format'] ?? 'grid');
        return $n . ($n === 1 ? ' pick' : ' picks') . ' · '
             . ucfirst($fmt) . ($fmt === 'grid' ? ' · ' . $rows . ($rows !== 'all' ? ' rows' : ' rows') : '');
    }
    // feed
    $fts = is_array($s['feed_types'] ?? null) ? $s['feed_types'] : [];
    $tlabels = array_map(static fn($t) => $typeLabels[$t] ?? ucfirst($t), $fts);
    $tstr = $tlabels === [] ? 'All types' : implode(' + ', $tlabels);
    $sort = (string)($s['feed_sort'] ?? 'newest');
    $fmt  = (string)($s['display_format'] ?? 'grid');
    $rows = (string)($s['grid_rows'] ?? 'all');
    return $tstr . ' · ' . ucfirst($sort) . ' · ' . ucfirst($fmt) . ($fmt === 'grid' ? ' · ' . $rows . ' rows' : '');
};

/** Categories the author can pick / filter by for a feed section.
    Returns rows with value_slug + label + colour. When $types is non-
    empty, restrict to categories whose type matches one of those. */
$catsForTypes = static function (array $types) use ($allCats): array {
    if ($types === []) return $allCats;
    $out = [];
    foreach ($allCats as $c) {
        if (in_array((string)$c['type'], $types, true)) $out[] = $c;
    }
    return $out;
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
          ? 'Editorial Page · composed of typed sections (Hero / Curated / Filtered).'
          : 'Basic Listing · single filtered feed.';
      $actions  = '<a href="/cms/indexes" class="btn-sec">← Back to Indexes</a>'
                . ' <a href="/' . $e((string)$index['slug']) . '/" target="_blank" rel="noopener" class="btn-sec">Live ↗</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $heading = "Couldn't save:";
      require __DIR__ . '/../partials/form-errors.php';
      require __DIR__ . '/../partials/flash.php';
      ?>

      <div class="content-area">
      <form method="post" action="/cms/indexes/edit" class="cms-form cms-form-wide reveal-page" id="index-edit-form">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="layout" id="layout-input" value="<?= $e($layout) ?>">
        <input type="hidden" name="featured_ids" id="featured-ids-input" value="<?= $e(implode(',', $featuredIds)) ?>">

        <!-- Page-level fields: title, subtitle, show_title toggle on the
             left, Layout pill toggle on the right. -->
        <div class="field-group">
          <label class="field-label" for="title-input">Page Title</label>
          <input id="title-input" type="text" name="title" class="field-input large" value="<?= $e((string)($index['title'] ?? '')) ?>" maxlength="500">
        </div>

        <div class="field-group">
          <label class="field-label" for="subtitle-input">Page Subtitle</label>
          <input id="subtitle-input" type="text" name="subtitle" class="field-input" value="<?= $e((string)($index['subtitle'] ?? '')) ?>" maxlength="500">
        </div>

        <div class="field-group" style="display:flex;gap:var(--space-12);align-items:center;flex-wrap:wrap">
          <label class="switch-filled"><input type="checkbox" name="show_title" id="show-title-toggle" value="1" <?= $showTitle ? 'checked' : '' ?>><span class="slider"></span></label>
          <label for="show-title-toggle" style="font-family:var(--font);font-size:var(--text-meta);color:var(--secondary);cursor:pointer;margin:0">Show page title</label>
          <span style="flex:1"></span>
          <span class="content-block-label" style="margin-right:var(--space-8)">Layout</span>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:nowrap">
            <div class="filter-group" data-layout-toggle>
              <button type="button" class="filter-pill <?= $isEditorial ? 'active' : '' ?>" data-layout="editorial">Editorial</button>
              <button type="button" class="filter-pill <?= !$isEditorial ? 'active' : '' ?>" data-layout="listing">Basic Listing</button>
            </div>
          </div>
        </div>

<?php if ($isEditorial): /* ─── EDITORIAL SECTION STACK BUILDER ───── */ ?>

        <input type="hidden" name="sections_submitted" value="1">

        <div class="content-block-header">
          <div>
            <span class="content-block-label">Sections</span>
            <span class="content-block-sublabel">drag to reorder</span>
          </div>
        </div>

        <?php if (count($sections) === 0): ?>
          <div class="empty-state" data-sec-empty>No sections yet — add one below.</div>
        <?php endif; ?>

        <div id="sec-stack" class="sec-list">
          <?php
          $i = 0;
          foreach ($sections as $s):
              $sid     = (int)$s['id'];
              $stype   = (string)$s['section_type'];
              $stitle  = (string)($s['title'] ?? '');
              $hstyle  = (string)($s['header_style'] ?? 'small');
              $himode  = (string)($s['hero_image_mode'] ?? 'auto');
              $himgUrl = (string)($s['hero_image_url']  ?? '');
              $hlayout = (string)($s['hero_layout']     ?? 'within');
              $hbg     = (string)($s['hero_background'] ?? 'transparent');
              $items   = is_array($s['item_ids'] ?? null) ? $s['item_ids'] : [];
              $ftypes  = is_array($s['feed_types'] ?? null) ? $s['feed_types'] : [];
              $fcats   = is_array($s['feed_categories'] ?? null) ? $s['feed_categories'] : [];
              $fopts   = is_array($s['filter_options'] ?? null) ? $s['filter_options'] : [];
              $fshow   = !empty($s['filter_show']);
              $fby     = (string)($s['filter_by'] ?? 'types');
              $fmt     = (string)($s['display_format'] ?? 'grid');
              $gridR   = (string)($s['grid_rows'] ?? 'all');
              $limit   = $s['item_limit'] ?? '';
              $seeLab  = (string)($s['see_more_label']  ?? '');
              $seeTgt  = (string)($s['see_more_target'] ?? '');
              $fsort   = (string)($s['feed_sort'] ?? 'newest');
          ?>
          <?php require __DIR__ . '/index-edit-section.php'; ?>
          <?php $i++; endforeach; ?>
        </div>

        <div class="form-actions">
          <button type="button" class="btn-sec" data-add-type="hero">+ Hero</button>
          <button type="button" class="btn-sec" data-add-type="curated">+ Curated</button>
          <button type="button" class="btn-sec" data-add-type="feed">+ Filtered</button>
          <button type="button" class="btn-sec" disabled title="Coming soon" aria-disabled="true">+ Hero Multi</button>
        </div>

        <!-- Section templates for JS-driven Add. Hidden; the JS clones,
             swaps placeholders, and appends to #sec-stack. -->
        <?php foreach (['hero', 'curated', 'feed'] as $tplType):
            $s = [
                'id' => 0, 'section_type' => $tplType, 'title' => '',
                'item_ids' => [], 'feed_types' => [], 'feed_categories' => [],
                'filter_options' => [], 'filter_show' => false, 'filter_by' => 'types',
                'display_format' => 'grid', 'grid_rows' => 'all',
                'item_limit' => '', 'see_more_label' => '', 'see_more_target' => '',
                'feed_sort' => 'newest',
            ];
            $sid = 0; $stype = $tplType; $stitle = ''; $hstyle = 'small';
            $himode = 'auto'; $himgUrl = ''; $hlayout = 'within'; $hbg = 'transparent';
            $items = [];
            $ftypes = []; $fcats = []; $fopts = []; $fshow = false; $fby = 'types';
            $fmt = 'grid'; $gridR = 'all'; $limit = ''; $seeLab = ''; $seeTgt = '';
            $fsort = 'newest';
            $i = '__TPL__';
        ?>
        <template id="sec-tpl-<?= $tplType ?>"><?php require __DIR__ . '/index-edit-section.php'; ?></template>
        <?php endforeach; ?>

<?php else: /* ─── BASIC LISTING (single config card, no sections) ──────── */ ?>

        <?php
        // Map the legacy flat filter_mode (enum: 'none' | 'types' | 'categories')
        // to the toggle+choice UX. The hidden filter_mode input is set on
        // submit by JS based on the toggle + the Pills represent choice.
        $bl_filter_on = $filterMode !== 'none';
        $bl_filter_by = $bl_filter_on ? $filterMode : 'categories';
        ?>

        <div class="sec-card"><div class="sec-card-body">

          <div class="field-group">
            <label class="field-label">Types</label>
            <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none">
              <div class="filter-group" data-bl-types>
                <?php foreach ($typeLabels as $slug => $label):
                  $on = in_array($slug, $feedTypes, true);
                ?>
                  <label class="filter-pill <?= $on ? 'active' : '' ?>">
                    <input type="checkbox" name="feed_types[]" value="<?= $e($slug) ?>" <?= $on ? 'checked' : '' ?> style="display:none">
                    <?= $e($label) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <?php
          // Derive the category options available to this index based on its
          // currently-selected types. If no types are selected (= all types),
          // show the full set. JS keeps this in sync on the fly.
          $blAvailCats = [];
          $blTypesForCats = $feedTypes === [] ? array_keys($catsByType) : $feedTypes;
          $seenSlug = [];
          foreach ($blTypesForCats as $t) {
              foreach ($catsByType[$t] ?? [] as $c) {
                  $k = $c['slug'];
                  if ($k === '' || isset($seenSlug[$k])) continue;
                  $seenSlug[$k] = true;
                  $blAvailCats[] = $c;
              }
          }
          ?>
          <div class="field-group">
            <label class="field-label">Categories <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:var(--text-micro);color:var(--muted);font-family:var(--font)">optional · empty = all</span></label>
            <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none">
              <div class="filter-group" data-bl-cats>
                <?php if ($blAvailCats === []): ?>
                  <span class="field-hint" data-bl-cats-empty>No categories available for the selected types.</span>
                <?php else: foreach ($blAvailCats as $c):
                  $on = in_array($c['slug'], $feedCats, true);
                ?>
                  <label class="filter-pill <?= $on ? 'active' : '' ?>" data-cat-pill="<?= $e($c['slug']) ?>">
                    <input type="checkbox" name="feed_categories[]" value="<?= $e($c['slug']) ?>" <?= $on ? 'checked' : '' ?> style="display:none">
                    <?= $e($c['label']) ?>
                  </label>
                <?php endforeach; endif; ?>
              </div>
            </div>
          </div>

          <div class="field-group">
            <label class="field-label">Sort</label>
            <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none">
              <div class="filter-group">
                <?php foreach (['newest' => 'Newest first', 'oldest' => 'Oldest first'] as $val => $label):
                  $on = $sort === $val;
                ?>
                  <label class="filter-pill <?= $on ? 'active' : '' ?>">
                    <input type="radio" name="feed_sort" value="<?= $e($val) ?>" <?= $on ? 'checked' : '' ?> style="display:none">
                    <?= $e($label) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <input type="hidden" name="feed_rows_shown" value="<?= $e($rowsShown) ?>">

          <div class="field-group">
            <label class="field-label" style="display:flex;align-items:center;gap:var(--space-8)">
              <span>Show Filters</span>
              <label class="switch-filled">
                <input type="checkbox" id="bl-filter-toggle" <?= $bl_filter_on ? 'checked' : '' ?>>
                <span class="slider"></span>
              </label>
            </label>
            <div id="bl-filter-detail" style="<?= $bl_filter_on ? '' : 'display:none' ?>">
              <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none">
                <div class="filter-group" data-bl-by>
                  <?php foreach (['types' => 'Types', 'categories' => 'Categories'] as $val => $label): ?>
                    <button type="button" class="filter-pill <?= $bl_filter_by === $val ? 'active' : '' ?>" data-bl-by-value="<?= $e($val) ?>"><?= $e($label) ?></button>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <!-- Server-facing hidden input — JS rewrites on submit based on the
                 toggle + the Pills-represent choice. -->
            <input type="hidden" name="filter_mode" id="bl-filter-mode" value="<?= $e($filterMode) ?>">
          </div>

        </div></div><!-- /.sec-card-body / .sec-card -->

<?php endif; ?>

        <div class="form-actions form-actions-sticky">
          <button type="submit" class="btn-sec" data-save-btn>Publish</button>
          <a href="/cms/indexes" class="btn-sec">Cancel</a>
        </div>

      </form>
      </div>
    </div>
  </main>
</div>

<script>window.CMS_CATEGORIES_BY_TYPE = <?= json_encode($catsByType, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/cms/_assets/index-edit.js" defer></script>
<script src="/cms/_assets/preview-tab-guard.js" defer></script>
<script src="/cms/_assets/dirty-flip.js" defer></script>

</body>
</html>
