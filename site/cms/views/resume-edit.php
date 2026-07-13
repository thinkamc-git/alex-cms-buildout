<?php
/**
 * cms/views/resume-edit.php — Library → Resumes editor.
 *
 * Single-record résumé: Edit · Configure · PDF Exports tabs.
 * Edit tab: HTML · Style · Draft Preview · Live panes (defaults to Live).
 * Configure tab: public URL + snapshot archive.
 * PDF Exports tab: drag-drop upload + date/note table.
 *
 * Mirrors the Pages editor (page-edit.php) pattern throughout.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/resumes.php';

Auth::require_login();
$csrf_token = Csrf::token();

$errors = [];
$flash  = '';

// ── POST handlers ─────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'publish') {
            if (publish_resume()) {
                header('Location: /cms/resumes?tab=edit&pane=live&flash=' . rawurlencode('Published — deploy to push to production.'));
                exit;
            }
            $errors[] = 'Could not publish.';
        } elseif ($action === 'restore_snapshot') {
            $snap_id = (int)($_POST['snapshot_id'] ?? 0);
            if ($snap_id > 0 && restore_resume_snapshot($snap_id)) {
                header('Location: /cms/resumes?tab=edit&flash=' . rawurlencode('Snapshot restored to draft — review before publishing.'));
                exit;
            }
            $errors[] = 'Could not restore snapshot.';
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// ── Load state ────────────────────────────────────────────────────────
$resume    = get_resume();
$snapshots = list_resume_snapshots();
$pdfs      = list_resume_pdfs();

$draft_html = $resume ? (string)$resume['draft_html'] : '';
$draft_css  = $resume ? (string)($resume['draft_css'] ?? '') : '';
$is_pub     = $resume && (bool)$resume['is_published'];
$last_pub   = ($resume && !empty($resume['last_published']))
    ? (int)strtotime((string)$resume['last_published'])
    : null;

// ── Tab routing ───────────────────────────────────────────────────────
$tab  = (string)($_GET['tab']  ?? ($is_pub ? 'page' : 'edit'));
$pane = (string)($_GET['pane'] ?? 'html');   // default pane within Edit tab
if (!in_array($tab, ['page', 'edit', 'configure', 'pdfs'], true)) $tab = $is_pub ? 'page' : 'edit';
if (!in_array($pane, ['html', 'style', 'preview', 'live'], true)) $pane = 'html';

$live_url    = '/resume/';
$live_url_cb = $live_url . '?_t=' . time();
$preview_url = '/cms/resumes/preview';

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');
$e = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

function rv_rel_time(int $epoch): string
{
    if ($epoch <= 0) return '<span class="muted">—</span>';
    $diff = time() - $epoch;
    if ($diff < 60)       return '<span class="rel-time"><span class="rel-full">just now</span><span class="rel-short" aria-hidden="true">now</span></span>';
    if ($diff < 3600)   { $n = (int)floor($diff / 60);    return '<span class="rel-time"><span class="rel-full">' . $n . ' min ago</span><span class="rel-short" aria-hidden="true">' . $n . 'm</span></span>'; }
    if ($diff < 86400)  { $n = (int)floor($diff / 3600);  return '<span class="rel-time"><span class="rel-full">' . $n . ' hr ago</span><span class="rel-short" aria-hidden="true">' . $n . 'h</span></span>'; }
    if ($diff < 86400 * 30) { $n = (int)floor($diff / 86400); return '<span class="rel-time"><span class="rel-full">' . $n . ' days ago</span><span class="rel-short" aria-hidden="true">' . $n . 'd</span></span>'; }
    return '<span class="muted">' . htmlspecialchars(date('M j', $epoch), ENT_QUOTES, 'UTF-8') . '</span>';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Résumé — alexmchong.ca CMS</title>
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
  /* ── Save status ── */
  #rv-save-status { font-size: var(--text-micro); font-family: var(--font-mono); color: var(--muted); }

  /* ── CodeMirror ── */
  .CodeMirror { border: 1px solid var(--border); border-radius: 4px; font-size: 13px; height: 560px; }

  .is-hidden-tab { display: none !important; }

  /* ── Tabs + view pills ── */
  .rv-view-pills {
    margin-left: auto;
    display: flex;
    align-items: center;
    padding: 0 var(--space-16);
    border-left: var(--rule-faint);
    gap: 3px;
  }

  /* ── Page + Edit tabs: full-bleed flex column ── */
  #rv-view .rv-page-tab:not(.is-hidden-tab),
  #rv-view .rv-edit-tab:not(.is-hidden-tab) {
    flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden;
  }

  /* ── Panes row ── */
  .rv-panes { display: flex; flex: 1; min-height: 0; overflow: hidden; }
  .rv-pane  { display: none; flex-direction: column; flex: 1; min-width: 0; overflow: hidden; }
  .rv-pane.is-active { display: flex; }
  .rv-pane.is-active + .rv-pane.is-active { border-left: var(--rule-faint); }

  /* ── HTML pane: CodeMirror fills height ── */
  .rv-pane[data-view="html"] #rv-html-panel {
    flex: 1; display: flex; flex-direction: column;
    padding: var(--space-16) var(--space-20); overflow: hidden; min-height: 0;
  }
  .rv-pane[data-view="html"] .CodeMirror { flex: 1; height: auto; min-height: 200px; }

  /* ── Style pane: CodeMirror fills height ── */
  .rv-pane[data-view="style"] #rv-style-panel {
    flex: 1; display: flex; flex-direction: column;
    padding: var(--space-16) var(--space-20); overflow: hidden; min-height: 0;
  }
  .rv-pane[data-view="style"] .CodeMirror { flex: 1; height: auto; min-height: 200px; }

  /* ── Pane notice bar ── */
  .rv-pane-notice {
    font-family: var(--font-mono); font-size: var(--text-micro);
    color: var(--muted); padding: var(--space-8) var(--space-16);
    background: var(--canvas-raised); border-bottom: var(--rule-faint); flex-shrink: 0;
  }

  /* ── Preview / Live frames ── */
  .rv-pane-frame { flex: 1; overflow: hidden; background: var(--canvas-bg); position: relative; }
  .rv-pane-frame iframe { width: 100%; height: 100%; border: none; display: block; }

  /* ── Preview overlay spinner ── */
  .rv-preview-overlay {
    position: absolute; inset: 0; background: rgba(0,0,0,0.1);
    opacity: 0; pointer-events: none; z-index: 2;
    transition: opacity 0.25s ease;
    display: flex; align-items: center; justify-content: center;
  }
  .rv-preview-overlay.is-active  { opacity: 1; }
  .rv-preview-overlay.is-instant { transition: none; }
  .rv-preview-overlay::after {
    content: ''; width: 22px; height: 22px;
    border: 2px solid rgba(255,255,255,0.4);
    border-top-color: rgba(255,255,255,0.9);
    border-radius: 50%; animation: rv-spin 0.7s linear infinite;
  }
  @keyframes rv-spin { to { transform: rotate(360deg); } }

  /* ── Resize handle ── */
  .rv-resize-handle {
    width: 6px; flex-shrink: 0; background: var(--ink-18);
    cursor: col-resize; display: none; position: relative; transition: background 0.1s;
  }
  .rv-resize-handle::before { content: ''; position: absolute; inset: 0 -5px; cursor: col-resize; }
  .rv-resize-handle::after {
    content: ''; position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%); width: 2px; height: 24px;
    background: repeating-linear-gradient(to bottom, var(--ink-30) 0 2px, transparent 2px 5px);
    border-radius: 1px;
  }
  .rv-resize-handle:hover, .rv-resize-handle.is-dragging { background: var(--ink-12, var(--border)); }
  .rv-pane[data-view="html"].is-active + .rv-resize-handle { display: block; }

  /* ── Preview area ── */
  .rv-preview-area { display: none; flex: 1; min-width: 0; overflow: hidden; }
  .rv-preview-area:has(.rv-pane.is-active) { display: flex; }
  .rv-preview-area .rv-pane.is-active + .rv-pane.is-active { border-left: var(--rule-faint); }

  /* ── Bottom action bar ── */
  .rv-actions-bar {
    padding: var(--space-12) var(--space-20); background: var(--canvas-raised);
    flex-shrink: 0;
  }
  .rv-actions-right { display: flex; align-items: center; gap: var(--space-8); margin-left: auto; }
  .rv-actions-meta  { font-size: var(--text-micro); font-family: var(--font-mono); color: var(--muted); }

  /* ── Configure tab ── */
  .rv-settings-section + .rv-settings-section {
    padding-top: var(--space-32); border-top: 1px solid var(--border); margin-top: var(--space-32);
  }
  .rv-meta-form {
    display: grid; grid-template-columns: 200px 1fr;
    gap: var(--space-12) var(--space-16); align-items: start; max-width: 880px; margin-bottom: var(--space-24);
  }
  .rv-meta-form label {
    font-size: var(--text-micro); color: var(--muted); padding-top: 8px;
    font-family: var(--font-cond); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600;
  }
  .rv-meta-form input[type=text] {
    width: 100%; padding: 8px 10px; border: 1px solid var(--border);
    border-radius: 4px; font-size: 13px; background: var(--surface); color: var(--ink); font-family: var(--font-mono);
  }
  .rv-archive-desc { font-size: var(--text-meta); color: var(--muted); margin: 0 0 var(--space-16); max-width: 520px; }

  /* ── PDF Exports tab ── */
  .rv-pdf-drop-zone {
    border: 2px dotted var(--ink-30); border-radius: 6px;
    padding: var(--space-32); text-align: center;
    color: var(--muted); font-size: var(--text-meta);
    transition: border-color 0.15s, background 0.15s;
    cursor: pointer; margin-bottom: var(--space-24);
    position: relative;
  }
  .rv-pdf-drop-zone.is-dragover {
    border-color: var(--c-forest); background: color-mix(in srgb, var(--c-forest) 12%, transparent);
  }
  .rv-pdf-drop-zone input[type=file] {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
  }
  .rv-pdf-drop-zone-label { font-weight: 600; color: var(--ink); margin-bottom: 4px; }
  .rv-pdf-drop-zone-sub   { font-size: var(--text-micro); }

  /* ── PDF table ── */
  .rv-pdf-list {
    --rowform-cols: 90px minmax(160px, 1fr) minmax(140px, 1.5fr) auto;
  }
  .rv-pdf-filename {
    font-size: var(--text-micro); font-family: var(--font-mono);
    color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    align-self: center;
  }
  .rv-pdf-saved-tag {
    font-size: var(--text-micro); font-family: var(--font-mono);
    color: var(--muted); opacity: 0; transition: opacity 0.3s;
    white-space: nowrap; pointer-events: none; margin-right: var(--space-4);
  }
  .rv-pdf-saved-tag.is-visible { opacity: 1; }

  /* ── Empty Live pane state ── */
  .rv-empty-live {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    height: 100%; gap: var(--space-12); color: var(--muted); font-size: var(--text-meta);
    font-family: var(--font-mono);
  }
