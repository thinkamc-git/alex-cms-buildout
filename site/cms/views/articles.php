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
$articles   = list_articles();

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

  <main class="main">
    <div class="view active" id="view-articles">
      <?php
      $title    = 'Articles';
      $subtitle = 'Long-form writing. Create, edit, and ship drafts. Pipeline + transitions land in Phase 7.';
      $actions  = '<a href="/cms/articles/new" class="btn-pri">+ New Article</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $groups = [
          [
              'label' => 'Stage',
              'mode'  => 'or',
              'pills' => [
                  ['label' => 'All',       'active' => true, 'all' => true],
                  ['label' => 'Draft'],
                  ['label' => 'Published'],
              ],
          ],
          [
              'label' => 'Special Tag',
              'mode'  => 'or',
              'pills' => [
                  ['label' => 'All',       'active' => true, 'all' => true],
                  ['label' => 'Framework'],
                  ['label' => 'Principle'],
              ],
          ],
      ];
      require __DIR__ . '/../partials/filter-bar.php';
      ?>

      <div class="content-area">
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status"><?= $e($flash) ?></div>
        <?php endif; ?>

        <?php
        // Split into "In progress" (idea/concept/outline/draft) and "Published".
        $inProgress = [];
        $published  = [];
        foreach ($articles as $a) {
            if ((string)($a['status'] ?? '') === 'published') {
                $published[] = $a;
            } else {
                $inProgress[] = $a;
            }
        }

        $columns = [
            ['label' => 'Article Title', 'width' => '34%'],
            ['label' => 'Stage',         'width' => '10%'],
            ['label' => 'Special tag',   'width' => '12%'],
            ['label' => 'Series',        'width' => '16%'],
            ['label' => 'Updated',       'width' => '14%'],
            ['label' => 'Actions',       'width' => '14%'],
        ];

        $buildRow = static function (array $a) use ($e, $csrf_token, $stagePill): array {
            $id      = (int)($a['id'] ?? 0);
            $slug    = (string)($a['slug'] ?? '');
            $title2  = (string)($a['title'] ?? '');
            $special = (string)($a['special_tag'] ?? '');
            $updated = (string)($a['updated_at'] ?? '');
            $updatedShort = $updated !== '' ? date('Y-m-d', strtotime($updated)) : '';

            $titleHtml = '<a href="/cms/articles/edit?id=' . $id . '" class="row-title">'
                       . $e($title2 !== '' ? $title2 : '(untitled)')
                       . '</a>'
                       . ' <span class="row-slug">/' . $e($slug) . '</span>';

            $specialHtml = $special !== ''
                ? '<span class="pill special-' . $e($special) . '">' . $e(ucfirst($special)) . '</span>'
                : '<span class="muted">—</span>';

            $seriesName = (string)($a['series_name'] ?? '');
            if ($seriesName !== '') {
                $partNo = (int)($a['series_order'] ?? 0);
                $partTxt = $partNo > 0 ? ' – ' . str_pad((string)$partNo, 2, '0', STR_PAD_LEFT) : '';
                $seriesHtml = '<a href="/cms/series" class="val-pill" style="text-decoration:none">'
                            . $e($seriesName . $partTxt) . '</a>';
            } else {
                $seriesHtml = '<span class="muted">—</span>';
            }

            $actionsHtml = '<div class="row-actions">'
                . '<a href="/cms/articles/edit?id=' . $id . '" class="btn-ghost btn-tiny">Edit</a>'
                . '<form method="post" action="/cms/articles/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this article? This cannot be undone.">'
                .   '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                .   '<button type="submit" class="btn-ghost btn-tiny btn-danger">Delete</button>'
                . '</form>'
                . '</div>';

            return [
                'href'  => '/cms/articles/edit?id=' . $id,
                'cells' => [
                    ['html' => $titleHtml],
                    ['html' => $stagePill((string)($a['status'] ?? 'draft'))],
                    ['html' => $specialHtml],
                    ['html' => $seriesHtml],
                    ['html' => '<span class="muted">' . $e($updatedShort) . '</span>'],
                    ['html' => $actionsHtml, 'class' => 'cell-actions'],
                ],
            ];
        };
        ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">In progress</span>
              <span class="content-block-sublabel">Idea · Concept · Outline · Draft</span>
            </div>
            <span class="content-block-count"><?= (int)count($inProgress) ?> entries</span>
          </div>

          <?php
          $rows = array_map($buildRow, $inProgress);
          $empty_text = 'No articles in progress. Click + New Article to start.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Published</span>
              <span class="content-block-sublabel">Live on /writing/[slug]</span>
            </div>
            <span class="content-block-count"><?= (int)count($published) ?> entries</span>
          </div>

          <?php
          $rows = array_map($buildRow, $published);
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
