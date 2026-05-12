/* ============================================================
   Alex M. Chong — Design System
   Shared script for index.html

   - Tab switching (top tabs bar) with URL hash routing
   - Per-panel sidebar scroll spy
   - Filter pill toggle behaviour
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
    // Anchor ~56px above the section so the eyebrow (.section-num) is comfortably visible
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

  // ── Init on DOMContentLoaded ─────────────────────────────
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

    var first = initialTab();
    if (first) activateTab(first, false);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
