<?php
/**
 * cms/views/categories.php — Categories admin (Phase 11).
 *
 * Four blocks (Articles / Journals / Live Sessions / Experiments) in a
 * 2-column grid, mirroring docs/design-mockups/cms-ui.html.
 *
 * Each row's controls (label input, colour select, Save, Delete) are
 * bound via the HTML5 `form="row-form-cat-N"` attribute to a `<form>`
 * rendered outside the <table> — forms can't wrap <tr> validly, so the
 * attribute is the cleanest cross-cell binding. One form per row,
 * two submit buttons, each named `action` with a different value:
 *   action=update  → label + colour PATCH
 *   action=delete  → DELETE (blocked at the DB layer if usage_count>0)
 * The Add row at the bottom of each block uses its own action=add form.
 *
 * value_slug is permanent (rendered as a pill, not an input). Updates
 * never touch it; the migrations + INSERT path derive it from the
 * label via slugify() and the (type, value_slug) UNIQUE key enforces.
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'add') {
            $res = save_category([
                'type'   => (string)($_POST['type']   ?? ''),
                'label'  => (string)($_POST['label']  ?? ''),
                'colour' => (string)($_POST['colour'] ?? ''),
            ]);
            $flash = $res['ok'] ? 'Category added.' : '';
            if (!$res['ok']) $errors[] = $res['error'];
        } elseif ($action === 'update') {
            $res = save_category([
                'id'     => (int)($_POST['id']      ?? 0),
                'label'  => (string)($_POST['label']  ?? ''),
                'colour' => (string)($_POST['colour'] ?? ''),
            ]);
            $flash = $res['ok'] ? 'Category updated.' : '';
            if (!$res['ok']) $errors[] = $res['error'];
        } elseif ($action === 'delete') {
            $res = delete_category((int)($_POST['id'] ?? 0));
            $flash = $res['ok'] ? 'Category deleted.' : '';
            if (!$res['ok']) $errors[] = $res['error'];
        } else {
            $errors[] = 'Unknown action.';
        }

        // PRG so refresh doesn't replay. On error, fall through and
        // render with $errors set (without a redirect).
        if (count($errors) === 0) {
            header('Location: /cms/categories?flash=' . rawurlencode($flash));
            exit;
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// Group categories by type. list_categories() returns every row sorted
// by (type, sort_order, label); we partition into the four blocks below.
$all = list_categories();
$byType = ['article' => [], 'journal' => [], 'live-session' => [], 'experiment' => []];
foreach ($all as $cat) {
    $t = (string)($cat['type'] ?? '');
    if (isset($byType[$t])) $byType[$t][] = $cat;
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/**
 * Render the colour <select>. Background colour updates live via the
 * onchange handler so the chip preview matches the picked token before
 * the form is submitted.
 */
$colour_select = static function (string $current, ?string $formId = null) use ($e): string {
    $opts = '';
    foreach (PALETTE_COLORS as $c) {
        $sel = $c === $current ? ' selected' : '';
        $opts .= '<option value="' . $e($c) . '"' . $sel . '>' . $e($c) . '</option>';
    }
    return '<select class="cat-colour-select" name="colour"'
         . ($formId !== null ? ' form="' . $e($formId) . '"' : '')
         . ' data-colour="' . $e($current) . '"'
         . ' style="background-color:var(--c-' . $e($current) . ')"'
         . ' onchange="this.style.backgroundColor=\'var(--c-\'+this.value+\')\'">'
         . $opts . '</select>';
};

