<?php
/**
 * cms/views/live-sessions.php — Live Sessions list (Phase 9 / Phase 12).
 *
 * Two stacked tables grouped by stage (matches the articles list pattern):
 *   - Drafts — idea + draft rows, soonest event first (undated last)
 *   - Published — every live row, soonest upcoming first then past last
 *
 * Past published rows get a "(past)" suffix on the event-date cell.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';

// Phase 20.3 filter bar — multi-select per-type stages + categories.
$filterStages     = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['stage']    ?? ''))), 'strlen'));
$filterCategories = array_values(array_filter(array_map('trim', explode(',', (string)($_GET['category'] ?? ''))), 'strlen'));

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();
$sessions      = list_live_sessions([
    'stage'    => implode(',', $filterStages),
    'category' => implode(',', $filterCategories),
]);
$allCategories = list_categories('live-session');
$flash      = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

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
<title>Live Sessions — alexmchong.ca CMS</title>
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
$breadcrumb = 'Live Sessions';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'live-sessions';
  $nav_counts    = ['live-sessions' => count(array_filter($sessions, fn($s) => ($s['status'] ?? '') === 'published'))];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-live-sessions">
      <?php
      $title    = 'Live Sessions';
      $subtitle = 'Talks, workshops, and conversations. Past events stay live with a PAST badge.';
      $actions  = '<a href="/live-sessions/" target="_blank" rel="noopener" class="btn-sec">View live index ↗</a>'
                . '<a href="/cms/live-sessions/new" class="btn-pri">+ New Live Session</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      // Live-session pipeline is idea→draft→published — only Draft/Scheduled/
      // Published render in the stage bar (no Concept/Outline).
      $groups = build_filter_groups('live-session', '/cms/live-sessions', $filterStages, $filterCategories, $allCategories);
      require __DIR__ . '/../partials/filter-bar.php';
      ?>

      <div class="content-area">
        <?php require __DIR__ . '/../partials/flash.php'; ?>

        <?php
        // Split sessions into Drafts (idea + draft) and Published. Matches
        // the articles list pattern. Within each group, soonest event first
        // (undated rows last), and within Published, past events sink to
        // the bottom after upcoming.
        // Three sections: Drafts (concept + outline + draft) / Scheduled /
        // Published (live). Idea-stage hidden — Ideation view only.
        $nowTs     = time();
        $drafts    = [];
        $scheduled = [];
        $published = [];
        foreach ($sessions as $s) {
            $status = (string)($s['status'] ?? '');
            $pubStatus = (string)($s['published_status'] ?? '');
            if ($status === 'idea') continue;
            if ($status === 'published') {
                if ($pubStatus === 'scheduled') { $scheduled[] = $s; }
                else { $published[] = $s; }
            } else {
                $drafts[] = $s;
            }
        }

        // Sort helper: soonest event_date first, undated last.
        $sortByDate = static function (array $a, array $b): int {
            $ad = (string)($a['event_date'] ?? '');
            $bd = (string)($b['event_date'] ?? '');
            if ($ad === '' && $bd === '') return 0;
            if ($ad === '') return 1;
            if ($bd === '') return -1;
            return strcmp($ad, $bd);
        };
        usort($drafts, $sortByDate);

        // Published: upcoming first (soonest), past last (most recent past
        // at the top of the past group).
        $upcomingPub = [];
        $pastPub     = [];
        foreach ($published as $p) {
            $eDate = (string)($p['event_date']     ?? '');
            $eTime = (string)($p['event_time']     ?? '');
            $eEnd  = (string)($p['event_end_time'] ?? '');
            if ($eDate === '') { $upcomingPub[] = $p; continue; }
            $cmpTime = $eEnd !== '' ? $eEnd : ($eTime !== '' ? $eTime : '23:59');
            $eventTs = strtotime($eDate . ' ' . $cmpTime);
            if ($eventTs !== false && $eventTs < $nowTs) {
                $pastPub[] = $p;
            } else {
                $upcomingPub[] = $p;
            }
        }
        usort($upcomingPub, $sortByDate);
        usort($pastPub, static fn(array $a, array $b): int =>
            strcmp((string)($b['event_date'] ?? ''), (string)($a['event_date'] ?? ''))
        );
        $published = array_merge($upcomingPub, $pastPub);

        $makeColumns = static function (string $dateLabel): array {
            return [
                ['label' => 'Event Title', 'width' => '32%'],
                ['label' => 'Stage',       'width' => '10%'],
                ['label' => 'Category',    'width' => '14%'],
                ['label' => 'Event Date',  'width' => '20%'],
                ['label' => $dateLabel,    'width' => '12%'],
                ['label' => '',            'width' => '12%'],
            ];
        };

        $buildRow = static function (array $s, string $dateMode) use ($e, $csrf_token, $stagePill, $nowTs): array {
            $id      = (int)($s['id'] ?? 0);
            $slug    = (string)($s['slug'] ?? '');
            $title2  = (string)($s['title'] ?? '');
            $updated = (string)($s['updated_at'] ?? '');
            $pubAt   = (string)($s['published_at'] ?? '');
            $dateRaw = $dateMode === 'published' ? ($pubAt !== '' ? $pubAt : $updated) : $updated;
            $dateShort = $dateRaw !== '' ? date('Y-m-d', strtotime($dateRaw)) : '';

            $eDate    = (string)($s['event_date']     ?? '');
            $eTimeRaw = (string)($s['event_time']     ?? '');
            $eEndRaw  = (string)($s['event_end_time'] ?? '');
            $eTime    = $eTimeRaw !== '' ? substr($eTimeRaw, 0, 5) : '';
            $eEnd     = $eEndRaw  !== '' ? substr($eEndRaw,  0, 5) : '';
            $whenHtml = '<span class="muted">—</span>';
            if ($eDate !== '') {
                $dateTs   = strtotime($eDate) ?: $nowTs;
                $datePart = date('M j, Y', $dateTs);

                $timePart = '';
                if ($eTime !== '') {
                    $sTs   = strtotime($eTime);
                    $sLbl  = $sTs !== false ? date('g:i A', $sTs) : $eTime;
                    if ($eEnd !== '') {
                        $eTs   = strtotime($eEnd);
                        $eLbl  = $eTs !== false ? date('g:i A', $eTs) : $eEnd;
                        // Drop the meridiem from start when start/end share it.
                        $sMer  = $sTs !== false ? date('A', $sTs) : '';
                        $eMer  = $eTs !== false ? date('A', $eTs) : '';
                        $sBare = $sTs !== false ? date('g:i', $sTs) : $eTime;
                        $sStr  = ($sMer !== '' && $sMer === $eMer) ? $sBare : $sLbl;
                        $timePart = ' · ' . $sStr . ' – ' . $eLbl;
                    } else {
                        $timePart = ' · ' . $sLbl;
                    }
                }

                $cmpTime = $eEnd !== '' ? $eEnd : ($eTime !== '' ? $eTime : '23:59');
                $eventTs = strtotime($eDate . ' ' . $cmpTime);
                $pastSuffix = '';
                if ($eventTs !== false && $eventTs < $nowTs && (string)($s['status'] ?? '') === 'published') {
                    $pastSuffix = ' (past)';
                }

                $whenHtml = '<span class="muted">' . $e($datePart . $timePart . $pastSuffix) . '</span>';
            }

            $titleHtml = '<a href="/cms/live-sessions/edit?id=' . $id . '&from=live-sessions" class="row-title">'
                       . $e($title2 !== '' ? $title2 : '(untitled)')
                       . '</a>'
                       . '<div class="row-slug">/' . $e($slug) . '</div>';

            $catLabel  = (string)($s['category_label']  ?? '');
            $catColour = (string)($s['category_colour'] ?? '');
            if ($catLabel !== '') {
                $bg = $catColour !== '' ? 'var(--c-' . $e($catColour) . ')' : 'var(--ink-30)';
                $catHtml = '<span class="cat-chip" style="--cat-bg:' . $bg . '">' . $e($catLabel) . '</span>';
            } else {
                $catHtml = '<span class="muted">—</span>';
            }

            $isLiveRow = (string)($s['status'] ?? '') === 'published'
                      && (string)($s['published_status'] ?? '') !== 'scheduled';
            $liveBtn = $isLiveRow && $slug !== ''
                ? '<a href="/live-sessions/' . $e($slug) . '" target="_blank" rel="noopener" class="btn-sec btn-tiny row-action-live" title="Open the live published page">Live ↗</a>'
                : '';

            $actionsHtml = '<div class="row-actions">'
                . $liveBtn
                . '<span class="row-actions-hover">'
                .   '<a href="/cms/live-sessions/edit?id=' . $id . '&from=live-sessions" class="btn-sec btn-tiny">Edit</a>'
                .   '<form method="post" action="/cms/live-sessions/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this session? This cannot be undone.">'
                .     '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                .     '<button type="submit" class="btn-icon btn-icon-danger" title="Delete" aria-label="Delete">'
                .       '<svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                .     '</button>'
                .   '</form>'
                . '</span>'
                . '</div>';

            return [
                'href'  => '/cms/live-sessions/edit?id=' . $id . '&from=live-sessions',
                'cells' => [
                    ['html' => $titleHtml],
                    ['html' => $stagePill(((string)($s['status'] ?? '') === 'published' && (string)($s['published_status'] ?? '') === 'scheduled') ? 'scheduled' : (string)($s['status'] ?? 'idea'))],
                    ['html' => $catHtml],
                    ['html' => $whenHtml],
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
          $rows       = array_map(static fn($s) => $buildRow($s, 'updated'), $drafts);
          $empty_text = 'No live session drafts yet — click [+ New Session] to start.';
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
          $rows       = array_map(static fn($s) => $buildRow($s, 'published'), $scheduled);
          $empty_text = 'No scheduled sessions.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
        <?php endif; ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Published</span>
              <span class="content-block-sublabel">Live at /live-sessions/&lt;slug&gt;</span>
            </div>
            <span class="content-block-count"><?= (int)count($published) ?> entries</span>
          </div>

          <?php
          $columns    = $makeColumns('Published');
          $rows       = array_map(static fn($s) => $buildRow($s, 'published'), $published);
          $empty_text = 'No published sessions yet.';
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
