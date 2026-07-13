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
$byType = ['article' => [], 'journal' => [], 'experiment' => [], 'live-session' => []];
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
// Phase 21.7 — native <select> + <option> styling is unreliable
// cross-browser (Safari uses native macOS popups that ignore option
// CSS). Replace with a custom dropdown component that uses real
// HTML elements we can fully style. Hidden <input name="colour">
// carries the value for form submission.
$PALETTE_HEX = [
    'rust'       => '#765150', 'terracotta' => '#7d4631', 'clay'   => '#765e44',
    'amber'      => '#81642a', 'ochre'      => '#786e4a', 'olive'  => '#6e7448',
    'moss'       => '#607549', 'forest'     => '#49634b', 'sage'   => '#4d705a',
    'teal'       => '#4a716e', 'ocean'      => '#4a6677', 'denim'  => '#46556a',
    'indigo'     => '#4d567a', 'purple'     => '#5d5376', 'violet' => '#6c4d7a',
    'plum'       => '#785071', 'mauve'      => '#6f4b61', 'rose'   => '#7a5160',
];
$colour_select = static function (string $current, ?string $formId = null) use ($e, $PALETTE_HEX): string {
    $currentHex = $PALETTE_HEX[$current] ?? '#7d7d7d';
    $opts = '';
    foreach (PALETTE_COLORS as $c) {
        $hex = $PALETTE_HEX[$c] ?? '#7d7d7d';
        $cls = 'cat-colour-opt' . ($c === $current ? ' is-selected' : '');
        $opts .= '<button type="button" class="' . $cls . '" data-value="' . $e($c) . '" style="background-color:' . $hex . '">' . $e($c) . '</button>';
    }
    $formAttr = $formId !== null ? ' form="' . $e($formId) . '"' : '';
    return '<div class="cat-colour-picker" data-value="' . $e($current) . '">'
         . '<input type="hidden" name="colour"' . $formAttr . ' value="' . $e($current) . '" data-colour-input>'
         . '<button type="button" class="cat-colour-trigger" style="background-color:' . $currentHex . '" data-colour-trigger>'
         .   '<span class="cat-colour-name">' . $e($current) . '</span>'
         .   '<span class="cat-colour-arrow" aria-hidden="true">▾</span>'
         . '</button>'
         . '<div class="cat-colour-menu" hidden role="listbox">' . $opts . '</div>'
         . '</div>';
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
<link rel="stylesheet" href="/cms/_assets/style-cms.css<?= asset_ver('/cms/_assets/style-cms.css') ?>">
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
              ['label' => '', 'width' => '28px', 'thclass' => 'cat-grip-cell'],
              ['label' => 'Label',     'width' => '32%'],
              ['label' => 'Value slug','width' => '22%'],
              ['label' => 'Colour',    'width' => '22%'],
              ['label' => 'Use',       'width' => '8%'],
              ['label' => '',          'width' => '16%'],
          ];
          $rows = [];
          foreach ($catRows as $cat) {
              $use       = (int)($cat['usage_count'] ?? 0);
              $canDelete = $use === 0;
              $rid       = 'row-form-cat-' . (int)$cat['id'];
              $gripCell    = '<span class="cms-grip" aria-hidden="true" title="Drag to reorder">⠿</span>';
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
                  'cells' => [
                      ['html' => $gripCell, 'class' => 'cat-grip-cell'],
                      $labelCell,
                      $slugCell,
                      $colourCell,
                      ['html' => $useCell, 'class' => 'cat-count' . ($canDelete ? ' zero' : '')],
                      ['html' => $actionsCell, 'class' => 'cell-actions'],
                  ],
                  'attrs' => [
                      'draggable' => 'true',
                      'data-id'   => (string)(int)$cat['id'],
                  ],
              ];
          }
          $empty_text  = 'No categories yet — add one below.';
          $variant     = 'cat';
          $table_attrs = 'data-cat-type="' . $e($type) . '"';
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

