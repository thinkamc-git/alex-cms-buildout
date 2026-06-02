<?php
/**
 * _layout/footer.php — DB-driven marketing footer (Phase 20, staging).
 *
 * Live version for the "footer" partial in the Pages CMS. Wrapping
 * <footer> + copyright label stay in code; the right-hand link list is
 * emitted by render_nav('footer') reading the nav_items table.
 */
if (!function_exists('render_nav')) {
    foreach ([__DIR__ . '/../lib/nav.php', __DIR__ . '/../../lib/nav.php'] as $_p) {
        if (is_file($_p)) { require_once $_p; break; }
    }
}
?>
<footer class="layout-footer">
  <span class="layout-footer-left">© 2026 alex m. chong</span>
  <div class="layout-footer-right">
<?php render_nav('footer'); ?>
  </div>
</footer>
