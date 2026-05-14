<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-summary.php — Single-line deck below the title. Optional.
 * Path A: render iff non-empty.
 */
$summary = trim((string)($ctx['article']['summary'] ?? ''));
if ($summary === '') return;
?>
<p class="article-summary" data-block="summary"><?= htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') ?></p>
