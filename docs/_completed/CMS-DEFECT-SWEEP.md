# CMS Defect Sweep — Inventory

**Date:** 2026-06-11
**Method:** Six parallel read-only audits across the CMS surface (auth, list views, content-edit views, index editor, settings/showcase, action handlers). HIGH-severity items spot-verified against source before listing here; confirmed false positives dropped.

**Status:** Inventory complete — awaiting scope approval before any fix.

Legend: ✅ = verified against source · ⚠️ = reported, not yet verified · ❌ = checked and dismissed (false positive / intentional)

---

## A. Confirmed defects (verified, real)

| # | Cat | Sev | Location | Defect |
|---|-----|-----|----------|--------|
| A1 | FUNCTIONAL | med | `article-edit.php:437` | ✅ `$updatedAtDateOnly` read in POST handler at L437 but not defined until L578 → undefined-var notice; the "is this date overridden" compare runs against `''`. |
| A2 | UI/STYLE | low | `index-edit.js:~788` | ✅ Author-info collapsed-summary fallback is `'plain'` (dead enum). Empty bg shows "Plain" in the section summary instead of "Transparent". |
| A3 | DEAD | low | `index-edit.js:159` | ✅ `is-abg-plain` still in the class-clear list. Harmless (never added) but should go with `'plain'`. |
| A4 | CONSISTENCY | low | `navigation-reorder.php:18` vs `series-reorder.php:34` | ✅ navigation-reorder has no `REQUEST_METHOD==='POST'` guard; sibling returns 405. Functionally safe (GET fails CSRF → 403) but inconsistent. |

---

## B. Reported, plausible — need a 2-min confirm before fixing

