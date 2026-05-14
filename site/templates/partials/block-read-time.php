<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-read-time.php — right slot of the byline-row.
 * Optional. Format: "14 min read". Path A: render iff a positive integer.
 */
$rt = (int)($ctx['article']['read_time'] ?? 0);
if ($rt <= 0) return;
?>
<span class="article-read-time" data-block="read-time"><?= $rt ?> min read</span>
