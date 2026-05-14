<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-series.php — series pill + progress in the topstrip right slot.
 * Optional on article-standard; required on article-series (Phase 8+).
 * Path A: render iff $ctx['series'] is a resolved row. Series-row count
 * (for the "Part N of M" line) lands when the series table grows real
 * data — for now we render the pill + order without the total.
 */
$series = $ctx['series'] ?? null;
if (!is_array($series)) return;
$name  = (string)($series['name'] ?? '');
$order = (int)($ctx['article']['series_order'] ?? 0);
$total = (int)($series['_count'] ?? 0);
if ($name === '') return;
?>
<span class="article-series-pill" data-block="series"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
<?php if ($order > 0 && $total > 0): ?>
  <span class="article-series-progress">Part <?= $order ?> of <?= $total ?></span>
  <span class="article-series-dots" aria-hidden="true">
    <?php for ($i = 1; $i <= $total; $i++): ?>
      <span class="article-series-dot<?= $i === $order ? ' is-active' : '' ?>"></span>
    <?php endfor; ?>
  </span>
<?php endif; ?>
