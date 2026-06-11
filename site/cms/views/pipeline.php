<?php
/**
 * cms/views/pipeline.php — Draft Writing (the in-flight kanban).
 *
 * Phase 19 reshape: was "Pipeline" with 5 stage lanes (concept/outline/
 * draft/scheduled/published). Renamed to "Draft Writing" with five
 * conceptually-distinct columns:
 *
 *   Concept · Outline · Draft · Scheduled · Recently Published
 *
 * Differences from the old Pipeline:
 *   - "Idea" stage stays in Ideation Board (separate view) — never appears here
 *   - "Scheduled" sub-groups by calendar week (This Week / Next Week / Future)
 *     using list_scheduled_content() in the author's timezone
 *   - "Recently Published" shows the top 5 most recent live rows
 *     (list_recently_published(5)), read-only — not a drop target
 *   - All 4 content types feed every column (articles + journals + sessions +
 *     experiments). Old view excluded experiments.
 *
 * URL stays `/cms/` (root) so existing bookmarks keep working; only the
 * label flipped.
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

$articles    = list_articles();
$journals    = list_journals();
$sessions    = list_live_sessions();
$experiments = list_experiments();

foreach ($articles    as &$a) { $a['type'] = 'article'; }       unset($a);
foreach ($journals    as &$j) { $j['type'] = 'journal'; }       unset($j);
foreach ($sessions    as &$s) { $s['type'] = 'live-session'; }  unset($s);
foreach ($experiments as &$x) { $x['type'] = 'experiment'; }    unset($x);

$rows = array_merge($articles, $journals, $sessions, $experiments);
usort($rows, static function (array $a, array $b): int {
    $po = (int)($a['pipeline_order'] ?? 0) <=> (int)($b['pipeline_order'] ?? 0);
    if ($po !== 0) return $po;
    return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
});

// In-flight bucketing — Concept / Outline / Draft only. Scheduled +
// Recently Published come from dedicated helpers (cross-type, with
// week-bucketing + ordering already applied).
$byStage = ['concept' => [], 'outline' => [], 'draft' => []];
foreach ($rows as $r) {
    $s = (string)($r['status'] ?? 'draft');
    if (isset($byStage[$s])) {
        $byStage[$s][] = $r;
    }
}

$scheduledBuckets   = list_scheduled_content();
$recentlyPublished  = list_recently_published(5);
$scheduledTotal     = count($scheduledBuckets['this_week'])
                    + count($scheduledBuckets['next_week'])
                    + count($scheduledBuckets['future']);

// Stat-row counts. "Live" = total ever-published live rows (separate from
// the Recently Published column which caps at 5). Hand-counted across all
// 4 lists so we don't run an extra query just for the dash header.
$liveTotal = 0;
foreach ($rows as $r) {
    $st = (string)($r['status'] ?? '');
    $ps = (string)($r['published_status'] ?? '');
    if ($st === 'published' && ($ps === '' || $ps === 'live' || $ps === null)) {
        $liveTotal++;
    }
}
$inFlight = count($byStage['concept']) + count($byStage['outline']) + count($byStage['draft']) + $scheduledTotal;

$flash = isset($_GET['flash']) ? (string)$_GET['flash'] : '';

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

/**
 * Edit URL for a row by type. Concept/Outline/Draft stages route to the
 * type-specific editor; idea rows would route to the shared editor but
 * they don't appear in this view anymore.
 */
// Phase 20.3: stamp ?from=draft-writing so the edit view keeps the
// Draft Writing sidebar entry highlighted + the back link returns here,
// regardless of post type.
$editUrlFor = static function (array $r): string {
    $id   = (int)($r['id'] ?? 0);
    $type = (string)($r['type'] ?? 'article');
    $base = match ($type) {
        'journal'      => '/cms/journals/edit?id='      . $id,
        'live-session' => '/cms/live-sessions/edit?id=' . $id,
        'experiment'   => '/cms/experiments/edit?id='   . $id,
        default        => '/cms/articles/edit?id='      . $id,
    };
    return $base . '&from=draft-writing';
};

/**
 * Display name for a row. Journals prefer key_statement (their primary
 * surface field); everything else uses title.
 */
$displayOf = static function (array $r): string {
    $type = (string)($r['type'] ?? 'article');
    if ($type === 'journal') {
        $ks = (string)($r['key_statement'] ?? '');
        if ($ks !== '') return $ks;
    }
    $t = (string)($r['title'] ?? '');
    return $t !== '' ? $t : '(untitled)';
};

