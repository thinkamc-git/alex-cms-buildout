<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-tags.php — tags pills inside the footer. Auto-conditional:
 * renders only if any tags exist. `tags` is comma-separated text in the
 * content row; we split, trim, and dedupe before emitting.
 */
$raw = (string)($ctx['article']['tags'] ?? '');
if (trim($raw) === '') return;
$parts = array_values(array_unique(array_filter(array_map(
    static fn($s) => trim($s),
    explode(',', $raw)
), static fn($s) => $s !== '')));
if (count($parts) === 0) return;
?>
<div class="article-tags-list" data-block="tags">
  <?php foreach ($parts as $tag): ?>
    <span class="article-tag"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
  <?php endforeach; ?>
</div>
