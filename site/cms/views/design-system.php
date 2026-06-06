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
      $subtitle = 'The admin (CMS) design system — the same shared catalogue shown in the public design system\'s CMS tab, rendered here inside the CMS. Edit the CSS in _design-system/css/ (system-cms.css) to change anything here, in the showcase, and on the live admin at once.';
      require __DIR__ . '/../partials/view-header.php';
      ?>

            <div class="content-area" style="padding:0">
        <!-- Single shared CMS catalogue (also shown in /_ds/ CMS tab + CSS Library). -->
        <iframe src="/_ds/showcase/cms.html" title="CMS components" style="width:100%;height:100%;border:0;display:block"></iframe>
      </div>
    </div>
  </main>
</div>

</body>
</html>
