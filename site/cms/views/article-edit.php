<?php
/**
 * cms/views/article-edit.php — edit an existing Article at any pipeline stage.
 *
 * Routed from site/index.php:
 *   GET  /cms/articles/edit?id=N — render the form (stage-aware variant)
 *   POST /cms/articles/edit?id=N — save fields and/or transition stage
 *
 * Stage variants:
 *   - Idea  → minimal form (title + notes [stored as concept_text]).
 *   - Concept/Outline/Draft/Published → full editor inherited from Phase 6a.
 *
 * Actions (POST body `action`):
 *   - save        — write fields, keep stage.
 *   - advance     — write fields, transition to next stage.
 *   - step-back   — write fields, transition to previous stage.
 *   - publish     — alias of advance, used when stage = draft.
 *   - unpublish   — write fields, transition Published → Draft (UI requires
 *                   a JS confirm, server allows it unconditionally).
 *
 * Forward skipping is rejected by lib/content.php::transition_stage; the UI
 * already hides off-neighbor buttons, but the server is the source of truth.
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
    header('Location: /cms/articles');
    exit;
}

$article = get_article($id);
if ($article === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Article not found.\n";
    exit;
}

$errors = [];
$flash  = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

/**
 * Map an action to a destination stage (or null = no transition). Returns
 * a tuple [stage|null, defaultFlashMessage].
 */
