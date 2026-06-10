<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * templates/index-editorial.php — Editorial Page layout (CMS-STRUCTURE §16).
 *
 * Iterates the resolved section stack ($ctx['sections']). Each section
 * carries:
 *   _cards : resolved card rows (drives the grid)
 *   _pills : visitor-filter pills (feed sections only, may be null)
 *
 * Hero      → single full-width card.
 * Curated   → grid OR carousel of picked cards.
 * Feed      → grid OR carousel of query-resolved cards, optional pills.
 */
$idx       = $ctx['index']    ?? [];
$sections  = $ctx['sections'] ?? [];
$isSeries  = (bool)($ctx['is_series'] ?? false);

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$showTitle = !empty($idx['show_title']) || $isSeries;
$title     = (string)($idx['title']    ?? '');
$subtitle  = (string)($idx['subtitle'] ?? '');
$eyebrow   = $isSeries ? 'Series' : humanize_slug((string)($idx['slug'] ?? ''));

$count = 0;
foreach ($sections as $s) $count += count($s['_cards'] ?? []);

$grid_rows_for = static function (array $sec): int {
    $r = (string)($sec['grid_rows'] ?? 'all');
    return ctype_digit($r) ? max(1, (int)$r) : 0;
};
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
      </div>
    </header>
  <?php endif; ?>

  <?php if ($sections === []): ?>
    <p class="index-empty">Nothing here yet.</p>
  <?php endif; ?>

  <?php foreach ($sections as $sec):
      $stype   = (string)$sec['section_type'];
      $stitle  = (string)($sec['title'] ?? '');
      $hstyle  = (string)($sec['header_style'] ?? 'small');
      $cards   = $sec['_cards'] ?? [];
      $count   = count($cards);
      $format  = (string)($sec['display_format'] ?? 'grid');
      $seeLab  = (string)($sec['see_more_label']  ?? '');
      $seeTgt  = (string)($sec['see_more_target'] ?? '');
      $viewLab = $seeLab !== '' ? $seeLab : 'View all';
      if ($cards === [] && $stype !== 'feed') continue; // empty hero / curated → skip silently
  ?>
    <section class="index-section index-section--<?= $e($stype) ?>">
      <?php if ($stype !== 'hero' && $stitle !== ''): ?>
        <?php $viewHref = $seeTgt !== '' ? $seeTgt : '#'; ?>
        <?php if ($hstyle === 'big'): ?>
          <header class="index-section-header is-big">
            <h2 class="index-section-title-big"><?= render_title_emphasis($stitle) ?></h2>
            <a class="index-section-view-all" href="<?= $e($viewHref) ?>"><?= $e($viewLab) ?> →</a>
          </header>
        <?php else: ?>
          <div class="group-header">
            <span class="group-header-eyebrow"><?= $e($stitle) ?></span>
            <a class="group-header-link" href="<?= $e($viewHref) ?>"><?= $e($viewLab) ?> →</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <?php
      // Visitor pills for feed sections — render bare under the title,
      // no .controller wrapper / "Type" label.
      $pills = $sec['_pills'] ?? null;
      if ($pills && !empty($pills['show']) && $pills['pills'] !== []):
          $mode = (string)$pills['by'];
      ?>
        <div class="index-section-pills" data-pill-mode="<?= $e($mode) ?>">
          <button type="button" class="fp on" data-cat="all">All</button>
          <?php foreach ($pills['pills'] as $p): ?>
            <button type="button" class="fp" data-cat="<?= $e((string)$p['key']) ?>"><?= $e((string)$p['label']) ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($stype === 'hero' && $cards !== []):
          $hcard      = $cards[0];
          $hType      = (string)($hcard['type']  ?? 'article');
          $hSlug      = (string)($hcard['slug']  ?? '');
          $hTitle     = (string)($hcard['title'] ?? '');
          $hSummary   = (string)($hcard['summary'] ?? '');
          $hPub       = (string)($hcard['published_at'] ?? '');
          $hRead      = $hcard['read_time'] ?? null;
          $hCatLabel  = (string)($hcard['category_label'] ?? '');
          $hCatColour = (string)($hcard['category_colour'] ?? '');
          $hSeriesId  = (int)($hcard['series_id'] ?? 0);
          $hSeriesNm  = (string)($hcard['series_name'] ?? '');
          $hSeriesOrd = (int)($hcard['series_order'] ?? 0);
          $hSeriesTot = $hSeriesId > 0 ? count_series_published($hSeriesId) : 0;
          $hLayout    = (string)($sec['hero_layout']     ?? 'within');
          $hBg        = (string)($sec['hero_background'] ?? 'transparent');
          $hBlur      = (int)($sec['hero_blur'] ?? 0);
          $hImgMode   = (string)($sec['hero_image_mode'] ?? 'auto');
          $hImgCustom = (string)($sec['hero_image_url']  ?? '');
          // Resolve the image — used as side panel (within) or full bg (bleed).
          $hImage = '';
          if ($hImgMode === 'custom') {
              $hImage = $hImgCustom;
          } elseif ($hImgMode === 'auto') {
              $hImage = (string)($hcard['hero_image'] ?? '');
          }
          // Eyebrow format: "— {section title} — {category}"
          // The section title sits between two em-dashes; the category
          // (if present) follows after the trailing dash with a single
          // space, no extra separator.
          $eyebrowParts = [];
          if ($stitle !== '')    $eyebrowParts[] = '— ' . $stitle . ' —';
          if ($hCatLabel !== '') $eyebrowParts[] = $hCatLabel;
          $eyebrowText = $eyebrowParts !== [] ? strtoupper(implode(' ', $eyebrowParts)) : '';
          $hUrlBase = [
              'article'      => '/writing/',
              'journal'      => '/journal/',
              'live-session' => '/live-sessions/',
              'experiment'   => '/experiments/',
          ][$hType] ?? '/writing/';
          $hUrl   = $hSlug !== '' ? $hUrlBase . $hSlug : '#';
          $hDate  = $hPub !== '' ? strtoupper(date('M j, Y', strtotime($hPub))) : '';
          $hMeta  = trim($hDate . ($hRead ? ' · ' . (int)$hRead . ' MIN READ' : ''));
          $isBleed = $hLayout === 'bleed-dark' || $hLayout === 'bleed-light';
          $isWithin = $hLayout === 'within';
          $hHasSeriesCard = $isWithin && $hImage === '' && $hImgMode !== 'none' && $hSeriesNm !== '';
          $hHasSide       = $isWithin && ($hImage !== '' || $hHasSeriesCard);
          // Helper: build inline style for bleed bg image.
          $bleedStyle = $isBleed && $hImage !== ''
              ? 'background-image:url(\'' . $e($hImage) . '\')'
              : '';
      ?>
      <?php if ($isBleed): ?>
        <div class="editorial-hero editorial-hero--<?= $e($hLayout) ?><?= $hBlur ? ' editorial-hero--blur' : '' ?>" <?= $bleedStyle !== '' ? 'style="' . $bleedStyle . '"' : '' ?>>
          <div class="editorial-hero-text">
            <?php if ($eyebrowText !== ''): ?>
              <div class="editorial-hero-eyebrow"<?= $hCatColour ? ' style="--c-current:var(--c-' . $e($hCatColour) . ')"' : '' ?>><?= $e($eyebrowText) ?></div>
            <?php endif; ?>
            <h1 class="editorial-hero-title"><?= $e($hTitle) ?></h1>
            <?php if ($hSummary !== ''): ?>
              <p class="editorial-hero-summary"><?= $e($hSummary) ?></p>
            <?php endif; ?>
            <div class="editorial-hero-foot">
              <?php if ($hMeta !== ''): ?>
                <span class="editorial-hero-meta"><?= $e($hMeta) ?></span>
              <?php endif; ?>
              <a href="<?= $e($hUrl) ?>" class="editorial-hero-cta">Read &rarr;</a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="editorial-hero editorial-hero--<?= $e($hLayout) ?> editorial-hero--bg-<?= $e($hBg) ?><?= $hHasSide ? '' : ' is-solo' ?>">
          <div class="editorial-hero-text">
            <?php if ($eyebrowText !== ''): ?>
              <div class="editorial-hero-eyebrow"<?= $hCatColour ? ' style="--c-current:var(--c-' . $e($hCatColour) . ')"' : '' ?>>
                <?= $e($eyebrowText) ?>
              </div>
            <?php endif; ?>
            <h1 class="editorial-hero-title"><?= $e($hTitle) ?></h1>
            <?php if ($hSummary !== ''): ?>
              <p class="editorial-hero-summary"><?= $e($hSummary) ?></p>
            <?php endif; ?>
            <div class="editorial-hero-foot">
              <?php if ($hMeta !== ''): ?>
                <span class="editorial-hero-meta"><?= $e($hMeta) ?></span>
              <?php endif; ?>
              <a href="<?= $e($hUrl) ?>" class="editorial-hero-cta">Read &rarr;</a>
            </div>
          </div>
          <?php if ($hHasSide): ?>
            <aside class="editorial-hero-side">
              <?php if ($hImage !== ''): ?>
                <div class="editorial-hero-thumb" style="background-image:url('<?= $e($hImage) ?>')"></div>
              <?php elseif ($hHasSeriesCard): ?>
                <div class="editorial-hero-card">
                  <div class="editorial-hero-card-label">Series</div>
                  <div class="editorial-hero-card-title">
                    <?= $e($hSeriesNm) ?>
                    <?php if ($hSeriesOrd > 0 && $hSeriesTot > 0): ?>
                      <span class="editorial-hero-card-part">&mdash; Part <?= $hSeriesOrd ?> of <?= $hSeriesTot ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endif; ?>
            </aside>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php elseif ($cards !== []): ?>
        <?php
        $gridStyle = '';
        $rowLimit  = $grid_rows_for($sec);
        if ($format === 'grid' && $rowLimit > 0) {
            // Cap rendered cards at rowLimit * 4 (matches the lib's v1 cap).
            $cards = array_slice($cards, 0, $rowLimit * 4);
        }
        ?>
        <div class="cards-grid<?= $format === 'carousel' ? ' is-carousel' : '' ?>"<?= $gridStyle ?>>
          <?php foreach ($cards as $card): ?>
            <?php require __DIR__ . '/partials/index-card.php'; ?>
          <?php endforeach; ?>
        </div>

      <?php else: /* empty feed */ ?>
        <p class="index-empty">No matching items.</p>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>

  <?php
  // Per-section visitor pills get one shared script — same OR-semantics
  // as the listing template, scoped to each .index-section.
  $hasPills = false;
  foreach ($sections as $s) {
      if (($s['_pills']['show'] ?? false) && ($s['_pills']['pills'] ?? []) !== []) { $hasPills = true; break; }
  }
  if ($hasPills):
  ?>
  <script>
  (function () {
    'use strict';
    document.querySelectorAll('.index-section .index-section-pills').forEach(function (ctrl) {
      var section = ctrl.closest('.index-section');
      if (!section) return;
      var attr  = ctrl.getAttribute('data-pill-mode') === 'types' ? 'type' : 'category';
      var pills = Array.prototype.slice.call(ctrl.querySelectorAll('.fp'));
      var cards = Array.prototype.slice.call(section.querySelectorAll('.cards-grid > .card'));
      function activeKeys() {
        return pills.filter(function (p) { return p.classList.contains('on') && p.getAttribute('data-cat') !== 'all'; })
                    .map(function (p) { return p.getAttribute('data-cat'); });
      }
      function apply() {
        var keys = activeKeys();
        var allOn = pills.some(function (p) { return p.getAttribute('data-cat') === 'all' && p.classList.contains('on'); });
        cards.forEach(function (c) {
          var v = c.getAttribute('data-' + attr) || '';
          var show = allOn || (keys.length > 0 && keys.indexOf(v) !== -1);
          c.classList.toggle('is-filtered-out', !show);
        });
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
    });
  })();
  </script>
  <?php endif; ?>

</div>
