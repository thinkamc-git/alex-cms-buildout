<?php
/**
 * cms/views/experiment-edit.php — edit an Experiment (Draft/Published).
 *
 * Branches on `template`:
 *   - 'experiment'      — article-format. Tiptap body, hero image is
 *                          deferred (Phase 10.5). Same block lineup as
 *                          articles (minus Special Tag and Series).
 *   - 'experiment-html' — raw HTML import. Body field is replaced by a
 *                          Custom HTML Folder picker (CMS-STRUCTURE.md §12).
 *
 * POST actions:
 *   - save        — write the form
 *   - publish     — save + transition to published
 *   - unpublish   — save + back to draft
 *   - undo        — revert last advance (from_stage query param)
 *   - setup_folder  — html variant: create /content/experiment/<slug>/
 *   - refresh_folder — html variant: re-scan the folder (no DB write)
 *
 * Idea-stage experiments live in the shared editor (article-edit.php);
 * they advance straight to Draft (experiments skip Concept/Outline per §15).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/content.php';
require_once __DIR__ . '/../../lib/folders.php';
require_once __DIR__ . '/../../lib/sanitize.php';

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: /cms/experiments');
    exit;
}

$experiment = get_experiment($id);
if ($experiment === null) {
    $stmt = db()->prepare("SELECT id FROM content WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    if ($stmt->fetch() === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Experiment not found.\n";
        exit;
    }
    header('Location: /cms/articles/edit?id=' . $id);
    exit;
}

$status = (string)($experiment['status'] ?? 'draft');
if ($status === 'idea') {
    // Idea-stage rows live in the shared editor.
    header('Location: /cms/articles/edit?id=' . $id);
    exit;
}

$errors = [];
$flash  = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

$allExperimentCategories = list_categories('experiment');
$currentPrimaryCategory  = get_primary_category($id);

$expStages = stages_for_type('experiment'); // ['idea','draft','published']

$undoSuffix = static function (string $action, string $current): string {
    return in_array($action, ['advance', 'publish'], true)
        ? '&from_stage=' . urlencode($current)
        : '';
};

$actionToStage = static function (string $action, string $current) use ($expStages) {
    $idx = array_search($current, $expStages, true);
    switch ($action) {
        case 'advance':
            if ($idx === false || $idx >= count($expStages) - 1) return [null, ''];
            $next = $expStages[$idx + 1];
            return [$next, 'Advanced to ' . ucfirst($next) . '.'];
        case 'step-back':
            if ($idx === false || $idx <= 0) return [null, ''];
            $prev = $expStages[$idx - 1];
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
        $action = (string)($_POST['action'] ?? 'save');

        // ── Folder ops (no DB write, no body sanitization needed) ──────
        if ($action === 'setup_folder' || $action === 'refresh_folder') {
            $slug = (string)($experiment['slug'] ?? '');
            if ($slug === '') {
                header('Location: /cms/experiments/edit?id=' . $id
                    . '&flash=' . rawurlencode('Slug required before setting up a folder.'));
                exit;
            }
            if ($action === 'setup_folder') {
                $res = folder_setup('experiment', $slug);
                if ($res['ok']) {
                    $absPath = (string)($res['path'] ?? '');
                    $msg = ($res['created'] ?? false)
                        ? 'Folder created at: ' . $absPath
                        : 'Folder already exists at: ' . $absPath;
                } else {
                    $msg = (string)($res['error'] ?? 'Could not set up folder.');
                }
            } else {
                $msg = 'Refreshed.';
            }
            header('Location: /cms/experiments/edit?id=' . $id . '&flash=' . rawurlencode($msg));
            exit;
        }

        // ── Undo last advance ─────────────────────────────────────────
        if ($action === 'undo') {
            $idx = array_search($status, $expStages, true);
            if ($idx !== false && $idx > 0) {
                $prev = $expStages[$idx - 1];
                $res  = transition_stage($id, $prev);
                if ($res['ok']) {
                    $dest = $prev === 'idea'
                        ? '/cms/articles/edit?id=' . $id
                        : '/cms/experiments/edit?id=' . $id;
                    header('Location: ' . $dest
                        . '&flash=' . rawurlencode('Reverted to ' . ucfirst($prev) . '.'));
                    exit;
                }
            }
            header('Location: /cms/experiments/edit?id=' . $id);
            exit;
        }

        // ── Save (+ optional stage transition) ────────────────────────
        // Phase 20.3: template stays 'experiment' for all experiment rows;
        // body_mode (rtf | html-body | html-swap) drives the variant.
        $template = 'experiment';
        $postedMode = (string)($_POST['body_mode'] ?? 'rtf');
        if (!in_array($postedMode, ['rtf', 'html-body', 'html-swap'], true)) $postedMode = 'rtf';

        $post = [
            'slug'        => trim((string)($_POST['slug']        ?? '')),
            'title'       => trim((string)($_POST['title']       ?? '')),
            'summary'     => trim((string)($_POST['summary']     ?? '')),
            'body_raw'    =>      (string)($_POST['body']        ?? ''),
            'body_mode'   => $postedMode,
            'source_file' => trim((string)($_POST['source_file'] ?? '')),
            'read_time'   => trim((string)($_POST['read_time']   ?? '')),
            'tags'        => trim((string)($_POST['tags']        ?? '')),
            'primary_category' => trim((string)($_POST['primary_category'] ?? '')),
        ];

        $allowedCatSlugs = array_map(static fn($c) => (string)$c['value_slug'], $allExperimentCategories);
        if ($post['primary_category'] !== '' && !in_array($post['primary_category'], $allowedCatSlugs, true)) {
            $errors[] = 'Primary category is not a known experiment category.';
            $post['primary_category'] = '';
        }

        if ($post['title'] === '') {
            $errors[] = 'Title is required.';
        }

        $slug = $post['slug'] !== '' ? slugify($post['slug']) : slugify($post['title']);
        if ($slug === '') {
            $errors[] = 'Slug is required.';
        } else {
            $slug = unique_slug($slug, $id);
        }

        $readTime = null;
        if ($post['read_time'] !== '') {
            if (!ctype_digit($post['read_time'])) {
                $errors[] = 'Read time must be a whole number of minutes.';
            } else {
                $readTime = (int)$post['read_time'];
            }
        }

        // Phase 20.3: keep TipTap body across mode toggles so flipping the
        // selector from RTF → HTML → RTF doesn't lose the in-progress draft.
        $bodyClean = sanitize_html($post['body_raw']);

        // source_file lives with both html-body and html-swap (both read
        // from a real file in /content/experiment/<slug>/).
        $sourceFile = ($post['body_mode'] !== 'rtf' && $post['source_file'] !== '')
            ? $post['source_file']
            : null;

        // Validate that source_file (if non-null) actually exists in the folder.
        // Catches drift between picker state and disk.
        if ($sourceFile !== null && $slug !== '') {
            $files = folder_scan('experiment', $slug);
            if (!in_array($sourceFile, $files, true)) {
                $errors[] = 'Selected file no longer exists in the folder. Refresh and pick again.';
            }
        }

        if (count($errors) === 0) {
            // Phase 20.3: always persist body (TipTap state) — toggling the
            // mode keeps the draft. body_mode drives render-time selection.
            // source_file only updates when the user is in an HTML mode;
            // a stale select in a hidden panel can't overwrite the saved
            // reference when RTF is active.
            $saveData = [
                'id'        => $id,
                'template'  => $template,
                'body_mode' => $post['body_mode'],
                'slug'      => $slug,
                'title'     => $post['title'],
                'summary'   => $post['summary'] !== '' ? $post['summary'] : null,
                'read_time' => $readTime,
                'tags'      => $post['tags']    !== '' ? $post['tags']    : null,
                'body'      => $bodyClean,
            ];
            if ($post['body_mode'] !== 'rtf') {
                $saveData['source_file'] = $sourceFile;
            }

            // Phase 14.6 — published_at editable on live rows.
            $rowIsCurrentlyLive = ((string)($experiment['status'] ?? '') === 'published')
                && ((string)($experiment['published_status'] ?? '') !== 'scheduled');
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
                header('Location: /cms/experiments/edit?id=' . $id . '&flash=' . rawurlencode('Published — live now.'));
                exit;
            }

            // Phase 14.6 — schedule branch (see article-edit.php for canonical
            // explanation). Saves form fields then schedules; cron promotes.
            if ($action === 'schedule') {
                $scheduleAt = trim((string)($_POST['schedule_at'] ?? ''));
                if ($scheduleAt === '') {
                    $errors[] = 'A schedule date/time is required.';
                } else {
                    save_experiment($saveData);
                    assign_primary_category($id, 'experiment', $post['primary_category']);
                    $res = schedule_content($id, $scheduleAt);
                    if (!$res['ok']) {
                        header('Location: /cms/experiments/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                        exit;
                    }
                    $stamp = (string)($res['published_at'] ?? $scheduleAt);
                    $msg = 'Scheduled for ' . date('M j, Y · g:i A', strtotime($stamp));
                    header('Location: /cms/experiments/edit?id=' . $id . '&flash=' . rawurlencode($msg));
                    exit;
                }
            }

            [$targetStage, $stageMsg] = $actionToStage($action, $status);

            if ($targetStage !== null) {
                save_experiment($saveData);
                assign_primary_category($id, 'experiment', $post['primary_category']);
                $res = transition_stage($id, $targetStage);
                if (!$res['ok']) {
                    header('Location: /cms/experiments/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                    exit;
                }
                if ($targetStage === 'published') {
                    $stageMsg = 'Published — live at /field-work/' . $slug;
                }
                header('Location: /cms/experiments/edit?id=' . $id
                    . '&flash=' . rawurlencode($stageMsg)
                    . $undoSuffix($action, $status));
                exit;
            }

            save_experiment($saveData);
            assign_primary_category($id, 'experiment', $post['primary_category']);
            header('Location: /cms/experiments/edit?id=' . $id . '&flash=' . rawurlencode($flashMsg));
            exit;
        }

        // Validation failed — keep posted values on the form.
        $experiment = array_merge($experiment, [
            'slug'        => $slug !== '' ? $slug : $post['slug'],
            'title'       => $post['title'],
            'summary'     => $post['summary'],
            'body'        => $bodyClean ?? $experiment['body'] ?? null,
            'source_file' => $sourceFile,
            'read_time'   => $readTime,
            'tags'        => $post['tags'],
        ]);
    }
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$status        = (string)($experiment['status'] ?? 'draft');

// Phase 14.6 — publish-state derivation (parallels article-edit.php).
$publishedStatus    = (string)($experiment['published_status'] ?? '');
$isScheduled        = ($status === 'published' && $publishedStatus === 'scheduled');
$isLive             = ($status === 'published' && $publishedStatus !== 'scheduled');
$showPublishSection = ($status === 'draft' || $isScheduled);
$publishedAtRaw     = (string)($experiment['published_at'] ?? '');
$scheduleAtForInput = $isScheduled && $publishedAtRaw !== ''
    ? str_replace(' ', 'T', substr($publishedAtRaw, 0, 16))
    : '';
$minScheduleAt      = date('Y-m-d\TH:i', time() + 60);

// Phase 14.6 (followup) — Publish info box for live rows.
$publishedAtForInput = $publishedAtRaw !== ''
    ? str_replace(' ', 'T', substr($publishedAtRaw, 0, 16))
    : '';
$updatedAtRaw       = (string)($experiment['updated_at'] ?? '');
$updatedAtFormatted = $updatedAtRaw !== ''
    ? date('M j, Y · g:i A', strtotime($updatedAtRaw))
    : '—';
$updatedAtDateOnly  = $updatedAtRaw !== '' ? substr($updatedAtRaw, 0, 10) : '';
$showUpdated        = !empty($experiment['show_updated']);
$updatedDisplayRaw  = (string)($experiment['updated_display'] ?? '');
$updatedDisplayDateOnly = $updatedDisplayRaw !== '' ? substr($updatedDisplayRaw, 0, 10) : '';
$updatedHasOverride = $updatedDisplayDateOnly !== '' && $updatedDisplayDateOnly !== $updatedAtDateOnly;
$updatedInputValue  = $updatedHasOverride ? $updatedDisplayDateOnly : $updatedAtDateOnly;

$template      = (string)($experiment['template'] ?? 'experiment');
$slugPublished = $status === 'published';
$bodyInitial   = (string)($experiment['body'] ?? '');
$slugVal       = (string)($experiment['slug'] ?? '');

// Phase 20.3: body_mode drives the three-way variant selector. Always
// available — even pre-Phase-20.3 rows backfilled correctly via the
// migration. Default 'rtf' for any row that somehow lacks the column.
$bodyMode = (string)($experiment['body_mode'] ?? 'rtf');
if (!in_array($bodyMode, ['rtf', 'html-body', 'html-swap'], true)) $bodyMode = 'rtf';

$myStatusIdx = array_search($status, $expStages, true);
if ($myStatusIdx === false) $myStatusIdx = -1;

$saveLabel = $status === 'published' ? 'Publish changes' : 'Save ' . ucfirst($status);
$fromStage = (string)($_GET['from_stage'] ?? '');
$canUndo   = $fromStage !== '' && $myStatusIdx > 0;

// Phase 20.2: Preview sub-tab. Experiments render at draft + published.
$showPreviewTab = ($status === 'draft' || $status === 'published');
$activeTab      = (string)($_GET['tab'] ?? 'edit');
if (!in_array($activeTab, ['edit', 'preview'], true)) $activeTab = 'edit';
if ($activeTab === 'preview' && !$showPreviewTab) $activeTab = 'edit';

// Folder state — always computed when a slug exists, regardless of the
// row's saved body_mode. The user may toggle to html-body/html-swap on
// the form without having saved yet; the folder UI needs the path
// resolved at render time so clicking "Set up folder" creates the right
// directory.
$folderExists = false;
$folderFiles  = [];
$folderPath   = '';
if ($slugVal !== '') {
    $folderPath   = '/content/experiment/' . $slugVal . '/';
    $folderExists = folder_exists('experiment', $slugVal);
    if ($folderExists) {
        $folderFiles = folder_scan('experiment', $slugVal);
    }
}
$sourceFileVal = (string)($experiment['source_file'] ?? '');
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Edit experiment: <?= $e((string)($experiment['title'] ?? 'Untitled')) ?> — alexmchong.ca CMS</title>
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
<link rel="stylesheet" href="/cms/_assets/style-cms.css<?= asset_ver('/cms/_assets/style-cms.css') ?>">
<?php /* Phase 20.3: always load tiptap.css — the RTF panel mounts even
   when body_mode starts in html-body/html-swap, so the user can flip
   back to RTF without a stylesheet reload. */ ?>
