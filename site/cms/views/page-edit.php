<?php
/**
 * cms/views/page-edit.php — Pages editor.
 *
 * Mental model:
 *   Drafts   — named versions stored in DB. Any one can be published.
 *   Published — the draft last written to the live file (is_published = 1).
 *   Live file — what the URL currently serves (written by Publish →).
 *
 * Tabs: Draft | Settings
 * Within Draft tab: selector row → [Edit] [Preview] toggle → editor / iframe.
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
if ($slug === '') { header('Location: /cms/pages'); exit; }

$file_row = find_page_file($slug);
if ($file_row === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Unknown page slug: $slug\n";
    return;
}

$is_partial     = $file_row['kind'] === 'partial';
$is_publishable = in_array($slug, PAGES_PUBLISHABLE_SLUGS, true);

// Resolve referrer context early — must survive redirects that add version_id.
$_from     = in_array($_GET['from'] ?? '', ['navigation'], true) ? (string)($_GET['from'] ?? '') : '';
$_from_qs  = $_from !== '' ? '&from=' . rawurlencode($_from) : '';

// ─── POST handlers ────────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);

        if ($action === 'create_mock') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') { $errors[] = 'A name is required.'; }
            else {
                // Seed from the last published mock's SEPARATED fields (body + style)
                // so we never parse the folded file back apart; fall back to the
                // on-disk file for pages never published through the CMS.
                $pub = get_published_mock($slug);
                if ($pub !== null) {
                    $new_id = create_page_mock($slug, $name, (string)$pub['body_html'], [], $pub['style_css'] ?? null);
                } else {
                    $new_id = create_page_mock($slug, $name, read_page_file($slug) ?? '');
                }
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=edit&version_id=' . $new_id . '&flash=' . rawurlencode('Draft created.'));
                exit;
            }
        } elseif ($action === 'save_metadata') {
            upsert_page_metadata($slug, [
                'meta_title'       => trim((string)($_POST['meta_title']       ?? '')) ?: null,
                'meta_description' => trim((string)($_POST['meta_description'] ?? '')) ?: null,
                'og_image'         => trim((string)($_POST['og_image']         ?? '')) ?: null,
                'og_type'          => trim((string)($_POST['og_type']          ?? 'website')),
                'twitter_card'     => trim((string)($_POST['twitter_card']     ?? 'summary_large_image')),
            ]);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=configure&flash=' . rawurlencode('Metadata saved.'));
            exit;
        } elseif ($action === 'rename_mock') {
            $name = trim((string)($_POST['name'] ?? ''));
            if ($name === '') { $errors[] = 'A name is required.'; }
            else {
                rename_page_mock($id, $name);
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=edit&version_id=' . $id . '&flash=' . rawurlencode('Draft renamed.'));
                exit;
            }
        } elseif ($action === 'duplicate_mock') {
            $new_name = trim((string)($_POST['new_name'] ?? ''));
            if ($new_name === '') { $errors[] = 'A name is required.'; }
            else {
                $new_id = duplicate_page_mock($id, $new_name);
                if ($new_id === null) { $errors[] = 'Could not duplicate.'; }
                else {
                    header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=edit&version_id=' . $new_id . '&flash=' . rawurlencode('Draft duplicated.'));
                    exit;
                }
            }
        } elseif ($action === 'delete_mock') {
            delete_page_mock($id);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=edit&flash=' . rawurlencode('Draft deleted.'));
            exit;
        } elseif ($action === 'publish_mock') {
            if (publish_page_mock($id)) {
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=page&version_id=' . $id . '&flash=' . rawurlencode('Published on staging.'));
                exit;
            }
            $errors[] = 'Could not publish (only available for header/footer partials).';
        } elseif ($action === 'unpublish_mock') {
            unpublish_page_mock($id);
            header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=edit&version_id=' . $id . '&flash=' . rawurlencode('Un-published.'));
            exit;
        } elseif ($action === 'archive_page') {
            if ($file_row['kind'] !== 'page' || page_type($slug) !== 'standard') { $errors[] = 'Archive is only available for standard pages (Home and error pages can\'t be archived).'; }
            elseif (archive_page($slug)) {
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=configure&flash=' . rawurlencode('Page archived — '. page_public_url($slug) .' now redirects to /archive/' . $slug . '/.'));
                exit;
            } else { $errors[] = 'Could not archive the page.'; }
        } elseif ($action === 'restore_page') {
            if ($file_row['kind'] !== 'page') { $errors[] = 'Restore is only available for marketing pages.'; }
            elseif (restore_page($slug)) {
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=configure&flash=' . rawurlencode('Page restored.'));
                exit;
            } else { $errors[] = 'Could not restore.'; }
        } elseif ($action === 'import_pending') {
            $filename = basename((string)($_POST['pending_filename'] ?? ''));
            if ($filename === '') { $errors[] = 'No pending file specified.'; }
            else {
                $new_id = import_pending_draft($filename);
                if ($new_id === null) { $errors[] = 'Could not import — the file may already have been imported.'; }
                else {
                    header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=edit&version_id=' . $new_id . '&flash=' . rawurlencode('Draft imported — preview and publish when ready.'));
                    exit;
                }
            }
        } elseif ($action === 'reset_draft') {
            $live_body = read_page_file($slug);
            if ($live_body === null) { $errors[] = 'Could not read live file to reset from.'; }
            else {
                // Clear the style override too — the live file is the complete
                // published artifact, so a reset shouldn't leave a stale override.
                update_page_mock($id, $live_body, null, [], '');
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=edit&version_id=' . $id . '&flash=' . rawurlencode('Draft reset to live file.'));
                exit;
            }
        } elseif ($action === 'restore_snapshot') {
            $snapshot_id = (int)($_POST['snapshot_id'] ?? 0);
            if ($snapshot_id <= 0 || $id <= 0) { $errors[] = 'Invalid snapshot or draft.'; }
            elseif (restore_snapshot_to_draft($snapshot_id, $id)) {
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=edit&version_id=' . $id . '&flash=' . rawurlencode('Version restored to draft — review before publishing.'));
                exit;
            } else { $errors[] = 'Could not restore snapshot.'; }
        } elseif ($action === 'publish_to_file') {
            if ($file_row['kind'] !== 'page') { $errors[] = 'Publish is only available for marketing pages.'; }
            elseif (publish_mock_to_file($id)) {
                header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=page&version_id=' . $id . '&flash=' . rawurlencode('Published — deploy to push to production.'));
                exit;
            } else { $errors[] = 'Could not write to file. Check server write permissions.'; }
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// ─── Load state ───────────────────────────────────────────────────────────────
$mocks      = list_page_mocks($slug);
$version_id = isset($_GET['version_id']) ? (int)$_GET['version_id'] : 0;

// Auto-create Draft 1 for any file with no drafts yet (pages and partials).
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' &&
    count($mocks) === 0
) {
    $new_id   = create_page_mock($slug, 'Draft 1', read_page_file($slug) ?? '');
    $flash_qs = ($flash !== '') ? '&flash=' . rawurlencode($flash) : '';
    header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=edit&version_id=' . $new_id . $_from_qs . $flash_qs);
    exit;
}

// Auto-select best draft when none specified.
if ($version_id === 0 && count($mocks) > 0) {
    $target = null;
    foreach ($mocks as $m) {
        if ((int)$m['is_published'] === 1) { $target = $m; break; }
    }
    if (!$target) $target = $mocks[0];
    $flash_qs = ($flash !== '') ? '&flash=' . rawurlencode($flash) : '';
    header('Location: /cms/pages/edit?slug=' . rawurlencode($slug) . '&tab=' . ($_GET['tab'] ?? 'draft') . '&version_id=' . (int)$target['id'] . $_from_qs . $flash_qs);
    exit;
}

$current_mock = null;
foreach ($mocks as $m) {
    if ((int)$m['id'] === $version_id) { $current_mock = $m; break; }
}
if ($current_mock === null && count($mocks) > 0) {
    header('Location: /cms/pages/edit?slug=' . rawurlencode($slug));
    exit;
}

$published_mock = null;
foreach ($mocks as $m) {
    if ((int)$m['is_published'] === 1) { $published_mock = $m; break; }
}

$current_body   = $current_mock ? (string)$current_mock['body_html'] : '';
$current_style  = $current_mock ? (string)($current_mock['style_css'] ?? '') : '';
$pending_drafts = ($file_row['kind'] === 'page') ? list_pending_drafts_for_slug($slug) : [];
$is_archived    = ($file_row['kind'] === 'page') ? is_page_archived($slug) : false;
$snapshots      = list_page_snapshots($slug);

$saved_meta = get_page_metadata($slug);
$current_meta = [
    'meta_title'       => $saved_meta['meta_title']       ?? null,
    'meta_description' => $saved_meta['meta_description'] ?? null,
    'og_image'         => $saved_meta['og_image']         ?? null,
    'og_type'          => $saved_meta['og_type']          ?? 'website',
    'twitter_card'     => $saved_meta['twitter_card']     ?? 'summary_large_image',
];

// ─── Tab + view-mode routing ─────────────────────────────────────────────────
$_tab_compat = ['body' => 'edit', 'metadata' => 'configure', 'live' => 'edit', 'preview' => 'edit', 'draft' => 'edit', 'settings' => 'configure'];
$_tab_raw    = (string)($_GET['tab'] ?? '');
$tab         = $_tab_compat[$_tab_raw] ?? $_tab_raw;
if (!in_array($tab, ['page', 'edit', 'configure'], true)) $tab = 'page';
if ($is_partial && $tab === 'configure') $tab = 'edit';

// Live URL — single source of truth in lib/pages.php (page_public_url).
// landing → '/', not /landing/. Partials render inside /about/.
$live_url = page_public_url($slug);
if ($is_partial) $live_url = '/about/?_partial_focus=' . rawurlencode($slug);
// Cache-bust for Page tab iframe so publish always shows fresh content.
$live_url_cb  = $live_url . (strpos($live_url, '?') !== false ? '&' : '?') . '_t=' . time();

// Original file source — shown read-only in the Page tab HTML sub-view.
$original_body = read_page_file($slug) ?? '';

$preview_url = $current_mock
    ? ($is_partial
        ? '/about/?_partial_focus=' . rawurlencode($slug) . '&_preview='
        : (page_public_url($slug) . '?_preview=')) . (int)$current_mock['id']
    : $live_url;

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// rel_time_html() provided by lib/pages.php

$_mock_name_js = $current_mock ? (string)$current_mock['name'] : '';
$_editor_mode  = (substr((string)$file_row['filename'], -5) === '.html') ? 'html' : 'php';
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title><?= $e(page_display_name($slug)) ?> — alexmchong.ca CMS</title>
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
<link rel="stylesheet" href="/cms/_assets/codemirror/codemirror.min.css">
<style>
  /* ── Save status — micro muted mono, sits beside Save draft button ── */
  #pe-save-status { font-size: var(--text-micro); font-family: var(--font-mono); color: var(--muted); }

  /* ── CodeMirror — base style; height overridden by pane flex rules below ── */
  .CodeMirror { border: 1px solid var(--border); border-radius: 4px; font-size: 13px; height: 560px; }

  /* ── Overflow menu ── */
  .pe-overflow { position: relative; display: inline-block; }
  .pe-overflow > summary { list-style: none; cursor: pointer; }
  .pe-overflow > summary::-webkit-details-marker { display: none; }
  .pe-overflow-menu {
    position: absolute; right: 0; top: calc(100% + 6px);
    background: var(--surface);
    border: 0.5px solid var(--ink-18);
    border-radius: 6px;
    min-width: 172px; z-index: 20; padding: 4px;
    box-shadow: var(--shadow-h);
  }
  /* form wrapper must not add layout — button fills the row */
  .pe-overflow-menu form { margin: 0; padding: 0; }
  .pe-overflow-menu button,
  .pe-overflow-menu a {
    display: block; width: 100%; padding: 7px 12px;
    text-align: left; background: none; border: none;
    font-size: var(--text-meta); font-family: var(--font); font-weight: 500; line-height: 1.4;
    color: var(--ink); cursor: pointer; white-space: nowrap;
    text-decoration: none; border-radius: 4px;
    transition: background 0.1s;
    box-sizing: border-box;
  }
  .pe-overflow-menu button:hover,
  .pe-overflow-menu a:hover { background: var(--ink-08); }
  .pe-overflow-danger { color: var(--c-terracotta) !important; }
  .pe-overflow-danger:hover { background: color-mix(in srgb, var(--c-terracotta) 6%, transparent) !important; }
  .pe-overflow-divider { border: none; border-top: 1px solid var(--border); margin: 4px 0; }

  /* ── Overflow menu summary button ── */
  .pe-overflow > summary.btn-sec {
    padding: 7px 14px; font-family: var(--font-cond); font-size: var(--text-label);
    font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
    line-height: 1; border-radius: var(--r-pill);
    display: inline-flex; align-items: center; justify-content: center;
  }

  /* ── Settings ── */
  .pe-settings-section + .pe-settings-section { padding-top: var(--space-32); border-top: 1px solid var(--border); margin-top: var(--space-32); }
  .pe-meta-form { display: grid; grid-template-columns: 200px 1fr; gap: var(--space-12) var(--space-16); align-items: start; max-width: 880px; margin-bottom: var(--space-24); }
  .pe-meta-form label { font-size: var(--text-micro); color: var(--muted); padding-top: 8px; font-family: var(--font-cond); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
  .pe-meta-form input[type=text], .pe-meta-form textarea, .pe-meta-form select { width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 4px; font-size: 13px; background: var(--surface); color: var(--ink); font-family: var(--font-mono); }
  .pe-meta-form textarea { min-height: 80px; resize: vertical; }
  .pe-meta-charcount { font-size: 11px; color: var(--muted); padding-top: 4px; }
  .pe-unfurl { max-width: 520px; border: 1px solid var(--border); border-radius: 6px; overflow: hidden; background: var(--surface); }
  .pe-unfurl-img  { width: 100%; height: 180px; background: var(--bg-soft) center/cover no-repeat; }
  .pe-unfurl-body { padding: var(--space-12) var(--space-16); }
  .pe-unfurl-title { font-weight: 600; color: var(--ink); font-size: 14px; margin-bottom: 4px; }
  .pe-unfurl-desc  { color: var(--muted); font-size: 12px; line-height: 1.4; }
  .pe-archive-desc { font-size: var(--text-meta); color: var(--muted); margin: 0 0 var(--space-16); max-width: 520px; }

  .is-hidden-tab { display: none !important; }
  .pe-inline-form { display: inline; }

  /* ── View pills — right side of .cms-tabs ── */
  .pe-view-pills {
    margin-left: auto;
    display: flex;
    align-items: center;
    padding: 0 var(--space-16);
    border-left: var(--rule-faint);
    gap: 3px;
  }

  /* ── Page + Edit tab panels: full-bleed, direct child of .view ── */
  #view-page-edit .pe-page-panel:not(.is-hidden-tab),
  #view-page-edit .pe-edit-tab:not(.is-hidden-tab) {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
    overflow: hidden;
  }

  /* ── Panes row: up to 3 side-by-side columns ── */
  .pe-panes {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }
  .pe-pane {
    display: none;
    flex-direction: column;
    flex: 1;
    min-width: 0;
    overflow: hidden;
  }
  .pe-pane.is-active { display: flex; }
  /* Separator between adjacent active panes — CSS only, no JS needed */
  .pe-pane.is-active + .pe-pane.is-active { border-left: var(--rule-faint); }

  /* ── Edit pane: CodeMirror fills height ── */
  .pe-pane[data-view="edit"] #pe-edit-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: var(--space-16) var(--space-20);
    margin-top: 0;
    overflow: hidden;
    min-height: 0;
  }
  .pe-pane[data-view="edit"] .CodeMirror { flex: 1; height: auto; min-height: 200px; }

  /* ── Style pane: CodeMirror fills height (mirrors the edit pane) ── */
  .pe-pane[data-view="style"] #pe-style-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: var(--space-16) var(--space-20);
    overflow: hidden;
    min-height: 0;
  }
  .pe-pane[data-view="style"] .CodeMirror { flex: 1; height: auto; min-height: 200px; }

  /* ── Original HTML sub-pane — read-only, visually distinct ── */
  #pe-original-panel { flex: 1; display: flex; flex-direction: column; padding: var(--space-16) var(--space-20); overflow: hidden; min-height: 0; background: var(--canvas-bg); }
  #pe-original-panel .CodeMirror { flex: 1; height: auto; min-height: 200px; background: var(--canvas-bg); cursor: default; }
  [data-page-view="html"] { background: var(--canvas-bg); }

  /* ── Preview / Live panes ── */
  .pe-pane-notice {
    font-family: var(--font-mono);
    font-size: var(--text-micro);
    color: var(--muted);
    padding: var(--space-8) var(--space-16);
    background: var(--canvas-raised);
    border-bottom: var(--rule-faint);
    flex-shrink: 0;
  }
  .pe-pane-frame { flex: 1; overflow: hidden; background: var(--canvas-bg); position: relative; }
  .pe-pane-frame iframe { width: 100%; height: 100%; border: none; display: block; }

  /* ── Resize handle between editor and preview area ── */
  .pe-resize-handle {
    width: 6px; flex-shrink: 0; background: var(--ink-18);
    cursor: col-resize; display: none; position: relative;
    transition: background 0.1s;
  }
  /* Widen the hit area without widening the visual */
  .pe-resize-handle::before {
    content: ''; position: absolute; inset: 0 -5px; cursor: col-resize;
  }
  /* Grip dots */
  .pe-resize-handle::after {
    content: ''; position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 2px; height: 24px;
    background: repeating-linear-gradient(
      to bottom, var(--ink-30) 0 2px, transparent 2px 5px
    );
    border-radius: 1px;
  }
  .pe-resize-handle:hover, .pe-resize-handle.is-dragging {
    background: var(--ink-12, var(--border));
  }
  /* Only show handle when edit pane AND at least one preview pane are active */
  .pe-pane[data-view="edit"].is-active + .pe-resize-handle:has(+ .pe-preview-area .pe-pane.is-active) {
    display: block;
  }

  /* ── Preview area — wraps preview + live panes ── */
  /* Hidden when empty; flex:1 when any pane inside is active */
  .pe-preview-area { display: none; flex: 1; min-width: 0; overflow: hidden; }
  .pe-preview-area:has(.pe-pane.is-active) { display: flex; }
  .pe-preview-area .pe-pane.is-active + .pe-pane.is-active { border-left: var(--rule-faint); }

  /* ── Live preview overlay — fades in while editing, snaps off on reload ── */
  .pe-preview-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.1);
    opacity: 0; pointer-events: none; z-index: 2;
    transition: opacity 0.25s ease;
    display: flex; align-items: center; justify-content: center;
  }
  .pe-preview-overlay.is-active  { opacity: 1; }
  .pe-preview-overlay.is-instant { transition: none; }
  .pe-preview-overlay::after {
    content: ''; width: 22px; height: 22px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: rgba(255,255,255,0.9);
    border-radius: 50%;
    animation: pe-spin 0.7s linear infinite;
  }
  @keyframes pe-spin { to { transform: rotate(360deg); } }

  /* ── Bottom action bar ── */
  .pe-actions-bar { padding: var(--space-12) var(--space-20); margin-top: 0; background: var(--canvas-raised); flex-shrink: 0; }
  .pe-actions-right { display: flex; align-items: center; gap: var(--space-8); margin-left: auto; }
  .pe-actions-meta { font-size: var(--text-micro); font-family: var(--font-mono); color: var(--muted); }

  /* ── Settings area: hidden when draft tab active ── */
  .pe-settings-area.is-hidden-tab { display: none !important; }
