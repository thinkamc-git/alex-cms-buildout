/**
 * cms/_assets/dragdrop.js — drag-and-drop reordering for Pipeline + Ideation.
 *
 * Wiring contract (matches the markup in pipeline.php and ideation.php):
 *
 *   <div class="kanban-board"
 *        data-dnd-mode="pipeline|ideation"
 *        data-dnd-endpoint="/cms/articles/reorder-pipeline"
 *        data-csrf-token="...">
 *     <div class="kanban-lane" data-key="<stage-or-type>">
 *       <div class="lane-cards">
 *         <a class="kcard" data-id="123" draggable="true">…</a>
 *         …
 *       </div>
 *     </div>
 *   </div>
 *
 *   - data-key on each .kanban-lane is the value posted as `stage`
 *     (pipeline mode) or `type` (ideation mode). "none" means NULL type.
 *   - data-dnd-mode picks the AJAX shape (key name + cross-lane allowed
 *     only in ideation mode).
 *
 * UX:
 *   - Drag a .kcard → opacity drops and a placeholder shows insertion point.
 *   - On drop, DOM is reordered immediately and AJAX is fired.
 *   - On non-2xx, the DOM reverts and the error is alerted.
 *   - In pipeline mode, cross-lane drops are rejected client-side (stage
 *     changes go through the editor's Advance flow).
 *
 * Browser support: HTML5 native drag/drop, which is solid on desktop —
 * the CMS is desktop-only per CLAUDE.md.
 */

