<?php
/**
 * cms/views/pipeline.php — Articles pipeline kanban.
 *
 * Routed from site/index.php as GET /cms.
 *
 * Header: stat row (counts per stage) + a quick-capture bar that
 * creates a new Article at Idea stage (POSTs to /cms/articles/new-idea).
 *
 * Body: five lanes — Idea / Concept / Outline / Drafts / Published.
 * Each card surfaces title + slug + last-modified relative time, with
 * a category color band reserved for Phase 8+ when categories ship.
 *
 * Phase 7 is Articles-only; other content types extend the SELECT in a
 * later phase. The type-badge on each card is rendered defensively so
 * the same template can welcome Journals/Sessions/Experiments later.
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

$articles = list_articles();
$counts   = count_articles_by_stage();
$inFlight = $counts['idea'] + $counts['concept'] + $counts['outline'] + $counts['draft'];

$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

// Group articles by stage for kanban rendering.
$byStage = array_fill_keys(ARTICLE_STAGES, []);
foreach ($articles as $a) {
    $s = (string)($a['status'] ?? 'idea');
    if (isset($byStage[$s])) $byStage[$s][] = $a;
}

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/**
 * Render one kanban card. $stage drives the per-stage display variant
 * (idea = title only; concept onward = title + slug + foot).
 */
$renderCard = static function (array $a, string $stage) use ($e): string {
    $id      = (int)($a['id'] ?? 0);
    $title   = (string)($a['title'] ?? '');
    if ($title === '') $title = '(untitled)';
    $slug    = (string)($a['slug'] ?? '');
    $updated = relative_time((string)($a['updated_at'] ?? ''));

    $variant = $stage === 'idea' ? ' idea' : ($stage === 'concept' ? ' concept' : '');
    $editUrl = '/cms/articles/edit?id=' . $id;

    $head = '<div class="kcard-head">'
          . '<div class="kcard-title">' . $e($title) . '</div>'
          . '<span class="type-badge tb-article">Article</span>'
          . '</div>';

    $foot = '';
    if ($stage !== 'idea') {
        $foot = '<div class="kcard-foot">'
              . '<span class="row-slug">/' . $e($slug) . '</span>'
              . '<span class="kcard-date">' . $e($updated) . '</span>'
              . '</div>';
    }

    return '<a href="' . $e($editUrl) . '" class="kcard' . $variant . '" style="text-decoration:none;display:block;color:inherit">'
         . $head . $foot
         . '</a>';
};

$lanes = [
    ['stage' => 'idea',      'label' => 'Ideas',     'token' => 'idea'],
    ['stage' => 'concept',   'label' => 'Concepts',  'token' => 'concept'],
    ['stage' => 'outline',   'label' => 'Outlines',  'token' => 'outline'],
    ['stage' => 'draft',     'label' => 'Drafts',    'token' => 'draft'],
    ['stage' => 'published', 'label' => 'Published', 'token' => 'published'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title>Pipeline — alexmchong.ca CMS</title>
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
$breadcrumb = 'Pipeline';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'pipeline';
  $nav_counts    = ['articles' => count($articles)];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-pipeline">
      <div class="pipeline-header">
        <div class="pipeline-title">Pipeline</div>
        <div class="pipeline-desc">All work in progress — from raw idea to final draft. Capture quickly, develop deliberately. Content moves left to right as it matures toward publication.</div>
        <div class="dash-meta">
          <div class="dash-stat"><span class="num"><?= (int)$inFlight ?></span><span class="lbl">In flight</span></div>
          <div class="dash-stat-div"></div>
          <div class="dash-stat"><span class="num" style="color:var(--stage-idea)"><?= (int)$counts['idea'] ?></span><span class="lbl">Ideas</span></div>
          <div class="dash-stat-div"></div>
          <div class="dash-stat"><span class="num" style="color:var(--stage-concept)"><?= (int)$counts['concept'] ?></span><span class="lbl">Concept</span></div>
          <div class="dash-stat-div"></div>
          <div class="dash-stat"><span class="num" style="color:var(--stage-outline)"><?= (int)$counts['outline'] ?></span><span class="lbl">Outline</span></div>
          <div class="dash-stat-div"></div>
          <div class="dash-stat"><span class="num" style="color:var(--stage-draft)"><?= (int)$counts['draft'] ?></span><span class="lbl">Draft</span></div>
          <div class="dash-stat-div"></div>
          <div class="dash-stat"><span class="num" style="color:var(--stage-published)"><?= (int)$counts['published'] ?></span><span class="lbl">Live</span></div>
        </div>
        <form method="post" action="/cms/articles/new-idea" class="quick-capture">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <input type="hidden" name="from" value="pipeline">
          <input class="qc-input" type="text" name="title" placeholder="Capture a new idea — type and press Add…" maxlength="500" required>
          <select class="qc-select" name="type" disabled title="Articles only in Phase 7"><option>Article</option></select>
          <button class="qc-btn" type="submit">+ Add</button>
        </form>
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status" style="margin-top:var(--space-12)"><?= $e($flash) ?></div>
        <?php endif; ?>
      </div>
      <div class="kanban-board">
        <?php foreach ($lanes as $lane):
          $stage = $lane['stage'];
          $cards = $byStage[$stage];
        ?>
          <div class="kanban-lane">
            <div class="lane-header">
              <div class="lane-dot" style="background:var(--stage-<?= $e($lane['token']) ?>)"></div>
              <div class="lane-title" style="color:var(--stage-<?= $e($lane['token']) ?>)"><?= $e($lane['label']) ?></div>
              <div class="lane-count"><?= (int)count($cards) ?></div>
            </div>
            <div class="lane-cards">
              <?php if (count($cards) === 0): ?>
                <div class="idea-lane-empty">Nothing here yet</div>
              <?php else: foreach ($cards as $card): ?>
                <?= $renderCard($card, $stage) ?>
              <?php endforeach; endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>

</body>
</html>
