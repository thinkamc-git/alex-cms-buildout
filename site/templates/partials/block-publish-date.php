<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-publish-date.php — formatted publish date in the date row.
 * Format: "Mar 4, 2026" (Jun 12, 2026 etc.). Always present for a
 * published article because render_content() gates on status='published'.
 */
$ts = (string)($ctx['article']['published_at'] ?? '');
if ($ts === '') return;
$dt = strtotime($ts);
if ($dt === false) return;
?>
<span class="article-pub-date" data-block="publish-date"><?= htmlspecialchars(date('M j, Y', $dt), ENT_QUOTES, 'UTF-8') ?></span>
