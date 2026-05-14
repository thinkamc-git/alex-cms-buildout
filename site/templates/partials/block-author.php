<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-author.php — byline (image + name + tagline) inside the byline-row.
 * Honours per-content `show_author` flag (default TRUE). When fields are
 * empty, the placeholders from author_display() render verbatim — that's
 * intentional editor-aid behaviour per CMS-STRUCTURE.md §11.
 *
 * The image slot prefers an uploaded image; otherwise the initials circle;
 * otherwise an empty circle (the CSS gives it a neutral background).
 */
if (!($ctx['article']['show_author'] ?? true)) return;
$a = $ctx['author'] ?? [];
$image    = $a['image'] ?? null;
$name     = (string)($a['name'] ?? '');
$short    = (string)($a['short_description'] ?? '');
$initials = (string)($a['initials'] ?? '');
?>
<div class="article-author" data-block="author">
  <div class="article-author-avatar">
    <?php if ($image !== null && $image !== ''): ?>
      <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
    <?php else: ?>
      <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
    <?php endif; ?>
  </div>
  <div class="article-author-info">
    <span class="article-author-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></span>
    <span class="article-author-separator"> – </span>
    <span class="article-author-tagline"><?= htmlspecialchars($short, ENT_QUOTES, 'UTF-8') ?></span>
  </div>
</div>
