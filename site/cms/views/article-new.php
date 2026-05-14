<?php
/**
 * cms/views/article-new.php — create a new Article (Draft stage).
 *
 * Routed from site/index.php:
 *   GET  /cms/articles/new   — render the form
 *   POST /cms/articles/new   — validate, save, redirect to /cms/articles/edit?id=N
 *
 * Minimal form: title (required) → slug auto-generates from title on save.
 * The author can override the slug on this form before save. The full
 * editor (Tiptap body, hero image, summary, tags, etc.) lives on the
 * edit view, which the author lands on immediately after save.
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

$errors = [];
$form   = [
    'title' => '',
    'slug'  => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $form['title'] = trim((string)($_POST['title'] ?? ''));
        $form['slug']  = trim((string)($_POST['slug']  ?? ''));

        if ($form['title'] === '') {
            $errors[] = 'Title is required.';
        }

        // Auto-generate slug if blank.
        $slug = $form['slug'] !== ''
            ? slugify($form['slug'])
            : slugify($form['title']);
        if ($slug === '') {
            $errors[] = 'Slug could not be generated — provide a title or slug containing letters or numbers.';
        }

        if (count($errors) === 0) {
            $slug = unique_slug($slug);
            $id   = save_article([
                'title'  => $form['title'],
                'slug'   => $slug,
                'status' => 'draft',
            ]);

            // POST-then-redirect.
            header('Location: /cms/articles/edit?id=' . $id . '&flash=' . rawurlencode('Draft created — keep going.'));
            exit;
        }
    }
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>New Article — alexmchong.ca CMS</title>
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
$breadcrumb = 'Articles → New';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'articles';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-article-new">
      <?php
      $title    = 'New article';
      $subtitle = 'Set a title and slug. You can write the body, add a hero, and edit metadata on the next screen.';
      $actions  = '<a href="/cms/articles" class="btn-ghost">Cancel</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

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

        <form method="post" action="/cms/articles/new" class="cms-form">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="field-group">
            <label class="field-label" for="article-title">Title <span class="field-req">required</span></label>
            <input
              type="text"
              class="field-input large"
              id="article-title"
              name="title"
              value="<?= $e($form['title']) ?>"
              maxlength="500"
              required
              autofocus>
            <p class="field-hint">Used to auto-generate the slug if you leave it blank.</p>
          </div>

          <div class="field-group">
            <label class="field-label" for="article-slug">Slug <span class="field-hint-inline">optional</span></label>
            <input
              type="text"
              class="field-input"
              id="article-slug"
              name="slug"
              value="<?= $e($form['slug']) ?>"
              maxlength="200"
              pattern="[a-z0-9\-]*"
              placeholder="auto-from-title">
            <p class="field-hint">
              Lowercase letters, numbers, hyphens. The slug becomes part of the public URL
              (<code>/writing/&lt;slug&gt;</code>) and is permanent once published.
            </p>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-pri">Create draft</button>
            <a href="/cms/articles" class="btn-ghost">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

</body>
</html>
