<?php
declare(strict_types=1);
if (!defined('TEMPLATE_OK')) { http_response_code(404); exit; }
/**
 * partials/footer.php — public-site footer (CMS-rendered pages).
 *
 * Footer links source from the `nav_items` table via render_nav('footer').
 * Same path marketing pages take. Navigation editor at /cms/navigation
 * is the single source of truth.
 */

if (!function_exists('render_nav')) {
    require_once __DIR__ . '/../../lib/nav.php';
}
?>
<footer class="layout-footer">
  <span class="layout-footer-left">© <?= date('Y') ?> alex m. chong</span>
  <div class="layout-footer-right">
<?php render_nav('footer'); ?>
  </div>
</footer>