$actionToStage = static function (string $action, string $current) {
    $idx = stage_index($current);
    switch ($action) {
        case 'advance':
            if ($idx < 0 || $idx >= count(ARTICLE_STAGES) - 1) return [null, ''];
            $next = ARTICLE_STAGES[$idx + 1];
            return [$next, 'Advanced to ' . ucfirst($next) . '.'];
        case 'step-back':
            if ($idx <= 0) return [null, ''];
            $prev = ARTICLE_STAGES[$idx - 1];
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
        $currentStage = (string)($article['status'] ?? 'idea');

        if ($currentStage === 'idea') {
            // ── Idea-stage form: title + notes (→ concept_text) ─────────
            $titleIn = trim((string)($_POST['title'] ?? ''));
            $notesIn = trim((string)($_POST['notes'] ?? ''));

            if ($titleIn === '') {
                $errors[] = 'Title is required.';
            }

            // Re-derive slug from title if title changed; ideas are pre-publish
            // so the slug churning here is harmless and keeps URLs in sync.
            $slug = (string)($article['slug'] ?? '');
            $currentTitle = (string)($article['title'] ?? '');
            if ($titleIn !== '' && $titleIn !== $currentTitle) {
                $candidate = slugify($titleIn);
                if ($candidate !== '') $slug = unique_slug($candidate, $id);
            }

            if (count($errors) === 0) {
                $saveData = [
                    'id'           => $id,
                    'title'        => $titleIn,
                    'slug'         => $slug,
                    'concept_text' => $notesIn !== '' ? $notesIn : null,
                ];
                $flashMsg = 'Saved.';

                [$targetStage, $stageMsg] = $actionToStage($action, $currentStage);
                if ($targetStage !== null) {
                    save_article($saveData);
                    $res = transition_stage($id, $targetStage);
                    if (!$res['ok']) {
                        header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                        exit;
                    }
                    header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($stageMsg));
                    exit;
                }

                save_article($saveData);
                header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($flashMsg));
                exit;
            }

            $article = array_merge($article, [
                'title'        => $titleIn,
                'slug'         => $slug,
                'concept_text' => $notesIn,
            ]);
        } else {
            // ── Concept / Outline / Draft / Published — full editor ─────
            $post = [
                'title'         => trim((string)($_POST['title']         ?? '')),
                'slug'          => trim((string)($_POST['slug']          ?? '')),
                'summary'       => trim((string)($_POST['summary']       ?? '')),
                'body_raw'      =>      (string)($_POST['body']          ?? ''),
                'tags'          => trim((string)($_POST['tags']          ?? '')),
                'read_time'     => trim((string)($_POST['read_time']     ?? '')),
                'special_tag'   => trim((string)($_POST['special_tag']   ?? '')),
                'hero_caption'  => trim((string)($_POST['hero_caption']  ?? '')),
                'hero_size'     => trim((string)($_POST['hero_size']     ?? 'default')),
                'remove_hero'   => isset($_POST['remove_hero']),
            ];

            if ($post['title'] === '') {
                $errors[] = 'Title is required.';
            }

            $slug = $post['slug'] !== '' ? slugify($post['slug']) : slugify($post['title']);
            if ($slug === '') {
                $errors[] = 'Slug is required.';
            } else {
                $slug = unique_slug($slug, $id);
            }

            $specialTag = $post['special_tag'];
            if (!in_array($specialTag, ['', 'principle', 'framework'], true)) {
                $errors[] = 'Special tag must be empty, principle, or framework.';
                $specialTag = '';
            }
            $specialTagDb = $specialTag === '' ? null : $specialTag;

            $heroSize = in_array($post['hero_size'], ['default', 'wide', 'full'], true)
                ? $post['hero_size']
                : 'default';

            $readTime = null;
            if ($post['read_time'] !== '') {
                if (!ctype_digit($post['read_time'])) {
                    $errors[] = 'Read time must be a whole number of minutes.';
                } else {
                    $readTime = (int)$post['read_time'];
                }
            }

            $bodyClean = sanitize_html($post['body_raw']);

            $heroPath = (string)($article['hero_image'] ?? '');
            if ($post['remove_hero']) {
                $heroPath = '';
            }
            if (isset($_FILES['hero']) && is_array($_FILES['hero']) && (int)$_FILES['hero']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = accept_upload($_FILES['hero'], 'content/article/' . $slug);
                if (!$up['ok']) {
                    $errors[] = 'Hero image: ' . $up['error'];
                } else {
                    $heroPath = $up['url'];
                }
            }

            if (count($errors) === 0) {
                $saveData = [
                    'id'           => $id,
                    'template'     => 'article-standard',
                    'title'        => $post['title'],
                    'slug'         => $slug,
                    'summary'      => $post['summary'] !== '' ? $post['summary'] : null,
                    'body'         => $bodyClean,
                    'hero_image'   => $heroPath !== '' ? $heroPath : null,
                    'hero_caption' => $post['hero_caption'] !== '' ? $post['hero_caption'] : null,
                    'hero_size'    => $heroSize,
                    'special_tag'  => $specialTagDb,
                    'tags'         => $post['tags'] !== '' ? $post['tags'] : null,
                    'read_time'    => $readTime,
                ];

                $flashMsg = 'Saved.';
                [$targetStage, $stageMsg] = $actionToStage($action, $currentStage);

                if ($targetStage !== null) {
                    save_article($saveData);
                    $res = transition_stage($id, $targetStage);
                    if (!$res['ok']) {
                        header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($res['error']));
                        exit;
                    }
                    if ($targetStage === 'published') {
                        $stageMsg = 'Published — live at /writing/' . $slug;
                    }
                    header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($stageMsg));
                    exit;
                }

                save_article($saveData);
                header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode($flashMsg));
                exit;
            }

            $article = array_merge($article, [
                'title'        => $post['title'],
                'slug'         => $slug !== '' ? $slug : $post['slug'],
                'summary'      => $post['summary'],
                'body'         => $bodyClean,
                'hero_image'   => $heroPath,
                'hero_caption' => $post['hero_caption'],
                'hero_size'    => $heroSize,
                'special_tag'  => $specialTagDb,
                'tags'         => $post['tags'],
                'read_time'    => $readTime,
            ]);
        }
    }
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$status        = (string)($article['status'] ?? 'idea');
$statusIdx     = stage_index($status);
$isIdea        = $status === 'idea';
$slugPublished = $status === 'published';
$bodyInitial   = (string)($article['body'] ?? '');

