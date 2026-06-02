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

$files = list_pages_files();

// Sidecar: mock count + published-mock name per slug.
$mock_counts    = [];
$published_name = [];
foreach ($files as $f) {
    $mocks = list_page_mocks($f['slug']);
    $mock_counts[$f['slug']] = count($mocks);
    foreach ($mocks as $m) {
        if ((int)$m['is_published'] === 1) { $published_name[$f['slug']] = (string)$m['name']; break; }
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
    if ($diff < 3600)       return '<span class="muted">' . intdiv($diff, 60)   . 'm ago</span>';
    if ($diff < 86400)      return '<span class="muted">' . intdiv($diff, 3600) . 'h ago</span>';
    if ($diff < 86400 * 30) return '<span class="muted">' . intdiv($diff, 86400) . 'd ago</span>';
    return '<span class="muted">' . $e(date('Y-m-d', $epoch)) . '</span>';
};

$buildRow = static function (array $f) use ($e, $mock_counts, $published_name, $rel_time): array {
    $slug     = (string)$f['slug'];
    $filename = (string)$f['filename'];
    $editUrl  = '/cms/pages/edit?slug=' . rawurlencode($slug);
    $liveUrl  = '/' . rawurlencode($slug) . '/';

    $nameHtml = '<a href="' . $e($editUrl) . '" class="row-title">' . $e($filename) . '</a>';

    $n = (int)($mock_counts[$slug] ?? 0);
    if ($n === 0) {
        $mockHtml = '<span class="muted">No mocks</span>';
    } else {
        $mockHtml = '<span class="val-pill">' . $n . ' mock' . ($n === 1 ? '' : 's') . '</span>';
    }
    if (isset($published_name[$slug])) {
        $mockHtml .= ' <span class="pill pill-published" title="A mock is currently published — overriding the file at runtime">LIVE: ' . $e($published_name[$slug]) . '</span>';
    }

    $modHtml = $rel_time((int)$f['modified_at']);

    $actionsHtml = '<div class="row-actions">'
        . '<a href="' . $e($liveUrl) . '" target="_blank" rel="noopener" class="btn-ghost btn-tiny" title="Open the live page">Live ↗</a>'
        . '<a href="' . $e($editUrl) . '" class="btn-ghost btn-tiny">Edit</a>'
        . '</div>';

    return [
        'href'  => $editUrl,
        'cells' => [
            ['html' => $nameHtml],
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
      $subtitle = 'Marketing pages live on disk and ship via deploy. The CMS lets you save named mock versions for preview. For header.php / footer.php only, a mock can be published to override the file at runtime on staging.';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status"><?= $e($flash) ?></div>
        <?php endif; ?>

        <?php
        $columns = [
            ['label' => 'File',          'width' => '36%'],
            ['label' => 'Mocks',         'width' => '28%'],
            ['label' => 'Last modified', 'width' => '14%'],
            ['label' => 'Actions',       'width' => '22%'],
        ];
        ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Marketing pages</span>
              <span class="content-block-sublabel">Mock-only sandbox · files remain canonical</span>
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
              <span class="content-block-sublabel">Rendered when no route or file matches</span>
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
              <span class="content-block-sublabel">Shared header + footer · publish-capable on staging</span>
            </div>
            <span class="content-block-count"><?= count($partials) ?> files</span>
          </div>

          <?php
          $rows = array_map($buildRow, $partials);
          $empty_text = 'No layout partials found.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
  for (const tr of document.querySelectorAll('tr.row-clickable')) {
    tr.addEventListener('click', (e) => {
      if (e.target.closest('.cell-actions, a, button, form, input, label, select')) return;
      const href = tr.getAttribute('data-row-href');
      if (href) location.href = href;
    });
  }
</script>

</body>
</html>
