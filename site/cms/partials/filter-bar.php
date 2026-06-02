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
 *       'mode'   => 'or'|'jump',      // 'or' = single-select OR within group;
 *                                     // 'jump' = scroll-to-section pills
 *       'pills'  => [
 *         [ 'label' => 'All',         'href' => '?…', 'active' => true,  'all' => true ],
 *         [ 'label' => 'UX Industry', 'href' => '?…', 'active' => false, 'colour' => 'terracotta' ],
 *       ],
 *     ]
 *
 *   Each pill may carry:
 *     - href    string  When set, the pill renders as an <a> that navigates
 *                       — drives the OR filter via query-string state.
 *                       When omitted, falls back to a plain <button> (jump
 *                       mode and legacy callers).
 *     - colour  string  Optional design-system colour token (terracotta,
 *                       forest, denim, …). Drives the active-state tint via
 *                       `--pill-cat` so category pills carry their category
 *                       colour.
 *     - active  bool    Marks the pill as selected.
 *     - all     bool    Adds the .all-btn class (used for the "All" reset).
 *
 * Renders nothing if $groups is empty — callers can skip including the
 * partial in that case, but a defensive return keeps composition tidy.
 */

$groups = (array)($groups ?? []);
if (count($groups) === 0) return;
$_e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
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
      <span class="filter-label"><?= $_e($label) ?></span>
    <?php endif; ?>
    <div class="filter-group" <?= $dataAttr ?>>
      <?php foreach ($pills as $pill):
          $pillLabel  = (string)($pill['label']  ?? '');
          $pillHref   = (string)($pill['href']   ?? '');
          $pillActive = (bool)  ($pill['active'] ?? false);
          $pillAll    = (bool)  ($pill['all']    ?? false);
          $pillColour = (string)($pill['colour'] ?? '');
          $classes    = ['filter-pill'];
          if ($pillAll)    $classes[] = 'all-btn';
          if ($pillActive) $classes[] = 'active';
          $style = $pillColour !== '' ? ' style="--pill-cat:var(--c-' . $_e($pillColour) . ')"' : '';
      ?>
        <?php if ($pillHref !== ''): ?>
          <a href="<?= $_e($pillHref) ?>" class="<?= $_e(implode(' ', $classes)) ?>"<?= $style ?>><?= $_e($pillLabel) ?></a>
        <?php else: ?>
          <button type="button" class="<?= $_e(implode(' ', $classes)) ?>"<?= $style ?>><?= $_e($pillLabel) ?></button>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
