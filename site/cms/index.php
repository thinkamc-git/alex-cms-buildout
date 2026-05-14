<?php
/**
 * cms/index.php — post-login dashboard.
 *
 * Phase 5 ships the admin shell: topbar + sidebar + view-header + filter-bar
 * + table, composed from the partials under cms/partials/. The body is an
 * empty-state Articles-shaped view that exercises every partial; real data
 * starts flowing in Phase 6a (Articles in CMS).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

Auth::require_login();
$user       = Auth::current_user();
$email      = (string)($user['email'] ?? '');
$csrf_token = Csrf::token();

// Gate: partials check this constant before rendering. Without it, anyone
// could fetch /cms/partials/sidebar.php and get raw chrome HTML back.
define('CMS_PARTIAL_OK', true);

header('Content-Type: text/html; charset=utf-8');
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
require __DIR__ . '/partials/topbar.php';
?>

<div class="layout">
  <?php
  $active_nav_id = 'pipeline';
  $nav_counts    = [];
  require __DIR__ . '/partials/sidebar.php';
  ?>

  <main class="main">
    <div class="view active" id="view-pipeline">
      <?php
      $title    = 'Pipeline';
      $subtitle = 'Everything in motion, grouped by stage. Content types start populating here in Phase 6.';
      $actions  = '<button type="button" class="btn-pri" disabled title="Available in Phase 6a">+ New</button>';
      require __DIR__ . '/partials/view-header.php';
      ?>

      <?php
      $groups = [
          [
              'label' => 'Category',
              'mode'  => 'or',
              'pills' => [
                  ['label' => 'All', 'active' => true, 'all' => true],
                  ['label' => 'UX Industry'],
                  ['label' => 'Leading Design'],
                  ['label' => 'For Designers'],
              ],
          ],
          [
              'label' => 'Special Tag',
              'mode'  => 'or',
              'pills' => [
                  ['label' => 'All', 'active' => true, 'all' => true],
                  ['label' => 'Framework'],
                  ['label' => 'Principle'],
              ],
          ],
      ];
      require __DIR__ . '/partials/filter-bar.php';
      ?>

      <div class="content-area">
        <div class="content-block">
          <div class="content-block-header">
            <div>
              <span class="content-block-label">All content</span>
              <span class="content-block-sublabel">Idea · Concept · Outline · Draft · Published</span>
            </div>
            <span class="content-block-count">0 entries</span>
          </div>
          <?php
          $columns = [
              ['label' => 'Title',    'width' => '36%'],
              ['label' => 'Category', 'width' => '14%'],
              ['label' => 'Stage',    'width' => '11%'],
              ['label' => 'Tags',     'width' => '14%'],
              ['label' => 'Series',   'width' => '13%'],
              ['label' => 'Actions',  'width' => '12%'],
          ];
          $rows = [];
          $empty_text = 'No entries yet — content types ship in Phase 6 onward.';
          require __DIR__ . '/partials/table.php';
          ?>
          <p style="margin-top:var(--space-16);font-size:var(--text-tiny);color:var(--muted)">
            Signed in as <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?> ·
            <a href="/cms/account" style="color:var(--muted);text-decoration:underline">Account</a>
          </p>
        </div>
      </div>
    </div>
  </main>
</div>

</body>
</html>
