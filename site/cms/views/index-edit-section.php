<?php
/**
 * cms/views/index-edit-section.php — one section card inside the
 * Editorial section-stack builder.
 *
 * Included by index-edit.php inside the foreach over $sections AND
 * inside three <template> elements (one per type) the Add-section JS
 * clones. The caller passes variables in scope:
 *
 *   $i       int | string  Index within the stack (for sections[$i][...] names).
 *                          Templates pass the literal string '__TPL__' so the
 *                          JS can str-replace it to the real index on clone.
 *   $sid     int           Section id (0 for new / template).
 *   $hstyle  string        'small' | 'big' — section header rendering.
 *   $stype   string        'hero' | 'curated' | 'feed'
 *   $stitle  string
 *   $items   int[]         picked content ids (hero = 0 or 1 id, curated = N).
 *   $ftypes  string[]      feed_types (Feed only)
 *   $fcats   string[]      feed_categories (Feed only)
 *   $fopts   string[]      filter_options (Feed only)
 *   $fshow   bool
 *   $fby     string        'types' | 'categories'
 *   $fmt     string        'grid' | 'carousel'
 *   $gridR   string        '1'..'4' | 'all'
 *   $limit   int|string
 *   $seeLab  string
 *   $seeTgt  string        index slug ('/writing') OR absolute URL
 *   $fsort   string        'newest' | 'oldest'
 *
 * Plus closures + tables from the view: $e, $pickList, $pickById,
 * $pickLabel, $secTypeLabel, $sectionSummary, $catsForTypes, $allCats,
 * $typeLabels.
 */
if (!isset($i)) return;

$n           = $e((string)$i);
$inputBase   = 'sections[' . $n . ']';
$typeLabel   = $secTypeLabel($stype);
$summary     = $sectionSummary([
    'section_type'   => $stype,
    'item_ids'       => $items,
    'feed_types'     => $ftypes,
    'feed_sort'      => $fsort,
    'display_format' => $fmt,
    'grid_rows'      => $gridR,
]);
$itemIdsStr  = implode(',', array_map('intval', $items));
$displayName = $stitle !== '' ? $stitle : '(no title)';

// See more — split the stored target into ("index"|"custom") + value
// for the picker. An empty target = no see-more card.
$seeType = 'index';
$seeIdxSlug = '';
$seeCustom  = '';
if ($seeTgt !== '') {
    if (preg_match('#^https?://#', $seeTgt)) {
        $seeType = 'custom';
        $seeCustom = $seeTgt;
    } else {
        // Treat anything else as an index slug (strip leading slash).
        $seeType = 'index';
        $seeIdxSlug = ltrim($seeTgt, '/');
    }
}

// Categories shown in the section's pickers depend on the feed_types
// selection. Empty types → show all categories.
$catsForThis = $catsForTypes($ftypes);
?>
<div class="sec-card <?= $sid > 0 ? 'is-collapsed' : '' ?>" data-section data-section-type="<?= $e($stype) ?>">
  <input type="hidden" name="<?= $inputBase ?>[id]"   value="<?= (int)$sid ?>">
  <input type="hidden" name="<?= $inputBase ?>[type]" value="<?= $e($stype) ?>">

  <div class="sec-card-head" data-collapse-toggle style="cursor:pointer;align-items:center;display:flex;gap:var(--space-12)">
    <span class="cms-grip" title="Drag to reorder" data-grip>&#8942;&#8942;</span>
    <span class="content-block-label" style="min-width:70px;letter-spacing:0.16em"><?= $e($typeLabel) ?></span>
    <span class="cms-divider-v"></span>
    <span style="font-family:var(--font);font-size:var(--text-meta);font-weight:600;color:var(--primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" data-section-name><?= $e($displayName) ?></span>
    <span style="font-family:var(--font-mono);font-size:var(--text-micro);color:var(--muted);flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" data-section-summary><?= $e($summary) ?></span>
    <div style="display:flex;gap:var(--space-4);align-items:center;flex-shrink:0;margin-left:auto">
      <button type="button" class="btn-icon btn-icon-danger" title="Delete section" data-section-delete>
        <svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <button type="button" class="btn-icon" title="Expand / collapse" data-collapse-chevron>
        <svg viewBox="0 0 14 14" fill="none"><path d="M3 5l4 4 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
    </div>
  </div>

  <div class="sec-card-body" style="padding:var(--space-20) var(--space-24)">

