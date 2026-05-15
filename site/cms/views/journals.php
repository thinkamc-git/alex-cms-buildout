<?php
/**
 * cms/views/journals.php — Journals list (Phase 8).
 *
 * Mirrors cms/views/articles.php in structure. The display title comes
 * from key_statement when set (Draft/Published), else falls back to the
 * working title used during Idea/Concept/Outline stages.
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
$journals   = list_journals();
$flash      = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

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
<title>Journals — alexmchong.ca CMS</title>
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
$breadcrumb = 'Journals';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'journals';
  $nav_counts    = ['journals' => count($journals)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-journals">
      <?php
      $title    = 'Journals';
      $subtitle = 'Short, declarative entries. Each gets a per-category Entry number when published.';
      $actions  = '<a href="/cms/journals/new" class="btn-pri">+ New Journal</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status"><?= $e($flash) ?></div>
        <?php endif; ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">All journals</span>
              <span class="content-block-sublabel">Idea · Concept · Outline · Draft · Published</span>
            </div>
            <span class="content-block-count"><?= (int)count($journals) ?> entries</span>
          </div>

          <?php
          $columns = [
              ['label' => 'Key Statement', 'width' => '52%'],
              ['label' => 'Stage',         'width' => '12%'],
              ['label' => 'Entry #',       'width' => '10%'],
              ['label' => 'Updated',       'width' => '14%'],
              ['label' => 'Actions',       'width' => '12%'],
          ];

          $rows = [];
          foreach ($journals as $j) {
              $id           = (int)($j['id'] ?? 0);
              $slug         = (string)($j['slug'] ?? '');
              $keyStatement = (string)($j['key_statement'] ?? '');
              $workingTitle = (string)($j['title'] ?? '');
              $display      = $keyStatement !== '' ? $keyStatement : ($workingTitle !== '' ? $workingTitle : '(untitled)');
              $entryNum     = $j['journal_number'] !== null
                  ? str_pad((string)(int)$j['journal_number'], 3, '0', STR_PAD_LEFT)
                  : null;
              $updated      = (string)($j['updated_at'] ?? '');
              $updatedShort = $updated !== '' ? date('Y-m-d', strtotime($updated)) : '';

              $titleHtml = '<a href="/cms/journals/edit?id=' . $id . '" class="row-title">'
                         . $e($display)
                         . '</a>'
                         . ' <span class="row-slug">/' . $e($slug) . '</span>';

              $entryHtml = $entryNum !== null
                  ? '<span class="pill">Entry ' . $e($entryNum) . '</span>'
                  : '<span class="muted">—</span>';

              $actionsHtml = '<div class="row-actions">'
                  . '<a href="/cms/journals/edit?id=' . $id . '" class="btn-ghost btn-tiny">Edit</a>'
                  . '<form method="post" action="/cms/journals/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this journal? This cannot be undone.">'
                  .   '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                  .   '<button type="submit" class="btn-ghost btn-tiny btn-danger">Delete</button>'
                  . '</form>'
                  . '</div>';

              $rows[] = [
                  'href'  => '/cms/journals/edit?id=' . $id,
                  'cells' => [
                      ['html' => $titleHtml],
                      ['html' => $stagePill((string)($j['status'] ?? 'idea'))],
                      ['html' => $entryHtml],
                      ['html' => '<span class="muted">' . $e($updatedShort) . '</span>'],
                      ['html' => $actionsHtml, 'class' => 'cell-actions'],
                  ],
              ];
          }

          $empty_text = 'No journals yet. Click + New Journal to start.';
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

  for (const form of document.querySelectorAll('form.inline-delete')) {
    form.addEventListener('submit', (e) => {
      const msg = form.getAttribute('data-confirm') || 'Delete?';
      if (!window.confirm(msg)) e.preventDefault();
    });
  }
</script>

</body>
</html>
