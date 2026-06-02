<?php
/**
 * cms/views/categories.php — Categories admin (Phase 11).
 *
 * Four blocks (Articles / Journals / Live Sessions / Experiments) in a
 * 2-column grid, mirroring docs/design-mockups/cms-ui.html.
 *
 * Each row's controls (label input, colour select, Save, Delete) are
 * bound via the HTML5 `form="cat-row-N"` attribute to a `<form>`
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
    'article'      => ['title' => 'Articles',      'note' => 'Primary category drives colour and card display. Secondary categories expand index inclusion.'],
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

  <main class="main">
    <div class="view active" id="view-categories">
      <?php
      $title    = 'Categories';
      $subtitle = "Value slugs are permanent — they're what the database stores. Labels and colours are editable anytime. A category can only be deleted when its usage count is zero.";
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
      // Render the per-row <form> elements once, before the layout grid.
      // They live outside the tables so they can host inputs from any
      // cell via the HTML5 form="cat-row-N" attribute.
      foreach ($all as $cat):
        $rid = 'cat-row-' . (int)$cat['id'];
      ?>
        <form id="<?= $e($rid) ?>" method="post" action="/cms/categories" style="display:none">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
        </form>
      <?php endforeach; ?>

      <div class="categories-layout">
        <?php foreach (CATEGORY_TYPES as $type):
          $rows = $byType[$type] ?? [];
          $meta = $block_meta[$type];
        ?>
        <div class="cat-block">
          <div class="cat-block-title"><?= $e($meta['title']) ?></div>
          <div class="cat-block-note"><?= $e($meta['note']) ?></div>
          <table class="cat-table">
            <thead><tr>
              <th style="width:32%">Label</th>
              <th style="width:22%">Value slug</th>
              <th style="width:22%">Colour</th>
              <th style="width:8%;text-align:center">Use</th>
              <th style="width:16%;text-align:right">Actions</th>
            </tr></thead>
            <tbody>
              <?php if (count($rows) === 0): ?>
                <tr><td colspan="5" style="color:var(--muted);font-style:italic;padding:var(--space-12)">No categories yet — add one below.</td></tr>
              <?php endif; ?>
              <?php foreach ($rows as $cat):
                $use = (int)($cat['usage_count'] ?? 0);
                $canDelete = $use === 0;
                $rid = 'cat-row-' . (int)$cat['id'];
              ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:6px">
                    <div class="cat-swatch" style="background:var(--c-<?= $e((string)$cat['colour']) ?>)"></div>
                    <input class="cat-input" name="label" form="<?= $e($rid) ?>" value="<?= $e((string)$cat['label']) ?>" maxlength="255" required>
                  </div>
                </td>
                <td><span class="val-pill"><?= $e((string)$cat['value_slug']) ?></span></td>
                <td><?= $colour_select((string)$cat['colour'], $rid) ?></td>
                <td class="cat-count<?= $canDelete ? ' zero' : '' ?>"><?= $use ?></td>
                <td style="text-align:right;white-space:nowrap">
                  <button type="submit" name="action" value="update" form="<?= $e($rid) ?>" class="btn-row-action" title="Save changes" style="font-size:11px;margin-right:6px">Save</button>
                  <?php if ($canDelete): ?>
                    <button type="submit" name="action" value="delete" form="<?= $e($rid) ?>" class="cat-del ok" title="Delete category" aria-label="Delete" onclick="return confirm('Delete category &quot;<?= $e((string)$cat['label']) ?>&quot;?');">
                      <svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                  <?php else: ?>
                    <button class="cat-del" title="Delete (in use, cannot delete)" aria-label="Delete" disabled>
                      <svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <form method="post" action="/cms/categories" class="cat-add-row">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="type" value="<?= $e($type) ?>">
            <input class="cat-add-input" name="label" placeholder="New label…" maxlength="255" required>
            <?= $colour_select('terracotta') ?>
            <button type="submit" class="cat-add-btn">Add</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>

</body>
</html>
