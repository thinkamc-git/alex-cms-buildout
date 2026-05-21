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
            'cost_pill'      => trim((string)($_POST['cost_pill']      ?? '')),
            'attendance'     => trim((string)($_POST['attendance']     ?? '')),
            'custom_pill'    => trim((string)($_POST['custom_pill']    ?? '')),
            'body_raw'       =>      (string)($_POST['body']           ?? ''),
            'tags'           => trim((string)($_POST['tags']           ?? '')),
        ];

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
                'cost_pill'      => $post['cost_pill']   !== '' ? $post['cost_pill']   : null,
                'attendance'     => $attendance          !== '' ? $attendance          : null,
                'custom_pill'    => $post['custom_pill'] !== '' ? $post['custom_pill'] : null,
                'body'           => $bodyClean,
                'tags'           => $post['tags']        !== '' ? $post['tags']        : null,
            ];

            $flashMsg = 'Saved.';
            [$targetStage, $stageMsg] = $actionToStage($action, $currentStage);

            if ($targetStage !== null) {
                save_live_session($saveData);
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
$bodyInitial   = (string)($session['body'] ?? '');

$myStatusIdx = array_search($status, $sessionStages, true);
if ($myStatusIdx === false) $myStatusIdx = -1;
$prevStage = $myStatusIdx > 0 ? $sessionStages[$myStatusIdx - 1] : null;
$nextStage = $myStatusIdx >= 0 && $myStatusIdx < count($sessionStages) - 1
    ? $sessionStages[$myStatusIdx + 1] : null;

$saveLabel = $status === 'published' ? 'Save changes' : 'Save ' . ucfirst($status);

$fromStage = (string)($_GET['from_stage'] ?? '');
$canUndo   = $fromStage !== '' && $myStatusIdx > 0;

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
</head>
<body>

<?php
$breadcrumb = 'Live Sessions → Edit';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'live-sessions';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-live-session-edit">
      <?php
      $titleHdr = (string)($session['title'] ?? 'Untitled');
      if ($titleHdr === '') $titleHdr = 'Untitled';
      $title    = $titleHdr;
      $pastTag  = $isPast && $status === 'published' ? ' · PAST' : '';
      $subtitle = 'Live Session · ' . ucfirst($status) . $pastTag . ' · last saved ' . $e((string)($session['updated_at'] ?? ''));
      $actions  = '<a href="/cms/live-sessions" class="btn-ghost">Back to list</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="stage-bar">
        <?php foreach ($sessionStages as $i => $s):
          $cls = '';
          if ($i < $myStatusIdx)        $cls = ' done';
          elseif ($i === $myStatusIdx)  $cls = ' current';
        ?>
          <div class="stage-bar-step<?= $cls ?>"><?= ucfirst($s) ?></div>
        <?php endforeach; ?>
      </div>

      <div class="content-area">
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status">
            <?= $e($flash) ?>
            <?php if ($canUndo): ?>
              <form method="post" action="/cms/live-sessions/edit?id=<?= (int)$id ?>" class="flash-undo">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                <button type="submit" name="action" value="undo" class="btn-link" formnovalidate
                  title="Reverts the last advance. Unsaved changes at this stage are lost.">
                  ↶ Undo
                </button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (count($errors) > 0): ?>
          <div class="form-errors" role="alert">
            <strong>Couldn’t save:</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?= $e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post"
              action="/cms/live-sessions/edit?id=<?= (int)$id ?>"
              class="cms-form cms-form-wide">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

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
                    <strong>Warning:</strong> Changing the slug on a published live-session will create a 301 redirect (Phase 11).
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
                <label class="field-label" for="ls-title">Title <span class="field-req">required</span></label>
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
                <label class="field-label">Event Details <span class="field-hint-inline">date required · times optional</span></label>
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
                    <label class="field-sublabel" for="ls-location">Location</label>
                    <input
                      type="text"
                      class="field-input"
                      id="ls-location"
                      name="location"
                      value="<?= $e((string)($session['location'] ?? '')) ?>"
                      maxlength="255"
                      placeholder="e.g. Vancouver, BC · Online">
                  </div>
                </div>
                <p class="field-hint">Publish Date is separate — that's stamped when the session goes live. Past events stay live with a PAST badge.</p>
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
                <p class="field-hint">Display only — not used for filtering yet.</p>
              </div>
            </aside>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" name="action" value="save" class="btn-pri"><?= $e($saveLabel) ?></button>
            <a href="/cms/live-sessions" class="btn-ghost">Cancel</a>

            <?php if ($status === 'draft'): ?>
              <button type="submit" name="action" value="publish" class="btn-pri" style="margin-left:auto">Publish →</button>
            <?php endif; ?>

            <?php if ($status === 'published'): ?>
              <button
                type="submit"
                name="action"
                value="unpublish"
                class="btn-ghost"
                style="margin-left:auto"
                data-confirm-unpublish="1">Move to draft</button>
            <?php endif; ?>
          </div>
        </form>

        <form method="post"
              action="/cms/live-sessions/delete?id=<?= (int)$id ?>"
              class="inline-delete danger-zone"
              data-stage="<?= $e($status) ?>"
              data-slug="<?= $e((string)($session['slug'] ?? '')) ?>">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <?php if ($slugPublished): ?>
            <input type="hidden" name="typed_slug" value="">
          <?php endif; ?>
          <button type="submit" class="btn-ghost btn-danger">Delete live session</button>
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

<script>
  for (const btn of document.querySelectorAll('[data-confirm-unpublish]')) {
    btn.addEventListener('click', (e) => {
      const ok = window.confirm("Move this session back to draft? It will be removed from the public site immediately.");
      if (!ok) e.preventDefault();
    });
  }
  for (const form of document.querySelectorAll('form.inline-delete')) {
    form.addEventListener('submit', (e) => {
      const stage = form.getAttribute('data-stage') || '';
      const slug  = form.getAttribute('data-slug')  || '';
      if (stage === 'published') {
        const typed = window.prompt(
          'Deleting a published live session is permanent.\n\nType the slug to confirm:\n\n  ' + slug
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

</body>
</html>
