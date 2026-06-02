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
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&version_id=' . $new_id . '&flash=' . rawurlencode('Mock created.'));
                exit;
            }
        } elseif ($action === 'save_mock') {
            $body = (string)($_POST['body_html'] ?? '');
            $meta = [
                'meta_title'       => trim((string)($_POST['meta_title'] ?? '')) ?: null,
                'meta_description' => trim((string)($_POST['meta_description'] ?? '')) ?: null,
                'og_image'         => trim((string)($_POST['og_image'] ?? '')) ?: null,
                'og_type'          => trim((string)($_POST['og_type'] ?? 'website')),
                'twitter_card'     => trim((string)($_POST['twitter_card'] ?? 'summary_large_image')),
            ];
            update_page_mock($id, $body, null, $meta);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&version_id=' . $id . '&flash=' . rawurlencode('Saved.'));
            exit;
        } elseif ($action === 'rename_mock') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') {
                $errors[] = 'A name is required.';
            } else {
                rename_page_mock($id, $name);
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&version_id=' . $id . '&flash=' . rawurlencode('Renamed.'));
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
                    header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&version_id=' . $new_id . '&flash=' . rawurlencode('Duplicated.'));
                    exit;
                }
            }
        } elseif ($action === 'delete_mock') {
            delete_page_mock($id);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&flash=' . rawurlencode('Mock deleted.'));
            exit;
        } elseif ($action === 'publish_mock') {
            if (publish_page_mock($id)) {
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&version_id=' . $id . '&flash=' . rawurlencode('Published — this mock is now live on staging.'));
                exit;
            }
            $errors[] = 'Could not publish (publishing is only available for header / footer partials).';
        } elseif ($action === 'unpublish_mock') {
            unpublish_page_mock($id);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&version_id=' . $id . '&flash=' . rawurlencode('Un-published — fell back to file.'));
            exit;
        } elseif ($action === 'revert_to_file') {
            unpublish_all_for_slug($slug);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&flash=' . rawurlencode('Reverted to file.'));
            exit;
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// Load state for the chosen version.
$mocks      = list_page_mocks($slug);
$version_id = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 0;

$current_mock = null;
$current_body = '';
$current_meta = [
    'meta_title'       => null,
    'meta_description' => null,
    'og_image'         => null,
    'og_type'          => 'website',
    'twitter_card'     => 'summary_large_image',
];
$is_live = ($version_id === 0);

if ($is_live) {
    $current_body = read_page_file($slug) ?? '';
} else {
    foreach ($mocks as $m) {
        if ((int)$m['id'] === $version_id) {
            $current_mock = $m;
            $current_body = (string)$m['body_html'];
            $current_meta = [
                'meta_title'       => $m['meta_title'],
                'meta_description' => $m['meta_description'],
                'og_image'         => $m['og_image'],
                'og_type'          => $m['og_type'] ?? 'website',
                'twitter_card'     => $m['twitter_card'] ?? 'summary_large_image',
            ];
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

$tab = (string)($_GET['tab'] ?? 'body');
if (!in_array($tab, ['body', 'metadata'], true)) $tab = 'body';

// Preview URL: partials preview against /about/; pages preview against themselves.
$preview_url      = '';
if (!$is_live && $current_mock !== null) {
    $preview_url = $is_partial
        ? '/about/?_preview=' . (int)$current_mock['id']
        : '/' . rawurlencode($slug) . '/?_preview=' . (int)$current_mock['id'];
}
$preview_live_url = $is_partial ? '/about/' : ('/' . rawurlencode($slug) . '/');

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e  = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$rel_time = static function (string $ts) use ($e): string {
    if (!$ts) return '';
    $epoch = strtotime($ts);
    if (!$epoch) return '';
    $diff = time() - $epoch;
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return intdiv($diff, 60)   . 'm ago';
    if ($diff < 86400) return intdiv($diff, 3600) . 'h ago';
    if ($diff < 86400*30) return intdiv($diff, 86400) . 'd ago';
    return date('Y-m-d', $epoch);
};

$_mock_name_js = $current_mock ? (string)$current_mock['name'] : '';
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
  .pe-tabs { display:flex; gap:0; border-bottom:var(--rule-faint); margin-bottom:var(--space-16); }
  .pe-tab { padding:10px 18px; font-family:var(--font-cond); font-size:var(--text-micro); letter-spacing:0.10em; text-transform:uppercase; color:var(--muted); border-bottom:2px solid transparent; cursor:pointer; text-decoration:none; font-weight:600; }
  .pe-tab:hover { color:var(--ink); }
  .pe-tab.is-active { color:var(--ink); border-bottom-color:var(--primary); }
  .pe-version-row { display:flex; gap:var(--space-12); align-items:center; padding:var(--space-12) var(--space-16); background:var(--bg-soft); border:1px solid var(--border); border-radius:4px; margin-bottom:var(--space-16); flex-wrap:wrap; }
  .pe-version-label { font-family:var(--font-cond); font-size:var(--text-micro); font-weight:700; letter-spacing:0.10em; text-transform:uppercase; color:var(--muted); }
  .pe-version-row select { padding:6px 10px; border:1px solid var(--border); border-radius:4px; font-size:13px; background:var(--surface); color:var(--ink); min-width:280px; font-family:var(--font-cond); }
  .pe-version-row .pe-version-actions { margin-left:auto; display:flex; gap:var(--space-8); flex-wrap:wrap; align-items:center; }
  .pe-override-note { font-family:var(--font-mono); font-size:var(--text-micro); color:var(--muted); }
  .pe-override-note strong { color:var(--ink); font-weight:600; }
  .pe-unsaved { color:var(--c-terracotta); font-size:var(--text-micro); display:none; font-family:var(--font-mono); }
  .pe-unsaved.is-visible { display:inline; }
  .pe-readonly-notice { padding:10px 14px; background:color-mix(in srgb, var(--c-amber) 10%, transparent); border:1px solid color-mix(in srgb, var(--c-amber) 30%, transparent); border-radius:4px; font-size:var(--text-micro); margin-bottom:var(--space-12); color:var(--ink); }
  .CodeMirror { border:1px solid var(--border); border-radius:4px; font-size:13px; height:560px; }
  .pe-meta-form { display:grid; grid-template-columns: 200px 1fr; gap:var(--space-12) var(--space-16); align-items:start; max-width:880px; margin-bottom:var(--space-24); }
  .pe-meta-form label { font-size:var(--text-micro); color:var(--muted); padding-top:8px; font-family:var(--font-cond); text-transform:uppercase; letter-spacing:0.08em; font-weight:600; }
  .pe-meta-form input[type=text], .pe-meta-form textarea, .pe-meta-form select { width:100%; padding:8px 10px; border:1px solid var(--border); border-radius:4px; font-size:13px; background:var(--surface); color:var(--ink); font-family:var(--font-mono); }
  .pe-meta-form textarea { min-height:80px; resize:vertical; }
  .pe-meta-charcount { font-size:11px; color:var(--muted); padding-top:4px; }
  .pe-unfurl { max-width:520px; border:1px solid var(--border); border-radius:6px; overflow:hidden; background:var(--surface); }
  .pe-unfurl-img { width:100%; height:180px; background:var(--bg-soft) center/cover no-repeat; }
  .pe-unfurl-body { padding:var(--space-12) var(--space-16); }
  .pe-unfurl-title { font-weight:600; color:var(--ink); font-size:14px; margin-bottom:4px; }
  .pe-unfurl-desc  { color:var(--muted); font-size:12px; line-height:1.4; }
  .pe-inline-form { display:inline; }
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
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-page-edit">
      <?php
      $title    = $file_row['filename'];
      $subtitle = $is_partial
        ? 'Layout partial. Mocks can be published to override the file on staging — file remains canonical until you publish.'
        : 'Marketing page. Mock-only sandbox: the CMS never writes to disk. Files remain canonical.';
      $actions  = '<a href="/cms/pages" class="btn-ghost">← Back to Pages</a>';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="content-area">
        <?php if (count($errors) > 0): ?>
          <div class="form-errors" role="alert">
            <strong>Couldn't save:</strong>
            <ul><?php foreach ($errors as $err): ?><li><?= $e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status"><?= $e($flash) ?></div>
        <?php endif; ?>

        <!-- Version selector + actions -->
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
              <a class="btn-ghost" href="<?= $e($preview_live_url) ?>" target="_blank" rel="noopener">Preview Live ↗</a>
              <?php if ($published_mock !== null && $is_publishable): ?>
                <form class="pe-inline-form" method="post" action="/cms/pages/edit" onsubmit="return confirm('Revert to file? This un-publishes all mocks for this slug.');">
                  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                  <input type="hidden" name="action" value="revert_to_file">
                  <button type="submit" class="btn-ghost btn-danger">Revert to file</button>
                </form>
              <?php endif; ?>
              <button type="button" class="btn-pri" onclick="peCreateMock()">+ New Mock</button>
            <?php else: ?>
              <button type="button" class="btn-ghost" onclick="peRenameMock()">Rename</button>
              <button type="button" class="btn-ghost" onclick="peDuplicateMock()">Duplicate</button>
              <a class="btn-ghost" href="<?= $e($preview_url) ?>" target="_blank" rel="noopener">Preview ↗</a>
              <form class="pe-inline-form" method="post" action="/cms/pages/edit" onsubmit="return confirm('Delete mock &quot;<?= $e($current_mock['name']) ?>&quot;? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
                <input type="hidden" name="action" value="delete_mock">
                <button type="submit" class="btn-ghost btn-danger">Delete</button>
              </form>
              <?php if ($is_publishable): ?>
                <?php if ((int)$current_mock['is_published'] === 1): ?>
                  <form class="pe-inline-form" method="post" action="/cms/pages/edit">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                    <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                    <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
                    <input type="hidden" name="action" value="unpublish_mock">
                    <button type="submit" class="btn-ghost">Un-publish</button>
                  </form>
                <?php else: ?>
                  <form class="pe-inline-form" method="post" action="/cms/pages/edit" onsubmit="return confirm('Publish &quot;<?= $e($current_mock['name']) ?>&quot;? This will override <?= $e($file_row['filename']) ?> on staging.');">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                    <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                    <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
                    <input type="hidden" name="action" value="publish_mock">
                    <button type="submit" class="btn-pri">Publish →</button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
              <button type="submit" form="pe-mock-form" class="btn-pri">Save</button>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tabs -->
        <div class="pe-tabs">
          <a class="pe-tab <?= $tab === 'body' ? 'is-active' : '' ?>" href="?slug=<?= rawurlencode($slug) ?>&tab=body<?= !$is_live ? '&version_id=' . $version_id : '' ?>">Body HTML</a>
          <a class="pe-tab <?= $tab === 'metadata' ? 'is-active' : '' ?>" href="?slug=<?= rawurlencode($slug) ?>&tab=metadata<?= !$is_live ? '&version_id=' . $version_id : '' ?>">Metadata</a>
        </div>

        <?php if ($tab === 'body'): ?>
          <?php if ($is_live): ?>
            <div class="pe-readonly-notice">This is the on-disk file. The CMS never writes here — click <strong>+ New Mock</strong> to start editing.</div>
            <textarea id="pe-editor-live" readonly><?= $e($current_body) ?></textarea>
          <?php else: ?>
            <form id="pe-mock-form" method="post" action="/cms/pages/edit">
              <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
              <input type="hidden" name="slug" value="<?= $e($slug) ?>">
              <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
              <input type="hidden" name="action" value="save_mock">
              <input type="hidden" name="meta_title"       value="<?= $e($current_meta['meta_title']) ?>">
              <input type="hidden" name="meta_description" value="<?= $e($current_meta['meta_description']) ?>">
              <input type="hidden" name="og_image"         value="<?= $e($current_meta['og_image']) ?>">
              <input type="hidden" name="og_type"          value="<?= $e($current_meta['og_type']) ?>">
              <input type="hidden" name="twitter_card"     value="<?= $e($current_meta['twitter_card']) ?>">
              <textarea id="pe-editor-mock" name="body_html"><?= $e($current_body) ?></textarea>
            </form>
          <?php endif; ?>

        <?php else: /* metadata */ ?>
          <?php if ($is_live): ?>
            <div class="pe-readonly-notice">Metadata is stored per-mock. Create a mock to edit metadata.</div>
          <?php else: ?>
            <form method="post" action="/cms/pages/edit" id="pe-meta-form">
              <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
              <input type="hidden" name="slug" value="<?= $e($slug) ?>">
              <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
              <input type="hidden" name="action" value="save_mock">
              <input type="hidden" name="body_html" value="<?= $e($current_body) ?>">

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

                <div></div>
                <div><button type="submit" class="btn-pri">Save metadata</button></div>
              </div>

              <div class="content-block-header"><span class="content-block-label">Unfurl preview</span></div>
              <div class="pe-unfurl">
                <div class="pe-unfurl-img" id="pe-unfurl-img" style="background-image:url('<?= $e($current_meta['og_image']) ?>');"></div>
                <div class="pe-unfurl-body">
                  <div class="pe-unfurl-title" id="pe-unfurl-title"><?= $e($current_meta['meta_title'] ?: $current_mock['name']) ?></div>
                  <div class="pe-unfurl-desc"  id="pe-unfurl-desc"><?= $e($current_meta['meta_description']) ?></div>
                  <div class="pe-meta-charcount" style="margin-top:8px;">alexmchong.ca<?= $is_partial ? '' : ('/' . $e($slug) . '/') ?></div>
                </div>
              </div>
            </form>
          <?php endif; ?>
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
    var cm = CodeMirror.fromTextArea(ta, {
      mode: 'application/x-httpd-php',
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
        var f = document.getElementById('pe-unsaved');
        if (!f) return;
        if (cm.getValue() !== initial) f.classList.add('is-visible');
        else f.classList.remove('is-visible');
      });
    }
    window._peEditor = cm;
  })();

  function peSwitchVersion(vid) {
    var v = String(vid || '0');
    var qs = 'slug=<?= rawurlencode($slug) ?>';
    if (v !== '0') qs += '&version_id=' + encodeURIComponent(v);
    location.href = '/cms/pages/edit?' + qs;
  }
  function peCreateMock() {
    var name = prompt('Name this mock (e.g. "Tighter intro"):', '');
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
