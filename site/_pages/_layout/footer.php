<?php
/**
 * _layout/footer.php — DB-driven marketing footer.
 *
 * Used on all environments. Wrapping <footer> + copyright label stay in
 * code; the right-hand link list is emitted by render_nav('footer') reading
 * the nav_items table.
 */
if (!function_exists('render_nav')) {
    foreach ([__DIR__ . '/../lib/nav.php', __DIR__ . '/../../lib/nav.php'] as $_p) {
        if (is_file($_p)) { require_once $_p; break; }
    }
}
if (!function_exists('get_setting')) {
    foreach ([__DIR__ . '/../lib/settings.php', __DIR__ . '/../../lib/settings.php'] as $_p) {
        if (is_file($_p)) { require_once $_p; break; }
    }
}
$_footer_copy = function_exists('get_setting') ? get_setting('footer_copyright', 'alex m. chong') : 'alex m. chong';
?>
<footer class="layout-footer">
  <span class="layout-footer-left">© <?= date('Y') ?> <?= htmlspecialchars($_footer_copy, ENT_QUOTES, 'UTF-8') ?></span>
  <div class="layout-footer-right">
<?php render_nav('footer'); ?>
  </div>
</footer>