$typeBadge = static function (string $type): array {
    return match ($type) {
        'journal'      => ['tb-journal',      'Journal'],
        'live-session' => ['tb-live-session', 'Session'],
        'experiment'   => ['tb-experiment',   'Experiment'],
        default        => ['tb-article',      'Article'],
    };
};

/**
 * Render a kanban card for the in-flight columns (Concept / Outline / Draft).
 * These remain draggable for stage reordering.
 */
$renderInFlightCard = static function (array $r, string $stage) use ($e, $editUrlFor, $displayOf, $typeBadge): string {
    $id      = (int)($r['id'] ?? 0);
    $slug    = (string)($r['slug'] ?? '');
    $updated = relative_time((string)($r['updated_at'] ?? ''));
    $display = $displayOf($r);
    $variant = $stage === 'concept' ? ' concept' : '';
    [$badgeClass, $badgeLabel] = $typeBadge((string)$r['type']);

    // Phase 20.3: drop slug from Draft Writing cards (not useful at-a-glance
    // while drafting); type badge moves to the foot so the title gets the
    // full head width.
    return '<a href="' . $e($editUrlFor($r)) . '" class="kcard kcard--draggable' . $variant . '" data-id="' . $id . '" draggable="true" style="text-decoration:none;display:block;color:inherit">'
         . '<div class="kcard-head">'
         . '<div class="kcard-title">' . $e($display) . '</div>'
         . '<span class="kcard-grip" aria-hidden="true" title="Drag to reorder">⋮⋮</span>'
         . '</div>'
         . '<div class="kcard-foot">'
         . '<span class="type-badge ' . $badgeClass . '">' . $badgeLabel . '</span>'
         . '<span class="kcard-date">' . $e($updated) . '</span>'
         . '</div>'
         . '</a>';
};

/**
 * Render a scheduled-column card. Shows the scheduled date prominently,
 * not draggable (sub-grouped by week — DnD ordering doesn't apply here).
 */
$renderScheduledCard = static function (array $r) use ($e, $editUrlFor, $displayOf, $typeBadge): string {
    $id          = (int)($r['id'] ?? 0);
    $slug        = (string)($r['slug'] ?? '');
    $scheduledAt = (string)($r['published_at'] ?? '');
    $when        = $scheduledAt !== '' ? date('M j · g:i A', strtotime($scheduledAt)) : '—';
    $display     = $displayOf($r);
    [$badgeClass, $badgeLabel] = $typeBadge((string)$r['type']);

    // Phase 20.3: top-right gets a green date pill — that's the salient
    // detail for a scheduled card. Type badge moves to the foot.
    return '<a href="' . $e($editUrlFor($r)) . '" class="kcard" data-id="' . $id . '" draggable="false" style="text-decoration:none;display:block;color:inherit">'
         . '<div class="kcard-head">'
         . '<div class="kcard-title">' . $e($display) . '</div>'
         . '<span class="kcard-schedule-pill" title="Scheduled to publish">' . $e($when) . '</span>'
         . '</div>'
         . '<div class="kcard-foot">'
         . '<span class="type-badge ' . $badgeClass . '">' . $badgeLabel . '</span>'
         . '</div>'
         . '</a>';
};

/**
 * Render a recently-published card. Static (not draggable, not a drop
 * target). Mirrors the in-flight card convention: slug dropped, type
 * badge in the foot, with the published date sitting at the bottom-right.
 */
$renderPublishedCard = static function (array $r) use ($e, $editUrlFor, $displayOf, $typeBadge): string {
    $id          = (int)($r['id'] ?? 0);
    $publishedAt = (string)($r['published_at'] ?? '');
    $when        = $publishedAt !== '' ? date('M j', strtotime($publishedAt)) : '—';
    $display     = $displayOf($r);
    [$badgeClass, $badgeLabel] = $typeBadge((string)$r['type']);

    return '<a href="' . $e($editUrlFor($r)) . '" class="kcard" data-id="' . $id . '" draggable="false" style="text-decoration:none;display:block;color:inherit">'
         . '<div class="kcard-head">'
         . '<div class="kcard-title">' . $e($display) . '</div>'
         . '</div>'
         . '<div class="kcard-foot">'
         . '<span class="type-badge ' . $badgeClass . '">' . $badgeLabel . '</span>'
         . '<span class="kcard-date">' . $e($when) . '</span>'
         . '</div>'
         . '</a>';
};

