<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-entry-number.php — Journal "Entry N" in the topstrip (right slot).
 * Auto-conditional per BLOCKS.md: renders only when journal_number is set.
 * Phase 8 assigns it on first publish (assign_journal_number in lib/content.php).
 */
$n = $ctx['article']['journal_number'] ?? null;
if ($n === null) return;
$padded = str_pad((string)(int)$n, 3, '0', STR_PAD_LEFT);
?>
<span class="article-entry-number" data-block="entry-number">Entry <?= htmlspecialchars($padded, ENT_QUOTES, 'UTF-8') ?></span>
