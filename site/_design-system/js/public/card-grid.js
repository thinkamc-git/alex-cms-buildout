/* ──────────────────────────────────────────────────────────────────────────
 * card-grid.js — load + filter motion for every public .cards-grid.
 *
 * Promoted from the card-load sandbox (2026-06). Two phases, both judged in the
 * sandbox and applied universally:
 *   • Load   — Stagger entrance: visible cards fade + translate up on first
 *              paint, one after another (320ms / 45ms per card).
 *   • Filter — Re-enter: the grid fades out, the filter is applied, then the
 *              new set animates back in with the same entrance (260ms / 25ms).
 *
 * The per-index filter scripts (index-editorial.php, index-listing.php) keep
 * owning the pill on/off + .is-filtered-out logic; they hand their apply()
 * callback to window.CardGrid.reenter() so the swap is animated. Respects
 * prefers-reduced-motion (no animation, instant apply).
 * ────────────────────────────────────────────────────────────────────────── */
(function () {
  'use strict';

  var LOAD_DUR = 320, LOAD_STAG = 45;   // first-paint entrance
  var FILT_DUR = 260, FILT_STAG = 25;   // filter re-enter
  var FADE_OUT = 120;                   // grid fade-out before the swap

  var reduce = window.matchMedia &&
               window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function visibleCards(grid) {
    return Array.prototype.filter.call(
      grid.querySelectorAll('.card'),
      function (c) { return !c.classList.contains('is-filtered-out'); }
    );
  }

  function clearEnter(c) {
    c.classList.remove('cg-enter', 'cg-enter-active');
    c.style.transitionDelay = '';
  }

  // Staggered fade/translate entrance for a set of cards in one grid. The start
  // state is committed in a single reflow so the transition reliably runs (not a
  // hard before/after snap).
  function staggerIn(grid, list, dur, stag) {
    if (reduce || !list.length) return;
    grid.style.setProperty('--cg-dur', dur + 'ms');
    list.forEach(function (c) { clearEnter(c); c.classList.add('cg-enter'); });
    void grid.offsetWidth;
    requestAnimationFrame(function () {
      list.forEach(function (c, i) {
        c.style.transitionDelay = (i * stag) + 'ms';
        c.classList.add('cg-enter-active');
        c.addEventListener('transitionend', function te() {
          clearEnter(c);
          c.removeEventListener('transitionend', te);
        }, { once: true });
      });
    });
  }

  // Public: animate a grid's currently-visible cards in (first paint / replay).
  function load(grid) {
    if (!grid) return;
    staggerIn(grid, visibleCards(grid), LOAD_DUR, LOAD_STAG);
  }

  // Public: re-enter on filter change. applyFn() must do the actual visibility
  // work (toggle .is-filtered-out + any count update).
  function reenter(grid, applyFn) {
    if (reduce || !grid) { applyFn(); return; }
    grid.style.transition = 'opacity ' + FADE_OUT + 'ms ease';
    grid.style.opacity = '0';
    setTimeout(function () {
      applyFn();
      grid.style.transition = '';
      grid.style.opacity = '1';
      void grid.offsetWidth;
      staggerIn(grid, visibleCards(grid), FILT_DUR, FILT_STAG);
    }, FADE_OUT);
  }

  window.CardGrid = { load: load, reenter: reenter };

  // Auto load-entrance on every card grid once fonts are settled (a font swap
  // mid-animation reads as jagged). Idempotent guard so the fallback can't
  // double-fire.
  var inited = false;
  function init() {
    if (inited) return;
    inited = true;
    Array.prototype.forEach.call(document.querySelectorAll('.cards-grid'), load);
  }
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(init);
    setTimeout(init, 800);
  } else if (document.readyState !== 'loading') {
    init();
  } else {
    document.addEventListener('DOMContentLoaded', init);
  }
})();
