<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-hero-image.php — between the byline-row and the body.
 * Path A: render iff `hero_image` is set. `hero_size` ENUM drives the
 * data-size attribute the CSS uses for default/wide/full layouts.
 * Caption is optional; rendered as a separate <figcaption> when present.
 */
$src = trim((string)($ctx['article']['hero_image'] ?? ''));
if ($src === '') return;
$caption = trim((string)($ctx['article']['hero_caption'] ?? ''));
$size = (string)($ctx['article']['hero_size'] ?? 'default');
if (!in_array($size, ['default','wide','full'], true)) $size = 'default';
?>
<figure class="article-hero" data-size="<?= htmlspecialchars($size, ENT_QUOTES, 'UTF-8') ?>" data-block="hero-image">
  <img src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($caption !== ''): ?>
    <figcaption><?= htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') ?></figcaption>
  <?php endif; ?>
</figure>
