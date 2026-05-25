<?php
/**
 * cms/views/index-new.php — create a new editorial index (Phase 12).
 *
 * Two fields: slug (permanent — becomes the URL) + layout choice. On
 * success, redirects to the edit view so the author can configure title
 * + hero + featured + feed immediately.
 *
 * Slug collisions with the four built-in seeds (writing/journal/...) are
 * caught by unique_index_slug() and surfaced as a validation error.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/indexes.php';
require_once __DIR__ . '/../../lib/content.php';

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();

$errors   = [];
$defaults = ['slug' => '', 'layout' => 'listing', 'title' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $defaults = [
            'slug'   => (string)($_POST['slug']   ?? ''),
            'layout' => (string)($_POST['layout'] ?? 'listing'),
            'title'  => (string)($_POST['title']  ?? ''),
        ];
        $res = save_index($defaults);
        if ($res['ok']) {
            header('Location: /cms/indexes/edit?id=' . $res['id'] . '&flash=' . rawurlencode('Index created — configure below.'));
            exit;
        }
        $errors[] = $res['error'];
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
<title>New Index — alexmchong.ca CMS</title>
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
$breadcrumb = 'Indexes / New';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'indexes';
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-new-index">
      <?php
      $title    = 'New Editorial Index';
      $subtitle = 'Creates a configurable page at a custom URL. Slug is permanent — pick carefully.';
      $actions  = '<a href="/cms/indexes" class="btn-ghost">Cancel</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php if (count($errors) > 0): ?>
          <div class="form-errors" role="alert">
            <strong>Couldn't create:</strong>
            <ul>
              <?php foreach ($errors as $err): ?>
                <li><?= $e($err) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="/cms/indexes/new" class="simple-config" style="max-width:720px">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="field-group">
            <label class="field-label" for="title-input">Title</label>
            <input id="title-input" type="text" name="title" value="<?= $e($defaults['title']) ?>" maxlength="500" placeholder="e.g. Digital Garden"
                   class="field-input" style="width:100%">
            <p class="field-hint">Shown at the top of the index page. Also used to derive the slug if you leave that blank.</p>
          </div>

          <div class="field-group">
            <label class="field-label" for="slug-input">Slug <span class="field-req">required if no title</span></label>
            <div style="display:flex;align-items:center;gap:6px">
              <span style="color:var(--muted);font-family:var(--font-mono);font-size:var(--text-meta)">alexmchong.ca/</span>
              <input id="slug-input" type="text" name="slug" value="<?= $e($defaults['slug']) ?>" maxlength="200" pattern="[a-z0-9\-/]*" placeholder="e.g. digital-garden"
                     class="field-input" style="flex:1;font-family:var(--font-mono)">
              <span style="color:var(--muted);font-family:var(--font-mono);font-size:var(--text-meta)">/</span>
            </div>
            <p class="field-hint">Becomes the URL. Permanent once set. Lowercase letters, numbers, and hyphens only.</p>
          </div>

          <div class="field-group">
            <label class="field-label">Layout</label>
            <div style="display:flex;gap:8px">
              <label style="flex:1;display:block;padding:14px;border:1px solid var(--ink-18);border-radius:4px;cursor:pointer;background:<?= $defaults['layout'] === 'listing' ? 'var(--canvas-raised)' : 'var(--surface)' ?>">
                <input type="radio" name="layout" value="listing" <?= $defaults['layout'] === 'listing' ? 'checked' : '' ?> style="margin-right:8px">
                <strong>Basic Listing</strong>
                <p class="field-hint" style="margin:6px 0 0">Title, optional description, and a content feed. Best for catch-all section indexes (e.g. /writing, /journal).</p>
              </label>
              <label style="flex:1;display:block;padding:14px;border:1px solid var(--ink-18);border-radius:4px;cursor:pointer;background:<?= $defaults['layout'] === 'editorial' ? 'var(--canvas-raised)' : 'var(--surface)' ?>">
                <input type="radio" name="layout" value="editorial" <?= $defaults['layout'] === 'editorial' ? 'checked' : '' ?> style="margin-right:8px">
                <strong>Editorial Page</strong>
                <p class="field-hint" style="margin:6px 0 0">Hero feature + curated picks + content feed. Use for curated landing pages (e.g. /digital-garden).</p>
              </label>
            </div>
          </div>

          <div class="row-btn-group" style="margin-top:var(--space-24)">
            <button type="submit" class="btn-pri">Create Index</button>
            <a href="/cms/indexes" class="btn-ghost">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

</body>
</html>
