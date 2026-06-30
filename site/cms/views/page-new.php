<?php
/**
 * cms/views/page-new.php — Create a new marketing page.
 *
 * Writes two files into the source tree:
 *   site/_pages/<slug>.php        — thin assembler (sets $title + $body)
 *   site/_pages/_bodies/<slug>.html — empty body stub
 *
 * After creation, redirects to the page editor so the author can edit
 * the body as a mock and eventually Publish to file.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/pages.php';

Auth::require_login();
$csrf_token = Csrf::token();

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $slug  = strtolower(trim((string)($_POST['slug'] ?? '')));
        $title = trim((string)($_POST['title'] ?? ''));

        // Validate slug.
        if ($slug === '') {
            $errors[] = 'Slug is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9-]*$/', $slug)) {
            $errors[] = 'Slug must start with a letter and contain only lowercase letters, numbers, and hyphens.';
        } elseif (strlen($slug) > 80) {
            $errors[] = 'Slug must be 80 characters or fewer.';
        } elseif (find_page_file($slug) !== null) {
            $errors[] = "A page with slug \"{$slug}\" already exists.";
        }

        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if (empty($errors)) {
            $pages_root  = _pages_root();
            $assembler   = $pages_root . '/' . $slug . '.php';
            $body_file   = $pages_root . '/_bodies/' . $slug . '.html';

            $assembler_content = "<?php\n\$title = " . var_export($title, true) . ";\n\$body  = " . var_export($slug, true) . ";\nrequire __DIR__ . '/_layout/_page-shell.php';\n";
            $body_content      = "<main class=\"page-body\">\n\n</main>\n";

            if (file_put_contents($assembler, $assembler_content) === false) {
                $errors[] = 'Could not write assembler file. Check server write permissions.';
            } elseif (file_put_contents($body_file, $body_content) === false) {
                @unlink($assembler);
                $errors[] = 'Could not write body file. Check server write permissions.';
            } else {
                // Register as active (upsert — handles edge case of a restored slug).
                restore_page($slug);
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=body&flash=' . rawurlencode('Page created.'));
                exit;
            }
        }
    }
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$slug_val  = (string)($_POST['slug']  ?? '');
$title_val = (string)($_POST['title'] ?? '');
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>New page — alexmchong.ca CMS</title>
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
$breadcrumb = 'Pages → New page';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'pages';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-page-new">
      <?php
      $title    = 'New page';
      $subtitle = 'Creates the assembler and an empty body file. Edit the body as a mock, then Publish to file and deploy.';
      $actions  = '<a href="/cms/pages" class="btn-sec">← Back to Pages</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php
        $heading = "Couldn't create page:";
        require __DIR__ . '/../partials/form-errors.php';
        ?>

        <form method="post" action="/cms/pages/new" class="reveal-page">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">

          <div class="content-block">
            <div class="content-block-header">
              <span class="content-block-label">Page details</span>
            </div>

            <div class="field-group">
              <label class="field-label" for="pn-slug">Slug <span class="field-req">required</span></label>
              <input id="pn-slug" class="field-input" type="text" name="slug" value="<?= $e($slug_val) ?>"
                placeholder="e.g. speaking" maxlength="80"
                pattern="[a-z][a-z0-9\-]*"
                autocomplete="off" spellcheck="false"
                oninput="document.getElementById('pn-url-preview').textContent = '/' + (this.value || 'slug') + '/'">
              <div class="field-hint" id="pn-url-preview">/<?= $e($slug_val ?: 'slug') ?>/</div>
              <div class="field-hint">Lowercase letters, numbers, hyphens only. Permanent once deployed.</div>
            </div>

            <div class="field-group">
              <label class="field-label" for="pn-title">Page title <span class="field-req">required</span></label>
              <input id="pn-title" class="field-input large" type="text" name="title" value="<?= $e($title_val) ?>"
                placeholder="e.g. Speaking" maxlength="120">
              <div class="field-hint">Used as the browser tab title — can be overridden via page metadata later.</div>
            </div>
          </div>

          <div class="form-actions form-actions-sticky">
            <button type="submit" class="btn-pri">Create page →</button>
            <a href="/cms/pages" class="btn-sec">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>

</body>
</html>
