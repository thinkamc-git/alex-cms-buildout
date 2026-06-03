/**
 * preview-tab-guard.js — client-side Edit/Preview tab controller +
 * dirty-form beforeunload guard.
 *
 * NEW BEHAVIOUR (Phase 20.2 redesign):
 *
 *  1. Tabs are pure client-side toggles. Both Edit and Preview panels are
 *     rendered server-side; this script flips their visibility and updates
 *     history.state without a page reload. Form values are preserved.
 *
 *  2. When the user activates the Preview tab, the script POSTs the current
 *     form values to /cms/post/preview-form (or /cms/pages/preview-form for
 *     pages) targeting the iframe — so the preview re-renders against what
 *     the user just typed, not the saved DB version. (Pass B.)
 *
 *  3. The save button (.btn-pri at the bottom of the form) flips from
 *     .btn-ghost → .btn-pri on the first input/change inside the form.
 *     A single save commits both Edit-side form values and Preview-side
 *     inline edits (Q2) — Preview edits sync back to the form via
 *     postMessage before save.
 *
 *  4. A beforeunload prompt fires only when the user navigates AWAY from
 *     the edit page with unsaved changes (tab close, sidebar link, back
 *     button). Tab switching between Edit/Preview never prompts.
 */
(function () {
  'use strict';

  // ── Tab containers ──────────────────────────────────────────────────
  // Edit panel = container with [data-tab-panel="edit"]; Preview panel
  // = [data-tab-panel="preview"]; tab links = [data-tab-target] with
  // values "edit" or "preview". Views opt in by adding these attributes;
  // if absent, this controller is inert.
  var tabLinks    = Array.prototype.slice.call(document.querySelectorAll('[data-tab-target]'));
  var panels      = Array.prototype.slice.call(document.querySelectorAll('[data-tab-panel]'));
  var iframe      = document.querySelector('[data-preview-iframe]');
  var previewEp   = iframe ? iframe.getAttribute('data-preview-endpoint') : '';
  var mainForm    = document.querySelector('[data-preview-source-form]');
  var previewPan  = document.querySelector('[data-tab-panel="preview"]');

  // Inject a Save bar inside the preview panel so the bar persists on
  // both tabs. The clone clicks the real Save button (which lives inside
  // the form on the Edit tab), so we don't need any form= plumbing.
  if (previewPan && mainForm) injectPreviewSaveBar();

  if (tabLinks.length && panels.length) {
    tabLinks.forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var target = a.getAttribute('data-tab-target');
        activate(target);
      });
    });
    // Initial activation from the URL (preserves bookmarks + back/forward).
    var urlTab = new URL(location.href).searchParams.get('tab');
    var initial = urlTab && tabLinks.some(function (a) { return a.getAttribute('data-tab-target') === urlTab; })
      ? urlTab
      : (tabLinks[0] && tabLinks[0].getAttribute('data-tab-target'));
    if (initial) activate(initial, /*replaceHistory*/ true);
  }

  function injectPreviewSaveBar() {
    var sourceSave   = mainForm.querySelector('[data-save-btn]');
    // Cancel link from the source form-actions (first .btn-ghost <a> that
    // isn't the View-live link). Falls back to a list-route guess.
    var sourceCancel = mainForm.querySelector('.form-actions a.btn-ghost:not([target])');
    if (!sourceSave) return;

    var bar = document.createElement('div');
    bar.className = 'preview-save-bar';

    var left = document.createElement('div');
    left.className = 'preview-save-bar-left';
    var back = document.createElement('button');
    back.type = 'button';
    back.className = 'btn-ghost';
    back.textContent = '← Back to editing';
    back.addEventListener('click', function () { activate('edit'); });
    left.appendChild(back);

    var right = document.createElement('div');
    right.className = 'preview-save-bar-right';

    if (sourceCancel) {
      var cancel = document.createElement('a');
      cancel.className = 'btn-ghost';
      cancel.href      = sourceCancel.getAttribute('href') || '';
      cancel.textContent = 'Cancel';
      right.appendChild(cancel);
    }

    var save = document.createElement('button');
    save.type = 'button';
    save.className = sourceSave.className; // mirror btn-ghost / btn-pri
    save.setAttribute('data-save-btn', '');
    save.setAttribute('data-preview-save-clone', '');
    save.textContent = sourceSave.textContent;
    save.addEventListener('click', function () { sourceSave.click(); });
    right.appendChild(save);

    bar.appendChild(left);
    bar.appendChild(right);
    previewPan.appendChild(bar);
  }

  function activate(target, replaceHistory) {
    panels.forEach(function (p) {
      var match = p.getAttribute('data-tab-panel') === target;
      p.classList.toggle('is-hidden-tab', !match);
    });
    tabLinks.forEach(function (a) {
      var match = a.getAttribute('data-tab-target') === target;
      a.classList.toggle('active', match);
      a.classList.toggle('is-active', match);
      a.setAttribute('aria-selected', match ? 'true' : 'false');
    });

    // URL update without reload.
    try {
      var url = new URL(location.href);
      url.searchParams.set('tab', target);
      if (replaceHistory) history.replaceState({}, '', url.toString());
      else                history.pushState({}, '', url.toString());
    } catch (_) {}

    // Re-render the preview iframe against current form values.
    if (target === 'preview') refreshPreviewIframe();
  }

  // ── Preview iframe refresh (Edit form values → Preview render) ──────
  function refreshPreviewIframe() {
    if (!iframe || !previewEp || !mainForm) return;
    // Build a one-shot hidden form that POSTs the current main form's
    // values to the preview endpoint, targeting the iframe.
    var staging = document.createElement('form');
    staging.method = 'POST';
    staging.action = previewEp;
    staging.target = iframe.name || (iframe.name = 'preview-iframe-' + Math.floor(Math.random() * 1e9));
    staging.style.display = 'none';
    // Mirror every named input/textarea/select from the main form.
    Array.prototype.forEach.call(mainForm.elements, function (el) {
      if (!el.name) return;
      if (el.type === 'submit' || el.type === 'button') return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        if (!el.checked) return;
      }
      var input = document.createElement('input');
      input.type  = 'hidden';
      input.name  = el.name;
      input.value = el.value;
      staging.appendChild(input);
    });
    document.body.appendChild(staging);
    staging.submit();
    setTimeout(function () { staging.remove(); }, 0);
  }

  // ── Dirty-form tracking + Save button flip + beforeunload ───────────
  var trackedForms = Array.prototype.filter.call(
    document.querySelectorAll('form'),
    function (f) {
      var method = (f.getAttribute('method') || '').toLowerCase();
      if (method !== 'post') return false;
      if (f.classList.contains('inline-delete')) return false;
      if (f.id && /-delete-form$/.test(f.id)) return false;
      return true;
    }
  );
  var dirty = false;
  function flipDirty() {
    if (dirty) return;
    dirty = true;
    // The btn-ghost → btn-pri class flip is handled by the shared
    // dirty-flip module (cms/_assets/dirty-flip.js). This handler now
    // only tracks the dirty flag for the beforeunload guard below.
  }
  // Track form submission so we don't pop a "leaving site" prompt when the
  // navigation is the user's own Save click.
  var submittingNow = false;
  trackedForms.forEach(function (f) {
    f.addEventListener('input',  flipDirty);
    f.addEventListener('change', flipDirty);
    f.addEventListener('submit', function () { submittingNow = true; });
  });

  // ── beforeunload: prompt only when leaving the page, not on tab toggle
  // and not when the user is saving.
  window.addEventListener('beforeunload', function (e) {
    if (submittingNow) return;
    if (!dirty) return;
    // Some browsers ignore custom strings; presence of returnValue is enough.
    e.preventDefault();
    e.returnValue = '';
    return '';
  });

  // ── popstate (back/forward): re-activate the tab from the URL.
  window.addEventListener('popstate', function () {
    var t = new URL(location.href).searchParams.get('tab');
    if (t) activate(t, /*replaceHistory*/ true);
  });
})();
