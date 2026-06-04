/**
 * cms/_assets/index-edit.js — Editorial Index section-stack builder (Phase 21.7).
 *
 * Handles:
 *   - Pill single-select toggles (writes to a hidden input)
 *   - Multi-select pill labels (active class flips with the checkbox)
 *   - Content-block expand/collapse via .is-collapsed
 *   - Add section: clones a hidden <template id="sec-tpl-{type}">
 *   - Delete section (DOM remove; server dedupes on save)
 *   - Drag-reorder section stack (native HTML5)
 *   - Drag-reorder Posts inside a Curated section + add/remove post
 *   - Layout pill toggle (Editorial / Basic Listing) — reloads the page
 *     since the form structure differs per layout
 *   - See-more target picker (Index ↔ Custom) — resolves to one hidden value on submit
 *   - Categories pill rails rebuild when the section's Types pills change
 *   - Reindex sections[N][...] form names after add / delete / reorder
 *
 * No external deps. Pure HTML5 + form serialization.
 */
(function () {
  'use strict';

  var form = document.getElementById('index-edit-form');
  if (!form) return;

  // Signal dirty to dirty-flip after non-input mutations (add/delete/
  // reorder/post add/remove). dirty-flip listens for `change` bubbling
  // up to the form element.
  function markDirty() {
    form.dispatchEvent(new Event('change', { bubbles: true }));
  }

  // Apply visibility rules for the Hero section editor fields based on
  // the section's current hero_layout and hero_image_mode hidden inputs.
  function syncHeroFieldVisibility(sec) {
    if (!sec) return;
    var layoutH = sec.querySelector('input[type="hidden"][name$="[hero_layout]"]');
    var modeH   = sec.querySelector('input[type="hidden"][name$="[hero_image_mode]"]');
    if (!layoutH || !modeH) return;
    var layout = layoutH.value;
    var mode   = modeH.value;
    var bgField = sec.querySelector('[data-hero-bg-field]');
    var imgMode = sec.querySelector('[data-hero-image-mode-field]');
    var imgUrl  = sec.querySelector('[data-hero-image-url]');
    if (bgField) bgField.style.display = (layout === 'plain' || layout === 'within') ? '' : 'none';
    if (imgMode) imgMode.style.display = (layout === 'plain') ? 'none' : '';
    if (imgUrl)  imgUrl.style.display  = (layout !== 'plain' && mode === 'custom') ? '' : 'none';
    syncHeroPreview(sec);
  }

  // Update the right-side hero image preview based on current
  // layout / image source / custom URL / picked post's hero_image.
  function syncHeroPreview(sec) {
    if (!sec) return;
    var pane = sec.querySelector('[data-hero-preview]');
    if (!pane) return;
    var layoutH = sec.querySelector('input[type="hidden"][name$="[hero_layout]"]');
    var modeH   = sec.querySelector('input[type="hidden"][name$="[hero_image_mode]"]');
    var bgH     = sec.querySelector('input[type="hidden"][name$="[hero_background]"]');
    var urlIn   = sec.querySelector('[data-hero-img-url-input]');
    var pickSel = sec.querySelector('[data-hero-pick]');
    var layout = layoutH ? layoutH.value : 'within';
    var mode   = modeH ? modeH.value : 'auto';
    var bg     = bgH ? bgH.value : 'transparent';
    var src = '';
    if (layout !== 'plain' && mode === 'custom' && urlIn) {
      src = (urlIn.value || '').trim();
    } else if (layout !== 'plain' && mode === 'auto' && pickSel) {
      var opt = pickSel.options[pickSel.selectedIndex];
      src = opt ? (opt.getAttribute('data-hero-image') || '') : '';
    }
    // Mirror layout + background onto the pane as modifier classes.
    ['plain', 'within', 'bleed-dark', 'bleed-light'].forEach(function (k) {
      pane.classList.remove('hero-img-preview--' + k);
    });
    pane.classList.add('hero-img-preview--' + layout);
    ['transparent', 'surface'].forEach(function (k) {
      pane.classList.remove('hero-img-preview--bg-' + k);
    });
    pane.classList.add('hero-img-preview--bg-' + bg);

    // Repopulate the imgwrap; leave the .hero-img-preview-text overlay
    // alone so the placeholder Title / Caption stay rendered.
    var imgwrap = pane.querySelector('[data-hero-preview-imgwrap]');
    if (!imgwrap) return;
    imgwrap.innerHTML = '';
    if (src) {
      var img = document.createElement('img');
      img.src = src;
      img.alt = '';
      img.setAttribute('data-hero-preview-img', '');
      imgwrap.appendChild(img);
    } else {
      var span = document.createElement('span');
      span.className = 'hero-img-preview-empty';
      span.setAttribute('data-hero-preview-empty', '');
      span.textContent = layout === 'plain' ? 'Plain' : 'No image';
      imgwrap.appendChild(span);
    }
  }

  // Refresh preview when the URL input changes (paste or upload-fill)
  // or the Pick select changes.
  form.addEventListener('input', function (e) {
    if (e.target && e.target.matches('[data-hero-img-url-input]')) {
      syncHeroPreview(e.target.closest('[data-section]'));
    }
  });
  form.addEventListener('change', function (e) {
    if (e.target && e.target.matches('[data-hero-pick]')) {
      syncHeroPreview(e.target.closest('[data-section]'));
    }
  });

  // ── Pill single-select (data-pill-group="single") ───────────────────
  // Each .filter-group with data-pill-group="single" pairs with a hidden
  // <input name=...> that captures the active pill's data-pill-value.
  form.addEventListener('click', function (e) {
    var pill = e.target.closest('.filter-pill[data-pill-value]');
    if (!pill) return;
    var grp = pill.closest('.filter-group[data-pill-group="single"]');
    if (!grp) return;
    grp.querySelectorAll('.filter-pill').forEach(function (p) { p.classList.remove('active'); });
    pill.classList.add('active');
    // The hidden input is a sibling of .filter-bar inside the same
    // .field-group, not inside .filter-bar — go up to the field-group.
    var fieldGroup = grp.closest('.field-group');
    var hidden = fieldGroup ? fieldGroup.querySelector('input[type="hidden"]') : null;
    if (hidden) {
      hidden.value = pill.getAttribute('data-pill-value');
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }
    // Carousel format hides Grid rows; Grid restores it.
    if (hidden && hidden.name && hidden.name.indexOf('[display_format]') > -1) {
      var section = pill.closest('[data-section]');
      if (section) {
        var rowsField = section.querySelector('[data-grid-rows-field]');
        if (rowsField) rowsField.style.display = hidden.value === 'carousel' ? 'none' : '';
      }
    }
    // Hero Layout / Image-source visibility rules:
    //   Plain  → Background visible; no image source or custom URL.
    //   Within → Background visible; image source visible; custom URL
    //            visible only when source = Custom.
    //   Bleed* → Background hidden;  image source visible; custom URL
    //            visible only when source = Custom.
    if (hidden && hidden.name &&
        (hidden.name.indexOf('[hero_layout]') > -1 ||
         hidden.name.indexOf('[hero_image_mode]') > -1 ||
         hidden.name.indexOf('[hero_background]') > -1)) {
      var sec3 = pill.closest('[data-section]');
      if (sec3) syncHeroFieldVisibility(sec3);
    }
    updateSummaryFor(pill.closest('[data-section]'));
  });

  // ── Multi-select pill labels — checkbox inside .filter-pill label ───
  // Native label/checkbox click toggles .checked; mirror to .active.
  form.addEventListener('change', function (e) {
    var cb = e.target;
    if (cb.tagName !== 'INPUT' || cb.type !== 'checkbox') return;
    var pill = cb.closest('.filter-pill');
    if (pill) pill.classList.toggle('active', cb.checked);
    // Rebuild category pill rails when feed_types changes.
    if (cb.name && cb.name.indexOf('[feed_types]') > -1) {
      rebuildCategoriesFor(cb.closest('[data-section]'));
    }
    updateSummaryFor(cb.closest('[data-section]'));
  });

  // ── Collapse / expand ───────────────────────────────────────────────
  // The chevron button explicitly toggles. Clicks on the head bar
  // (anywhere outside an interactive control) also toggle. Clicks on
  // the grip, delete, or any input/select inside the body don't.
  form.addEventListener('click', function (e) {
    var chevron = e.target.closest('[data-collapse-chevron]');
    if (chevron) {
      var card = chevron.closest('.sec-card');
      if (card) card.classList.toggle('is-collapsed');
      return;
    }
    // Clicks on other interactive elements should NOT toggle.
    if (e.target.closest('button, input, select, textarea, a, [data-section-delete], [data-grip]')) return;
    var head = e.target.closest('[data-collapse-toggle]');
    if (!head) return;
    var card2 = head.closest('.sec-card');
    if (card2) card2.classList.toggle('is-collapsed');
  });

  // ── Delete section ──────────────────────────────────────────────────
  form.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-section-delete]');
    if (!btn) return;
    if (!confirm('Delete this section? The configuration will be lost.')) return;
    var sec = btn.closest('[data-section]');
    if (sec) sec.remove();
    reindexSections();
    updateSectionCount();
    toggleSecEmpty();
    markDirty();
  });

  // ── Add section: three buttons (+ Hero / + Curated / + Filtered) ────
  form.querySelectorAll('button[data-add-type]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      appendSectionFromTemplate(btn.getAttribute('data-add-type'));
    });
  });

  function appendSectionFromTemplate(type) {
    var tpl = document.getElementById('sec-tpl-' + type);
    var stack = document.getElementById('sec-stack');
    if (!tpl || !stack) return;
    // Clone the template's content, swap __TPL__ placeholder for a real index,
    // and append.
    var html = tpl.innerHTML.replace(/__TPL__/g, String(stack.children.length));
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var card = tmp.firstElementChild;
    if (!card) return;
    // New sections start expanded so the author can configure immediately.
    card.classList.remove('is-collapsed');
    card.classList.add('is-fresh');
    stack.appendChild(card);
    reindexSections();
    updateSectionCount();
    toggleSecEmpty();
    card.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    setTimeout(function () { card.classList.remove('is-fresh'); }, 1300);
    markDirty();
  }

  function toggleSecEmpty() {
    var empty = document.querySelector('[data-sec-empty]');
    var has = stack && stack.children.length > 0;
    if (empty && has) empty.remove();
    if (!empty && !has) {
      var d = document.createElement('div');
      d.className = 'empty-state';
      d.setAttribute('data-sec-empty', '');
      d.textContent = 'No sections yet — add one below.';
      if (stack && stack.parentElement) stack.parentElement.insertBefore(d, stack);
    }
  }

  // ── Drag-reorder for the section stack ──────────────────────────────
  var stack = document.getElementById('sec-stack');
  if (stack) attachDragReorder(stack, '[data-section]', '[data-grip]', reindexSections);

  // ── Drag-reorder + Add / Remove for Posts inside Curated ────────────
  document.querySelectorAll('[data-posts-table]').forEach(function (table) {
    var tbody = table.querySelector('[data-posts-tbody]');
    if (tbody) attachDragReorder(tbody, 'tr[data-post-id]', '.cms-grip', function () {
      renumberPosts(tbody);
      serializePosts(table.closest('[data-section]'));
    });
  });

  // Post add
  form.addEventListener('change', function (e) {
    var sel = e.target.closest('[data-post-add]');
    if (!sel) return;
    var id = sel.value;
    if (!id) return;
    var sec = sel.closest('[data-section]');
    var tbody = sec.querySelector('[data-posts-tbody]');
    if (!tbody) return;
    // Skip if already present.
    if (tbody.querySelector('tr[data-post-id="' + id + '"]')) {
      sel.value = '';
      return;
    }
    var opt = sel.options[sel.selectedIndex];
    var ptype = opt.getAttribute('data-type') || '';
    var title = opt.getAttribute('data-title') || '';
    var date  = opt.getAttribute('data-date')  || '';
    var typeLabels = { article:'Articles', journal:'Journals', 'live-session':'Live Sessions', experiment:'Experiments' };
    var tr = document.createElement('tr');
    tr.setAttribute('draggable', 'true');
    tr.setAttribute('data-post-id', id);
    tr.innerHTML =
      '<td><span class="cms-grip">⋮⋮</span></td>' +
      '<td><span class="val-pill">00</span></td>' +
      '<td style="font-weight:600;color:var(--primary)"></td>' +
      '<td><span class="pill tb-' + escapeHtml(ptype) + '">' + escapeHtml(typeLabels[ptype] || ptype) + '</span></td>' +
      '<td style="font-family:var(--font-mono);font-size:var(--text-micro);color:var(--muted);white-space:nowrap">' + escapeHtml(date) + '</td>' +
      '<td><button type="button" class="btn-icon" title="Remove from this section" data-post-remove><svg viewBox="0 0 14 14" fill="none"><path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></button></td>';
    tr.children[2].textContent = title;
    // Drop the "No posts yet" row if it's there.
    var empty = tbody.querySelector('[data-posts-empty]');
    if (empty) empty.remove();
    tbody.appendChild(tr);
    sel.value = '';
    renumberPosts(tbody);
    serializePosts(sec);
    updateSummaryFor(sec);
  });

  // Post remove
  form.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-post-remove]');
    if (!btn) return;
    var tr = btn.closest('tr[data-post-id]');
    var sec = btn.closest('[data-section]');
    var tbody = tr.parentElement;
    tr.remove();
    // Re-add the "No posts yet" row if the table is empty.
    if (!tbody.querySelector('tr[data-post-id]') && !tbody.querySelector('[data-posts-empty]')) {
      var er = document.createElement('tr');
      er.setAttribute('data-posts-empty', '');
      er.innerHTML = '<td colspan="6" style="text-align:center;color:var(--muted);padding:var(--space-24)">No posts yet.</td>';
      tbody.appendChild(er);
    }
    renumberPosts(tbody);
    serializePosts(sec);
    updateSummaryFor(sec);
  });

  function renumberPosts(tbody) {
    var rn = 0;
    tbody.querySelectorAll('tr[data-post-id]').forEach(function (tr) {
      rn++;
      var pill = tr.querySelector('.val-pill');
      if (pill) pill.textContent = (rn < 10 ? '0' : '') + rn;
    });
  }

  function serializePosts(sec) {
    if (!sec) return;
    var ids = [];
    sec.querySelectorAll('tr[data-post-id]').forEach(function (tr) {
      ids.push(tr.getAttribute('data-post-id'));
    });
    var hidden = sec.querySelector('[data-posts-ids]');
    if (hidden) hidden.value = ids.join(',');
  }

  // ── Layout toggle: Editorial ↔ Basic Listing ────────────────────────
  // The two layouts use materially different form structures, so we
  // can't swap them client-side — submit immediately so the server
  // re-renders the appropriate form. Any other pending edits in the
  // current form get saved along the way.
  document.querySelectorAll('[data-layout-toggle] .filter-pill[data-layout]').forEach(function (p) {
    p.addEventListener('click', function () {
      var newLayout = p.getAttribute('data-layout');
      var hidden = document.getElementById('layout-input');
      if (!hidden) return;
      if (hidden.value === newLayout) return;
      hidden.value = newLayout;
      p.parentElement.querySelectorAll('.filter-pill').forEach(function (x) { x.classList.remove('active'); });
      p.classList.add('active');
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.submit();
    });
  });

  // ── On submit: resolve the see-more target into a single string ─────
  form.addEventListener('submit', function () {
    document.querySelectorAll('[data-see-target-resolved]').forEach(function (hidden) {
      var wrap = hidden.parentElement;
      var type = wrap.querySelector('[data-see-type]');
      var picker = wrap.querySelector('[data-see-picker]');
      if (!type || !picker) return;
      var t = type.value;
      var pick = picker.querySelector('[data-see-pick="' + t + '"]');
      if (!pick) { hidden.value = ''; return; }
      var v = (pick.value || '').trim();
      if (t === 'index' && v) v = '/' + v;
      hidden.value = v;
    });
  });

  // ── Helpers ──────────────────────────────────────────────────────────

  /**
   * Native HTML5 drag-and-drop for a vertical list. Mirrors series-edit's
   * dragstart/dragover/dragend pattern. Calls onReorder after a drop.
   */
  function attachDragReorder(container, itemSel, handleSel, onReorder) {
    var dragging = null;
    container.addEventListener('mousedown', function (e) {
      var grip = e.target.closest(handleSel);
      if (!grip) return;
      var item = grip.closest(itemSel);
      if (item) item.setAttribute('draggable', 'true');
    });
    container.addEventListener('dragstart', function (e) {
      var item = e.target.closest(itemSel);
      if (!item) return;
      dragging = item;
      item.classList.add('is-dragging');
      if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
    });
    container.addEventListener('dragend', function () {
      if (dragging) dragging.classList.remove('is-dragging');
      dragging = null;
      if (onReorder) onReorder();
      markDirty();
    });
    // Drop-line placeholder — a real 2px line element inserted into the
    // gap where the dragging item will land. Cleared on drop / dragend.
    function makeDropLine() {
      // tr-style for posts table needs a <tr> wrapper; everything else
      // uses a plain <div>.
      if (container.tagName === 'TBODY') {
        var tr = document.createElement('tr');
        tr.className = 'drop-line-row';
        var td = document.createElement('td');
        td.setAttribute('colspan', '6');
        tr.appendChild(td);
        return tr;
      }
      var d = document.createElement('div');
      d.className = 'drop-line';
      return d;
    }
    function clearDropLine() {
      container.querySelectorAll('.drop-line, .drop-line-row').forEach(function (el) { el.remove(); });
    }
    container.addEventListener('dragover', function (e) {
      if (!dragging) return;
      e.preventDefault();
      clearDropLine();
      var siblings = Array.prototype.slice.call(
        container.querySelectorAll(itemSel + ':not(.is-dragging)')
      );
      var after = siblings.find(function (sib) {
        var box = sib.getBoundingClientRect();
        return e.clientY < box.top + box.height / 2;
      });
      var line = makeDropLine();
      if (after) container.insertBefore(line, after);
      else       container.appendChild(line);
    });
    container.addEventListener('drop', function (e) {
      e.preventDefault();
      if (!dragging) return;
      var line = container.querySelector('.drop-line, .drop-line-row');
      if (line) {
        container.insertBefore(dragging, line);
        line.remove();
      } else {
        container.appendChild(dragging);
      }
    });
    container.addEventListener('dragleave', function (e) {
      // Only clear when leaving the container entirely (not when moving
      // between children — dragleave fires on every child boundary).
      if (e.target === container) clearDropLine();
    });
  }

  /**
   * Rewrite all sections[N][...] input names so they're contiguous and
   * match DOM order. Called after add/delete/reorder.
   */
  function reindexSections() {
    if (!stack) return;
    Array.prototype.forEach.call(stack.children, function (sec, idx) {
      sec.querySelectorAll('input[name^="sections["], select[name^="sections["], textarea[name^="sections["]').forEach(function (el) {
        var n = el.getAttribute('name');
        el.setAttribute('name', n.replace(/^sections\[[^\]]*\]/, 'sections[' + idx + ']'));
      });
    });
  }

  function updateSectionCount() {
    var c = document.getElementById('sec-count');
    if (c && stack) c.textContent = String(stack.children.length);
  }

  /**
   * Rebuild a Filtered section's Category pill rails (Content Query
   * Categories + Visible Category Toggles). One rail per [data-cat-rail].
   * Categories are grouped per type with visible separation, sourced from
   * window.CMS_CATEGORIES_BY_TYPE (emitted by the view).
   */
  var CAT_TYPE_ORDER = ['article', 'journal', 'live-session', 'experiment'];

  function renderCatRail(rail) {
    if (!rail) return;
    var sec = rail.closest('[data-section]');
    var name = rail.getAttribute('data-cat-rail-name') || '';
    var selAttr = rail.getAttribute('data-cat-rail-selected') || '';
    var selected = selAttr ? selAttr.split(',').filter(Boolean) : [];

    // Read this section's active Content Query types. Empty = show all.
    var types = sec
      ? Array.prototype.map.call(
          sec.querySelectorAll('input[name$="[feed_types][]"]:checked'),
          function (cb) { return cb.value; }
        )
      : [];
    if (types.length === 0) types = CAT_TYPE_ORDER.slice();

    var byType = window.CMS_CATEGORIES_BY_TYPE || {};
    rail.innerHTML = '';
    types.forEach(function (t) {
      var cats = byType[t] || [];
      if (cats.length === 0) return;
      var group = document.createElement('div');
      group.className = 'filter-group cat-group';
      cats.forEach(function (cat) {
        var on = selected.indexOf(cat.slug) !== -1;
        var lab = document.createElement('label');
        lab.className = 'filter-pill' + (on ? ' active' : '');
        lab.style.cursor = 'pointer';
        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.name = name;
        cb.value = cat.slug;
        cb.checked = on;
        cb.style.display = 'none';
        lab.appendChild(cb);
        lab.appendChild(document.createTextNode(cat.label));
        group.appendChild(lab);
      });
      rail.appendChild(group);
    });
  }

  function refreshCatRailsIn(scope) {
    if (!scope) return;
    scope.querySelectorAll('[data-cat-rail]').forEach(function (rail) {
      var checked = Array.prototype.map.call(
        rail.querySelectorAll('input[type=checkbox]:checked'),
        function (cb) { return cb.value; }
      );
      if (checked.length) rail.setAttribute('data-cat-rail-selected', checked.join(','));
      renderCatRail(rail);
    });
  }

  // Initial render of all rails on the page.
  document.querySelectorAll('[data-cat-rail]').forEach(renderCatRail);

  // Initial pass on every Hero section so its field visibility lines
  // up with the saved layout + image_mode (covers both server-rendered
  // sections and cloned templates on Add).
  document.querySelectorAll('[data-section][data-section-type="hero"]').forEach(syncHeroFieldVisibility);

  // When any Content Query Types pill changes, refresh that section's rails.
  form.addEventListener('change', function (e) {
    var cb = e.target;
    if (!cb || cb.tagName !== 'INPUT' || cb.type !== 'checkbox') return;
    if (!cb.name || cb.name.indexOf('[feed_types][]') === -1) return;
    refreshCatRailsIn(cb.closest('[data-section]'));
  });

  function rebuildCategoriesFor(sec) { refreshCatRailsIn(sec); }

  /**
   * Update the collapsed-header summary line based on the section's
   * current config. Best-effort cosmetic only — saves a reload.
   */
  function updateSummaryFor(sec) {
    if (!sec) return;
    var sumEl = sec.querySelector('[data-section-summary]');
    if (!sumEl) return;
    var type = sec.getAttribute('data-section-type');
    if (type === 'hero') {
      var sel = sec.querySelector('select[name$="[item_ids]"]');
      sumEl.textContent = (sel && sel.value) ? '1 item picked' : 'no pick yet';
    } else if (type === 'curated') {
      var hidden = sec.querySelector('[data-posts-ids]');
      var n = (hidden && hidden.value) ? hidden.value.split(',').filter(Boolean).length : 0;
      var rows = (sec.querySelector('input[name$="[grid_rows]"]') || {}).value || 'all';
      var fmt  = (sec.querySelector('input[name$="[display_format]"]') || {}).value || 'grid';
      sumEl.textContent = n + (n === 1 ? ' pick' : ' picks') + ' · ' + cap(fmt) + (fmt === 'grid' ? ' · ' + rows + ' rows' : '');
    } else if (type === 'feed') {
      var typesArr = Array.prototype.map.call(
        sec.querySelectorAll('input[name$="[feed_types][]"]:checked'),
        function (cb) { return cb.value; }
      );
      var labels = { article:'Articles', journal:'Journals', 'live-session':'Live Sessions', experiment:'Experiments' };
      var ts = typesArr.length ? typesArr.map(function (t) { return labels[t] || t; }).join(' + ') : 'All types';
      var fsort = (sec.querySelector('input[name$="[feed_sort]"]') || {}).value || 'newest';
      var fmt2  = (sec.querySelector('input[name$="[display_format]"]') || {}).value || 'grid';
      var rows2 = (sec.querySelector('input[name$="[grid_rows]"]') || {}).value || 'all';
      sumEl.textContent = ts + ' · ' + cap(fsort) + ' · ' + cap(fmt2) + (fmt2 === 'grid' ? ' · ' + rows2 + ' rows' : '');
    }
    // Also keep the displayed section name in sync with its title input.
    // Strip any <em>…</em> wrappers so the CMS header reads clean.
    var nameEl = sec.querySelector('[data-section-name]');
    var titleInput = sec.querySelector('input[name$="[title]"]');
    if (nameEl && titleInput) {
      var v = titleInput.value.replace(/<\/?em[^>]*>/gi, '').trim();
      nameEl.textContent = v !== '' ? v : '(no title)';
    }
  }

  // Wire title-input → live update of header name.
  form.addEventListener('input', function (e) {
    if (e.target && e.target.matches('input[name$="[title]"]')) {
      updateSummaryFor(e.target.closest('[data-section]'));
    }
  });

  function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
    });
  }

  // Wire the Filtered visitor-filter toggle to show/hide the detail block.
  form.addEventListener('change', function (e) {
    if (e.target && e.target.matches('[data-filter-toggle]')) {
      var sec = e.target.closest('[data-section]');
      var detail = sec ? sec.querySelector('[data-filter-detail]') : null;
      if (detail) detail.style.display = e.target.checked ? '' : 'none';
    }
  });

  // ── Basic Listing: Show Filters toggle + Types/Categories choice ────
  // Wires the page-level filter UI (NOT the per-section Filtered card).
  // On change we recompute the hidden `filter_mode` from toggle + pill,
  // and show/hide the detail block.
  (function () {
    var toggle = document.getElementById('bl-filter-toggle');
    var detail = document.getElementById('bl-filter-detail');
    var hidden = document.getElementById('bl-filter-mode');
    var byGroup = document.querySelector('[data-bl-by]');
    if (!toggle || !hidden) return;

    function syncMode() {
      if (!toggle.checked) { hidden.value = 'none'; return; }
      var active = byGroup ? byGroup.querySelector('.filter-pill.active') : null;
      var v = active ? active.getAttribute('data-bl-by-value') : 'categories';
      hidden.value = (v === 'types' || v === 'categories') ? v : 'categories';
    }

    toggle.addEventListener('change', function () {
      if (detail) detail.style.display = toggle.checked ? '' : 'none';
      syncMode();
    });

    if (byGroup) {
      byGroup.addEventListener('click', function (e) {
        var pill = e.target.closest('.filter-pill[data-bl-by-value]');
        if (!pill) return;
        byGroup.querySelectorAll('.filter-pill').forEach(function (p) { p.classList.remove('active'); });
        pill.classList.add('active');
        syncMode();
      });
    }
  })();

  // ── Hero Side-image upload ──────────────────────────────────────────
  // Each Hero section has an [data-hero-img-upload] file input next to
  // the URL text field. On change, POST the file to the upload endpoint
  // and stuff the returned URL into the text input.
  form.addEventListener('change', function (e) {
    var input = e.target;
    if (!input || !input.matches('[data-hero-img-upload]')) return;
    var file = input.files && input.files[0];
    if (!file) return;
    var wrap   = input.closest('[data-hero-image-url]');
    var urlIn  = wrap ? wrap.querySelector('[data-hero-img-url-input]') : null;
    var status = wrap ? wrap.querySelector('[data-hero-img-status]') : null;
    if (status) { status.style.display = ''; status.textContent = 'Uploading…'; }
    var csrf = (form.querySelector('input[name="csrf_token"]') || {}).value || '';
    var fd = new FormData();
    fd.append('image', file);
    fd.append('csrf_token', csrf);
    fetch('/cms/indexes/upload-image', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.ok && j.url) {
          if (urlIn) {
            urlIn.value = j.url;
            urlIn.dispatchEvent(new Event('input', { bubbles: true }));
          }
          if (status) status.textContent = 'Uploaded.';
          setTimeout(function () { if (status) status.style.display = 'none'; }, 1500);
        } else {
          if (status) status.textContent = 'Upload failed: ' + ((j && j.error) || 'unknown');
        }
      })
      .catch(function (err) {
        if (status) status.textContent = 'Upload failed: ' + err.message;
      });
    input.value = '';
  });

  // ── Featured-list (legacy Basic Listing) — keep the existing behaviour.
  (function () {
    var list = document.getElementById('featured-list');
    if (!list) return;
    var hidden = document.getElementById('featured-ids-input');
    var addSel = document.getElementById('featured-add');
    var addBtn2 = document.getElementById('featured-add-btn');
    function ser() {
      var ids = [];
      list.querySelectorAll('.rowform-row[data-id]').forEach(function (el) {
        ids.push(el.getAttribute('data-id'));
      });
      if (hidden) hidden.value = ids.join(',');
    }
    attachDragReorder(list, '.rowform-row[data-id]', '.cms-grip', ser);
    list.addEventListener('click', function (e) {
      var btn = e.target.closest('.featured-remove');
      if (!btn) return;
      var row = btn.closest('.rowform-row');
      if (row) row.remove();
      ser();
    });
    if (addBtn2 && addSel) {
      addBtn2.addEventListener('click', function () {
        var id = addSel.value;
        if (!id || id === '0') return;
        if (list.querySelector('.rowform-row[data-id="' + id + '"]')) {
          addSel.value = ''; return;
        }
        var opt = addSel.options[addSel.selectedIndex];
        var label = opt ? (opt.getAttribute('data-label') || opt.textContent) : id;
        var row = document.createElement('div');
        row.className = 'rowform-row';
        row.draggable = true;
        row.setAttribute('data-id', id);
        row.innerHTML = '<span class="cms-grip">⋮⋮</span><span style="flex:1"></span>'
                      + '<button type="button" class="featured-remove btn-icon btn-icon-danger" title="Remove">'
                      + '<svg viewBox="0 0 14 14" fill="none"><path d="M3 4h8M5.5 4V2.5h3V4M4 4l0.5 8h5l0.5-8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                      + '</button>';
        row.children[1].textContent = label;
        list.appendChild(row);
        addSel.value = '';
        ser();
      });
    }
  })();

})();
