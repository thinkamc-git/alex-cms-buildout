<?php
/**
 * cms/views/live-session-edit.php — edit a Live Session (Draft/Published).
 *
 * Idea-stage live-sessions live in the shared editor (article-edit.php);
 * they advance straight to Draft (live-sessions skip Concept/Outline per
 * spec §15).
 *
 * Routed from site/index.php:
 *   GET  /cms/live-sessions/edit?id=N
 *   POST /cms/live-sessions/edit?id=N
 *
 * Form shape (sidebar uses the same shell as article/journal edit):
 *   - Slug (required)
 *   - Title (required)
 *   - Summary (optional)
 *   - Event Details — event_start (datetime-local, required), location (required)
 *   - Format Pills — cost_pill (Free / Fee / custom string), attendance
 *     (in-person / remote), custom_pill (any short string). NULL hides each.
 *   - Body (Tiptap, required at Draft)
 *   - Tags (sidebar)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/uploads.php';

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /cms/live-sessions');
    exit;
}

$session = get_live_session($id);
if ($session === null) {
    $stmt = db()->prepare("SELECT id FROM content WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    if ($stmt->fetch() === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Live session not found.\n";
        exit;
    }
    header('Location: /cms/articles/edit?id=' . $id);
    exit;
}

$status = (string)($session['status'] ?? 'draft');
if ($status === 'idea') {
    header('Location: /cms/articles/edit?id=' . $id);
    exit;
}

$errors = [];
$flash  = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

$allLiveSessionCategories = list_categories('live-session');
$currentPrimaryCategory   = get_primary_category($id);

$undoSuffix = static function (string $action, string $current): string {
    return in_array($action, ['advance', 'publish'], true)
        ? '&from_stage=' . urlencode($current)
        : '';
};

$sessionStages = stages_for_type('live-session');

$actionToStage = static function (string $action, string $current) use ($sessionStages) {
    $idx = array_search($current, $sessionStages, true);
    switch ($action) {
        case 'advance':
            if ($idx === false || $idx >= count($sessionStages) - 1) return [null, ''];
            $next = $sessionStages[$idx + 1];
            return [$next, 'Advanced to ' . ucfirst($next) . '.'];
        case 'step-back':
            if ($idx === false || $idx <= 0) return [null, ''];
            $prev = $sessionStages[$idx - 1];
            return [$prev, 'Stepped back to ' . ucfirst($prev) . '.'];
        case 'publish':
            return ['published', 'Published — live now.'];
        case 'unpublish':
            return ['draft', 'Moved back to draft — no longer publicly visible.'];
    }
    return [null, ''];
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action       = (string)($_POST['action'] ?? 'save');
        $currentStage = $status;

        if ($action === 'undo') {
            $idx = array_search($currentStage, $sessionStages, true);
            if ($idx !== false && $idx > 0) {
                $prev = $sessionStages[$idx - 1];
                $res  = transition_stage($id, $prev);
                if ($res['ok']) {
                    $dest = $prev === 'idea'
                        ? '/cms/articles/edit?id=' . $id
                        : '/cms/live-sessions/edit?id=' . $id;
                    header('Location: ' . $dest
                        . '&flash=' . rawurlencode('Reverted to ' . ucfirst($prev) . '.'));
                    exit;
                }
            }
            header('Location: /cms/live-sessions/edit?id=' . $id);
            exit;
        }

        $post = [
            'slug'           => trim((string)($_POST['slug']           ?? '')),
            'title'          => trim((string)($_POST['title']          ?? '')),
            'summary'        => trim((string)($_POST['summary']        ?? '')),
            'event_date'     => trim((string)($_POST['event_date']     ?? '')),
            'event_time'     => trim((string)($_POST['event_time']     ?? '')),
            'event_end_time' => trim((string)($_POST['event_end_time'] ?? '')),
            'location'       => trim((string)($_POST['location']       ?? '')),
            'venue'          => trim((string)($_POST['venue']          ?? '')),
            'cost_pill'      => trim((string)($_POST['cost_pill']      ?? '')),
            'attendance'     => trim((string)($_POST['attendance']     ?? '')),
            'custom_pill'    => trim((string)($_POST['custom_pill']    ?? '')),
            'body_raw'       =>      (string)($_POST['body']           ?? ''),
            'tags'           => trim((string)($_POST['tags']           ?? '')),
            'primary_category' => trim((string)($_POST['primary_category'] ?? '')),
            'hero_caption'   => trim((string)($_POST['hero_caption']   ?? '')),
            'hero_size'      => trim((string)($_POST['hero_size']      ?? 'default')),
            // Hidden input flipped by the trash button — value '1' means remove.
            'remove_hero'    => (string)($_POST['remove_hero'] ?? '0') === '1',
        ];

        $allowedCatSlugs = array_map(static fn($c) => (string)$c['value_slug'], $allLiveSessionCategories);
        if ($post['primary_category'] !== '' && !in_array($post['primary_category'], $allowedCatSlugs, true)) {
            $errors[] = 'Primary category is not a known live-session category.';
            $post['primary_category'] = '';
        }

        if ($post['title'] === '') {
            $errors[] = 'Title is required.';
        }

        // Slug: auto-derive from title if blank.
        $slug = $post['slug'] !== ''
            ? slugify($post['slug'])
            : slugify($post['title']);
        if ($slug === '') {
            $errors[] = 'Slug is required.';
        } else {
            $slug = unique_slug($slug, $id);
        }

        // Event timing: date is required, time is optional. An end time is
        // only meaningful when a start time is set.
        $eventDate    = $post['event_date']     !== '' ? $post['event_date']     : null;
        $eventTime    = $post['event_time']     !== '' ? $post['event_time']     : null;
        $eventEndTime = $post['event_end_time'] !== '' ? $post['event_end_time'] : null;

        if ($eventDate === null) {
            $errors[] = 'Event date is required.';
        } elseif (strtotime($eventDate) === false) {
            $errors[] = 'Event date could not be parsed.';
            $eventDate = null;
        }
        if ($eventTime !== null && !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $eventTime)) {
            $errors[] = 'Start time must be in HH:MM format.';
            $eventTime = null;
        }
        if ($eventEndTime !== null && !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $eventEndTime)) {
            $errors[] = 'End time must be in HH:MM format.';
            $eventEndTime = null;
        }
        if ($eventEndTime !== null && $eventTime === null) {
            $errors[] = 'End time requires a start time. Clear End or set Start.';
            $eventEndTime = null;
        }
        if ($eventTime !== null && $eventEndTime !== null
            && strtotime($eventTime) !== false && strtotime($eventEndTime) !== false
            && strtotime($eventEndTime) <= strtotime($eventTime)) {
            $errors[] = 'End time must be after the start time.';
        }

        $attendance = $post['attendance'];
        if ($attendance !== '' && !in_array($attendance, ['in-person', 'remote'], true)) {
            $errors[] = 'Attendance must be in-person, remote, or blank.';
        }

        $bodyClean = sanitize_html($post['body_raw']);

        // Hero size: validate against the allowlist, default if invalid.
        $heroSize = in_array($post['hero_size'], ['default', 'wide', 'full'], true)
            ? $post['hero_size']
            : 'default';

        // Hero image: existing path is the baseline; remove flag clears it,
        // an uploaded file replaces it. accept_upload stores under
        // /content/live-session/<slug>/ to mirror the article pattern.
        $heroPath = (string)($session['hero_image'] ?? '');
        if ($post['remove_hero']) {
            $heroPath = '';
        }
        if (isset($_FILES['hero']) && is_array($_FILES['hero']) && (int)$_FILES['hero']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = accept_upload($_FILES['hero'], 'content/live-session/' . $slug);
            if (!$up['ok']) {
                $errors[] = 'Hero image: ' . $up['error'];
            } else {
                $heroPath = $up['url'];
            }
        }

        if (count($errors) === 0) {
            $saveData = [
                'id'             => $id,
                'template'       => 'live-session',
                'slug'           => $slug,
                'title'          => $post['title'],
                'summary'        => $post['summary']     !== '' ? $post['summary']     : null,
                'event_date'     => $eventDate,
                'event_time'     => $eventTime,
                'event_end_time' => $eventEndTime,
                'location'       => $post['location']    !== '' ? $post['location']    : null,
                'venue'          => $post['venue']       !== '' ? $post['venue']       : null,
                'cost_pill'      => $post['cost_pill']   !== '' ? $post['cost_pill']   : null,
                'attendance'     => $attendance          !== '' ? $attendance          : null,
                'custom_pill'    => $post['custom_pill'] !== '' ? $post['custom_pill'] : null,
                'body'           => $bodyClean,
                'tags'           => $post['tags']        !== '' ? $post['tags']        : null,
                'hero_image'     => $heroPath !== '' ? $heroPath : null,
                'hero_caption'   => $post['hero_caption'] !== '' ? $post['hero_caption'] : null,
                'hero_size'      => $heroSize,
            ];

            // Phase 14.6 — published_at editable on live rows.
            $rowIsCurrentlyLive = ((string)($session['status'] ?? '') === 'published')
                && ((string)($session['published_status'] ?? '') !== 'scheduled');
            if ($rowIsCurrentlyLive && isset($_POST['published_at']) && trim((string)$_POST['published_at']) !== '') {
                $rawPa = trim((string)$_POST['published_at']);
                $tsPa  = strtotime($rawPa);
                if ($tsPa !== false) {
                    $saveData['published_at'] = date('Y-m-d H:i:s', $tsPa);
                }
            }

            // Phase 14.6 (followup 2) — show_updated + updated_display (date-only).
            if ($rowIsCurrentlyLive) {
                $saveData['show_updated'] = isset($_POST['show_updated']) ? 1 : 0;
                $udRaw = trim((string)($_POST['updated_display'] ?? ''));
                if ($udRaw === '' || $udRaw === $updatedAtDateOnly) {
                    $saveData['updated_display'] = null;
                } else {
                    $tsUd = strtotime($udRaw);
                    $saveData['updated_display'] = $tsUd === false
                        ? null
                        : date('Y-m-d 00:00:00', $tsUd);
                }
            }

            $flashMsg = 'Saved.';

            // Phase 14.6 — publish-now branch. Promotes a scheduled row
            // to live immediately (published_status='live', published_at=NOW()).
            if ($action === 'publish-now') {
                $stmt = db()->prepare("UPDATE content SET published_status='live', published_at=NOW() WHERE id = :id AND status='published'");
                $stmt->execute([':id' => $id]);
                header('Location: /cms/live-sessions/edit?id=' . $id . '&flash=' . rawurlencode('Published — live now.'));
                exit;
            }

            // Phase 14.6 — schedule branch (see article-edit.php for canonical
            // explanation). Saves form fields then schedules; cron promotes.
            if ($action === 'schedule') {
                $scheduleAt = trim((string)($_POST['schedule_at'] ?? ''));
                if ($scheduleAt === '') {
                    $errors[] = 'A schedule date/time is required.';
                } else {
                    save_live_session($saveData);
                    assign_primary_category($id, 'live-session', $post['primary_category']);
                    $res = schedule_content($id, $scheduleAt);
                    if (!$res['ok']) {
                        header('Location: /cms/live-sessions/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                        exit;
                    }
                    $stamp = (string)($res['published_at'] ?? $scheduleAt);
                    $msg = 'Scheduled for ' . date('M j, Y · g:i A', strtotime($stamp));
                    header('Location: /cms/live-sessions/edit?id=' . $id . '&flash=' . rawurlencode($msg));
                    exit;
                }
            }

            [$targetStage, $stageMsg] = $actionToStage($action, $currentStage);

            if ($targetStage !== null) {
                save_live_session($saveData);
                assign_primary_category($id, 'live-session', $post['primary_category']);
                $res = transition_stage($id, $targetStage);
                if (!$res['ok']) {
                    header('Location: /cms/live-sessions/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                    exit;
                }
                if ($targetStage === 'published') {
                    $stageMsg = 'Published — live at /live-sessions/' . $slug;
                }
                header('Location: /cms/live-sessions/edit?id=' . $id
                    . '&flash=' . rawurlencode($stageMsg)
                    . $undoSuffix($action, $currentStage));
                exit;
            }

            save_live_session($saveData);
            assign_primary_category($id, 'live-session', $post['primary_category']);
            header('Location: /cms/live-sessions/edit?id=' . $id . '&flash=' . rawurlencode($flashMsg));
            exit;
        }

        $session = array_merge($session, [
            'slug'           => $slug !== '' ? $slug : $post['slug'],
            'title'          => $post['title'],
            'summary'        => $post['summary'],
            'event_date'     => $eventDate,
            'event_time'     => $eventTime,
            'event_end_time' => $eventEndTime,
            'location'       => $post['location'],
            'venue'          => $post['venue'],
            'cost_pill'      => $post['cost_pill'],
            'attendance'     => $post['attendance'],
            'custom_pill'    => $post['custom_pill'],
            'body'           => $bodyClean,
            'tags'           => $post['tags'],
        ]);
    }
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$status        = (string)($session['status'] ?? 'draft');
$slugPublished = $status === 'published';

// Phase 14.6 — publish-state derivation (parallels article-edit.php).
$publishedStatus    = (string)($session['published_status'] ?? '');
$isScheduled        = ($status === 'published' && $publishedStatus === 'scheduled');
$isLive             = ($status === 'published' && $publishedStatus !== 'scheduled');
$showPublishSection = ($status === 'draft' || $isScheduled);
$publishedAtRaw     = (string)($session['published_at'] ?? '');
$scheduleAtForInput = $isScheduled && $publishedAtRaw !== ''
    ? str_replace(' ', 'T', substr($publishedAtRaw, 0, 16))
    : '';
$minScheduleAt      = date('Y-m-d\TH:i', time() + 60);

// Phase 14.6 (followup) — Publish info box for live rows.
$publishedAtForInput = $publishedAtRaw !== ''
    ? str_replace(' ', 'T', substr($publishedAtRaw, 0, 16))
    : '';
$updatedAtRaw       = (string)($session['updated_at'] ?? '');
$updatedAtFormatted = $updatedAtRaw !== ''
    ? date('M j, Y · g:i A', strtotime($updatedAtRaw))
    : '—';
$updatedAtDateOnly  = $updatedAtRaw !== '' ? substr($updatedAtRaw, 0, 10) : '';
$showUpdated        = !empty($session['show_updated']);
$updatedDisplayRaw  = (string)($session['updated_display'] ?? '');
$updatedDisplayDateOnly = $updatedDisplayRaw !== '' ? substr($updatedDisplayRaw, 0, 10) : '';
$updatedHasOverride = $updatedDisplayDateOnly !== '' && $updatedDisplayDateOnly !== $updatedAtDateOnly;
$updatedInputValue  = $updatedHasOverride ? $updatedDisplayDateOnly : $updatedAtDateOnly;

$bodyInitial   = (string)($session['body'] ?? '');

$myStatusIdx = array_search($status, $sessionStages, true);
if ($myStatusIdx === false) $myStatusIdx = -1;
$prevStage = $myStatusIdx > 0 ? $sessionStages[$myStatusIdx - 1] : null;
$nextStage = $myStatusIdx >= 0 && $myStatusIdx < count($sessionStages) - 1
    ? $sessionStages[$myStatusIdx + 1] : null;

$saveLabel = $status === 'published' ? 'Save changes' : 'Save ' . ucfirst($status);

$fromStage = (string)($_GET['from_stage'] ?? '');
$canUndo   = $fromStage !== '' && $myStatusIdx > 0;

// Phase 20.2: Preview sub-tab. Live sessions render at draft + published.
$showPreviewTab = ($status === 'draft' || $status === 'published');
$activeTab      = (string)($_GET['tab'] ?? 'edit');
if (!in_array($activeTab, ['edit', 'preview'], true)) $activeTab = 'edit';
if ($activeTab === 'preview' && !$showPreviewTab) $activeTab = 'edit';

// Event timing — three independent fields now (date required, times optional).
$eventDateVal    = (string)($session['event_date']     ?? '');
$eventTimeRaw    = (string)($session['event_time']     ?? '');
$eventEndTimeRaw = (string)($session['event_end_time'] ?? '');
// Trim any trailing :SS that MySQL hands back so the form input accepts it.
$eventTimeVal    = $eventTimeRaw    !== '' ? substr($eventTimeRaw,    0, 5) : '';
$eventEndTimeVal = $eventEndTimeRaw !== '' ? substr($eventEndTimeRaw, 0, 5) : '';

// PAST means the event has already happened. Compare end-or-start time on
// the event_date against now.
$isPast = false;
if ($eventDateVal !== '') {
    $effectiveTime = $eventEndTimeVal !== '' ? $eventEndTimeVal : ($eventTimeVal !== '' ? $eventTimeVal : '23:59');
    $eventTs = strtotime($eventDateVal . ' ' . $effectiveTime);
    if ($eventTs !== false && $eventTs < time()) $isPast = true;
}

// Idea Notes carry-forward (parity with journals): visible read-only at
// Draft, archived (hidden) once Published.
$showIdeaNotesReadOnly = $status === 'draft';
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Edit live session: <?= $e((string)($session['title'] ?? 'Untitled')) ?> — alexmchong.ca CMS</title>
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
<link rel="stylesheet" href="/cms/_assets/tiptap.css">
<link rel="stylesheet" href="/_templates/style-articles.css">
</head>
<body>

<?php
// Phase 21.x: resolve provenance BEFORE topbar renders so the breadcrumb
// reflects where the user came from.
$validFromKeys = ['ideation', 'draft-writing', 'articles', 'journals', 'live-sessions', 'experiments'];
$fromKey = (string)($_GET['from'] ?? '');
if (!in_array($fromKey, $validFromKeys, true)) {
    if ($status === 'idea') {
        $fromKey = 'ideation';
    } elseif ($status === 'published') {
        $fromKey = 'live-sessions';
    } else {
        $fromKey = 'draft-writing';
    }
}
$navLabelMap = [
    'ideation'      => ['Ideation',      '/cms/ideation'],
    'draft-writing' => ['Draft Writing', '/cms/'],
    'articles'      => ['Articles',      '/cms/articles'],
    'journals'      => ['Journals',      '/cms/journals'],
    'live-sessions' => ['Live Sessions', '/cms/live-sessions'],
    'experiments'   => ['Experiments',   '/cms/experiments'],
];
[$_navLabel, $_navHref] = $navLabelMap[$fromKey] ?? ['Live Sessions', '/cms/live-sessions'];
$breadcrumb      = $_navLabel . ' → Edit';
$breadcrumb_href = $_navHref;
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = $fromKey;
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-live-session-edit">
      <?php
      $titleHdr = (string)($session['title'] ?? 'Untitled');
      if ($titleHdr === '') $titleHdr = 'Untitled';
      $title    = $titleHdr;
      $pastTag  = $isPast && $status === 'published' ? ' · PAST' : '';
      $stageLabel = $isScheduled ? 'Scheduled for Publish' : ucfirst($status);
      $subtitle = 'Live Session · ' . $stageLabel . $pastTag . ' · saved ' . (string)($session['updated_at'] ?? '');

      // Flash + optional Undo render via the canonical flash-success banner
      // above the content area (proposal #4 + #29).
      $flash_extra = '';
      if ($flash !== '' && $canUndo) {
          $flash_extra = ' <form method="post" action="/cms/live-sessions/edit?id=' . (int)$id . '" class="flash-undo">'
                       . '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                       . '<button type="submit" name="action" value="undo" formnovalidate class="btn-link"'
                       . ' title="Reverts the last advance. Unsaved changes at this stage are lost.">↶ Undo</button>'
                       . '</form>';
      }

      $backMap = [
          'ideation'      => ['/cms/ideation',      'Back to Ideation'],
          'draft-writing' => ['/cms/',              'Back to Draft Writing'],
          'articles'      => ['/cms/articles',      'Back to Articles'],
          'journals'      => ['/cms/journals',      'Back to Journals'],
          'live-sessions' => ['/cms/live-sessions', 'Back to Live Sessions'],
          'experiments'   => ['/cms/experiments',   'Back to Experiments'],
      ];
      [$backHref, $backLabel] = $backMap[$fromKey] ?? ['/cms/live-sessions', 'Back to list'];
      $actions  = '<a href="' . $e($backHref) . '" class="btn-sec">' . $e($backLabel) . '</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="stage-bar">
        <?php foreach ($sessionStages as $i => $s):
          $cls = '';
          if ($i < $myStatusIdx)        $cls = ' done';
          elseif ($i === $myStatusIdx)  $cls = ' current';
          $stepLabel = ($s === 'published' && $isScheduled) ? 'Scheduled' : ucfirst($s);
        ?>
          <div class="stage-bar-step<?= $cls ?>"><?= $e($stepLabel) ?></div>
        <?php endforeach; ?>
      </div>

      <?php if ($showPreviewTab): ?>
        <div class="post-edit-tabs" role="tablist" aria-label="Live session edit and preview">
          <a class="post-edit-tab<?= $activeTab === 'edit' ? ' active' : '' ?>"
             role="tab" data-tab-target="edit"
             aria-selected="<?= $activeTab === 'edit' ? 'true' : 'false' ?>"
             href="/cms/live-sessions/edit?id=<?= (int)$id ?>&tab=edit">Edit</a>
          <a class="post-edit-tab<?= $activeTab === 'preview' ? ' active' : '' ?>"
             role="tab" data-tab-target="preview"
             aria-selected="<?= $activeTab === 'preview' ? 'true' : 'false' ?>"
             href="/cms/live-sessions/edit?id=<?= (int)$id ?>&tab=preview">Preview</a>
        </div>

        <div class="post-preview-frame<?= $activeTab === 'preview' ? '' : ' is-hidden-tab' ?>" data-tab-panel="preview">
          <iframe
            name="post-preview-frame-<?= (int)$id ?>"
            src="/cms/post/preview?id=<?= (int)$id ?>"
            title="Preview · Live session"
            class="post-preview-iframe"
            loading="lazy"
            data-preview-iframe
            data-preview-endpoint="/cms/post/preview-form?id=<?= (int)$id ?>"></iframe>
        </div>
      <?php endif; ?>

      <div class="content-area<?= ($showPreviewTab && $activeTab === 'preview') ? ' is-hidden-tab' : '' ?>" data-tab-panel="edit">
        <?php
        require __DIR__ . '/../partials/flash.php';
        $heading = "Couldn’t save:";
        require __DIR__ . '/../partials/form-errors.php';
        ?>

        <form method="post"
              action="/cms/live-sessions/edit?id=<?= (int)$id ?>"
              enctype="multipart/form-data"
              class="cms-form cms-form-wide"
              data-preview-source-form>
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <?php if ($isScheduled): ?>
            <?php
            $published_at_raw = $publishedAtRaw;
            require __DIR__ . '/../partials/schedule-banner.php';
            ?>
          <?php elseif ($isLive): ?>
            <?php
            $sessionSlug      = (string)($session['slug'] ?? '');
            $published_at_raw = $publishedAtRaw;
            $live_url         = $sessionSlug !== '' ? ('/live-sessions/' . $sessionSlug) : '';
            require __DIR__ . '/../partials/live-banner.php';
            ?>
          <?php endif; ?>

          <div class="form-grid">
            <div class="form-main">
              <div class="field-group">
                <label class="field-label" for="ls-slug">Slug <span class="field-req">required</span></label>
                <input
                  type="text"
                  class="field-input"
                  id="ls-slug"
                  name="slug"
                  value="<?= $e((string)($session['slug'] ?? '')) ?>"
                  maxlength="200"
                  pattern="[a-z0-9\-]+"
                  required>
                <p class="field-hint">
                  <?php if ($slugPublished): ?>
                    <strong>Warning:</strong> changing the slug on a published live session will create a 301 redirect.
                  <?php else: ?>
                    Lowercase letters, numbers, hyphens. Becomes part of <code>/live-sessions/&lt;slug&gt;</code>.
                  <?php endif; ?>
                </p>
              </div>

              <?php if ($showIdeaNotesReadOnly): ?>
                <div class="field-group">
                  <label class="field-label">Idea Notes</label>
                  <div class="readonly-block"><?= nl2br($e((string)($session['notes'] ?? '')), false) ?></div>
                  <p class="field-hint">Private scratchpad from the Idea stage. Archived once published.</p>
                </div>
              <?php endif; ?>

              <div class="field-group">
                <label class="field-label" for="ls-title">Event Title <span class="field-req">required</span></label>
                <input
                  type="text"
                  class="field-input large"
                  id="ls-title"
                  name="title"
                  value="<?= $e((string)($session['title'] ?? '')) ?>"
                  maxlength="500"
                  required>
              </div>

              <div class="field-group">
                <label class="field-label" for="ls-summary">Summary <span class="field-hint-inline">optional</span></label>
                <textarea
                  id="ls-summary"
                  class="field-input"
                  name="summary"
                  rows="3"
                  maxlength="500"
                  placeholder="One-line deck below the title."><?= $e((string)($session['summary'] ?? '')) ?></textarea>
              </div>

              <div class="field-group">
                <label class="field-label">Event Details <span class="field-hint-inline">date required · times optional · Toronto (ET) timezone</span></label>
                <div class="event-grid">
                  <div>
                    <label class="field-sublabel" for="ls-event-date">Date <span class="field-req">required</span></label>
                    <input
                      type="date"
                      class="field-input"
                      id="ls-event-date"
                      name="event_date"
                      value="<?= $e($eventDateVal) ?>"
                      required>
                  </div>
                  <div>
                    <label class="field-sublabel" for="ls-event-time">Start</label>
                    <input
                      type="time"
                      class="field-input"
                      id="ls-event-time"
                      name="event_time"
                      value="<?= $e($eventTimeVal) ?>">
                  </div>
                  <div>
                    <label class="field-sublabel" for="ls-event-end">End</label>
                    <input
                      type="time"
                      class="field-input"
                      id="ls-event-end"
                      name="event_end_time"
                      value="<?= $e($eventEndTimeVal) ?>">
                  </div>
                </div>
                <div class="event-grid" style="margin-top:var(--space-12)">
                  <div style="grid-column:1/-1">
                    <label class="field-sublabel" for="ls-location">Location <span class="field-hint-inline">city / region</span></label>
                    <input
                      type="text"
                      class="field-input"
                      id="ls-location"
                      name="location"
                      value="<?= $e((string)($session['location'] ?? '')) ?>"
                      maxlength="255"
                      placeholder="e.g. Toronto, ON">
                  </div>
                  <div style="grid-column:1/-1">
                    <label class="field-sublabel" for="ls-venue">Venue <span class="field-hint-inline">subline · optional</span></label>
                    <input
                      type="text"
                      class="field-input"
                      id="ls-venue"
                      name="venue"
                      value="<?= $e((string)($session['venue'] ?? '')) ?>"
                      maxlength="255"
                      placeholder="e.g. Centre for Social Innovation · 16 seats">
                  </div>
                </div>
                <p class="field-hint">Publish Date is separate — it's stamped when the session is published. Past events stay live with a PAST badge.</p>
              </div>

              <div class="field-group">
                <label class="field-label">Format Pills <span class="field-hint-inline">all optional · leave blank to hide each pill</span></label>
                <div class="pills-grid">
                  <div>
                    <label class="field-sublabel" for="ls-cost">Cost</label>
                    <input
                      type="text"
                      class="field-input"
                      id="ls-cost"
                      name="cost_pill"
                      value="<?= $e((string)($session['cost_pill'] ?? '')) ?>"
                      maxlength="50"
                      placeholder="Free · Fee · $300 · …">
                  </div>
                  <div>
                    <label class="field-sublabel" for="ls-attendance">Attendance</label>
                    <select id="ls-attendance" class="field-input" name="attendance">
                      <?php
                      $att = (string)($session['attendance'] ?? '');
                      ?>
                      <option value=""        <?= $att === ''          ? 'selected' : '' ?>>— No pill —</option>
                      <option value="in-person" <?= $att === 'in-person' ? 'selected' : '' ?>>In-Person</option>
                      <option value="remote"    <?= $att === 'remote'    ? 'selected' : '' ?>>Remote</option>
                    </select>
                  </div>
                  <div>
                    <label class="field-sublabel" for="ls-custom">Custom</label>
                    <input
                      type="text"
                      class="field-input"
                      id="ls-custom"
                      name="custom_pill"
                      value="<?= $e((string)($session['custom_pill'] ?? '')) ?>"
                      maxlength="50"
                      placeholder="Any short tag">
                  </div>
                </div>
              </div>

              <div class="field-group">
                <label class="field-label">Body <span class="field-hint-inline">optional</span></label>
                <div class="tiptap-wrap body-box">
                  <div class="tiptap-toolbar" id="tiptap-toolbar">
                    <button type="button" data-cmd="bold"        class="tt-btn"><strong>B</strong></button>
                    <button type="button" data-cmd="italic"      class="tt-btn"><em>I</em></button>
                    <button type="button" data-cmd="h2"          class="tt-btn">H2</button>
                    <button type="button" data-cmd="h3"          class="tt-btn">H3</button>
                    <button type="button" data-cmd="ul"          class="tt-btn">• List</button>
                    <button type="button" data-cmd="ol"          class="tt-btn">1. List</button>
                    <button type="button" data-cmd="link"        class="tt-btn">Link</button>
                    <button type="button" data-cmd="blockquote"  class="tt-btn">“ Quote</button>
                    <button type="button" data-cmd="code"        class="tt-btn">Code</button>
                    <button type="button" data-cmd="muted"       class="tt-btn">m</button>
                  </div>
                  <div id="tiptap-editor" class="tiptap-editor"></div>
                  <textarea
                    id="ls-body"
                    name="body"
                    rows="14"
                    class="tiptap-fallback"
                    aria-label="Live session body (HTML)"><?= $e($bodyInitial) ?></textarea>
                </div>
              </div>
            </div>

            <aside class="form-side">
              <?php
                $hero            = (string)($session['hero_image'] ?? '');
                $heroSizeCurrent = (string)($session['hero_size'] ?? 'default');
                if (!in_array($heroSizeCurrent, ['default','wide','full'], true)) $heroSizeCurrent = 'default';
                $heroSizeLabels  = ['default' => 'Column', 'wide' => 'Wide', 'full' => 'Full'];
              ?>
              <div class="cms-hero-box">
                <div class="cms-hero-header">
                  <label class="field-label">Hero image</label>
                  <span class="field-hint-inline">optional</span>
                </div>

                <div class="cms-hero-preview<?= $hero !== '' ? ' is-loaded' : ' is-empty' ?>" data-hero-size="<?= $e($heroSizeCurrent) ?>">
                  <?php if ($hero !== ''): ?>
                    <img src="<?= $e($hero) ?>" alt="" loading="lazy">
                  <?php else: ?>
                    <div class="cms-hero-empty">No image yet</div>
                  <?php endif; ?>
                  <button type="button"
                          class="cms-hero-trash"
                          aria-label="Remove hero image"
                          title="Remove hero image">
                    <svg viewBox="0 0 16 16" aria-hidden="true" width="14" height="14"><path fill="currentColor" d="M6 2h4l1 1h3v2H2V3h3l1-1zm-3 4h10l-1 9H4L3 6zm3 2v6h1V8H6zm3 0v6h1V8H9z"/></svg>
                  </button>
                </div>

                <input type="hidden" name="remove_hero" value="0" class="cms-hero-remove-flag">

                <input type="file"
                       class="cms-hero-file sr-only"
                       id="ls-hero-file"
                       name="hero"
                       accept="image/jpeg,image/png,image/webp,image/gif">
                <div class="cms-hero-pick-row">
                  <label for="ls-hero-file" class="btn-sec cms-hero-pick-btn">
                    <?= $hero !== '' ? 'Replace image' : 'Choose image' ?>
                  </label>
                  <span class="cms-hero-pick-name" aria-live="polite"></span>
                </div>

                <div class="cms-hero-controls">
                  <span class="cms-hero-controls-label">Size</span>
                  <div class="cms-hero-size-group" role="group" aria-label="Hero size">
                    <?php foreach (['default','wide','full'] as $sz): ?>
                      <button type="button"
                              class="cms-hero-size-btn<?= $heroSizeCurrent === $sz ? ' is-active' : '' ?>"
                              data-hero-size-btn="<?= $sz ?>"
                              aria-pressed="<?= $heroSizeCurrent === $sz ? 'true' : 'false' ?>"><?= $e($heroSizeLabels[$sz]) ?></button>
                    <?php endforeach; ?>
                  </div>
                  <input type="hidden" name="hero_size" id="ls-hero-size" value="<?= $e($heroSizeCurrent) ?>">
                </div>

                <input
                  type="text"
                  class="field-input cms-hero-caption"
                  id="ls-hero-caption"
                  name="hero_caption"
                  placeholder="Caption (optional)"
                  value="<?= $e((string)($session['hero_caption'] ?? '')) ?>"
                  maxlength="500">

                <p class="field-hint cms-hero-hint">JPEG, PNG, WebP, or GIF · max 5 MB</p>
              </div>

              <div class="field-group">
                <label class="field-label" for="ls-primary-category">Primary category</label>
                <select class="field-select" id="ls-primary-category" name="primary_category">
                  <option value="">— None</option>
                  <?php foreach ($allLiveSessionCategories as $cat): ?>
                    <option value="<?= $e((string)$cat['value_slug']) ?>" <?= $currentPrimaryCategory === (string)$cat['value_slug'] ? 'selected' : '' ?>><?= $e((string)$cat['label']) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="field-hint">Drives card colour on /live-sessions/.</p>
              </div>

              <div class="field-group">
                <label class="field-label" for="ls-tags">Tags <span class="field-hint-inline">optional</span></label>
                <input
                  type="text"
                  class="field-input"
                  id="ls-tags"
                  name="tags"
                  value="<?= $e((string)($session['tags'] ?? '')) ?>"
                  maxlength="500"
                  placeholder="workshop, talk, …">
                <p class="field-hint">Display only — not used for filtering.</p>
              </div>

              <?php
              $is_live              = $isLive;
              $show_publish_section = $showPublishSection;
              $is_scheduled         = $isScheduled;
              $live_url             = '/live-sessions/' . (string)($session['slug'] ?? '');
              $published_at_id      = 'ls-published-at';
              $published_at_value   = $publishedAtForInput;
              $updated_label        = 'live session';
              $show_updated         = $showUpdated;
              $updated_input_value  = $updatedInputValue;
              $updated_default      = $updatedAtDateOnly;
              $updated_has_override = $updatedHasOverride;
              $schedule_at_value    = $scheduleAtForInput;
              $min_schedule_at      = $minScheduleAt;
              require __DIR__ . '/../partials/publish-box.php';
              ?>
            </aside>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" name="action" value="save" class="btn-sec" data-save-btn><?= $e($saveLabel) ?></button>
            <a href="<?= $e($backHref) ?>" class="btn-sec">Cancel</a>

            <button type="submit" form="live-session-delete-form" class="btn-sec btn-danger">Delete</button>

            <?php if ($status === 'draft'): ?>
              <button type="submit" name="action" value="publish" class="btn-pri" data-publish-btn>Publish →</button>
              <button type="submit" name="action" value="schedule" class="btn-pri" data-schedule-btn hidden>Schedule →</button>
              <button type="button" class="btn-sec" data-set-schedule>Schedule Publish</button>
            <?php endif; ?>

            <?php if ($isScheduled): ?>
              <button type="submit" name="action" value="publish-now" class="btn-pri"
                      data-confirm="Publish this now? It will go live immediately at the current time.">Publish Now</button>
              <button
                type="submit"
                name="action"
                value="unpublish"
                class="btn-sec"
                data-confirm-unpublish="1">Move back to Draft</button>
            <?php endif; ?>

            <?php if ($isLive): ?>
              <button
                type="submit"
                name="action"
                value="unpublish"
                class="btn-sec"
                data-confirm-unpublish="1">Move to draft</button>
            <?php endif; ?>
          </div>
        </form>

        <form id="live-session-delete-form"
              method="post"
              action="/cms/live-sessions/delete?id=<?= (int)$id ?>"
              class="inline-delete"
              data-stage="<?= $e($status) ?>"
              data-slug="<?= $e((string)($session['slug'] ?? '')) ?>"
              hidden>
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <?php if ($slugPublished): ?>
            <input type="hidden" name="typed_slug" value="">
          <?php endif; ?>
        </form>
      </div>
    </div>
  </main>
</div>

<script type="module">
  import { setupTiptap } from '/cms/_assets/tiptap-setup.js';
  setupTiptap({
    mount:        document.getElementById('tiptap-editor'),
    fallback:     document.getElementById('ls-body'),
    toolbar:      document.getElementById('tiptap-toolbar'),
    uploadUrl:    '/cms/articles/upload-image?id=<?= (int)$id ?>',
    csrfToken:    <?= json_encode($csrf_token, JSON_UNESCAPED_SLASHES) ?>,
  });
</script>

<script src="/cms/_assets/scroll-actions.js" defer></script>

<script>
  for (const btn of document.querySelectorAll('[data-confirm-unpublish]')) {
    btn.addEventListener('click', (e) => {
      const ok = window.confirm("Move this live session back to draft? It will be removed from the public site immediately.");
      if (!ok) e.preventDefault();
    });
  }
  for (const form of document.querySelectorAll('form.inline-delete')) {
    form.addEventListener('submit', (e) => {
      const stage = form.getAttribute('data-stage') || '';
      const slug  = form.getAttribute('data-slug')  || '';
      if (stage === 'published') {
        const typed = window.prompt(
          'Deleting a published live session is permanent.\n\nType the slug exactly to confirm:\n\n  ' + slug
        );
        if (typed === null) { e.preventDefault(); return; }
        if (typed.trim() !== slug) {
          e.preventDefault();
          window.alert('Slug did not match — nothing deleted.');
          return;
        }
        const inp = form.querySelector('input[name="typed_slug"]');
        if (inp) inp.value = typed.trim();
      } else {
        if (!window.confirm('Delete this live session? This cannot be undone.')) {
          e.preventDefault();
        }
      }
    });
  }
</script>

<script>
  // Hero box: pill toggle writes selected size into the hidden input the
  // form posts, and live-previews a freshly-picked file before upload so
  // the author sees something in the preview pane immediately. Trash
  // overlay flips the remove flag + clears the preview. Mirrors the
  // controller in article-edit.php (same DOM contract, generic selector).
  (function () {
    const box = document.querySelector('.cms-hero-box');
    if (!box) return;

    const hidden      = box.querySelector('[name="hero_size"]');
    const buttons     = box.querySelectorAll('[data-hero-size-btn]');
    const preview     = box.querySelector('.cms-hero-preview');
    const fileInput   = box.querySelector('.cms-hero-file');
    const nameEl      = box.querySelector('.cms-hero-pick-name');
    const pickBtn     = box.querySelector('.cms-hero-pick-btn');
    const removeFlag  = box.querySelector('.cms-hero-remove-flag');
    const trash       = box.querySelector('.cms-hero-trash');

    buttons.forEach(btn => {
      btn.addEventListener('click', () => {
        const size = btn.getAttribute('data-hero-size-btn');
        if (!size) return;
        if (hidden) hidden.value = size;
        if (preview) preview.setAttribute('data-hero-size', size);
        buttons.forEach(b => {
          const match = b === btn;
          b.classList.toggle('is-active', match);
          b.setAttribute('aria-pressed', match ? 'true' : 'false');
        });
        if (hidden) hidden.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    if (fileInput && preview) {
      fileInput.addEventListener('change', () => {
        const f = fileInput.files && fileInput.files[0];
        if (!f) return;
        const url = URL.createObjectURL(f);
        const empty = preview.querySelector('.cms-hero-empty');
        if (empty) empty.remove();
        let img = preview.querySelector('img');
        if (!img) {
          img = document.createElement('img');
          img.alt = '';
          preview.appendChild(img);
        }
        img.src = url;
        preview.classList.remove('is-empty');
        preview.classList.add('is-loaded');
        if (removeFlag) removeFlag.value = '0';
        if (nameEl)  nameEl.textContent = f.name;
        if (pickBtn) pickBtn.textContent = 'Replace image';
      });
    }

    if (trash && preview) {
      trash.addEventListener('click', () => {
        if (!window.confirm('Remove this hero image? You\'ll need to Save to confirm.')) return;
        if (removeFlag) {
          removeFlag.value = '1';
          removeFlag.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const img = preview.querySelector('img');
        if (img) img.remove();
        preview.classList.remove('is-loaded');
        preview.classList.add('is-empty');
        if (!preview.querySelector('.cms-hero-empty')) {
          const empty = document.createElement('div');
          empty.className = 'cms-hero-empty';
          empty.textContent = 'No image yet';
          preview.appendChild(empty);
        }
        if (fileInput) fileInput.value = '';
        if (pickBtn)   pickBtn.textContent = 'Choose image';
        if (nameEl)    nameEl.textContent  = '';
      });
    }
  })();
</script>

<script src="/cms/_assets/publish-choreography.js" defer></script>
<script src="/cms/_assets/preview-tab-guard.js" defer></script>
<script src="/cms/_assets/dirty-flip.js" defer></script>
</body>
</html>
