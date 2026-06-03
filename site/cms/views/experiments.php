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
// Phase 20.3 filter bar — multi-select per-type stages + categories.
$filterStages     = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['stage']    ?? ''))), 'strlen'));
$filterCategories = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['category'] ?? ''))), 'strlen'));
$experiments   = list_experiments([
    'stage'    => implode(',', $filterStages),
    'category' => implode(',', $filterCategories),
]);
$allCategories = list_categories('experiment');
$flash       = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . '/../../lib/pills.php';
$stagePill = static function (string $status): string {
    return cms_pill_stage($status);
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
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
  $nav_counts    = ['experiments' => count(array_filter($experiments, fn($x) => ($x['status'] ?? '') === 'published'))];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-experiments">
      <?php
      $title    = 'Experiments';
      $subtitle = 'Prototypes, custom HTML, and standalone pieces. Three body modes: rich text, HTML body file, or full HTML swap.';
      $actions  = '<a href="/experiments/" target="_blank" rel="noopener" class="btn-sec">View live index ↗</a>'
                . '<a href="/cms/experiments/new" class="btn-pri">+ New Experiment</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      // Experiment pipeline is idea→draft→published — only Draft/Scheduled/
      // Published render in the stage bar (no Concept/Outline).
      $groups = build_filter_groups('experiment', '/cms/experiments', $filterStages, $filterCategories, $allCategories);
      require __DIR__ . '/../partials/filter-bar.php';
      ?>

      <div class="content-area">
        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <?php
        // Three sections: Drafts (concept + outline + draft) / Scheduled / Published (live).
        // Idea-stage hidden — Ideation view only.
        $drafts    = [];
        $scheduled = [];
        $published = [];
        foreach ($experiments as $x) {
            $st = (string)($x['status'] ?? '');
            $ps = (string)($x['published_status'] ?? '');
            if ($st === 'idea') continue;
            if ($st === 'published') {
                if ($ps === 'scheduled') { $scheduled[] = $x; } else { $published[] = $x; }
            } else {
                $drafts[] = $x;
            }
        }

        $makeColumns = static function (string $dateLabel): array {
            return [
                ['label' => 'Experiment Title', 'width' => '32%'],
                ['label' => 'Stage',            'width' => '10%'],
                ['label' => 'Category',         'width' => '13%'],
                ['label' => 'Content Type',     'width' => '15%'],
                ['label' => $dateLabel,         'width' => '14%'],
                ['label' => '',                 'width' => '16%'],
            ];
        };

        $buildRow = static function (array $x, string $dateMode) use ($e, $csrf_token, $stagePill): array {
            $id      = (int)($x['id'] ?? 0);
            $slug    = (string)($x['slug']        ?? '');
            $title2  = (string)($x['title']       ?? '');
            $tpl      = (string)($x['template']    ?? '');
            $bodyMode = (string)($x['body_mode']   ?? 'rtf');
            $srcFile  = (string)($x['source_file'] ?? '');
            $updated  = (string)($x['updated_at'] ?? '');
            $pubAt    = (string)($x['published_at'] ?? '');
            $dateRaw  = $dateMode === 'published' ? ($pubAt !== '' ? $pubAt : $updated) : $updated;
            $dateShort = $dateRaw !== '' ? date('Y-m-d', strtotime($dateRaw)) : '';

            $titleHtml = '<a href="/cms/experiments/edit?id=' . $id . '&from=experiments" class="row-title">'
                       . $e($title2 !== '' ? $title2 : '(untitled)')
                       . '</a>'
                       . '<div class="row-slug">/' . $e($slug) . '</div>';

            $catLabel  = (string)($x['category_label']  ?? '');
            $catColour = (string)($x['category_colour'] ?? '');
            if ($catLabel !== '') {
                $bg = $catColour !== '' ? 'var(--c-' . $e($catColour) . ')' : 'var(--ink-30)';
                $catHtml = '<span class="cat-chip" style="--cat-bg:' . $bg . '">' . $e($catLabel) . '</span>';
            } else {
                $catHtml = '<span class="muted">—</span>';
            }

            // Content Type: grey pill carrying the body_mode label, with a
            // muted hint on its own line when a file-backed mode lacks its
            // folder or file.
            $modeLabel = $tpl === 'experiment' ? $bodyMode : '—';
            $hint = '';
            if ($tpl === 'experiment' && in_array($bodyMode, ['html-body', 'html-swap'], true)) {
                $folderOk = $slug !== '' && folder_exists('experiment', $slug);
                $hint = !$folderOk
                    ? '<div class="row-cell-hint">no folder</div>'
                    : ($srcFile === '' ? '<div class="row-cell-hint">no file picked</div>' : '');
            }
            $tplHtml = $modeLabel === '—'
                ? '<span class="muted">—</span>'
                : '<span class="content-type-pill">' . $e($modeLabel) . '</span>' . $hint;

            $isLiveRow = (string)($x['status'] ?? '') === 'published'
                      && (string)($x['published_status'] ?? '') !== 'scheduled';
            $liveBtn = $isLiveRow && $slug !== ''
                ? '<a href="/experiments/' . $e($slug) . '" target="_blank" rel="noopener" class="btn-sec btn-tiny row-action-live" title="Open the live published page">Live ↗</a>'
                : '';

            $actionsHtml = '<div class="row-actions">'
                . $liveBtn
                . '<span class="row-actions-hover">'
                .   '<a href="/cms/experiments/edit?id=' . $id . '&from=experiments" class="btn-sec btn-tiny">Edit</a>'
                .   '<form method="post" action="/cms/experiments/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this experiment? This cannot be undone.">'
                .     '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                .     '<button type="submit" class="btn-icon btn-icon-danger" title="Delete" aria-label="Delete">'
                .       '<svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                .     '</button>'
                .   '</form>'
                . '</span>'
                . '</div>';

            return [
                'href'  => '/cms/experiments/edit?id=' . $id . '&from=experiments',
                'cells' => [
                    ['html' => $titleHtml],
                    ['html' => $stagePill(((string)($x['status'] ?? '') === 'published' && (string)($x['published_status'] ?? '') === 'scheduled') ? 'scheduled' : (string)($x['status'] ?? 'draft'))],
                    ['html' => $catHtml],
                    ['html' => $tplHtml],
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
          $rows       = array_map(static fn($x) => $buildRow($x, 'updated'), $drafts);
          $empty_text = 'No experiment drafts yet — click [+ New Experiment] to start.';
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
          $rows       = array_map(static fn($x) => $buildRow($x, 'published'), $scheduled);
          $empty_text = 'No scheduled experiments.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
        <?php endif; ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Published</span>
              <span class="content-block-sublabel">Live at /experiments/&lt;slug&gt;</span>
            </div>
            <span class="content-block-count"><?= (int)count($published) ?> entries</span>
          </div>
          <?php
          $columns    = $makeColumns('Published');
          $rows       = array_map(static fn($x) => $buildRow($x, 'published'), $published);
          $empty_text = 'No published experiments yet.';
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