$prevStage  = $statusIdx > 0                              ? ARTICLE_STAGES[$statusIdx - 1] : null;
$nextStage  = $statusIdx >= 0 && $statusIdx < count(ARTICLE_STAGES) - 1
            ? ARTICLE_STAGES[$statusIdx + 1] : null;

// Stage-aware Save label. Published reads "Save changes" since it's a
// post-publish edit; every other stage names the stage you're saving in.
$saveLabel = $status === 'published'
    ? 'Save changes'
    : 'Save ' . ucfirst($status);
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Edit: <?= $e((string)($article['title'] ?? 'Untitled')) ?> — alexmchong.ca CMS</title>
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
<?php if (!$isIdea): ?>
<link rel="stylesheet" href="/cms/_assets/tiptap.css">
<?php endif; ?>
</head>
<body>

<?php
$breadcrumb = 'Articles → Edit';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'articles';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-article-edit">
      <?php
      $titleHdr = (string)($article['title'] ?? 'Untitled');
      if ($titleHdr === '') $titleHdr = 'Untitled';
      $title    = $titleHdr;
      $subtitle = 'Article · ' . ucfirst($status) . ' · last saved ' . $e((string)($article['updated_at'] ?? ''));
      $actions  = '<a href="/cms/articles" class="btn-ghost">Back to list</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="stage-bar">
        <?php foreach (ARTICLE_STAGES as $i => $s):
          $cls = '';
          if ($i < $statusIdx)      $cls = ' done';
          elseif ($i === $statusIdx) $cls = ' current';
        ?>
          <div class="stage-bar-step<?= $cls ?>"><?= ucfirst($s) ?></div>
        <?php endforeach; ?>
      </div>

      <div class="content-area">
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status"><?= $e($flash) ?></div>
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

      <?php if ($isIdea): ?>
        <form method="post"
              action="/cms/articles/edit?id=<?= (int)$id ?>"
              class="cms-form">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="info-box"><strong>Idea stage</strong> — capture the title and any early notes. Slug and full editor unlock once you advance to Concept.</div>

          <div class="field-group">
            <label class="field-label" for="article-title">Title <span class="field-req">required</span></label>
            <input
              type="text"
              class="field-input large"
              id="article-title"
              name="title"
              value="<?= $e((string)($article['title'] ?? '')) ?>"
              maxlength="500"
              required>
          </div>

          <div class="field-group">
            <label class="field-label" for="article-notes">Notes <span class="field-hint-inline">optional</span></label>
            <textarea
              class="field-input"
              id="article-notes"
              name="notes"
              rows="6"
              maxlength="5000"
              placeholder="Jot down what this idea is about, possible angles, references, anything you'd lose otherwise…"><?= $e((string)($article['concept_text'] ?? '')) ?></textarea>
            <p class="field-hint">Notes carry forward to Concept as starting material. Nothing here is published.</p>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" name="action" value="save" class="btn-pri"><?= $e($saveLabel) ?></button>
            <a href="/cms" class="btn-ghost">Cancel</a>
            <button type="submit" name="action" value="advance" class="btn-pri" style="margin-left:auto">Advance to Concept →</button>
          </div>
        </form>

      <?php else: ?>
        <form method="post"
              action="/cms/articles/edit?id=<?= (int)$id ?>"
              class="cms-form cms-form-wide"
              enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="form-grid">
            <div class="form-main">
              <div class="field-group">
                <label class="field-label" for="article-title">Title <span class="field-req">required</span></label>
                <input
                  type="text"
                  class="field-input large"
                  id="article-title"
                  name="title"
                  value="<?= $e((string)($article['title'] ?? '')) ?>"
                  maxlength="500"
                  required>
              </div>

              <div class="field-group">
                <label class="field-label" for="article-slug">Slug <span class="field-req">required</span></label>
                <input
                  type="text"
                  class="field-input"
                  id="article-slug"
                  name="slug"
                  value="<?= $e((string)($article['slug'] ?? '')) ?>"
                  maxlength="200"
                  pattern="[a-z0-9\-]+"
                  required>
                <p class="field-hint">
                  <?php if ($slugPublished): ?>
                    <strong>Warning:</strong> This article is published. Changing the slug
                    will create a 301 redirect from the old URL in Phase 11.
                  <?php else: ?>
                    Lowercase letters, numbers, hyphens. Becomes part of <code>/writing/&lt;slug&gt;</code>.
                  <?php endif; ?>
                </p>
              </div>

              <div class="field-group">
                <label class="field-label" for="article-summary">Summary</label>
                <textarea
                  id="article-summary"
                  class="field-input"
                  name="summary"
                  rows="3"
                  maxlength="500"
                  placeholder="One- to two-sentence summary for cards and meta description."><?= $e((string)($article['summary'] ?? '')) ?></textarea>
              </div>

              <div class="field-group">
                <label class="field-label">Body</label>
                <div class="tiptap-wrap">
                  <div class="tiptap-toolbar" id="tiptap-toolbar">
                    <button type="button" data-cmd="bold"        title="Bold"            class="tt-btn"><strong>B</strong></button>
                    <button type="button" data-cmd="italic"      title="Italic"          class="tt-btn"><em>I</em></button>
                    <button type="button" data-cmd="h2"          title="Heading 2"       class="tt-btn">H2</button>
                    <button type="button" data-cmd="h3"          title="Heading 3"       class="tt-btn">H3</button>
                    <button type="button" data-cmd="ul"          title="Bullet list"     class="tt-btn">• List</button>
                    <button type="button" data-cmd="ol"          title="Numbered list"   class="tt-btn">1. List</button>
                    <button type="button" data-cmd="link"        title="Link"            class="tt-btn">Link</button>
                    <button type="button" data-cmd="blockquote"  title="Blockquote"      class="tt-btn">“ Quote</button>
                    <button type="button" data-cmd="code"        title="Inline code"     class="tt-btn">Code</button>
                    <button type="button" data-cmd="muted"       title="Muted word (m)"  class="tt-btn">m</button>
                    <button type="button" data-cmd="image"       title="Insert image"    class="tt-btn">Image</button>
                  </div>
                  <div id="tiptap-editor" class="tiptap-editor"></div>
                  <textarea
                    id="article-body"
                    name="body"
                    rows="20"
                    class="tiptap-fallback"
                    aria-label="Article body (HTML)"><?= $e($bodyInitial) ?></textarea>
                </div>
                <p class="field-hint">
                  The editor strips any HTML outside the toolbar allowlist on save.
                </p>
              </div>
            </div>

            <aside class="form-side">
              <div class="field-group">
                <label class="field-label">Hero image</label>
                <?php $hero = (string)($article['hero_image'] ?? ''); if ($hero !== ''): ?>
                  <div class="hero-preview">
                    <img src="<?= $e($hero) ?>" alt="" loading="lazy">
                    <label class="hero-remove">
                      <input type="checkbox" name="remove_hero" value="1"> Remove
                    </label>
                  </div>
                <?php endif; ?>
                <input type="file" class="field-input field-file" name="hero" accept="image/jpeg,image/png,image/webp,image/gif">
                <p class="field-hint">JPEG, PNG, WebP, GIF · max 5 MB.</p>
              </div>

              <div class="field-group">
                <label class="field-label" for="article-hero-caption">Hero caption</label>
                <input
                  type="text"
                  class="field-input"
                  id="article-hero-caption"
                  name="hero_caption"
                  value="<?= $e((string)($article['hero_caption'] ?? '')) ?>"
                  maxlength="500">
              </div>

              <div class="field-group">
                <label class="field-label" for="article-hero-size">Hero size</label>
                <select class="field-select" id="article-hero-size" name="hero_size">
                  <?php
                  $heroSizeCurrent = (string)($article['hero_size'] ?? 'default');
                  foreach (['default','wide','full'] as $sz):
                  ?>
                    <option value="<?= $sz ?>" <?= $heroSizeCurrent === $sz ? 'selected' : '' ?>><?= ucfirst($sz) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field-group">
                <label class="field-label" for="article-special-tag">Special tag</label>
                <select class="field-select" id="article-special-tag" name="special_tag">
                  <?php
                  $stCurrent = (string)($article['special_tag'] ?? '');
                  $stOptions = ['' => '— None', 'principle' => 'Principle', 'framework' => 'Framework'];
                  foreach ($stOptions as $val => $lbl):
                  ?>
                    <option value="<?= $e((string)$val) ?>" <?= $stCurrent === $val ? 'selected' : '' ?>><?= $e($lbl) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field-group">
                <label class="field-label" for="article-tags">Tags</label>
                <input
                  type="text"
                  class="field-input"
                  id="article-tags"
                  name="tags"
                  value="<?= $e((string)($article['tags'] ?? '')) ?>"
                  maxlength="500"
                  placeholder="comma, separated, list">
                <p class="field-hint">Display only — not used for filtering yet.</p>
              </div>

              <div class="field-group">
                <label class="field-label" for="article-read-time">Read time <span class="field-hint-inline">minutes</span></label>
                <input
                  type="number"
                  class="field-input"
                  id="article-read-time"
                  name="read_time"
                  value="<?= $e((string)($article['read_time'] ?? '')) ?>"
                  min="0"
                  max="120">
              </div>
            </aside>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" name="action" value="save" class="btn-pri"><?= $e($saveLabel) ?></button>
            <a href="/cms/articles" class="btn-ghost">Cancel</a>

            <?php if ($nextStage !== null && $status !== 'draft' && $status !== 'published'): ?>
              <button type="submit" name="action" value="advance" class="btn-pri" style="margin-left:auto">Advance to <?= $e(ucfirst($nextStage)) ?> →</button>
            <?php endif; ?>

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
      <?php endif; ?>

        <form method="post"
              action="/cms/articles/delete?id=<?= (int)$id ?>"
              class="inline-delete danger-zone"
              data-stage="<?= $e($status) ?>"
              data-slug="<?= $e((string)($article['slug'] ?? '')) ?>">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <?php if ($slugPublished): ?>
            <input type="hidden" name="typed_slug" value="">
          <?php endif; ?>
          <button type="submit" class="btn-ghost btn-danger">Delete article</button>
        </form>
      </div>
    </div>
  </main>
