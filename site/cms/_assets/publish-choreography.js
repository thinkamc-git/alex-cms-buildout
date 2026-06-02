/**
 * publish-choreography.js — wires the Phase 14.6 (revised) "Schedule for
 * Publish" box in the edit-view right aside to the form's action row at
 * the bottom, plus countdown banner + pipeline load-more.
 *
 * Expected DOM (rendered by each edit view at Draft stage):
 *
 *   In the right aside (data-publish-section):
 *     <input type="checkbox" data-publish-toggle>
 *     <div data-publish-schedule-row [hidden]>
 *       <input type="datetime-local" data-schedule-input>
 *
 *   In the action row:
 *     <button data-publish-btn>Publish Now</button>
 *     <button data-schedule-btn hidden>Schedule →</button>
 *     <button data-set-schedule>Schedule Publish</button>
 *
 *   Scheduled-row banner (above the title field):
 *     <div class="schedule-banner" data-target="2026-06-15T09:00:00Z">
 *       <span class="schedule-countdown"></span>
 *
 *   Pipeline Published lane load-more:
 *     <button data-pipeline-load-more>Load more</button>
 *     (sibling .kcard.pipeline-load-more-hidden elements are revealed)
 */
(function () {
  'use strict';

  // ─── Publish-box checkbox choreography ───────────────────────────────
  const section = document.querySelector('[data-publish-section]');
  if (section) {
    const toggle        = document.querySelector('[data-publish-toggle]');
    const scheduleRow   = document.querySelector('[data-publish-schedule-row]');
    const scheduleInput = document.querySelector('[data-schedule-input]');
    const publishBtn    = document.querySelector('[data-publish-btn]');
    const scheduleBtn   = document.querySelector('[data-schedule-btn]');
    const setBtn        = document.querySelector('[data-set-schedule]');

    function applyMode() {
      const isSchedule = !!(toggle && toggle.checked);
      if (scheduleRow) scheduleRow.hidden = !isSchedule;
      if (publishBtn)  publishBtn.hidden  = isSchedule;
      if (scheduleBtn) scheduleBtn.hidden = !isSchedule;
      if (setBtn)      setBtn.hidden      = isSchedule;
    }

    if (toggle) {
      toggle.addEventListener('change', applyMode);
    }

    if (setBtn) {
      setBtn.addEventListener('click', function (event) {
        event.preventDefault();
        if (!toggle) return;
        toggle.checked = true;
        toggle.dispatchEvent(new Event('change', { bubbles: true }));
        if (scheduleInput) {
          scheduleInput.focus();
          if (typeof scheduleInput.showPicker === 'function') {
            try { scheduleInput.showPicker(); } catch (_) { /* ignore */ }
          }
        }
      });
    }

    // Sync initial state in case the server rendered the checkbox checked.
    applyMode();
  }

  // ─── Schedule-banner countdown ───────────────────────────────────────
  const banners = Array.from(document.querySelectorAll('.schedule-banner'));

  function formatCountdown(targetMs) {
    const now = Date.now();
    const diff = targetMs - now;
    if (diff <= 0) return 'publishing soon…';

    const minute = 60 * 1000;
    const hour   = 60 * minute;
    const day    = 24 * hour;

    if (diff > day) {
      const days  = Math.floor(diff / day);
      const hours = Math.floor((diff - days * day) / hour);
      return 'in ' + days + ' day' + (days === 1 ? '' : 's') +
             ', ' + hours + ' hour' + (hours === 1 ? '' : 's');
    }
    if (diff > hour) {
      const hours   = Math.floor(diff / hour);
      const minutes = Math.floor((diff - hours * hour) / minute);
      return 'in ' + hours + ' hour' + (hours === 1 ? '' : 's') +
             ', ' + minutes + ' minute' + (minutes === 1 ? '' : 's');
    }
    if (diff > minute) {
      const minutes = Math.floor(diff / minute);
      return 'in ' + minutes + ' minute' + (minutes === 1 ? '' : 's');
    }
    return 'publishing soon…';
  }

  function updateBanners() {
    banners.forEach(function (banner) {
      const target = banner.getAttribute('data-target');
      if (!target) return;
      const targetMs = Date.parse(target);
      if (isNaN(targetMs)) return;
      const span = banner.querySelector('.schedule-countdown');
      if (!span) return;
      span.textContent = formatCountdown(targetMs);
    });
  }

  if (banners.length) {
    updateBanners();
    setInterval(updateBanners, 30 * 1000);
  }

  // ─── Updated-date checkbox + X clear (Phase 14.6 followup 2) ────────
  // Date-only input is ALWAYS pre-filled with data-default (the actual
  // updated_at date). When value === default, we render dimmed ("is-default"
  // class) and hide the X. When value differs, full darkness + X visible.
  // Clicking X resets value to default → dim + hide X again.
  const updGroup = document.querySelector('[data-updated-group]');
  if (updGroup) {
    const showBox  = updGroup.querySelector('[data-show-updated]');
    const updInput = updGroup.querySelector('[data-updated-input]');
    const clearBtn = updGroup.querySelector('[data-clear-updated]');

    function syncUpdatedState() {
      const isOn = !!(showBox && showBox.checked);
      if (updInput) updInput.disabled = !isOn;
      const defaultVal = updInput ? (updInput.dataset.default || '') : '';
      const isDefault  = !!(updInput && updInput.value === defaultVal);
      if (updInput) updInput.classList.toggle('is-default', isDefault);
      // X visible only when checkbox is ON AND value differs from default.
      if (clearBtn) clearBtn.hidden = !isOn || isDefault;
    }

    if (showBox)  showBox.addEventListener('change', syncUpdatedState);
    if (updInput) updInput.addEventListener('input', syncUpdatedState);
    if (clearBtn) {
      clearBtn.addEventListener('click', function (event) {
        event.preventDefault();
        if (updInput) updInput.value = updInput.dataset.default || '';
        syncUpdatedState();
        if (updInput) updInput.focus();
      });
    }

    // Sync initial state in case the server pre-rendered with checked + override.
    syncUpdatedState();
  }

  // ─── Pipeline Load-More ──────────────────────────────────────────────
  const loadMoreBtns = Array.from(document.querySelectorAll('[data-pipeline-load-more]'));
  loadMoreBtns.forEach(function (btn) {
    btn.addEventListener('click', function (event) {
      event.preventDefault();
      const parent = btn.parentNode;
      if (!parent) { btn.remove(); return; }
      const hidden = parent.querySelectorAll('.pipeline-load-more-hidden');
      hidden.forEach(function (el) { el.classList.remove('pipeline-load-more-hidden'); });
      btn.remove();
    });
  });
})();
