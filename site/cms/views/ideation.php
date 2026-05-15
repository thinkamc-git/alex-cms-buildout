<?php
/**
 * cms/views/ideation.php — Articles ideation board.
 *
 * Routed from site/index.php as GET /cms/ideation.
 *
 * A holding space for raw Article ideas. Quick-capture posts to
 * /cms/articles/new-idea (same endpoint as Pipeline). Each card is
 * itself a link into the Idea-stage editor; advancing to Concept
 * happens via the editor's Advance button, not a list-view shortcut.
 *
 * Phase 7 is Articles-only; the type-lane layout from the mockup will
 * grow Journals/Sessions/Experiments lanes as those types ship.
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

$ideas = list_articles(['status' => 'idea']);
$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
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
  $nav_counts    = ['ideation' => count($ideas)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-ideation">
      <?php
      $title    = 'Ideation';
      $subtitle = 'A holding space for raw ideas. Capture quickly; build an idea to advance it to Concept and continue developing it.';
      $actions  = '';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <div class="ideation-capture" style="background:var(--canvas-raised);border-bottom:var(--rule-faint);padding:var(--space-16) var(--space-24);">
        <form method="post" action="/cms/articles/new-idea" style="display:flex;gap:0;max-width:580px;">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <input type="hidden" name="from" value="ideation">
          <input class="qc-input" type="text" name="title" placeholder="What's the idea?" maxlength="500" required>
          <select class="qc-select" name="type" disabled title="Articles only in Phase 7"><option>Article</option></select>
          <button class="qc-btn" type="submit">+ Add</button>
        </form>
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status" style="margin-top:var(--space-12);max-width:580px"><?= $e($flash) ?></div>
        <?php endif; ?>
      </div>

      <div class="kanban-board">
        <div class="kanban-lane">
          <div class="lane-header">
            <span class="type-badge tb-article">Article</span>
            <div class="lane-count"><?= (int)count($ideas) ?></div>
          </div>
          <div class="lane-cards">
            <?php if (count($ideas) === 0): ?>
              <div class="idea-lane-empty">No ideas yet — capture one above</div>
            <?php else: foreach ($ideas as $a):
              $id      = (int)($a['id'] ?? 0);
              $title2  = (string)($a['title'] ?? '');
              if ($title2 === '') $title2 = '(untitled)';
              // list_articles() doesn't currently pull notes — fetch per
              // card. Fine at single-author volumes; if the Idea backlog
              // ever grows huge we'd extend the SELECT list.
              $full    = get_article($id);
              $notes   = (string)($full['notes'] ?? '');
              $updated = relative_time((string)($a['updated_at'] ?? ''));
            ?>
              <a href="/cms/articles/edit?id=<?= (int)$id ?>" class="idea-card">
                <div class="idea-card-title"><?= $e($title2) ?></div>
                <?php if ($notes !== ''): ?>
                  <div class="idea-card-desc"><?= $e($notes) ?></div>
                <?php endif; ?>
                <div class="idea-card-foot">
                  <span class="idea-card-meta"><?= $e($updated) ?></span>
                </div>
              </a>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

</body>
</html>