</div>

<?php if (!$isIdea): ?>
<script type="module">
  import { setupTiptap } from '/cms/_assets/tiptap-setup.js';
  setupTiptap({
    mount:        document.getElementById('tiptap-editor'),
    fallback:     document.getElementById('article-body'),
    toolbar:      document.getElementById('tiptap-toolbar'),
    uploadUrl:    '/cms/articles/upload-image?id=<?= (int)$id ?>',
    csrfToken:    <?= json_encode($csrf_token, JSON_UNESCAPED_SLASHES) ?>,
  });
</script>
<?php endif; ?>

<script>
  // Move-to-draft (Published → Draft) needs explicit confirmation.
  for (const btn of document.querySelectorAll('[data-confirm-unpublish]')) {
    btn.addEventListener('click', (e) => {
      const ok = window.confirm("Move this article back to draft? It will be removed from the public site immediately.");
      if (!ok) e.preventDefault();
    });
  }

  // Delete confirmation: typed-slug for Published, simple OK otherwise.
  for (const form of document.querySelectorAll('form.inline-delete')) {
    form.addEventListener('submit', (e) => {
      const stage = form.getAttribute('data-stage') || '';
      const slug  = form.getAttribute('data-slug')  || '';
      if (stage === 'published') {
        const typed = window.prompt(
          'Deleting a published article is permanent.\n\n' +
          'Type the slug to confirm:\n\n  ' + slug
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
        if (!window.confirm('Delete this article? This cannot be undone.')) {
          e.preventDefault();
        }
      }
    });
  }
</script>

</body>
</html>