<link rel="stylesheet" href="/cms/_assets/tiptap.css<?= asset_ver('/cms/_assets/tiptap.css') ?>">
<!-- Phase 22.6: blocks slice styles the .article-prose editor (style-articles.css deleted). -->
<link rel="stylesheet" href="/_ds/css/public/blocks.css">
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
        $fromKey = 'experiments';
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
[$_navLabel, $_navHref] = $navLabelMap[$fromKey] ?? ['Experiments', '/cms/experiments'];
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
    <div class="view active" id="view-experiment-edit">
      <?php
      $titleHdr = (string)($experiment['title'] ?? 'Untitled');
      if ($titleHdr === '') $titleHdr = 'Untitled';
      $title    = $titleHdr;
      $stageLabel = $isScheduled ? 'Scheduled for Publish' : ucfirst($status);
      $subtitle = 'Experiment · ' . $template . ' · ' . $stageLabel
                . ' · saved ' . (string)($experiment['updated_at'] ?? '');

      // Flash + optional Undo render via the canonical flash-success banner
      // above the content area (proposal #4 + #29).
      $flash_extra = '';
      if ($flash !== '' && $canUndo) {
          $flash_extra = ' <form method="post" action="/cms/experiments/edit?id=' . (int)$id . '" class="flash-undo">'
                       . '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                       . '<button type="submit" name="action" value="undo" formnovalidate class="btn-link"'
                       . ' title="Reverts the last advance.">↶ Undo</button>'
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
      [$backHref, $backLabel] = $backMap[$fromKey] ?? ['/cms/experiments', '← Back to list'];
      $actions  = '<a href="' . $e($backHref) . '" class="btn-sec">' . $e($backLabel) . '</a>';
      if ((string)($experiment['status'] ?? '') === 'published' && !empty($experiment['slug'])) {
          $actions .= ' <a href="/field-work/' . $e((string)$experiment['slug']) . '" target="_blank" rel="noopener" class="btn-sec">Live ↗</a>';
      }
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="stage-bar">
        <?php foreach ($expStages as $i => $s):
          $cls = '';
          if ($i < $myStatusIdx)        $cls = ' done';
          elseif ($i === $myStatusIdx)  $cls = ' current';
          $stepLabel = ($s === 'published' && $isScheduled) ? 'Scheduled' : ucfirst($s);
        ?>
          <div class="stage-bar-step<?= $cls ?>"><?= $e($stepLabel) ?></div>
        <?php endforeach; ?>
      </div>

      <?php if ($showPreviewTab): ?>
        <div class="cms-tabs" role="tablist" aria-label="Experiment edit and preview">
          <a class="cms-tab<?= $activeTab === 'edit' ? ' active' : '' ?>"
             role="tab" data-tab-target="edit"
             aria-selected="<?= $activeTab === 'edit' ? 'true' : 'false' ?>"
             href="/cms/experiments/edit?id=<?= (int)$id ?>&tab=edit">Edit</a>
          <a class="cms-tab<?= $activeTab === 'preview' ? ' active' : '' ?>"
             role="tab" data-tab-target="preview"
             aria-selected="<?= $activeTab === 'preview' ? 'true' : 'false' ?>"
             href="/cms/experiments/edit?id=<?= (int)$id ?>&tab=preview">Preview</a>
        </div>

        <div class="post-preview-frame<?= $activeTab === 'preview' ? '' : ' is-hidden-tab' ?>" data-tab-panel="preview">
          <iframe
            name="post-preview-frame-<?= (int)$id ?>"
            src="/cms/post/preview?id=<?= (int)$id ?>"
            title="Preview · Experiment"
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
              action="/cms/experiments/edit?id=<?= (int)$id ?>"
              class="cms-form cms-form-wide reveal-page"
              data-preview-source-form>
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <?php if ($isScheduled): ?>
            <?php
            $published_at_raw = $publishedAtRaw;
            require __DIR__ . '/../partials/schedule-banner.php';
            ?>
          <?php elseif ($isLive): ?>
            <?php
            $experimentSlug   = (string)($experiment['slug'] ?? '');
            $published_at_raw = $publishedAtRaw;
            $live_url         = $experimentSlug !== '' ? ('/experiments/' . $experimentSlug) : '';
            require __DIR__ . '/../partials/live-banner.php';
            ?>
          <?php endif; ?>

          <div class="form-grid">
            <div class="form-main">
              <div class="field-group">
                <label class="field-label" for="ex-slug">Slug <span class="field-req">required</span></label>
                <input
                  type="text"
                  class="field-input"
                  id="ex-slug"
                  name="slug"
                  value="<?= $e($slugVal) ?>"
                  maxlength="200"
                  pattern="[a-z0-9\-]+"
                  required>
                <p class="field-hint">
                  <?php if ($slugPublished): ?>
                    <strong>Warning:</strong> changing the slug on a published experiment will create a 301 redirect.
                  <?php else: ?>
                    Lowercase letters, numbers, hyphens. Becomes part of <code>/experiments/&lt;slug&gt;</code>.
                  <?php endif; ?>
                </p>
              </div>

              <?php if ($status === 'draft' && ($experiment['notes'] ?? '') !== ''): ?>
                <div class="field-group">
                  <label class="field-label">Idea Notes</label>
                  <div class="readonly-block"><?= nl2br($e((string)($experiment['notes'] ?? '')), false) ?></div>
                  <p class="field-hint">Private scratchpad from the Idea stage. Archived once published.</p>
                </div>
              <?php endif; ?>

              <div class="field-group">
                <label class="field-label" for="ex-title">Experiment Title <span class="field-req">required</span></label>
                <input
                  type="text"
                  class="field-input large"
                  id="ex-title"
                  name="title"
                  value="<?= $e((string)($experiment['title'] ?? '')) ?>"
                  maxlength="500"
                  required>
              </div>

              <div class="field-group">
                <label class="field-label" for="ex-summary">Summary <span class="field-hint-inline">optional</span></label>
                <textarea
                  id="ex-summary"
                  class="field-input"
                  name="summary"
                  rows="3"
                  maxlength="500"
                  placeholder="One-line deck below the title."><?= $e((string)($experiment['summary'] ?? '')) ?></textarea>
              </div>

              <div class="field-group" data-body-source-block>
                <div class="body-source-header">
                  <label class="field-label" style="margin:0">Body</label>
                  <div class="body-source-toggle" role="radiogroup" aria-label="Body source">
                    <label class="body-source-option<?= $bodyMode === 'rtf' ? ' is-active' : '' ?>">
                      <input type="radio" name="body_mode" value="rtf"<?= $bodyMode === 'rtf' ? ' checked' : '' ?>>
                      <span>Rich text</span>
                    </label>
                    <label class="body-source-option<?= $bodyMode === 'html-body' ? ' is-active' : '' ?>">
                      <input type="radio" name="body_mode" value="html-body"<?= $bodyMode === 'html-body' ? ' checked' : '' ?>>
                      <span>HTML body</span>
                    </label>
                    <label class="body-source-option<?= $bodyMode === 'html-swap' ? ' is-active' : '' ?>">
                      <input type="radio" name="body_mode" value="html-swap"<?= $bodyMode === 'html-swap' ? ' checked' : '' ?>>
                      <span>HTML swap</span>
                    </label>
                  </div>
                </div>

                <!-- RTF — TipTap (always mounted so toggling preserves draft state) -->
                <div class="body-source-panel" data-body-panel="rtf"<?= $bodyMode !== 'rtf' ? ' hidden' : '' ?>>
                  <div class="tiptap-wrap body-box">
                    <div class="tiptap-toolbar" id="tiptap-toolbar">
                      <button type="button" data-cmd="bold"        class="tt-btn"><strong>B</strong></button>
                      <button type="button" data-cmd="italic"      class="tt-btn"><em>I</em></button>
                      <button type="button" data-cmd="h2"          class="tt-btn">H2</button>
                      <button type="button" data-cmd="kicker-h2"   title="Section-header kicker" class="tt-btn">H2^</button>
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
                      id="ex-body"
                      name="body"
                      rows="14"
                      class="tiptap-fallback"
                      aria-label="Experiment body (HTML)"><?= $e($bodyInitial) ?></textarea>
                  </div>
                  <p class="field-hint">Article-format body. Any HTML outside the toolbar's allowlist is stripped on save.</p>
                </div>

                <!-- HTML body — chrome stays, body slot reads the file -->
                <!-- HTML swap — full passthrough (no chrome). Same folder picker drives both. -->
                <?php
                $folderHintBody = 'The article chrome stays; the body slot reads the selected file.';
                $folderHintSwap = 'Full-page passthrough — the file is served directly at <code>/experiments/' . $e($slugVal) . '/</code> with no template wrapper.';
                foreach (['html-body' => $folderHintBody, 'html-swap' => $folderHintSwap] as $mode => $hintHtml):
                ?>
                  <div class="body-source-panel" data-body-panel="<?= $mode ?>"<?= $bodyMode !== $mode ? ' hidden' : '' ?>>
                    <div class="folder-block">
                      <div class="folder-block-hd">
                        <div class="folder-path"><?= $e($folderPath) ?: '/content/experiment/<slug>/' ?></div>
                        <span class="folder-status">
                          <?php if (!$folderExists): ?>
                            <span class="muted">Folder not set up yet</span>
                          <?php elseif (count($folderFiles) === 0): ?>
                            <span class="muted">Folder is empty</span>
                          <?php else: ?>
                            <?= (int)count($folderFiles) ?> file<?= count($folderFiles) === 1 ? '' : 's' ?>
                          <?php endif; ?>
                        </span>
                      </div>
                      <div class="folder-block-bd">
                        <?php if (!$folderExists): ?>
                          <p class="field-hint">No folder exists yet for this slug. Click "Set up folder" to create
                            <code><?= $e($folderPath) ?: '/content/experiment/&lt;slug&gt;/' ?></code> on the server.
                            Drop your <code>.html</code> files into it via SSH or CloudMounter, then click Refresh.</p>
                          <button type="submit" name="action" value="setup_folder" class="btn-sec" formnovalidate>
                            Set up folder
                          </button>
                        <?php else: ?>
                          <div style="display:flex;gap:var(--space-8);align-items:center">
                            <?php if (count($folderFiles) === 0): ?>
                              <select class="field-select" name="source_file" disabled style="flex:1">
                                <option>— no .html files in folder —</option>
                              </select>
                            <?php else: ?>
                              <select class="field-select" name="source_file" id="ex-source-file-<?= $mode ?>" style="flex:1">
                                <option value="">— Pick a file —</option>
                                <?php foreach ($folderFiles as $f): ?>
                                  <option value="<?= $e($f) ?>"<?= $sourceFileVal === $f ? ' selected' : '' ?>><?= $e($f) ?></option>
                                <?php endforeach; ?>
                              </select>
                            <?php endif; ?>
                            <button type="submit" name="action" value="refresh_folder" class="btn-sec" formnovalidate>↺ Refresh</button>
                          </div>
                          <p class="field-hint"><?= $hintHtml ?></p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <aside class="form-side">
              <div class="field-group">
                <label class="field-label" for="ex-primary-category">Primary category</label>
                <select class="field-select" id="ex-primary-category" name="primary_category">
                  <option value="">— None</option>
                  <?php foreach ($allExperimentCategories as $cat): ?>
                    <option value="<?= $e((string)$cat['value_slug']) ?>" <?= $currentPrimaryCategory === (string)$cat['value_slug'] ? 'selected' : '' ?>><?= $e((string)$cat['label']) ?></option>
                  <?php endforeach; ?>
                </select>
                <p class="field-hint">Drives card colour on /experiments/. The Concept category renders with the dark variant.</p>
              </div>

              <div class="field-group">
                <label class="field-label" for="ex-read-time">Read time <span class="field-hint-inline">manual</span></label>
                <div style="display:flex;gap:var(--space-8);align-items:center">
                  <input
                    type="text"
                    class="field-input"
                    id="ex-read-time"
                    name="read_time"
                    value="<?= $e($experiment['read_time'] !== null ? (string)$experiment['read_time'] : '') ?>"
                    maxlength="3"
                    style="width:72px;text-align:center;font-family:var(--font-mono)"
                    inputmode="numeric"
                    pattern="\d*">
                  <span class="muted">min</span>
                </div>
                <p class="field-hint">Manual estimate. Optional.</p>
              </div>

              <div class="field-group">
                <label class="field-label" for="ex-tags">Tags <span class="field-hint-inline">optional</span></label>
                <input
                  type="text"
                  class="field-input"
                  id="ex-tags"
                  name="tags"
                  value="<?= $e((string)($experiment['tags'] ?? '')) ?>"
                  maxlength="500"
                  placeholder="prototype, tool, …">
                <p class="field-hint">Display only — not used for filtering.</p>
              </div>

              <?php
              $is_live              = $isLive;
              $show_publish_section = $showPublishSection;
              $is_scheduled         = $isScheduled;
              $live_url             = '/experiments/' . (string)($experiment['slug'] ?? '');
              $published_at_id      = 'ex-published-at';
              $published_at_value   = $publishedAtForInput;
              $updated_label        = 'experiment';
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

            <button type="submit" form="experiment-delete-form" class="btn-sec btn-danger">Delete</button>

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

        <form id="experiment-delete-form"
              method="post"
              action="/cms/experiments/delete?id=<?= (int)$id ?>"
              class="inline-delete"
              data-stage="<?= $e($status) ?>"
              data-slug="<?= $e($slugVal) ?>"
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

<?php /* Phase 20.3: TipTap always mounts so RTF body state is preserved
   across mode toggles. The RTF panel may be hidden by JS, but the editor
   instance lives on in the DOM and its fallback textarea still submits. */ ?>
<script type="module">
  import { setupTiptap } from '/cms/_assets/tiptap-setup.js<?= asset_ver('/cms/_assets/tiptap-setup.js') ?>';
  setupTiptap({
    mount:        document.getElementById('tiptap-editor'),
    fallback:     document.getElementById('ex-body'),
    toolbar:      document.getElementById('tiptap-toolbar'),
    uploadUrl:    '/cms/experiments/upload-image?id=<?= (int)$id ?>',
    csrfToken:    <?= json_encode($csrf_token, JSON_UNESCAPED_SLASHES) ?>,
  });
</script>
<script>
  // Phase 20.3: body-source toggle (rtf / html-body / html-swap).
  // Show only the active panel; preserve form state in all of them so
  // toggling never loses draft content or a file selection. Sync the
  // file-picker selects between html-body and html-swap so they share a
  // single source_file value.
  (function () {
    const root = document.querySelector('[data-body-source-block]');
    if (!root) return;
    const radios  = root.querySelectorAll('input[name="body_mode"]');
    const panels  = root.querySelectorAll('[data-body-panel]');
    const options = root.querySelectorAll('.body-source-option');
    const selects = root.querySelectorAll('select[name="source_file"]');

    function activate(mode) {
      panels.forEach(p => { p.hidden = (p.getAttribute('data-body-panel') !== mode); });
      options.forEach(o => o.classList.toggle('is-active', o.querySelector('input').value === mode));
    }
    radios.forEach(r => r.addEventListener('change', () => activate(r.value)));

    // Keep both html-mode selects in lockstep so the value at submit time
    // matches what the visible select shows.
    selects.forEach(sel => {
      sel.addEventListener('change', () => {
        selects.forEach(other => { if (other !== sel) other.value = sel.value; });
      });
    });
  })();
</script>

<script src="/cms/_assets/scroll-actions.js" defer></script>

<script>
  for (const btn of document.querySelectorAll('[data-confirm-unpublish]')) {
    btn.addEventListener('click', (e) => {
      const ok = window.confirm("Move this experiment back to draft? It will be removed from the public site immediately.");
      if (!ok) e.preventDefault();
    });
  }
  for (const form of document.querySelectorAll('form.inline-delete')) {
    form.addEventListener('submit', (e) => {
      const stage = form.getAttribute('data-stage') || '';
      const slug  = form.getAttribute('data-slug')  || '';
      if (stage === 'published') {
        const typed = window.prompt(
          'Deleting a published experiment is permanent.\n\nType the slug exactly to confirm:\n\n  ' + slug
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
        if (!window.confirm('Delete this experiment? This cannot be undone.')) {
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