$inFlightLanes = [
    ['stage' => 'concept', 'label' => 'Concepts', 'token' => 'concept'],
    ['stage' => 'outline', 'label' => 'Outlines', 'token' => 'outline'],
    ['stage' => 'draft',   'label' => 'Drafts',   'token' => 'draft'],
];

$weekBuckets = [
    ['key' => 'this_week', 'label' => 'This Week'],
    ['key' => 'next_week', 'label' => 'Next Week'],
    ['key' => 'future',    'label' => 'Future'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Draft Writing — alexmchong.ca CMS</title>
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
$breadcrumb = 'Draft Writing';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'draft-writing';
  // Counts are scoped to the active view's own nav-item (matches every
  // other list view). Don't leak counts onto sibling items.
  $nav_counts    = [];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-draft-writing">
      <?php
      $title    = 'Draft Writing';
      $subtitle = 'Everything in flight and recently published — Concept through to live. Content moves left to right as it matures.';
      $actions  = '';
      require __DIR__ . '/../partials/view-header.php';
      ?>
      <?php require __DIR__ . '/../partials/flash.php'; ?>
      <div class="dash-meta reveal">
        <div class="dash-stat"><span class="num"><?= (int)$inFlight ?></span><span class="lbl">In flight</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat"><span class="num" style="color:var(--stage-concept)"><?= (int)count($byStage['concept']) ?></span><span class="lbl">Concept</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat"><span class="num" style="color:var(--stage-outline)"><?= (int)count($byStage['outline']) ?></span><span class="lbl">Outline</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat"><span class="num" style="color:var(--stage-draft)"><?= (int)count($byStage['draft']) ?></span><span class="lbl">Draft</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat"><span class="num" style="color:var(--stage-concept)"><?= (int)$scheduledTotal ?></span><span class="lbl">Scheduled</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat"><span class="num" style="color:var(--stage-published)"><?= (int)$liveTotal ?></span><span class="lbl">Live</span></div>
      </div>

      <div class="kanban-board"
           data-dnd-mode="pipeline"
           data-dnd-endpoint="/cms/articles/reorder-pipeline"
           data-csrf-token="<?= $e($csrf_token) ?>">

        <?php foreach ($inFlightLanes as $lane):
          $stage = $lane['stage'];
          $cards = $byStage[$stage];
        ?>
          <div class="kanban-lane" data-key="<?= $e($stage) ?>">
            <div class="lane-header">
              <div class="lane-dot" style="background:var(--stage-<?= $e($lane['token']) ?>)"></div>
              <div class="lane-title" style="color:var(--stage-<?= $e($lane['token']) ?>)"><?= $e($lane['label']) ?></div>
              <div class="lane-count"><?= (int)count($cards) ?></div>
            </div>
            <div class="lane-cards reveal">
              <?php if (count($cards) === 0): ?>
                <div class="idea-lane-empty">No drafts yet</div>
              <?php else:
                foreach ($cards as $card):
                  echo $renderInFlightCard($card, $stage);
                endforeach;
              endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="kanban-lane lane-scheduled" data-key="scheduled">
          <div class="lane-header">
            <div class="lane-dot" style="background:var(--stage-concept)"></div>
            <div class="lane-title" style="color:var(--stage-concept)">Scheduled</div>
            <div class="lane-count"><?= (int)$scheduledTotal ?></div>
          </div>
          <div class="lane-cards reveal">
            <?php if ($scheduledTotal === 0): ?>
              <div class="idea-lane-empty">Nothing scheduled</div>
            <?php else:
              foreach ($weekBuckets as $wb):
                $bucket = $scheduledBuckets[$wb['key']];
                if (count($bucket) === 0) continue;
            ?>
              <div class="kgroup-label"><?= $e($wb['label']) ?> · <?= (int)count($bucket) ?></div>
              <?php foreach ($bucket as $card) echo $renderScheduledCard($card); ?>
            <?php endforeach;
            endif; ?>
          </div>
        </div>

        <div class="kanban-lane lane-recently-published" data-key="recently-published">
          <div class="lane-header">
            <div class="lane-dot" style="background:var(--stage-published)"></div>
            <div class="lane-title" style="color:var(--stage-published)">Recently Published</div>
            <div class="lane-count"><?= (int)count($recentlyPublished) ?></div>
          </div>
          <div class="lane-cards reveal">
            <?php if (count($recentlyPublished) === 0): ?>
              <div class="idea-lane-empty">Nothing published yet</div>
            <?php else:
              foreach ($recentlyPublished as $card) echo $renderPublishedCard($card);
            endif; ?>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>

<script src="/cms/_assets/dragdrop.js"></script>
</body>
</html>