</style>
</head>
<body>

<?php
$breadcrumb = 'Library → CV / Résumé';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'resumes';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="rv-view">
      <?php
      $title    = 'Résumé';
      $subtitle = 'Draft → Publish → /resume/ · Deploy pushes to production.';
      $actions  = $is_pub
          ? '<a href="' . $e($live_url) . '" target="_blank" rel="noopener" class="btn-sec">Live /resume/ ↗</a>'
          : '';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $heading = "Couldn't save:";
      require __DIR__ . '/../partials/form-errors.php';
      require __DIR__ . '/../partials/flash.php';
      ?>

      <!-- Tabs: Page | Edit | Configure | PDF Exports + view pills -->
      <div class="cms-tabs" role="tablist">
        <a class="cms-tab <?= $tab === 'page'      ? 'is-active' : '' ?>" role="tab"
           href="?tab=page">Page</a>
        <a class="cms-tab <?= $tab === 'edit'      ? 'is-active' : '' ?>" role="tab"
           href="?tab=edit">Edit</a>
        <a class="cms-tab <?= $tab === 'configure' ? 'is-active' : '' ?>" role="tab"
           href="?tab=configure">Configure</a>
        <a class="cms-tab <?= $tab === 'pdfs'      ? 'is-active' : '' ?>" role="tab"
           href="?tab=pdfs">PDF Exports</a>
        <?php if ($tab === 'edit'): ?>
        <div class="rv-view-pills">
          <div class="filter-group">
            <button type="button" class="filter-pill <?= $pane === 'html'    ? 'active' : '' ?>" data-view-toggle="html">HTML</button>
            <button type="button" class="filter-pill <?= $pane === 'style'   ? 'active' : '' ?>" data-view-toggle="style">Style</button>
            <button type="button" class="filter-pill <?= $pane === 'preview' ? 'active' : '' ?>" data-view-toggle="preview">Preview</button>
            <button type="button" class="filter-pill <?= $pane === 'live'    ? 'active' : '' ?>" data-view-toggle="live">Live version</button>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── PAGE TAB ── -->
      <div class="rv-page-tab reveal-page <?= $tab !== 'page' ? 'is-hidden-tab' : '' ?>">
        <?php if ($is_pub): ?>
          <div class="rv-pane-frame" style="flex:1">
            <iframe id="rv-page-iframe" src="" data-live-src="<?= $e($live_url) ?>"
                    title="Live · /resume/" style="width:100%;height:100%;border:none;display:block;"></iframe>
          </div>
        <?php else: ?>
          <div class="rv-empty-live">
            <span>No published version yet —</span>
            <span>go to Edit, write your résumé, and publish.</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- ── EDIT TAB ── -->
      <div class="rv-edit-tab reveal-page <?= $tab !== 'edit' ? 'is-hidden-tab' : '' ?>">
        <div class="rv-panes">

          <!-- HTML pane -->
          <div class="rv-pane <?= $pane === 'html' ? 'is-active' : '' ?>" data-view="html">
            <div class="rv-pane-notice">HTML — full document (includes &lt;!doctype&gt;, &lt;head&gt;, &lt;body&gt;)</div>
            <div id="rv-html-panel">
              <textarea id="rv-editor-html" class="fade-on-load"><?= $e($draft_html) ?></textarea>
            </div>
          </div>

          <!-- Resize handle -->
          <div class="rv-resize-handle" id="rv-resize-handle"></div>

          <!-- Preview area -->
          <div class="rv-preview-area" id="rv-preview-area">

            <!-- Style pane -->
            <div class="rv-pane <?= $pane === 'style' ? 'is-active' : '' ?>" data-view="style">
              <div class="rv-pane-notice">Style — isolated to this résumé · injected into &lt;head&gt; on publish</div>
              <div id="rv-style-panel">
                <textarea id="rv-editor-style"><?= $e($draft_css) ?></textarea>
              </div>
            </div>

            <!-- Draft Preview pane -->
            <div class="rv-pane <?= $pane === 'preview' ? 'is-active' : '' ?>" data-view="preview">
              <div class="rv-pane-notice">Draft preview — not yet published</div>
              <div class="rv-pane-frame">
                <div class="rv-preview-overlay" id="rv-preview-overlay"></div>
                <iframe id="rv-preview-iframe" title="Draft preview" class="post-preview-iframe"></iframe>
              </div>
            </div>

            <!-- Live version pane -->
            <div class="rv-pane <?= $pane === 'live' ? 'is-active' : '' ?>" data-view="live">
              <div class="rv-pane-notice">Live version — currently published</div>
              <div class="rv-pane-frame">
                <?php if ($is_pub): ?>
                  <iframe id="rv-live-iframe" title="Live · /resume/"
                          data-live-src="<?= $e($live_url) ?>"></iframe>
                <?php else: ?>
                  <div class="rv-empty-live">
                    <span>No published version yet —</span>
                    <span>edit and publish to see a live preview here.</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>

          </div><!-- /.rv-preview-area -->
        </div><!-- /.rv-panes -->

        <!-- Bottom action bar -->
        <div class="form-actions rv-actions-bar">
          <button type="button" class="btn-sec" id="rv-save-btn" onclick="rvSaveDraft()">Save draft</button>
          <span id="rv-save-status"></span>
          <div class="rv-actions-right">
            <?php if ($last_pub !== null): ?>
              <span class="rv-actions-meta">Last published <?= rv_rel_time($last_pub) ?></span>
            <?php endif; ?>
            <button type="submit" form="rv-publish-form" class="btn-pri" id="rv-publish-btn">Publish →</button>
          </div>
        </div>
      </div><!-- /.rv-edit-tab -->

      <!-- ── CONFIGURE TAB ── -->
      <div class="content-area <?= $tab !== 'configure' ? 'is-hidden-tab' : '' ?>">

        <!-- Public URL -->
        <div class="rv-settings-section">
          <div class="content-block-header"><span class="content-block-label">Address</span></div>
          <div class="rv-meta-form">
            <label>Public URL</label>
            <div>
              <input type="text" value="/resume/" disabled style="opacity:.55">
              <div style="font-size:11px;color:var(--muted);padding-top:4px;">The résumé is always served at /resume/ when published.</div>
            </div>
          </div>
        </div>

        <!-- Version history -->
        <div class="rv-settings-section">
          <div class="content-block-header">
            <span class="content-block-label">Version history</span>
            <span class="content-block-sublabel">Snapshots are captured automatically each time you publish.</span>
          </div>
          <?php if (empty($snapshots)): ?>
            <p class="rv-archive-desc muted">No snapshots yet — one will be saved the next time you publish.</p>
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
                  <td><?= rv_rel_time((int)strtotime((string)$snap['created_at'])) ?></td>
                  <td class="muted" style="font-family:var(--font-mono);font-size:var(--text-micro)"><?= number_format((int)$snap['body_len']) ?> chars</td>
                  <td>
                    <form method="post" action="/cms/resumes"
                          data-confirm="Restore '<?= $e((string)$snap['name']) ?>' to your draft? This will overwrite your current draft.">
                      <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                      <input type="hidden" name="action" value="restore_snapshot">
                      <input type="hidden" name="snapshot_id" value="<?= (int)$snap['id'] ?>">
                      <button type="submit" class="btn-sec btn-tiny">Restore to draft</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

      </div><!-- /.configure -->

      <!-- ── PDF EXPORTS TAB ── -->
      <div class="content-area <?= $tab !== 'pdfs' ? 'is-hidden-tab' : '' ?>">
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">PDF Exports</span>
              <span class="content-block-sublabel">Upload exported PDF versions of your résumé.</span>
            </div>
            <span class="content-block-count"><?= count($pdfs) ?> PDF<?= count($pdfs) === 1 ? '' : 's' ?></span>
          </div>

          <!-- Drop zone -->
          <div class="rv-pdf-drop-zone" id="rv-drop-zone" role="button" tabindex="0"
               aria-label="Drop a PDF here or click to browse">
            <input type="file" id="rv-file-input" accept=".pdf,application/pdf"
                   aria-label="Choose PDF file">
            <div class="rv-pdf-drop-zone-label" id="rv-drop-label">Drop PDF here or click to browse</div>
            <div class="rv-pdf-drop-zone-sub">PDF only · 20 MB max</div>
          </div>

          <div id="rv-upload-status" style="margin-bottom:var(--space-16);font-size:var(--text-meta);font-family:var(--font-mono);color:var(--muted);display:none;"></div>

          <!-- PDF table -->
          <?php if (!empty($pdfs)): ?>
          <div class="rowform-list rv-pdf-list reveal" id="rv-pdf-table">
            <div class="rowform-headers">
              <span>Date</span>
              <span>Note</span>
              <span>File</span>
              <span></span>
            </div>
            <?php foreach ($pdfs as $pdf): ?>
              <div class="rowform-row rv-pdf-row" data-id="<?= (int)$pdf['id'] ?>">
                <input type="text"
                       class="rv-pdf-date"
                       value="<?= $e((string)$pdf['pdf_date']) ?>"
                       placeholder="YYYY-MM"
                       maxlength="7"
                       data-field="pdf_date"
                       aria-label="PDF date">
                <input type="text"
                       class="rv-pdf-note"
                       value="<?= $e((string)($pdf['note'] ?? '')) ?>"
                       placeholder="Add a note…"
                       data-field="note"
                       aria-label="PDF note">
                <div class="rv-pdf-filename" title="<?= $e((string)$pdf['original_name']) ?>"><?= $e((string)$pdf['original_name']) ?></div>
                <div style="display:flex;gap:var(--space-8);align-items:center;justify-content:flex-end">
                  <span class="rv-pdf-saved-tag" aria-live="polite">Saved</span>
                  <a href="/cms/resumes/pdf/<?= (int)$pdf['id'] ?>/view"
                     class="btn-sec btn-tiny" target="_blank" rel="noopener">View ↗</a>
                  <a href="/cms/resumes/pdf/<?= (int)$pdf['id'] ?>/download"
                     class="btn-sec btn-tiny" download="<?= $e((string)$pdf['original_name']) ?>">Download</a>
                  <form method="post" action="/cms/resumes/pdf/delete"
                        style="display:inline"
                        data-confirm="Delete this PDF? This cannot be undone.">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                    <input type="hidden" name="id" value="<?= (int)$pdf['id'] ?>">
                    <button type="submit" class="btn-icon btn-icon-danger" title="Delete" aria-label="Delete PDF">
                      <svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p id="rv-pdf-empty" style="font-size:var(--text-meta);color:var(--muted);font-family:var(--font-mono);margin:0">
            No PDFs exported yet — upload one above.
          </p>
          <?php endif; ?>
        </div>
      </div><!-- /.pdfs -->

    </div><!-- /#rv-view -->
  </main>
