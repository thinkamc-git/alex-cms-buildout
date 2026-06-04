<?php
/**
 * cms/views/page-edit.php — Pages editor (Phase 20).
 *
 * Matches the article-edit chrome (topbar breadcrumb + view-header with
 * actions slot + content-block sections + btn-pri/ghost). One view, two
 * modes:
 *   ?slug=<slug>                — Live Version (file content, read-only)
 *   ?slug=<slug>&version_id=<n> — A mock (editable)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/pages.php';

Auth::require_login();
$csrf_token = Csrf::token();

$errors = [];
$flash  = '';

$slug = trim((string)($_GET['slug'] ?? $_POST['slug'] ?? ''));
if ($slug === '') {
    header('Location: /cms/pages');
    exit;
}

$file_row = find_page_file($slug);
if ($file_row === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unknown page slug: $slug\n";
    return;
}

$is_partial     = $file_row['kind'] === 'partial';
$is_publishable = in_array($slug, PAGES_PUBLISHABLE_SLUGS, true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);

        if ($action === 'create_mock') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                $errors[] = 'A name is required.';
            } else {
                $seed   = read_page_file($slug) ?? '';
                $new_id = create_page_mock($slug, $name, $seed);
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=body&version_id=' . $new_id . '&flash=' . rawurlencode('Mock created.'));
                exit;
            }
        } elseif ($action === 'save_mock') {
            // Mocks only carry body content; metadata is page-level now.
            $body = (string)($_POST['body_html'] ?? '');
            update_page_mock($id, $body);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=body&version_id=' . $id . '&flash=' . rawurlencode('Saved.'));
            exit;
        } elseif ($action === 'save_metadata') {
            // Slug-level metadata. No version concept, no mock needed.
            upsert_page_metadata($slug, [
                'meta_title'       => trim((string)($_POST['meta_title'] ?? '')) ?: null,
                'meta_description' => trim((string)($_POST['meta_description'] ?? '')) ?: null,
                'og_image'         => trim((string)($_POST['og_image'] ?? '')) ?: null,
                'og_type'          => trim((string)($_POST['og_type'] ?? 'website')),
                'twitter_card'     => trim((string)($_POST['twitter_card'] ?? 'summary_large_image')),
            ]);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&flash=' . rawurlencode('Metadata saved.'));
            exit;
        } elseif ($action === 'rename_mock') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                $errors[] = 'A name is required.';
            } else {
                rename_page_mock($id, $name);
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=body&version_id=' . $id . '&flash=' . rawurlencode('Renamed.'));
                exit;
            }
        } elseif ($action === 'duplicate_mock') {
            $new_name = trim((string)($_POST['new_name'] ?? ''));
            if ($new_name === '') {
                $errors[] = 'A name is required for the duplicate.';
            } else {
                $new_id = duplicate_page_mock($id, $new_name);
                if ($new_id === null) {
                    $errors[] = 'Could not duplicate.';
                } else {
                    header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=body&version_id=' . $new_id . '&flash=' . rawurlencode('Duplicated.'));
                    exit;
                }
            }
        } elseif ($action === 'delete_mock') {
            delete_page_mock($id);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=body&flash=' . rawurlencode('Mock deleted.'));
            exit;
        } elseif ($action === 'publish_mock') {
            if (publish_page_mock($id)) {
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=body&version_id=' . $id . '&flash=' . rawurlencode('Published — this mock is now live on staging.'));
                exit;
            }
            $errors[] = 'Could not publish (publishing is only available for header / footer partials).';
        } elseif ($action === 'unpublish_mock') {
            unpublish_page_mock($id);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=body&version_id=' . $id . '&flash=' . rawurlencode('Un-published — fell back to file.'));
            exit;
        } elseif ($action === 'revert_to_file') {
            unpublish_all_for_slug($slug);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=body&flash=' . rawurlencode('Reverted to file.'));
            exit;
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// Load state for the chosen version (body) and the slug-level metadata.
$mocks      = list_page_mocks($slug);
$version_id = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 0;

$current_mock = null;
$current_body = '';
$is_live      = ($version_id === 0);

if ($is_live) {
    $current_body = read_page_file($slug) ?? '';
} else {
    foreach ($mocks as $m) {
        if ((int)$m['id'] === $version_id) {
            $current_mock = $m;
            $current_body = (string)$m['body_html'];
            break;
        }
    }
    if ($current_mock === null) {
        header('Location: /cms/pages/edit?slug=' . rawurlencode($slug));
        exit;
    }
}

$published_mock = null;
foreach ($mocks as $m) {
    if ((int)$m['is_published'] === 1) { $published_mock = $m; break; }
}

// Page-level metadata. Falls back to parsed file values for meta_title /
// meta_description so the form pre-populates with something sensible on
// first visit; user can save to commit to page_metadata.
$saved_meta = get_page_metadata($slug);
$file_body  = read_page_file($slug) ?? '';
$file_title = preg_match('/\$title\s*=\s*[\'"]([^\'"]+)[\'"]/', $file_body, $_tm) ? $_tm[1] : null;
$file_desc  = preg_match('/\$description\s*=\s*[\'"]([^\'"]+)[\'"]/', $file_body, $_dm) ? $_dm[1] : null;
$current_meta = [
    'meta_title'       => $saved_meta['meta_title']       ?? $file_title,
    'meta_description' => $saved_meta['meta_description'] ?? $file_desc,
    'og_image'         => $saved_meta['og_image']         ?? null,
    'og_type'          => $saved_meta['og_type']          ?? 'website',
    'twitter_card'     => $saved_meta['twitter_card']     ?? 'summary_large_image',
];

// Default tab is metadata (the always-editable side); partials don't have
// metadata so they default to body and the metadata tab is hidden.
// Phase 20.2: 'preview' added as a third tab, available whenever a mock
// exists (the public-side mock-preview URL needs a version id).
$default_tab = $is_partial ? 'body' : 'metadata';
$tab = (string)($_GET['tab'] ?? $default_tab);
if (!in_array($tab, ['body', 'metadata', 'preview'], true)) $tab = $default_tab;
if ($is_partial && $tab === 'metadata') $tab = 'body';

// Preview URL: partials preview against /about/; pages preview against themselves.
$preview_url      = '';
if (!$is_live && $current_mock !== null) {
    $preview_url = $is_partial
        ? '/about/?_preview=' . (int)$current_mock['id']
        : '/' . rawurlencode($slug) . '/?_preview=' . (int)$current_mock['id'];
}
$preview_live_url = $is_partial ? '/about/' : ('/' . rawurlencode($slug) . '/');

// Phase 21.x: Preview tab is always visible — no longer appears/disappears
// based on mock presence (that was confusing UX). In Live mode the iframe
// just points at the live URL; in Mock mode it points at the mock-preview
// URL and gets overridden by form-driven POSTs from preview-tab-guard.js.
$preview_iframe_src = $preview_url !== '' ? $preview_url : $preview_live_url;
$preview_tab_available = true;

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e  = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$rel_time = static function (string $ts) use ($e): string {
    if (!$ts) return '';
    $epoch = strtotime($ts);
    if (!$epoch) return '';
    $diff = time() - $epoch;
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return intdiv($diff, 60)   . 'min ago';
    if ($diff < 86400) return intdiv($diff, 3600) . 'hr ago';
    if ($diff < 86400*30) return intdiv($diff, 86400) . 'd ago';
    return date('Y-m-d', $epoch);
};

$_mock_name_js = $current_mock ? (string)$current_mock['name'] : '';
// CodeMirror mode key — 'html' for marketing-page body files, '' (default
// = PHP) for partials and 404. Threaded onto the textarea via data-mode.
$_editor_mode  = (substr((string)$file_row['filename'], -5) === '.html') ? 'html' : 'php';
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title><?= $e($file_row['filename']) ?> — alexmchong.ca CMS</title>
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
<link rel="stylesheet" href="/cms/_assets/codemirror/codemirror.min.css">
<style>
  /* Editor-specific: tabs row, version selector row, CodeMirror sizing,
     metadata form. All other elements use existing CMS conventions. */
  /* .pe-tabs / .pe-tab definitions moved to style-cms.css (Batch 2 #14). */
  .pe-version-row { display:flex; gap:var(--space-12); align-items:center; padding:var(--space-12) var(--space-16); background:var(--bg-soft); border:1px solid var(--border); border-radius:4px; margin-bottom:var(--space-16); flex-wrap:wrap; }
  .pe-version-label { font-family:var(--font-cond); font-size:var(--text-micro); font-weight:700; letter-spacing:0.10em; text-transform:uppercase; color:var(--muted); }
  .pe-version-row select { padding:6px 10px; border:1px solid var(--border); border-radius:4px; font-size:13px; background:var(--surface); color:var(--ink); min-width:280px; font-family:var(--font-cond); }
  .pe-version-row .pe-version-actions { margin-left:auto; display:flex; gap:var(--space-8); flex-wrap:wrap; align-items:center; }
  .pe-override-note { font-family:var(--font-mono); font-size:var(--text-micro); color:var(--muted); }
  .pe-override-note strong { color:var(--ink); font-weight:600; }
  .pe-unsaved { color:var(--c-terracotta); font-size:var(--text-micro); display:none; font-family:var(--font-mono); }
  .pe-unsaved.is-visible { display:inline; }
  /* .pe-readonly-notice rule moved to style-cms.css as .readonly-notice
     (Batch 2 #28); kept as alias in the central sheet. */
  .CodeMirror { border:1px solid var(--border); border-radius:4px; font-size:13px; height:560px; }
  .pe-meta-form { display:grid; grid-template-columns: 200px 1fr; gap:var(--space-12) var(--space-16); align-items:start; max-width:880px; margin-bottom:var(--space-24); }
  .pe-meta-form label { font-size:var(--text-micro); color:var(--muted); padding-top:8px; font-family:var(--font-cond); text-transform:uppercase; letter-spacing:0.08em; font-weight:600; }
  .pe-meta-form input[type=text], .pe-meta-form textarea, .pe-meta-form select { width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:4px; font-size:13px; background:var(--surface); color:var(--ink); font-family:var(--font-mono); }
  .pe-meta-form textarea { min-height:80px; resize:vertical; }
  /* Client-side tab visibility — toggled by preview-tab-guard.js. */
  .is-hidden-tab { display: none !important; }
  .pe-meta-charcount { font-size:11px; color:var(--muted); padding-top:4px; }
  .pe-unfurl { max-width:520px; border:1px solid var(--border); border-radius:6px; overflow:hidden; background:var(--surface); }
  .pe-unfurl-img { width:100%; height:180px; background:var(--bg-soft) center/cover no-repeat; }
  .pe-unfurl-body { padding:var(--space-12) var(--space-16); }
  .pe-unfurl-title { font-weight:600; color:var(--ink); font-size:14px; margin-bottom:4px; }
  .pe-unfurl-desc  { color:var(--muted); font-size:12px; line-height:1.4; }
  .pe-inline-form { display:inline; }
  /* Uniform button dimensions inside the page-edit chrome — btn-sec and
     btn-pri only differ in colour, not size, so dirty-flips don't jump. */
  .pe-version-actions .btn-sec, .pe-version-actions .btn-pri,
  .pe-version-actions a.btn-sec, .pe-version-actions a.btn-pri, a.pe-btn,
  .pe-meta-form .btn-sec, .pe-meta-form .btn-pri {
    padding: 7px 16px;
    font-family: var(--font-cond);
    font-size: var(--text-label);
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    line-height: 1;
    border-radius: var(--r-pill);
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
</style>
</head>
<body>

<?php
$breadcrumb = 'Pages → ' . $file_row['filename'];
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'pages';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-page-edit">
      <?php
      $title    = $file_row['filename'];
      $subtitle = $is_partial
        ? 'Layout partial. Publish a mock to override the file on staging — the file stays canonical until you do.'
        : 'Marketing page. Edit as mocks for preview — the CMS never writes to disk, so the file stays canonical.';
      $actions  = '<a href="/cms/pages" class="btn-sec">← Back to Pages</a>';
      if (!$is_partial && $slug !== '') {
          $actions .= ' <a href="/' . $e($slug) . '/" target="_blank" rel="noopener" class="btn-sec">Live ↗</a>';
      }
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <!-- Tabs — anchored flush below the view-header (positioned OUTSIDE
           .content-area like the filter rail in articles.php so they sit at
           the top-left edge with no padding band around them). -->
      <div class="pe-tabs" role="tablist">
        <?php if (!$is_partial): ?>
          <a class="pe-tab <?= $tab === 'metadata' ? 'is-active' : '' ?>" data-tab-target="metadata" role="tab" aria-selected="<?= $tab === 'metadata' ? 'true' : 'false' ?>" href="?slug=<?= rawurlencode($slug) ?>&tab=metadata">Metadata</a>
        <?php endif; ?>
        <a class="pe-tab <?= $tab === 'body' ? 'is-active' : '' ?>" data-tab-target="body" role="tab" aria-selected="<?= $tab === 'body' ? 'true' : 'false' ?>" href="?slug=<?= rawurlencode($slug) ?>&tab=body<?= !$is_live ? '&version_id=' . $version_id : '' ?>">Body HTML</a>
        <?php if ($preview_tab_available): ?>
          <a class="pe-tab <?= $tab === 'preview' ? 'is-active' : '' ?>" data-tab-target="preview" role="tab" aria-selected="<?= $tab === 'preview' ? 'true' : 'false' ?>" href="?slug=<?= rawurlencode($slug) ?>&tab=preview<?= !$is_live ? '&version_id=' . $version_id : '' ?>">Preview</a>
        <?php endif; ?>
      </div>

      <div class="content-area">
        <?php
        $heading = "Couldn't save:";
        require __DIR__ . '/../partials/form-errors.php';
        require __DIR__ . '/../partials/flash.php';
        ?>

        <!-- BODY panel — always in the DOM, hidden by .is-hidden-tab when
             another tab is active. Same for Metadata and Preview below. -->
        <div data-tab-panel="body" class="<?= $tab !== 'body' ? 'is-hidden-tab' : '' ?>">
          <!-- Version selector + actions (only in the Body HTML tab) -->
          <div class="pe-version-row">
            <span class="pe-version-label">Version:</span>
            <select id="pe-version-select" onchange="peSwitchVersion(this.value)">
              <option value="0"<?= $is_live ? ' selected' : '' ?>>Live Version (file on disk)</option>
              <?php foreach ($mocks as $m): ?>
                <option value="<?= (int)$m['id'] ?>"<?= ((int)$m['id'] === $version_id) ? ' selected' : '' ?>>
                  <?= $e($m['name']) ?> [ <?= $e($rel_time((string)$m['updated_at'])) ?> ]<?= ((int)$m['is_published'] === 1) ? ' [LIVE]' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>

            <?php if ($is_live && $published_mock !== null): ?>
              <span class="pe-override-note">↪ Override active: <strong><?= $e($published_mock['name']) ?></strong></span>
            <?php endif; ?>
            <span class="pe-unsaved" id="pe-unsaved">(unsaved changes)</span>

            <div class="pe-version-actions">
              <?php if ($is_live): ?>
                <a class="btn-sec" href="<?= $e($preview_live_url) ?>" target="_blank" rel="noopener">Preview Live ↗</a>
                <?php if ($published_mock !== null && $is_publishable): ?>
                  <form class="pe-inline-form" method="post" action="/cms/pages/edit" data-confirm="Revert to file? This un-publishes the active mock and falls back to the on-disk file.">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                    <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                    <input type="hidden" name="action" value="revert_to_file">
                    <button type="submit" class="btn-sec btn-danger">Revert to file</button>
                  </form>
                <?php endif; ?>
                <button type="button" class="btn-pri" onclick="peCreateMock()">+ New Mock</button>
              <?php else: ?>
                <button type="button" class="btn-sec" onclick="peRenameMock()">Rename</button>
                <button type="button" class="btn-sec" onclick="peDuplicateMock()">Duplicate</button>
                <a class="btn-sec" href="<?= $e($preview_url) ?>" target="_blank" rel="noopener">Preview ↗</a>
                <form class="pe-inline-form" method="post" action="/cms/pages/edit" data-confirm="Delete mock &quot;<?= $e($current_mock['name']) ?>&quot;? This can&#039;t be undone.">
                  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                  <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
                  <input type="hidden" name="action" value="delete_mock">
                  <button type="submit" class="btn-sec btn-danger">Delete version</button>
                </form>
                <?php if ($is_publishable): ?>
                  <?php if ((int)$current_mock['is_published'] === 1): ?>
                    <form class="pe-inline-form" method="post" action="/cms/pages/edit">
                      <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                      <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                      <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
                      <input type="hidden" name="action" value="unpublish_mock">
                      <button type="submit" class="btn-sec">Un-publish</button>
                    </form>
                  <?php else: ?>
                    <form class="pe-inline-form" method="post" action="/cms/pages/edit" data-confirm="Publish &quot;<?= $e($current_mock['name']) ?>&quot;? This overrides <?= $e($file_row['filename']) ?> on staging until you un-publish or revert.">
                      <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                      <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                      <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
                      <input type="hidden" name="action" value="publish_mock">
                      <button type="submit" class="btn-pri">Publish →</button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($is_live): ?>
            <div class="readonly-notice">This is the on-disk file. The CMS never writes here — click [+ New Mock] to start editing.</div>
            <textarea id="pe-editor-live" readonly data-mode="<?= $e($_editor_mode) ?>"><?= $e($current_body) ?></textarea>
          <?php else: ?>
            <form id="pe-mock-form" method="post" action="/cms/pages/edit" data-preview-source-form>
              <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
              <input type="hidden" name="slug" value="<?= $e($slug) ?>">
              <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
              <input type="hidden" name="action" value="save_mock">
              <textarea id="pe-editor-mock" name="body_html" data-mode="<?= $e($_editor_mode) ?>"><?= $e($current_body) ?></textarea>
            </form>
            <div class="form-actions form-actions-sticky">
              <button type="submit" form="pe-mock-form" class="btn-sec" data-save-btn data-body-save>Save</button>
              <a href="/cms/pages" class="btn-sec">Cancel</a>
            </div>
          <?php endif; ?>
        </div><!-- /data-tab-panel="body" -->

        <?php if ($preview_tab_available): /* PREVIEW panel — only when a mock exists */ ?>
        <div data-tab-panel="preview" class="<?= $tab !== 'preview' ? 'is-hidden-tab' : '' ?>">
          <div class="post-preview-frame" style="margin-top:var(--space-16)">
            <iframe
              name="pe-preview-iframe"
              src="<?= $e($preview_iframe_src) ?>"
              title="Preview · <?= $e($file_row['filename']) ?>"
              class="post-preview-iframe"
              loading="lazy"
              data-preview-iframe
              data-preview-endpoint="/cms/pages/preview-form"></iframe>
          </div>
        </div><!-- /data-tab-panel="preview" -->
        <?php endif; ?>

        <?php if (!$is_partial): /* METADATA panel — pages only, partials don't have meta */ ?>
        <div data-tab-panel="metadata" class="<?= $tab !== 'metadata' ? 'is-hidden-tab' : '' ?>">
          <?php
          // Live mode: form is visible but every field is read-only. The
          // values come from the parsed file ($title / $description) plus
          // Metadata is page-level — always editable, saves to page_metadata,
          // no version concept, no mock required.
          $unfurl_fallback_title = (string)$file_row['filename'];
          ?>
          <form method="post" action="/cms/pages/edit" id="pe-meta-form">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="slug" value="<?= $e($slug) ?>">
            <input type="hidden" name="action" value="save_metadata">

            <div class="pe-meta-form">
              <label for="pe-meta-title">Meta title</label>
              <div>
                <input id="pe-meta-title" type="text" name="meta_title" maxlength="60" value="<?= $e($current_meta['meta_title']) ?>" oninput="document.getElementById('pe-meta-title-count').textContent = this.value.length + ' / 60'; peUnfurlSync();">
                <div class="pe-meta-charcount" id="pe-meta-title-count"><?= strlen((string)$current_meta['meta_title']) ?> / 60</div>
              </div>

              <label for="pe-meta-description">Meta description</label>
              <div>
                <textarea id="pe-meta-description" name="meta_description" maxlength="160" oninput="document.getElementById('pe-meta-desc-count').textContent = this.value.length + ' / 160'; peUnfurlSync();"><?= $e($current_meta['meta_description']) ?></textarea>
                <div class="pe-meta-charcount" id="pe-meta-desc-count"><?= strlen((string)$current_meta['meta_description']) ?> / 160</div>
              </div>

              <label for="pe-og-image">og:image URL</label>
              <div>
                <input id="pe-og-image" type="text" name="og_image" placeholder="/uploads/og/about.jpg" value="<?= $e($current_meta['og_image']) ?>" oninput="peUnfurlSync();">
                <div class="pe-meta-charcount">Recommended 1200×630. Paths starting with / resolve relative to site root.</div>
              </div>

              <label for="pe-og-type">og:type</label>
              <select id="pe-og-type" name="og_type">
                <?php foreach (['website','article','profile'] as $t): ?>
                  <option value="<?= $t ?>"<?= ($current_meta['og_type'] === $t) ? ' selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>

              <label for="pe-twitter-card">twitter:card</label>
              <select id="pe-twitter-card" name="twitter_card">
                <?php foreach (['summary','summary_large_image'] as $t): ?>
                  <option value="<?= $t ?>"<?= ($current_meta['twitter_card'] === $t) ? ' selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
              </select>

            </div>

            <div class="content-block-header"><span class="content-block-label">Unfurl preview</span></div>
            <div class="pe-unfurl">
              <div class="pe-unfurl-img" id="pe-unfurl-img" style="background-image:url('<?= $e($current_meta['og_image']) ?>');"></div>
              <div class="pe-unfurl-body">
                <div class="pe-unfurl-title" id="pe-unfurl-title"><?= $e($current_meta['meta_title'] ?: $unfurl_fallback_title) ?></div>
                <div class="pe-unfurl-desc"  id="pe-unfurl-desc"><?= $e($current_meta['meta_description']) ?></div>
                <div class="pe-meta-charcount" style="margin-top:8px;">alexmchong.ca<?= $is_partial ? '' : ('/' . $e($slug) . '/') ?></div>
              </div>
              </div>

            <div class="form-actions form-actions-sticky">
              <button type="submit" class="btn-sec" data-save-btn>Save metadata</button>
              <a href="/cms/pages" class="btn-sec">Cancel</a>
            </div>
            </form>
        </div><!-- /data-tab-panel="metadata" -->
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<!-- Hidden forms for prompt-driven actions -->
<form id="pe-create-form"    method="post" action="/cms/pages/edit" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
  <input type="hidden" name="action" value="create_mock">
  <input type="hidden" name="name" id="pe-create-name">
</form>
<form id="pe-rename-form"    method="post" action="/cms/pages/edit" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
  <input type="hidden" name="id" value="<?= $current_mock ? (int)$current_mock['id'] : 0 ?>">
  <input type="hidden" name="action" value="rename_mock">
  <input type="hidden" name="name" id="pe-rename-name">
</form>
<form id="pe-duplicate-form" method="post" action="/cms/pages/edit" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
  <input type="hidden" name="id" value="<?= $current_mock ? (int)$current_mock['id'] : 0 ?>">
  <input type="hidden" name="action" value="duplicate_mock">
  <input type="hidden" name="new_name" id="pe-duplicate-name">
</form>

<!-- Client-side tab controller — intercepts the [data-tab-target] clicks
     and POSTs the body form to the preview endpoint when Preview activates. -->
<script src="/cms/_assets/preview-tab-guard.js" defer></script>
<script src="/cms/_assets/dirty-flip.js" defer></script>
<script src="/cms/_assets/codemirror/codemirror.min.js"></script>
<script src="/cms/_assets/codemirror/mode/xml/xml.min.js"></script>
<script src="/cms/_assets/codemirror/mode/javascript/javascript.min.js"></script>
<script src="/cms/_assets/codemirror/mode/css/css.min.js"></script>
<script src="/cms/_assets/codemirror/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="/cms/_assets/codemirror/mode/clike/clike.min.js"></script>
<script src="/cms/_assets/codemirror/mode/php/php.min.js"></script>
<script src="/cms/_assets/codemirror/addon/edit/matchbrackets.min.js"></script>
<script src="/cms/_assets/codemirror/addon/edit/closebrackets.min.js"></script>
<script>
  (function () {
    var ta = document.getElementById('pe-editor-mock') || document.getElementById('pe-editor-live');
    if (!ta) return;
    var isLive = ta.id === 'pe-editor-live';
    // HTML body files use the htmlmixed mode (HTML + inline CSS/JS); PHP
    // partials and the 404 page use the PHP mode so embedded PHP blocks
    // highlight correctly.
    var mode = (ta.dataset.mode === 'html') ? 'htmlmixed' : 'application/x-httpd-php';
    var cm = CodeMirror.fromTextArea(ta, {
      mode: mode,
      lineNumbers: true,
      matchBrackets: true,
      autoCloseBrackets: true,
      indentUnit: 2,
      tabSize: 2,
      readOnly: isLive,
      lineWrapping: false,
    });
    if (!isLive) {
      var initial = cm.getValue();
      cm.on('change', function () {
        var dirty = cm.getValue() !== initial;
        var flag = document.getElementById('pe-unsaved');
        if (flag) flag.classList.toggle('is-visible', dirty);
        // Mirror to the underlying textarea so listeners like
        // preview-tab-guard (which watches input/change) detect this edit.
        // The Body Save button's btn-sec → btn-pri flip is handled by
        // the shared cms/_assets/dirty-flip.js module, which binds to the
        // dispatched 'input' event below.
        ta.value = cm.getValue();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      });
    }
    window._peEditor = cm;

    // Body panel may start hidden (default tab is metadata). CodeMirror
    // initialises to 0×0 when its host is display:none; refresh whenever
    // the panel becomes visible so the editor renders correctly.
    var bodyPanel = document.querySelector('[data-tab-panel="body"]');
    if (bodyPanel) {
      new MutationObserver(function () {
        if (!bodyPanel.classList.contains('is-hidden-tab')) {
          setTimeout(function () { cm.refresh(); }, 0);
        }
      }).observe(bodyPanel, { attributes: true, attributeFilter: ['class'] });
    }
  })();

  function peSwitchVersion(vid) {
    var v = String(vid || '0');
    // The version selector only lives in the Body HTML tab, so always
    // return there after a switch (default tab is now metadata).
    var qs = 'slug=<?= rawurlencode($slug) ?>&tab=body';
    if (v !== '0') qs += '&version_id=' + encodeURIComponent(v);
    location.href = '/cms/pages/edit?' + qs;
  }
  function peCreateMock() {
    var name = prompt('Name this mock (e.g. "Tighter intro")', '');
    if (!name) return;
    document.getElementById('pe-create-name').value = name;
    document.getElementById('pe-create-form').submit();
  }
  function peRenameMock() {
    var current = <?= json_encode($_mock_name_js) ?>;
    var name = prompt('Rename to:', current);
    if (!name || name === current) return;
    document.getElementById('pe-rename-name').value = name;
    document.getElementById('pe-rename-form').submit();
  }
  function peDuplicateMock() {
    var base = <?= json_encode($_mock_name_js) ?>;
    var name = prompt('Name the duplicate:', base + ' (copy)');
    if (!name) return;
    document.getElementById('pe-duplicate-name').value = name;
    document.getElementById('pe-duplicate-form').submit();
  }
  // Save-button dirty-flip (ghost → primary on first edit) is handled by
  // the shared cms/_assets/dirty-flip.js module loaded sitewide on
  // page-edit. Both the Body Save (mock body form) and the Save metadata
  // button carry data-save-btn; the module finds each one and resolves
  // its owning form via the form= attribute or closest <form>.

  function peUnfurlSync() {
    var t  = document.getElementById('pe-meta-title');
    var d  = document.getElementById('pe-meta-description');
    var im = document.getElementById('pe-og-image');
    if (t)  document.getElementById('pe-unfurl-title').textContent = t.value || <?= json_encode($_mock_name_js) ?>;
    if (d)  document.getElementById('pe-unfurl-desc').textContent  = d.value;
    if (im) document.getElementById('pe-unfurl-img').style.backgroundImage = im.value ? "url('" + im.value.replace(/'/g, "\\'") + "')" : '';
  }
</script>

</body>
</html>
