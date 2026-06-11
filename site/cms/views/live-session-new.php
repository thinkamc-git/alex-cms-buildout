<?php
/**
 * cms/views/live-session-new.php — create a new Live Session (Draft).
 *
 * Mirrors journal-new.php. Minimal form: title (required) → slug
 * auto-derives. Event details, format pills, body land on the edit screen.
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
            $errors[] = 'Title is required.';
        }

        $slug = $form['slug'] !== '' ? slugify($form['slug']) : slugify($form['title']);
        if ($slug === '') {
            $errors[] = 'Slug could not be generated — provide a title or slug containing letters or numbers.';
        }

        if (count($errors) === 0) {
            $slug = unique_slug($slug);
            $id   = save_live_session([
                'title'    => $form['title'],
                'slug'     => $slug,
                'status'   => 'draft',
                'template' => 'live-session',
            ]);

            header('Location: /cms/live-sessions/edit?id=' . $id . '&from=live-sessions&flash=' . rawurlencode('Draft created — add the event details.'));
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
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>New Live Session — alexmchong.ca CMS</title>
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
</head>
<body>

<?php
$breadcrumb = 'Live Sessions → New';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'live-sessions';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-live-session-new">
      <?php
      $title    = 'New live session';
      $subtitle = 'Set a title and slug. Event details, format pills, and body are on the next screen.';
      $actions  = '<a href="/cms/live-sessions" class="btn-sec">Cancel</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php
        $heading = "Couldn’t save:";
        require __DIR__ . '/../partials/form-errors.php';
        ?>

        <form method="post" action="/cms/live-sessions/new" class="cms-form reveal-page">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="field-group">
            <label class="field-label" for="ls-title">Title <span class="field-req">required</span></label>
            <input
              type="text"
              class="field-input large"
              id="ls-title"
              name="title"
              value="<?= $e($form['title']) ?>"
              maxlength="500"
              required
              autofocus>
            <p class="field-hint">e.g. "Designing for Human Agency". Shown on the public page and in /live-sessions/ listings.</p>
          </div>

          <div class="field-group">
            <label class="field-label" for="ls-slug">Slug <span class="field-hint-inline">optional</span></label>
            <input
              type="text"
              class="field-input"
              id="ls-slug"
              name="slug"
              value="<?= $e($form['slug']) ?>"
              maxlength="200"
              pattern="[a-z0-9\-]*"
              placeholder="auto-from-title">
            <p class="field-hint">
              Becomes part of <code>/live-sessions/&lt;slug&gt;</code>. Permanent once published.
            </p>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-pri">Create draft</button>
            <a href="/cms/live-sessions" class="btn-sec">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

</body>
</html>
