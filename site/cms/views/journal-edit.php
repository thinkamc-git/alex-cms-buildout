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
        ];

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

            $flashMsg = 'Saved.';
            [$targetStage, $stageMsg] = $actionToStage($action, $currentStage);

            if ($targetStage !== null) {
                save_journal($saveData);
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
$bodyInitial   = (string)($journal['body'] ?? '');

$myStatusIdx = array_search($status, $journalStages, true);
if ($myStatusIdx === false) $myStatusIdx = -1;
$prevStage = $myStatusIdx > 0 ? $journalStages[$myStatusIdx - 1] : null;
$nextStage = $myStatusIdx >= 0 && $myStatusIdx < count($journalStages) - 1
    ? $journalStages[$myStatusIdx + 1] : null;

$saveLabel = $status === 'published' ? 'Save changes' : 'Save ' . ucfirst($status);

$showIdeaNotesReadOnly = $status === 'draft';  // archived after Draft
$showBody              = $status === 'draft' || $status === 'published';

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
<?php endif; ?>
</head>
<body>

<?php
$breadcrumb = 'Journals → Edit';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'journals';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-journal-edit">
      <?php
      $titleHdr = (string)($journal['key_statement'] ?? '');
      if ($titleHdr === '') $titleHdr = (string)($journal['title'] ?? 'Untitled');
      if ($titleHdr === '') $titleHdr = 'Untitled';
      $title    = $titleHdr;
      $entryHdr = $entryNumPadded !== null ? ' · Entry ' . $entryNumPadded : '';
      $subtitle = 'Journal · ' . ucfirst($status) . $entryHdr . ' · last saved ' . $e((string)($journal['updated_at'] ?? ''));
      $actions  = '<a href="/cms/journals" class="btn-ghost">Back to list</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="stage-bar">
        <?php foreach ($journalStages as $i => $s):
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
              <form method="post" action="/cms/journals/edit?id=<?= (int)$id ?>" class="flash-undo">
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
              action="/cms/journals/edit?id=<?= (int)$id ?>"
              class="cms-form cms-form-wide">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

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
                    <strong>Warning:</strong> Changing the slug on a published journal will create a 301 redirect (Phase 11).
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
                  <p class="field-hint">Key Statement alone is enough — body is for expansion.</p>
                </div>
              <?php endif; ?>
            </div>

            <aside class="form-side">
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
                <p class="field-hint">Display only — not used for filtering yet.</p>
              </div>

              <?php if ($entryNumPadded !== null): ?>
                <div class="field-group">
                  <label class="field-label">Entry number</label>
                  <div class="readonly-block">Entry <?= $e($entryNumPadded) ?></div>
                  <p class="field-hint">Assigned on first publish. Permanent identifier.</p>
                </div>
              <?php endif; ?>
            </aside>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" name="action" value="save" class="btn-pri"><?= $e($saveLabel) ?></button>
            <a href="/cms/journals" class="btn-ghost">Cancel</a>

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
              action="/cms/journals/delete?id=<?= (int)$id ?>"
              class="inline-delete danger-zone"
              data-stage="<?= $e($status) ?>"
              data-slug="<?= $e((string)($journal['slug'] ?? '')) ?>">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <?php if ($slugPublished): ?>
            <input type="hidden" name="typed_slug" value="">
          <?php endif; ?>
          <button type="submit" class="btn-ghost btn-danger">Delete journal</button>
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
          'Deleting a published journal is permanent.\n\nType the slug to confirm:\n\n  ' + slug
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

</body>
</html>
