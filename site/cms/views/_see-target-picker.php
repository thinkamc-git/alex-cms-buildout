<?php
/**
 * Reusable "See more / Read more" target picker (nav parity). Types: index,
 * category, series, content, page, custom — mirroring the navigation builder.
 *
 * Each dependent picker's option VALUE is the resolved URL (equal to what's
 * stored in see_more_target), so the choice round-trips on reload by matching
 * the stored URL. The type <select> submits see_more_target_type; index-edit.js
 * resolves the active pick's value into the hidden see_more_target on submit.
 *
 * Expects in scope: $inputBase, $seeType, $seeTgt, $seeCustom, $e,
 * $seeTargetOpts (['index'|'category'|'series'|'content'|'page' => [['url','label'],…]]).
 * Optional: $seePlaceholder (empty-option label).
 */
declare(strict_types=1);
$seePlaceholder = $seePlaceholder ?? '— no link —';
$seeKinds = [
    'index'    => 'Index',
    'category' => 'Category',
    'series'   => 'Series',
    'content'  => 'Content',
    'page'     => 'Page',
    'custom'   => 'Custom link',
];
?>
<div class="see-target-row">
  <select class="field-input" name="<?= $inputBase ?>[see_more_target_type]" data-see-type
          onchange="(function(s){var w=s.closest('.see-target-row').querySelector('[data-see-picker]'); w.querySelectorAll('[data-see-pick]').forEach(function(el){el.style.display=(el.getAttribute('data-see-pick')===s.value?'':'none');}); var sec=s.closest('[data-section]'); if(sec)sec.dispatchEvent(new Event('change',{bubbles:true}));})(this)">
    <?php foreach ($seeKinds as $k => $label): ?>
      <option value="<?= $k ?>"<?= $seeType === $k ? ' selected' : '' ?>><?= $e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <span data-see-picker>
    <?php foreach (['index', 'category', 'series', 'content', 'page'] as $kind): ?>
      <select class="field-input" data-see-pick="<?= $kind ?>" style="<?= $seeType === $kind ? '' : 'display:none' ?>">
        <option value=""><?= $e($seePlaceholder) ?></option>
        <?php foreach (($seeTargetOpts[$kind] ?? []) as $opt): ?>
          <option value="<?= $e($opt['url']) ?>"<?= ($seeType === $kind && $seeTgt === $opt['url']) ? ' selected' : '' ?>><?= $e($opt['label']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endforeach; ?>
    <input type="text" class="field-input" data-see-pick="custom" placeholder="https://… or /path/" value="<?= $seeType === 'custom' ? $e($seeCustom) : '' ?>" style="<?= $seeType === 'custom' ? '' : 'display:none' ?>">
  </span>
</div>
<input type="hidden" name="<?= $inputBase ?>[see_more_target]" value="<?= $e($seeTgt) ?>" data-see-target-resolved>
