/* cms/_assets/body-mode-toggle.js — shared RTF / HTML body-source toggle.
 *
 * Wires every [data-body-source-block] on the page: clicking one of the radio
 * options toggles the matching [data-body-panel] element and updates the
 * .is-active class on .body-source-option labels. Panels are hidden via the
 * hidden attribute (so the DOM stays intact and Tiptap / file inputs keep
 * state across toggles).
 *
 * Extracted Batch 2 #23 — the same IIFE used to live inline in article-edit
 * and experiment-edit.
 */
(function () {
  'use strict';

  function wireBlock(root) {
    if (!root || root.__bodyModeWired) return;
    root.__bodyModeWired = true;
    var radios  = root.querySelectorAll('input[name="body_mode"]');
    var panels  = root.querySelectorAll('[data-body-panel]');
    var options = root.querySelectorAll('.body-source-option');
    function activate(mode) {
      for (var i = 0; i < panels.length; i++) {
        panels[i].hidden = (panels[i].getAttribute('data-body-panel') !== mode);
      }
      for (var j = 0; j < options.length; j++) {
        var input = options[j].querySelector('input');
        options[j].classList.toggle('is-active', input && input.value === mode);
      }
    }
    for (var k = 0; k < radios.length; k++) {
      (function (r) {
        r.addEventListener('change', function () { activate(r.value); });
      })(radios[k]);
    }
  }

  function wire() {
    var blocks = document.querySelectorAll('[data-body-source-block]');
    for (var i = 0; i < blocks.length; i++) wireBlock(blocks[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wire);
  } else {
    wire();
  }
})();
