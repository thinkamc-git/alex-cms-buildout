<?php
/**
 * cms/views/series.php — Series list (Phase 22 redesign).
 *
 * Replaces the original card-grid layout with the canonical list-view
 * shape used by Articles / Journals / Live Sessions: view-header →
 * content-block → cms-table via the partials/table.php primitive.
 *
 * GET-only. Create/edit/delete/add-part/remove-part live on the
 * dedicated edit page at /cms/series/edit. The drag-reorder POST
 * endpoint at /cms/series/reorder is unchanged.
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

$series = list_series();

// Published-part counts per series. The "Parts" column reflects what's
// actually live, not the total — matches what the public /series/<slug>/
// page renders.
$publishedCounts = [];
if (count($series) > 0) {
    $ids = array_map(static fn($s) => (int)$s['id'], $series);
    $stmt = db()->prepare(
        "SELECT series_id, COUNT(*) AS n
           FROM content
          WHERE series_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
            AND status = 'published'
            AND (published_status IS NULL OR published_status = 'live')
          GROUP BY series_id"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $r) {
        $publishedCounts[(int)$r['series_id']] = (int)$r['n'];
    }
}

// Drained from the URL after a destructive action on the edit page.
$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/**
 * Server-side truncate that prefers word boundaries when cheap. Hard-cuts
 * if the next space is too far away. Always appends an ellipsis when the
 * original exceeded $max chars.
 */
$truncate = static function (string $text, int $max = 40) use ($e): string {
    $text = trim($text);
    if ($text === '') return '<span class="muted">—</span>';
    if (mb_strlen($text) <= $max) return $e($text);
    $cut = mb_substr($text, 0, $max);
    $lastSpace = mb_strrpos($cut, ' ');
    // Only honour the word boundary if it's reasonably close to the end —
    // otherwise the truncation looks arbitrary.
    if ($lastSpace !== false && $lastSpace >= (int)floor($max * 0.6)) {
        $cut = mb_substr($cut, 0, $lastSpace);
    }
    return $e(rtrim($cut)) . '…';
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Series — alexmchong.ca CMS</title>
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
$breadcrumb = 'Series';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'series';
  $nav_counts    = ['series' => count($series)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-series">
      <?php
      $title    = 'Series';
      $subtitle = 'Ordered groups of articles. Each series gets a matching index page at /series/<slug>/.';
      $actions  = '<a href="/cms/series/edit?id=new" class="btn-pri">+ New Series</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">All series</span>
            </div>
            <span class="content-block-count"><?= count($series) ?> <?= count($series) === 1 ? 'series' : 'series' ?></span>
          </div>

          <?php
          $columns = [
            ['label' => 'Name',        'width' => '22%'],
            ['label' => 'Description', 'width' => '34%'],
            ['label' => 'Slug',        'width' => '20%'],
            ['label' => 'Parts',       'width' => '8%'],
            ['label' => '',            'width' => '16%'],
          ];

          $rows = [];
          foreach ($series as $s) {
              $sid      = (int)$s['id'];
              $name     = (string)$s['name'];
              $slug     = (string)$s['slug'];
              $desc     = (string)($s['description'] ?? '');
              $pubCount = $publishedCounts[$sid] ?? 0;

              $editHref = '/cms/series/edit?id=' . $sid;

              $nameHtml = '<a href="' . $e($editHref) . '" class="row-title">'
                        . $e($name !== '' ? $name : '(untitled)')
                        . '</a>';

              $descHtml = $truncate($desc, 40);
              $slugHtml = '<span class="val-pill">' . $e($slug) . '</span>';
              $partsHtml = '<span class="muted">' . (int)$pubCount . '</span>';

              // Live ↗ always visible — even empty series have an index page
              // at /series/<slug>/ (it just shows the "no parts yet" state).
              // Edit hover-reveals via .row-actions-hover, same as Pages list.
              $actionsHtml = '<div class="row-actions">'
                  . '<a href="/series/' . $e($slug) . '/" target="_blank" rel="noopener" class="btn-sec btn-tiny row-action-live" title="Open the live series index">Live ↗</a>'
                  . '<span class="row-actions-hover">'
                  .   '<a href="' . $e($editHref) . '" class="btn-sec btn-tiny">Edit</a>'
                  . '</span>'
                  . '</div>';

              $rows[] = [
                  'href'  => $editHref,
                  'cells' => [
                      ['html' => $nameHtml],
                      ['html' => $descHtml],
                      ['html' => $slugHtml],
                      ['html' => $partsHtml],
                      ['html' => $actionsHtml, 'class' => 'cell-actions'],
                  ],
              ];
          }
          $empty_text = 'No series yet — click [+ New Series] to start.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
      </div>
    </div>
  </main>
</div>

</body>
</html>
