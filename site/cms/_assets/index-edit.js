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
    // Find the sibling hidden input.
    var hidden = grp.parentElement.querySelector('input[type="hidden"]');
    if (hidden) {
      hidden.value = pill.getAttribute('data-pill-value');
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
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
  // Flip the pill + the hidden layout input, dispatch a change so the
  // dirty-flip wiring marks the Save button. Server re-renders the
  // appropriate form on the next Save (the two layouts use materially
  // different markup, so we can't swap them client-side).
  document.querySelectorAll('[data-layout-toggle] .filter-pill[data-layout]').forEach(function (p) {
    p.addEventListener('click', function () {
      var newLayout = p.getAttribute('data-layout');
      var hidden = document.getElementById('layout-input');
      if (!hidden) return;
      if (hidden.value === newLayout) return;
      hidden.value = newLayout;
      p.parentElement.querySelectorAll('.filter-pill').forEach(function (x) { x.classList.remove('active'); });
      p.classList.add('active');
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
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
   * Categories + Show Filter Category Filters) when the Types selection
   * changes. For v1 we just toggle .active class — the per-type
   * category fetch is server-side, so on full refresh the right pills
   * render. Simpler than fetching via AJAX. Future: fetch on change.
   */
  function rebuildCategoriesFor(sec) {
    // Intentionally minimal for v1 — server-rendered on next reload.
  }

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
    var nameEl = sec.querySelector('[data-section-name]');
    var titleInput = sec.querySelector('input[name$="[title]"]');
    if (nameEl && titleInput) {
      var v = titleInput.value.trim();
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
