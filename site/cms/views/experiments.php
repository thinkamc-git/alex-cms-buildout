<?php
/**
 * cms/views/experiments.php — Experiments list (Phase 10).
 *
 * Two stacked tables — "In progress" (idea/draft) vs "Published" — mirrors
 * the Articles list pattern. The Template column flags which of the two
 * variants each row is (`experiment` article-format vs `experiment-html`
 * raw passthrough); that, plus a folder-status hint on -html rows, is the
 * only column unique to Experiments.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';
require_once __DIR__ . '/../../lib/folders.php';

Auth::require_login();
$user        = Auth::current_user();
$email       = (string)($user['email'] ?? '');
$csrf_token  = Csrf::token();
$experiments = list_experiments();
$flash       = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

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
<title>Experiments — alexmchong.ca CMS</title>
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
$breadcrumb = 'Experiments';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'experiments';
  $nav_counts    = ['experiments' => count($experiments)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-experiments">
      <?php
      $title    = 'Experiments';
      $subtitle = 'Prototypes, custom HTML, and standalone pieces. Two variants: article-format and raw HTML import.';
      $actions  = '<a href="/cms/experiments/new" class="btn-pri">+ New Experiment</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status"><?= $e($flash) ?></div>
        <?php endif; ?>

        <?php
        $inProgress = [];
        $published  = [];
        foreach ($experiments as $x) {
            if ((string)($x['status'] ?? '') === 'published') {
                $published[] = $x;
            } else {
                $inProgress[] = $x;
            }
        }

        $columns = [
            ['label' => 'Experiment Title', 'width' => '36%'],
            ['label' => 'Stage',            'width' => '12%'],
            ['label' => 'Template',         'width' => '18%'],
            ['label' => 'Updated',          'width' => '16%'],
            ['label' => 'Actions',          'width' => '18%'],
        ];

        $buildRow = static function (array $x) use ($e, $csrf_token, $stagePill): array {
            $id      = (int)($x['id'] ?? 0);
            $slug    = (string)($x['slug']        ?? '');
            $title2  = (string)($x['title']       ?? '');
            $tpl     = (string)($x['template']    ?? '');
            $srcFile = (string)($x['source_file'] ?? '');
            $updated = (string)($x['updated_at']  ?? '');
            $updatedShort = $updated !== '' ? date('Y-m-d', strtotime($updated)) : '';

            $titleHtml = '<a href="/cms/experiments/edit?id=' . $id . '" class="row-title">'
                       . $e($title2 !== '' ? $title2 : '(untitled)')
                       . '</a>'
                       . ' <span class="row-slug">/' . $e($slug) . '</span>';

            // Template cell: surface the variant + a folder hint for html rows.
            if ($tpl === 'experiment-html') {
                $folderOk = $slug !== '' && folder_exists('experiment', $slug);
                $hint = !$folderOk
                    ? ' <span class="muted">· no folder</span>'
                    : ($srcFile === '' ? ' <span class="muted">· no file picked</span>' : '');
                $tplHtml = '<span class="mono">experiment-html</span>' . $hint;
            } elseif ($tpl === 'experiment') {
                $tplHtml = '<span class="mono">experiment</span>';
            } else {
                $tplHtml = '<span class="muted">—</span>';
            }

            $actionsHtml = '<div class="row-actions">'
                . '<a href="/cms/experiments/edit?id=' . $id . '" class="btn-ghost btn-tiny">Edit</a>'
                . '<form method="post" action="/cms/experiments/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this experiment? This cannot be undone.">'
                .   '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                .   '<button type="submit" class="btn-ghost btn-tiny btn-danger">Delete</button>'
                . '</form>'
                . '</div>';

            return [
                'href'  => '/cms/experiments/edit?id=' . $id,
                'cells' => [
                    ['html' => $titleHtml],
                    ['html' => $stagePill((string)($x['status'] ?? 'draft'))],
                    ['html' => $tplHtml],
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
              <span class="content-block-sublabel">Idea · Draft</span>
            </div>
            <span class="content-block-count"><?= (int)count($inProgress) ?> entries</span>
          </div>

          <?php
          $rows = array_map($buildRow, $inProgress);
          $empty_text = 'No experiments in progress. Click + New Experiment to start.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Published</span>
              <span class="content-block-sublabel">Live on /experiments/[slug]</span>
            </div>
            <span class="content-block-count"><?= (int)count($published) ?> entries</span>
          </div>

          <?php
          $rows = array_map($buildRow, $published);
          $empty_text = 'No published experiments yet.';
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
