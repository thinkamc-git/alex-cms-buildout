<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * partials/index-card.php — a single content card on an index page.
 *
 * Inputs (set immediately before include):
 *   $card    — content row from list_index_feed() / get_index_content_card()
 *   $catMap  — optional cache (passed by enclosing template) for per-card
 *              primary-category lookups. Pass [] to skip categories.
 *
 * Markup mirrors the canonical reference in site/_design-system/index.html
 * §"Content Cards" (lines 1220–1822). No inline font-size overrides —
 * the DS .card-* classes own all sizing. Inline styles are only used
 * where the reference itself uses them (margin-bottom on titles, etc.).
 *
 * Variants:
 *   article      — .card--article
 *   journal      — .card--journal
 *   live-session — .card--event
 *   experiment   — .card--experiment (dark variant)
 */

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$type    = (string)($card['type']  ?? '');
$slug    = (string)($card['slug']  ?? '');
$title   = (string)($card['title'] ?? '');
$summary = (string)($card['summary'] ?? '');

$prefixes = [
    'article'      => '/writing/',
    'journal'      => '/journal/',
    'live-session' => '/live-sessions/',
    'experiment'   => '/experiments/',
];
$href = isset($prefixes[$type]) ? $prefixes[$type] . $slug : '#';

// ── Primary category lookup (cached per-card in $catMap) ──────────────
$catSlug   = '';
$catLabel  = '';
$catColour = '';
if ($type !== '' && isset($card['id'])) {
    $cid = (int)$card['id'];
    if (!isset($catMap['_id_' . $cid])) {
        $cstmt = db()->prepare(
            "SELECT c.value_slug, c.label, c.colour
               FROM content_categories cc
               JOIN categories c ON c.type = cc.type AND c.value_slug = cc.category
              WHERE cc.content_id = :id
           ORDER BY cc.is_primary DESC, cc.id ASC
              LIMIT 1"
        );
        $cstmt->execute([':id' => $cid]);
        $crow = $cstmt->fetch();
        $catMap['_id_' . $cid] = $crow === false ? null : $crow;
    }
    $cat = $catMap['_id_' . $cid];
    if (is_array($cat)) {
        $catSlug   = (string)($cat['value_slug'] ?? '');
        $catLabel  = (string)($cat['label']     ?? '');
        $catColour = (string)($cat['colour']    ?? '');
    }
}

// Bookmark SVG used in every non-experiment card header (the .bm icon).
$bookmarkSvg = '<div class="bm"><svg viewBox="0 0 24 24"><path d="M6 4h12a1 1 0 0 1 1 1v15l-7-4-7 4V5a1 1 0 0 1 1-1z"/></svg></div>';

