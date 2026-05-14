<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-body.php — Tiptap-emitted rich text.
 * Body was already run through sanitize_html() on save (lib/sanitize.php),
 * so emit raw. The .article-prose wrapper picks up all the typographic
 * rules from style-articles.css §5.
 */
$body = (string)($ctx['article']['body'] ?? '');
if (trim($body) === '') return;
?>
<div class="article-prose" data-block="body">
<?= $body ?>
</div>
