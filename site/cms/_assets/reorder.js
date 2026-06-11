/**
 * cms/_assets/reorder.js — shared drag-to-reorder with a single drop-line
 * indicator. One consistent insertion line (`.drop-line`, 2px primary) is
 * shown in the gap where the dragged item will land — the same visual the
 * Index-section editor already used, now the CMS-wide standard (replaces the
 * older "highlight the top/bottom edge of a card" approach).
 *
 * Usage:
 *   CmsReorder.wire({
 *     container:    listEl,               // the element that holds the rows
 *     itemSelector: '.rowform-row',       // draggable rows (must have draggable="true" + data-id)
 *     tailSelector: '.rowform-add-row',   // optional: keep the line/row before this trailing affordance
 *     onDrop: function (info) {           // info = { orderedIds:[…], item, revert() }
 *       // persist info.orderedIds; call info.revert() to restore order on failure
 *     },
 *   });
 *
 * The helper owns the indicator + DOM move only. Each caller supplies its own
 * persistence + feedback in onDrop (so the per-surface AJAX shape, renumber,
 * and save-pulse stay where they belong).
 */
(function () {
  'use strict';

  function wire(opts) {
    var container = opts.container;
    var itemSel   = opts.itemSelector;
    var tailSel   = opts.tailSelector || null;
    if (!container || !itemSel) return;

    var dragging = null;
    var snapshot = null;

    function clearLine() {
      container.querySelectorAll('.drop-line').forEach(function (el) { el.remove(); });
    }
    function tailNode() { return tailSel ? container.querySelector(tailSel) : null; }
    function placeAtEnd(node) {
      var tail = tailNode();
      if (tail) container.insertBefore(node, tail);
      else container.appendChild(node);
    }
    function items() {
      return Array.prototype.slice.call(container.querySelectorAll(itemSel + '[data-id]'));
    }
    function orderedIds() {
      return items().map(function (el) { return el.getAttribute('data-id'); });
    }

    container.addEventListener('dragstart', function (e) {
      var item = e.target.closest(itemSel);
      if (!item || !container.contains(item)) return;
      dragging = item;
      snapshot = items();                       // pre-drag order, for revert
      item.classList.add('is-dragging');
      if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragend', function () {
      if (dragging) dragging.classList.remove('is-dragging');
      clearLine();
      dragging = null;
    });

    container.addEventListener('dragover', function (e) {
      if (!dragging) return;
      e.preventDefault();
      if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
      clearLine();
      var sibs = Array.prototype.slice.call(
        container.querySelectorAll(itemSel + ':not(.is-dragging)')
      );
      var after = sibs.find(function (s) {
        var b = s.getBoundingClientRect();
        return e.clientY < b.top + b.height / 2;
      });
      var line = document.createElement('div');
      line.className = 'drop-line';
      if (after) container.insertBefore(line, after);
      else placeAtEnd(line);
    });

    container.addEventListener('dragleave', function (e) {
      if (!container.contains(e.relatedTarget)) clearLine();
    });

    container.addEventListener('drop', function (e) {
      e.preventDefault();
      if (!dragging) return;
      var line = container.querySelector('.drop-line');
      if (line) { container.insertBefore(dragging, line); line.remove(); }
      clearLine();
      var moved = dragging;
      var snap  = snapshot;
      dragging = null;
      if (typeof opts.onDrop === 'function') {
        opts.onDrop({
          orderedIds: orderedIds(),
          item: moved,
          revert: function () {
            if (snap) snap.forEach(function (el) { placeAtEnd(el); });
          },
        });
      }
    });
  }

  window.CmsReorder = { wire: wire };
})();
