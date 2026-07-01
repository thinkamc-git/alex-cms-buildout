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

// ── POST: restore / hard-delete a page from the Archives tab ──────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        header('Location: /cms/pages?filter=archives&flash=' . rawurlencode('Session expired. Try again.'));
        exit;
    }
    $action = (string)($_POST['action'] ?? '');
    $pslug  = preg_replace('/[^a-z0-9-]/', '', (string)($_POST['slug'] ?? ''));
    if ($pslug !== '' && $action === 'restore_page') {
        restore_page($pslug);
        // Back to the All list, where the now-active page reappears.
        header('Location: /cms/pages?flash=' . rawurlencode('Page restored — active again.'));
        exit;
    }
    if ($pslug !== '' && $action === 'delete_page') {
        $ok  = delete_page($pslug);
        $msg = $ok ? 'Page deleted permanently.' : 'That page type can’t be deleted.';
        header('Location: /cms/pages?filter=archives&flash=' . rawurlencode($msg));
        exit;
    }
    header('Location: /cms/pages');
    exit;
}

// Filter mode: 'all' (default) shows the 3 file sections;
// 'archives' shows a flat list of mocks whose name starts with "Archive ".
$filter = ($_GET['filter'] ?? '') === 'archives' ? 'archives' : 'all';

$files          = list_pages_files();
$archived_pages = list_archived_pages();

// Sidecar: draft saved-at per slug.
$draft_saved_at = [];
$archived_slugs = [];
foreach ($files as $f) {
    $mocks = list_page_mocks($f['slug']);
    if (!empty($mocks) && !empty($mocks[0]['updated_at'])) {
        $draft_saved_at[$f['slug']] = (int)strtotime((string)$mocks[0]['updated_at']);
    }
    if ($f['kind'] === 'page' && is_page_archived($f['slug'])) {
        $archived_slugs[] = $f['slug'];
    }
}

// Active marketing pages only — archived ones live under the Archives tab.
$pages    = array_values(array_filter($files, fn($f) => $f['kind'] === 'page' && !in_array($f['slug'], $archived_slugs, true)));
// Pin Home (landing) to the top of the marketing pages.
usort($pages, fn($a, $b) => (page_type($b['slug']) === 'home' ? 1 : 0) <=> (page_type($a['slug']) === 'home' ? 1 : 0));
$errors   = array_values(array_filter($files, fn($f) => $f['kind'] === 'error'));
$partials = array_values(array_filter($files, fn($f) => $f['kind'] === 'partial'));

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$rel_time = 'rel_time_html';