<?php if ($stype === 'hero'):
    // Resolve the currently-selected post's hero_image so the preview
    // can show it server-side on first render (Auto mode default).
    $pickedHero = '';
    $pickedId = $items !== [] ? (int)$items[0] : 0;
    if ($pickedId > 0 && isset($pickById[$pickedId])) {
        $pickedHero = (string)($pickById[$pickedId]['hero_image'] ?? '');
    }
    $previewSrc = '';
    if ($himode === 'custom' && $himgUrl !== '') {
        $previewSrc = $himgUrl;
    } elseif ($himode === 'auto' && $pickedHero !== '') {
        $previewSrc = $pickedHero;
    }
?>
    <div class="form-grid" style="grid-template-columns: minmax(0,1fr) 360px; gap: var(--space-24)">
      <div class="form-side" data-hero-form>
        <div class="field-group sec-title-field">
          <label class="field-label">Title</label>
          <span class="field-clearable">
            <input type="text" class="field-input" name="<?= $inputBase ?>[title]" placeholder="no title" value="<?= $e($stitle) ?>">
            <button type="button" class="clear-x" title="Clear" aria-label="Clear" onclick="var i=this.previousElementSibling; i.value=''; i.dispatchEvent(new Event('input',{bubbles:true})); i.focus();"><svg viewBox="0 0 14 14" fill="none" width="12" height="12"><path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></button>
          </span>
        </div>
        <div class="field-group">
          <label class="field-label">Select a post</label>
          <select class="field-input" name="<?= $inputBase ?>[item_ids]" data-hero-pick>
            <option value="" data-hero-image="" data-title="" data-meta="">— pick a published post —</option>
            <?php foreach ($pickList as $row):
                $rid     = (int)$row['id'];
                $sel     = ($items !== [] && (int)$items[0] === $rid) ? ' selected' : '';
                $rType   = (string)($row['type']  ?? '');
                $rTitle  = (string)($row['title'] ?? '');
                $rPub    = (string)($row['published_at'] ?? '');
                $rDate   = $rPub !== '' ? date('Y-m-d', strtotime($rPub)) : '';
                $rTLab   = ucfirst(str_replace('-', ' ', $rType));
                $rMeta   = trim($rTLab . ($rDate !== '' ? ' · ' . $rDate : ''));
            ?>
              <option value="<?= $rid ?>"<?= $sel ?>
                      data-hero-image="<?= $e((string)($row['hero_image'] ?? '')) ?>"
                      data-title="<?= $e($rTitle) ?>"
                      data-meta="<?= $e($rMeta) ?>"><?= $pickLabel($row) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php $showCustomImg = $hlayout !== 'plain' && $himode === 'custom'; ?>
        <div class="field-group" data-hero-image-mode-field style="margin-bottom:0;<?= $hlayout === 'plain' ? 'display:none' : '' ?>">
          <label class="field-label">Image source</label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:wrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[hero_image_mode]">
              <?php foreach (['auto' => 'From Post', 'custom' => 'Custom', 'none' => 'None'] as $v => $l): ?>
                <button type="button" class="filter-pill <?= $himode === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($l) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[hero_image_mode]" value="<?= $e($himode) ?>">

          <div data-hero-image-url style="margin-top:var(--space-8);<?= $showCustomImg ? '' : 'display:none' ?>">
            <div style="display:flex;gap:var(--space-8);align-items:center">
              <input type="text" class="field-input" name="<?= $inputBase ?>[hero_image_url]" placeholder="/uploads/2026/03/cover.jpg" value="<?= $e($himgUrl) ?>" data-hero-img-url-input style="flex:1;min-width:0">
              <label class="btn-sec" style="cursor:pointer;white-space:nowrap;margin:0">
                Upload
                <input type="file" accept="image/*" data-hero-img-upload style="display:none">
              </label>
            </div>
            <p class="field-hint" data-hero-img-status style="display:none"></p>
          </div>
        </div>
      </div><!-- /.form-side data-hero-form -->

      <div class="form-side" data-hero-preview-pane>
        <div class="field-group">
          <label class="field-label">Preview</label>
          <div class="hero-img-preview hero-img-preview--<?= $e($hlayout) ?> hero-img-preview--bg-<?= $e($hbg) ?>" data-hero-preview>
            <div class="hero-img-preview-text" aria-hidden="true">
              <div class="hero-img-preview-title">Title Example</div>
              <div class="hero-img-preview-caption">An example of the summary text.</div>
            </div>
            <div class="hero-img-preview-imgwrap" data-hero-preview-imgwrap>
              <?php if ($previewSrc !== ''): ?>
                <img src="<?= $e($previewSrc) ?>" alt="" data-hero-preview-img>
              <?php else: ?>
                <span class="hero-img-preview-empty" data-hero-preview-empty>No image</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="field-group">
          <label class="field-label">Layout</label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:wrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[hero_layout]" data-hero-layout-pills>
              <?php foreach (['plain' => 'Plain', 'within' => 'Image Container', 'bleed-dark' => 'Bleed · Dark', 'bleed-light' => 'Bleed · Light'] as $v => $l): ?>
                <button type="button" class="filter-pill <?= $hlayout === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($l) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[hero_layout]" value="<?= $e($hlayout) ?>">
        </div>

        <div class="field-group" data-hero-bg-field style="margin-bottom:0;<?= in_array($hlayout, ['plain','within'], true) ? '' : 'display:none' ?>">
          <label class="field-label">Background</label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:nowrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[hero_background]">
              <?php foreach (['transparent' => 'Transparent', 'surface' => 'Solid White'] as $v => $l): ?>
                <button type="button" class="filter-pill <?= $hbg === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($l) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[hero_background]" value="<?= $e($hbg) ?>">
        </div>
      </div>
    </div><!-- /.form-grid hero 2-col -->


