<?php
/**
 * cms/views/content-template.php — Content Template admin (Phase 14.5).
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
            header('Location: /cms/content-template?tpl=master&tab=author&flash=' . rawurlencode('Author saved.'));
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

$activeTab = (string)($_GET['tab'] ?? 'blocks');
$validTab  = ['blocks', 'fields', 'author', 'php'];
if (!in_array($activeTab, $validTab, true)) $activeTab = 'blocks';

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
    return '/cms/content-template?tpl=' . rawurlencode($selected) . '&tab=' . rawurlencode($tab);
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Content Template — alexmchong.ca CMS</title>
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
<style>
  /* View-specific tweaks layered on top of style-cms.css's .templates-layout. */
  #view-content-template { display:flex; flex-direction:column; flex:1; min-height:0; }
  /* Make panels behave block-style — we render one panel at a time server-side
     rather than the mockup's JS-toggled .tpl-panel.active pattern. */
  .tpl-panel.is-server-active { display:block; }
  /* "Optional" mode pill — base .block-mode-pill exists but mode-optional doesn't
     have its own color in style-cms.css. Lean italic + muted for read-only feel. */
  .block-mode-pill.mode-optional { color:var(--muted); font-style:italic; }
  /* PHP file preview viewer. */
  .ct-code { font-family:var(--font-mono); font-size:var(--text-tiny); line-height:1.55; color:var(--secondary); background:var(--canvas-bg); border:1px solid var(--ink-12); border-radius:var(--r-card); padding:var(--space-16); overflow:auto; max-height:520px; white-space:pre; }
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
  .ct-readonly-note { font-size:var(--text-tiny); color:var(--muted); font-style:italic; margin-top:var(--space-16); padding:var(--space-12) var(--space-16); border:1px dashed var(--ink-18); border-radius:var(--r-card); }
</style>
</head>
<body>

