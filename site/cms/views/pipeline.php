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
$journals = list_journals();
$sessions = list_live_sessions();

// Tag each row with its display type so the kanban card render picks the
// right badge. (list_articles / list_journals / list_live_sessions don't
// ship a `type` column because each query is filtered to one type —
// synthesize it here.)
foreach ($articles as &$a) { $a['type'] = 'article'; }       unset($a);
foreach ($journals as &$j) { $j['type'] = 'journal'; }       unset($j);
foreach ($sessions as &$s) { $s['type'] = 'live-session'; }  unset($s);

// Merge then re-sort by pipeline_order ASC, updated_at DESC. Each list
// already arrives in its lane-local order; the merge needs a stable
// global sort so the three types share lane positions cleanly.
$rows = array_merge($articles, $journals, $sessions);
usort($rows, static function (array $a, array $b): int {
    $po = (int)($a['pipeline_order'] ?? 0) <=> (int)($b['pipeline_order'] ?? 0);
    if ($po !== 0) return $po;
    return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
});

// Per-bucket counts across all types. The "scheduled" bucket is virtual:
// rows where status='published' but published_status='scheduled' (cron
// hasn't promoted to 'live' yet). Phase 14.6 split.
$counts = ['concept' => 0, 'outline' => 0, 'draft' => 0, 'scheduled' => 0, 'published' => 0];
foreach ($rows as $r) {
    $s  = (string)($r['status'] ?? '');
    $ps = (string)($r['published_status'] ?? '');
    if ($s === 'published' && $ps === 'scheduled') {
        $counts['scheduled']++;
    } elseif (isset($counts[$s])) {
        $counts[$s]++;
    }
}
$inFlight = $counts['concept'] + $counts['outline'] + $counts['draft'] + $counts['scheduled'];

$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

// Group rows by bucket for kanban rendering. Scheduled siphons rows out
// of the 'published' bucket so the lane only shows live posts.
$byStage = ['concept' => [], 'outline' => [], 'draft' => [], 'scheduled' => [], 'published' => []];
foreach ($rows as $r) {
    $s  = (string)($r['status'] ?? 'draft');
    $ps = (string)($r['published_status'] ?? '');
    if ($s === 'published' && $ps === 'scheduled') {
        $byStage['scheduled'][] = $r;
    } elseif (isset($byStage[$s])) {
        $byStage[$s][] = $r;
    }
}

// Scheduled lane: sort by published_at ASC (soonest-next first).
usort($byStage['scheduled'], static function (array $a, array $b): int {
    return strcmp((string)($a['published_at'] ?? ''), (string)($b['published_at'] ?? ''));
});

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/**
 * Render one kanban card. Pipeline now mixes types (articles + journals);
 * the badge and edit URL switch on the row's `type`. Journals past Idea
 * live at /cms/journals/edit and prefer key_statement as the display name.
 */
$renderCard = static function (array $a, string $stage) use ($e): string {
    $id      = (int)($a['id'] ?? 0);
    $type    = (string)($a['type'] ?? 'article');
    $slug    = (string)($a['slug'] ?? '');
    $updated = relative_time((string)($a['updated_at'] ?? ''));

    $display = $type === 'journal'
        ? (string)($a['key_statement'] ?? '') ?: (string)($a['title'] ?? '')
        : (string)($a['title'] ?? '');
    if ($display === '') $display = '(untitled)';

    $variant = $stage === 'idea' ? ' idea' : ($stage === 'concept' ? ' concept' : '');

    // Idea stage stays in the shared editor for every type; past Idea each
    // type lives in its own editor.
    if ($stage === 'idea') {
        $editUrl = '/cms/articles/edit?id=' . $id;
    } elseif ($type === 'journal') {
        $editUrl = '/cms/journals/edit?id=' . $id;
    } elseif ($type === 'live-session') {
        $editUrl = '/cms/live-sessions/edit?id=' . $id;
    } else {
        $editUrl = '/cms/articles/edit?id=' . $id;
    }

    [$badgeClass, $badgeLabel] = match ($type) {
        'journal'      => ['tb-journal',      'Journal'],
        'live-session' => ['tb-live-session', 'Session'],
        default        => ['tb-article',      'Article'],
    };

    $head = '<div class="kcard-head">'
          . '<div class="kcard-title">' . $e($display) . '</div>'
          . '<span class="type-badge ' . $badgeClass . '">' . $badgeLabel . '</span>'
          . '</div>';

    $foot = '';
    if ($stage === 'scheduled') {
        // Show the scheduled-for date prominently — it's the most useful
        // info on a scheduled card. updated_at lives below as a smaller line.
        $scheduledAt = (string)($a['published_at'] ?? '');
        $when = $scheduledAt !== '' ? date('M j · g:i A', strtotime($scheduledAt)) : '—';
        $foot = '<div class="kcard-foot">'
              . '<span class="kcard-date" style="font-weight:600;color:var(--stage-published)">→ ' . $e($when) . '</span>'
              . '<span class="row-slug">/' . $e($slug) . '</span>'
              . '</div>';
    } elseif ($stage !== 'idea') {
        $foot = '<div class="kcard-foot">'
              . '<span class="row-slug">/' . $e($slug) . '</span>'
              . '<span class="kcard-date">' . $e($updated) . '</span>'
              . '</div>';
    }

    return '<a href="' . $e($editUrl) . '" class="kcard' . $variant . '" data-id="' . $id . '" draggable="true" style="text-decoration:none;display:block;color:inherit">'
         . $head . $foot
         . '</a>';
};

