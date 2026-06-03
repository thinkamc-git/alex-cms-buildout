/* cms/_assets/confirm.js — shared confirm-on-submit / confirm-on-click guard.
 *
 * Auto-wires on DOMContentLoaded.
 *   <form data-confirm="…">     intercepts the submit event.
 *   <button data-confirm="…">   intercepts the click event (useful for buttons
 *                                that submit via form= attribute).
 * If the user cancels the dialog the original event's default action is
 * suppressed. A trivial fallback message is used when the attribute value is
 * empty.
 *
 * Extracted Batch 2 #48/#49 — the same window.confirm() guard previously
 * lived inline in each of the 13+ confirm sites.
 */
(function () {
  'use strict';

  function ask(el) {
    var msg = el.getAttribute('data-confirm') || 'Are you sure?';
    return window.confirm(msg);
  }

  function wire() {
    var forms = document.querySelectorAll('form[data-confirm]');
    for (var i = 0; i < forms.length; i++) {
      var f = forms[i];
      if (f.__confirmWired) continue;
      f.__confirmWired = true;
      f.addEventListener('submit', function (ev) {
        if (!ask(this)) ev.preventDefault();
      });
    }
    var btns = document.querySelectorAll('button[data-confirm], input[type="submit"][data-confirm], a[data-confirm]');
    for (var j = 0; j < btns.length; j++) {
      var b = btns[j];
      if (b.__confirmWired) continue;
      b.__confirmWired = true;
      b.addEventListener('click', function (ev) {
        if (!ask(this)) ev.preventDefault();
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wire);
  } else {
    wire();
  }
})();
