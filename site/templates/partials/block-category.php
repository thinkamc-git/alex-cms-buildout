<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-category.php — Category pill in the topstrip (left slot).
 * Reads $ctx['category']['label'] (joined). Falls through to empty span
 * so the topstrip's flex layout still balances when category is missing.
 */
$label = (string)($ctx['category']['label'] ?? '');
?>
<span class="article-category" data-block="category"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
