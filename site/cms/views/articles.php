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

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">All articles</span>
              <span class="content-block-sublabel">Draft + Published</span>
            </div>
            <span class="content-block-count"><?= (int)count($articles) ?> entries</span>
          </div>

          <?php
          $columns = [
              ['label' => 'Title',       'width' => '40%'],
              ['label' => 'Stage',       'width' => '12%'],
              ['label' => 'Special tag', 'width' => '14%'],
              ['label' => 'Updated',     'width' => '18%'],
              ['label' => 'Actions',     'width' => '16%'],
          ];

          $rows = [];
          foreach ($articles as $a) {
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

              $actionsHtml = '<div class="row-actions">'
                  . '<a href="/cms/articles/edit?id=' . $id . '" class="btn-ghost btn-tiny">Edit</a>'
                  . '<form method="post" action="/cms/articles/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this article? This cannot be undone.">'
                  .   '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                  .   '<button type="submit" class="btn-ghost btn-tiny btn-danger">Delete</button>'
                  . '</form>'
                  . '</div>';

              $rows[] = [
                  ['html' => $titleHtml],
                  ['html' => $stagePill((string)($a['status'] ?? 'draft'))],
                  ['html' => $specialHtml],
                  ['html' => '<span class="muted">' . $e($updatedShort) . '</span>'],
                  ['html' => $actionsHtml, 'class' => 'cell-actions'],
              ];
          }

          $empty_text = 'No articles yet. Click + New Article to start.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
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
