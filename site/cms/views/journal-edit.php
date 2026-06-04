<?php
/**
 * cms/views/journal-edit.php — edit a Journal at Draft/Published.
 *
 * Idea-stage journals live in the shared editor (article-edit.php); they
 * advance straight to Draft (journals skip Concept/Outline per spec §15).
 *
 * Routed from site/index.php:
 *   GET  /cms/journals/edit?id=N
 *   POST /cms/journals/edit?id=N
 *
 * Journal form shape:
 *   - Slug (required)
 *   - Idea Notes (read-only, carried from Idea — only at Draft, archived after)
 *   - Key Statement (the public "headline", 280 chars; replaces a working title)
 *   - Body (Tiptap, optional — Key Statement alone is enough)
 *   - Tags (sidebar)
 *   - Entry number (sidebar, after first publish)
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
    header('Location: /cms/journals');
    exit;
}

$journal = get_journal($id);
if ($journal === null) {
    // Idea-stage rows + non-journals bounce to the shared editor.
    $stmt = db()->prepare("SELECT id FROM content WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    if ($stmt->fetch() === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Journal not found.\n";
        exit;
    }
    header('Location: /cms/articles/edit?id=' . $id);
    exit;
}

$status = (string)($journal['status'] ?? 'draft');
if ($status === 'idea') {
    header('Location: /cms/articles/edit?id=' . $id);
    exit;
}

$errors = [];
$flash  = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

$allJournalCategories  = list_categories('journal');
$currentPrimaryCategory = get_primary_category($id);

$undoSuffix = static function (string $action, string $current): string {
    return in_array($action, ['advance', 'publish'], true)
        ? '&from_stage=' . urlencode($current)
        : '';
};

// Journals only see idea → draft → published — see stages_for_type().
$journalStages = stages_for_type('journal');

$actionToStage = static function (string $action, string $current) use ($journalStages) {
    $idx = array_search($current, $journalStages, true);
    switch ($action) {
        case 'advance':
            if ($idx === false || $idx >= count($journalStages) - 1) return [null, ''];
            $next = $journalStages[$idx + 1];
            return [$next, 'Advanced to ' . ucfirst($next) . '.'];
        case 'step-back':
            if ($idx === false || $idx <= 0) return [null, ''];
            $prev = $journalStages[$idx - 1];
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
            $idx = array_search($currentStage, $journalStages, true);
            if ($idx !== false && $idx > 0) {
                $prev = $journalStages[$idx - 1];
                $res  = transition_stage($id, $prev);
                if ($res['ok']) {
                    $dest = $prev === 'idea'
                        ? '/cms/articles/edit?id=' . $id
                        : '/cms/journals/edit?id=' . $id;
                    header('Location: ' . $dest
                        . '&flash=' . rawurlencode('Reverted to ' . ucfirst($prev) . '.'));
                    exit;
                }
            }
            header('Location: /cms/journals/edit?id=' . $id);
            exit;
        }

        $editBody = $currentStage === 'draft' || $currentStage === 'published';

        $post = [
            'slug'          => trim((string)($_POST['slug']          ?? '')),
            'key_statement' => trim((string)($_POST['key_statement'] ?? '')),
            'body_raw'      =>      (string)($_POST['body']          ?? ''),
            'tags'          => trim((string)($_POST['tags']          ?? '')),
            'primary_category' => trim((string)($_POST['primary_category'] ?? '')),
        ];

        $allowedCatSlugs = array_map(static fn($c) => (string)$c['value_slug'], $allJournalCategories);
        if ($post['primary_category'] !== '' && !in_array($post['primary_category'], $allowedCatSlugs, true)) {
            $errors[] = 'Primary category is not a known journal category.';
            $post['primary_category'] = '';
        }

        if ($post['key_statement'] === '') {
            $errors[] = 'Key Statement is required.';
        } elseif (mb_strlen($post['key_statement']) > 280) {
            $errors[] = 'Key Statement is too long — 280 characters max.';
        }

        // Slug: auto-derive from the key statement if blank.
        $slug = $post['slug'] !== ''
            ? slugify($post['slug'])
            : slugify($post['key_statement']);
        if ($slug === '') {
            $errors[] = 'Slug is required.';
        } else {
            $slug = unique_slug($slug, $id);
        }

        $bodyClean = $editBody ? sanitize_html($post['body_raw']) : (string)($journal['body'] ?? '');

        if (count($errors) === 0) {
            $saveData = [
                'id'            => $id,
                'template'      => 'journal-entry',
                'slug'          => $slug,
                'key_statement' => $post['key_statement'],
                // Mirror key_statement into title so list views have a
                // single field to display; the working title from Idea
                // stage gets superseded here.
                'title'         => $post['key_statement'],
                'tags'          => $post['tags'] !== '' ? $post['tags'] : null,
            ];
            if ($editBody) {
                $saveData['body'] = $bodyClean;
            }

            // Phase 14.6 — published_at editable on live rows. Only honor
            // when the row is currently live AND a value was posted.
            $rowIsCurrentlyLive = ((string)($journal['status'] ?? '') === 'published')
                && ((string)($journal['published_status'] ?? '') !== 'scheduled');
            if ($rowIsCurrentlyLive && isset($_POST['published_at']) && trim((string)$_POST['published_at']) !== '') {
                $rawPa = trim((string)$_POST['published_at']);
                $tsPa  = strtotime($rawPa);
                if ($tsPa !== false) {
                    $saveData['published_at'] = date('Y-m-d H:i:s', $tsPa);
                }
            }

            // Phase 14.6 (followup 2) — show_updated + updated_display (date-only).
            // If submitted date matches actual updated_at date, store NULL.
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
                header('Location: /cms/journals/edit?id=' . $id . '&flash=' . rawurlencode('Published — live now.'));
                exit;
            }

            // Phase 14.6 — schedule branch (see article-edit.php for the canonical
            // explanation). Saves form fields then schedules; the cron promotes.
            if ($action === 'schedule') {
                $scheduleAt = trim((string)($_POST['schedule_at'] ?? ''));
                if ($scheduleAt === '') {
                    $errors[] = 'A schedule date/time is required.';
                } else {
                    save_journal($saveData);
                    assign_primary_category($id, 'journal', $post['primary_category']);
                    $res = schedule_content($id, $scheduleAt);
                    if (!$res['ok']) {
                        header('Location: /cms/journals/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                        exit;
                    }
                    $stamp = (string)($res['published_at'] ?? $scheduleAt);
                    $msg = 'Scheduled for ' . date('M j, Y · g:i A', strtotime($stamp));
                    header('Location: /cms/journals/edit?id=' . $id . '&flash=' . rawurlencode($msg));
                    exit;
                }
            }

            [$targetStage, $stageMsg] = $actionToStage($action, $currentStage);

            if ($targetStage !== null) {
                save_journal($saveData);
                assign_primary_category($id, 'journal', $post['primary_category']);
                $res = transition_stage($id, $targetStage);
                if (!$res['ok']) {
                    header('Location: /cms/journals/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                    exit;
                }
                if ($targetStage === 'published') {
                    $stageMsg = 'Published — live at /journal/' . $slug;
                }
                header('Location: /cms/journals/edit?id=' . $id
                    . '&flash=' . rawurlencode($stageMsg)
                    . $undoSuffix($action, $currentStage));
                exit;
            }

            save_journal($saveData);
            assign_primary_category($id, 'journal', $post['primary_category']);
            header('Location: /cms/journals/edit?id=' . $id . '&flash=' . rawurlencode($flashMsg));
            exit;
        }

        $journal = array_merge($journal, [
            'slug'          => $slug !== '' ? $slug : $post['slug'],
            'key_statement' => $post['key_statement'],
            'body'          => $bodyClean,
            'tags'          => $post['tags'],
        ]);
    }
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$status        = (string)($journal['status'] ?? 'draft');
$slugPublished = $status === 'published';

// Phase 14.6 — publish-state derivation (same shape as article-edit.php).
$publishedStatus    = (string)($journal['published_status'] ?? '');
$isScheduled        = ($status === 'published' && $publishedStatus === 'scheduled');
$isLive             = ($status === 'published' && $publishedStatus !== 'scheduled');
$showPublishSection = ($status === 'draft' || $isScheduled);
$publishedAtRaw     = (string)($journal['published_at'] ?? '');
$scheduleAtForInput = $isScheduled && $publishedAtRaw !== ''
    ? str_replace(' ', 'T', substr($publishedAtRaw, 0, 16))
    : '';
$minScheduleAt      = date('Y-m-d\TH:i', time() + 60);

// Phase 14.6 (followup) — Publish info box for live rows.
$publishedAtForInput = $publishedAtRaw !== ''
    ? str_replace(' ', 'T', substr($publishedAtRaw, 0, 16))
    : '';
$updatedAtRaw       = (string)($journal['updated_at'] ?? '');
$updatedAtFormatted = $updatedAtRaw !== ''
    ? date('M j, Y · g:i A', strtotime($updatedAtRaw))
    : '—';
$updatedAtDateOnly  = $updatedAtRaw !== '' ? substr($updatedAtRaw, 0, 10) : '';
$showUpdated        = !empty($journal['show_updated']);
$updatedDisplayRaw  = (string)($journal['updated_display'] ?? '');
$updatedDisplayDateOnly = $updatedDisplayRaw !== '' ? substr($updatedDisplayRaw, 0, 10) : '';
$updatedHasOverride = $updatedDisplayDateOnly !== '' && $updatedDisplayDateOnly !== $updatedAtDateOnly;
$updatedInputValue  = $updatedHasOverride ? $updatedDisplayDateOnly : $updatedAtDateOnly;

$bodyInitial   = (string)($journal['body'] ?? '');

$myStatusIdx = array_search($status, $journalStages, true);
if ($myStatusIdx === false) $myStatusIdx = -1;
$prevStage = $myStatusIdx > 0 ? $journalStages[$myStatusIdx - 1] : null;
$nextStage = $myStatusIdx >= 0 && $myStatusIdx < count($journalStages) - 1
    ? $journalStages[$myStatusIdx + 1] : null;

$saveLabel = $status === 'published' ? 'Save changes' : 'Save ' . ucfirst($status);

$showIdeaNotesReadOnly = $status === 'draft';  // archived after Draft
$showBody              = $status === 'draft' || $status === 'published';

// Phase 20.2: Preview sub-tab (same logic as article-edit).
$showPreviewTab = $showBody;
$activeTab      = (string)($_GET['tab'] ?? 'edit');
if (!in_array($activeTab, ['edit', 'preview'], true)) $activeTab = 'edit';
if ($activeTab === 'preview' && !$showPreviewTab) $activeTab = 'edit';

$fromStage = (string)($_GET['from_stage'] ?? '');
$canUndo   = $fromStage !== '' && $myStatusIdx > 0;

$entryNumPadded = $journal['journal_number'] !== null
    ? str_pad((string)(int)$journal['journal_number'], 3, '0', STR_PAD_LEFT)
    : null;
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Edit journal: <?= $e((string)($journal['key_statement'] ?? $journal['title'] ?? 'Untitled')) ?> — alexmchong.ca CMS</title>
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
<?php if ($showBody): ?>
<link rel="stylesheet" href="/cms/_assets/tiptap.css">
<link rel="stylesheet" href="/_templates/style-articles.css">
<?php endif; ?>
</head>
<body>

<?php
// Phase 21.x: resolve provenance BEFORE topbar renders so the breadcrumb
// reflects where the user came from. Same pattern across the four edit views.
$validFromKeys = ['ideation', 'draft-writing', 'articles', 'journals', 'live-sessions', 'experiments'];
$fromKey = (string)($_GET['from'] ?? '');
if (!in_array($fromKey, $validFromKeys, true)) {
    if ($status === 'idea') {
        $fromKey = 'ideation';
    } elseif ($status === 'published') {
        $fromKey = 'journals';
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
[$_navLabel, $_navHref] = $navLabelMap[$fromKey] ?? ['Journals', '/cms/journals'];
$breadcrumb      = $_navLabel . ' → Edit';
$breadcrumb_href = $_navHref;
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  // $fromKey resolved above (before topbar). Re-use for sidebar highlight.
  $active_nav_id = $fromKey;
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-journal-edit">
      <?php
      $titleHdr = (string)($journal['key_statement'] ?? '');
      if ($titleHdr === '') $titleHdr = (string)($journal['title'] ?? 'Untitled');
      if ($titleHdr === '') $titleHdr = 'Untitled';
      $title    = $titleHdr;
      $entryHdr = $entryNumPadded !== null ? ' · Entry ' . $entryNumPadded : '';
      $stageLabel = $isScheduled ? 'Scheduled for Publish' : ucfirst($status);
      $subtitle = 'Journal · ' . $stageLabel . $entryHdr . ' · saved ' . (string)($journal['updated_at'] ?? '');

      // Flash + optional Undo render via the canonical flash-success banner
      // above the content area (proposal #4 + #29).
      $flash_extra = '';
      if ($flash !== '' && $canUndo) {
          $flash_extra = ' <form method="post" action="/cms/journals/edit?id=' . (int)$id . '" class="flash-undo">'
                       . '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                       . '<button type="submit" name="action" value="undo" formnovalidate class="btn-link"'
                       . ' title="Reverts the last advance. Unsaved changes at this stage are lost.">↶ Undo</button>'
                       . '</form>';
      }

      $backMap = [
          'ideation'      => ['/cms/ideation',      '← Back to Ideation'],
          'draft-writing' => ['/cms/',              '← Back to Draft Writing'],
          'articles'      => ['/cms/articles',      '← Back to Articles'],
          'journals'      => ['/cms/journals',      '← Back to Journals'],
          'live-sessions' => ['/cms/live-sessions', '← Back to Live Sessions'],
          'experiments'   => ['/cms/experiments',   '← Back to Experiments'],
      ];
      [$backHref, $backLabel] = $backMap[$fromKey] ?? ['/cms/journals', '← Back to list'];
      $actions  = '<a href="' . $e($backHref) . '" class="btn-sec">' . $e($backLabel) . '</a>';
      if ((string)($journal['status'] ?? '') === 'published' && !empty($journal['slug'])) {
          $actions .= ' <a href="/journal/' . $e((string)$journal['slug']) . '" target="_blank" rel="noopener" class="btn-sec">Live ↗</a>';
      }
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="stage-bar">
        <?php foreach ($journalStages as $i => $s):
          $cls = '';
          if ($i < $myStatusIdx)        $cls = ' done';
          elseif ($i === $myStatusIdx)  $cls = ' current';
          $stepLabel = ($s === 'published' && $isScheduled) ? 'Scheduled' : ucfirst($s);
        ?>
          <div class="stage-bar-step<?= $cls ?>"><?= $e($stepLabel) ?></div>
        <?php endforeach; ?>
      </div>

      <?php if ($showPreviewTab): ?>
        <div class="post-edit-tabs" role="tablist" aria-label="Journal edit and preview">
          <a class="post-edit-tab<?= $activeTab === 'edit' ? ' active' : '' ?>"
             role="tab" data-tab-target="edit"
             aria-selected="<?= $activeTab === 'edit' ? 'true' : 'false' ?>"
             href="/cms/journals/edit?id=<?= (int)$id ?>&tab=edit">Edit</a>
          <a class="post-edit-tab<?= $activeTab === 'preview' ? ' active' : '' ?>"
             role="tab" data-tab-target="preview"
             aria-selected="<?= $activeTab === 'preview' ? 'true' : 'false' ?>"
             href="/cms/journals/edit?id=<?= (int)$id ?>&tab=preview">Preview</a>
        </div>

        <div class="post-preview-frame<?= $activeTab === 'preview' ? '' : ' is-hidden-tab' ?>" data-tab-panel="preview">
          <iframe
            name="post-preview-frame-<?= (int)$id ?>"
            src="/cms/post/preview?id=<?= (int)$id ?>"
            title="Preview · Journal entry"
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
              action="/cms/journals/edit?id=<?= (int)$id ?>"
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
            $journalSlug      = (string)($journal['slug'] ?? '');
            $published_at_raw = $publishedAtRaw;
            $live_url         = $journalSlug !== '' ? ('/journal/' . $journalSlug) : '';
            require __DIR__ . '/../partials/live-banner.php';
            ?>
          <?php endif; ?>

          <div class="form-grid">
            <div class="form-main">
              <div class="field-group">
                <label class="field-label" for="journal-slug">Slug <span class="field-req">required</span></label>
                <input
                  type="text"
                  class="field-input"
                  id="journal-slug"
                  name="slug"
                  value="<?= $e((string)($journal['slug'] ?? '')) ?>"
                  maxlength="200"
                  pattern="[a-z0-9\-]+"
                  required>
                <p class="field-hint">
                  <?php if ($slugPublished): ?>
                    <strong>Warning:</strong> changing the slug on a published journal will create a 301 redirect.
                  <?php else: ?>
                    Lowercase letters, numbers, hyphens. Becomes part of <code>/journal/&lt;slug&gt;</code>.
                  <?php endif; ?>
                </p>
              </div>

              <?php if ($showIdeaNotesReadOnly): ?>
                <div class="field-group">
                  <label class="field-label">Idea Notes</label>
                  <div class="readonly-block"><?= nl2br($e((string)($journal['notes'] ?? '')), false) ?></div>
                </div>
              <?php endif; ?>

              <div class="field-group">
                <label class="field-label" for="journal-key-statement">Key Statement <span class="field-hint-inline">required · max 280 chars</span></label>
                <textarea
                  id="journal-key-statement"
                  class="field-input large"
                  name="key_statement"
                  rows="3"
                  maxlength="280"
                  required
                  placeholder="One declarative sentence. This is what readers see at the top of the page."><?= $e((string)($journal['key_statement'] ?? '')) ?></textarea>
                <p class="field-hint">Renders in Instrument Serif italic with a left rule in the category colour.</p>
              </div>

              <?php if ($showBody): ?>
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
                      id="journal-body"
                      name="body"
                      rows="14"
                      class="tiptap-fallback"
                      aria-label="Journal body (HTML)"><?= $e($bodyInitial) ?></textarea>
                  </div>
                  <p class="field-hint">The Key Statement alone is enough — the body is optional, for expansion.</p>
                </div>
              <?php endif; ?>
            </div>

            <aside class="form-side">
              <div class="field-group">
                <label class="field-label" for="journal-primary-category">Primary category</label>
                <select class="field-select" id="journal-primary-category" name="primary_category">
                  <option value="">— None</option>
                  <?php foreach ($allJournalCategories as $cat): ?>
                    <option value="<?= $e((string)$cat['value_slug']) ?>" <?= $currentPrimaryCategory === (string)$cat['value_slug'] ? 'selected' : '' ?>><?= $e((string)$cat['label']) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="field-hint">Drives card colour on /journal/ and the per-category entry counter.</p>
              </div>

              <div class="field-group">
                <label class="field-label" for="journal-tags">Tags <span class="field-hint-inline">optional</span></label>
                <input
                  type="text"
                  class="field-input"
                  id="journal-tags"
                  name="tags"
                  value="<?= $e((string)($journal['tags'] ?? '')) ?>"
                  maxlength="500"
                  placeholder="comma, separated, list">
                <p class="field-hint">Display only — not used for filtering.</p>
              </div>

              <?php if ($entryNumPadded !== null): ?>
                <div class="field-group">
                  <label class="field-label">Entry number</label>
                  <div class="readonly-block">Entry <?= $e($entryNumPadded) ?></div>
                  <p class="field-hint">Assigned on first publish. Permanent identifier.</p>
                </div>
              <?php endif; ?>

              <?php
              $is_live              = $isLive;
              $show_publish_section = $showPublishSection;
              $is_scheduled         = $isScheduled;
              $live_url             = '/journal/' . (string)($journal['slug'] ?? '');
              $published_at_id      = 'journal-published-at';
              $published_at_value   = $publishedAtForInput;
              $updated_label        = 'journal entry';
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

            <button type="submit" form="journal-delete-form" class="btn-sec btn-danger">Delete</button>

            <?php if ($status === 'draft'): ?>
              <button type="submit" name="action" value="schedule" class="btn-sec btn-actions-end" data-set-schedule>Schedule Publish</button>
              <button type="submit" name="action" value="publish" class="btn-pri" data-publish-btn>Publish →</button>
            <?php endif; ?>

            <?php if ($isScheduled): ?>
              <button type="submit" name="action" value="publish-now" class="btn-pri btn-actions-end"
                      data-confirm="Publish this now? It will go live immediately at the current time.">Publish Now</button>
              <button
                type="submit"
                name="action"
                value="unpublish"
                class="btn-sec"
                data-confirm-unpublish="1">Move Back To Draft</button>
            <?php endif; ?>

            <?php if ($isLive): ?>
              <button
                type="submit"
                name="action"
                value="unpublish"
                class="btn-sec btn-actions-end"
                data-confirm-unpublish="1">Move Back To Draft</button>
            <?php endif; ?>
          </div>
        </form>

        <form id="journal-delete-form"
              method="post"
              action="/cms/journals/delete?id=<?= (int)$id ?>"
              class="inline-delete"
              data-stage="<?= $e($status) ?>"
              data-slug="<?= $e((string)($journal['slug'] ?? '')) ?>"
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

<?php if ($showBody): ?>
<script type="module">
  import { setupTiptap } from '/cms/_assets/tiptap-setup.js';
  setupTiptap({
    mount:        document.getElementById('tiptap-editor'),
    fallback:     document.getElementById('journal-body'),
    toolbar:      document.getElementById('tiptap-toolbar'),
    uploadUrl:    '/cms/articles/upload-image?id=<?= (int)$id ?>',
    csrfToken:    <?= json_encode($csrf_token, JSON_UNESCAPED_SLASHES) ?>,
  });
</script>
<?php endif; ?>

<script src="/cms/_assets/scroll-actions.js" defer></script>

<script>
  for (const btn of document.querySelectorAll('[data-confirm-unpublish]')) {
    btn.addEventListener('click', (e) => {
      const ok = window.confirm("Move this journal back to draft? It will be removed from the public site immediately.");
      if (!ok) e.preventDefault();
    });
  }
  for (const form of document.querySelectorAll('form.inline-delete')) {
    form.addEventListener('submit', (e) => {
      const stage = form.getAttribute('data-stage') || '';
      const slug  = form.getAttribute('data-slug')  || '';
      if (stage === 'published') {
        const typed = window.prompt(
          'Deleting a published journal is permanent.\n\nType the slug exactly to confirm:\n\n  ' + slug
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
        if (!window.confirm('Delete this journal? This cannot be undone.')) {
          e.preventDefault();
        }
      }
    });
  }
</script>

<script src="/cms/_assets/publish-choreography.js" defer></script>
<script src="/cms/_assets/preview-tab-guard.js" defer></script>
<script src="/cms/_assets/dirty-flip.js" defer></script>
</body>
</html>
