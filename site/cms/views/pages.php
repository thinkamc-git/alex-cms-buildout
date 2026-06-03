<?php
/**
 * cms/views/pages.php — Pages CMS list (Phase 20).
 *
 * Mirrors the Articles list convention: view-header with title/subtitle,
 * grouped content-blocks, partials/table.php for each group.
 *
 * Two groups:
 *   - Layout partials  (header.php / footer.php — publish-capable)
 *   - Marketing pages  (about, coaching, … — mock-only sandbox)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/pages.php';

Auth::require_login();
$user       = Auth::current_user();
$csrf_token = Csrf::token();

$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

// Filter mode: 'all' (default) shows the 3 file sections;
// 'archives' shows a flat list of mocks whose name starts with "Archive ".
$filter = ($_GET['filter'] ?? '') === 'archives' ? 'archives' : 'all';

$files    = list_pages_files();
$archives = list_archive_mocks();

// Sidecar: mock count + published-mock name + page metadata per slug.
$mock_counts    = [];
$published_name = [];
$meta_titles    = [];
foreach ($files as $f) {
    $mocks = list_page_mocks($f['slug']);
    $mock_counts[$f['slug']] = count($mocks);
    foreach ($mocks as $m) {
        if ((int)$m['is_published'] === 1) { $published_name[$f['slug']] = (string)$m['name']; break; }
    }
    $meta = get_page_metadata($f['slug']);
    if ($meta !== null && !empty($meta['meta_title'])) {
        $meta_titles[$f['slug']] = (string)$meta['meta_title'];
    }
}

$pages    = array_values(array_filter($files, fn($f) => $f['kind'] === 'page'));
$errors   = array_values(array_filter($files, fn($f) => $f['kind'] === 'error'));
$partials = array_values(array_filter($files, fn($f) => $f['kind'] === 'partial'));

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$rel_time = static function (int $epoch) use ($e): string {
    if ($epoch <= 0) return '<span class="muted">—</span>';
    $diff = time() - $epoch;
    if ($diff < 60)         return '<span class="muted">just now</span>';
    if ($diff < 3600)       return '<span class="muted">' . intdiv($diff, 60)   . 'min ago</span>';
    if ($diff < 86400)      return '<span class="muted">' . intdiv($diff, 3600) . 'hr ago</span>';
    if ($diff < 86400 * 30) return '<span class="muted">' . intdiv($diff, 86400) . 'd ago</span>';
    return '<span class="muted">' . $e(date('Y-m-d', $epoch)) . '</span>';
};

$buildRow = static function (array $f) use ($e, $mock_counts, $published_name, $meta_titles, $rel_time): array {
    $slug     = (string)$f['slug'];
    $filename = (string)$f['filename'];
    $editUrl  = '/cms/pages/edit?slug=' . rawurlencode($slug);
    $liveUrl  = '/' . rawurlencode($slug) . '/';

    $nameHtml = '<a href="' . $e($editUrl) . '" class="row-title">' . $e($filename) . '</a>';

    $title = $meta_titles[$slug] ?? '';
    $titleHtml = $title !== ''
        ? $e($title)
        : '<span class="muted">—</span>';

    $n = (int)($mock_counts[$slug] ?? 0);
    if ($n === 0) {
        $mockHtml = '<span class="muted">No mocks</span>';
    } else {
        $mockHtml = '<span class="val-pill">' . $n . ' mock' . ($n === 1 ? '' : 's') . '</span>';
    }
    if (isset($published_name[$slug])) {
        $mockHtml .= ' <span class="pill pill-published" title="A mock is currently published — overriding the file on staging">LIVE: ' . $e($published_name[$slug]) . '</span>';
    }

    $modHtml = $rel_time((int)$f['modified_at']);

    $actionsHtml = '<div class="row-actions row-actions-always">'
        . '<a href="' . $e($liveUrl) . '" target="_blank" rel="noopener" class="btn-sec btn-tiny" title="Open the live page">Live ↗</a>'
        . '<a href="' . $e($editUrl) . '" class="btn-sec btn-tiny">Edit</a>'
        . '</div>';

    return [
        'href'  => $editUrl,
        'cells' => [
            ['html' => $nameHtml],
            ['html' => $titleHtml],
            ['html' => $mockHtml],
            ['html' => $modHtml],
            ['html' => $actionsHtml, 'class' => 'cell-actions'],
        ],
    ];
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Pages — alexmchong.ca CMS</title>
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
$breadcrumb = 'Pages';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'pages';
  $nav_counts    = ['pages' => count($files)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-pages">
      <?php
      $title    = 'Pages';
      $subtitle = 'Marketing pages live as files on disk and update via deploy. Use mocks to save and preview alternate body content without touching the file. For header.php and footer.php only, you can publish a mock to override the file on staging.';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      // Filter rail — same partial/styling as the Articles list, and
      // positioned outside the content-area like Articles does so it
      // sits flush under the view-header with no extra padding band.
      $groups = [
          [
              'label' => 'View',
              'mode'  => 'or',
              'pills' => [
                  ['label' => 'All',      'href' => '/cms/pages',                 'active' => $filter === 'all',      'all' => true],
                  ['label' => 'Archives', 'href' => '/cms/pages?filter=archives', 'active' => $filter === 'archives'],
              ],
          ],
      ];
      require __DIR__ . '/../partials/filter-bar.php';
      ?>

      <div class="content-area">
        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <?php if ($filter === 'archives'): ?>
          <?php
          $arch_columns = [
              ['label' => 'Archive name', 'width' => '40%'],
              ['label' => 'Page',         'width' => '20%'],
              ['label' => 'Captured',     'width' => '20%'],
              ['label' => 'Actions',      'width' => '20%'],
          ];

          $arch_rows = [];
          foreach ($archives as $a) {
              $previewUrl = '/cms/pages/archive-preview?id=' . (int)$a['id'];
              $captured   = !empty($a['created_at']) ? strtotime((string)$a['created_at']) : 0;
              $arch_rows[] = [
                  'href'  => $previewUrl,
                  'cells' => [
                      ['html' => '<a href="' . $e($previewUrl) . '" target="_blank" rel="noopener" class="row-title">' . $e((string)$a['name']) . '</a>'],
                      ['html' => $e((string)$a['slug'])],
                      ['html' => $rel_time((int)$captured)],
                      ['html' => '<div class="row-actions row-actions-always">'
                          . '<a href="' . $e($previewUrl) . '" target="_blank" rel="noopener" class="btn-sec btn-tiny">Preview ↗</a>'
                          . '</div>', 'class' => 'cell-actions'],
                  ],
              ];
          }
          ?>

          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Archives</span>
                <span class="content-block-sublabel">Past page snapshots · preview-only, no public URL</span>
              </div>
              <span class="content-block-count"><?= count($archives) ?> <?= count($archives) === 1 ? 'archive' : 'archives' ?></span>
            </div>

            <?php
            $columns = $arch_columns;
            $rows = $arch_rows;
            $empty_text = 'No archives yet — to archive a page, create a mock whose name starts with "Archive ".';
            require __DIR__ . '/../partials/table.php';
            ?>
          </div>

        <?php else: ?>
          <?php
          $columns = [
              ['label' => 'File',          'width' => '22%'],
              ['label' => 'Meta title',    'width' => '35%'],
              ['label' => 'Mocks',         'width' => '13%'],
              ['label' => 'Last modified', 'width' => '12%'],
              ['label' => 'Actions',       'width' => '18%'],
          ];
          ?>

          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Marketing pages</span>
                <span class="content-block-sublabel">Editable as mocks · the on-disk file stays canonical</span>
              </div>
              <span class="content-block-count"><?= count($pages) ?> files</span>
            </div>

            <?php
            $rows = array_map($buildRow, $pages);
            $empty_text = 'No marketing pages found.';
            require __DIR__ . '/../partials/table.php';
            ?>
          </div>

          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Error pages</span>
                <span class="content-block-sublabel">Shown when no route or file matches a request</span>
              </div>
              <span class="content-block-count"><?= count($errors) ?> files</span>
            </div>

            <?php
            $rows = array_map($buildRow, $errors);
            $empty_text = 'No error pages found.';
            require __DIR__ . '/../partials/table.php';
            ?>
          </div>

          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Layout partials</span>
                <span class="content-block-sublabel">Shared header and footer · publishable on staging</span>
              </div>
              <span class="content-block-count"><?= count($partials) ?> files</span>
            </div>

            <?php
            $rows = array_map($buildRow, $partials);
            $empty_text = 'No layout partials found.';
            require __DIR__ . '/../partials/table.php';
            ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Row-click now loads via partials/table.php (Batch 2 #52). -->

</body>
</html>
