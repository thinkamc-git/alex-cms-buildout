<?php
/**
 * cms/views/article-edit.php — edit an existing Article (Draft stage).
 *
 * Routed from site/index.php:
 *   GET  /cms/articles/edit?id=N — render the form
 *   POST /cms/articles/edit?id=N — validate, sanitize body, save
 *
 * Full editor surface for Phase 6a:
 *   - title, slug (editable with warning), body (Tiptap), summary
 *   - hero image (upload), hero caption, hero size
 *   - special_tag (none / principle / framework), tags, read_time
 *
 * Stage transitions (Draft → Published) land in Phase 7. For now the
 * Save button keeps status = 'draft' regardless.
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
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

        // Title is required even at Draft (slug derives from it on create).
        if ($post['title'] === '') {
            $errors[] = 'Title is required.';
        }

        // Slug — required, must be a valid slug, must be unique (excluding self).
        $slug = $post['slug'] !== '' ? slugify($post['slug']) : slugify($post['title']);
        if ($slug === '') {
            $errors[] = 'Slug is required.';
        } else {
            $slug = unique_slug($slug, $id);
        }

        // Special tag — empty or one of the enum values.
        $specialTag = $post['special_tag'];
        if (!in_array($specialTag, ['', 'principle', 'framework'], true)) {
            $errors[] = 'Special tag must be empty, principle, or framework.';
            $specialTag = '';
        }
        $specialTagDb = $specialTag === '' ? null : $specialTag;

        // Hero size — must be one of the enum values.
        $heroSize = in_array($post['hero_size'], ['default', 'wide', 'full'], true)
            ? $post['hero_size']
            : 'default';

        // Read time — empty or positive integer.
        $readTime = null;
        if ($post['read_time'] !== '') {
            if (!ctype_digit($post['read_time'])) {
                $errors[] = 'Read time must be a whole number of minutes.';
            } else {
                $readTime = (int)$post['read_time'];
            }
        }

        // Body — sanitize through allowlist.
        $bodyClean = sanitize_html($post['body_raw']);

        // Hero image upload (optional).
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
            save_article([
                'id'           => $id,
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
            ]);

            header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode('Saved.'));
            exit;
        }

        // Errors path — reflect submitted values back into $article so the
        // form re-renders what the author just typed.
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

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$status = (string)($article['status'] ?? 'draft');
$slugPublished = $status === 'published';

// For Tiptap initialization we hand JS the raw body via a hidden input.
$bodyInitial = (string)($article['body'] ?? '');
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
<link rel="stylesheet" href="/cms/_assets/tiptap.css">
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
      $title    = (string)($article['title'] ?? 'Untitled');
      if ($title === '') $title = 'Untitled';
      $subtitle = 'Draft · last saved ' . $e((string)($article['updated_at'] ?? ''));
      $actions  = '<a href="/cms/articles" class="btn-ghost">Back to list</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

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
            <button type="submit" class="btn-pri">Save draft</button>
            <a href="/cms/articles" class="btn-ghost">Cancel</a>
          </div>
        </form>

        <form method="post"
              action="/cms/articles/delete?id=<?= (int)$id ?>"
              class="inline-delete danger-zone"
              data-confirm="Delete this article? This cannot be undone.">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <button type="submit" class="btn-ghost btn-danger">Delete article</button>
        </form>
      </div>
    </div>
  </main>
</div>

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

<script>
  for (const form of document.querySelectorAll('form.inline-delete')) {
    form.addEventListener('submit', (e) => {
      const msg = form.getAttribute('data-confirm') || 'Delete?';
      if (!window.confirm(msg)) e.preventDefault();
    });
  }
</script>

</body>
</html>
