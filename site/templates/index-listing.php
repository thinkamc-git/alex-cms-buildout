<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/index-listing.php — Basic Listing layout (CMS-STRUCTURE §16).
 *
 * Mirrors the "Full Page Index" treatment in site/_design-system/index.html
 * §04: left-aligned header (eyebrow + title with serif-italic emphasis),
 * count chip on the right, header divider, then a 3-col card grid.
 *
 * Expects $ctx (set by render_index):
 *   $ctx['index']     — index row
 *   $ctx['feed_rows'] — array of content rows from list_index_feed()
 */
$idx      = $ctx['index']     ?? [];
$feedRows = $ctx['feed_rows'] ?? [];

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$showTitle = !empty($idx['show_title']);
$title     = (string)($idx['title']    ?? '');
$subtitle  = (string)($idx['subtitle'] ?? '');
$eyebrow   = humanize_slug((string)($idx['slug'] ?? ''));
$count     = count($feedRows);

$pillRow = build_index_pills($idx);
$catMap  = [];
?>
<div class="index-page">

  <?php if ($showTitle && ($title !== '' || $subtitle !== '' || $eyebrow !== '')): ?>
    <header class="index-page-header">
      <div class="index-page-header-row">
        <div class="index-page-header-left">
          <?php if ($eyebrow !== ''): ?>
            <div class="index-eyebrow"><?= $e($eyebrow) ?></div>
          <?php endif; ?>
          <?php if ($title !== ''): ?>
            <h1 class="index-title"><?= render_title_emphasis($title) ?></h1>
          <?php endif; ?>
          <?php if ($subtitle !== ''): ?>
            <p class="index-subtitle"><?= $e($subtitle) ?></p>
          <?php endif; ?>
        </div>
      </div>

    </header>
  <?php endif; ?>

  <?php if ($pillRow['mode'] !== 'none' && $pillRow['pills'] !== []): ?>
    <div class="controller" data-pill-mode="<?= $e($pillRow['mode']) ?>">
      <div class="controller-row">
        <span class="ctrl-label"><?= $pillRow['mode'] === 'types' ? 'Type' : 'Topic' ?></span>
        <div class="pill-group">
          <button type="button" class="fp on" data-cat="all">All</button>
          <?php foreach ($pillRow['pills'] as $p): ?>
            <button type="button"
                    class="fp"
                    data-cat="<?= $e($p['key']) ?>"><?= $e($p['label']) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($feedRows === []): ?>
    <p class="index-empty">Nothing here yet.</p>
  <?php else: ?>
    <div class="index-grid cards-grid">
      <?php foreach ($feedRows as $card): ?>
        <?php require __DIR__ . '/partials/index-card.php'; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($pillRow['mode'] !== 'none' && $pillRow['pills'] !== []): ?>
  <script>
  (function () {
    'use strict';
    // OR-semantic filter pills:
    //   - "All" is the default. Clicking it deselects every other pill.
    //   - Clicking a specific pill toggles it on/off, deselecting "All".
    //   - With no specific pill on, "All" turns back on automatically.
    //   - Each card has data-category and data-type. Visibility = card
    //     matches AT LEAST ONE active pill, or "All" is on.
    //
    // Visibility uses the .is-filtered-out class. views.css declares
    // .card { display: flex !important }, so direct style.display=none
    // is impotent — toggling a class with its own !important wins.
    var page = document.currentScript.closest('.index-page');
    if (!page) return;
    var ctrl = page.querySelector('.controller');
    if (!ctrl) return;
    var attr = ctrl.getAttribute('data-pill-mode') === 'types' ? 'type' : 'category';
    var pills = Array.prototype.slice.call(ctrl.querySelectorAll('.fp'));
    var cards = Array.prototype.slice.call(page.querySelectorAll('.cards-grid > .card'));
    var countTarget = page.querySelector('[data-count-target]');

    function activeKeys() {
      return pills
        .filter(function (p) { return p.classList.contains('on') && p.getAttribute('data-cat') !== 'all'; })
        .map(function (p) { return p.getAttribute('data-cat'); });
    }

    function apply() {
      var keys = activeKeys();
      var allOn = pills.some(function (p) { return p.getAttribute('data-cat') === 'all' && p.classList.contains('on'); });
      var visible = 0;
      cards.forEach(function (c) {
        var v = c.getAttribute('data-' + attr) || '';
        var show = allOn || (keys.length > 0 && keys.indexOf(v) !== -1);
        c.classList.toggle('is-filtered-out', !show);
        if (show) visible++;
      });
      if (countTarget) {
        countTarget.textContent = visible + ' ' + (visible === 1 ? 'item' : 'items');
      }
    }

    pills.forEach(function (p) {
      p.addEventListener('click', function () {
        var key = p.getAttribute('data-cat');
        if (key === 'all') {
          pills.forEach(function (q) { q.classList.toggle('on', q === p); });
        } else {
          var allPill = pills.find(function (q) { return q.getAttribute('data-cat') === 'all'; });
          if (allPill) allPill.classList.remove('on');
          p.classList.toggle('on');
          if (activeKeys().length === 0 && allPill) allPill.classList.add('on');
        }
        var grid = page.querySelector('.cards-grid');
        if (window.CardGrid && grid) window.CardGrid.reenter(grid, apply);
        else apply();
      });
    });

    apply();
  })();
  </script>
  <?php endif; ?>

</div>