<?php
$breadcrumb = 'Content Template';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'templates';
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-content-template">
      <?php
      $title    = 'Content Template';
      $subtitle = "Each content type uses a PHP layout file that controls how its fields render on the live site. The Master template lists every available field and its PHP variable — it doesn't turn anything on or off. Each sub-template inherits everything and can suppress specific fields.";
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php if (count($errors) > 0): ?>
        <div class="form-errors" role="alert" style="margin:var(--space-16) var(--space-24) 0">
          <strong>Couldn't save:</strong>
          <ul>
            <?php foreach ($errors as $err): ?><li><?= $e($err) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($flash !== ''): ?>
        <div class="flash-success" role="status" style="margin:var(--space-16) var(--space-24) 0"><?= $e($flash) ?></div>
      <?php endif; ?>

      <div class="templates-layout">
        <!-- ═══ LEFT: Template list ═══ -->
        <div class="template-list">
          <a class="tpl-master<?= $selected === 'master' ? ' active' : '' ?>"
             href="/cms/content-template?tpl=master&tab=blocks"
             style="display:block;text-decoration:none">
            <div class="tpl-master-label">Reference</div>
            <div class="tpl-master-name">Master Template</div>
            <div class="tpl-master-desc">Defines every block, field, and the author profile. Each sub-template inherits all of this — sub-templates only suppress optional blocks.</div>
          </a>
          <?php foreach ($subs as $slug => $info): ?>
            <a class="tpl-item<?= $selected === $slug ? ' active' : '' ?>"
               href="/cms/content-template?tpl=<?= $e($slug) ?>"
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
            </div>

            <?php if ($activeTab === 'blocks'): ?>
              <div class="tpl-panel is-server-active">
                <div class="info-box">
                  The <strong>Master Template</strong> defines every block available across content types. Each block has a stable slug used in code (<code style="font-family:var(--font-mono);font-size:var(--text-tiny)">data-block</code>) and a visibility mode. <em>Always</em> blocks render whenever applicable. <em>Optional</em> blocks are toggled on or off per content type from each sub-template. <em>Auto</em> blocks render based on the data (e.g. Tags renders only when tags exist). To inspect a sub-template's specific visibility, select it from the list on the left.
                </div>
                <table class="master-field-table" style="margin-top:var(--space-16)">
                  <thead>
                    <tr>
                      <th style="width:18%">Block</th>
                      <th style="width:14%">Slug</th>
                      <th style="width:30%">Composition</th>
                      <th>Purpose</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($blocks as $slug => $b): ?>
                      <tr>
                        <td><?= $e($b['name']) ?></td>
                        <td><span class="val-pill"><?= $e($slug) ?></span></td>
                        <td style="font-family:var(--font-mono);font-size:var(--text-tiny);color:var(--secondary)"><?= $e($b['composition']) ?></td>
                        <td style="font-size:var(--text-tiny);color:var(--muted);line-height:1.55"><?= $e($b['purpose']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

            <?php elseif ($activeTab === 'fields'): ?>
              <div class="tpl-panel is-server-active">
                <div class="info-box">
                  Every database field underlying the blocks. Each row maps a field to its PHP variable. Blocks read these fields to populate themselves — so the field reference is for layout work, the Content Blocks tab is for visibility.
                </div>
                <table class="master-field-table" style="margin-top:var(--space-16)">
                  <thead>
                    <tr>
                      <th style="width:22%">Field</th>
                      <th style="width:30%">PHP Variable</th>
                      <th>Description</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($fields as $name => $f): ?>
                      <tr>
                        <td><span class="val-pill"><?= $e($name) ?></span></td>
                        <td style="font-family:var(--font-mono);font-size:var(--text-tiny);color:var(--secondary)"><?= $e($f['php']) ?></td>
                        <td style="font-size:var(--text-tiny);color:var(--muted);line-height:1.55"><?= $e($f['description']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

            <?php elseif ($activeTab === 'author'): ?>
              <div class="tpl-panel is-server-active">
                <div class="info-box">
                  The author block renders next to the byline on every template that includes the <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">$author</code> fields. Sub-templates can hide it on a per-content basis via the <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">show_author</code> / <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">show_author_bio</code> booleans on each content row.
                </div>
                <form method="post" action="/cms/content-template">
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
                        <button class="btn-save" type="submit">Save Author</button>
                      </div>
                    </div>
                  </div>
                </form>
              </div>

            <?php elseif ($activeTab === 'php'): ?>
              <?php $masterCode = $readTemplate('master-layout.php'); ?>
              <div class="tpl-panel is-server-active">
                <div class="info-box">
                  The master PHP layout file — used as the wrapper for every public-rendered article-family page. Read-only view; edits happen in code (<code style="font-family:var(--font-mono);font-size:var(--text-tiny)">site/templates/master-layout.php</code>) and ship through deploy.
                </div>
                <?php if ($masterCode !== null): ?>
                  <div style="font-family:var(--font-cond);font-size:var(--text-micro);font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin:var(--space-16) 0 var(--space-8)">site/templates/master-layout.php</div>
                  <pre class="ct-code"><?= $e($masterCode) ?></pre>
                <?php else: ?>
                  <div class="ct-code-missing">master-layout.php not found at site/templates/ — check the deploy.</div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

          <?php else:
            // ─── Sub-template view (no tabs) ───
            $info = $subs[$selected];
            $rowMatrix = $matrix[$selected] ?? [];
            $subCode = $readTemplate($info['php_file']);
          ?>
            <div class="tpl-panel is-server-active">
              <div class="info-box">
                <strong><?= $e($info['name']) ?>.</strong> <?= $e($info['desc']) ?>
              </div>

              <div style="font-family:var(--font-cond);font-size:var(--text-micro);font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin:var(--space-24) 0 var(--space-8)">Block visibility</div>
              <table class="master-field-table">
                <thead>
                  <tr>
                    <th style="width:22%">Block</th>
                    <th style="width:18%">Slug</th>
                    <th style="width:18%">Visibility</th>
                    <th>Notes</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($blocks as $blockSlug => $b):
                    $mode = $rowMatrix[$blockSlug] ?? '—';
                    if ($mode === '—') continue;
                    $pillClass = 'block-mode-pill mode-' . $mode;
                    $pillLabel = ucfirst($mode);
                  ?>
                    <tr>
                      <td><?= $e($b['name']) ?></td>
                      <td><span class="val-pill"><?= $e($blockSlug) ?></span></td>
                      <td><span class="<?= $e($pillClass) ?>"><?= $e($pillLabel) ?></span></td>
                      <td style="font-size:var(--text-tiny);color:var(--muted);line-height:1.55"><?= $e($notes[$mode] ?? '') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <div class="ct-readonly-note">
                Per-sub-template visibility toggles are read-only in v1.0 (modes shown above are the BLOCKS.md matrix defaults). Editable per-sub-template suppression is deferred to a future phase — see <code style="font-family:var(--font-mono);font-size:var(--text-tiny)">docs/BUILD-PLAN.md</code> §19.5.
              </div>

              <div style="font-family:var(--font-cond);font-size:var(--text-micro);font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin:var(--space-32) 0 var(--space-8)">PHP Layout File</div>
              <?php if ($subCode !== null): ?>
                <div style="font-family:var(--font-mono);font-size:var(--text-tiny);color:var(--muted);margin-bottom:var(--space-8)">site/templates/<?= $e($info['php_file']) ?></div>
                <pre class="ct-code"><?= $e($subCode) ?></pre>
              <?php else: ?>
                <div class="ct-code-missing">
                  <strong style="color:var(--secondary)"><?= $e($info['php_file']) ?></strong> not found in <code>site/templates/</code>.
                  <?php if ($selected === 'article-series'): ?>
                    <div style="margin-top:var(--space-8)">Likely folded into <code>article-standard.php</code> via a conditional, or pending creation. Flagged in Phase 14.5 brief.</div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

</body>
</html>
