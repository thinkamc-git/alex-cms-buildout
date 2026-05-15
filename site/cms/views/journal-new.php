<?php
/**
 * cms/views/journal-new.php — create a new Journal (Draft stage).
 *
 * Mirrors article-new.php. The "+ New Journal" path skips Idea / Concept /
 * Outline and creates directly at Draft per CMS-STRUCTURE.md §15.
 *
 * Minimal form: title (working internal title, required) → slug auto-derives.
 * The Key Statement and Body fields appear on the edit screen after save.
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
$form   = ['title' => '', 'slug' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $form['title'] = trim((string)($_POST['title'] ?? ''));
        $form['slug']  = trim((string)($_POST['slug']  ?? ''));

        if ($form['title'] === '') {
            $errors[] = 'Working title is required.';
        }

        $slug = $form['slug'] !== '' ? slugify($form['slug']) : slugify($form['title']);
        if ($slug === '') {
            $errors[] = 'Slug could not be generated — provide a title or slug containing letters or numbers.';
        }

        if (count($errors) === 0) {
            $slug = unique_slug($slug);
            $id   = save_journal([
                'title'    => $form['title'],
                'slug'     => $slug,
                'status'   => 'draft',
                'template' => 'journal-entry',
            ]);

            header('Location: /cms/journals/edit?id=' . $id . '&flash=' . rawurlencode('Draft created — write your Key Statement.'));
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
<title>New Journal — alexmchong.ca CMS</title>
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
$breadcrumb = 'Journals → New';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'journals';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-journal-new">
      <?php
      $title    = 'New journal';
      $subtitle = 'Set a working title and slug. The Key Statement and body are on the next screen.';
      $actions  = '<a href="/cms/journals" class="btn-ghost">Cancel</a>';
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

        <form method="post" action="/cms/journals/new" class="cms-form">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="field-group">
            <label class="field-label" for="journal-title">Working title <span class="field-req">required</span></label>
            <input
              type="text"
              class="field-input large"
              id="journal-title"
              name="title"
              value="<?= $e($form['title']) ?>"
              maxlength="500"
              required
              autofocus>
            <p class="field-hint">Internal label so you can find this row in lists. Not rendered on the public page — the Key Statement is.</p>
          </div>

          <div class="field-group">
            <label class="field-label" for="journal-slug">Slug <span class="field-hint-inline">optional</span></label>
            <input
              type="text"
              class="field-input"
              id="journal-slug"
              name="slug"
              value="<?= $e($form['slug']) ?>"
              maxlength="200"
              pattern="[a-z0-9\-]*"
              placeholder="auto-from-title">
            <p class="field-hint">
              Becomes part of <code>/journal/&lt;slug&gt;</code>. Permanent once published.
            </p>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-pri">Create draft</button>
            <a href="/cms/journals" class="btn-ghost">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

</body>
</html>
