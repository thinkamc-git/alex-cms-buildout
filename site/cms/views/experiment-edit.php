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
                $msg = $res['ok']
                    ? (($res['created'] ?? false) ? 'Folder created.' : 'Folder already exists.')
                    : ($res['error'] ?? 'Could not set up folder.');
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
        $template = (string)($experiment['template'] ?? 'experiment');

        $post = [
            'slug'        => trim((string)($_POST['slug']        ?? '')),
            'title'       => trim((string)($_POST['title']       ?? '')),
            'summary'     => trim((string)($_POST['summary']     ?? '')),
            'body_raw'    =>      (string)($_POST['body']        ?? ''),
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

        $bodyClean = $template === 'experiment' ? sanitize_html($post['body_raw']) : null;

        // source_file: html variant only. Empty = no file picked yet (allowed).
        $sourceFile = $template === 'experiment-html'
            ? ($post['source_file'] !== '' ? $post['source_file'] : null)
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
            $saveData = [
                'id'        => $id,
                'template'  => $template,
                'slug'      => $slug,
                'title'     => $post['title'],
                'summary'   => $post['summary'] !== '' ? $post['summary'] : null,
                'read_time' => $readTime,
                'tags'      => $post['tags']    !== '' ? $post['tags']    : null,
            ];
            if ($template === 'experiment') {
                $saveData['body']        = $bodyClean;
                $saveData['source_file'] = null;
            } else {
                $saveData['body']        = null;
                $saveData['source_file'] = $sourceFile;
            }

            $flashMsg = 'Saved.';
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
                    $stageMsg = 'Published — live at /experiments/' . $slug;
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
$template      = (string)($experiment['template'] ?? 'experiment');
$slugPublished = $status === 'published';
$bodyInitial   = (string)($experiment['body'] ?? '');
$slugVal       = (string)($experiment['slug'] ?? '');

$myStatusIdx = array_search($status, $expStages, true);
if ($myStatusIdx === false) $myStatusIdx = -1;

$saveLabel = $status === 'published' ? 'Save changes' : 'Save ' . ucfirst($status);
$fromStage = (string)($_GET['from_stage'] ?? '');
$canUndo   = $fromStage !== '' && $myStatusIdx > 0;

// Folder state (html variant only).
$folderExists = false;
$folderFiles  = [];
$folderPath   = '';
if ($template === 'experiment-html' && $slugVal !== '') {
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
<link rel="stylesheet" href="/cms/_assets/style-cms.css">
<?php if ($template === 'experiment'): ?>
<link rel="stylesheet" href="/cms/_assets/tiptap.css">
<?php endif; ?>
</head>
<body>

<?php
$breadcrumb = 'Experiments → Edit';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'experiments';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-experiment-edit">
      <?php
      $titleHdr = (string)($experiment['title'] ?? 'Untitled');
      if ($titleHdr === '') $titleHdr = 'Untitled';
      $title    = $titleHdr;
      $subtitle = 'Experiment · ' . $template . ' · ' . ucfirst($status)
                . ' · last saved ' . (string)($experiment['updated_at'] ?? '');

      $subtitle_extra = '';
      if ($flash !== '') {
          $undoHtml = '';
          if ($canUndo) {
              $undoHtml = '<form method="post" action="/cms/experiments/edit?id=' . (int)$id . '">'
                        . '<input type="hidden" name="csrf_token" value="' . $e($csrf_token) . '">'
                        . '<button type="submit" name="action" value="undo" formnovalidate'
                        . ' title="Reverts the last advance.">↶ Undo</button>'
                        . '</form>';
          }
          $subtitle_extra = '<span class="view-subtitle-flash" role="status">'
                          . $e($flash) . $undoHtml
                          . '</span>';
      }

      $actions  = '<a href="/cms/experiments" class="btn-ghost">Back to list</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="stage-bar">
        <?php foreach ($expStages as $i => $s):
          $cls = '';
          if ($i < $myStatusIdx)        $cls = ' done';
          elseif ($i === $myStatusIdx)  $cls = ' current';
        ?>
          <div class="stage-bar-step<?= $cls ?>"><?= ucfirst($s) ?></div>
        <?php endforeach; ?>
      </div>

      <div class="content-area">
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
              action="/cms/experiments/edit?id=<?= (int)$id ?>"
              class="cms-form cms-form-wide">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

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
                    <strong>Warning:</strong> Changing the slug on a published experiment will create a 301 redirect (Phase 11).
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

              <?php if ($template === 'experiment'): ?>
                <div class="field-group">
                  <label class="field-label">Body <span class="field-hint-inline">optional · article-format</span></label>
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
                      id="ex-body"
                      name="body"
                      rows="14"
                      class="tiptap-fallback"
                      aria-label="Experiment body (HTML)"><?= $e($bodyInitial) ?></textarea>
                  </div>
                </div>
              <?php else: /* experiment-html */ ?>
                <div class="field-group">
                  <label class="field-label">Content Folder <span class="field-req">required to publish</span></label>
                  <div class="folder-block">
                    <div class="folder-block-hd">
                      <div class="folder-path" style="font-family:var(--font-mono);font-size:var(--text-meta)"><?= $e($folderPath) ?></div>
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
                        <p class="field-hint">No folder exists yet for this slug. Click <strong>Set up folder</strong> to create
                          <code><?= $e($folderPath) ?></code> on the server.
                          Then drop your <code>.html</code> files into it via SSH/CloudMounter and click <strong>Refresh</strong>.</p>
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
                            <select class="field-select" name="source_file" id="ex-source-file" style="flex:1">
                              <option value="">— Pick a file —</option>
                              <?php foreach ($folderFiles as $f): ?>
                                <option value="<?= $e($f) ?>"<?= $sourceFileVal === $f ? ' selected' : '' ?>><?= $e($f) ?></option>
                              <?php endforeach; ?>
                            </select>
                          <?php endif; ?>
                          <button type="submit" name="action" value="refresh_folder" class="btn-sec" formnovalidate>↺ Refresh</button>
                        </div>
                        <p class="field-hint">
                          Drop <code>.html</code> files into <code><?= $e($folderPath) ?></code> via SSH/CloudMounter,
                          then Refresh. The selected file serves at <code>/experiments/<?= $e($slugVal) ?></code> with no
                          template wrapper.
                        </p>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
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
                <p class="field-hint">Drives card colour on /experiments/ (Prototype vs Concept dark variant).</p>
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
                <p class="field-hint">Display only — not used for filtering yet.</p>
              </div>
            </aside>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" name="action" value="save" class="btn-pri"><?= $e($saveLabel) ?></button>
            <a href="/cms/experiments" class="btn-ghost">Cancel</a>

            <button type="submit" form="experiment-delete-form" class="btn-ghost btn-danger" style="margin-left:auto">Delete</button>

            <?php if ($status === 'draft'): ?>
              <button type="submit" name="action" value="publish" class="btn-pri">Publish →</button>
            <?php endif; ?>

            <?php if ($status === 'published'): ?>
              <button
                type="submit"
                name="action"
                value="unpublish"
                class="btn-ghost"
                data-confirm-unpublish="1">Move to draft</button>
              <a
                href="/experiments/<?= $e($slugVal) ?>"
                target="_blank"
                rel="noopener"
                class="btn-ghost">View live ↗</a>
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

<?php if ($template === 'experiment'): ?>
<script type="module">
  import { setupTiptap } from '/cms/_assets/tiptap-setup.js';
  setupTiptap({
    mount:        document.getElementById('tiptap-editor'),
    fallback:     document.getElementById('ex-body'),
    toolbar:      document.getElementById('tiptap-toolbar'),
    uploadUrl:    '/cms/experiments/upload-image?id=<?= (int)$id ?>',
    csrfToken:    <?= json_encode($csrf_token, JSON_UNESCAPED_SLASHES) ?>,
  });
</script>
<?php endif; ?>

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
          'Deleting a published experiment is permanent.\n\nType the slug to confirm:\n\n  ' + slug
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

</body>
</html>
