<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-key-statement.php — Journal Key Statement (replaces Title).
 * Renders the single declarative sentence in Instrument Serif italic with
 * a left rule in the category colour. CSS handles the typography via
 * .article-key-statement; the left-rule colour is set via data-category
 * on the parent <article>.
 */
$ks = (string)($ctx['article']['key_statement'] ?? '');
if ($ks === '') return;
?>
<p class="article-key-statement" data-block="key-statement"><?= htmlspecialchars($ks, ENT_QUOTES, 'UTF-8') ?></p>