$block_meta = [
    'article'      => ['title' => 'Articles',      'note' => 'Primary category drives colour and card display. Secondary categories add the article to more index pages.'],
    'journal'      => ['title' => 'Journals',      'note' => 'Single category per entry. Drives the sequential entry number within that category.'],
    'live-session' => ['title' => 'Live Sessions', 'note' => 'Single category per session. Displayed publicly as the event type.'],
    'experiment'   => ['title' => 'Experiments',   'note' => 'Single category per experiment. Drives the grid treatment on the index.'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Categories — alexmchong.ca CMS</title>
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
$breadcrumb = 'Categories';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'categories';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-categories">
      <?php
      $title    = 'Categories';
      $subtitle = "Value slugs are permanent — they're what the database stores. Labels and colours are editable any time. A category can only be deleted when nothing is using it.";
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $heading = "Couldn't save:";
      require __DIR__ . '/../partials/form-errors.php';
      require __DIR__ . '/../partials/flash.php';
      ?>

      <?php
      // Render the per-row <form> elements once, before the layout grid.
      // They live outside the tables so they can host inputs from any
      // cell via the HTML5 form="row-form-cat-N" attribute.
      foreach ($all as $cat):
        $rid = 'row-form-cat-' . (int)$cat['id'];
      ?>
        <form id="<?= $e($rid) ?>" method="post" action="/cms/categories" style="display:none">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
        </form>
      <?php endforeach; ?>

      <div class="categories-layout">
        <?php foreach (CATEGORY_TYPES as $type):
          $catRows = $byType[$type] ?? [];
          $meta    = $block_meta[$type];
        ?>
        <div class="cat-block">
          <div class="cat-block-title"><?= $e($meta['title']) ?></div>
          <div class="cat-block-note"><?= $e($meta['note']) ?></div>
          <?php
          $columns = [
              ['label' => 'Label',     'width' => '32%'],
              ['label' => 'Value slug','width' => '22%'],
              ['label' => 'Colour',    'width' => '22%'],
              ['label' => 'Use',       'width' => '8%'],
              ['label' => 'Actions',   'width' => '16%'],
          ];
          $rows = [];
          foreach ($catRows as $cat) {
              $use       = (int)($cat['usage_count'] ?? 0);
              $canDelete = $use === 0;
              $rid       = 'row-form-cat-' . (int)$cat['id'];
              $labelCell =
                  '<div style="display:flex;align-items:center;gap:6px">'
                . '<div class="cat-swatch" style="background:var(--c-' . $e((string)$cat['colour']) . ')"></div>'
                . '<input class="cat-input" name="label" form="' . $e($rid) . '" value="' . $e((string)$cat['label']) . '" maxlength="255" required>'
                . '</div>';
              $slugCell    = '<span class="val-pill">' . $e((string)$cat['value_slug']) . '</span>';
              $colourCell  = $colour_select((string)$cat['colour'], $rid);
              $useCell     = (string)$use;
              $actionsCell = '<button type="submit" name="action" value="update" form="' . $e($rid) . '" class="btn-sec btn-tiny" title="Save changes" data-save-btn style="margin-right:6px">Save</button>';
              if ($canDelete) {
                  // Batch 2 #37/#48: switch to canonical icon-button + data-confirm.
                  $catLabelAttr = $e((string)$cat['label']);
                  $actionsCell .=
                      '<button type="submit" name="action" value="delete" form="' . $e($rid) . '" class="btn-icon btn-icon-danger" title="Delete category" aria-label="Delete"'
                    . ' data-confirm="Delete category &quot;' . $catLabelAttr . '&quot;? This can&#039;t be undone.">'
                    . '<svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                    . '</button>';
              } else {
                  $actionsCell .=
                      '<button class="btn-icon" title="Delete (in use, cannot delete)" aria-label="Delete" disabled>'
                    . '<svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                    . '</button>';
              }
              $rows[] = [
                  $labelCell,
                  $slugCell,
                  $colourCell,
                  ['html' => $useCell, 'class' => 'cat-count' . ($canDelete ? ' zero' : '')],
                  ['html' => $actionsCell, 'class' => 'cell-actions'],
              ];
          }
          $empty_text = 'No categories yet — add one below.';
          $variant    = 'cat';
          require __DIR__ . '/../partials/table.php';
          ?>
          <form method="post" action="/cms/categories" class="cat-add-row">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="type" value="<?= $e($type) ?>">
            <input class="cat-add-input" name="label" placeholder="New label…" maxlength="255" required>
            <?= $colour_select('terracotta') ?>
            <button type="submit" class="btn-sec cat-add-btn">Add</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>

</body>
</html>
