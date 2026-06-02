<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-series.php — series pill + progress in the topstrip right slot.
 * Optional on article-standard; required on article-series (Phase 8+).
 *
 * Renders iff $ctx['series'] is a resolved row. The pill links to the
 * series index page at /series/<slug>/. The "Part N of M" line and the
 * progress dots use published-only position (`_position`) and count
 * (`_count`) computed in lib/render.php — never the raw series_order on
 * the content row, which would include drafts and produce off-by-N's.
 */
$series = $ctx['series'] ?? null;
if (!is_array($series)) return;
$name  = (string)($series['name'] ?? '');
$slug  = (string)($series['slug'] ?? '');
$order = (int)($series['_position'] ?? 0);
$total = (int)($series['_count']    ?? 0);
if ($name === '') return;

$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$pillContent = $e($name);
?>
<?php if ($slug !== ''): ?>
  <a class="article-series-pill" data-block="series" href="/series/<?= $e($slug) ?>/"><?= $pillContent ?></a>
<?php else: ?>
  <span class="article-series-pill" data-block="series"><?= $pillContent ?></span>
<?php endif; ?>
<?php if ($order > 0 && $total > 0): ?>
  <span class="article-series-progress">Part <?= $order ?> of <?= $total ?></span>
  <span class="article-series-dots" aria-hidden="true">
    <?php for ($i = 1; $i <= $total; $i++): ?>
      <span class="article-series-dot<?= $i === $order ? ' is-active' : '' ?>"></span>
    <?php endfor; ?>
  </span>
<?php endif; ?>