</div>

<!-- Publish form -->
<form id="rv-publish-form" method="post" action="/cms/resumes"
      data-confirm="Publish to /resume/? Deploy to push to production.">
  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
  <input type="hidden" name="action" value="publish">
</form>

<script src="/cms/_assets/dirty-flip.js" defer></script>
<script src="/cms/_assets/confirm.js" defer></script>
<script src="/cms/_assets/codemirror/codemirror.min.js"></script>
<script src="/cms/_assets/codemirror/mode/xml/xml.min.js"></script>
<script src="/cms/_assets/codemirror/mode/javascript/javascript.min.js"></script>
<script src="/cms/_assets/codemirror/mode/css/css.min.js"></script>
<script src="/cms/_assets/codemirror/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="/cms/_assets/codemirror/addon/edit/matchbrackets.min.js"></script>
<script src="/cms/_assets/codemirror/addon/edit/closebrackets.min.js"></script>
<script>
(function () {
  var CSRF       = <?= json_encode($csrf_token) ?>;
  var RESUME_ID  = 1;
  var SAVED_AT   = <?= json_encode($last_pub) ?>;
  var IS_PUB     = <?= json_encode($is_pub) ?>;
  var PREVIEW_URL = <?= json_encode($preview_url) ?>;
  var LIVE_URL    = <?= json_encode($live_url) ?>;  // /resume/

  // ── CodeMirror — HTML editor ──────────────────────────────────────
  var htmlCM  = null;
  var styleCM = null;

  var taHtml = document.getElementById('rv-editor-html');
  if (taHtml) {
    htmlCM = CodeMirror.fromTextArea(taHtml, {
      mode: 'htmlmixed', lineNumbers: true,
      matchBrackets: true, autoCloseBrackets: true,
      indentUnit: 2, tabSize: 2, lineWrapping: false,
    });
  }

  var taStyle = document.getElementById('rv-editor-style');
  if (taStyle) {
    styleCM = CodeMirror.fromTextArea(taStyle, {
      mode: 'css', lineNumbers: true,
      matchBrackets: true, autoCloseBrackets: true,
      indentUnit: 2, tabSize: 2, lineWrapping: false,
    });
  }

  // ── Pane toggle ───────────────────────────────────────────────────
  var htmlPane    = document.querySelector('.rv-pane[data-view="html"]');
  var panesEl     = document.querySelector('.rv-panes');

  function previewPanes() {
    return document.querySelectorAll('.rv-preview-area .rv-pane');
  }
  function anyPreviewActive() {
    return Array.prototype.some.call(previewPanes(), function (p) {
      return p.classList.contains('is-active');
    });
  }

  function syncEditorWidth() {
    if (!htmlPane || !panesEl) return;
    var nowAny = anyPreviewActive();
    if (nowAny && !htmlPane.style.width) {
      htmlPane.style.flex  = 'none';
      htmlPane.style.width = (panesEl.getBoundingClientRect().width * 0.5) + 'px';
    } else if (!nowAny) {
      htmlPane.style.flex  = '';
      htmlPane.style.width = '';
    }
    if (htmlCM) setTimeout(function () { htmlCM.refresh(); }, 0);
  }

  function loadLive() {
    var iframe = document.getElementById('rv-live-iframe');
    if (!iframe) return;
    var base = iframe.getAttribute('data-live-src') || LIVE_URL;
    iframe.src = base + (base.indexOf('?') !== -1 ? '&' : '?') + '_t=' + Date.now();
  }

  document.querySelectorAll('[data-view-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      rvToggleView(btn.getAttribute('data-view-toggle'));
    });
  });

  window.rvToggleView = function (name) {
    var pane = document.querySelector('.rv-pane[data-view="' + name + '"]');
    var btn  = document.querySelector('[data-view-toggle="' + name + '"]');
    if (!pane || !btn) return;

    var activeCount = document.querySelectorAll('.rv-edit-tab .rv-pane.is-active').length;
    if (pane.classList.contains('is-active') && activeCount === 1) return;

    var activating = !pane.classList.contains('is-active');
    pane.classList.toggle('is-active');
    btn.classList.toggle('active');
    syncEditorWidth();

    if (activating) {
      if (name === 'preview') rvLoadPreview();
      if (name === 'live')    loadLive();
      if (name === 'style' && styleCM) setTimeout(function () { styleCM.refresh(); }, 0);
    }
  };

  // ── Draft Preview ──────────────────────────────────────────────────
  var previewDebounce = null;
  var overlay = document.getElementById('rv-preview-overlay');

  function isPreviewActive() {
    var p = document.querySelector('.rv-pane[data-view="preview"]');
    return p && p.classList.contains('is-active');
  }
  function showOverlay() { if (overlay) { overlay.classList.remove('is-instant'); overlay.classList.add('is-active'); } }
  function hideOverlay() { if (overlay) { overlay.classList.add('is-instant'); overlay.classList.remove('is-active'); } }

  function rvLoadPreview() {
    var iframe = document.getElementById('rv-preview-iframe');
    if (!iframe) return;
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('html', htmlCM ? htmlCM.getValue() : '');
    fd.append('css',  styleCM ? styleCM.getValue() : '');
    fetch('/cms/resumes/preview', { method: 'POST', body: fd })
      .then(function (r) { return r.text(); })
      .then(function (html) { iframe.srcdoc = html; })
      .catch(function (err) {
        iframe.srcdoc = '<pre style="padding:16px;font-family:monospace;color:red">Preview error: ' + err + '</pre>';
      })
      .finally(hideOverlay);
  }

  // ── Dirty-flip + autosave ──────────────────────────────────────────
  var isDirty    = false;
  var saveBtn    = document.getElementById('rv-save-btn');
  var publishBtn = document.getElementById('rv-publish-btn');
  var statusEl   = document.getElementById('rv-save-status');

  function markDirty() {
    if (isDirty) return;
    isDirty = true;
    if (saveBtn)    { saveBtn.classList.remove('btn-sec'); saveBtn.classList.add('btn-pri'); }
    if (publishBtn) { publishBtn.disabled = true; publishBtn.title = 'Save draft before publishing'; }
  }
  function markClean() {
    isDirty = false;
    if (saveBtn)    { saveBtn.classList.remove('btn-pri'); saveBtn.classList.add('btn-sec'); }
    if (publishBtn) { publishBtn.disabled = false; publishBtn.title = ''; }
  }

  function relTime(epoch) {
    var diff = Math.floor(Date.now() / 1000) - epoch;
    if (diff < 60)    return 'Saved just now';
    if (diff < 3600)  return 'Saved ' + Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return 'Saved ' + Math.floor(diff / 3600) + ' hr ago';
    return 'Saved ' + new Date(epoch * 1000).toLocaleDateString('en-CA', { month: 'short', day: 'numeric' });
  }

  if (statusEl && SAVED_AT) { statusEl.textContent = relTime(SAVED_AT); }

  function onEditorChange() {
    markDirty();
    if (!isPreviewActive()) return;
    showOverlay();
    clearTimeout(previewDebounce);
    previewDebounce = setTimeout(rvLoadPreview, 800);
  }
  if (htmlCM)  { htmlCM.on('change',  onEditorChange); }
  if (styleCM) { styleCM.on('change', onEditorChange); }

  window.rvSaveDraft = function () {
    if (!htmlCM) return;
    if (saveBtn) { saveBtn.textContent = 'Saving…'; saveBtn.disabled = true; }
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('html', htmlCM.getValue());
    fd.append('css',  styleCM ? styleCM.getValue() : '');
    fetch('/cms/resumes/autosave', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok) {
          markClean();
          if (statusEl) statusEl.textContent = relTime(Math.floor(Date.now() / 1000));
        } else {
          if (statusEl) statusEl.textContent = 'Save failed';
        }
      })
      .catch(function () { if (statusEl) statusEl.textContent = 'Save failed'; })
      .finally(function () {
        if (saveBtn) { saveBtn.textContent = 'Save draft'; saveBtn.disabled = false; }
      });
  };

  window.addEventListener('beforeunload', function (e) { if (isDirty) e.preventDefault(); });

  // ── Resize handle ─────────────────────────────────────────────────
  (function () {
    var handle = document.getElementById('rv-resize-handle');
    if (!handle || !htmlPane || !panesEl) return;
    var resizing = false, startX = 0, startW = 0, totalW = 0, shield = null;

    function onMove(e) {
      var newW = Math.max(240, Math.min(startW + (e.clientX - startX), totalW - 240));
      htmlPane.style.width = newW + 'px';
    }
    function onUp() {
      resizing = false;
      handle.classList.remove('is-dragging');
      document.body.style.cursor     = '';
      document.body.style.userSelect = '';
      if (shield) { document.body.removeChild(shield); shield = null; }
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup',   onUp);
      if (htmlCM) htmlCM.refresh();
    }
    handle.addEventListener('mousedown', function (e) {
      resizing = true;
      startX   = e.clientX;
      startW   = htmlPane.getBoundingClientRect().width;
      totalW   = panesEl.getBoundingClientRect().width;
      htmlPane.style.flex = 'none';
      handle.classList.add('is-dragging');
      document.body.style.cursor     = 'col-resize';
      document.body.style.userSelect = 'none';
      shield = document.createElement('div');
      shield.style.cssText = 'position:fixed;inset:0;z-index:9999;cursor:col-resize;';
      document.body.appendChild(shield);
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup',   onUp);
      e.preventDefault();
    });
  }());

  // ── PDF Exports: drag-drop upload ─────────────────────────────────
  (function () {
    var zone      = document.getElementById('rv-drop-zone');
    var fileInput = document.getElementById('rv-file-input');
    var status    = document.getElementById('rv-upload-status');
    var dropLabel = document.getElementById('rv-drop-label');
    var defaultLabel = dropLabel ? dropLabel.textContent : '';
    if (!zone || !fileInput) return;

    function setLabel(name) {
      if (!dropLabel) return;
      dropLabel.textContent = name || defaultLabel;
    }

    function showStatus(msg, isError) {
      if (!status) return;
      status.style.display = 'block';
      status.style.color   = isError ? 'var(--c-terracotta)' : 'var(--muted)';
      status.textContent   = msg;
    }

    zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('is-dragover'); });
    zone.addEventListener('dragleave', function ()  { zone.classList.remove('is-dragover'); });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      zone.classList.remove('is-dragover');
      var files = e.dataTransfer && e.dataTransfer.files;
      if (files && files.length) { setLabel(files[0].name); uploadFile(files[0]); }
    });

    fileInput.addEventListener('change', function () {
      if (this.files && this.files.length) { setLabel(this.files[0].name); uploadFile(this.files[0]); }
      this.value = '';
    });

    function uploadFile(file) {
      if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
        setLabel('');
        showStatus('Please upload a PDF file.', true);
        return;
      }
      showStatus('Uploading…', false);

      var fd = new FormData();
      fd.append('csrf_token', CSRF);
      fd.append('pdf_file',   file);

      fetch('/cms/resumes/pdf/upload', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok) {
            showStatus('Uploaded. Refreshing…', false);
            setLabel('');
            location.reload();
          } else {
            setLabel('');
            showStatus('Upload failed: ' + (j.error || 'unknown error'), true);
          }
        })
        .catch(function (err) { showStatus('Upload failed: ' + err, true); });
    }
  }());

  // ── PDF inline save (date + note autosave on blur) ─────────────────
  (function () {
    document.querySelectorAll('.rv-pdf-row').forEach(function (row) {
      var id      = row.getAttribute('data-id');
      var savedEl = row.querySelector('.rv-pdf-saved-tag');
      var timer   = null;

      function showSaved() {
        if (!savedEl) return;
        savedEl.classList.add('is-visible');
        clearTimeout(timer);
        timer = setTimeout(function () { savedEl.classList.remove('is-visible'); }, 2500);
      }

      function saveRow() {
        var dateEl = row.querySelector('.rv-pdf-date');
        var noteEl = row.querySelector('.rv-pdf-note');
        if (!dateEl || !noteEl) return;
        var fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('id',       id);
        fd.append('pdf_date', dateEl.value);
        fd.append('note',     noteEl.value);
        fetch('/cms/resumes/pdf/save', { method: 'POST', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (j) { if (j.ok) showSaved(); });
      }

      row.querySelectorAll('.rv-pdf-date, .rv-pdf-note').forEach(function (input) {
        input.addEventListener('blur', saveRow);
      });

      // YYYY-MM format helper: auto-insert dash after 4 digits.
      var dateInput = row.querySelector('.rv-pdf-date');
      if (dateInput) {
        dateInput.addEventListener('input', function () {
          var v = this.value.replace(/[^0-9]/g, '');
          if (v.length > 4) { v = v.slice(0, 4) + '-' + v.slice(4, 6); }
          this.value = v;
        });
      }
    });
  }());

  // ── Auto-load iframes on initial render ──────────────────────────
  // Page tab: load the live iframe when the page tab is active on load.
  if (<?= json_encode($tab === 'page' && $is_pub) ?>) {
    var pageIframe = document.getElementById('rv-page-iframe');
    if (pageIframe) {
      var src = pageIframe.getAttribute('data-live-src') || LIVE_URL;
      pageIframe.src = src + (src.indexOf('?') !== -1 ? '&' : '?') + '_t=' + Date.now();
    }
  }
  // Edit tab / Live version pane: load when that pane is active on load.
  if (<?= json_encode($tab === 'edit' && $pane === 'live' && $is_pub) ?>) {
    loadLive();
  }

  // ── Fade-in ───────────────────────────────────────────────────────
  (function () {
    function mark(el) { el.classList.add('is-loaded'); }
    function go()     { document.querySelectorAll('.fade-on-load:not(iframe)').forEach(mark); }
    if (document.readyState !== 'loading') go();
    else document.addEventListener('DOMContentLoaded', go);
  }());

})();
</script>

</body>
</html>
