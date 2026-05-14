<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-special-tag.php — Principle / Framework pill on its own row.
 * Articles only. Path A: render iff one of the two enum values is set.
 * The label is the value with the first letter capitalised.
 */
$tag = (string)($ctx['article']['special_tag'] ?? '');
if ($tag !== 'principle' && $tag !== 'framework') return;
$label = ucfirst($tag);
?>
<div class="article-tag-row">
  <span class="article-special-tag" data-block="special-tag"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
</div>
