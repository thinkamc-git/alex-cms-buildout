<?php
declare(strict_types=1);
if (!defined('CMS_PARTIAL_OK')) { http_response_code(404); exit; }
/**
 * cms/partials/filter-bar.php — horizontal filter rail.
 *
 * Inputs (set before include):
 *   $groups  array  Ordered list of filter groups. Each group:
 *     [
 *       'label'  => 'Category',       // shown as the uppercase rail label
 *       'mode'   => 'or'|'jump',      // 'or' = multi-select OR filter;
 *                                     // 'jump' = scroll-to-section pills
 *       'pills'  => [
 *         [ 'label' => 'All',         'active' => true,  'all' => true ],
 *         [ 'label' => 'UX Industry', 'active' => false ],
 *         [ 'label' => 'Framework',   'active' => false ],
 *       ],
 *     ]
 *
 * Renders nothing if $groups is empty — callers can skip including the
 * partial in that case, but a defensive return keeps composition tidy.
 */

$groups = (array)($groups ?? []);
if (count($groups) === 0) return;
?>
<div class="filter-bar">
  <?php foreach ($groups as $groupIndex => $group):
      $label = (string)($group['label'] ?? '');
      $mode  = (string)($group['mode']  ?? 'or');
      $pills = (array) ($group['pills'] ?? []);
      $dataAttr = $mode === 'jump' ? 'data-jump-group' : 'data-or-group';
  ?>
    <?php if ($groupIndex > 0): ?><div class="filter-sep"></div><?php endif; ?>
    <?php if ($label !== ''): ?>
      <span class="filter-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
    <div class="filter-group" <?= $dataAttr ?>>
      <?php foreach ($pills as $pill):
          $pillLabel  = (string)($pill['label']  ?? '');
          $pillActive = (bool)  ($pill['active'] ?? false);
          $pillAll    = (bool)  ($pill['all']    ?? false);
          $classes    = ['filter-pill'];
          if ($pillAll)    $classes[] = 'all-btn';
          if ($pillActive) $classes[] = 'active';
      ?>
        <button type="button" class="<?= implode(' ', $classes) ?>"><?= htmlspecialchars($pillLabel, ENT_QUOTES, 'UTF-8') ?></button>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
