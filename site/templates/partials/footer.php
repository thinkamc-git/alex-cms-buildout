<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * partials/footer.php — public-site footer.
 *
 * Matches the .layout-footer treatment in style-articles.css §4 (condensed
 * uppercase, primary text, neutral background, top rule).
 */
$year = date('Y');
?>
<footer class="layout-footer">
  <div class="layout-footer-left">© <?= $year ?> Alex M. Chong</div>
  <div class="layout-footer-right">
    <a href="/newsletter">Newsletter</a>
    <a href="/about">About</a>
    <a href="/work-with-me">Work with me</a>
  </div>
</footer>
