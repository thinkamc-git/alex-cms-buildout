<?php
/**
 * cms/views/subscribers.php — Newsletter subscribers admin (Phase 14).
 *
 * Audience group's first (and only) view. Lists every subscriber with
 * filters for status / source / date, and row actions to unsubscribe,
 * re-subscribe, or delete. The CSV export honors the active filters
 * (so a filtered view can be exported as-is).
 *
 * Each row's actions bind via HTML5 form="row-form-sub-N" to a per-row form
 * rendered before the table — same pattern as cms/views/categories.php
 * and cms/views/redirects.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/subscribers.php';

Auth::require_login();
$csrf_token = Csrf::token();

$errors = [];
$flash  = '';

// CSV export short-circuits before any HTML is emitted.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && (string)($_GET['export'] ?? '') === 'csv') {
    export_subscribers_csv([
        'status' => (string)($_GET['status'] ?? '') ?: null,
        'source' => (string)($_GET['source'] ?? '') ?: null,
        'since'  => (string)($_GET['since']  ?? '') ?: null,
        'until'  => (string)($_GET['until']  ?? '') ?: null,
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Session expired. Reload the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $id     = (int)($_POST['id'] ?? 0);
        if ($action === 'unsubscribe' && $id > 0) {
            unsubscribe_subscriber($id);
            $flash = 'Marked unsubscribed.';
        } elseif ($action === 'resubscribe' && $id > 0) {
            resubscribe_subscriber($id);
            $flash = 'Re-subscribed.';
        } elseif ($action === 'delete' && $id > 0) {
            delete_subscriber($id);
            $flash = 'Subscriber deleted.';
        } else {
            $errors[] = 'Unknown action.';
        }

        if (count($errors) === 0) {
            $qs = $_GET ?: [];
            $qs['flash'] = $flash;
            header('Location: /cms/subscribers?' . http_build_query($qs));
            exit;
        }
    }
}

if ($flash === '' && isset($_GET['flash'])) {
    $flash = (string)$_GET['flash'];
}

$activeStatus = (string)($_GET['status'] ?? '');
$activeSource = (string)($_GET['source'] ?? '');
$activeSince  = (string)($_GET['since']  ?? '');
$activeUntil  = (string)($_GET['until']  ?? '');

$rows    = list_subscribers([
    'status' => $activeStatus ?: null,
    'source' => $activeSource ?: null,
    'since'  => $activeSince  ?: null,
    'until'  => $activeUntil  ?: null,
]);
$sources = list_subscriber_sources();
$counts  = subscriber_counts();

// Build the CSV export href that mirrors the current filters.
$exportQs = array_filter([
    'export' => 'csv',
    'status' => $activeStatus,
    'source' => $activeSource,
    'since'  => $activeSince,
    'until'  => $activeUntil,
], static fn($v) => $v !== '');
$exportHref = '/cms/subscribers?' . http_build_query($exportQs);

define('CMS_PARTIAL_OK', true);
header('Content-Type: text/html; charset=utf-8');

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$fmt = static function (?string $ts) use ($e): string {
    if ($ts === null || $ts === '' || $ts === '0000-00-00 00:00:00') return '—';
    $t = strtotime((string)$ts);
    return $e(date('Y-m-d H:i', $t ?: time()));
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="/_layout/favicon-cms<?= (defined('APP_ENV') && APP_ENV === 'staging') ? '-stage' : '' ?>.png">
<title>Subscribers — alexmchong.ca CMS</title>
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
  /* Filter rail — kept inline since this view doesn't share the
     filter-bar partial's pill-rail vocabulary. Uses canonical .field-input
     / .field-select / .btn-sec / .btn-pri so it visually matches the rest
     of the CMS. */
  /* Phase 21.7 — short single-row layout: leading "Filter:" caption,
     inline label+control pairs, actions trail the filters (left-aligned). */
  .sub-filters { display:flex; gap:var(--space-12); align-items:center; padding:var(--space-8) var(--space-24); border-bottom:var(--rule-faint); background:var(--canvas-raised); flex-wrap:wrap; }
  .sub-filters .filter-caption { font-family:var(--font-cond); font-size:var(--text-micro); font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--muted); }
  .sub-filters .field { display:flex; flex-direction:row; align-items:center; gap:var(--space-6); }
  .sub-filters label { font-family:var(--font-cond); font-size:var(--text-micro); font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--muted); }
  .sub-filters select { height:28px; box-sizing:border-box; padding:0 28px 0 var(--space-12); border:1px solid var(--ink-18); border-radius:var(--r-pill); background:var(--surface); font-family:var(--font-mono); font-size:var(--text-meta); color:var(--primary); outline:none; cursor:pointer; appearance:none; background-image:url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1L5 5L9 1' stroke='%23818080' stroke-width='1.5'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; }
  .sub-filters input { height:28px; box-sizing:border-box; padding:0 var(--space-12); border:1px solid var(--ink-18); border-radius:var(--r-pill); background:var(--surface); font-family:var(--font-mono); font-size:var(--text-meta); color:var(--primary); outline:none; }
  .sub-filters .actions { display:flex; gap:var(--space-8); align-items:center; }
  .sub-status { display:inline-flex; align-items:center; padding:2px var(--space-8); border-radius:2px; font-family:var(--font-mono); font-size:var(--text-micro); font-weight:500; letter-spacing:0.06em; text-transform:uppercase; line-height:1.5; white-space:nowrap; }
  .sub-status.sub { color:var(--stage-published); background:color-mix(in srgb,var(--stage-published) 10%,transparent); border:1px solid color-mix(in srgb,var(--stage-published) 28%,transparent); }
  .sub-status.uns { color:var(--muted); background:var(--ink-08); border:1px solid var(--ink-18); }
  /* Subscribers table now uses the canonical .row-actions wrapper for
     cell actions — no extra inline style needed. */
</style>
</head>
<body>

<?php
$breadcrumb = 'Subscribers';
require __DIR__ . '/../partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'subscribers';
  $nav_counts    = ['subscribers' => $counts['subscribed']];
  require __DIR__ . '/../partials/sidebar.php';
  ?>

  <main class="main" id="main" tabindex="-1">
    <div class="view active" id="view-subscribers">
      <?php
      $title    = 'Subscribers';
      $subtitle = 'Captured from the public newsletter form. Re-subscribers update the existing row — every email here is unique.';
      require __DIR__ . '/../partials/view-header.php';
      ?>

      <?php
      $heading = "Couldn't update:";
      require __DIR__ . '/../partials/form-errors.php';
      require __DIR__ . '/../partials/flash.php';
      ?>

      <div class="dash-meta">
        <div class="dash-stat"><span class="num"><?= (int)$counts['subscribed'] ?></span><span class="lbl">Subscribed</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat"><span class="num"><?= (int)$counts['unsubscribed'] ?></span><span class="lbl">Unsubscribed</span></div>
        <div class="dash-stat-div"></div>
        <div class="dash-stat"><span class="num"><?= (int)$counts['recent'] ?></span><span class="lbl" style="text-transform:none;letter-spacing:0">New in last 30 days</span></div>
      </div>

      <form class="sub-filters" method="get" action="/cms/subscribers">
        <span class="filter-caption">Filter:</span>
        <div class="field">
          <label for="status">Status</label>
          <select name="status" id="status">
            <option value="">All</option>
            <option value="subscribed"<?= $activeStatus === 'subscribed' ? ' selected' : '' ?>>Subscribed</option>
            <option value="unsubscribed"<?= $activeStatus === 'unsubscribed' ? ' selected' : '' ?>>Unsubscribed</option>
          </select>
        </div>
        <div class="field">
          <label for="source">Source</label>
          <select name="source" id="source">
            <option value="">All</option>
            <?php foreach ($sources as $src): ?>
              <option value="<?= $e($src) ?>"<?= $activeSource === $src ? ' selected' : '' ?>><?= $e($src) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="since">From</label>
          <input type="date" name="since" id="since" value="<?= $e($activeSince) ?>">
        </div>
        <div class="field">
          <label for="until">To</label>
          <input type="date" name="until" id="until" value="<?= $e($activeUntil) ?>">
        </div>
        <div class="actions">
          <button type="submit" class="btn-pri">Apply filter</button>
          <a class="btn-sec" href="/cms/subscribers">Reset</a>
          <a class="btn-sec" href="<?= $e($exportHref) ?>">Export CSV</a>
        </div>
      </form>

      <?php
      // Per-row forms — one per existing row, rendered before the table.
      foreach ($rows as $r):
        $rid = 'row-form-sub-' . (int)$r['id'];
      ?>
        <form id="<?= $e($rid) ?>" method="post" action="/cms/subscribers" style="display:none">
          <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        </form>
      <?php endforeach; ?>

      <div class="content-area">
      <?php
      $columns = [
          ['label' => 'Email',      'width' => '28%'],
          ['label' => 'Name',       'width' => '16%'],
          ['label' => 'Subscribed', 'width' => '18%'],
          ['label' => 'Source',     'width' => '14%'],
          ['label' => 'Status',     'width' => '10%'],
          ['label' => 'Actions',    'width' => '14%'],
      ];
      $subRows = [];
      foreach ($rows as $r) {
          $rid    = 'row-form-sub-' . (int)$r['id'];
          $subbed = ($r['unsubscribed_at'] === null || $r['unsubscribed_at'] === '');
          $statusCell = $subbed
              ? '<span class="sub-status sub">subscribed</span>'
              : '<span class="sub-status uns">unsubscribed</span>';
          $toggleBtn = $subbed
              ? '<button type="submit" name="action" value="unsubscribe" form="' . $e($rid) . '" class="btn-sec btn-tiny" title="Mark unsubscribed">Unsubscribe</button>'
              : '<button type="submit" name="action" value="resubscribe" form="' . $e($rid) . '" class="btn-sec btn-tiny" title="Mark re-subscribed">Re-subscribe</button>';
          $emailAttr = $e((string)$r['email']);
          $deleteBtn = '<button type="submit" name="action" value="delete" form="' . $e($rid) . '" class="btn-icon btn-icon-danger" title="Delete" aria-label="Delete"'
                     . ' data-confirm="Delete subscriber &quot;' . $emailAttr . '&quot;? This can&#039;t be undone.">'
                     . '<svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                     . '</button>';
          $subRows[] = [
              ['html' => $e((string)$r['email']),               'class' => 'is-mono'],
              ['html' => $e((string)($r['name'] ?? ''))],
              ['html' => $fmt((string)($r['subscribed_at'] ?? '')), 'class' => 'is-mono'],
              ['html' => $e((string)($r['source'] ?? '')),      'class' => 'is-mono'],
              ['html' => $statusCell],
              ['html' => '<div class="row-actions">' . $toggleBtn . $deleteBtn . '</div>', 'class' => 'cell-actions'],
          ];
      }
      $rowsOriginal = $rows;
      $rows         = $subRows;
      $empty_text   = 'No subscribers match the current filters.';
      $variant      = 'sub';
      require __DIR__ . '/../partials/table.php';
      $rows = $rowsOriginal;
      ?>
      </div>
    </div>
  </main>
</div>

</body>
</html>