$lanes = [
    ['stage' => 'concept',   'label' => 'Concepts',  'token' => 'concept'],
    ['stage' => 'outline',   'label' => 'Outlines',  'token' => 'outline'],
    ['stage' => 'draft',     'label' => 'Drafts',    'token' => 'draft'],
    ['stage' => 'scheduled', 'label' => 'Scheduled', 'token' => 'concept'],
    ['stage' => 'published', 'label' => 'Published', 'token' => 'published'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
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
          <div class="dash-stat"><span class="num" style="color:var(--stage-concept)"><?= (int)$counts['concept'] ?></span><span class="lbl">Concept</span></div>
          <div class="dash-stat-div"></div>
          <div class="dash-stat"><span class="num" style="color:var(--stage-outline)"><?= (int)$counts['outline'] ?></span><span class="lbl">Outline</span></div>
          <div class="dash-stat-div"></div>
          <div class="dash-stat"><span class="num" style="color:var(--stage-draft)"><?= (int)$counts['draft'] ?></span><span class="lbl">Draft</span></div>
          <div class="dash-stat-div"></div>
          <div class="dash-stat"><span class="num" style="color:var(--stage-concept)"><?= (int)$counts['scheduled'] ?></span><span class="lbl">Scheduled</span></div>
          <div class="dash-stat-div"></div>
          <div class="dash-stat"><span class="num" style="color:var(--stage-published)"><?= (int)$counts['published'] ?></span><span class="lbl">Live</span></div>
        </div>
        <?php if ($flash !== ''): ?>
          <div class="flash-success" role="status" style="margin-top:var(--space-12)"><?= $e($flash) ?></div>
        <?php endif; ?>
      </div>
      <div class="kanban-board"
           data-dnd-mode="pipeline"
           data-dnd-endpoint="/cms/articles/reorder-pipeline"
           data-csrf-token="<?= $e($csrf_token) ?>">
        <?php foreach ($lanes as $lane):
          $stage = $lane['stage'];
          $cards = $byStage[$stage];
        ?>
          <div class="kanban-lane" data-key="<?= $e($stage) ?>">
            <div class="lane-header">
              <div class="lane-dot" style="background:var(--stage-<?= $e($lane['token']) ?>)"></div>
              <div class="lane-title" style="color:var(--stage-<?= $e($lane['token']) ?>)"><?= $e($lane['label']) ?></div>
              <div class="lane-count"><?= (int)count($cards) ?></div>
            </div>
            <div class="lane-cards">
              <?php if (count($cards) === 0): ?>
                <div class="idea-lane-empty">Nothing here yet</div>
              <?php else:
                $paginate = ($stage === 'published' && count($cards) > 5);
                $hiddenCount = $paginate ? count($cards) - 5 : 0;
                foreach ($cards as $idx => $card):
                  $hiddenClass = ($paginate && $idx >= 5) ? ' pipeline-load-more-hidden' : '';
              ?>
                <?php if ($hiddenClass !== ''): ?>
                  <div class="<?= ltrim($hiddenClass) ?>"><?= $renderCard($card, $stage) ?></div>
                <?php else: ?>
                  <?= $renderCard($card, $stage) ?>
                <?php endif; ?>
              <?php endforeach; ?>
                <?php if ($paginate): ?>
                  <button class="pipeline-load-more" type="button" data-count="<?= (int)$hiddenCount ?>">Load <?= (int)$hiddenCount ?> more</button>
                <?php endif; ?>
              <?php endif; ?>
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
