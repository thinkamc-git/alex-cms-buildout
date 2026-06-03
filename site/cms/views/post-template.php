<?php
/**
 * cms/views/post-template.php — Post Template admin (Phase 14.5).
 *
 * Read-only port of the design-mockups Content Template view, with the
 * Author info tab editable so the single-row `author` config can be
 * updated from the CMS.
 *
 * Left pane: Master Template card + 6 sub-template rows.
 *   Click → updates ?tpl=<slug>.
 *
 * Right pane:
 *   - tpl=master → 4 tabs (Content Blocks / Field Reference / Author info / PHP Layout File)
 *   - tpl=<sub-template> → single panel (visibility table + PHP file preview)
 *     No "Save Template" button — sub-template visibility is read-only in α.
 *
 * Data layer: lib/blocks_data.php (hardcoded, sourced from docs/BLOCKS.md).
 * Author CRUD: lib/author.php (UPDATE the single-row table).
 *
 * No new schema; no render-layer changes; staging-only ship.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/blocks_data.php';
require_once __DIR__ . '/../../lib/author.php';

Auth::require_login();
$csrf_token = Csrf::token();

$errors = [];
$flash  = '';

// POST: Author save
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save-author') {
            save_author(
                (string)($_POST['name'] ?? ''),
                (string)($_POST['short_description'] ?? ''),
                (string)($_POST['extended_description'] ?? ''),
                (string)($_POST['image'] ?? '')
            );
            header('Location: /cms/post-template?tpl=master&tab=author&flash=' . rawurlencode('Author saved.'));
            exit;
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

// State
$blocks = blocks_reference();
$fields = fields_reference();
$subs   = sub_templates_reference();
$matrix = content_type_matrix();
$notes  = block_mode_notes();
$author = get_author();

$selected = (string)($_GET['tpl'] ?? 'master');
$validTpl = array_merge(['master'], array_keys($subs));
if (!in_array($selected, $validTpl, true)) $selected = 'master';

// Master uses: blocks / fields / author / php / preview
// Sub uses:    visibility / php / preview
$isSub = ($selected !== 'master');
$defaultTab = $isSub ? 'visibility' : 'blocks';
$activeTab = (string)($_GET['tab'] ?? $defaultTab);
$validTab  = $isSub
    ? ['visibility', 'php', 'preview']
    : ['blocks', 'fields', 'author', 'php', 'preview'];
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
<link rel="stylesheet" href="/cms/_assets/style-cms.css">
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
  /* Author info layout. */
  .ct-author-grid { display:flex; gap:var(--space-24); margin-top:var(--space-24); align-items:flex-start; }
  .ct-author-avatar-col { flex-shrink:0; display:flex; flex-direction:column; align-items:center; gap:var(--space-8); }
  .ct-author-avatar { width:96px; height:96px; border-radius:50%; background:var(--canvas-raised); border:1px solid var(--ink-18); display:flex; align-items:center; justify-content:center; font-family:var(--font-serif); font-style:italic; font-size:36px; color:var(--muted); overflow:hidden; }
  .ct-author-avatar img { width:100%; height:100%; object-fit:cover; }
  .ct-author-fields { flex:1; min-width:0; }
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
            <div class="tpl-tabs">
              <a class="tpl-tab<?= $activeTab === 'blocks' ? ' active' : '' ?>" href="<?= $e($tabHref('blocks')) ?>" style="text-decoration:none">Content Blocks</a>
              <a class="tpl-tab<?= $activeTab === 'fields' ? ' active' : '' ?>" href="<?= $e($tabHref('fields')) ?>" style="text-decoration:none">Field Reference</a>
              <a class="tpl-tab<?= $activeTab === 'author' ? ' active' : '' ?>" href="<?= $e($tabHref('author')) ?>" style="text-decoration:none">Author info</a>
              <a class="tpl-tab<?= $activeTab === 'php' ? ' active' : '' ?>" href="<?= $e($tabHref('php')) ?>" style="text-decoration:none">PHP Layout File</a>
              <a class="tpl-tab<?= $activeTab === 'preview' ? ' active' : '' ?>" href="<?= $e($tabHref('preview')) ?>" style="text-decoration:none">Preview</a>
            </div>

            <?php if ($activeTab === 'blocks'): ?>
              <div class="tpl-panel is-server-active">
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
              <div class="tpl-panel is-server-active">
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

            <?php elseif ($activeTab === 'author'): ?>
              <div class="tpl-panel is-server-active">
                <div class="info-box">
                  The author block renders next to the byline on every template that includes the <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">$author</code> fields. Sub-templates can hide it on a per-content basis via the <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">show_author</code> / <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">show_author_bio</code> booleans on each content row.
                </div>
                <form method="post" action="/cms/post-template">
                  <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
                  <input type="hidden" name="action" value="save-author">
                  <div class="ct-author-grid">
                    <div class="ct-author-avatar-col">
                      <div class="ct-author-avatar">
                        <?php
                        $imgUrl = trim((string)($author['image'] ?? ''));
                        if ($imgUrl !== ''):
                        ?>
                          <img src="<?= $e($imgUrl) ?>" alt="">
                        <?php else: ?>
                          <?= $e(author_initials($author['name'] ?? null) ?: 'AC') ?>
                        <?php endif; ?>
                      </div>
                      <div class="field-note-box" style="font-size:var(--text-tiny);text-align:center;max-width:160px">96×96 recommended. Paste the URL or relative path below.</div>
                    </div>
                    <div class="ct-author-fields">
                      <div class="field-group">
                        <div class="field-label">Image URL</div>
                        <input class="field-input" type="text" name="image" value="<?= $e((string)($author['image'] ?? '')) ?>" placeholder="/uploads/author.jpg or https://…">
                      </div>
                      <div class="field-group">
                        <div class="field-label">Name <span class="field-req">required</span></div>
                        <input class="field-input" type="text" name="name" value="<?= $e((string)($author['name'] ?? '')) ?>" placeholder="Alex M. Chong">
                      </div>
                      <div class="field-group">
                        <div class="field-label">Short Description</div>
                        <textarea class="field-input" name="short_description" style="min-height:80px" placeholder="A short bio that appears alongside articles…"><?= $e((string)($author['short_description'] ?? '')) ?></textarea>
                        <div class="field-note-box">Displays beside the byline on every article that includes the author block. Keep it short — one or two sentences.</div>
                      </div>
                      <div class="field-group">
                        <div class="field-label">Extended Description</div>
                        <textarea class="field-input" name="extended_description" style="min-height:160px" placeholder="The fuller bio rendered in the Author Bio block…"><?= $e((string)($author['extended_description'] ?? '')) ?></textarea>
                        <div class="field-note-box">Renders in the <strong>Author Bio</strong> block — the footer "About the author" panel. Independently toggleable from the inline Author byline.</div>
                      </div>
                      <div style="margin-top:var(--space-16)">
                        <button class="btn-pri" type="submit">Save Author</button>
                      </div>
                    </div>
                  </div>
                </form>
              </div>

            <?php elseif ($activeTab === 'php'): ?>
              <?php $masterCode = $readTemplate('master-layout.php'); ?>
              <div class="tpl-panel is-server-active">
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
              <div class="tpl-panel is-server-active">
                <div class="info-box">
                  Full-block preview — renders <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">master-layout.php</code> wrapping <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">article-standard.php</code> with every block populated. This is the live counterpart to <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">site/_templates/article.html</code>.
                </div>
                <div style="margin-top:var(--space-16);border:1px solid var(--border);border-radius:var(--r-card);overflow:hidden;background:#fff">
                  <iframe
                    src="/cms/post-template/preview?tpl=master"
                    title="Master Template preview · every block populated"
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
            <div class="tpl-tabs">
              <a class="tpl-tab<?= $activeTab === 'visibility' ? ' active' : '' ?>" href="<?= $e($tabHref('visibility')) ?>" style="text-decoration:none">Block Visibility</a>
              <a class="tpl-tab<?= $activeTab === 'php' ? ' active' : '' ?>" href="<?= $e($tabHref('php')) ?>" style="text-decoration:none">PHP Layout File</a>
              <a class="tpl-tab<?= $activeTab === 'preview' ? ' active' : '' ?>" href="<?= $e($tabHref('preview')) ?>" style="text-decoration:none">Preview</a>
            </div>

            <?php if ($activeTab === 'visibility'): ?>
              <div class="tpl-panel is-server-active">
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
              <div class="tpl-panel is-server-active">
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
              <div class="tpl-panel is-server-active">
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
</script>

</body>
</html>