(function () {
  'use strict';

  function init(board) {
    const mode     = board.getAttribute('data-dnd-mode') || 'pipeline';
    const endpoint = board.getAttribute('data-dnd-endpoint') || '';
    const csrf     = board.getAttribute('data-csrf-token') || '';
    if (!endpoint || !csrf) return;

    const laneKeyName = mode === 'ideation' ? 'type' : 'stage';
    const allowCrossLane = mode === 'ideation';

    /** Pre-drag snapshot used to revert on failure. */
    let snapshot = null;

    function takeSnapshot() {
      snapshot = [];
      board.querySelectorAll('.kanban-lane').forEach(function (lane) {
        const key = lane.getAttribute('data-key');
        const cards = Array.from(lane.querySelectorAll('.kcard'));
        snapshot.push({ key: key, ids: cards.map(c => c.getAttribute('data-id')), cards: cards });
      });
    }

    function revertFromSnapshot() {
      if (!snapshot) return;
      snapshot.forEach(function (laneSnap) {
        const lane  = board.querySelector('.kanban-lane[data-key="' + cssEscape(laneSnap.key) + '"]');
        if (!lane) return;
        const cards = lane.querySelector('.lane-cards');
        if (!cards) return;
        // Remove empty placeholders before re-inserting cards.
        cards.querySelectorAll('.idea-lane-empty').forEach(n => n.remove());
        laneSnap.cards.forEach(c => cards.appendChild(c));
        refreshEmptyState(lane);
      });
    }

    function refreshEmptyState(lane) {
      const cards = lane.querySelector('.lane-cards');
      if (!cards) return;
      const hasCards = cards.querySelector('.kcard') !== null;
      let empty = cards.querySelector('.idea-lane-empty');
      if (hasCards && empty) empty.remove();
      if (!hasCards && !empty) {
        empty = document.createElement('div');
        empty.className = 'idea-lane-empty';
        empty.textContent = 'Drop here';
        cards.appendChild(empty);
      }
    }

    // ── Drag lifecycle ────────────────────────────────────────────────
    board.addEventListener('dragstart', function (e) {
      const card = e.target.closest('.kcard');
      if (!card) return;
      takeSnapshot();
      card.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.getAttribute('data-id') || '');
    });

    board.addEventListener('dragend', function (e) {
      const card = e.target.closest('.kcard');
      if (card) card.classList.remove('dragging');
      board.querySelectorAll('.kanban-lane').forEach(l => l.classList.remove('drag-over'));
    });

    board.addEventListener('dragover', function (e) {
      const lane = e.target.closest('.kanban-lane');
      if (!lane) return;
      const dragging = board.querySelector('.kcard.dragging');
      if (!dragging) return;

      // Pipeline mode forbids cross-lane drops.
      if (!allowCrossLane) {
        const sourceLane = dragging.closest('.kanban-lane');
        if (sourceLane !== lane) return;
      }

      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      board.querySelectorAll('.kanban-lane.drag-over').forEach(l => l.classList.remove('drag-over'));
      lane.classList.add('drag-over');

      // Live placement: find which card to insert above based on cursor Y.
      const cardsHost = lane.querySelector('.lane-cards');
      if (!cardsHost) return;
      const target = findInsertTarget(cardsHost, e.clientY);
      if (target == null) {
        cardsHost.appendChild(dragging);
      } else if (target !== dragging) {
        cardsHost.insertBefore(dragging, target);
      }
      // Remove "empty" placeholder from the lane we just dragged into.
      cardsHost.querySelectorAll('.idea-lane-empty').forEach(n => n.remove());
    });

    board.addEventListener('drop', function (e) {
      const lane = e.target.closest('.kanban-lane');
      if (!lane) return;
      e.preventDefault();
      lane.classList.remove('drag-over');

      const cardsHost = lane.querySelector('.lane-cards');
      if (!cardsHost) return;
      const newIds = Array.from(cardsHost.querySelectorAll('.kcard'))
                          .map(c => c.getAttribute('data-id'));
      const laneKey = lane.getAttribute('data-key') || '';

      // Persist target-lane order.
      persist(laneKey, newIds).catch(function (err) {
        revertFromSnapshot();
        window.alert('Drag failed: ' + err.message);
      }).then(function (ok) {
        if (!ok) return;
        // Cross-lane in ideation mode: also persist the source lane that
        // lost a card (so its remaining cards get re-numbered 1..N-1).
        if (allowCrossLane && snapshot) {
          const sourceSnap = snapshot.find(s => (s.ids || []).indexOf(newIds.find(id => id) || '') === -1 ? false :
                                               (s.ids || []).length !== newIds.length || laneKey !== s.key);
          // Simpler: re-persist every lane whose current id list differs from snapshot.
          board.querySelectorAll('.kanban-lane').forEach(function (otherLane) {
            if (otherLane === lane) return;
            const otherKey = otherLane.getAttribute('data-key') || '';
            const cardsNow = Array.from(otherLane.querySelectorAll('.kcard')).map(c => c.getAttribute('data-id'));
            const snap = (snapshot || []).find(s => s.key === otherKey);
            if (!snap) return;
            const oldIds = snap.ids;
            const changed = cardsNow.length !== oldIds.length ||
                            cardsNow.some((id, i) => id !== oldIds[i]);
            if (changed) {
              persist(otherKey, cardsNow).catch(function () {/* best-effort */});
            }
            refreshEmptyState(otherLane);
          });
        }
        refreshEmptyState(lane);
        snapshot = null;
      });
    });

    /**
     * Send the new lane order to the server. Returns true on success.
     */
    function persist(laneKey, ids) {
      const params = new URLSearchParams();
      params.append('csrf_token', csrf);
      params.append(laneKeyName, laneKey);
      ids.forEach(id => params.append('ids[]', id));
      return fetch(endpoint, {
        method:      'POST',
        headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body:        params.toString(),
      }).then(function (r) {
        return r.json().then(function (body) {
          if (!r.ok || !body.ok) {
            throw new Error(body.error || ('HTTP ' + r.status));
          }
          return true;
        });
      });
    }
  }

  /**
   * Given a horizontal lane's `.lane-cards` host and a cursor Y, return
   * the .kcard that the dragging card should be inserted BEFORE, or null
   * to mean "append to end."
   */
  function findInsertTarget(cardsHost, clientY) {
    const cards = Array.from(cardsHost.querySelectorAll('.kcard:not(.dragging)'));
    for (let i = 0; i < cards.length; i++) {
      const r = cards[i].getBoundingClientRect();
      if (clientY < r.top + r.height / 2) return cards[i];
    }
    return null;
  }

  /** Minimal CSS.escape polyfill — DreamHost may serve older browsers. */
  function cssEscape(s) {
    return String(s).replace(/[^a-zA-Z0-9_-]/g, function (ch) {
      return '\\' + ch;
    });
  }

  function bootAll() {
    document.querySelectorAll('.kanban-board[data-dnd-mode]').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootAll);
  } else {
    bootAll();
  }
})();
