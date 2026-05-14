<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-updated-date.php — auto-conditional. Renders only when the
 * `updated_at` value is more than 24 hours after `published_at`. Smaller
 * gaps suggest the same editorial pass and aren't worth a date pill.
 */
$pub = (string)($ctx['article']['published_at'] ?? '');
$upd = (string)($ctx['article']['updated_at'] ?? '');
if ($pub === '' || $upd === '') return;
$pubTs = strtotime($pub);
$updTs = strtotime($upd);
if ($pubTs === false || $updTs === false) return;
if (($updTs - $pubTs) <= 86400) return;
?>
<span class="article-updated-date" data-block="updated-date">Updated <?= htmlspecialchars(date('M j, Y', $updTs), ENT_QUOTES, 'UTF-8') ?></span>
