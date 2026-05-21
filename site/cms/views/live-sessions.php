<?php
/**
 * cms/views/live-sessions.php — Live Sessions list (Phase 9).
 *
 * Mirrors articles.php / journals.php. The "When" column shows the
 * event_start datetime when present; rows whose event_start is in the
 * past get a "PAST" pill alongside the stage pill.
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

  <main class="main">
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

        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">All sessions</span>
              <span class="content-block-sublabel">Idea · Draft · Published</span>
            </div>
            <span class="content-block-count"><?= (int)count($sessions) ?> entries</span>
          </div>

          <?php
          $columns = [
              ['label' => 'Title',   'width' => '40%'],
              ['label' => 'Stage',   'width' => '12%'],
              ['label' => 'When',    'width' => '20%'],
              ['label' => 'Updated', 'width' => '14%'],
              ['label' => 'Actions', 'width' => '14%'],
          ];

          $rows = [];
          $nowTs = time();
          foreach ($sessions as $s) {
              $id      = (int)($s['id'] ?? 0);
              $slug    = (string)($s['slug'] ?? '');
              $title2  = (string)($s['title'] ?? '');
              $updated = (string)($s['updated_at'] ?? '');
              $updatedShort = $updated !== '' ? date('Y-m-d', strtotime($updated)) : '';

              $eDate     = (string)($s['event_date']     ?? '');
              $eTimeRaw  = (string)($s['event_time']     ?? '');
              $eEndRaw   = (string)($s['event_end_time'] ?? '');
              $eTime     = $eTimeRaw !== '' ? substr($eTimeRaw, 0, 5) : '';
              $eEnd      = $eEndRaw  !== '' ? substr($eEndRaw,  0, 5) : '';
              $whenHtml  = '<span class="muted">—</span>';
              $pastTag   = '';
              if ($eDate !== '') {
                  $datePart = date('Y-m-d', strtotime($eDate) ?: $nowTs);
                  $timePart = $eTime !== ''
                      ? ' · ' . $eTime . ($eEnd !== '' ? '–' . $eEnd : '')
                      : '';
                  $whenHtml = '<span class="muted">' . $e($datePart . $timePart) . '</span>';

                  // PAST: row is published AND the event is in the past.
                  // Use end time if set, else start time, else end-of-day.
                  $cmpTime = $eEnd !== '' ? $eEnd : ($eTime !== '' ? $eTime : '23:59');
                  $eventTs = strtotime($eDate . ' ' . $cmpTime);
                  if ($eventTs !== false && $eventTs < $nowTs && (string)($s['status'] ?? '') === 'published') {
                      $pastTag = ' <span class="pill pill-past">PAST</span>';
                  }
              }

              $titleHtml = '<a href="/cms/live-sessions/edit?id=' . $id . '" class="row-title">'
                         . $e($title2 !== '' ? $title2 : '(untitled)')
                         . '</a>'
                         . ' <span class="row-slug">/' . $e($slug) . '</span>';

              $actionsHtml = '<div class="row-actions">'
                  . '<a href="/cms/live-sessions/edit?id=' . $id . '" class="btn-ghost btn-tiny">Edit</a>'
                  . '<form method="post" action="/cms/live-sessions/delete?id=' . $id . '" class="inline-delete" data-confirm="Delete this session? This cannot be undone.">'
                  .   '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                  .   '<button type="submit" class="btn-ghost btn-tiny btn-danger">Delete</button>'
                  . '</form>'
                  . '</div>';

              $rows[] = [
                  'href'  => '/cms/live-sessions/edit?id=' . $id,
                  'cells' => [
                      ['html' => $titleHtml],
                      ['html' => $stagePill((string)($s['status'] ?? 'idea')) . $pastTag],
                      ['html' => $whenHtml],
                      ['html' => '<span class="muted">' . $e($updatedShort) . '</span>'],
                      ['html' => $actionsHtml, 'class' => 'cell-actions'],
                  ],
              ];
          }

          $empty_text = 'No live sessions yet. Click + New Session to start.';
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
