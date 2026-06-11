<?php
/**
 * cms/views/post-template.php — Post Template admin (Phase 14.5).
 *
 * Read-only port of the design-mockups Content Template view. (Author editing
 * moved to Settings → Author Info; this view is now a read-only catalogue.)
 *
 * Left pane: Master Template card + 6 sub-template rows.
 *   Click → updates ?tpl=<slug>.
 *
 * Right pane:
 *   - tpl=master → 4 tabs (Content Blocks / Field Reference / PHP Layout File / Preview)
 *   - tpl=<sub-template> → single panel (visibility table + PHP file preview)
 *     No "Save Template" button — sub-template visibility is read-only in α.
 *
 * Data layer: lib/blocks_data.php (hardcoded, sourced from docs/BLOCKS.md).
 *
 * No new schema; no render-layer changes; staging-only ship.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/blocks_data.php';

Auth::require_login();
$csrf_token = Csrf::token();

$errors = [];
$flash  = '';

// Author editing moved to Settings → Author Info (Phase: Author Info). This
// view is now a read-only template catalogue.

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// State
$blocks = blocks_reference();
$fields = fields_reference();
$subs   = sub_templates_reference();
$matrix = content_type_matrix();
$notes  = block_mode_notes();

$selected = (string)($_GET['tpl'] ?? 'master');
$validTpl = array_merge(['master'], array_keys($subs));
if (!in_array($selected, $validTpl, true)) $selected = 'master';

// Master uses: blocks / fields / php / preview
// Sub uses:    visibility / php / preview
$isSub = ($selected !== 'master');
$defaultTab = $isSub ? 'visibility' : 'blocks';
$activeTab = (string)($_GET['tab'] ?? $defaultTab);
$validTab  = $isSub
    ? ['visibility', 'php', 'preview']
    : ['blocks', 'fields', 'php', 'preview'];
if (!in_array($activeTab, $validTab, true)) $activeTab = $defaultTab;

// Template file reader for the PHP Layout File tab / sub-template preview.
// realpath() guards against path traversal even though inputs are sub-template slugs.
$readTemplate = static function (string $relName): ?string {
    $base = realpath(__DIR__ . '/../../templates');
    if ($base === false) return null;
    $full = realpath($base . '/' . $relName);
    if ($full === false) return null;
    if (strpos($full, $base . DIRECTORY_SEPARATOR) !== 0) return null;
    if (!is_file($full)) return null;
    $content = file_get_contents($full);
    return $content === false ? null : $content;
};

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

// Build a tab href that preserves the selected template.
$tabHref = static function (string $tab) use ($selected): string {
    return '/cms/post-template?tpl=' . rawurlencode($selected) . '&tab=' . rawurlencode($tab);
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Post Templates — alexmchong.ca CMS</title>
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
  /* View-specific tweaks layered on top of style-cms.css's .templates-layout. */
  #view-post-template { display:flex; flex-direction:column; flex:1; min-height:0; }
  /* Make panels behave block-style — we render one panel at a time server-side
     rather than the mockup's JS-toggled .tpl-panel.active pattern. */
  .tpl-panel.is-server-active { display:block; }
  /* "Optional" mode pill — base .block-mode-pill exists but mode-optional doesn't
     have its own color in style-cms.css. Lean italic + muted for read-only feel. */
  .block-mode-pill.mode-optional { color:var(--muted); font-style:italic; }
  /* PHP file preview viewer — CodeMirror-driven, syntax-highlighted. */
  .ct-code-editor + .CodeMirror { border:1px solid var(--border); border-radius:var(--r-card); font-size:13px; height:520px; }
  .ct-code-missing { font-family:var(--font-mono); font-size:var(--text-meta); color:var(--muted); border:1px dashed var(--ink-18); padding:var(--space-24); text-align:center; border-radius:var(--r-card); }
  /* Required asterisk. */
  .field-req { font-family:var(--font-cond); font-size:10px; color:var(--c-terracotta); margin-left:var(--space-4); letter-spacing:0.06em; text-transform:uppercase; }
  /* Phase 14.5 read-only note on sub-template panels. */
  /* .ct-readonly-note rule moved to style-cms.css (Batch 2 #28). */
</style>
</head>
<body>