| # | Cat | Sev | Location | Defect |
|---|-----|-----|----------|--------|
| B2 | CONSISTENCY | high | `article-edit` / `journal-edit` / `live-session-edit` / `experiment-edit` | ⚠️ "Idea Notes" read-only visibility triggers at a different pipeline stage per editor (Concept vs Draft) and experiment inlines the condition instead of a `$showIdeaNotes` bool. Need to decide the intended rule, then align all four. |
| B3 | CONSISTENCY | med | content-edit ×4 | ⚠️ Body-HTML sanitization: live-session/experiment always sanitize; article/journal gate on `$editBody`. (Article's gate is *defensible* — see C — but the four should follow one explicit rule.) |
| B4 | FUNCTIONAL | med | `ideation.php` / `pages.php` | ⚠️ Each list view defines its own inline `rel_time`/date formatter instead of a shared helper → drift. |

---

## C. Inconsistencies worth a decision (not bugs, but the system is divergent)

| # | Cat | Location | Note |
|---|-----|----------|------|
| C1 | CONSISTENCY | `articles.php` vs `journals.php` (stage-pill default) | Unpublished fallback differs ("draft" vs "idea"). May be **intentional** (different default stages per type). Confirm, then document or align. |
| C2 | UI/STYLE | `indexes.php:55`, `categories.php:122`, `redirects.php:102`, `filter-bar.php:60` | Inline `style="background:var(--ink-08);…font-size:10px;padding:2px 7px"` pills repeated across views. Should be one `.pill` utility class in `style-cms.css`. |
| C3 | UI/STYLE | `page-edit.php` (pervasive), `article-edit.php:1158` | page-edit uses inline styles extensively vs siblings. Larger cleanup. |
| C4 | CONSISTENCY | `topbar.php:49` | Breadcrumb href map keys off the human title ("Draft Writing") rather than a stable nav-id → fragile. |

---

## D. design-system.php — verdict: **REBUILD or REMOVE** (your call)

The in-CMS Design System view was gutted in the recent merge; it's now a ~106-line shell that lazy-loads iframes pointing at the same public `/_ds/` showcase the project already serves. Per project canon, the **public `/_ds/` showcase (rendered from the real CSS) is the source of truth** for styles.

Two clean options:
- **REMOVE** — delete the view, keep the existing "Open /_ds/ →" link in the CMS chrome. Least code, no duplication, honours "one source of truth."
- **REBUILD** — make it a *real* in-CMS quick-reference pulled live from the CSS (no hardcoded swatch arrays). More work; only worth it if you want tokens visible without leaving the admin.

My recommendation: **REMOVE** (link out to `/_ds/`). A hardcoded in-CMS copy is exactly the duplication the build-discipline rule warns against.

---

## E. Confirmed false positives (checked, no action)

- `navigation.php:341` "unescaped target_id" — ❌ it **is** `$e()`-escaped. No XSS.
- `ideation.php:129` "`relative_time()` undefined" — ❌ defined in `lib/content.php:71`. No crash.
- `article-edit.php:345` body "preservation bug" — ❌ preserving existing body at pre-draft stages is **correct**, not a bug.
- Auth surface (login/logout/account/unlock/reset + lib/auth, login_throttle, recovery_codes) — ❌ effectively clean. Only nit: `logout.php:25` returns instead of `exit` after redirect (cosmetic), and `reset-password.php:74` clears two legacy throttle columns no longer read (dead, harmless).
- All 5 new / 5 delete / 3 upload / 5 preview handlers — ❌ clean (CSRF, auth, existence checks, centralised `accept_upload()` validation all present).

---

---

# Part 2 — Page-by-page review (Alex + Claude, walkthrough 2026-06-11)

This is the functional / UI / interactive pass — the collaborative walkthrough. Part 1 above (the code audit) feeds it as a per-page checklist. Each item here is an Alex-confirmed defect or request.

> **Status (2026-06-11):** all walkthrough items resolved and deployed to staging. ✅ = fixed · ⚪ = confirmed not-a-defect (closed).

### Global — environment badge ✅ DONE
- [x] Topbar prod variant — black bg / white text "prod" via `--primary`/`--surface` tokens. (`topbar.php`, `style-cms.css` `.topbar-env-pill--prod`)
- [x] Same prod pill on **login**.
- [x] Login pill **clickable to switch environments** — staging→`alexmchong.ca/cms/`, prod→`staging.alexmchong.ca/cms/`. (`login.php`)

### 1 — Pipeline (`/cms/`)
- [x] ✅ Drag fails in **Recently Published** ("Unknown stage"). Cause: Scheduled/Recently-Published cards are `<a>` links — draggable-by-default — so native link-drag entered the reorder flow and posted a non-stage lane key. Fix: `draggable="false"` on both static card renderers + a `dragstart` guard in `dragdrop.js` (only acts on `draggable="true"`). (`pipeline.php`, `dragdrop.js`)
- [x] ✅ Date format → `5d ago` short form (`5m`/`5h`/`5d`). (`relative_time()` in `lib/content.php`)

### 2 — Ideation (`/cms/ideation`)
- ⚪ Edit idea → Concept not Draft. **Closed — works as designed:** *article* ideas advance to Concept; journal/live-session/experiment skip to Draft by design (`stages_for_type()`).
- [x] ✅ RTF toolbar over save bar — save bar given `z-index:11` to sit above the toolbar's `z-index:10`. (`style-cms.css`)
- [x] ✅ RTF ↔ HTML toggle — not a component swap; just needed spacing. Bumped `.body-source-toggle` gap `--space-6` → `--space-12` (all 4 editors). (`style-cms.css`)
- [x] ✅ Editor missing **top page-gutter**, unified across all 4 post editors. The `:has(.tiptap-wrap.body-box){padding-top:0}` hack killed the gutter on Draft/Published; replaced with `.content-area[data-tab-panel="edit"]{padding-top:0}` + gutter on the form, so every stage/type matches. (`style-cms.css`)

### 11 — Categories (`/cms/categories`)
- [x] ✅ Colour dropdown now opens **above** the rows. Took three passes: z-index and overflow-escapes both failed (real `<table>` + scroll container), and `position:fixed` alone broke because the rows carry a lingering `transform` from the reveal animation (transformed ancestor = containing block for fixed). Final fix: menu is `position:fixed` **portaled to `<body>`** and JS-anchored to the trigger, escaping the table, scroll clip, and transform entirely. (`categories.php`, `style-cms.css`)

### 12 — Navigation (`/cms/navigation`)
- ⚪ Mobile "show on mobile" checkboxes. **Closed — was a stale-CSS cache; correct after a hard refresh.**

### 13 — Redirects (`/cms/redirects`)
- [x] ✅ Added **Updated** column (auto-bumps on edit). Migration `0034`, `lib/redirects.php`, view. *(Labeled "Updated" since the date refreshes on edit — flag if you'd rather it read "Created.")*

### 15 — Settings (`/cms/settings`)
- [x] ✅ Integrations snippet now renders in **CodeMirror** (`htmlmixed` — syntax-highlighted, monospaced, the editor already used by Pages/Post-Template), mirroring edits back to the textarea for dirty-flip/submit. (Interim `.field-input--code` block style was dropped; also removed a dead inline `var(--text-small)` reference.) (`settings.php`, `style-cms.css`)

### Sidebar — reorder (`partials/sidebar.php`) ✅ DONE
- [x] **Site:** Navigation, Pages, Indexes, Redirects
- [x] **System:** Post Templates, Design System, Settings
- [x] **Collections:** Series, Categories

---

## Suggested fix batches (Part 1 code defects — for approval)

1. **Quick wins** (A1–A4) — undefined-var, dead `'plain'`, method-guard parity. ~15 min, low risk.
2. **Editor consistency** (B2, B3, C1) — decide the intended rule for Idea-Notes visibility + body sanitization + stage defaults, then align the four editors. Needs your decision on intended behaviour.
3. **Style consolidation** (C2, C4) — extract the repeated pill into a `.pill` class; fix the breadcrumb map. C3 (page-edit inline styles) is a bigger separate cleanup.
4. **design-system.php** (D) — remove or rebuild per your call.