<?php elseif ($stype === 'curated'): ?>
    <div class="form-grid" style="grid-template-columns: 280px minmax(0,1fr)">
      <!-- Left (narrow): title + display + trailing card -->
      <div class="form-side">
        <div class="field-group sec-title-field">
          <label class="field-label">Title</label>
          <span class="field-clearable">
            <input type="text" class="field-input" name="<?= $inputBase ?>[title]" placeholder="no title" value="<?= $e($stitle) ?>">
            <button type="button" class="clear-x" title="Clear" aria-label="Clear" onclick="var i=this.previousElementSibling; i.value=''; i.dispatchEvent(new Event('input',{bubbles:true})); i.focus();"><svg viewBox="0 0 14 14" fill="none" width="12" height="12"><path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></button>
          </span>
        </div>
        <div class="field-group">
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:nowrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[header_style]">
              <?php foreach (['small' => 'Tiny', 'big' => 'Large'] as $v => $l): ?>
                <button type="button" class="filter-pill <?= $hstyle === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($l) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[header_style]" value="<?= $e($hstyle) ?>">
        </div>
        <div class="field-group">
          <label class="field-label">Format</label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:nowrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[display_format]">
              <?php foreach (['grid' => 'Grid', 'carousel' => 'Carousel'] as $v => $l): ?>
                <button type="button" class="filter-pill <?= $fmt === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($l) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[display_format]" value="<?= $e($fmt) ?>">
        </div>
        <div class="field-group" data-grid-rows-field<?= $fmt === 'carousel' ? ' style="display:none"' : '' ?>>
          <label class="field-label">Grid rows</label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:nowrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[grid_rows]">
              <?php foreach (['1', '2', '3', '4', 'all'] as $v): ?>
                <button type="button" class="filter-pill <?= $gridR === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($v === 'all' ? 'All' : $v) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[grid_rows]" value="<?= $e($gridR) ?>">
        </div>
        <div class="field-group">
          <label class="field-label">View all label</label>
          <input type="text" class="field-input" name="<?= $inputBase ?>[see_more_label]" placeholder="View all" value="<?= $e($seeLab) ?>">
        </div>
        <div class="field-group" style="margin-bottom:0">
          <label class="field-label">View all target</label>
          <div class="see-target-row">
            <select class="field-input" data-see-type onchange="(function(s){var w=s.closest('.see-target-row').querySelector('[data-see-picker]'); w.querySelectorAll('[data-see-pick]').forEach(function(el){el.style.display=(el.getAttribute('data-see-pick')===s.value?'':'none');});})(this)">
              <option value="index"<?= $seeType === 'index' ? ' selected' : '' ?>>Index</option>
              <option value="custom"<?= $seeType === 'custom' ? ' selected' : '' ?>>Custom link</option>
            </select>
            <span data-see-picker>
              <select class="field-input" name="<?= $inputBase ?>[see_more_target_index]" data-see-pick="index" style="<?= $seeType === 'index' ? '' : 'display:none' ?>">
                <option value="">— pick an index —</option>
                <?php foreach (list_indexes() as $idx):
                    $idxSlug = (string)$idx['slug'];
                ?>
                  <option value="<?= $e($idxSlug) ?>"<?= $seeIdxSlug === $idxSlug ? ' selected' : '' ?>><?= $e((string)($idx['title'] ?? $idxSlug)) ?> (/<?= $e($idxSlug) ?>/)</option>
                <?php endforeach; ?>
              </select>
              <input type="text" class="field-input" name="<?= $inputBase ?>[see_more_target_custom]" data-see-pick="custom" placeholder="https://…" value="<?= $e($seeCustom) ?>" style="<?= $seeType === 'custom' ? '' : 'display:none' ?>">
            </span>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[see_more_target]" value="<?= $e($seeTgt) ?>" data-see-target-resolved>
        </div>
      </div>

      <!-- Right (wide): posts table + add-post search -->
      <div class="form-main">
        <div class="field-group" style="margin-bottom:0">
          <label class="field-label">Posts <span class="field-hint-inline" style="font-family:var(--font-mono);font-size:var(--text-micro);font-weight:400;letter-spacing:0;text-transform:none;color:var(--muted)">drag to reorder</span></label>
          <div class="table-card">
            <table class="cms-table" data-posts-table>
              <thead>
                <tr>
                  <th style="width:32px"></th>
                  <th style="width:48px">#</th>
                  <th>Title</th>
                  <th>Type</th>
                  <th>Date</th>
                  <th style="width:32px"></th>
                </tr>
              </thead>
              <tbody data-posts-tbody>
                <?php $rn = 0; foreach ($items as $pid):
                    $r = $pickById[(int)$pid] ?? null;
                    if ($r === null) continue;
                    $rn++;
                    $ptype = (string)$r['type'];
                ?>
                <tr draggable="true" data-post-id="<?= (int)$r['id'] ?>">
                  <td><span class="cms-grip">&#8942;&#8942;</span></td>
                  <td><span class="val-pill"><?= str_pad((string)$rn, 2, '0', STR_PAD_LEFT) ?></span></td>
                  <td style="font-weight:600;color:var(--primary)"><?= $e((string)$r['title']) ?></td>
                  <td><span class="pill tb-<?= $e($ptype) ?>"><?= $e($typeLabels[$ptype] ?? ucfirst($ptype)) ?></span></td>
                  <td style="font-family:var(--font-mono);font-size:var(--text-micro);color:var(--muted);white-space:nowrap"><?= $e($r['published_at'] ? date('Y-m-d', strtotime((string)$r['published_at'])) : '') ?></td>
                  <td><button type="button" class="btn-icon" title="Remove from this section" data-post-remove><svg viewBox="0 0 14 14" fill="none"><path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></button></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($rn === 0): ?>
                <tr data-posts-empty>
                  <td colspan="6" style="text-align:center;color:var(--muted);padding:var(--space-24)">No posts yet.</td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[item_ids]" value="<?= $e($itemIdsStr) ?>" data-posts-ids>
          <div style="display:flex;gap:var(--space-8);align-items:center;padding:var(--space-8) var(--space-12);background:var(--canvas-raised);border:1px dashed var(--border);border-radius:3px;margin-top:6px">
            <select class="field-input" data-post-add style="flex:1">
              <option value="">+ Add post…</option>
              <?php foreach ($pickList as $row): ?>
                <option value="<?= (int)$row['id'] ?>" data-type="<?= $e((string)$row['type']) ?>" data-title="<?= $e((string)$row['title']) ?>" data-date="<?= $e($row['published_at'] ? date('Y-m-d', strtotime((string)$row['published_at'])) : '') ?>"><?= $pickLabel($row) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

