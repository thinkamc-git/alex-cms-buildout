<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/view-header.php — title + subtitle + optional action buttons.
 *
 * Inputs (set before include):
 *   $title           string  Required. Plain text. Italic serif heading.
 *   $subtitle        string  Optional. Plain text. Muted secondary line.
 *   $subtitle_extra  string  Optional. RAW HTML appended inside .view-subtitle
 *                            (after the escaped $subtitle text). Used by edit
 *                            views to surface inline status notes — "Saved.",
 *                            "Advanced to Concept.", etc. — alongside the
 *                            "last saved" timestamp. Caller escapes contents.
 *   $actions         string  Optional. Raw HTML for the right-hand actions
 *                            slot. Caller escapes contents.
 */

$title          = (string)($title ?? '');
$subtitle       = (string)($subtitle ?? '');
$subtitle_extra = (string)($subtitle_extra ?? '');
$actions        = (string)($actions ?? '');
?>
<div class="view-header">
  <div class="view-header-left">
    <div class="view-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>
    <?php if ($subtitle !== '' || $subtitle_extra !== ''): ?>
      <div class="view-subtitle">
        <?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($subtitle_extra !== ''): ?><?= $subtitle_extra ?><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php if ($actions !== ''): ?>
    <div class="view-header-actions"><?= $actions ?></div>
  <?php endif; ?>
</div>
