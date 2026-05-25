<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/index-editorial.php — Editorial Page layout (CMS-STRUCTURE §16).
 *
 * Same header treatment as the Basic Listing (mirrors the DS Full Page
 * Index showcase), with an extra hero card above the feed and an
 * optional row of curated picks. No invented section labels.
 *
 * Expects $ctx (set by render_index):
 *   $ctx['index']           — index row (or synthetic series_auto_index)
 *   $ctx['hero_card']       — single content row (or null)
 *   $ctx['featured_cards']  — array of content rows
 *   $ctx['feed_rows']       — array of content rows
 *   $ctx['is_series']       — bool: true when this is /series/[slug]/
 */
$idx            = $ctx['index']           ?? [];
$heroCard       = $ctx['hero_card']       ?? null;
$featuredCards  = $ctx['featured_cards']  ?? [];
$feedRows       = $ctx['feed_rows']       ?? [];
$isSeries       = (bool)($ctx['is_series'] ?? false);

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$showTitle = !empty($idx['show_title']) || $isSeries;
$title     = (string)($idx['title']    ?? '');
$subtitle  = (string)($idx['subtitle'] ?? '');
$eyebrow   = $isSeries ? 'Series' : humanize_slug((string)($idx['slug'] ?? ''));
$count     = count($feedRows) + ($heroCard ? 1 : 0) + count($featuredCards);

// Series indexes force no pill row. Custom/built-in indexes go through the
// usual builder.
$pillRow = $isSeries
    ? ['mode' => 'none', 'pills' => []]
    : build_index_pills($idx);

$catMap = [];
?>
<div class="index-page index-page--editorial<?= $isSeries ? ' index-page--series' : '' ?>">

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
        <div class="index-page-header-right">
          <span class="index-count" data-count-target><?= $count ?> <?= $count === 1 ? 'item' : 'items' ?></span>
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

  <?php if (is_array($heroCard)): ?>
    <div class="index-hero">
      <div class="cards-grid" style="grid-template-columns:1fr">
        <?php $card = $heroCard; require __DIR__ . '/partials/index-card.php'; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($featuredCards !== []): ?>
    <div class="index-featured">
      <div class="cards-grid">
        <?php foreach ($featuredCards as $card): ?>
          <?php require __DIR__ . '/partials/index-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($feedRows === []): ?>
    <?php if ($heroCard === null && $featuredCards === []): ?>
      <p class="index-empty">Nothing here yet.</p>
    <?php endif; ?>
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
    // See index-listing.php for the full docstring. Same OR-semantic
    // pill logic; cards are toggled via .is-filtered-out class so the
    // views.css .card{display:flex !important} rule doesn't block us.
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
        apply();
      });
    });
    apply();
  })();
  </script>
  <?php endif; ?>

</div>
