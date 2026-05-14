<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/sidebar.php — left navigation rail.
 *
 * Inputs (set before include):
 *   $active_nav_id  string  Slug of the currently-active nav item, matching
 *                           the data-nav-id below. Defaults to 'pipeline'.
 *   $nav_counts     array   Optional assoc map of nav-id => integer count
 *                           (e.g. ['articles' => 8]). When a count is set
 *                           for an item that supports counts, a .nav-count
 *                           pill renders. Omit for empty-state chrome.
 *
 * The structure mirrors docs/design-mockups/cms-ui.html exactly. Items are
 * rendered as <a> with href="#" placeholders for Phase 5 — Phase 6a (Articles
 * in CMS) wires the first real route, and each later content-type phase
 * fills in its own href. Until then the chrome is visible but inert.
 */

$active_nav_id = (string)($active_nav_id ?? 'pipeline');
$nav_counts    = (array)($nav_counts ?? []);

/**
 * Render a count pill if a count is supplied for this nav id.
 */
$count = static function (string $id) use ($nav_counts): string {
    if (!array_key_exists($id, $nav_counts)) return '';
    $n = (int)$nav_counts[$id];
    return ' <span class="nav-count">' . htmlspecialchars((string)$n, ENT_QUOTES, 'UTF-8') . '</span>';
};

/**
 * Render the `active` class for the currently-selected nav item.
 */
$activeClass = static function (string $id) use ($active_nav_id): string {
    return $id === $active_nav_id ? ' active' : '';
};
?>
<nav class="sidebar dot-surface">
  <div class="nav-section">
    <span class="nav-label">Overview</span>
    <a class="nav-item<?= $activeClass('pipeline') ?>" href="/cms/" data-nav-id="pipeline">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="2.5" height="12" rx="1" stroke="currentColor" stroke-width="1.2"/><rect x="5.5" y="3" width="2.5" height="10" rx="1" stroke="currentColor" stroke-width="1.2"/><rect x="10" y="5" width="2.5" height="8" rx="1" stroke="currentColor" stroke-width="1.2"/></svg>
      Pipeline
    </a>
    <a class="nav-item<?= $activeClass('ideation') ?>" href="#" data-nav-id="ideation">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="6" r="3.5" stroke="currentColor" stroke-width="1.2"/><path d="M5.5 10.5h3M6.5 12.5h1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Ideation<?= $count('ideation') ?>
    </a>
    <a class="nav-item<?= $activeClass('published') ?>" href="#" data-nav-id="published">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 7l2 2 3-3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Published<?= $count('published') ?>
    </a>
  </div>
  <div class="nav-section">
    <span class="nav-label">Content</span>
    <a class="nav-item<?= $activeClass('articles') ?>" href="#" data-nav-id="articles">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><path d="M2 2h10M2 5h10M2 8h6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Articles<?= $count('articles') ?>
    </a>
    <a class="nav-item<?= $activeClass('journals') ?>" href="#" data-nav-id="journals">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><path d="M3 1h8v12H3z" stroke="currentColor" stroke-width="1.2"/><path d="M5 4h4M5 7h4M5 10h2" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Journals<?= $count('journals') ?>
    </a>
    <a class="nav-item<?= $activeClass('live-sessions') ?>" href="#" data-nav-id="live-sessions">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.2"/><circle cx="7" cy="7" r="2" fill="currentColor" opacity="0.4"/></svg>
      Live Sessions<?= $count('live-sessions') ?>
    </a>
    <a class="nav-item<?= $activeClass('experiments') ?>" href="#" data-nav-id="experiments">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><path d="M5 1v5L2 12h10L9 6V1" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M4 1h6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Experiments<?= $count('experiments') ?>
    </a>
  </div>
  <div class="nav-section">
    <span class="nav-label">Structure</span>
    <a class="nav-item<?= $activeClass('templates') ?>" href="#" data-nav-id="templates">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="12" height="4" stroke="currentColor" stroke-width="1.2"/><rect x="1" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/><rect x="8" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/></svg>
      Content Template
    </a>
    <a class="nav-item<?= $activeClass('categories') ?>" href="#" data-nav-id="categories">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><circle cx="4" cy="4" r="2" stroke="currentColor" stroke-width="1.2"/><circle cx="10" cy="4" r="2" stroke="currentColor" stroke-width="1.2"/><circle cx="4" cy="10" r="2" stroke="currentColor" stroke-width="1.2"/><circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.2"/></svg>
      Categories
    </a>
    <a class="nav-item<?= $activeClass('series') ?>" href="#" data-nav-id="series">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><circle cx="3" cy="7" r="1.5" stroke="currentColor" stroke-width="1.2"/><circle cx="7" cy="7" r="1.5" stroke="currentColor" stroke-width="1.2"/><circle cx="11" cy="7" r="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 7h1M8.5 7h1" stroke="currentColor" stroke-width="1.2"/></svg>
      Series<?= $count('series') ?>
    </a>
    <a class="nav-item<?= $activeClass('redirects') ?>" href="#" data-nav-id="redirects">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><path d="M2 7h8M7 4l3 3-3 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Redirects<?= $count('redirects') ?>
    </a>
  </div>
  <div class="nav-section">
    <span class="nav-label">Indexes</span>
    <a class="nav-item sub<?= $activeClass('idx-writing') ?>" href="#" data-nav-id="idx-writing" title="Basic Listing">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><path d="M2 2h10M2 5h7M2 8h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      /writing
    </a>
    <a class="nav-item sub<?= $activeClass('idx-journal') ?>" href="#" data-nav-id="idx-journal" title="Basic Listing">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><path d="M2 2h10M2 5h7M2 8h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      /journal
    </a>
    <a class="nav-item sub<?= $activeClass('idx-experiments') ?>" href="#" data-nav-id="idx-experiments" title="Basic Listing">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><path d="M2 2h10M2 5h7M2 8h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      /experiments
    </a>
    <a class="nav-item sub<?= $activeClass('idx-live-sessions') ?>" href="#" data-nav-id="idx-live-sessions" title="Basic Listing">
      <svg class="nav-icon" viewBox="0 0 14 14" fill="none"><path d="M2 2h10M2 5h7M2 8h5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      /live-sessions
    </a>
    <a class="nav-new-idx" href="#" data-nav-id="new-index">
      <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M5 1v8M1 5h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
      New Index
    </a>
  </div>
</nav>
