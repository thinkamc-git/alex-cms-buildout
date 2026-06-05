<?php
/**
 * cms/views/design-system.php — in-CMS Design System viewer (Phase 22.5).
 *
 * A read-only catalogue of the admin design system: tokens, buttons,
 * pills/badges, fields, tables, cards, and title roles. Each entry shows a
 * live render, its canonical class name(s), and the slice it lives in
 * (Root / CMS) so the author can see what's available without leaving the
 * CMS or reading CSS.
 *
 * DOGFOOD NOTE (Phase 22.5 decision): unlike every other CMS view — which
 * still links the 8 individual /_ds/css/*.css modules + style-cms.css — this
 * page loads ONLY the system-cms.css *barrel* + style-cms.css. It is the
 * first consumer of the barrel, proving it resolves on a real page before
 * Phase 22.6 flips every view to barrel-only. If this page renders correctly,
 * the barrel's @import order + paths are good.
 *
 * Lean scope (Phase 22.5): the catalogue covers the core component families.
 * The exhaustive "every component + expected markup + do/don't" depth and the
 * public-side showcase rebuild are Phase 22.6 work. See BUILD-PLAN §31/§32.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/**
 * Render one catalogue entry: a live preview, the canonical class name(s),
 * and a slice tag. $render is raw HTML (trusted — authored here, not user
 * input). $slice is 'Root' or 'CMS'.
 */
$item = static function (string $render, string $class, string $slice = 'CMS', string $note = '') use ($e): string {
    $sliceCls = $slice === 'Root' ? 'ds-slice ds-slice--root' : 'ds-slice ds-slice--cms';
    $noteHtml = $note !== '' ? '<div class="ds-note">' . $e($note) . '</div>' : '';
    return '<div class="ds-item">'
         . '<div class="ds-render">' . $render . '</div>'
         . '<div class="ds-meta"><code class="ds-class">' . $e($class) . '</code>'
         . '<span class="' . $sliceCls . '">' . $e($slice) . '</span></div>'
         . $noteHtml
         . '</div>';
};

// The 18-hue category colour pool (Root tier — tokens.css).
$COLOUR_POOL = [
    'rust','terracotta','clay','amber','ochre','olive','moss','forest','sage',
    'teal','ocean','denim','indigo','purple','violet','plum','mauve','rose',
];

