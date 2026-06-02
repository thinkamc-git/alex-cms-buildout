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

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();
$sessions   = list_live_sessions();
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
  $nav_counts    = ['live-sessions' => count($sessions)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-live-sessions">
      <?php
      $title    = 'Live Sessions';
      $subtitle = 'Talks, workshops, conversations. Past events stay live with a PAST badge.';
      $actions  = '<a href="/cms/live-sessions/new" class="btn-pri">+ New Session</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status"><?= $e($flash) ?></div>
        <?php endif; ?>

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

        $columns = [
            ['label' => 'Event Title', 'width' => '40%'],
            ['label' => 'Stage',       'width' => '12%'],
            ['label' => 'Event Date',  'width' => '22%'],
            ['label' => 'Updated',     'width' => '12%'],
            ['label' => 'Actions',     'width' => '14%'],
        ];

        $buildRow = static function (array $s) use ($e, $csrf_token, $stagePill, $nowTs): array {
            $id      = (int)($s['id'] ?? 0);
            $slug    = (string)($s['slug'] ?? '');
            $title2  = (string)($s['title'] ?? '');
            $updated = (string)($s['updated_at'] ?? '');
            $updatedShort = $updated !== '' ? date('Y-m-d', strtotime($updated)) : '';

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

            $titleHtml = '<a href="/cms/live-sessions/edit?id=' . $id . '" class="row-title">'
                       . $e($title2 !== '' ? $title2 : '(untitled)')
                       . '</a>'
                       . ' <span class="row-slug">/' . $e($slug) . '</span>';

            $isPublished = (string)($s['status'] ?? '') === 'published';
            $liveBtn = $isPublished && $slug !== ''
                ? '<a href="/live-sessions/' . $e($slug) . '" target="_blank" rel="noopener" class="btn-ghost btn-tiny" title="Open the live published page">Live ↗</a>'
                : '';

            $actionsHtml = '<div class="row-actions">'
                . $liveBtn
                . '<a href="/cms/live-sessions/edit?id=' . $id . '" class="btn-ghost btn-tiny">Edit</a>'
                . '<form method="post" action="/cms/live-sessions/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this session? This cannot be undone.">'
                .   '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                .   '<button type="submit" class="btn-ghost btn-tiny btn-danger">Delete</button>'
                . '</form>'
                . '</div>';

            return [
                'href'  => '/cms/live-sessions/edit?id=' . $id,
                'cells' => [
                    ['html' => $titleHtml],
                    ['html' => $stagePill(((string)($s['status'] ?? '') === 'published' && (string)($s['published_status'] ?? '') === 'scheduled') ? 'scheduled' : (string)($s['status'] ?? 'idea'))],
                    ['html' => $whenHtml],
                    ['html' => '<span class="muted">' . $e($updatedShort) . '</span>'],
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
          $rows = array_map($buildRow, $drafts);
          $empty_text = 'No drafts. Click + New Session to add one.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>

        <?php if (count($scheduled) > 0): ?>
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Scheduled</span>
              <span class="content-block-sublabel">Queued for future publish — cron promotes to Live</span>
            </div>
            <span class="content-block-count"><?= (int)count($scheduled) ?> entries</span>
          </div>

          <?php
          $rows = array_map($buildRow, $scheduled);
          $empty_text = 'No scheduled sessions.';
          require __DIR__ . '/../partials/table.php';
          ?>
        </div>
        <?php endif; ?>

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">Published</span>
              <span class="content-block-sublabel">Live on /live-sessions/[slug]</span>
            </div>
            <span class="content-block-count"><?= (int)count($published) ?> entries</span>
          </div>

          <?php
          $rows = array_map($buildRow, $published);
          $empty_text = 'No published sessions yet.';
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
