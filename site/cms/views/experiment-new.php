<?php
/**
 * cms/views/experiment-new.php — create a new Experiment (Draft).
 *
 * Minimal form: template (required — pick variant up front) + title +
 * optional slug. Everything else lands on the edit screen.
 *
 * Template choice is captured at creation because the rest of the edit
 * UI branches on it: article-format gets a Tiptap body, html-import gets
 * a Custom HTML folder picker.
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

// Phase 20.3: template is always 'experiment' for experiment rows; the
// variant is encoded in body_mode (rtf | html-body | html-swap).
const EXPERIMENT_BODY_MODES = ['rtf', 'html-body', 'html-swap'];

$errors = [];
$form   = [
    'title'     => '',
    'slug'      => '',
    'body_mode' => 'rtf',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $form['title']     = trim((string)($_POST['title']     ?? ''));
        $form['slug']      = trim((string)($_POST['slug']      ?? ''));
        $form['body_mode'] = trim((string)($_POST['body_mode'] ?? 'rtf'));

        if (!in_array($form['body_mode'], EXPERIMENT_BODY_MODES, true)) {
            $errors[] = 'Pick a valid body source.';
        }
        if ($form['title'] === '') {
            $errors[] = 'Title is required.';
        }

        $slug = $form['slug'] !== '' ? slugify($form['slug']) : slugify($form['title']);
        if ($slug === '') {
            $errors[] = 'Slug could not be generated — provide a title or slug containing letters or numbers.';
        }

        if (count($errors) === 0) {
            $slug = unique_slug($slug);
            $id   = save_experiment([
                'title'     => $form['title'],
                'slug'      => $slug,
                'status'    => 'draft',
                'template'  => 'experiment',
                'body_mode' => $form['body_mode'],
            ]);

            header('Location: /cms/experiments/edit?id=' . $id . '&from=experiments&flash=' . rawurlencode('Draft created.'));
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
<title>New Experiment — alexmchong.ca CMS</title>
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
$breadcrumb = 'Experiments → New';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'experiments';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-experiment-new">
      <?php
      $title    = 'New experiment';
      $subtitle = 'Pick a template, give it a title and slug. Body / folder picker live on the edit screen.';
      $actions  = '<a href="/cms/experiments" class="btn-sec">Cancel</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php
        $heading = "Couldn’t save:";
        require __DIR__ . '/../partials/form-errors.php';
        ?>

        <form method="post" action="/cms/experiments/new" class="cms-form reveal-page">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="field-group">
            <label class="field-label" for="ex-body-mode">Body source <span class="field-req">required</span></label>
            <select class="field-select" id="ex-body-mode" name="body_mode" style="max-width:320px">
              <option value="rtf"       <?= $form['body_mode'] === 'rtf'       ? 'selected' : '' ?>>Rich text body (article-format)</option>
              <option value="html-body" <?= $form['body_mode'] === 'html-body' ? 'selected' : '' ?>>HTML body file (article chrome + html file)</option>
              <option value="html-swap" <?= $form['body_mode'] === 'html-swap' ? 'selected' : '' ?>>HTML swap (full passthrough, no template)</option>
            </select>
            <p class="field-hint">
              <strong>Rich text</strong> uses the TipTap editor (same blocks as Articles).
              <strong>HTML body</strong> keeps the article chrome and replaces the body slot with a hand-built file from <code>/content/experiment/&lt;slug&gt;/</code>.
              <strong>HTML swap</strong> serves the file directly with no template wrapper.
              You can switch between modes later from the edit screen.
            </p>
          </div>

          <div class="field-group">
            <label class="field-label" for="ex-title">Experiment Title <span class="field-req">required</span></label>
            <input
              type="text"
              class="field-input large"
              id="ex-title"
              name="title"
              value="<?= $e($form['title']) ?>"
              maxlength="500"
              required
              autofocus>
            <p class="field-hint">e.g. "Decision scaffolding tool". Shown in /experiments/ and on the public page.</p>
          </div>

          <div class="field-group">
            <label class="field-label" for="ex-slug">Slug <span class="field-hint-inline">optional</span></label>
            <input
              type="text"
              class="field-input"
              id="ex-slug"
              name="slug"
              value="<?= $e($form['slug']) ?>"
              maxlength="200"
              pattern="[a-z0-9\-]*"
              placeholder="auto-from-title">
            <p class="field-hint">
              Becomes part of <code>/experiments/&lt;slug&gt;</code>. Permanent once published.
            </p>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-pri">Create draft</button>
            <a href="/cms/experiments" class="btn-sec">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

</body>
</html>