</style>
</head>
<body>

<?php
$_back_url   = $_from === 'navigation' ? '/cms/navigation' : '/cms/pages';
$_back_label = $_from === 'navigation' ? '← Navigation' : '← Pages';

$breadcrumb = ($_from === 'navigation' ? 'Navigation' : 'Pages') . ' → ' . page_display_name($slug);
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = $_from === 'navigation' ? 'navigation' : 'pages';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-page-edit">
      <?php
      $title    = page_display_name($slug);
      $subtitle = $is_partial
        ? 'Changes publish to staging directly — no deploy required.'
        : 'Draft → Publish → Live. Deploy pushes to production.';
      $actions  = '<a href="' . $e($_back_url) . '" class="btn-sec">' . $e($_back_label) . '</a>';
      if (!$is_partial) {
          $actions .= $is_archived
            ? ' <a href="/archive/' . $e($slug) . '/" target="_blank" rel="noopener" class="btn-sec">Archived ↗</a>'
            : ' <a href="' . $e($live_url) . '" target="_blank" rel="noopener" class="btn-sec">Live ↗</a>';
      }
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $heading = "Couldn't save:";
      require __DIR__ . '/../partials/form-errors.php';
      require __DIR__ . '/../partials/flash.php';
      ?>

      <?php if (!empty($pending_drafts)): ?>
        <div class="notice notice-pending" style="margin:0 0 var(--space-16)">
          <strong>Claude draft<?= count($pending_drafts) > 1 ? 's' : '' ?> ready to import</strong>
          <?php foreach ($pending_drafts as $pd): ?>
            <?php $pd_label = substr($pd['date'],0,4) . '-' . substr($pd['date'],4,2) . '-' . substr($pd['date'],6,2); ?>
            <form class="pe-inline-form" method="post" action="/cms/pages/edit" style="display:inline-block;margin-left:var(--space-12)">
              <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
              <input type="hidden" name="slug" value="<?= $e($slug) ?>">
              <input type="hidden" name="action" value="import_pending">
              <input type="hidden" name="pending_filename" value="<?= $e($pd['filename']) ?>">
              <button type="submit" class="btn-sec btn-tiny"><?= $e($pd_label) ?> — Import as draft</button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Tabs: Page | Edit | Configure + view column pills -->
      <div class="cms-tabs" role="tablist">
        <a class="cms-tab <?= $tab === 'page'      ? 'is-active' : '' ?>" role="tab"
           href="?slug=<?= rawurlencode($slug) ?>&tab=page<?= $version_id ? '&version_id='.$version_id : '' ?>">Page</a>
        <a class="cms-tab <?= $tab === 'edit'      ? 'is-active' : '' ?>" role="tab"
           href="?slug=<?= rawurlencode($slug) ?>&tab=edit<?= $version_id ? '&version_id='.$version_id : '' ?>">Edit</a>
        <?php if (!$is_partial): ?>
          <a class="cms-tab <?= $tab === 'configure' ? 'is-active' : '' ?>" role="tab"
             href="?slug=<?= rawurlencode($slug) ?>&tab=configure<?= $version_id ? '&version_id='.$version_id : '' ?>">Configure</a>
        <?php endif; ?>
        <?php if ($tab === 'page'): ?>
        <div class="pe-view-pills">
          <div class="filter-group">
            <button type="button" class="filter-pill active" data-page-toggle="view">View</button>
            <button type="button" class="filter-pill" data-page-toggle="html">HTML</button>
          </div>
        </div>
        <?php endif; ?>
        <?php if ($current_mock !== null && $tab === 'edit'): ?>
        <div class="pe-view-pills">
          <div class="filter-group">
            <button type="button" class="filter-pill active" data-view-toggle="edit">HTML</button>
            <button type="button" class="filter-pill" data-view-toggle="style">Style</button>
            <button type="button" class="filter-pill" data-view-toggle="preview">Preview</button>
            <button type="button" class="filter-pill" data-view-toggle="live">Live version</button>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── PAGE tab — rendered view + original HTML source ── -->
      <div class="pe-page-panel reveal-page <?= $tab !== 'page' ? 'is-hidden-tab' : '' ?>">
        <div class="pe-panes">

          <!-- View sub-pane: rendered live page -->
          <div class="pe-pane is-active" data-page-view="view">
            <div class="pe-pane-frame">
              <iframe src="<?= $e($live_url_cb) ?>"
                      title="Live page · <?= $e(page_public_url($slug)) ?>"></iframe>
            </div>
          </div>

          <!-- HTML sub-pane: original file source, read-only -->
          <div class="pe-pane" data-page-view="html">
            <div class="pe-pane-notice">Original file — read only</div>
            <div id="pe-original-panel">
              <textarea id="pe-editor-original" data-mode="<?= $e($_editor_mode) ?>"><?= $e($original_body) ?></textarea>
            </div>
          </div>

        </div>
      </div>

      <!-- ── EDIT tab — full-bleed column layout, no content-area ── -->
      <?php if ($current_mock !== null): ?>
      <div class="pe-edit-tab reveal-page <?= $tab !== 'edit' ? 'is-hidden-tab' : '' ?>">

        <div class="pe-panes">

          <!-- Edit pane -->
          <div class="pe-pane is-active" data-view="edit">
            <div class="pe-pane-notice">HTML — page body</div>
            <div id="pe-edit-panel">
              <textarea id="pe-editor-draft" class="fade-on-load" data-mode="<?= $e($_editor_mode) ?>"><?= $e($current_body) ?></textarea>
            </div>
          </div>

          <!-- Resize handle — visible when edit pane is active -->
          <div class="pe-resize-handle" id="pe-resize-handle"></div>

          <!-- Preview area — style editor + preview + live panes split this space -->
          <div class="pe-preview-area" id="pe-preview-area">

            <!-- Style pane — page-scoped CSS override (P2). Opens beside the HTML
                 editor, same toggle logic as the preview panes. -->
            <div class="pe-pane" data-view="style">
              <div class="pe-pane-notice">Page styles — scoped to this page · one-off overrides only (reusable patterns belong in the design system)</div>
              <div id="pe-style-panel">
                <textarea id="pe-editor-style"><?= $e($current_style) ?></textarea>
              </div>
            </div>

            <!-- Preview pane — auto-refreshes as you type -->
            <div class="pe-pane" data-view="preview">
              <div class="pe-pane-notice">Saved draft — not yet published</div>
              <div class="pe-pane-frame">
                <div class="pe-preview-overlay" id="pe-preview-overlay"></div>
                <iframe id="pe-preview-iframe"
                        title="Preview draft · <?= $e($file_row['filename']) ?>"
                        class="post-preview-iframe"></iframe>
              </div>
            </div>

            <!-- Live version pane -->
            <div class="pe-pane" data-view="live">
              <div class="pe-pane-notice">Live version — currently published</div>
              <div class="pe-pane-frame">
                <iframe id="pe-live-iframe"
                        title="Live · <?= $e($slug) ?>"
                        data-live-src="<?= $e($live_url) ?>"></iframe>
              </div>
            </div>

          </div><!-- /.pe-preview-area -->

        </div><!-- /.pe-panes -->

        <!-- Bottom action bar — matches article-edit pattern: Save · Cancel · [status] · Publish right -->
        <div class="form-actions pe-actions-bar">
          <button type="button" class="btn-sec" id="pe-save-btn" onclick="peSaveDraft()">Save draft</button>
          <button type="submit" form="pe-reset-form" class="btn-sec">Reset draft</button>
          <a href="/cms/pages" class="btn-sec">Cancel</a>
          <span id="pe-save-status"></span>
          <div class="pe-actions-right">
            <?php $_last_pub = page_last_published_at($slug); if ($_last_pub !== null): ?>
              <span class="pe-actions-meta">Last published <?= rel_time_html($_last_pub) ?></span>
            <?php endif; ?>
            <?php if (!$is_partial && $file_row['kind'] === 'page'): ?>
              <button type="submit" form="pe-publish-form" class="btn-pri" id="pe-publish-btn">Publish →</button>
            <?php elseif ($is_publishable): ?>
              <?php if ((int)$current_mock['is_published'] === 1): ?>
                <button type="submit" form="pe-unpublish-form" class="btn-sec">Un-publish</button>
              <?php else: ?>
                <button type="submit" form="pe-publish-form" class="btn-pri" id="pe-publish-btn">Publish →</button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /.pe-edit-tab -->
      <?php endif; ?>

      <!-- ── CONFIGURE tab — keeps content-area ── -->
      <?php if (!$is_partial): ?>
      <div class="content-area pe-settings-area <?= $tab !== 'configure' ? 'is-hidden-tab' : '' ?>">

        <!-- Address -->
        <div class="pe-settings-section">
          <div class="content-block-header"><span class="content-block-label">Address</span></div>
          <div class="pe-meta-form">
            <label>Public URL</label>
            <div>
              <?php if (page_type($slug) === 'error'): ?>
                <input type="text" value="Shown on an HTTP error code" disabled style="opacity:.55">
                <div class="pe-meta-charcount">Error pages don't have their own URL.</div>
              <?php elseif (page_type($slug) === 'home'): ?>
                <input type="text" value="/" disabled style="opacity:.55">
                <div class="pe-meta-charcount">Home page — the URL is locked to the site root.</div>
              <?php else: ?>
                <input type="text" value="<?= $e(page_public_url($slug)) ?>" disabled style="opacity:.55">
                <div class="pe-meta-charcount">The URL comes from the page's slug.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Metadata -->
        <div class="pe-settings-section">
          <div class="content-block-header"><span class="content-block-label">SEO &amp; sharing</span></div>
          <form method="post" action="/cms/pages/edit">
            <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
            <input type="hidden" name="slug" value="<?= $e($slug) ?>">
            <input type="hidden" name="action" value="save_metadata">
            <div class="pe-meta-form">
              <label for="pe-meta-title">Meta title</label>
              <div>
                <input id="pe-meta-title" type="text" name="meta_title" maxlength="60"
                       value="<?= $e($current_meta['meta_title']) ?>"
                       oninput="document.getElementById('pe-meta-title-count').textContent=this.value.length+' / 60';peUnfurlSync();">
                <div class="pe-meta-charcount" id="pe-meta-title-count"><?= strlen((string)$current_meta['meta_title']) ?> / 60</div>
              </div>
              <label for="pe-meta-desc">Meta description</label>
              <div>
                <textarea id="pe-meta-desc" name="meta_description" maxlength="160"
                          oninput="document.getElementById('pe-meta-desc-count').textContent=this.value.length+' / 160';peUnfurlSync();"><?= $e($current_meta['meta_description']) ?></textarea>
                <div class="pe-meta-charcount" id="pe-meta-desc-count"><?= strlen((string)$current_meta['meta_description']) ?> / 160</div>
              </div>
              <label for="pe-og-image">og:image URL</label>
              <div>
                <input id="pe-og-image" type="text" name="og_image" placeholder="/uploads/og/about.jpg"
                       value="<?= $e($current_meta['og_image']) ?>" oninput="peUnfurlSync();">
                <div class="pe-meta-charcount">Recommended 1200×630.</div>
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
            <div class="pe-unfurl" style="margin-bottom:var(--space-24)">
              <div class="pe-unfurl-img" id="pe-unfurl-img" style="background-image:url('<?= $e($current_meta['og_image']) ?>');"></div>
              <div class="pe-unfurl-body">
                <div class="pe-unfurl-title" id="pe-unfurl-title"><?= $e($current_meta['meta_title'] ?: page_display_name($slug)) ?></div>
                <div class="pe-unfurl-desc"  id="pe-unfurl-desc"><?= $e($current_meta['meta_description']) ?></div>
                <div class="pe-meta-charcount" style="margin-top:8px">alexmchong.ca<?= $e(page_public_url($slug)) ?></div>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-sec" id="pe-meta-save-btn">Save metadata</button>
            </div>
          </form>
        </div>

        <!-- Archive — standard pages only (Home / error pages can't be archived) -->
        <?php if ($file_row['kind'] === 'page' && page_type($slug) === 'standard'): ?>
        <div class="pe-settings-section">
          <div class="content-block-header"><span class="content-block-label">Archive</span></div>
          <?php if ($is_archived): ?>
            <p class="pe-archive-desc">This page is archived. <strong><?= $e(page_public_url($slug)) ?></strong> redirects to <strong>/archive/<?= $e($slug) ?>/</strong> and is not indexed by search engines.</p>
            <form method="post" action="/cms/pages/edit">
              <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
              <input type="hidden" name="slug" value="<?= $e($slug) ?>">
              <input type="hidden" name="action" value="restore_page">
              <button type="submit" class="btn-sec">Restore page</button>
            </form>
          <?php else: ?>
            <p class="pe-archive-desc">Archiving redirects <strong><?= $e(page_public_url($slug)) ?></strong> to <strong>/archive/<?= $e($slug) ?>/</strong> with a noindex banner. Files and drafts stay intact.</p>
            <form method="post" action="/cms/pages/edit"
                  data-confirm="Archive <?= $e(page_public_url($slug)) ?>? It will redirect until you restore it.">
              <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
              <input type="hidden" name="slug" value="<?= $e($slug) ?>">
              <input type="hidden" name="action" value="archive_page">
              <button type="submit" class="btn-sec">Archive page</button>
            </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Version history -->
        <div class="pe-settings-section">
          <div class="content-block-header">
            <span class="content-block-label">Version history</span>
            <span class="content-block-sublabel">Snapshots are captured automatically each time you publish.</span>
          </div>
          <?php if (empty($snapshots)): ?>
            <p class="pe-archive-desc muted">No snapshots yet — one will be saved the next time you publish.</p>
          <?php else: ?>
            <table class="cms-table" style="margin-top:var(--space-8)">
              <thead><tr>
                <th>Version</th>
                <th>Captured</th>
                <th>Size</th>
                <th></th>
              </tr></thead>
              <tbody>
              <?php foreach ($snapshots as $snap): ?>
                <tr>
                  <td><?= $e((string)$snap['name']) ?></td>
                  <td><?= rel_time_html((int)strtotime((string)$snap['created_at'])) ?></td>
                  <td class="muted" style="font-family:var(--font-mono);font-size:var(--text-micro)"><?= number_format((int)$snap['body_len']) ?> chars</td>
                  <td>
                    <?php if ($current_mock !== null): ?>
                    <form method="post" action="/cms/pages/edit"
                          data-confirm="Restore '<?= $e((string)$snap['name']) ?>' to your draft? This will overwrite your current draft content. You can still undo by resetting to the live file.">
                      <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                      <input type="hidden" name="slug" value="<?= $e($slug) ?>">
                      <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
                      <input type="hidden" name="snapshot_id" value="<?= (int)$snap['id'] ?>">
                      <input type="hidden" name="action" value="restore_snapshot">
                      <button type="submit" class="btn-sec btn-tiny">Restore to draft</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

      </div><!-- /.pe-settings-area -->
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Hidden publish / unpublish forms — submitted via form="id" from the action bar -->
<?php if ($current_mock !== null): ?>
<?php if (!$is_partial && $file_row['kind'] === 'page'): ?>
<form id="pe-publish-form" method="post" action="/cms/pages/edit"
      data-confirm="Publish <?= $e(page_public_url($slug)) ?> to file? This overwrites the live file — deploy to push to production.">
  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
  <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
  <input type="hidden" name="action" value="publish_to_file">
</form>
<?php elseif ($is_publishable): ?>
  <?php if ((int)$current_mock['is_published'] === 1): ?>
<form id="pe-unpublish-form" method="post" action="/cms/pages/edit">
  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
  <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
  <input type="hidden" name="action" value="unpublish_mock">
</form>
  <?php else: ?>
<form id="pe-publish-form" method="post" action="/cms/pages/edit"
      data-confirm="Publish <?= $e(page_public_url($slug)) ?> to staging? This overrides <?= $e($file_row['filename']) ?>.">
  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
  <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
  <input type="hidden" name="action" value="publish_mock">
</form>
  <?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<!-- Reset draft form -->
<?php if ($current_mock !== null): ?>
<form id="pe-reset-form" method="post" action="/cms/pages/edit"
      data-confirm="Reset draft? This replaces your current draft with the live file. This can't be undone.">
  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
  <input type="hidden" name="id" value="<?= (int)$current_mock['id'] ?>">
  <input type="hidden" name="action" value="reset_draft">
</form>
<?php endif; ?>

<!-- Hidden forms for prompt-driven draft actions -->
<form id="pe-create-form" method="post" action="/cms/pages/edit" style="display:none">
  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
  <input type="hidden" name="slug" value="<?= $e($slug) ?>">
  <input type="hidden" name="action" value="create_mock">
  <input type="hidden" name="name" id="pe-create-name">
</form>
<form id="pe-rename-form" method="post" action="/cms/pages/edit" style="display:none">
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
  var CSRF         = <?= json_encode($csrf_token) ?>;
  var MOCK_ID      = <?= json_encode($current_mock ? (int)$current_mock['id'] : 0) ?>;
  var MOCK_SAVED_AT = <?= json_encode($current_mock && !empty($current_mock['updated_at']) ? (int)strtotime((string)$current_mock['updated_at']) : 0) ?>;
  var CM_MODE = <?= json_encode(($_editor_mode === 'html') ? 'htmlmixed' : 'application/x-httpd-php') ?>;

  // ── CodeMirror — draft (editable) + original (read-only) ──────────────
  var draftCM = null;
  var ta = document.getElementById('pe-editor-draft');
  if (ta) {
    draftCM = CodeMirror.fromTextArea(ta, {
      mode: CM_MODE, lineNumbers: true,
      matchBrackets: true, autoCloseBrackets: true,
      indentUnit: 2, tabSize: 2, lineWrapping: false,
    });
  }

  // Style override editor (P2) — page-scoped CSS, CSS mode.
  var styleCM = null;
  var taStyle = document.getElementById('pe-editor-style');
  if (taStyle) {
    styleCM = CodeMirror.fromTextArea(taStyle, {
      mode: 'css', lineNumbers: true,
      matchBrackets: true, autoCloseBrackets: true,
      indentUnit: 2, tabSize: 2, lineWrapping: false,
    });
  }

  var originalCM = null;
  var taOrig = document.getElementById('pe-editor-original');
  if (taOrig) {
    originalCM = CodeMirror.fromTextArea(taOrig, {
      mode: CM_MODE, lineNumbers: true,
      readOnly: true, lineWrapping: false,
      cursorBlinkRate: -1,
    });
  }

  // ── Page tab sub-toggle: View / HTML ───────────────────────────────────
  document.querySelectorAll('[data-page-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var name = btn.getAttribute('data-page-toggle');
      document.querySelectorAll('[data-page-view]').forEach(function (p) {
        p.classList.toggle('is-active', p.getAttribute('data-page-view') === name);
      });
      document.querySelectorAll('[data-page-toggle]').forEach(function (b) {
        b.classList.toggle('active', b.getAttribute('data-page-toggle') === name);
      });
      if (name === 'html' && originalCM) { setTimeout(function () { originalCM.refresh(); }, 0); }
    });
  });

  // ── Column toggle — multi-pane view controls ─────────────────────────
  var SLUG = <?= json_encode($slug) ?>;

  // Wire up the filter-pill buttons in the tab bar
  document.querySelectorAll('[data-view-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      peToggleView(btn.getAttribute('data-view-toggle'));
    });
  });

  var peEditPane  = document.querySelector('.pe-pane[data-view="edit"]');
  var pePanesEl   = document.querySelector('.pe-edit-tab .pe-panes');

  function pePreviewPanes() {
    return document.querySelectorAll('.pe-preview-area .pe-pane');
  }
  function peAnyPreviewActive() {
    return Array.prototype.some.call(pePreviewPanes(), function (p) {
      return p.classList.contains('is-active');
    });
  }

  // Sync editor width: full when no previews, explicit 50% when first preview opens,
  // preserve explicit width when second preview opens or closes.
  function peSyncEditorWidth(wasActive, activating) {
    if (!peEditPane || !pePanesEl) return;
    var nowAny = peAnyPreviewActive();
    if (nowAny && !peEditPane.style.width) {
      // First preview opening — snap editor to 50%
      peEditPane.style.flex  = 'none';
      peEditPane.style.width = (pePanesEl.getBoundingClientRect().width * 0.5) + 'px';
    } else if (!nowAny) {
      // All previews closed — editor fills full width
      peEditPane.style.flex  = '';
      peEditPane.style.width = '';
    }
    if (draftCM) setTimeout(function () { draftCM.refresh(); }, 0);
  }

  // Live pane loads lazily on activation, fresh + cache-busted. An eager src
  // set at page load renders inside a display:none pane and comes up blank or
  // stale when revealed; loading on toggle mirrors the preview pane and always
  // reflects the currently-published page.
  function peLoadLive() {
    var iframe = document.getElementById('pe-live-iframe');
    if (!iframe) return;
    var base = iframe.getAttribute('data-live-src') || '';
    if (!base) return;
    iframe.src = base + (base.indexOf('?') !== -1 ? '&' : '?') + '_t=' + Date.now();
  }

  window.peToggleView = function (name) {
    var pane = document.querySelector('.pe-pane[data-view="' + name + '"]');
    var btn  = document.querySelector('[data-view-toggle="' + name + '"]');
    if (!pane || !btn) return;

    var activeCount = document.querySelectorAll('.pe-edit-tab .pe-pane.is-active').length;
    // Can't toggle off the last active pane in the edit tab
    if (pane.classList.contains('is-active') && activeCount === 1) return;

    var activating = !pane.classList.contains('is-active');
    pane.classList.toggle('is-active');
    btn.classList.toggle('active');

    peSyncEditorWidth();

    if (activating) {
      if (name === 'preview') peLoadPreview();
      if (name === 'live') peLoadLive();
      if (name === 'style' && styleCM) setTimeout(function () { styleCM.refresh(); }, 0);
    }
  };

  // ── Live preview — overlay + debounced auto-refresh ──────────────────
  var pePreviewDebounce = null;
  var peOverlay = document.getElementById('pe-preview-overlay');

  function peIsPreviewActive() {
    var p = document.querySelector('.pe-pane[data-view="preview"]');
    return p && p.classList.contains('is-active');
  }
  function peShowOverlay() {
    if (!peOverlay) return;
    peOverlay.classList.remove('is-instant');
    peOverlay.classList.add('is-active');
  }
  function peHideOverlay() {
    if (!peOverlay) return;
    peOverlay.classList.add('is-instant');   // no fade on dismiss
    peOverlay.classList.remove('is-active');
  }

  function peLoadPreview() {
    var iframe = document.getElementById('pe-preview-iframe');
    if (!iframe) return;
    var fd = new FormData();
    fd.append('slug',      SLUG);
    fd.append('body_html', draftCM ? draftCM.getValue() : '');
    fd.append('style_css', styleCM ? styleCM.getValue() : '');
    fetch('/cms/pages/preview-form', { method: 'POST', body: fd })
      .then(function (r) {
        if (!r.ok) return r.text().then(function (t) { throw new Error('Server ' + r.status + ': ' + t); });
        return r.text();
      })
      .then(function (html) { iframe.srcdoc = html; })
      .catch(function (err) {
        iframe.srcdoc = '<pre style="padding:16px;font-family:monospace;color:red">Preview error: ' + err + '</pre>';
      })
      .finally(peHideOverlay);
  }

  // ── Save draft — explicit dirty-flip pattern ─────────────────────────
  var peIsDirty = false;
  var peSaveBtn    = document.getElementById('pe-save-btn');
  var pePublishBtn = document.getElementById('pe-publish-btn');

  function peMarkDirty() {
    if (peIsDirty) return;
    peIsDirty = true;
    if (peSaveBtn)    { peSaveBtn.classList.remove('btn-sec'); peSaveBtn.classList.add('btn-pri'); }
    if (pePublishBtn) { pePublishBtn.disabled = true; pePublishBtn.title = 'Save draft before publishing'; }
  }
  function peMarkClean() {
    peIsDirty = false;
    if (peSaveBtn)    { peSaveBtn.classList.remove('btn-pri'); peSaveBtn.classList.add('btn-sec'); }
    if (pePublishBtn) { pePublishBtn.disabled = false; pePublishBtn.title = ''; }
  }

  function peOnEditorChange() {
    peMarkDirty();
    if (!peIsPreviewActive()) return;
    peShowOverlay();
    clearTimeout(pePreviewDebounce);
    pePreviewDebounce = setTimeout(peLoadPreview, 800);
  }
  if (draftCM) { draftCM.on('change', peOnEditorChange); }
  if (styleCM) { styleCM.on('change', peOnEditorChange); }

  // ── Resize handle — fluid drag between editor and preview area ────────
  (function () {
    var handle  = document.getElementById('pe-resize-handle');
    if (!handle || !peEditPane || !pePanesEl) return;

    var resizing = false, startX = 0, startW = 0, totalW = 0;
    var shield   = null; // full-screen drag shield so iframes are never touched

    function onMouseMove(e) {
      var newW = Math.max(240, Math.min(startW + (e.clientX - startX), totalW - 240));
      peEditPane.style.width = newW + 'px';
    }

    function onMouseUp() {
      resizing = false;
      handle.classList.remove('is-dragging');
      document.body.style.cursor     = '';
      document.body.style.userSelect = '';
      if (shield) { document.body.removeChild(shield); shield = null; }
      document.removeEventListener('mousemove', onMouseMove);
      document.removeEventListener('mouseup',   onMouseUp);
      if (draftCM) draftCM.refresh();
    }

    handle.addEventListener('mousedown', function (e) {
      resizing = true;
      startX   = e.clientX;
      startW   = peEditPane.getBoundingClientRect().width;
      totalW   = pePanesEl.getBoundingClientRect().width;
      peEditPane.style.flex = 'none';
      handle.classList.add('is-dragging');
      document.body.style.cursor     = 'col-resize';
      document.body.style.userSelect = 'none';
      // Transparent shield covers the full viewport so iframes never
      // capture the mouse — removed on mouseup, no iframe state touched.
      shield = document.createElement('div');
      shield.style.cssText = 'position:fixed;inset:0;z-index:9999;cursor:col-resize;';
      document.body.appendChild(shield);
      document.addEventListener('mousemove', onMouseMove);
      document.addEventListener('mouseup',   onMouseUp);
      e.preventDefault();
    });
  }());

  var peStatusEl = document.getElementById('pe-save-status');

  function peRelTime(epoch) {
    var diff = Math.floor(Date.now() / 1000) - epoch;
    if (diff < 60)    return 'Saved just now';
    if (diff < 3600)  return 'Saved ' + Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return 'Saved ' + Math.floor(diff / 3600) + ' hr ago';
    return 'Saved ' + new Date(epoch * 1000).toLocaleDateString('en-CA', { month: 'short', day: 'numeric' });
  }

  if (peStatusEl && MOCK_SAVED_AT) {
    peStatusEl.textContent = peRelTime(MOCK_SAVED_AT);
  }

  window.peSaveDraft = function () {
    if (!draftCM || MOCK_ID <= 0) return;
    if (peSaveBtn) { peSaveBtn.textContent = 'Saving…'; peSaveBtn.disabled = true; }
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('id', String(MOCK_ID));
    fd.append('body_html', draftCM.getValue());
    fd.append('style_css', styleCM ? styleCM.getValue() : '');
    fetch('/cms/pages/autosave', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok) {
          peMarkClean();
          if (peStatusEl) peStatusEl.textContent = peRelTime(Math.floor(Date.now() / 1000));
        } else {
          if (peStatusEl) peStatusEl.textContent = 'Save failed';
        }
      })
      .catch(function () {
        if (peStatusEl) peStatusEl.textContent = 'Save failed';
      })
      .finally(function () {
        if (peSaveBtn) { peSaveBtn.textContent = 'Save draft'; peSaveBtn.disabled = false; }
      });
  };

  window.addEventListener('beforeunload', function (e) {
    if (peIsDirty) { e.preventDefault(); }
  });

  // ── Draft selector ──────────────────────────────────────────────────
  window.peSwitchVersion = function (vid) {
    location.href = '/cms/pages/edit?slug=<?= rawurlencode($slug) ?>&tab=edit&version_id=' + encodeURIComponent(vid);
  };

  // ── Prompt-driven actions ──────────────────────────────────────────
  window.peCreateMock = function () {
    var name = prompt('Name this draft (e.g. "Tighter intro")', '');
    if (!name) return;
    document.getElementById('pe-create-name').value = name;
    document.getElementById('pe-create-form').submit();
  };
  window.peRenameMock = function () {
    var cur  = <?= json_encode($_mock_name_js) ?>;
    var name = prompt('Rename to:', cur);
    if (!name || name === cur) return;
    document.getElementById('pe-rename-name').value = name;
    document.getElementById('pe-rename-form').submit();
  };
  window.peDuplicateMock = function () {
    var name = prompt('Name the duplicate:', <?= json_encode($_mock_name_js) ?> + ' (copy)');
    if (!name) return;
    document.getElementById('pe-duplicate-name').value = name;
    document.getElementById('pe-duplicate-form').submit();
  };

  // ── Metadata unfurl ────────────────────────────────────────────────
  window.peUnfurlSync = function () {
    var t  = document.getElementById('pe-meta-title');
    var d  = document.getElementById('pe-meta-desc');
    var im = document.getElementById('pe-og-image');
    if (t)  document.getElementById('pe-unfurl-title').textContent = t.value || <?= json_encode(page_display_name($slug)) ?>;
    if (d)  document.getElementById('pe-unfurl-desc').textContent  = d.value;
    if (im) document.getElementById('pe-unfurl-img').style.backgroundImage =
              im.value ? "url('" + im.value.replace(/'/g, "\\'") + "')" : '';
  };

  // ── Metadata dirty-flip ─────────────────────────────────────────────
  (function () {
    var metaBtn = document.getElementById('pe-meta-save-btn');
    if (!metaBtn) return;
    var metaForm = metaBtn.closest('form');
    if (!metaForm) return;
    metaForm.addEventListener('input', function () {
      metaBtn.classList.replace('btn-sec', 'btn-pri');
    });
    metaForm.addEventListener('change', function () {
      metaBtn.classList.replace('btn-sec', 'btn-pri');
    });
  })();

  // ── Close ⋯ dropdown when clicking outside it ───────────────────────
  document.addEventListener('click', function (e) {
    if (!e.target.closest('.pe-overflow')) {
      document.querySelectorAll('.pe-overflow[open]').forEach(function (d) {
        d.removeAttribute('open');
      });
    }
  });

  // ── Fade-in ─────────────────────────────────────────────────────────
  (function () {
    function mark(el) { el.classList.add('is-loaded'); }
    function go() { document.querySelectorAll('.fade-on-load:not(iframe)').forEach(mark); }
    if (document.readyState !== 'loading') go(); else document.addEventListener('DOMContentLoaded', go);
  })();
})();
</script>

</body>
</html>