switch ($type):

  /* ════════ ARTICLE ════════════════════════════════════════════════════ */
  case 'article':
    $published    = (string)($card['published_at'] ?? '');
    $pubShort     = $published !== '' ? date('M j, Y', strtotime($published)) : '';
    $readTime     = (int)($card['read_time'] ?? 0);
    $seriesName   = (string)($card['series_name']  ?? '');
    $seriesSlug   = (string)($card['series_slug']  ?? '');
    $seriesPart   = (int)($card['series_order']    ?? 0);
    $seriesTotal  = 0;
    if ($seriesSlug !== '') {
        // Cache per series-slug so a page of cards in the same series only
        // hits the DB once for the total.
        if (!isset($catMap['_series_' . $seriesSlug])) {
            $st = db()->prepare(
                "SELECT COUNT(*) FROM content c
                  WHERE c.series_id = (SELECT id FROM series WHERE slug = :s)
                    AND c.status = 'published'
                    AND (c.published_status IS NULL OR c.published_status = 'live')"
            );
            $st->execute([':s' => $seriesSlug]);
            $catMap['_series_' . $seriesSlug] = (int)$st->fetchColumn();
        }
        $seriesTotal = (int)$catMap['_series_' . $seriesSlug];
    }
    $special = (string)($card['special_tag'] ?? '');
    $tagsRaw = (string)($card['tags'] ?? '');
    $tags    = $tagsRaw !== '' ? array_filter(array_map('trim', explode(',', $tagsRaw))) : [];
  ?>
  <a class="card card--article" data-type="article" data-category="<?= $e($catSlug) ?>" href="<?= $e($href) ?>">
    <div class="card-header">
      <span class="cat"><?= $e($catLabel !== '' ? $catLabel : 'Article') ?></span>
      <?= $bookmarkSvg ?>
    </div>
    <div class="card-body">
      <h2 class="title" style="margin-bottom: var(--space-12)"><?= $e($title !== '' ? $title : '(untitled)') ?></h2>
      <?php if ($seriesName !== ''): ?>
        <div class="series-row">
          <span class="series-pill"><?= $e($seriesName) ?></span>
          <?php if ($seriesPart > 0 && $seriesTotal > 0): ?>
            <span class="series-pos">Part <?= $seriesPart ?> of <?= $seriesTotal ?></span>
            <div class="series-dots">
              <?php for ($i = 1; $i <= $seriesTotal; $i++): ?>
                <div class="sdot<?= $i === $seriesPart ? ' on' : '' ?>"></div>
              <?php endfor; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($special !== ''): ?>
        <span class="fw-badge"><?= $e(ucfirst($special)) ?></span>
      <?php endif; ?>
      <?php if ($summary !== ''): ?>
        <p class="excerpt"><?= $e($summary) ?></p>
      <?php endif; ?>
      <div class="push-divider"></div>
      <?php if ($tags !== []): ?>
        <div class="row-between">
          <div class="tag-row">
            <?php foreach (array_slice($tags, 0, 3) as $t): ?>
              <span class="tag"><?= $e($t) ?></span>
            <?php endforeach; ?>
          </div>
          <span class="arrow">→</span>
        </div>
      <?php endif; ?>
    </div>
    <div class="meta-strip">
      <span><?= $e($pubShort) ?></span>
      <?php if ($readTime > 0): ?><span><?= $readTime ?> min read</span><?php endif; ?>
    </div>
  </a>
  <?php break;

  /* ════════ JOURNAL ═══════════════════════════════════════════════════ */
  case 'journal':
    $published     = (string)($card['published_at'] ?? '');
    $pubShort      = $published !== '' ? date('M j, Y', strtotime($published)) : '';
    $journalNumber = (int)($card['journal_number'] ?? 0);

    // Per-category glyph (matches the DS journal-card reference exactly).
    // Falls back to a generic open-book mark when the category has no
    // bespoke glyph.
    $journalIcon = static function (string $slug, string $colourToken) use ($e): string {
        $colour = $colourToken !== '' ? ('var(--c-' . $e($colourToken) . ')') : 'var(--muted)';
        $stroke = ' stroke="currentColor" stroke-width="1.4" stroke-linecap="round" fill="none"';
        switch ($slug) {
            case 'introspection':
                return '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="color:' . $colour . ';flex-shrink:0">'
                     . '<path d="M5 1 Q2 7 5 13"' . $stroke . '/>'
                     . '<path d="M9 1 Q12 7 9 13"' . $stroke . '/>'
                     . '</svg>';
            case 'contemplation':
                return '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="color:' . $colour . ';flex-shrink:0">'
                     . '<circle cx="7" cy="7" r="2" stroke="currentColor" stroke-width="1.4"/>'
                     . '<path d="M7 2 A5 5 0 0 1 12 7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" fill="none"/>'
                     . '<path d="M7 12 A5 5 0 0 1 2 7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" fill="none"/>'
                     . '</svg>';
            case 'insight':
                return '<svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="color:' . $colour . ';flex-shrink:0">'
                     . '<circle cx="7" cy="7" r="2.2" stroke="currentColor" stroke-width="1.4"/>'
                     . '<line x1="7" y1="1.1" x2="7" y2="2.9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>'
                     . '<line x1="7" y1="11.1" x2="7" y2="12.9" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>'
                     . '<line x1="1.1" y1="7" x2="2.9" y2="7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>'
                     . '<line x1="11.1" y1="7" x2="12.9" y2="7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>'
                     . '</svg>';
        }
        return '';
    };
  ?>
  <a class="card card--journal" data-type="journal" data-category="<?= $e($catSlug) ?>" href="<?= $e($href) ?>">
    <div class="card-header">
      <div style="display:flex;align-items:center;gap: 7px">
        <?= $journalIcon($catSlug, $catColour) ?>
        <span class="cat"><?= $e($catLabel !== '' ? $catLabel : 'Journal') ?><?php if ($journalNumber > 0): ?><span class="j-num"> <?= str_pad((string)$journalNumber, 3, '0', STR_PAD_LEFT) ?></span><?php endif; ?></span>
      </div>
      <?= $bookmarkSvg ?>
    </div>
    <div class="card-body">
      <?php if ($title !== ''): ?>
        <p class="j-ruled"><?= $e($title) ?></p>
      <?php endif; ?>
      <?php if ($summary !== ''): ?>
        <p class="excerpt" style="margin-bottom: var(--space-16);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden"><?= $e($summary) ?></p>
      <?php endif; ?>
      <div class="j-footer-push"></div>
      <div style="height:1px;background:var(--ink-18);margin-bottom: 18px"></div>
      <div class="row-between">
        <span class="j-date"><?= $e($pubShort) ?></span>
        <span class="arrow">→</span>
      </div>
    </div>
  </a>
  <?php break;

  /* ════════ LIVE SESSION ══════════════════════════════════════════════ */
  case 'live-session':
    $eventDateRaw = (string)($card['event_date']     ?? '');
    $eventTimeRaw = (string)($card['event_time']     ?? '');
    $eventEndRaw  = (string)($card['event_end_time'] ?? '');
    $eventDate    = $eventDateRaw !== '' ? date('M j, Y', strtotime($eventDateRaw)) : '';

    // Day + time line. "Wed · 7:00 PM ET" if just a start time; "Wed ·
    // 10:00 AM – 1:00 PM" if both start and end are given.
    $dayPrefix  = $eventDateRaw !== '' ? date('D', strtotime($eventDateRaw)) : '';
    $timeStart  = $eventTimeRaw !== '' ? date('g:i A', strtotime($eventDateRaw . ' ' . $eventTimeRaw)) : '';
    $timeEnd    = $eventEndRaw  !== '' && $eventDateRaw !== '' ? date('g:i A', strtotime($eventDateRaw . ' ' . $eventEndRaw)) : '';
    $tz         = 'ET'; // Site is Toronto-default; revisit when multi-tz lands.
    if ($timeEnd !== '') {
        $eventTime = $timeStart . ' – ' . $timeEnd;
    } elseif ($timeStart !== '') {
        $eventTime = $timeStart . ' ' . $tz;
    } else {
        $eventTime = '';
    }
    $dayTime = trim($dayPrefix . ($eventTime !== '' ? ' · ' . $eventTime : ''), ' ·');

    // Countdown / past pill. Compares against event_date at 23:59 so the
    // pill stays "Today!" for the full event day.
    $tsForCompare = $eventDateRaw !== ''
        ? strtotime($eventDateRaw . ' ' . ($eventTimeRaw !== '' ? $eventTimeRaw : '23:59:59'))
        : 0;
    $now = time();
    $isPast    = $tsForCompare > 0 && $tsForCompare < $now;
    $countdown = ['label' => '', 'class' => ''];
    if ($tsForCompare > 0 && !$isPast) {
        $startOfToday = strtotime(date('Y-m-d 00:00:00', $now));
        $diffDays = (int)floor((strtotime($eventDateRaw . ' 00:00:00') - $startOfToday) / 86400);
        if ($diffDays <= 0)        $countdown = ['label' => 'Today!',                 'class' => ' today'];
        elseif ($diffDays === 1)   $countdown = ['label' => 'Tomorrow',               'class' => ' tomorrow'];
        elseif ($diffDays < 7)     $countdown = ['label' => 'In ' . $diffDays . ' Days', 'class' => ''];
        elseif ($diffDays < 14)    $countdown = ['label' => 'Next Week',              'class' => ''];
        elseif ($diffDays < 60)    $countdown = ['label' => 'In ' . (int)ceil($diffDays / 7) . ' Weeks', 'class' => ''];
        else                       $countdown = ['label' => 'In ' . (int)ceil($diffDays / 30) . ' Months', 'class' => ''];
    }

    $cost       = (string)($card['cost_pill']  ?? '');
    $attendance = (string)($card['attendance'] ?? '');
    $locCity    = (string)($card['location']   ?? '');
    $locVenue   = (string)($card['venue']      ?? '');
    // Ghost backdrop = first chunk of city (before comma). For purely
    // remote sessions we show the literal word "Remote" as the ghost so
    // the visual treatment matches the DS reference.
    $locGhost   = $locCity !== '' ? trim(explode(',', $locCity)[0]) : '';

    $isRemote      = $attendance === 'remote';
    $isMasterclass = $catSlug === 'masterclass';

    // Masterclass card uses a fundamentally different body layout
    // (mc-body / mc-logistics / mc-cta-zone) — no location strip,
    // dark CTA footer. See site/_design-system/index.html §"Event Cards"
    // for the canonical markup.
    if ($isMasterclass) {
      // mc-logistics rows: 1) date+time, 2) format+venue.
      $row1 = trim($eventDate . ($dayTime !== '' ? ' · ' . $dayTime : ''));
      $formatLabel = '';
      if ($attendance === 'in-person') $formatLabel = 'In-Person';
      elseif ($attendance === 'remote') $formatLabel = 'Virtual';
      $row2Parts = [];
      if ($formatLabel !== '') $row2Parts[] = $formatLabel;
      if ($locCity   !== '')   $row2Parts[] = $locCity;
      if ($locVenue  !== '')   $row2Parts[] = $locVenue;
      $row2 = implode(' · ', $row2Parts);
      $ctaLabel = $isPast ? 'View Details' : 'Register Now';
      $ctaSub   = (string)($card['custom_pill'] ?? '');
    ?>
    <a class="card card--event<?= $isPast ? ' past' : '' ?>" data-type="event" data-category="<?= $e($catSlug) ?>" href="<?= $e($href) ?>">
      <div class="card-header">
        <span class="<?= $isPast ? 'ev-type-past' : 'ev-type' ?>"><?= $e($catLabel !== '' ? $catLabel : 'Masterclass') ?></span>
        <?php if ($isPast): ?>
          <span class="ev-past-label">Past</span>
        <?php elseif ($countdown['label'] !== ''): ?>
          <span class="ev-countdown-label<?= $countdown['class'] ?>"><?= $e($countdown['label']) ?></span>
        <?php endif; ?>
      </div>
      <div class="mc-body">
        <?php if ($title !== ''): ?>
          <h2 class="<?= $isPast ? 'ev-title-past' : 'ev-title' ?>" style="margin-bottom: var(--space-12)"><?= $e($title) ?></h2>
        <?php endif; ?>
        <?php if ($summary !== ''): ?>
          <p class="ev-desc"><?= $e($summary) ?></p>
        <?php endif; ?>
        <?php if ($row1 !== '' || $row2 !== ''): ?>
          <div class="mc-logistics">
            <?php if ($row1 !== ''): ?>
              <div class="mc-logistic"><span class="mc-logistic-icon">◷</span><span><?= $e($row1) ?></span></div>
            <?php endif; ?>
            <?php if ($row2 !== ''): ?>
              <div class="mc-logistic"><span class="mc-logistic-icon">○</span><span><?= $e($row2) ?></span></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="mc-cta-zone">
        <div class="mc-cta-left">
          <span class="mc-cta-label"><?= $e($ctaLabel) ?></span>
          <?php if ($ctaSub !== ''): ?>
            <span class="mc-cta-sub"><?= $e($ctaSub) ?></span>
          <?php endif; ?>
        </div>
        <div class="mc-cta-right">
          <?php if ($cost !== ''): ?>
            <span class="mc-price"><?= $e($cost) ?></span>
          <?php endif; ?>
          <span class="mc-arrow">→</span>
        </div>
      </div>
    </a>
    <?php break;
    }
  ?>
  <a class="card card--event<?= $isPast ? ' past' : '' ?>" data-type="event" data-category="<?= $e($catSlug) ?>" href="<?= $e($href) ?>">
    <div class="card-header">
      <span class="<?= $isPast ? 'ev-type-past' : 'ev-type' ?>"><?= $e($catLabel !== '' ? $catLabel : 'Talk') ?></span>
      <?php if ($isPast): ?>
        <span class="ev-past-label">Past</span>
      <?php elseif ($countdown['label'] !== ''): ?>
        <span class="ev-countdown-label<?= $countdown['class'] ?>"><?= $e($countdown['label']) ?></span>
      <?php endif; ?>
    </div>

    <?php if ($isRemote): ?>
      <div class="remote-zone">
        <div class="remote-ghost">Remote</div>
        <div style="position:relative">
          <div class="remote-label"><?= $e($locCity !== '' ? $locCity : 'Remote') ?></div>
          <div class="remote-sub"><?= $e($locVenue !== '' ? $locVenue : 'Join from anywhere') ?></div>
        </div>
      </div>
    <?php elseif ($locCity !== ''): ?>
      <div class="loc-zone">
        <?php if ($locGhost !== ''): ?>
          <div class="loc-ghost"><?= $e($locGhost) ?></div>
        <?php endif; ?>
        <div style="position:relative">
          <div class="loc-city"><?= $e($locCity) ?></div>
          <?php if ($locVenue !== ''): ?>
            <div class="loc-venue"><?= $e($locVenue) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="ev-body">
      <?php if ($eventDate !== ''): ?>
        <div class="ev-date-row">
          <span class="<?= $isPast ? 'ev-date-past' : 'ev-date' ?>"><?= $e($eventDate) ?></span>
          <?php if ($dayTime !== ''): ?><span class="ev-time"><?= $e($dayTime) ?></span><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($title !== ''): ?>
        <h2 class="<?= $isPast ? 'ev-title-past' : 'ev-title' ?>" style="margin-bottom: var(--space-12)"><?= $e($title) ?></h2>
      <?php endif; ?>
      <?php if ($summary !== ''): ?>
        <p class="ev-desc"><?= $e($summary) ?></p>
      <?php endif; ?>
      <div class="push-divider"></div>
      <div class="ev-footer">
        <div class="fmt-row">
          <?php if ($attendance !== ''): ?>
            <span class="fmt <?= $attendance === 'in-person' ? 'fmt-inperson' : 'fmt-remote' ?>"><?= $e($attendance === 'in-person' ? 'In-Person' : 'Remote') ?></span>
          <?php endif; ?>
          <?php if ($cost !== ''): ?>
            <span class="fmt <?= strtolower($cost) === 'free' ? 'fmt-free' : 'fmt-paid' ?>"><?= $e($cost) ?></span>
          <?php endif; ?>
        </div>
        <span class="arrow">→</span>
      </div>
    </div>
  </a>
  <?php break;

  /* ════════ EXPERIMENT ════════════════════════════════════════════════ */
  case 'experiment':
    $published = (string)($card['published_at'] ?? '');
    $pubShort  = $published !== '' ? date('M Y', strtotime($published)) : '';
    $scrim     = $catSlug === 'concept' ? 'scrim-concept' : 'scrim-proto';
    $bgToken   = $catSlug !== '' ? ('var(--c-experiment-' . $e($catSlug) . ')') : 'var(--c-experiment-prototype)';
    $tagsRaw   = (string)($card['tags'] ?? '');
    $tags      = $tagsRaw !== '' ? array_filter(array_map('trim', explode(',', $tagsRaw))) : [];
  ?>
  <a class="card card--experiment" data-type="experiment" data-category="<?= $e($catSlug) ?>" href="<?= $e($href) ?>" style="min-height:320px;background:<?= $bgToken ?>;position:relative">
    <div class="<?= $e($scrim) ?>"></div>
    <div class="exp-content">
      <div class="card-header" style="position:relative;z-index:2">
        <span class="od-type"><?= $e($catLabel !== '' ? $catLabel : 'Experiment') ?></span>
      </div>
      <div style="flex:1;min-height:80px"></div>
      <div style="padding: 0 var(--pad) var(--space-16);position:relative;z-index:2">
        <?php if ($title !== ''): ?>
          <h2 class="od-title" style="margin-bottom: 10px"><?= $e($title) ?></h2>
        <?php endif; ?>
        <?php if ($summary !== ''): ?>
          <p class="od-excerpt" style="margin-bottom:14px"><?= $e($summary) ?></p>
        <?php endif; ?>
        <?php if ($tags !== []): ?>
          <div class="tag-row" style="margin-bottom:var(--space-12)">
            <?php foreach (array_slice($tags, 0, 3) as $t): ?>
              <span class="od-tag"><?= $e($t) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($pubShort !== ''): ?>
          <span class="od-meta"><?= $e($pubShort) ?></span>
        <?php endif; ?>
      </div>
      <div class="cta-launch" style="position:relative;z-index:2">
        <span class="label">Open</span>
        <span class="arrow">→</span>
      </div>
    </div>
  </a>
  <?php break;
endswitch;
