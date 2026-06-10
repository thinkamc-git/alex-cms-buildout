<?php
/**
 * cms/views/navigation.php — Navigation editor (Phase 20).
 *
 * Two stacked sections (Header / Footer). Each row is a nav_items row
 * with inline-editable fields: label, nav_key, target_type + dependent
 * picker, highlight, color. Drag-handle on the left reorders within a
 * zone (POSTs to /cms/navigation/reorder).
 *
 * The dependent picker swaps based on target_type:
 *   custom   → text input for custom_url
 *   page     → text input for target_slug (matches a marketing page slug)
 *   index    → select of indexes by id
 *   category → select of categories by id
 *   series   → select of series by id
 *   content  → select of content rows by id (recent published, capped)
 *
 * Broken items (resolver returns NULL) get a BROKEN badge.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/nav.php';
require_once __DIR__ . '/../../lib/pages.php';

Auth::require_login();
$csrf_token = Csrf::token();

$errors = [];
$flash  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_item') {
            save_nav_item([
                'id'              => (int)($_POST['id'] ?? 0),
                'zone'            => (string)($_POST['zone'] ?? 'header'),
                'label'           => (string)($_POST['label'] ?? ''),
                'nav_key'         => (string)($_POST['nav_key'] ?? ''),
                'target_type'     => (string)($_POST['target_type'] ?? 'custom'),
                'target_id'       => $_POST['target_id'] ?? null,
                'target_slug'     => (string)($_POST['target_slug'] ?? ''),
                'custom_url'      => (string)($_POST['custom_url'] ?? ''),
                'highlight'       => (string)($_POST['highlight'] ?? 'none'),
                'highlight_text'  => (string)($_POST['highlight_text'] ?? ''),
                'highlight_color' => (string)($_POST['highlight_color'] ?? ''),
                // "Show on mobile" checkbox (default on). Stored inverted as
                // hide_mobile: shown → 0, hidden → 1.
                'hide_mobile'     => (($_POST['show_mobile'] ?? '1') === '1') ? 0 : 1,
                // Items are active unless the nightly broken-target sweep
                // deactivates them. The editor has no manual hide toggle.
                'is_active'       => 1,
            ]);
            $flash = (int)($_POST['id'] ?? 0) > 0 ? 'Item saved.' : 'Item added.';
        } elseif ($action === 'delete_item') {
            delete_nav_item((int)($_POST['id'] ?? 0));
            $flash = 'Item deleted.';
        }
        if (count($errors) === 0) {
            header('Location: /cms/navigation?flash=' . rawurlencode($flash));
            exit;
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

$header_items = list_nav_items('header');
$footer_items = list_nav_items('footer');

// Build option lists for the dependent pickers.
$indexes = db()->query('SELECT id, slug, title FROM indexes ORDER BY slug')->fetchAll();
$categories = db()->query('SELECT id, label, value_slug FROM categories ORDER BY label')->fetchAll();
$series_rows = db()->query('SELECT id, name, slug FROM series ORDER BY name')->fetchAll();
$content_rows = db()->query(
    "SELECT id, type, slug, title FROM content
      WHERE status = 'published' AND published_status = 'live'
      ORDER BY updated_at DESC
      LIMIT 100"
)->fetchAll();
$page_files = array_values(array_filter(list_pages_files(), fn($f) => $f['kind'] === 'page'));

// Compute broken-target flags up front so we can badge them in the UI.
$broken_ids = [];
foreach (array_merge($header_items, $footer_items) as $it) {
    if (resolve_nav_target($it) === null) $broken_ids[(int)$it['id']] = true;
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Auto-contrast text color for a given background hex. Returns '#000' for
// light backgrounds, '#fff' for dark ones, '#fff' if the input isn't a
// parseable hex (since the default red #d63031 reads well on white). Matches
// navContrast() in the JS below so server-render and client-render agree.
$contrast_text = static function (string $bg): string {
    $bg = trim($bg);
    if ($bg === '' || $bg[0] !== '#') return '#fff';
    $hex = substr($bg, 1);
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '#fff';
    $r = (int)hexdec(substr($hex, 0, 2));
    $g = (int)hexdec(substr($hex, 2, 2));
    $b = (int)hexdec(substr($hex, 4, 2));
    $brightness = (299 * $r + 587 * $g + 114 * $b) / 1000;
    return $brightness > 128 ? '#000' : '#fff';
};

// Server-side render of the pill_text + dot cell. Wraps the input AND a
// dot span in a .np-mark container; CSS picks which one shows based on the
// hl-* class. The container's CSS vars (--np-color / --np-contrast) drive
// the pill input's background + text colour, so the input itself IS the
// live preview when highlight=pill. Mirrored by navRefreshMark() in JS.
$renderMarkCell = static function (string $highlight, string $pill_text, string $color) use ($e, $contrast_text): string {
    $resolved_color = $color !== '' ? $color : '#d63031';
    $fg = $contrast_text($resolved_color);
    $style = '--np-color:' . $e($resolved_color) . ';--np-contrast:' . $e($fg);
    $class = 'np-mark hl-' . $e($highlight);
    return '<span class="' . $class . '" data-mark style="' . $style . '">'
         . '<input type="text" name="highlight_text" value="' . $e($pill_text) . '" placeholder="NEW" data-pill-text>'
         . '<span class="np-dot" aria-hidden="true"></span>'
         . '</span>';
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Navigation — alexmchong.ca CMS</title>
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
  /* Navigation row-form. Shared mechanics (grid container, save dirty-flip,
     delete hover-reveal, drag-affordance, add-row tint) live in
     style-cms.css under .rowform-*. View-specific bits below:
       1. The 11-column grid template (set via --rowform-cols).
       2. The is-broken row tint.
       3. The grip handle, np-mark preview cell, nav-picker, color column,
          pill-broken badge, and zone-help line.
  */
  .nav-list {
    --rowform-cols:
      18px                  /* grip                       */
      minmax(160px, 1.4fr)  /* label                      */
      90px                  /* nav_key                    */
      100px                 /* target_type                */
      minmax(180px, 1.6fr)  /* picker                     */
      80px                  /* highlight                  */
      96px                  /* pill text / dot preview    */
      92px                  /* color                      */
      44px                  /* hide on mobile             */
      64px                  /* save   — FIXED so the header grid matches the   */
      34px                  /* delete   row grid (empty header cells vs buttons */
      auto;                 /* broken   made the fr columns resolve differently */
  }
  .nav-row.is-broken { background:#fff5f5; border-color:#f5b0b0; }
  .nav-row .grip { cursor:grab; color:var(--muted); user-select:none; text-align:center; font-size:14px; }
  .nav-row .nav-picker { display:grid; }
  .nav-row .nav-picker > * { width:100%; box-sizing:border-box; }
  /* The color input keeps its column slot even when highlight=none. */
  .nav-row [data-color].is-off { visibility:hidden; }
  /* Pill/dot preview cell — IS the pill_text input area. The input
     itself adopts pill styling when highlight=pill so the user types
     directly into a live preview. When highlight=dot the input is hidden
     and a dot circle takes its place. */
  .np-mark { position:relative; min-width:0; display:flex; align-items:center; gap:6px; }
  .np-mark .np-dot {
    width:14px; height:14px; border-radius:50%;
    background: var(--np-color, #d63031);
    display:none;
  }
  .np-mark.hl-dot .np-dot   { display:inline-block; }
  .np-mark.hl-dot input     { display:none; }
  .np-mark.hl-pill input {
    background: var(--np-color, #d63031);
    color: var(--np-contrast, #fff);
    border-color: transparent;
    font-family: var(--font-cond);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    text-align: center;
    padding: 4px 8px;
    border-radius: 10px;
    /* Hug the text — same horizontal padding regardless of length. */
    field-sizing: content;
    width: auto;
    min-width: 40px;
    max-width: 100%;
  }
  .np-mark.hl-pill input::placeholder { color: var(--np-contrast, #fff); opacity: 0.6; }
  /* Phase 21.7 — the pill-styled input IS the visible pill, so the
     generic rowform field hover (darken border + bg → canvas-bg) would
     wipe out the pill colour and make the hover invisible. Keep the
     pill look on hover; focus gets a subtle white inset ring instead. */
  .rowform-row .np-mark.hl-pill input:hover {
    background: var(--np-color, #d63031);
    border-color: transparent;
  }
  .rowform-row .np-mark.hl-pill input:focus {
    background: var(--np-color, #d63031);
    border-color: transparent;
    outline: none;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.55);
  }
  .np-mark.hl-none input { visibility:hidden; }
  .pill-broken { display:inline-block; font-family:var(--font-cond); font-size:9px; letter-spacing:0.08em; text-transform:uppercase; padding:2px 6px; border-radius:3px; background:var(--c-terracotta); color:white; font-weight:600; vertical-align:middle; margin-left:6px; }
  .nav-zone-help { font-size:11px; color:var(--muted); padding:0 var(--space-24) var(--space-12); }
</style>
</head>
<body>

<?php
$breadcrumb = 'Navigation';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'navigation';
  $nav_counts = ['navigation' => count($header_items) + count($footer_items)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-navigation">
      <?php
      $title    = 'Navigation';
      $subtitle = 'Header and footer link lists. Drag to reorder. Items whose target no longer exists are flagged BROKEN and hidden from the public site until you fix them.';
      $actions  = '<a href="/" target="_blank" rel="noopener" class="btn-sec">Open homepage ↗</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $heading = "Couldn't save:";
      require __DIR__ . '/../partials/form-errors.php';
      require __DIR__ . '/../partials/flash.php';
      ?>

      <div class="content-area">
      <?php
      // Render-one-zone helper (closure to keep scope tight).
      $renderZone = function (string $zone, array $items) use ($e, $csrf_token, $broken_ids,
          $indexes, $categories, $series_rows, $content_rows, $page_files, $renderMarkCell): void {
      $zone_sub = $zone === 'header'
        ? 'Top nav shown above every public page'
        : 'Bottom links shown in the page footer';
      ?>
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label"><?= $e(ucfirst($zone)) ?></span>
              <span class="content-block-sublabel"><?= $e($zone_sub) ?></span>
            </div>
            <span class="content-block-count"><?= count($items) ?> item<?= count($items)===1?'':'s' ?></span>
          </div>

          <div class="rowform-list nav-list reveal" data-zone="<?= $e($zone) ?>" data-csrf="<?= $e($csrf_token) ?>">
            <div class="rowform-headers">
              <span></span>
              <span>Label</span>
              <span>Key</span>
              <span>Type</span>
              <span>Target</span>
              <span>Mark</span>
              <span>Label</span>
              <span>Color</span>
              <span title="Show on mobile (phone ≤767) — checked = visible" style="display:inline-flex;justify-content:center">
                <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-label="Show on mobile"><rect x="4" y="1.5" width="6" height="11" rx="1" stroke="currentColor" stroke-width="1.2"/><line x1="6" y1="10.5" x2="8" y2="10.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
              </span>
              <span></span>
              <span></span>
              <span></span>
            </div>
            <?php foreach ($items as $it): $broken = isset($broken_ids[(int)$it['id']]); ?>
              <div class="rowform-row nav-row<?= $broken ? ' is-broken' : '' ?>" draggable="true" data-id="<?= (int)$it['id'] ?>">
                <div class="grip" title="Drag to reorder">⋮⋮</div>
                <form method="post" action="/cms/navigation" id="nav-form-<?= (int)$it['id'] ?>" style="display:contents">
                  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <input type="hidden" name="zone" value="<?= $e($zone) ?>">
                  <input type="hidden" name="action" value="save_item">
                  <input type="text" name="label" value="<?= $e((string)$it['label']) ?>" placeholder="Label" required>
                  <input type="text" name="nav_key" value="<?= $e((string)$it['nav_key']) ?>" placeholder="nav_key">
                  <select name="target_type" onchange="navTypeChanged(this)">
                    <?php foreach (NAV_TARGET_TYPES as $t): ?>
                      <option value="<?= $t ?>"<?= ($it['target_type'] === $t) ? ' selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                  </select>
                  <span class="nav-picker">
                    <input type="text" name="custom_url" value="<?= $e((string)$it['custom_url']) ?>" placeholder="/path/ or https://…" data-picker="custom" style="<?= $it['target_type']==='custom'?'':'display:none' ?>">
                    <input type="text" name="target_slug" value="<?= $e((string)$it['target_slug']) ?>" placeholder="page-slug (e.g. about)" data-picker="page" style="<?= $it['target_type']==='page'?'':'display:none' ?>">
                    <select name="target_id_index" data-picker="index" style="<?= $it['target_type']==='index'?'':'display:none' ?>">
                      <option value="">— Choose index —</option>
                      <?php foreach ($indexes as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"<?= ($it['target_type']==='index' && (int)$it['target_id']===(int)$r['id'])?' selected':'' ?>><?= $e($r['title'] ?: $r['slug']) ?> (/<?= $e($r['slug']) ?>/)</option>
                      <?php endforeach; ?>
                    </select>
                    <select name="target_id_category" data-picker="category" style="<?= $it['target_type']==='category'?'':'display:none' ?>">
                      <option value="">— Choose category —</option>
                      <?php foreach ($categories as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"<?= ($it['target_type']==='category' && (int)$it['target_id']===(int)$r['id'])?' selected':'' ?>><?= $e($r['label']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <select name="target_id_series" data-picker="series" style="<?= $it['target_type']==='series'?'':'display:none' ?>">
                      <option value="">— Choose series —</option>
                      <?php foreach ($series_rows as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"<?= ($it['target_type']==='series' && (int)$it['target_id']===(int)$r['id'])?' selected':'' ?>><?= $e($r['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <select name="target_id_content" data-picker="content" style="<?= $it['target_type']==='content'?'':'display:none' ?>">
                      <option value="">— Choose content —</option>
                      <?php foreach ($content_rows as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"<?= ($it['target_type']==='content' && (int)$it['target_id']===(int)$r['id'])?' selected':'' ?>><?= $e($r['type']) ?>: <?= $e($r['title']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="target_id" value="<?= $e((string)($it['target_id'] ?? '')) ?>">
                  </span>
                  <select name="highlight" onchange="navHighlightChanged(this)">
                    <?php foreach (NAV_HIGHLIGHTS as $h): ?>
                      <option value="<?= $h ?>"<?= ($it['highlight']===$h)?' selected':'' ?>><?= $h === 'none' ? '—' : $h ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?= $renderMarkCell((string)$it['highlight'], (string)$it['highlight_text'], (string)$it['highlight_color']) ?>
                  <input type="text" name="highlight_color" value="<?= $e((string)$it['highlight_color']) ?>" placeholder="#d63031" data-color<?= $it['highlight']==='none' ? ' class="is-off"' : '' ?>>
                  <label class="ds-check" title="Show on mobile (phone ≤767) — uncheck to hide" style="justify-self:center">
                    <input type="hidden" name="show_mobile" value="0">
                    <input type="checkbox" name="show_mobile" value="1"<?= empty($it['hide_mobile']) ? ' checked' : '' ?>>
                    <span class="ds-check-box"><svg viewBox="0 0 14 14" fill="none"><path d="M3 7.5 6 10.5 11 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                  </label>
                  <button type="submit" class="btn-sec btn-tiny" data-save-btn>Save</button>
                </form>
                <form method="post" action="/cms/navigation" style="display:inline" data-confirm="Delete &quot;<?= $e((string)$it['label']) ?>&quot;? This removes the link from the public site.">
                  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <input type="hidden" name="action" value="delete_item">
                  <button type="submit" class="btn-icon btn-icon-danger" title="Delete" aria-label="Delete">
                    <svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </form>
                <?php if ($broken): ?><span class="pill-broken">Broken</span><?php endif; ?>
              </div>
            <?php endforeach; ?>

            <!-- Add new row -->
            <div class="rowform-row rowform-add-row nav-row nav-add-row">
              <div class="grip">+</div>
              <form method="post" action="/cms/navigation" style="display:contents">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                <input type="hidden" name="zone" value="<?= $e($zone) ?>">
                <input type="hidden" name="action" value="save_item">
                <input type="text" name="label" placeholder="New item label" required>
                <input type="text" name="nav_key" placeholder="nav_key">
                <select name="target_type" onchange="navTypeChanged(this)">
                  <?php foreach (NAV_TARGET_TYPES as $t): ?>
                    <option value="<?= $t ?>"<?= ($t==='custom')?' selected':'' ?>><?= $t ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="nav-picker">
                  <input type="text" name="custom_url" placeholder="/path/ or https://…" data-picker="custom">
                  <input type="text" name="target_slug" placeholder="page-slug" data-picker="page" style="display:none">
                  <select name="target_id_index" data-picker="index" style="display:none">
                    <option value="">— Choose index —</option>
                    <?php foreach ($indexes as $r): ?><option value="<?= (int)$r['id'] ?>"><?= $e($r['title'] ?: $r['slug']) ?></option><?php endforeach; ?>
                  </select>
                  <select name="target_id_category" data-picker="category" style="display:none">
                    <option value="">— Choose category —</option>
                    <?php foreach ($categories as $r): ?><option value="<?= (int)$r['id'] ?>"><?= $e($r['label']) ?></option><?php endforeach; ?>
                  </select>
                  <select name="target_id_series" data-picker="series" style="display:none">
                    <option value="">— Choose series —</option>
                    <?php foreach ($series_rows as $r): ?><option value="<?= (int)$r['id'] ?>"><?= $e($r['name']) ?></option><?php endforeach; ?>
                  </select>
                  <select name="target_id_content" data-picker="content" style="display:none">
                    <option value="">— Choose content —</option>
                    <?php foreach ($content_rows as $r): ?><option value="<?= (int)$r['id'] ?>"><?= $e($r['type']) ?>: <?= $e($r['title']) ?></option><?php endforeach; ?>
                  </select>
                  <input type="hidden" name="target_id">
                </span>
                <select name="highlight" onchange="navHighlightChanged(this)">
                  <?php foreach (NAV_HIGHLIGHTS as $h): ?>
                    <option value="<?= $h ?>"<?= ($h==='none')?' selected':'' ?>><?= $h ?></option>
                  <?php endforeach; ?>
                </select>
                <?= $renderMarkCell('none', '', '') ?>
                <input type="text" name="highlight_color" placeholder="#d63031" data-color class="is-off">
                <button type="submit" class="btn-sec btn-tiny">Add</button>
              </form>
            </div>
          </div>
        </div>
      <?php
      };
      $renderZone('header', $header_items);
      $renderZone('footer', $footer_items);
      ?>
      </div><!-- /.content-area -->
    </div>
  </main>
</div>

<script>
  // Type-change: show only the relevant picker; pre-populate the
  // single canonical target_id hidden field from the visible select.
  function navTypeChanged(select) {
    const row = select.closest('form');
    const type = select.value;
    row.querySelectorAll('[data-picker]').forEach(el => {
      el.style.display = (el.getAttribute('data-picker') === type) ? '' : 'none';
    });
    navSyncTargetId(row);
  }
  function navHighlightChanged(select) {
    const form = select.closest('form');
    navRefreshMark(form);
  }
  // Auto-contrast text colour for a given background hex. Mirrors the
  // PHP nav_contrast_text() so server-render and client-render agree.
  function navContrast(bg) {
    if (!bg || bg[0] !== '#') return '#fff';
    let h = bg.slice(1);
    if (h.length === 3) h = h.split('').map(c => c + c).join('');
    if (!/^[0-9a-fA-F]{6}$/.test(h)) return '#fff';
    const r = parseInt(h.slice(0, 2), 16);
    const g = parseInt(h.slice(2, 4), 16);
    const b = parseInt(h.slice(4, 6), 16);
    return ((299 * r + 587 * g + 114 * b) / 1000) > 128 ? '#000' : '#fff';
  }
  // field-sizing: content fallback for browsers that don't support it.
  // Measures the input's current text width in a hidden ghost span and
  // sets input.style.width so the pill hugs its text.
  const navSupportsFieldSizing = (() => {
    try { return CSS.supports && CSS.supports('field-sizing', 'content'); }
    catch (_) { return false; }
  })();
  function navFitPillInput(input) {
    if (navSupportsFieldSizing) return;
    const ghost = document.createElement('span');
    const cs = getComputedStyle(input);
    ['fontSize','fontFamily','fontWeight','letterSpacing','textTransform','paddingLeft','paddingRight'].forEach(p => { ghost.style[p] = cs[p]; });
    ghost.style.visibility = 'hidden';
    ghost.style.position = 'absolute';
    ghost.style.whiteSpace = 'pre';
    ghost.textContent = input.value || input.placeholder || ' ';
    document.body.appendChild(ghost);
    const w = ghost.getBoundingClientRect().width;
    document.body.removeChild(ghost);
    input.style.width = Math.max(w + 8, 40) + 'px';
  }
  // Refresh the .np-mark cell — swap hl-* class + drive --np-color /
  // --np-contrast CSS vars. Also toggle the color input's is-off state
  // and fit the pill input width.
  function navRefreshMark(form) {
    const hl    = form.querySelector('select[name=highlight]')?.value || 'none';
    const colIn = form.querySelector('input[name=highlight_color]')?.value || '';
    const col   = colIn.trim() !== '' ? colIn.trim() : '#d63031';
    const fg    = navContrast(col);
    const mark  = form.querySelector('[data-mark]');
    if (mark) {
      mark.classList.remove('hl-none', 'hl-dot', 'hl-pill');
      mark.classList.add('hl-' + hl);
      mark.style.setProperty('--np-color', col);
      mark.style.setProperty('--np-contrast', fg);
    }
    const colorInput = form.querySelector('input[name=highlight_color]');
    if (colorInput) colorInput.classList.toggle('is-off', hl === 'none');
    const pillInput = form.querySelector('input[data-pill-text]');
    if (pillInput && hl === 'pill') navFitPillInput(pillInput);
  }
  // Initial fit pass on load — sets pill input widths so they hug their
  // text before any user interaction.
  document.querySelectorAll('.np-mark.hl-pill input[data-pill-text]').forEach(navFitPillInput);
  document.querySelectorAll('.nav-row form').forEach(f => {
    ['select[name=highlight]','input[name=highlight_color]','input[name=highlight_text]'].forEach(sel => {
      const el = f.querySelector(sel);
      if (!el) return;
      const evt = el.tagName === 'SELECT' ? 'change' : 'input';
      el.addEventListener(evt, () => navRefreshMark(f));
    });
  });

  // Save-button dirty-flip (ghost → primary on first edit, then "Saved"
  // pulse on submit) lives in the shared cms/_assets/dirty-flip.js module,
  // loaded via a sibling <script> tag below.
  function navSyncTargetId(form) {
    // Before submit, copy the visible target_id_* value into target_id.
    const type = form.querySelector('select[name=target_type]').value;
    const hidden = form.querySelector('input[name=target_id]');
    if (!hidden) return;
    if (type === 'index')         hidden.value = form.querySelector('select[name=target_id_index]').value;
    else if (type === 'category') hidden.value = form.querySelector('select[name=target_id_category]').value;
    else if (type === 'series')   hidden.value = form.querySelector('select[name=target_id_series]').value;
    else if (type === 'content')  hidden.value = form.querySelector('select[name=target_id_content]').value;
    else                          hidden.value = '';
  }
  document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', () => navSyncTargetId(f));
  });

  // Drag-reorder within a zone.
  document.querySelectorAll('.nav-list').forEach(list => {
    const zone = list.getAttribute('data-zone');
    const csrf = list.getAttribute('data-csrf');
    let dragged = null;

    list.querySelectorAll('.nav-row[draggable=true]').forEach(row => {
      row.addEventListener('dragstart', e => {
        dragged = row;
        row.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
      });
      row.addEventListener('dragend', () => {
        row.classList.remove('is-dragging');
        list.querySelectorAll('.nav-row').forEach(r => r.classList.remove('is-over-top','is-over-bottom'));
      });
      row.addEventListener('dragover', e => {
        e.preventDefault();
        if (!dragged || dragged === row) return;
        const r = row.getBoundingClientRect();
        const top = (e.clientY - r.top) < (r.height / 2);
        row.classList.toggle('is-over-top', top);
        row.classList.toggle('is-over-bottom', !top);
      });
      row.addEventListener('dragleave', () => {
        row.classList.remove('is-over-top','is-over-bottom');
      });
      row.addEventListener('drop', e => {
        e.preventDefault();
        if (!dragged || dragged === row) return;
        const r = row.getBoundingClientRect();
        const top = (e.clientY - r.top) < (r.height / 2);
        if (top) row.parentNode.insertBefore(dragged, row);
        else     row.parentNode.insertBefore(dragged, row.nextSibling);
        // Persist new order.
        const ids = Array.from(list.querySelectorAll('.nav-row[data-id]')).map(el => el.getAttribute('data-id'));
        fetch('/cms/navigation/reorder', {
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body: 'csrf_token=' + encodeURIComponent(csrf) + '&zone=' + encodeURIComponent(zone) + '&ids=' + encodeURIComponent(ids.join(','))
        }).catch(() => alert('Reorder failed — refresh and try again.'));
      });
    });
  });
</script>
<script src="/cms/_assets/dirty-flip.js" defer></script>

</body>
</html>
