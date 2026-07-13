<?php
/**
 * cms/views/indexes.php — Editorial Indexes list (Phase 12).
 *
 * One row per index. The four built-in type indexes (writing/journal/
 * live-sessions/experiments) come from the 0007 seed; author-created
 * Editorial Pages stack underneath in their own block. Series indexes
 * are NOT listed here — they're rendered automatically per series row
 * and don't have a builder.
 *
 * Edit goes to /cms/indexes/edit?id=N. Delete is an inline confirm form
 * (same pattern as articles/series). + New Index lives in the sidebar.
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

$indexes = list_indexes();

// Split: built-in type indexes (seeded slugs) vs. author-created.
$builtinSlugs = ['essays', 'field-notes', 'live-sessions', 'field-work'];

// Icon + type label shown alongside each post-type index title.
$builtinMeta = ['essays', 'field-notes', 'live-sessions', 'field-work'];
$builtIn   = [];
$custom    = [];
foreach ($indexes as $i) {
    if (in_array((string)$i['slug'], $builtinSlugs, true)) {
        $builtIn[] = $i;
    } else {
        $custom[] = $i;
    }
}

// Also list every series so author sees the auto-generated /series/[slug]/
// pages they "own" — read-only, no edit (auto-rendered from series + parts).
$seriesRows = list_series();

$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$layoutPill = static function (string $layout) use ($e): string {
    $label = $layout === 'editorial' ? 'Editorial' : 'Listing';
    return '<span class="pill" style="background:var(--ink-08);color:var(--secondary);font-size:10px;padding:2px 7px">'
         . $e($label) . '</span>';
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Indexes — alexmchong.ca CMS</title>
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
$breadcrumb = 'Indexes';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'indexes';
  $nav_counts    = ['indexes' => count($indexes)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-indexes">
      <?php
      $title    = 'Indexes';
      $subtitle = 'Configure the public index pages for each section of the site. Editorial Page adds a hero, featured picks, and multi-section layout. Basic Listing is title plus content feed.';
      $actions  = '<a href="/cms/indexes/new" class="btn-pri">+ New Index</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <?php
        $indexColumns = [
            ['label' => 'Index',   'width' => '34%'],
            ['label' => 'Layout',  'width' => '12%'],
            ['label' => 'Feed',    'width' => '28%'],
            ['label' => 'Updated', 'width' => '12%'],
            ['label' => '',        'width' => '14%'],
        ];
        $columns = $indexColumns;

        $buildRow = static function (array $i, ?array $meta = null) use ($e, $csrf_token, $layoutPill): array {
            $id        = (int)$i['id'];
            $slug      = (string)$i['slug'];
            $titleStr  = (string)($i['title'] ?? '');
            $layout    = (string)$i['layout'];
            $updated   = (string)($i['updated_at'] ?? '');
            $updatedShort = $updated !== '' ? date('Y-m-d', strtotime($updated)) : '';

            // Feed summary: type list + sort + rows.
            $types = $i['feed_types'] ?? null;
            if (is_string($types)) $types = json_decode($types, true);
            if (!is_array($types) || $types === []) $types = ['all types'];
            $typesStr = implode(', ', $types);
            $sort     = (string)($i['feed_sort']       ?? 'newest');
            $rows     = (string)($i['feed_rows_shown'] ?? 'all');
            $feedSummary = $typesStr . ' · ' . $sort . ' · ' . ($rows === 'all' ? 'all rows' : $rows . ' row' . ($rows === '1' ? '' : 's'));

            $displayTitle = $titleStr !== '' ? $titleStr : '(untitled)';
            $titleHtml = '<a href="/cms/indexes/edit?id=' . $id . '" class="row-title">' . $e($displayTitle) . '</a>'
                       . '<div class="row-slug">/' . $e($slug) . '/</div>';

            // Phase 20.3: same row-action pattern as the post-library tables —
            // "View live ↗" stays visible (every index is always live), Edit
            // and trash reveal on hover.
            $actionsHtml = '<div class="row-actions">'
                . '<a href="/' . $e($slug) . '/" target="_blank" rel="noopener" class="btn-sec btn-tiny row-action-live" title="Open the live index page">Live ↗</a>'
                . '<span class="row-actions-hover">'
                .   '<a href="/cms/indexes/edit?id=' . $id . '" class="btn-sec btn-tiny">Edit</a>'
                .   '<form method="post" action="/cms/indexes/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete index &quot;' . $e($titleStr !== '' ? $titleStr : $slug) . '&quot;? The URL /' . $e($slug) . '/ will 404 unless you re-create it.">'
                .     '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                .     '<button type="submit" class="btn-icon btn-icon-danger" title="Delete" aria-label="Delete">'
                .       '<svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                .     '</button>'
                .   '</form>'
                . '</span>'
                . '</div>';

            return [
                'href'  => '/cms/indexes/edit?id=' . $id,
                'cells' => [
                    ['html' => $titleHtml],
                    ['html' => $layoutPill($layout)],
                    ['html' => '<span class="muted" style="font-family:var(--font-mono);font-size:var(--text-micro)">' . $e($feedSummary) . '</span>'],
                    ['html' => '<span class="muted">' . $e($updatedShort) . '</span>'],
                    ['html' => $actionsHtml, 'class' => 'cell-actions'],
                ],
            ];
        };
        ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Post Type indexes</span>
              <span class="content-block-sublabel">The four built-in content-type indexes (one per content type)</span>
            </div>
            <span class="content-block-count"><?= count($builtIn) ?> indexes</span>
          </div>
          <?php
          $rows = array_map(static fn($i) => $buildRow($i, $builtinMeta[$i['slug']] ?? null), $builtIn);
          $empty_text = 'Seed missing. Run migration 0007 to restore the four built-in indexes.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>

        <?php if (count($seriesRows) > 0): ?>
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Series indexes</span>
              <span class="content-block-sublabel">Auto-generated from Series — manage them in Collections › Series</span>
            </div>
            <span class="content-block-count"><?= count($seriesRows) ?> series</span>
          </div>
          <?php
          $seriesColumns = [
              ['label' => 'Series', 'width' => '34%'],
              ['label' => 'Layout', 'width' => '12%'],
              ['label' => 'Parts',  'width' => '28%'],
              ['label' => '',       'width' => '12%'],
              ['label' => '',       'width' => '14%'],
          ];
          $sRows = [];
          foreach ($seriesRows as $sr) {
              $sid = (int)$sr['id'];
              $sSlug = (string)$sr['slug'];
              $sName = (string)$sr['name'];
              $count = (int)($sr['parts_count'] ?? 0);
              $editHref = '/cms/series/edit?id=' . $sid . '&from=indexes';
              $sRows[] = [
                  'href' => $editHref,
                  'cells' => [
                      ['html' => '<a class="row-title" href="' . $editHref . '">' . $e($sName) . '</a><div class="row-slug">/series/' . $e($sSlug) . '/</div>'],
                      ['html' => $layoutPill('editorial')],
                      ['html' => '<span class="muted">' . $count . ' part' . ($count === 1 ? '' : 's') . '</span>'],
                      ['html' => ''],
                      ['html' => '<div class="row-actions">'
                          . '<a href="/series/' . $e($sSlug) . '/" target="_blank" rel="noopener" class="btn-sec btn-tiny row-action-live" title="Open the live series index">Live ↗</a>'
                          . '<span class="row-actions-hover">'
                          .   '<a href="' . $editHref . '" class="btn-sec btn-tiny">Manage</a>'
                          . '</span>'
                          . '</div>', 'class' => 'cell-actions'],
                  ],
              ];
          }
          $columns = $seriesColumns;
          $rows = $sRows;
          $empty_text = '';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
        <?php endif; ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Custom indexes</span>
              <span class="content-block-sublabel">Editorial Pages and additional Basic Listings you've created</span>
            </div>
            <span class="content-block-count"><?= count($custom) ?> indexes</span>
          </div>
          <?php
          // Reset $columns to the index-table template (the Series block
          // above set it to $seriesColumns, but each table call should be
          // explicit about its column set — see proposal #7).
          $columns    = $indexColumns;
          $rows       = array_map($buildRow, $custom);
          $empty_text = 'No custom indexes yet — click [+ New Index] to add one (e.g. /digital-garden, /thoughts).';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Row-click + confirm now load via partials/table.php (Batch 2 #48/#49/#52). -->

</body>
</html>
