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

/**
 * Render aria-current="page" on the active nav item so screen readers
 * announce which page is selected.
 */
$activeAria = static function (string $id) use ($active_nav_id): string {
    return $id === $active_nav_id ? ' aria-current="page"' : '';
};
?>
<nav class="sidebar dot-surface" aria-label="CMS navigation">
  <div class="nav-section">
    <span class="nav-label">Overview</span>
    <a class="nav-item<?= $activeClass('pipeline') ?>"<?= $activeAria('pipeline') ?> href="/cms/" data-nav-id="pipeline">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="2.5" height="12" rx="1" stroke="currentColor" stroke-width="1.2"/><rect x="5.5" y="3" width="2.5" height="10" rx="1" stroke="currentColor" stroke-width="1.2"/><rect x="10" y="5" width="2.5" height="8" rx="1" stroke="currentColor" stroke-width="1.2"/></svg>
      Pipeline
    </a>
    <a class="nav-item<?= $activeClass('ideation') ?>"<?= $activeAria('ideation') ?> href="/cms/ideation" data-nav-id="ideation">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="6" r="3.5" stroke="currentColor" stroke-width="1.2"/><path d="M5.5 10.5h3M6.5 12.5h1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Ideation<?= $count('ideation') ?>
    </a>
    <span class="nav-item is-placeholder" data-nav-id="published" aria-disabled="true" title="Coming soon">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 7l2 2 3-3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Published<?= $count('published') ?>
    </span>
  </div>
  <div class="nav-section">
    <span class="nav-label">Content</span>
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
    <span class="nav-label">Audience</span>
    <a class="nav-item<?= $activeClass('subscribers') ?>"<?= $activeAria('subscribers') ?> href="/cms/subscribers" data-nav-id="subscribers">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M1.5 3.5h11v7h-11z" stroke="currentColor" stroke-width="1.2"/><path d="M1.5 4l5.5 4 5.5-4" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/></svg>
      Subscribers<?= $count('subscribers') ?>
    </a>
  </div>
  <div class="nav-section">
    <span class="nav-label">Structure</span>
    <a class="nav-item<?= $activeClass('templates') ?>"<?= $activeAria('templates') ?> href="/cms/content-template" data-nav-id="templates">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="12" height="4" stroke="currentColor" stroke-width="1.2"/><rect x="1" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/><rect x="8" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/></svg>
      Content Template
    </a>
    <a class="nav-item<?= $activeClass('categories') ?>"<?= $activeAria('categories') ?> href="/cms/categories" data-nav-id="categories">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><circle cx="4" cy="4" r="2" stroke="currentColor" stroke-width="1.2"/><circle cx="10" cy="4" r="2" stroke="currentColor" stroke-width="1.2"/><circle cx="4" cy="10" r="2" stroke="currentColor" stroke-width="1.2"/><circle cx="10" cy="10" r="2" stroke="currentColor" stroke-width="1.2"/></svg>
      Categories
    </a>
    <a class="nav-item<?= $activeClass('series') ?>"<?= $activeAria('series') ?> href="/cms/series" data-nav-id="series">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><circle cx="3" cy="7" r="1.5" stroke="currentColor" stroke-width="1.2"/><circle cx="7" cy="7" r="1.5" stroke="currentColor" stroke-width="1.2"/><circle cx="11" cy="7" r="1.5" stroke="currentColor" stroke-width="1.2"/><path d="M4.5 7h1M8.5 7h1" stroke="currentColor" stroke-width="1.2"/></svg>
      Series<?= $count('series') ?>
    </a>
    <a class="nav-item<?= $activeClass('redirects') ?>"<?= $activeAria('redirects') ?> href="/cms/redirects" data-nav-id="redirects">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><path d="M2 7h8M7 4l3 3-3 3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Redirects<?= $count('redirects') ?>
    </a>
  </div>
  <div class="nav-section">
    <span class="nav-label">Indexes</span>
    <a class="nav-item<?= $activeClass('indexes') ?>"<?= $activeAria('indexes') ?> href="/cms/indexes" data-nav-id="indexes">
      <svg class="nav-icon" aria-hidden="true" viewBox="0 0 14 14" fill="none"><rect x="1" y="1" width="12" height="4" stroke="currentColor" stroke-width="1.2"/><rect x="1" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/><rect x="8" y="7" width="5" height="6" stroke="currentColor" stroke-width="1.2"/></svg>
      Indexes<?= $count('indexes') ?>
    </a>
    <a class="nav-new-idx" href="/cms/indexes/new" data-nav-id="new-index">
      <svg width="10" height="10" aria-hidden="true" viewBox="0 0 10 10" fill="none"><path d="M5 1v8M1 5h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
      New Index
    </a>
  </div>
</nav>
