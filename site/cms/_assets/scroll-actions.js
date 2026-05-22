/* scroll-actions.js — shy-hide for .form-actions-sticky.
 *
 * Toggles .is-hidden on the sticky save bar based on the user's scroll
 * direction inside .content-area (the edit-view scroll container). Scroll
 * down → bar slides off-screen; scroll up → bar comes back.
 *
 * Loaded from article-edit, journal-edit, live-session-edit. Auto-init on
 * DOMContentLoaded; no-op if either element is missing.
 */
(function () {
  function init() {
    var bar      = document.querySelector('.form-actions-sticky');
    var scroller = document.querySelector('.content-area');
    if (!bar || !scroller) return;

    var lastTop   = scroller.scrollTop;
    var THRESHOLD = 8; // ignore jitter under this px delta

    scroller.addEventListener('scroll', function () {
      var top   = scroller.scrollTop;
      var delta = top - lastTop;
      if (Math.abs(delta) < THRESHOLD) return;
      if (delta > 0) {
        bar.classList.add('is-hidden');    // scrolling down → hide
      } else {
        bar.classList.remove('is-hidden'); // scrolling up   → show
      }
      lastTop = top;
    }, { passive: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
