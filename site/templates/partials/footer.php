<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * partials/footer.php — public-site footer (CMS-rendered pages).
 *
 * Phase 20.x: on staging, footer links source from the `nav_items` table
 * via render_nav('footer') — same path marketing pages take. Prod stays
 * on the frozen hardcoded list until the Phase 29 cutover.
 */

$_is_staging = defined('APP_ENV') && APP_ENV === 'staging';
?>
<footer class="layout-footer">
  <span class="layout-footer-left">© <?= date('Y') ?> alex m. chong</span>
  <div class="layout-footer-right">
<?php if ($_is_staging):
    if (!function_exists('render_nav')) {
        require_once __DIR__ . '/../../lib/nav.php';
    }
    render_nav('footer');
else: ?>
    <a href="/newsletter">Newsletter</a>
    <a href="/about">About</a>
    <a href="/work-with-me">Work with me</a>
<?php endif; ?>
  </div>
</footer>
