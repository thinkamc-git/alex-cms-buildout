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
  .sub-counts { display:flex; gap:var(--space-24); padding:0 var(--space-24); margin:var(--space-16) 0; font-size:12px; color:var(--muted); }
  .sub-counts strong { color:var(--ink); font-weight:600; font-size:14px; margin-right:4px; }
  .sub-filters { display:flex; gap:12px; align-items:flex-end; padding:0 var(--space-24); margin:var(--space-12) 0 var(--space-16); flex-wrap:wrap; }
  .sub-filters .field { display:flex; flex-direction:column; gap:4px; }
  .sub-filters label { font-size:10px; text-transform:uppercase; letter-spacing:0.08em; color:var(--muted); font-weight:600; }
  .sub-filters select, .sub-filters input { padding:6px 8px; border:1px solid var(--border); border-radius:4px; font-size:12px; background:var(--surface); color:var(--ink); font-family:var(--font-sans); }
  .sub-filters .actions { margin-left:auto; display:flex; gap:8px; align-items:center; }
  .sub-filters .btn { padding:6px 12px; font-size:12px; border:1px solid var(--border); border-radius:4px; background:var(--surface); color:var(--ink); cursor:pointer; text-decoration:none; }
  .sub-filters .btn:hover { background:var(--bg-soft); }
  .sub-filters .btn.is-primary { background:var(--ink); color:var(--surface); border-color:var(--ink); }
  /* Sub-table is now rendered via partials/table.php with $variant='sub'.
     Visual styling lives in style-cms.css under .cms-table--sub /
     .table-card--sub / .sub-status / .btn-row-action / .btn-row-del. */
  .sub-status { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; letter-spacing:0.02em; }
  .sub-status.sub { background:var(--c-forest-soft, #d9ead7); color:var(--c-forest, #2d5a2d); }
  .sub-status.uns { background:var(--bg-soft); color:var(--muted); }
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

      <div class="sub-counts">
        <div><strong><?= (int)$counts['subscribed'] ?></strong>subscribed</div>
        <div><strong><?= (int)$counts['unsubscribed'] ?></strong>unsubscribed</div>
        <div><strong><?= (int)$counts['recent'] ?></strong>new in 30d</div>
      </div>

      <form class="sub-filters" method="get" action="/cms/subscribers">
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
          <button type="submit" class="btn is-primary">Apply</button>
          <a class="btn" href="/cms/subscribers">Reset</a>
          <a class="btn" href="<?= $e($exportHref) ?>">Export CSV</a>
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

      <div style="padding:0 var(--space-24)">
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
              ['html' => $toggleBtn . $deleteBtn, 'class' => 'cell-actions'],
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