<?php
$breadcrumb = 'Post Templates';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'post-templates';
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-post-template">
      <?php
      $title    = 'Post Templates';
      $subtitle = "Each content type has a PHP layout file that controls how its fields render on the public site. The Master template lists every available field and its PHP variable — it's a reference, not a switchboard. Each sub-template inherits everything and can suppress specific fields.";
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $heading = "Couldn't save:";
      require __DIR__ . '/../partials/form-errors.php';
      require __DIR__ . '/../partials/flash.php';
      ?>

      <div class="templates-layout">
        <!-- ═══ LEFT: Template list ═══ -->
        <div class="template-list">
          <a class="tpl-master<?= $selected === 'master' ? ' active' : '' ?>"
             href="/cms/post-template?tpl=master&tab=blocks"
             style="display:block;text-decoration:none">
            <div class="tpl-master-label">Reference</div>
            <div class="tpl-master-name">Master Template</div>
            <div class="tpl-master-desc">Defines every block, field, and the author profile. Each sub-template inherits all of this — sub-templates only suppress optional blocks.</div>
          </a>
          <?php foreach ($subs as $slug => $info): ?>
            <a class="tpl-item<?= $selected === $slug ? ' active' : '' ?>"
               href="/cms/post-template?tpl=<?= $e($slug) ?>"
               style="display:block;text-decoration:none">
              <div class="tpl-item-name"><?= $e($info['name']) ?><span class="tpl-sys">system</span></div>
              <div class="tpl-item-desc"><?= $e($info['desc']) ?></div>
            </a>
          <?php endforeach; ?>
        </div>

        <!-- ═══ RIGHT: Detail panel ═══ -->
        <div class="tpl-detail">
          <?php if ($selected === 'master'): ?>
            <!-- ─── Master Template: 4 tabs ─── -->
            <div class="cms-tabs">
              <a class="cms-tab<?= $activeTab === 'blocks' ? ' active' : '' ?>" href="<?= $e($tabHref('blocks')) ?>" style="text-decoration:none">Content Blocks</a>
              <a class="cms-tab<?= $activeTab === 'fields' ? ' active' : '' ?>" href="<?= $e($tabHref('fields')) ?>" style="text-decoration:none">Field Reference</a>
              <a class="cms-tab<?= $activeTab === 'php' ? ' active' : '' ?>" href="<?= $e($tabHref('php')) ?>" style="text-decoration:none">PHP Layout File</a>
              <a class="cms-tab<?= $activeTab === 'preview' ? ' active' : '' ?>" href="<?= $e($tabHref('preview')) ?>" style="text-decoration:none">Preview</a>
            </div>

            <?php if ($activeTab === 'blocks'): ?>
              <div class="tpl-panel is-server-active reveal-page">
                <div class="info-box">
                  The <strong>Master Template</strong> defines every block available across content types. Each block has a stable slug used in code (<code style="font-family:var(--font-mono);font-size:var(--text-tiny)">data-block</code>) and a visibility mode. <strong>Always</strong> blocks render whenever applicable. <strong>Optional</strong> blocks are toggled on or off per content type from each sub-template. <strong>Auto</strong> blocks render based on the data (e.g. Tags renders only when tags exist). To inspect a sub-template's specific visibility, select it from the list on the left.
                </div>
                <?php
                $columns = [
                    ['label' => 'Block',       'width' => '18%'],
                    ['label' => 'Slug',        'width' => '14%'],
                    ['label' => 'Composition', 'width' => '30%'],
                    ['label' => 'Purpose'],
                ];
                $rows = [];
                foreach ($blocks as $slug => $b) {
                    $rows[] = [
                        ['html' => $e($b['name'])],
                        ['html' => '<span class="val-pill">' . $e($slug) . '</span>'],
                        ['html' => $e($b['composition']),  'class' => 'cell-mono'],
                        ['html' => $e($b['purpose']),      'class' => 'cell-note'],
                    ];
                }
                $empty_text = 'No blocks defined.';
                $variant    = 'reference';
                require __DIR__ . '/../partials/table.php';
                ?>
              </div>

            <?php elseif ($activeTab === 'fields'): ?>
              <div class="tpl-panel is-server-active reveal-page">
                <div class="info-box">
                  Every database field that backs a block. Each row maps a field to its PHP variable. Use this tab for layout work; use the Content Blocks tab for visibility.
                </div>
                <?php
                $columns = [
                    ['label' => 'Field',        'width' => '22%'],
                    ['label' => 'PHP Variable', 'width' => '30%'],
                    ['label' => 'Description'],
                ];
                $rows = [];
                foreach ($fields as $name => $f) {
                    $rows[] = [
                        ['html' => '<span class="val-pill">' . $e($name) . '</span>'],
                        ['html' => $e($f['php']),         'class' => 'cell-mono'],
                        ['html' => $e($f['description']), 'class' => 'cell-note'],
                    ];
                }
                $empty_text = 'No fields defined.';
                $variant    = 'reference';
                require __DIR__ . '/../partials/table.php';
                ?>
              </div>

            <?php elseif ($activeTab === 'php'): ?>
              <?php $masterCode = $readTemplate('master-layout.php'); ?>
              <div class="tpl-panel is-server-active reveal-page">
                <div class="info-box">
                  The master PHP layout file — the wrapper for every public article-family page. Read-only here; edit at <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">site/templates/master-layout.php</code> and deploy to ship.
                </div>
                <?php if ($masterCode !== null): ?>
                  <div style="font-family:var(--font-cond);font-size:var(--text-micro);font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin:var(--space-16) 0 var(--space-8)">site/templates/master-layout.php</div>
                  <textarea class="ct-code-editor" data-ct-code readonly><?= $e($masterCode) ?></textarea>
                <?php else: ?>
                  <div class="ct-code-missing">master-layout.php not found at site/templates/ — check the deploy.</div>
                <?php endif; ?>
              </div>

            <?php elseif ($activeTab === 'preview'): ?>
              <div class="tpl-panel is-server-active reveal-page">
                <div class="info-box">
                  Full-block preview — renders <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">master-layout.php</code> wrapping <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">article-standard.php</code> with every block populated. This is the live counterpart to <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">site/_templates/article.html</code>.
                </div>
                <div style="margin-top:var(--space-16);border:1px solid var(--border);border-radius:var(--r-card);overflow:hidden;background:#fff">
                  <iframe
                    src="/cms/post-template/preview?tpl=master"
                    title="Master Template preview · every block populated"
                    class="fade-on-load"
                    style="display:block;width:100%;height:820px;border:0;background:#fff"
                    loading="lazy"></iframe>
                </div>
              </div>
            <?php endif; ?>

          <?php else:
            // ─── Sub-template view: 3 tabs ───
            $info = $subs[$selected];
            $rowMatrix = $matrix[$selected] ?? [];
            $subCode = $readTemplate($info['php_file']);
          ?>
            <div class="cms-tabs">
              <a class="cms-tab<?= $activeTab === 'visibility' ? ' active' : '' ?>" href="<?= $e($tabHref('visibility')) ?>" style="text-decoration:none">Block Visibility</a>
              <a class="cms-tab<?= $activeTab === 'php' ? ' active' : '' ?>" href="<?= $e($tabHref('php')) ?>" style="text-decoration:none">PHP Layout File</a>
              <a class="cms-tab<?= $activeTab === 'preview' ? ' active' : '' ?>" href="<?= $e($tabHref('preview')) ?>" style="text-decoration:none">Preview</a>
            </div>

            <?php if ($activeTab === 'visibility'): ?>
              <div class="tpl-panel is-server-active reveal-page">
                <div class="info-box">
                  <strong><?= $e($info['name']) ?>.</strong> <?= $e($info['desc']) ?>
                </div>
                <?php
                $columns = [
                    ['label' => 'Block',      'width' => '22%'],
                    ['label' => 'Slug',       'width' => '18%'],
                    ['label' => 'Visibility', 'width' => '18%'],
                    ['label' => 'Notes'],
                ];
                $rows = [];
                foreach ($blocks as $blockSlug => $b) {
                    $mode = $rowMatrix[$blockSlug] ?? '—';
                    if ($mode === '—') continue;
                    $pillClass = 'block-mode-pill mode-' . $mode;
                    $pillLabel = ucfirst($mode);
                    $rows[] = [
                        ['html' => $e($b['name'])],
                        ['html' => '<span class="val-pill">' . $e($blockSlug) . '</span>'],
                        ['html' => '<span class="' . $e($pillClass) . '">' . $e($pillLabel) . '</span>'],
                        ['html' => $e($notes[$mode] ?? ''), 'class' => 'cell-note'],
                    ];
                }
                $empty_text = 'No blocks visible in this sub-template.';
                $variant    = 'reference';
                require __DIR__ . '/../partials/table.php';
                ?>
                <div class="readonly-notice ct-readonly-note">
                  Per-sub-template visibility toggles are read-only — the modes shown above are the matrix defaults. Editable suppression isn't available yet.
                </div>
              </div>

            <?php elseif ($activeTab === 'php'): ?>
              <div class="tpl-panel is-server-active reveal-page">
                <div class="info-box">
                  The PHP layout file for <strong><?= $e($info['name']) ?></strong>. Read-only here; edit at <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">site/templates/<?= $e($info['php_file']) ?></code> and deploy to ship.
                </div>
                <?php if ($subCode !== null): ?>
                  <div style="font-family:var(--font-cond);font-size:var(--text-micro);font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin:var(--space-16) 0 var(--space-8)">site/templates/<?= $e($info['php_file']) ?></div>
                  <textarea class="ct-code-editor" data-ct-code readonly><?= $e($subCode) ?></textarea>
                <?php else: ?>
                  <div class="ct-code-missing">
                    <strong style="color:var(--secondary)"><?= $e($info['php_file']) ?></strong> not found in <code>site/templates/</code>. Check the deploy.
                    <?php if ($selected === 'article-series'): ?>
                      <div style="margin-top:var(--space-8)">Likely folded into <code>article-standard.php</code> via a conditional, or not yet created.</div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>

            <?php elseif ($activeTab === 'preview'): ?>
              <div class="tpl-panel is-server-active reveal-page">
                <div class="info-box">
                  Live preview of <strong><?= $e($info['name']) ?></strong> — renders <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">site/templates/<?= $e($info['php_file']) ?></code> against sample content. Edits to the template file show up here immediately.
                  <?php if ($selected === 'article-series'): ?>
                    <div style="margin-top:var(--space-8);color:var(--muted);font-style:italic">Article-series renders via <code>article-standard.php</code> (series detail folds into the topstrip).</div>
                  <?php elseif ($selected === 'experiment-html'): ?>
                    <div style="margin-top:var(--space-8);color:var(--muted);font-style:italic">Experiment-html bypasses master-layout in production and serves a folder we don't have in preview — so the chrome shown here is <code>experiment.php</code>'s.</div>
                  <?php endif; ?>
                </div>
                <div style="margin-top:var(--space-16);border:1px solid var(--border);border-radius:var(--r-card);overflow:hidden;background:#fff">
                  <iframe
                    src="/cms/post-template/preview?tpl=<?= $e($selected) ?>"
                    title="Preview · <?= $e($info['name']) ?>"
                    style="display:block;width:100%;height:820px;border:0;background:#fff"
                    loading="lazy"></iframe>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="/cms/_assets/codemirror/codemirror.min.js"></script>
