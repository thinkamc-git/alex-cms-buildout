<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/flash.php — single canonical flash-success banner.
 *
 * Inputs (set before include):
 *   $flash       string  Required. Plain text (the partial escapes it).
 *                        If empty, the partial renders nothing.
 *   $flash_extra string  Optional. RAW HTML appended INSIDE the banner
 *                        after the escaped $flash text — used by editors
 *                        that need to surface an inline Undo form alongside
 *                        the success message. Caller escapes contents.
 *
 * Spacing is owned by .flash-success in style-cms.css; callers do NOT
 * pass inline margin styles.
 */

$flash       = (string)($flash ?? '');
$flash_extra = (string)($flash_extra ?? '');

if ($flash === '' && $flash_extra === '') {
    return;
}
?>
<div class="flash-success" role="status">
  <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
  <?php if ($flash_extra !== ''): ?><?= $flash_extra ?><?php endif; ?>
</div>
