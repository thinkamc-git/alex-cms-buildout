<?php
/**
 * cms/views/ideation.php — Ideation board, grouped by content type.
 *
 * Routed from site/index.php as GET /cms/ideation.
 *
 * Layout: five lanes — No type | Article | Journal | Live Session | Experiment.
 * Quick-capture lands in "No type"; the author types an idea by dragging
 * the card into the matching column. Cross-column drag persists the new
 * type; same-column drag reorders. Click any card to open the Idea-stage
 * editor.
 *
 * Phase 7.6 ships the kanban + drag layer. The Article-typed editor is
 * the only one wired today; journal/session/experiment editors land in
 * Phases 8/9/10 (until then, advancing those types is blocked by
 * transition_stage).
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

$rows  = list_ideation_rows();
$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

// Bucket rows by type. "none" key holds untyped (NULL) rows.
$byType = ['none' => [], 'article' => [], 'journal' => [], 'live-session' => [], 'experiment' => []];
foreach ($rows as $r) {
    $t = $r['type'] === null ? 'none' : (string)$r['type'];
    if (!isset($byType[$t])) $byType[$t] = [];
    $byType[$t][] = $r;
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$lanes = [
    ['key' => 'none',         'label' => 'No type',       'badge' => 'tb-none'],
    ['key' => 'article',      'label' => 'Article',       'badge' => 'tb-article'],
    ['key' => 'journal',      'label' => 'Journal',       'badge' => 'tb-journal'],
    ['key' => 'live-session', 'label' => 'Live Session',  'badge' => 'tb-live-session'],
    ['key' => 'experiment',   'label' => 'Experiment',    'badge' => 'tb-experiment'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Ideation — alexmchong.ca CMS</title>
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
$breadcrumb = 'Ideation';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'ideation';
  $nav_counts    = ['ideation' => count($rows)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-ideation">
      <?php
      $title    = 'Ideation';
      $subtitle = 'Capture raw ideas. Drag a card into a type column to assign it; drag within a column to reorder.';
      $actions  = '';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="ideation-capture" style="background:var(--canvas-raised);border-bottom:var(--rule-faint);padding:var(--space-16) var(--space-24);">
        <form method="post" action="/cms/articles/new-idea" style="display:flex;gap:0;max-width:580px;">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <input type="hidden" name="from" value="ideation">
          <input class="qc-input" type="text" name="title" placeholder="What's the idea?" maxlength="500" required>
          <button class="qc-btn" type="submit">+ Add</button>
        </form>
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status" style="margin-top:var(--space-12);max-width:580px"><?= $e($flash) ?></div>
        <?php endif; ?>
      </div>

      <div class="kanban-board"
           data-dnd-mode="ideation"
           data-dnd-endpoint="/cms/articles/reorder-ideation"
           data-csrf-token="<?= $e($csrf_token) ?>">
        <?php foreach ($lanes as $lane):
          $key   = $lane['key'];
          $cards = $byType[$key] ?? [];
        ?>
          <div class="kanban-lane" data-key="<?= $e($key) ?>">
            <div class="lane-header">
              <span class="type-badge <?= $e($lane['badge']) ?>"><?= $e($lane['label']) ?></span>
              <div class="lane-count"><?= (int)count($cards) ?></div>
            </div>
            <div class="lane-cards">
              <?php if (count($cards) === 0): ?>
                <div class="idea-lane-empty">Drop here</div>
              <?php else: foreach ($cards as $card):
                $cid     = (int)($card['id'] ?? 0);
                $ctitle  = (string)($card['title'] ?? '');
                if ($ctitle === '') $ctitle = '(untitled)';
                $cnotes  = (string)($card['notes'] ?? '');
                $cupd    = relative_time((string)($card['updated_at'] ?? ''));
              ?>
                <a href="/cms/articles/edit?id=<?= $cid ?>" class="idea-card kcard" data-id="<?= $cid ?>" draggable="true">
                  <div class="idea-card-title"><?= $e($ctitle) ?></div>
                  <?php if ($cnotes !== ''): ?>
                    <div class="idea-card-desc"><?= $e($cnotes) ?></div>
                  <?php endif; ?>
                  <div class="idea-card-foot">
                    <span class="idea-card-meta"><?= $e($cupd) ?></span>
                  </div>
                </a>
              <?php endforeach; endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>

<script src="/cms/_assets/dragdrop.js"></script>
</body>
</html>
