/* cms/_assets/row-click.js — shared whole-row navigation for list-table rows
 *
 * Auto-wires on DOMContentLoaded. Any <tr class="row-clickable" data-row-href="…">
 * navigates to that href on click — except when the click originates inside a
 * .cell-actions cluster or on a focusable control (a / button / form / input /
 * label / select), so per-row Edit / Delete / Live ↗ buttons keep their own
 * behavior.
 *
 * Extracted Batch 2 #52 — previously this same loop lived inline in each of
 * the 7 list views (articles, journals, live-sessions, experiments, indexes,
 * pages, plus the inline-table form rows). Loaded by partials/table.php.
 */
(function () {
  'use strict';

  function wire() {
    var rows = document.querySelectorAll('tr.row-clickable[data-row-href]');
    for (var i = 0; i < rows.length; i++) {
      var tr = rows[i];
      if (tr.__rowClickWired) continue;
      tr.__rowClickWired = true;
      tr.addEventListener('click', function (ev) {
        if (ev.target.closest('.cell-actions, a, button, form, input, label, select, textarea')) return;
        var href = this.getAttribute('data-row-href');
        if (href) window.location.href = href;
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wire);
  } else {
    wire();
  }
})();
