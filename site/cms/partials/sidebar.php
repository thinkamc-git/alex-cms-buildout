<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/sidebar.php — left navigation rail.
 *
 * Phase 19 reorg: sidebar restructured to the new IA, 7 groups in order
 *   Overview · Writer's Desk · Library · Site · Collections · Audience · System
 * Items not yet wired show as muted `.is-placeholder` spans (no href,
 * `aria-disabled`, default cursor). Indexes folded into Collections.
 * Post Templates points to the existing Content Template view at
 * `/cms/post-template` (Phase 14.5's view, label-only rename here).
 *
 * Inputs (set before include):
 *   $active_nav_id  string  Slug of the currently-active nav item, matching
 *                           the data-nav-id below. Defaults to 'draft-writing'.
 *   $nav_counts     array   Optional assoc map of nav-id => integer count
 *                           (e.g. ['articles' => 8]). When a count is set
 *                           for an item that supports counts, a .nav-count
 *                           pill renders. Omit for empty-state chrome.
 */

$active_nav_id = (string)($active_nav_id ?? 'draft-writing');
$nav_counts    = (array)($nav_counts ?? []);

/* Phase 21.7 — only these nav items render counts. Posts show their
   published-count (callers compute); series shows total; subscribers
   shows "new since last visit" derived from the cms_subs_last_seen
   cookie (so it acts as an unread badge). Everything else is suppressed. */
$count_allowed = ['articles','journals','live-sessions','experiments','series','subscribers'];

/* Subscribers: compute the new-since-last-visit count and override any
   caller-supplied value. Suppressed when the user is already on the
   subscribers page (no badge against the page you're viewing). */
if ($active_nav_id !== 'subscribers') {
    require_once __DIR__ . '/../../lib/subscribers.php';
    $_subsLastSeen = (string)($_COOKIE['cms_subs_last_seen'] ?? '');
    $_subsNew      = subscribers_count_since($_subsLastSeen);
    if ($_subsNew > 0) {
        $nav_counts['subscribers'] = $_subsNew;
    } else {
        unset($nav_counts['subscribers']);
    }
} else {
    unset($nav_counts['subscribers']);
}

$count = static function (string $id) use ($nav_counts, $count_allowed): string {
    if (!in_array($id, $count_allowed, true)) return '';
    if (!array_key_exists($id, $nav_counts)) return '';
    $n = (int)$nav_counts[$id];
    if ($n === 0) return '';
    // Subscribers' count is a notification-style "new since last visit"
    // count — gets the prominent .is-new variant. All other counts are
    // plain totals and use the subtle default.
    $cls = $id === 'subscribers' ? 'nav-count is-new' : 'nav-count';
    return ' <span class="' . $cls . '">' . htmlspecialchars((string)$n, ENT_QUOTES, 'UTF-8') . '</span>';
};
$activeClass = static function (string $id) use ($active_nav_id): string {
    return $id === $active_nav_id ? ' active' : '';
};
$activeAria = static function (string $id) use ($active_nav_id): string {
    return $id === $active_nav_id ? ' aria-current="page"' : '';
};
?>
<nav class="sidebar dot-surface" aria-label="CMS navigation">

  <div class="nav-section">
    <span class="nav-label">Overview</span>
    <span class="nav-item is-placeholder" data-nav-id="dashboard" aria-disabled="true" title="Coming soon">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="5.5" height="5.5" stroke="currentColor" stroke-width="1.2"/><rect x="7.5" y="1" width="5.5" height="5.5" stroke="currentColor" stroke-width="1.2"/><rect x="1" y="7.5" width="5.5" height="5.5" stroke="currentColor" stroke-width="1.2"/><rect x="7.5" y="7.5" width="5.5" height="5.5" stroke="currentColor" stroke-width="1.2"/></svg>
      Dashboard
    </span>
    <span class="nav-item is-placeholder" data-nav-id="analytics" aria-disabled="true" title="Coming soon">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M1 12h12M3 10V6M6 10V3M9 10V7M12 10V5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Analytics
    </span>
    <span class="nav-item is-placeholder" data-nav-id="post-history" aria-disabled="true" title="Coming soon">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><rect x="1.5" y="3" width="11" height="9.5" stroke="currentColor" stroke-width="1.2"/><path d="M1.5 6h11" stroke="currentColor" stroke-width="1.2"/><path d="M4 1.5v2.5M10 1.5v2.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Post History
    </span>
  </div>

  <div class="nav-section">
    <span class="nav-label">Writer's Desk</span>
    <a class="nav-item<?= $activeClass('ideation') ?>"<?= $activeAria('ideation') ?> href="/cms/ideation" data-nav-id="ideation">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="6" r="3.5" stroke="currentColor" stroke-width="1.2"/><path d="M5.5 10.5h3M6.5 12.5h1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Ideation Board<?= $count('ideation') ?>
    </a>
    <a class="nav-item<?= $activeClass('draft-writing') ?>"<?= $activeAria('draft-writing') ?> href="/cms/" data-nav-id="draft-writing">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="2.5" height="12" rx="1" stroke="currentColor" stroke-width="1.2"/><rect x="5.5" y="3" width="2.5" height="10" rx="1" stroke="currentColor" stroke-width="1.2"/><rect x="10" y="5" width="2.5" height="8" rx="1" stroke="currentColor" stroke-width="1.2"/></svg>
      Draft Writing<?= $count('draft-writing') ?>
    </a>
  </div>

  <div class="nav-section">
    <span class="nav-label">Library</span>
    <a class="nav-item<?= $activeClass('articles') ?>"<?= $activeAria('articles') ?> href="/cms/articles" data-nav-id="articles">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M2 2h10M2 5h10M2 8h6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Articles<?= $count('articles') ?>
    </a>
    <a class="nav-item<?= $activeClass('journals') ?>"<?= $activeAria('journals') ?> href="/cms/journals" data-nav-id="journals">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M3 1h8v12H3z" stroke="currentColor" stroke-width="1.2"/><path d="M5 4h4M5 7h4M5 10h2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Journals<?= $count('journals') ?>
    </a>
    <a class="nav-item<?= $activeClass('live-sessions') ?>"<?= $activeAria('live-sessions') ?> href="/cms/live-sessions" data-nav-id="live-sessions">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.2"/><circle cx="7" cy="7" r="2" fill="currentColor" opacity="0.4"/></svg>
      Live Sessions<?= $count('live-sessions') ?>
    </a>
    <a class="nav-item<?= $activeClass('experiments') ?>"<?= $activeAria('experiments') ?> href="/cms/experiments" data-nav-id="experiments">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M5 1v5L2 12h10L9 6V1" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M4 1h6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Experiments<?= $count('experiments') ?>
    </a>
  </div>

  <div class="nav-section">
    <span class="nav-label">Site</span>
    <a class="nav-item<?= $activeClass('pages') ?>"<?= $activeAria('pages') ?> href="/cms/pages" data-nav-id="pages">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M3 1h6l2 2v10H3z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M9 1v2h2" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
      Pages<?= $count('pages') ?>
    </a>
    <a class="nav-item<?= $activeClass('navigation') ?>"<?= $activeAria('navigation') ?> href="/cms/navigation" data-nav-id="navigation">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M2 3h10M2 7h10M2 11h10" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Navigation<?= $count('navigation') ?>
    </a>
    <a class="nav-item<?= $activeClass('redirects') ?>"<?= $activeAria('redirects') ?> href="/cms/redirects" data-nav-id="redirects">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M2 7h8M7 4l3 3-3 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Redirects<?= $count('redirects') ?>
    </a>
  </div>

  <div class="nav-section">
    <span class="nav-label">Collections</span>
    <a class="nav-item<?= $activeClass('categories') ?>"<?= $activeAria('categories') ?> href="/cms/categories" data-nav-id="categories">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><circle cx="4" cy="4" r="2" stroke="currentColor" stroke-width="1.2"/><circle cx="10" cy="4" r="2" stroke="currentColor" stroke-width="1.2"/><circle cx="4" cy="10" r="2" stroke="currentColor" stroke-width="1.2"/><circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.2"/></svg>
      Categories
    </a>
    <a class="nav-item<?= $activeClass('series') ?>"<?= $activeAria('series') ?> href="/cms/series" data-nav-id="series">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><circle cx="3" cy="7" r="1.5" stroke="currentColor" stroke-width="1.2"/><circle cx="7" cy="7" r="1.5" stroke="currentColor" stroke-width="1.2"/><circle cx="11" cy="7" r="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 7h1M8.5 7h1" stroke="currentColor" stroke-width="1.2"/></svg>
      Series<?= $count('series') ?>
    </a>
    <a class="nav-item<?= $activeClass('indexes') ?>"<?= $activeAria('indexes') ?> href="/cms/indexes" data-nav-id="indexes">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="12" height="4" stroke="currentColor" stroke-width="1.2"/><rect x="1" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/><rect x="8" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/></svg>
      Indexes<?= $count('indexes') ?>
    </a>
  </div>

  <div class="nav-section">
    <span class="nav-label">Audience</span>
    <a class="nav-item<?= $activeClass('subscribers') ?>"<?= $activeAria('subscribers') ?> href="/cms/subscribers" data-nav-id="subscribers">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M1.5 3.5h11v7h-11z" stroke="currentColor" stroke-width="1.2"/><path d="M1.5 4l5.5 4 5.5-4" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
      Subscribers<?= $count('subscribers') ?>
    </a>
  </div>

  <div class="nav-section">
    <span class="nav-label">System</span>
    <a class="nav-item<?= $activeClass('post-templates') ?>"<?= $activeAria('post-templates') ?> href="/cms/post-template" data-nav-id="post-templates">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="12" height="4" stroke="currentColor" stroke-width="1.2"/><rect x="1" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/><rect x="8" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/></svg>
      Post Templates
    </a>
    <a class="nav-item<?= $activeClass('settings') ?>"<?= $activeAria('settings') ?> href="/cms/settings" data-nav-id="settings">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M7 1.5l.85.6 1.05-.15.45.96.95.46-.15 1.05.6.85-.6.85.15 1.05-.95.46-.45.96-1.05-.15L7 12.5l-.85-.6-1.05.15-.45-.96-.95-.46.15-1.05-.6-.85.6-.85-.15-1.05.95-.46.45-.96 1.05.15z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><circle cx="7" cy="7" r="1.8" stroke="currentColor" stroke-width="1.2"/></svg>
      Settings
    </a>
  </div>

</nav>
