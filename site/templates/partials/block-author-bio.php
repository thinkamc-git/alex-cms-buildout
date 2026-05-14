<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-author-bio.php — dark footer panel: image + extended description.
 * Independently toggleable from the byline via `show_author_bio` (default
 * TRUE). Empty extended_description renders the {no extended description}
 * placeholder per CMS-STRUCTURE.md §11.
 */
if (!($ctx['article']['show_author_bio'] ?? true)) return;
$a = $ctx['author'] ?? [];
$image    = $a['image'] ?? null;
$name     = (string)($a['name'] ?? '');
$ext      = (string)($a['extended_description'] ?? '');
$initials = (string)($a['initials'] ?? '');
?>
<aside class="article-author-bio" data-block="author-bio">
  <div class="article-author-bio-avatar">
    <?php if ($image !== null && $image !== ''): ?>
      <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
    <?php else: ?>
      <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
    <?php endif; ?>
  </div>
  <p class="article-author-bio-extended"><?= htmlspecialchars($ext, ENT_QUOTES, 'UTF-8') ?></p>
</aside>