// Type-scale tokens (Root tier) — name => px label.
$TYPE_SCALE = [
    '--text-display' => '54', '--text-h0' => '48', '--text-h1' => '40',
    '--text-h2' => '32', '--text-h3' => '28', '--text-h4' => '24',
    '--text-h5' => '22', '--text-body-lg' => '20', '--text-body' => '18',
    '--text-md' => '16', '--text-sm' => '15', '--text-base' => '14',
    '--text-meta' => '13', '--text-pill' => '12', '--text-label' => '11',
    '--text-tiny' => '10', '--text-micro' => '9',
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>CMS Design System — alexmchong.ca CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@500;600;700&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<?php /* DOGFOOD: barrel only (not the 8 individual modules) + style-cms overrides. */ ?>
<link rel="stylesheet" href="/_ds/css/system-cms.css">
<link rel="stylesheet" href="/cms/_assets/style-cms.css">
<style>
  /* ── Viewer-local catalogue scaffolding (Phase 22.5) ──
     Page-scoped; NOT part of the DS. Phase 22.6 may rebuild this against the
     public showcase. Uses DS tokens so it stays on-system. */
  .ds-cat { margin-bottom: var(--space-32); }
  .ds-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: var(--space-16); }
  .ds-grid.is-wide { grid-template-columns: 1fr; }
  .ds-item { border: var(--rule-faint); border-radius: var(--r-card); background: var(--surface); overflow: hidden; }
  .ds-render {
    padding: var(--space-20) var(--space-16);
    display: flex; flex-wrap: wrap; gap: var(--space-8); align-items: center;
    min-height: 64px;
    background-color: var(--neutral);
    background-image: url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%224%22%20height%3D%224%22%3E%3Crect%20width%3D%224%22%20height%3D%224%22%20fill%3D%22%23e8e8e8%22/%3E%3Ccircle%20cx%3D%220%22%20cy%3D%220%22%20r%3D%221.5%22%20fill%3D%22%23b8b6b2%22%20opacity%3D%220.15%22/%3E%3C/svg%3E");
    background-size: 4px 4px;
  }
  .ds-meta { display: flex; align-items: center; justify-content: space-between; gap: var(--space-8); padding: var(--space-8) var(--space-12); border-top: var(--rule-faint); }
  .ds-class { font-family: var(--font-mono); font-size: var(--text-micro); color: var(--secondary); background: var(--ink-08); padding: 2px 6px; border-radius: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ds-slice { font-family: var(--font-cond); font-size: 9px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; padding: 2px 7px; border-radius: 2px; flex-shrink: 0; }
  .ds-slice--root { color: var(--c-denim); background: color-mix(in srgb, var(--c-denim) 10%, transparent); border: 1px solid color-mix(in srgb, var(--c-denim) 26%, transparent); }
  .ds-slice--cms  { color: var(--c-forest); background: color-mix(in srgb, var(--c-forest) 10%, transparent); border: 1px solid color-mix(in srgb, var(--c-forest) 26%, transparent); }
  .ds-note { padding: 0 var(--space-12) var(--space-8); font-size: var(--text-micro); color: var(--muted); line-height: 1.5; }
  .ds-legend { display: flex; gap: var(--space-16); align-items: center; flex-wrap: wrap; margin-bottom: var(--space-24); padding: var(--space-12) var(--space-16); background: var(--canvas-raised); border: var(--rule-faint); border-radius: var(--r-card); }
  .ds-legend-item { display: flex; align-items: center; gap: 6px; font-size: var(--text-micro); color: var(--secondary); }
  /* Token swatch grid (reuses .swatch from components.css) */
  .ds-swatch-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: var(--space-12); }
  .ds-swatch { width: 100%; aspect-ratio: 1.7; border-radius: var(--r-tag); border: var(--rule-faint); }
  .ds-swatch-name { font-family: var(--font-cond); font-size: var(--text-tiny); font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--primary); margin-top: 5px; }
  .ds-swatch-tok { font-family: var(--font-mono); font-size: 9px; color: var(--muted); }
  .ds-type-row { display: flex; align-items: baseline; gap: var(--space-16); padding: 6px 0; border-bottom: 1px solid var(--ink-08); }
  .ds-type-tok { font-family: var(--font-mono); font-size: var(--text-micro); color: var(--muted); width: 130px; flex-shrink: 0; }
  .ds-type-px  { font-family: var(--font-mono); font-size: var(--text-micro); color: var(--muted); width: 36px; flex-shrink: 0; text-align: right; }
  .ds-type-sample { font-family: var(--font); font-weight: 500; color: var(--primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
</head>
<body>

<?php
$breadcrumb = 'CMS Design System';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'design-system';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-design-system">
      <?php
      $title    = 'CMS Design System';
      $subtitle = 'Live catalogue of the admin (CMS) design system — distinct from the public website design system. Every component with its canonical class name and the slice it lives in. Read-only reference; edit the CSS in _design-system/css/ to change anything here.';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">

        <div class="ds-legend">
          <span class="ds-legend-item"><span class="ds-slice ds-slice--root">Root</span> tokens.css — shared primitives</span>
          <span class="ds-legend-item"><span class="ds-slice ds-slice--cms">CMS</span> system-cms.css — admin components</span>
          <span class="ds-legend-item" style="margin-left:auto;font-family:var(--font-mono)">This page loads <code>system-cms.css</code> (barrel) only — dogfooding it ahead of Phase 22.6.</span>
        </div>

        <!-- ═══ TOKENS — Colour pool ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Tokens · Colour pool</span>
            <span class="content-block-count">18 hues · <code class="ds-class">--c-{name}</code></span>
          </div>
          <div class="ds-swatch-grid">
            <?php foreach ($COLOUR_POOL as $c): ?>
              <div>
                <div class="ds-swatch" style="background:var(--c-<?= $e($c) ?>)"></div>
                <div class="ds-swatch-name"><?= $e($c) ?></div>
                <div class="ds-swatch-tok">--c-<?= $e($c) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- ═══ TOKENS — Type scale ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Tokens · Type scale</span>
            <span class="content-block-count">17 steps · <code class="ds-class">--text-{role}</code></span>
          </div>
          <div class="table-card" style="padding:var(--space-12) var(--space-16)">
            <?php foreach ($TYPE_SCALE as $tok => $px): ?>
              <div class="ds-type-row">
                <span class="ds-type-tok"><?= $e($tok) ?></span>
                <span class="ds-type-px"><?= $e($px) ?>px</span>
                <span class="ds-type-sample" style="font-size:var(<?= $e($tok) ?>)">The quick brown fox</span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- ═══ BUTTONS ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Buttons</span>
            <span class="content-block-count">Condensed uppercase · canonical set</span>
          </div>
          <div class="ds-grid">
            <?= $item('<button class="btn-pri">Publish</button>', '.btn-pri') ?>
            <?= $item('<button class="btn-sec">Edit</button>', '.btn-sec') ?>
            <?= $item('<button class="btn-danger">Delete</button>', '.btn-danger') ?>
            <?= $item('<button class="btn-icon btn-icon-danger" title="Delete" aria-label="Delete"><svg viewBox="0 0 14 14" fill="none" style="width:14px;height:14px"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>', '.btn-icon · .btn-icon-danger') ?>
            <?= $item('<button class="btn-pri btn-tiny">Save</button> <button class="btn-sec btn-tiny">Cancel</button>', '.btn-tiny', 'CMS', 'Size modifier — composable with any button.') ?>
            <?= $item('<button class="btn-add-dashed">+ Add row</button>', '.btn-add-dashed') ?>
          </div>
        </div>

        <!-- ═══ STAGE PILLS ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Stage pills</span>
            <span class="content-block-count">Pipeline status · <code class="ds-class">.pill .pill-{stage}</code></span>
          </div>
          <div class="ds-grid">
            <?= $item('<span class="pill pill-idea">Idea</span>', '.pill .pill-idea') ?>
            <?= $item('<span class="pill pill-concept">Concept</span>', '.pill .pill-concept') ?>
            <?= $item('<span class="pill pill-outline">Outline</span>', '.pill .pill-outline') ?>
            <?= $item('<span class="pill pill-draft">Draft</span>', '.pill .pill-draft') ?>
            <?= $item('<span class="pill pill-live">Live</span>', '.pill .pill-live') ?>
            <?= $item('<span class="pill pill-scheduled">Scheduled</span>', '.pill .pill-scheduled') ?>
            <?= $item('<span class="pill pill-hidden">Hidden</span>', '.pill .pill-hidden') ?>
          </div>
        </div>

        <!-- ═══ TYPE + STATUS BADGES ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Type &amp; status badges</span>
            <span class="content-block-count">Content-type + publish state</span>
          </div>
          <div class="ds-grid">
            <?= $item('<span class="type-badge tb-article">Article</span>', '.type-badge .tb-article') ?>
            <?= $item('<span class="type-badge tb-journal">Journal</span>', '.type-badge .tb-journal') ?>
            <?= $item('<span class="type-badge tb-live-session">Live Session</span>', '.type-badge .tb-live-session') ?>
            <?= $item('<span class="type-badge tb-experiment">Experiment</span>', '.type-badge .tb-experiment') ?>
            <?= $item('<span class="st st-pub">Published</span>', '.st .st-pub', 'Root', 'Defined in status.css (Root-loaded slice).') ?>
            <?= $item('<span class="st st-dft">Draft</span>', '.st .st-dft', 'Root') ?>
            <?= $item('<span class="st st-sch">Scheduled</span>', '.st .st-sch', 'Root') ?>
          </div>
        </div>

        <!-- ═══ TAGS + LABELS ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Tags &amp; labels</span>
          </div>
          <div class="ds-grid">
            <?= $item('<span class="tag">Series</span>', '.tag') ?>
            <?= $item('<span class="tag special">Featured</span>', '.tag.special') ?>
            <?= $item('<span class="val-pill">value-slug</span>', '.val-pill') ?>
            <?= $item('<span class="cat-label"><span class="cat-label-dot" style="background:var(--c-forest)"></span>Leading Design</span>', '.cat-label .cat-label-dot') ?>
          </div>
        </div>

        <!-- ═══ FORM FIELDS ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Form fields</span>
          </div>
          <div class="ds-grid is-wide">
            <?= $item('<div class="field-group" style="margin:0;width:100%"><label class="field-label">Title <span class="field-req">*</span></label><input class="field-input" value="A Discipline Reset"></div>', '.field-input') ?>
            <?= $item('<div class="field-group" style="margin:0;width:100%"><label class="field-label">Stage</label><select class="field-select"><option>Draft</option><option>Published</option></select></div>', '.field-select') ?>
            <?= $item('<div class="field-group" style="margin:0;width:100%"><label class="field-label">Slug</label><div class="slug-field"><span class="slug-prefix">/articles/</span><input class="slug-input" value="a-discipline-reset"></div></div>', '.slug-field .slug-prefix .slug-input') ?>
            <?= $item('<div style="width:100%"><div class="field-note-box">Slugs are permanent once published — changing one issues a 301 redirect.</div></div>', '.field-note-box') ?>
          </div>
        </div>

        <!-- ═══ TABLE ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Table</span>
            <span class="content-block-count"><code class="ds-class">.table-card &gt; .cms-table</code></span>
          </div>
          <div class="table-card">
            <table class="cms-table">
              <thead><tr><th>Title</th><th>Stage</th><th>Updated</th><th></th></tr></thead>
              <tbody>
                <tr class="row-clickable"><td class="td-title"><div class="t">A Discipline Reset</div><div class="slug">/articles/a-discipline-reset</div></td><td><span class="pill pill-published" style="display:none"></span><span class="pill pill-draft">Draft</span></td><td class="td-mono">2026-06-04</td><td class="cell-actions"><button class="btn-sec btn-tiny">Edit</button></td></tr>
                <tr class="row-clickable"><td class="td-title"><div class="t">Notes on Craft</div><div class="slug live">/journals/notes-on-craft</div></td><td><span class="pill pill-live">Live</span></td><td class="td-mono">2026-05-28</td><td class="cell-actions"><button class="btn-sec btn-tiny">Edit</button></td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ═══ CARDS ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Cards</span>
            <span class="content-block-count">Admin board + library cards</span>
          </div>
          <div class="ds-grid">
            <?= $item('<div class="kcard" style="width:100%;cursor:default"><div class="kcard-head"><div class="kcard-title">A Discipline Reset</div><span class="type-badge tb-article">Art</span></div><div class="kcard-summary">A short summary line that wraps to two lines and then truncates with an ellipsis when it runs long.</div><div class="kcard-foot"><span class="pill pill-draft">Draft</span><span class="kcard-date">Jun 4</span></div></div>', '.kcard', 'CMS', 'Draft-Writing / ideation board card.') ?>
            <?= $item('<div class="pub-card" style="width:100%;cursor:default"><div class="pub-card-status"><span class="st st-pub">Pub</span></div><div class="pub-card-hd"><span class="pub-card-cat" style="color:var(--c-forest)">Leading Design</span></div><div class="pub-card-bd"><div class="pub-card-title">A Discipline Reset</div></div></div>', '.pub-card', 'CMS', 'Published-library grid card.') ?>
          </div>
        </div>

        <!-- ═══ TITLES ═══ -->
        <div class="content-block ds-cat">
          <div class="content-block-header">
            <span class="content-block-label">Titles &amp; labels</span>
          </div>
          <div class="ds-grid is-wide">
            <?= $item('<div class="view-title">Page title</div>', '.view-title', 'CMS', 'Italic serif — used by every view header (also .pipeline-title).') ?>
            <?= $item('<span class="content-block-label">Section label</span>', '.content-block-label') ?>
            <?= $item('<label class="field-label">Field label <span class="field-req">*</span></label>', '.field-label .field-req') ?>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

</body>
</html>