<script>
  // Phase 21.7 — make the Add button turn primary once a label is typed,
  // signalling the form is ready to submit. Mirrors dirty-flip's promote
  // pattern but for the "filled" rather than "changed" state.
  document.querySelectorAll('.cat-add-row').forEach(function (form) {
    var input = form.querySelector('.cat-add-input');
    var btn   = form.querySelector('.cat-add-btn');
    if (!input || !btn) return;
    function syncBtn() {
      var ready = input.value.trim().length > 0;
      btn.classList.toggle('btn-pri', ready);
      btn.classList.toggle('btn-sec', !ready);
    }
    input.addEventListener('input', syncBtn);
    syncBtn();
  });

  // Phase 21.7 — custom colour dropdown choreography. The trigger button
  // opens a popup of coloured swatches; clicking a swatch updates the
  // hidden input + trigger background, then dispatches 'change' so
  // dirty-flip wakes the row's Save button.
  document.querySelectorAll('.cat-colour-picker').forEach(function (picker) {
    var trigger = picker.querySelector('[data-colour-trigger]');
    var menu    = picker.querySelector('.cat-colour-menu');
    var hidden  = picker.querySelector('[data-colour-input]');
    var nameSpan = picker.querySelector('.cat-colour-name');
    if (!trigger || !menu || !hidden) return;

    // The menu is position:fixed and PORTALED to <body> on open. Portaling is
    // essential: the table rows keep a lingering `transform: translateY(0)`
    // from the reveal animation, and any transformed ancestor becomes the
    // containing block for position:fixed — which would anchor the menu to the
    // row (off-screen) instead of the viewport. As a direct child of <body>
    // there's no transformed ancestor, so fixed resolves against the viewport.
    function positionMenu() {
      if (menu.parentNode !== document.body) document.body.appendChild(menu);
      var r = trigger.getBoundingClientRect();
      menu.style.left  = r.left + 'px';
      menu.style.top   = (r.bottom + 4) + 'px';
      menu.style.width = r.width + 'px';
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      document.querySelectorAll('.cat-colour-menu').forEach(function (m) {
        if (m !== menu) m.hidden = true;
      });
      var willOpen = menu.hidden;
      menu.hidden = !willOpen;
      if (willOpen) positionMenu();
    });

    window.addEventListener('scroll', function () { if (!menu.hidden) positionMenu(); }, true);
    window.addEventListener('resize', function () { if (!menu.hidden) positionMenu(); });

    menu.querySelectorAll('.cat-colour-opt').forEach(function (opt) {
      opt.addEventListener('click', function (e) {
        e.stopPropagation();
        var value = opt.dataset.value;
        var bg    = opt.style.backgroundColor;
        hidden.value = value;
        trigger.style.backgroundColor = bg;
        if (nameSpan) nameSpan.textContent = value;
        menu.querySelectorAll('.cat-colour-opt').forEach(function (o) {
          o.classList.toggle('is-selected', o === opt);
        });
        picker.dataset.value = value;
        menu.hidden = true;
        // dirty-flip listens to 'change' on form inputs.
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });
  document.addEventListener('click', function () {
    document.querySelectorAll('.cat-colour-menu').forEach(function (m) { m.hidden = true; });
  });
</script>

<!-- Universal Save-button dirty-flip pattern (mirrors /cms/navigation +
     /cms/redirects) lives in the shared cms/_assets/dirty-flip.js module.
     Each row's Save button binds via form="row-form-cat-N"; the module
     reads that attribute and watches every input/select cross-bound to
     that form, including the label text input and the colour picker's
     hidden input above. preview-tab-guard.js's beforeunload guard rides
     the same cross-bound tracking, so leaving the page with an unsaved
     label/colour change prompts to confirm. -->
<script src="/cms/_assets/preview-tab-guard.js" defer></script>
<script src="/cms/_assets/dirty-flip.js" defer></script>
<script>
(function () {
  'use strict';
  var csrf = '<?= $e($csrf_token) ?>';

  function persist(type, ids) {
    var body = new FormData();
    body.append('csrf_token', csrf);
    body.append('type', type);
    ids.forEach(function (id) { body.append('ids[]', id); });
    return fetch('/cms/categories/reorder', {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
    }).then(function (r) {
      return r.json().then(function (j) {
        if (!r.ok || !j.ok) throw new Error(j.error || ('HTTP ' + r.status));
        return j;
      });
    });
  }

  document.querySelectorAll('.cms-table--cat[data-cat-type]').forEach(function (table) {
    var type  = table.getAttribute('data-cat-type');
    var tbody = table.querySelector('tbody');
    var block = table.closest('.cat-block');
    if (!tbody || !block) return;

    // Overlay drop-line: absolutely positioned in .cat-block so it never
    // inserts into the table DOM and causes no layout shift.
    block.style.position = 'relative';
    var line = document.createElement('div');
    line.className = 'cat-drop-line';
    block.appendChild(line);

    var dragging   = null;
    var snapshot   = null;
    var dropBefore = null;

    function items() {
      return Array.from(tbody.querySelectorAll('tr[draggable="true"][data-id]'));
    }

    function hideLine() { line.style.display = 'none'; }

    function showLine(targetRow) {
      var blockRect = block.getBoundingClientRect();
      var rowRect   = targetRow.getBoundingClientRect();
      line.style.top     = (rowRect.top - blockRect.top + block.scrollTop) + 'px';
      line.style.display = 'block';
    }

    function showLineAfterLast() {
      var all = items();
      if (!all.length) { hideLine(); return; }
      var last      = all[all.length - 1];
      var blockRect = block.getBoundingClientRect();
      var rowRect   = last.getBoundingClientRect();
      line.style.top     = (rowRect.bottom - blockRect.top + block.scrollTop) + 'px';
      line.style.display = 'block';
    }

    tbody.addEventListener('dragstart', function (e) {
      var row = e.target.closest('tr[data-id]');
      if (!row || !tbody.contains(row)) return;
      dragging = row;
      snapshot = items().slice();
      row.classList.add('is-dragging');
      if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
    });

    tbody.addEventListener('dragend', function () {
      if (dragging) dragging.classList.remove('is-dragging');
      hideLine();
      dragging   = null;
      snapshot   = null;
      dropBefore = null;
    });

    tbody.addEventListener('dragover', function (e) {
      if (!dragging) return;
      e.preventDefault();
      if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';

      var sibs   = items().filter(function (r) { return r !== dragging; });
      var target = null;
      for (var i = 0; i < sibs.length; i++) {
        var b = sibs[i].getBoundingClientRect();
        if (e.clientY < b.top + b.height / 2) { target = sibs[i]; break; }
      }

      dropBefore = target;
      if (target) showLine(target);
      else showLineAfterLast();
    });

    tbody.addEventListener('dragleave', function (e) {
      if (!tbody.contains(e.relatedTarget)) hideLine();
    });

    tbody.addEventListener('drop', function (e) {
      e.preventDefault();
      if (!dragging) return;
      hideLine();

      var moved = dragging;
      var snap  = snapshot;
      dragging  = null;
      snapshot  = null;

      if (dropBefore) tbody.insertBefore(moved, dropBefore);
      else tbody.appendChild(moved);
      dropBefore = null;

      var ids = items().map(function (r) { return r.getAttribute('data-id'); });
      persist(type, ids).catch(function (err) {
        if (snap) snap.forEach(function (el) { tbody.appendChild(el); });
        alert('Reorder failed: ' + (err && err.message ? err.message : 'unknown error'));
      });
    });
  });
})();
</script>

</body>
</html>