<?php else: /* feed */ ?>
    <div class="form-grid-3">
      <!-- Col 1 — title + display + trailing card -->
      <div>
        <div class="content-block-label" style="margin-bottom:var(--space-12);letter-spacing:0.16em">Configure section</div>
        <div class="field-group sec-title-field">
          <label class="field-label">Section Title</label>
          <span class="field-clearable">
            <input type="text" class="field-input" name="<?= $inputBase ?>[title]" placeholder="no title" value="<?= $e($stitle) ?>">
            <button type="button" class="clear-x" title="Clear" aria-label="Clear" onclick="var i=this.previousElementSibling; i.value=''; i.dispatchEvent(new Event('input',{bubbles:true})); i.focus();"><svg viewBox="0 0 14 14" fill="none" width="12" height="12"><path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></button>
          </span>
        </div>
        <div class="field-group">
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:nowrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[header_style]">
              <?php foreach (['small' => 'Tiny', 'big' => 'Large'] as $v => $l): ?>
                <button type="button" class="filter-pill <?= $hstyle === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($l) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[header_style]" value="<?= $e($hstyle) ?>">
        </div>
        <div class="field-group">
          <label class="field-label">Format</label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:nowrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[display_format]">
              <?php foreach (['grid' => 'Grid', 'carousel' => 'Carousel'] as $v => $l): ?>
                <button type="button" class="filter-pill <?= $fmt === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($l) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[display_format]" value="<?= $e($fmt) ?>">
        </div>
        <div class="field-group" data-grid-rows-field<?= $fmt === 'carousel' ? ' style="display:none"' : '' ?>>
          <label class="field-label">Grid rows</label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:nowrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[grid_rows]">
              <?php foreach (['1', '2', '3', '4', 'all'] as $v): ?>
                <button type="button" class="filter-pill <?= $gridR === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($v === 'all' ? 'All' : $v) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[grid_rows]" value="<?= $e($gridR) ?>">
        </div>
        <div class="field-group">
          <label class="field-label">View all target</label>
          <div class="see-target-row">
            <select class="field-input" data-see-type onchange="(function(s){var w=s.closest('.see-target-row').querySelector('[data-see-picker]'); w.querySelectorAll('[data-see-pick]').forEach(function(el){el.style.display=(el.getAttribute('data-see-pick')===s.value?'':'none');});})(this)">
              <option value="index"<?= $seeType === 'index' ? ' selected' : '' ?>>Index</option>
              <option value="custom"<?= $seeType === 'custom' ? ' selected' : '' ?>>Custom link</option>
            </select>
            <span data-see-picker>
              <select class="field-input" name="<?= $inputBase ?>[see_more_target_index]" data-see-pick="index" style="<?= $seeType === 'index' ? '' : 'display:none' ?>">
                <option value="">— pick an index —</option>
                <?php foreach (list_indexes() as $idx):
                    $idxSlug = (string)$idx['slug'];
                ?>
                  <option value="<?= $e($idxSlug) ?>"<?= $seeIdxSlug === $idxSlug ? ' selected' : '' ?>><?= $e((string)($idx['title'] ?? $idxSlug)) ?> (/<?= $e($idxSlug) ?>/)</option>
                <?php endforeach; ?>
              </select>
              <input type="text" class="field-input" name="<?= $inputBase ?>[see_more_target_custom]" data-see-pick="custom" placeholder="https://…" value="<?= $e($seeCustom) ?>" style="<?= $seeType === 'custom' ? '' : 'display:none' ?>">
            </span>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[see_more_target]" value="<?= $e($seeTgt) ?>" data-see-target-resolved>
        </div>
        <div class="field-group" style="margin-bottom:0">
          <input type="text" class="field-input" name="<?= $inputBase ?>[see_more_label]" placeholder="Custom View All Label" value="<?= $e($seeLab) ?>">
        </div>
      </div>

      <!-- Col 2 — Content Query -->
      <div>
        <div class="content-block-label" style="margin-bottom:var(--space-12);letter-spacing:0.16em">Content query</div>
        <div class="field-group">
          <label class="field-label">Types</label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:wrap">
            <div class="filter-group">
              <?php foreach ($typeLabels as $slug => $label):
                  $on = in_array($slug, $ftypes, true);
              ?>
                <label class="filter-pill <?= $on ? 'active' : '' ?>" style="cursor:pointer">
                  <input type="checkbox" name="<?= $inputBase ?>[feed_types][]" value="<?= $e($slug) ?>" <?= $on ? 'checked' : '' ?> style="display:none">
                  <?= $e($label) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="field-group">
          <label class="field-label">Categories <span class="field-hint-inline" style="font-family:var(--font-mono);font-size:var(--text-micro);font-weight:400;letter-spacing:0;text-transform:none;color:var(--muted)">based on selected types</span></label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:wrap">
            <div class="cat-rail"
                 data-cat-rail
                 data-cat-rail-name="<?= $inputBase ?>[feed_categories][]"
                 data-cat-rail-selected="<?= $e(implode(',', $fcats)) ?>"
                 data-cat-rail-types-source="content-query"></div>
          </div>
        </div>
        <div class="field-group" style="margin-bottom:0">
          <label class="field-label">Sort</label>
          <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:nowrap">
            <div class="filter-group" data-pill-group="single" data-pill-name="<?= $inputBase ?>[feed_sort]">
              <?php foreach (['newest' => 'Newest', 'oldest' => 'Oldest'] as $v => $l): ?>
                <button type="button" class="filter-pill <?= $fsort === $v ? 'active' : '' ?>" data-pill-value="<?= $e($v) ?>"><?= $e($l) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <input type="hidden" name="<?= $inputBase ?>[feed_sort]" value="<?= $e($fsort) ?>">
        </div>
      </div>

      <!-- Col 3 — Show Filter -->
      <div>
        <div class="content-block-label" style="margin-bottom:var(--space-12);display:flex;align-items:center;letter-spacing:0.16em">
          <span>Show Filters</span>
          <label class="switch-filled" style="margin-left:auto">
            <input type="checkbox" name="<?= $inputBase ?>[filter_show]" value="1" <?= $fshow ? 'checked' : '' ?> data-filter-toggle>
            <span class="slider"></span>
          </label>
        </div>
        <div data-filter-detail style="<?= $fshow ? '' : 'display:none' ?>">
          <div class="field-group">
            <label class="field-label">Type Toggles</label>
            <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:wrap">
              <div class="filter-group">
                <?php foreach ($typeLabels as $slug => $label):
                    $on = in_array($slug, $fopts, true);
                ?>
                  <label class="filter-pill <?= $on ? 'active' : '' ?>" style="cursor:pointer">
                    <input type="checkbox" name="<?= $inputBase ?>[filter_options][]" value="<?= $e($slug) ?>" <?= $on ? 'checked' : '' ?> style="display:none">
                    <?= $e($label) ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="field-group" style="margin-bottom:0">
            <label class="field-label">Category Toggles</label>
            <div class="filter-bar" style="padding:0;background:transparent;border-bottom:none;flex-wrap:wrap">
              <div class="cat-rail"
                   data-cat-rail
                   data-cat-rail-name="<?= $inputBase ?>[filter_options][]"
                   data-cat-rail-selected="<?= $e(implode(',', $fopts)) ?>"
                   data-cat-rail-types-source="content-query"></div>
            </div>
          </div>
        </div>
        <input type="hidden" name="<?= $inputBase ?>[filter_by]" value="<?= $e($fby ?: 'types') ?>">
      </div>
    </div>
<?php endif; ?>

  </div><!-- /.sec-card-body -->
</div>