<script src="/cms/_assets/codemirror/mode/xml/xml.min.js"></script>
<script src="/cms/_assets/codemirror/mode/javascript/javascript.min.js"></script>
<script src="/cms/_assets/codemirror/mode/css/css.min.js"></script>
<script src="/cms/_assets/codemirror/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="/cms/_assets/codemirror/mode/clike/clike.min.js"></script>
<script src="/cms/_assets/codemirror/mode/php/php.min.js"></script>
<script src="/cms/_assets/codemirror/addon/edit/matchbrackets.min.js"></script>
<script>
  // Turn every [data-ct-code] textarea into a read-only CodeMirror with
  // PHP syntax highlighting. Mirrors the editor in /cms/pages/edit.
  document.querySelectorAll('[data-ct-code]').forEach(function (ta) {
    CodeMirror.fromTextArea(ta, {
      mode: 'application/x-httpd-php',
      lineNumbers: true,
      matchBrackets: true,
      readOnly: true,
      indentUnit: 2,
      tabSize: 2,
      lineWrapping: false,
    });
  });

  // .fade-on-load → .is-loaded (see style-cms.css LAYER 8b).
  // Preview iframe fades in when its content has loaded.
  (function () {
    function mark(el) { el.classList.add('is-loaded'); }
    document.querySelectorAll('iframe.fade-on-load').forEach(function (f) {
      if (f.complete && f.contentDocument && f.contentDocument.readyState === 'complete') {
        mark(f);
      } else {
        f.addEventListener('load', function () { mark(f); });
      }
    });
  })();
</script>

</body>
</html>
