/* ============================================================
   Alex M. Chong — Design System · Showcase script
   ------------------------------------------------------------
   1. Tab switching (top tabs bar) with URL hash routing
   2. Per-panel sidebar scroll spy + click-to-scroll
   3. Filter pill toggle behaviour
   4. Runtime token reader — every .chip's swatch + token name
      drive the .chex value live, so editing a token in tokens.css
      and refreshing updates the rendered hex (or computed value).
   ============================================================ */

(function () {

  // ── Tab switching ─────────────────────────────────────────
  function activateTab(name, push) {
    var tabs = document.querySelectorAll('.topbar .topbar-tabsbar-tab');
    var panels = document.querySelectorAll('.workspace');
    var found = false;
    tabs.forEach(function (t) {
      var match = t.dataset.tab === name;
      t.classList.toggle('active', match);
      if (match) found = true;
    });
    panels.forEach(function (p) {
      p.classList.toggle('active', p.dataset.tab === name);
    });
    if (!found) return;
    if (push && location.hash !== '#' + name) {
      history.replaceState(null, '', '#' + name);
    }
  }

  function initialTab() {
    var hash = (location.hash || '').replace('#', '');
    var tabs = document.querySelectorAll('.topbar .topbar-tabsbar-tab');
    if (hash) {
      for (var i = 0; i < tabs.length; i++) {
        if (tabs[i].dataset.tab === hash) return hash;
      }
    }
    var firstActive = document.querySelector('.topbar .topbar-tabsbar-tab.active');
    if (firstActive && firstActive.dataset.tab) return firstActive.dataset.tab;
    return tabs[0] ? tabs[0].dataset.tab : null;
  }

  // ── Sidebar scroll spy + click-to-scroll ─────────────────
  function goSection(panelEl, sectionId) {
    var el = panelEl.querySelector('#' + CSS.escape(sectionId));
    if (!el) return;
    var content = panelEl.querySelector('.canvas');
    if (content) content.scrollTo({ top: el.offsetTop - 56, behavior: 'smooth' });
    var nav = panelEl.querySelector('.sidebar');
    if (nav) {
      nav.querySelectorAll('.sidebar-link').forEach(function (link) {
        link.classList.toggle('active', link.dataset.scrollSection === sectionId);
      });
    }
  }

  function bindPanel(panelEl) {
    var content = panelEl.querySelector('.canvas');
    var nav = panelEl.querySelector('.sidebar');
    if (nav) {
      nav.querySelectorAll('.sidebar-link[data-scroll-section]').forEach(function (link) {
        link.addEventListener('click', function () {
          goSection(panelEl, link.dataset.scrollSection);
        });
      });
    }
    if (content && nav) {
      content.addEventListener('scroll', function () {
        var current = '';
        content.querySelectorAll('[id]').forEach(function (s) {
          if (s.offsetTop - content.scrollTop < 120) current = s.id;
        });
        nav.querySelectorAll('.sidebar-link').forEach(function (link) {
          link.classList.toggle('active', !!current && link.dataset.scrollSection === current);
        });
      }, { passive: true });
    }
  }

  // ── Filter pill toggle ───────────────────────────────────
  function toggleFilter(btn) {
    var cat = btn.dataset.cat;
    var group = btn.closest('div');
    if (cat === 'all') {
      group.querySelectorAll('.fp').forEach(function (b) { b.classList.remove('on'); });
      btn.classList.add('on');
    } else {
      var allBtn = group.querySelector('[data-cat="all"]');
      if (allBtn) allBtn.classList.remove('on');
      btn.classList.toggle('on');
      if (!group.querySelectorAll('.fp.on').length && allBtn) allBtn.classList.add('on');
    }
  }

  // ── Runtime token reader ─────────────────────────────────
  // Converts a fully-opaque rgb(r,g,b) string to #RRGGBB.
  // Returns null for rgba() with alpha < 1, so transparent tokens
  // (ink-08/12/18/30/72, white-06/15/45/85) keep their hand-authored
  // "primary 8%" / "white 45%" labels.
  function rgbToHex(s) {
    if (!s) return null;
    var m = s.match(/^rgb\(\s*([0-9.]+)\s*,\s*([0-9.]+)\s*,\s*([0-9.]+)\s*\)$/i);
    if (!m) return null;
    function pad(n) { var x = parseInt(n, 10).toString(16); return x.length === 1 ? '0' + x : x; }
    return '#' + (pad(m[1]) + pad(m[2]) + pad(m[3])).toUpperCase();
  }

  function hydrateTokens() {
    // Every .chip with a swatch + chex pair: read the swatch's resolved
    // background-color and write it back to the chex element. Opaque
    // tokens become "#RRGGBB"; transparent washes are left untouched
    // (their chex is hand-authored as "primary 8%" / "white 45%" etc.).
    document.querySelectorAll('.chip').forEach(function (chip) {
      var swatch = chip.querySelector('.swatch');
      var chex = chip.querySelector('.chex');
      if (!swatch || !chex) return;
      var bg = getComputedStyle(swatch).backgroundColor;
      var hex = rgbToHex(bg);
      if (hex) chex.textContent = hex;
    });
  }

  // ── Init ─────────────────────────────────────────────────
  function init() {
    document.querySelectorAll('.topbar .topbar-tabsbar-tab').forEach(function (t) {
      t.addEventListener('click', function (e) {
        e.preventDefault();
        activateTab(t.dataset.tab, true);
      });
    });

    window.addEventListener('hashchange', function () {
      var hash = (location.hash || '').replace('#', '');
      if (hash) activateTab(hash, false);
    });

    document.querySelectorAll('.workspace').forEach(bindPanel);

    document.querySelectorAll('.fp').forEach(function (btn) {
      btn.addEventListener('click', function () { toggleFilter(btn); });
    });

    hydrateTokens();

    var first = initialTab();
    if (first) activateTab(first, false);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
