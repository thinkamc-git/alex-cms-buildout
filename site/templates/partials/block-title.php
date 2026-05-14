<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * block-title.php — Article title (always-on for articles).
 * The is-serif modifier triggers the Instrument Serif italic display in
 * the editorial layout. Title text is plain-escaped for now; inline HTML
 * support (<em>, <span class="m">) per BLOCKS.md is a later relaxation.
 */
$title = (string)($ctx['article']['title'] ?? '');
?>
<h1 class="article-title is-serif" data-block="title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
