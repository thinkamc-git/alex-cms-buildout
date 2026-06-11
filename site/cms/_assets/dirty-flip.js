/**
 * dirty-flip.js — single shared module for the Save-button dirty-flip
 * pattern used across editors, page-edit, navigation, and redirects.
 *
 * Behaviour:
 *   1. Find every [data-save-btn] in the document. (Legacy alias:
 *      [data-primary-save] is treated as data-save-btn so existing
 *      buttons keep working until they migrate.)
 *   2. For each button, resolve its owning form:
 *        - if the button has a form="<id>" attribute, use that form;
 *        - otherwise, walk up to the nearest <form>.
 *   3. Bind input/change on every input/textarea/select inside that
 *      form. On the first dirty event, flip the button from .btn-ghost
 *      to .btn-pri. (Idempotent — repeated dirties are a no-op.)
 *   4. On click of a dirty button, briefly show "Saved", disable the
 *      button, and submit the form via .submit() after a short pulse.
 *      Clean-state buttons fall through to the browser's normal submit
 *      behaviour (no preventDefault).
 *
 * Idempotency:
 *   The module marks each button with data-dirty-flip-bound so re-running
 *   the script (or multiple inclusions) won't double-wire handlers.
 *
 * Per-row pattern compatibility (navigation, redirects):
 *   When the button uses form="row-N" binding (cross-form), the module
 *   reads the form id from the attribute. Programmatic .requestSubmit()
 *   does NOT carry the submit button's name/value, so any callers that
 *   rely on a server-side `action` discriminator must inject it as a
 *   hidden input before invoking submit. The redirects view did this
 *   manually; we preserve that responsibility by checking for a hidden
 *   input named "action" and synthesizing one with value="update" if
 *   missing AND the button itself has a name="action" + value="<x>" pair.
 *
 * Submit dispatch:
 *   We invoke form.requestSubmit() rather than form.submit() so the
 *   form's `submit` event fires. preview-tab-guard.js listens for that
 *   event to set submittingNow=true and suppress its beforeunload
 *   "changes may not be saved" warning — the native form.submit() does
 *   not fire the event, so without requestSubmit the user gets a false
 *   warning every time they click Save.
 *
 * The module self-initialises on DOMContentLoaded and is safe to load on
 * any page — it's a no-op when no [data-save-btn] elements exist.
 */
(function () {
  'use strict';

  function findForm(btn) {
    var formId = btn.getAttribute('form');
    if (formId) {
      var f = document.getElementById(formId);
      if (f) return f;
    }
    return btn.closest('form');
  }

  function bind(btn) {
    if (btn.hasAttribute('data-dirty-flip-bound')) return;
    btn.setAttribute('data-dirty-flip-bound', '');

    var form = findForm(btn);
    if (!form) return;

    // Legacy: ensure [data-save-btn] is set so selectors that only look
    // for the canonical attribute still find this button.
    if (!btn.hasAttribute('data-save-btn')) {
      btn.setAttribute('data-save-btn', '');
    }

    function flip() {
      if (btn.classList.contains('btn-pri')) return; // already dirty
      // Batch 2 button canonical — Save buttons now start life as .btn-sec
      // (the prior .btn-ghost was an alias for the same look). Strip both
      // before adding .btn-pri so the cascade unambiguously paints primary.
      btn.classList.remove('btn-ghost');
      btn.classList.remove('btn-sec');
      btn.classList.add('btn-pri');
    }

    // Use form.elements (not querySelectorAll) so inputs cross-bound via
    // the HTML5 form="<id>" attribute also wire up. This is what makes
    // categories' Save button respond to changes in the row's inputs
    // (the form is hidden; the inputs sit in the table row).
    var elements = form.elements;
    for (var i = 0; i < elements.length; i++) {
      var el = elements[i];
      var tag = el.tagName;
      if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') continue;
      var evt = (tag === 'SELECT' || el.type === 'hidden') ? 'change' : 'input';
      el.addEventListener(evt, flip);
    }

    // Also bind a delegated form-level listener so inputs added AFTER
    // init (e.g. dynamically cloned section cards in index-edit.js) and
    // synthetic change events dispatched on the form itself still flip.
    form.addEventListener('input',  flip);
    form.addEventListener('change', flip);

    btn.addEventListener('click', function (e) {
      if (!btn.classList.contains('btn-pri')) return; // nothing to save
      e.preventDefault();

      // Validate BEFORE faking any submitting state. If the form is invalid
      // (e.g. a too-short password), surface the browser's native validation
      // UI and bail — never disable the button or claim a save for a submit
      // that won't happen. (This previously flashed "Saved", then the blocked
      // submit popped a validation message and prompted the password manager.)
      if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
        if (typeof form.reportValidity === 'function') form.reportValidity();
        return;
      }

      var label = btn.textContent;
      btn.textContent = 'Saving…';  // honest: the POST is in flight — the
      btn.disabled = true;          // reloaded page shows the real result.

      // Programmatic .submit() drops the submit button's name/value pair.
      // If the button carries name="action", inject a hidden input so the
      // server-side router still sees the action discriminator.
      var name  = btn.getAttribute('name');
      var value = btn.getAttribute('value');
      if (name && value !== null) {
        var existing = form.querySelector(
          'input[name="' + name + '"][data-dirty-flip-injected]'
        );
        if (!existing) {
          var inp = document.createElement('input');
          inp.type  = 'hidden';
          inp.name  = name;
          inp.value = value;
          inp.setAttribute('data-dirty-flip-injected', '');
          form.appendChild(inp);
        }
      }

      setTimeout(function () {
        // requestSubmit() fires the form's `submit` event (form.submit()
        // does not, per spec). preview-tab-guard.js listens for that event
        // to clear its beforeunload guard — without this, every Save triggers
        // a spurious "changes may not be saved" warning.
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          // Fallback for very old browsers — emit the event manually first.
          form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
          form.submit();
        }
      }, 300);
    });
  }

  function init() {
    var btns = document.querySelectorAll(
      '[data-save-btn], [data-primary-save]'
    );
    btns.forEach(bind);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
