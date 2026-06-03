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

// Phase 20.3 filter bar — multi-select per-type stages + categories.
$filterStages     = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['stage']    ?? ''))), 'strlen'));
$filterCategories = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['category'] ?? ''))), 'strlen'));
$journals       = list_journals([
    'stage'    => implode(',', $filterStages),
    'category' => implode(',', $filterCategories),
]);
$allCategories  = list_categories('journal');

$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

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
  $nav_counts    = ['journals' => count(array_filter($journals, fn($j) => ($j['status'] ?? '') === 'published'))];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-journals">
      <?php
      $title    = 'Journals';
      $subtitle = 'Short, declarative entries. Each gets a per-category entry number on first publish.';
      $actions  = '<a href="/journal/" target="_blank" rel="noopener" class="btn-sec">View live index ↗</a>'
                . '<a href="/cms/journals/new" class="btn-pri">+ New Journal</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      // Journal pipeline is idea→draft→published — only Draft/Scheduled/
      // Published render in the stage bar (no Concept/Outline).
      $groups = build_filter_groups('journal', '/cms/journals', $filterStages, $filterCategories, $allCategories);
      require __DIR__ . '/../partials/filter-bar.php';
      ?>

      <div class="content-area">
        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <?php
        // Three sections: Drafts (concept + outline + draft) / Scheduled /
        // Published. Idea-stage hidden — Ideation view only.
        $drafts    = [];
        $scheduled = [];
        $published = [];
        foreach ($journals as $j) {
            $st = (string)($j['status'] ?? '');
            $ps = (string)($j['published_status'] ?? '');
            if ($st === 'idea') continue;
            if ($st === 'published') {
                if ($ps === 'scheduled') { $scheduled[] = $j; } else { $published[] = $j; }
            } else {
                $drafts[] = $j;
            }
        }

        // Shared column set across Drafts / Scheduled / Published — only
        // the Date header label changes per section.
        $makeColumns = static function (string $dateLabel): array {
            return [
                ['label' => 'Key Statement', 'width' => '38%'],
                ['label' => 'Stage',         'width' => '10%'],
                ['label' => 'Category',      'width' => '14%'],
                ['label' => 'Entry #',       'width' => '8%'],
                ['label' => $dateLabel,      'width' => '14%'],
                ['label' => '',              'width' => '14%'],
            ];
        };

        $buildRow = static function (array $j, string $dateMode) use ($e, $csrf_token, $stagePill): array {
            $id           = (int)($j['id'] ?? 0);
            $slug         = (string)($j['slug'] ?? '');
            $keyStatement = (string)($j['key_statement'] ?? '');
            $workingTitle = (string)($j['title'] ?? '');
            $display      = $keyStatement !== '' ? $keyStatement : ($workingTitle !== '' ? $workingTitle : '(untitled)');
            $entryNum     = $j['journal_number'] !== null
                ? str_pad((string)(int)$j['journal_number'], 3, '0', STR_PAD_LEFT)
                : null;
            $updated = (string)($j['updated_at'] ?? '');
            $pubAt   = (string)($j['published_at'] ?? '');
            $dateRaw = $dateMode === 'published' ? ($pubAt !== '' ? $pubAt : $updated) : $updated;
            $dateShort = $dateRaw !== '' ? date('Y-m-d', strtotime($dateRaw)) : '';

            $titleHtml = '<a href="/cms/journals/edit?id=' . $id . '&from=journals" class="row-title">'
                       . $e($display)
                       . '</a>'
                       . '<div class="row-slug">/' . $e($slug) . '</div>';

            $catLabel  = (string)($j['category_label']  ?? '');
            $catColour = (string)($j['category_colour'] ?? '');
            if ($catLabel !== '') {
                $bg      = $catColour !== '' ? 'var(--c-' . $e($catColour) . ')' : 'var(--ink-30)';
                $catHtml = '<span class="cat-chip" style="--cat-bg:' . $bg . '">' . $e($catLabel) . '</span>';
            } else {
                $catHtml = '<span class="muted">—</span>';
            }

            // Entry # only renders for published rows — unpublished journals
            // don't have a stable number yet (assigned on publish, sequential
            // per category).
            $entryHtml = $entryNum !== null
                ? '<span class="val-pill">#' . $e($entryNum) . '</span>'
                : '<span class="muted">—</span>';

            $isLiveRow = (string)($j['status'] ?? '') === 'published'
                      && (string)($j['published_status'] ?? '') !== 'scheduled';
            $liveBtn = $isLiveRow && $slug !== ''
                ? '<a href="/journal/' . $e($slug) . '" target="_blank" rel="noopener" class="btn-sec btn-tiny row-action-live" title="Open the live published page">Live ↗</a>'
                : '';

            $actionsHtml = '<div class="row-actions">'
                . $liveBtn
                . '<span class="row-actions-hover">'
                .   '<a href="/cms/journals/edit?id=' . $id . '&from=journals" class="btn-sec btn-tiny">Edit</a>'
                .   '<form method="post" action="/cms/journals/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this journal? This cannot be undone.">'
                .     '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                .     '<button type="submit" class="btn-icon btn-icon-danger" title="Delete" aria-label="Delete">'
                .       '<svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                .     '</button>'
                .   '</form>'
                . '</span>'
                . '</div>';

            return [
                'href'  => '/cms/journals/edit?id=' . $id . '&from=journals',
                'cells' => [
                    ['html' => $titleHtml],
                    ['html' => $stagePill(((string)($j['status'] ?? '') === 'published' && (string)($j['published_status'] ?? '') === 'scheduled') ? 'scheduled' : (string)($j['status'] ?? 'idea'))],
                    ['html' => $catHtml],
                    ['html' => $entryHtml],
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
          $rows       = array_map(static fn($j) => $buildRow($j, 'updated'), $drafts);
          $empty_text = 'No journal drafts yet — click [+ New Journal] to start.';
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
          $rows       = array_map(static fn($j) => $buildRow($j, 'published'), $scheduled);
          $empty_text = 'No scheduled journals.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
        <?php endif; ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Published</span>
              <span class="content-block-sublabel">Live at /journal/&lt;slug&gt;</span>
            </div>
            <span class="content-block-count"><?= (int)count($published) ?> entries</span>
          </div>
          <?php
          $columns    = $makeColumns('Published');
          $rows       = array_map(static fn($j) => $buildRow($j, 'published'), $published);
          $empty_text = 'No published journals yet.';
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
