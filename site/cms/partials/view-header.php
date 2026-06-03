<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/view-header.php — title + subtitle + optional action buttons.
 *
 * Inputs (set before include):
 *   $title    string  Required. Plain text. Italic serif heading.
 *   $subtitle string  Optional. Plain text. Muted secondary line.
 *   $actions  string  Optional. Raw HTML for the right-hand actions slot.
 *                     Caller is responsible for escaping its contents.
 *
 * Batch 2 #30: $subtitle_extra was removed — every editor now surfaces flash
 * notes (Saved, Advanced, Reverted, Published) through the canonical
 * .flash-success banner (partials/flash.php), not as an inline pill inside
 * the view-subtitle line.
 */

$title    = (string)($title    ?? '');
$subtitle = (string)($subtitle ?? '');
$actions  = (string)($actions  ?? '');
?>
<div class="view-header">
  <div class="view-header-left">
    <div class="view-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>
    <?php if ($subtitle !== ''): ?>
      <div class="view-subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
  </div>
  <?php if ($actions !== ''): ?>
    <div class="view-header-actions"><?= $actions ?></div>
  <?php endif; ?>
</div>
