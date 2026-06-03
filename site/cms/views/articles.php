<?php
/**
 * cms/views/articles.php — Articles list.
 *
 * Routed from site/index.php as GET /cms/articles. Auth gate + chrome
 * (topbar / sidebar / view-header / filter-bar / table) reuse the
 * Phase 5 partials. The filter bar is rendered but its pills are
 * placeholder-only until Phase 7 wires real filtering.
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

// Phase 20.3 filter bar — URL-driven OR filters across Stage + Category.
// Both groups are multi-select via comma-separated values in the URL.
$filterStages     = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['stage']    ?? ''))), 'strlen'));
$filterCategories = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['category'] ?? ''))), 'strlen'));
$articles       = list_articles([
    'stage'    => implode(',', $filterStages),
    'category' => implode(',', $filterCategories),
]);
$allCategories  = list_categories('article');

// Drained from the URL after a destructive action.
$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/**
 * Render a stage pill (status). Uses the design system's .pill / .stage-{x}
 * classes from status.css.
 */
$stagePill = static function (string $status) use ($e): string {
    $status = strtolower($status);
    $label  = ucfirst($status);
    return '<span class="pill pill-' . $e($status) . '">' . $e($label) . '</span>';
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Articles — alexmchong.ca CMS</title>
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
$breadcrumb = 'Articles';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'articles';
  $nav_counts    = ['articles' => count($articles)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-articles">
      <?php
      $title    = 'Articles';
      $subtitle = 'Long-form writing. Create, edit, and publish drafts.';
      $actions  = '<a href="/writing/" target="_blank" rel="noopener" class="btn-sec">View live index ↗</a>'
                . '<a href="/cms/articles/new" class="btn-pri">+ New Article</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      // Phase 20.3 filter bar: per-type stages + categories, multi-select
      // OR within each group. Stage set drops Idea (Ideation view only)
      // and includes Concept/Outline only for the article pipeline.
      $groups = build_filter_groups('article', '/cms/articles', $filterStages, $filterCategories, $allCategories);
      require __DIR__ . '/../partials/filter-bar.php';
      ?>

      <div class="content-area">
        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <?php
        // Three sections: Drafts (concept + outline + draft), Scheduled
        // (status=published + published_status=scheduled), Published (live).
        // Idea-stage rows are intentionally hidden — they live in the Ideation
        // view only. Concept/Outline fold into Drafts.
        $drafts    = [];
        $scheduled = [];
        $published = [];
        foreach ($articles as $a) {
            $st = (string)($a['status'] ?? '');
            $ps = (string)($a['published_status'] ?? '');
            if ($st === 'idea') {
                continue; // hidden — only visible in Ideation view
            }
            if ($st === 'published') {
                if ($ps === 'scheduled') {
                    $scheduled[] = $a;
                } else {
                    $published[] = $a;
                }
            } else {
                // concept / outline / draft all roll into Drafts
                $drafts[] = $a;
            }
        }

        // Column set is shared across Drafts / Scheduled / Published. The
        // Date column's header + value depends on the section (Updated for
        // in-progress, Published for live, Scheduled for queued).
        $makeColumns = static function (string $dateLabel): array {
            return [
                ['label' => 'Article Title', 'width' => '32%'],
                ['label' => 'Stage',         'width' => '9%'],
                ['label' => 'Category',      'width' => '12%'],
                ['label' => 'Special tag',   'width' => '10%'],
                ['label' => 'Series',        'width' => '15%'],
                ['label' => $dateLabel,      'width' => '12%'],
                ['label' => '',              'width' => '10%'],
            ];
        };

        $buildRow = static function (array $a, string $dateMode) use ($e, $csrf_token, $stagePill): array {
            $id      = (int)($a['id'] ?? 0);
            $slug    = (string)($a['slug'] ?? '');
            $title2  = (string)($a['title'] ?? '');
            $special = (string)($a['special_tag'] ?? '');
            $updated = (string)($a['updated_at'] ?? '');
            $pubAt   = (string)($a['published_at'] ?? '');
            // Date cell: published_at for the Published section (with
            // optional Scheduled section reusing the same column), updated_at
            // everywhere else.
            $dateRaw = $dateMode === 'published' ? ($pubAt !== '' ? $pubAt : $updated) : $updated;
            $dateShort = $dateRaw !== '' ? date('Y-m-d', strtotime($dateRaw)) : '';

            // Title block: title on top, slug on its own line below.
            $titleHtml = '<a href="/cms/articles/edit?id=' . $id . '&from=articles" class="row-title">'
                       . $e($title2 !== '' ? $title2 : '(untitled)')
                       . '</a>'
                       . '<div class="row-slug">/' . $e($slug) . '</div>';

            // Category pill — uses the design-system colour token via inline
            // var() so existing palette swap works untouched.
            $catLabel  = (string)($a['category_label']  ?? '');
            $catColour = (string)($a['category_colour'] ?? '');
            if ($catLabel !== '') {
                $bg = $catColour !== '' ? 'var(--c-' . $e($catColour) . ')' : 'var(--ink-30)';
                $catHtml = '<span class="cat-chip" style="--cat-bg:' . $bg . '">' . $e($catLabel) . '</span>';
            } else {
                $catHtml = '<span class="muted">—</span>';
            }

            $specialHtml = $special !== ''
                ? '<span class="pill special-' . $e($special) . '">' . $e(ucfirst($special)) . '</span>'
                : '<span class="muted">—</span>';

            // Series cell shows just the name — the part number's "Part N of M"
            // meaning only resolves once the article is published (counted vs
            // its published siblings), so rendering it on drafts misleads.
            $seriesName = (string)($a['series_name'] ?? '');
            $seriesHtml = $seriesName !== ''
                ? '<a href="/cms/series" class="val-pill" style="text-decoration:none">' . $e($seriesName) . '</a>'
                : '<span class="muted">—</span>';

            // Live ↗ only on rows that are actually live — scheduled rows
            // have status='published' but their public URL won't resolve
            // until the cron promotes them.
            $isLiveRow = (string)($a['status'] ?? '') === 'published'
                      && (string)($a['published_status'] ?? '') !== 'scheduled';
            $liveBtn = $isLiveRow && $slug !== ''
                ? '<a href="/writing/' . $e($slug) . '" target="_blank" rel="noopener" class="btn-ghost btn-tiny row-action-live" title="Open the live published page">Live ↗</a>'
                : '';

            // Edit + Delete reveal on row hover. Live ↗ stays visible.
            $actionsHtml = '<div class="row-actions">'
                . $liveBtn
                . '<span class="row-actions-hover">'
                .   '<a href="/cms/articles/edit?id=' . $id . '&from=articles" class="btn-ghost btn-tiny">Edit</a>'
                .   '<form method="post" action="/cms/articles/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this article? This cannot be undone.">'
                .     '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                .     '<button type="submit" class="btn-icon btn-icon-danger" title="Delete" aria-label="Delete">×</button>'
                .   '</form>'
                . '</span>'
                . '</div>';

            return [
                // Include &from=articles so whole-row clicks land with the
                // same provenance the title/Edit links carry — keeps the
                // sidebar pinned to Articles instead of falling back to
                // the stage-based "Draft Writing" heuristic.
                'href'  => '/cms/articles/edit?id=' . $id . '&from=articles',
                'cells' => [
                    ['html' => $titleHtml],
                    ['html' => $stagePill(((string)($a['status'] ?? '') === 'published' && (string)($a['published_status'] ?? '') === 'scheduled') ? 'scheduled' : (string)($a['status'] ?? 'draft'))],
                    ['html' => $catHtml],
                    ['html' => $specialHtml],
                    ['html' => $seriesHtml],
                    ['html' => '<span class="muted">' . $e($dateShort) . '</span>'],
                    ['html' => $actionsHtml, 'class' => 'cell-actions'],
                ],
            ];
        };
        ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Drafts</span>
              <span class="content-block-sublabel">Concept · Outline · Draft</span>
            </div>
            <span class="content-block-count"><?= (int)count($drafts) ?> entries</span>
          </div>

          <?php
          $columns    = $makeColumns('Updated');
          $rows       = array_map(static fn($a) => $buildRow($a, 'updated'), $drafts);
          $empty_text = 'No article drafts yet — click [+ New Article] to start.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>

        <?php if (count($scheduled) > 0): ?>
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Scheduled</span>
              <span class="content-block-sublabel">Queued — auto-publishes at the scheduled time</span>
            </div>
            <span class="content-block-count"><?= (int)count($scheduled) ?> entries</span>
          </div>

          <?php
          $columns    = $makeColumns('Scheduled for');
          $rows       = array_map(static fn($a) => $buildRow($a, 'published'), $scheduled);
          $empty_text = 'No scheduled articles.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
        <?php endif; ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Published</span>
              <span class="content-block-sublabel">Live at /writing/&lt;slug&gt;</span>
            </div>
            <span class="content-block-count"><?= (int)count($published) ?> entries</span>
          </div>

          <?php
          $columns    = $makeColumns('Published');
          $rows       = array_map(static fn($a) => $buildRow($a, 'published'), $published);
          $empty_text = 'No published articles yet.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
  // Whole-row navigation: click anywhere on a .row-clickable row to open
  // the edit view. Clicks inside .cell-actions (Edit/Delete buttons) are
  // ignored so those keep their own behavior.
  for (const tr of document.querySelectorAll('tr.row-clickable')) {
    tr.addEventListener('click', (e) => {
      if (e.target.closest('.cell-actions, a, button, form, input, label, select')) return;
      const href = tr.getAttribute('data-row-href');
      if (href) location.href = href;
    });
  }

  // Wire delete confirmations. Each delete form has data-confirm="…"; if
  // the user cancels, the submit is suppressed.
  for (const form of document.querySelectorAll('form.inline-delete')) {
    form.addEventListener('submit', (e) => {
      const msg = form.getAttribute('data-confirm') || 'Delete?';
      if (!window.confirm(msg)) e.preventDefault();
    });
  }
</script>

</body>
</html>