$buildRow = static function (array $f) use ($e, $draft_saved_at, $rel_time, $archived_slugs): array {
    $slug     = (string)$f['slug'];
    $filename = (string)$f['filename'];
    $editUrl  = '/cms/pages/edit?slug=' . rawurlencode($slug);
    $liveUrl  = page_public_url($slug);   // single source of truth: landing → '/'

    $isArchived = in_array($slug, $archived_slugs, true);
    $nameHtml = '<a href="' . $e($editUrl) . '" class="row-title">' . $e(page_display_name($slug)) . '</a>';
    if (page_type($slug) === 'home') {
        $nameHtml .= ' <span class="home-flag" title="Home page — served at /" aria-label="Home page"'
            . ' style="display:inline-flex;vertical-align:middle;margin-left:6px;color:var(--muted)">'
            . '<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/></svg>'
            . '</span>';
    }
    if ($isArchived) {
        $nameHtml .= ' <span class="pill" title="Redirects to /archive/' . $e($slug) . '/">Archived</span>';
    }

    $draftHtml = isset($draft_saved_at[$slug])
        ? $rel_time($draft_saved_at[$slug])
        : '<span class="muted">—</span>';

    $actionsHtml = '<div class="row-actions">'
        . '<a href="' . $e($liveUrl) . '" target="_blank" rel="noopener" class="btn-sec btn-tiny" title="Open the live page">Live ↗</a>'
        . '<span class="row-actions-hover">'
        .   '<a href="' . $e($editUrl) . '" class="btn-sec btn-tiny">Edit</a>'
        . '</span>'
        . '</div>';

    $lastPub = page_last_published_at($slug);
    $publishedHtml = $lastPub !== null ? $rel_time($lastPub) : '<span class="muted">—</span>';

    return [
        'href'  => $editUrl,
        'cells' => [
            ['html' => $nameHtml],
            ['html' => $draftHtml],
            ['html' => $publishedHtml],
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
<link rel="stylesheet" href="/cms/_assets/style-cms.css<?= asset_ver('/cms/_assets/style-cms.css') ?>">
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
      $subtitle = 'Pages managed as files on disk. Edit as drafts, publish to file, then deploy.';
      $actions  = '<a href="/cms/pages/new" class="btn-pri">+ New page</a>';
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
              ['label' => 'Page',     'width' => '35%'],
              ['label' => 'Archived', 'width' => '20%'],
              ['label' => '',         'width' => '45%'],
          ];

          $arch_rows = [];
          foreach ($archived_pages as $a) {
              $aslug    = (string)$a['slug'];
              $viewUrl  = '/archive/' . rawurlencode($aslug) . '/';
              $archived = !empty($a['archived_at']) ? strtotime((string)$a['archived_at']) : 0;

              $restoreForm = '<form method="post" action="/cms/pages" style="display:inline">'
                  . '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                  . '<input type="hidden" name="action" value="restore_page">'
                  . '<input type="hidden" name="slug" value="' . $e($aslug) . '">'
                  . '<button type="submit" class="btn-sec btn-tiny">Restore</button>'
                  . '</form>';
              $deleteForm = '<form method="post" action="/cms/pages" style="display:inline" '
                  . 'onsubmit="return confirm(\'Permanently delete /' . $e($aslug) . '/ ?\\n\\nThis removes the page files, every draft, and its metadata. It cannot be undone.\');">'
                  . '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                  . '<input type="hidden" name="action" value="delete_page">'
                  . '<input type="hidden" name="slug" value="' . $e($aslug) . '">'
                  . '<button type="submit" class="btn-danger btn-tiny">Delete permanently</button>'
                  . '</form>';

              $arch_rows[] = [
                  'href'  => $viewUrl,
                  'cells' => [
                      ['html' => '<a href="' . $e($viewUrl) . '" target="_blank" rel="noopener" class="row-title">/' . $e($aslug) . '/</a>'],
                      ['html' => $archived ? $rel_time((int)$archived) : '<span class="muted">—</span>'],
                      ['html' => '<div class="row-actions row-actions-always">'
                          . '<a href="' . $e($viewUrl) . '" target="_blank" rel="noopener" class="btn-sec btn-tiny">View ↗</a>'
                          . $restoreForm . $deleteForm
                          . '</div>', 'class' => 'cell-actions'],
                  ],
              ];
          }
          ?>

          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Archived pages</span>
                <span class="content-block-sublabel">Retired pages · served at /archive/&lt;slug&gt;/ with a notice · restore or delete permanently</span>
              </div>
              <span class="content-block-count"><?= count($archived_pages) ?> <?= count($archived_pages) === 1 ? 'page' : 'pages' ?></span>
            </div>

            <?php
            $columns = $arch_columns;
            $rows = $arch_rows;
            $empty_text = 'No archived pages — archive a page from its editor to retire it here.';
            require __DIR__ . '/../partials/table.php';
            ?>
          </div>

        <?php else: ?>
          <?php
          $columns = [
              ['label' => 'Page',           'width' => '40%'],
              ['label' => 'Draft saved',    'width' => '20%'],
              ['label' => 'Last published', 'width' => '20%'],
              ['label' => '',               'width' => '20%'],
          ];
          ?>

          <div class="content-block">
            <div class="content-block-header">
              <div>
                <span class="content-block-label">Marketing pages</span>
                <span class="content-block-sublabel">Editable as drafts · the on-disk file stays canonical</span>
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
                <span class="content-block-sublabel">Site header and footer · changes publish to staging without a deploy</span>
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
