# Mobile audit — Phase 23.2 (implement + test)

> **Pass 2 of Phase 23.** Source of truth = `MOBILE-DECISIONS.md` (the signed-off
> spec). No new design calls here — only execution, a per-surface test checklist,
> and a live bug log. **Staging-only.** Breakpoints: `480` / `768` / `1024` / desktop.

## CSS targets (confirmed post-22.6)
- **Marketing surface →** `site/_design-system/css/public/pages.css` (the §10.1 hotspot — only had a 720px tier; adding **480 + 600**). Loaded by marketing pages via `system-public.css`.
- **Article/index surface →** `site/_design-system/css/public/blocks.css` (already 768/480/900/600; adjust per decisions). Loaded directly by `master-layout.php`.
- **Nav/footer chrome is duplicated:** marketing `_pages/_layout/header.html` + `footer.html` (styled in pages.css) AND template `templates/partials/nav.php` + `footer.php` (styled in blocks.css). Shared rules (X1/X6/X11) land in **both**; drawer JS shared (`_pages/_layout/mobile-nav.js`, loaded on both surfaces).

## Verification loop (per batch)
1. I self-render locally where possible (Brave headless at 390/768/1024): blocks family via the `/_ds/showcase/*` reps; marketing via static composites of `header.html` + `_bodies/<page>.html` + `footer.html` linking `system-public.css`.
2. Deploy to **staging**, md5-verify.
3. Alex confirms the real pages at each breakpoint → mark pass/fail + log any new bug below.

---

## Implementation order (batches)

**Batch 1 — Shared global rules** (touch every page; do first):
- [ ] X6 Footer mobile (centre, links top, copyright bottom) — both footers
- [ ] X7 Buttons (natural width, same row, never full-width/stacked) — global
- [ ] X11 Gutter alignment (logo→mobile gutter, logo+content paired; 🐞 BUG-2 breadcrumb extra gutter)
- [ ] X8 Topic/filter bar (gutter-flush desktop+tablet; mobile hide label, "All" to gutter)
- [ ] X2/X3 Type scale (page-header-title +1.3×; index titles +1.1×; article title +1.1×) · X10 blockquote desktop +1.05×

**Batch 2 — Nav drawer (X1) — ✅ DONE & approved.** Final spec (both nav surfaces — `header.php`/`header.html` marketing + `nav.php` templates):
- Hamburger below desktop (≤1024); bars ~0.8×, right-aligned to the page gutter; → X on open (X sits above the panel).
- **Tablet (768–1024):** side drawer from the right, animates in **and** out.
- **Mobile (≤767):** dropdown under the bar, **clip-reveal** unfurl (no bar-line flicker); uniform `space-20` gutter; items `--text-base` (14px), `space-12` pad, `space-8` gap.
- Menu panel = **solid `--surface` + grain texture** (true blur impossible while nested in the blurred bar; declined the JS un-nest). Bar keeps its real frosted blur.
- **Dim overlay** behind menu = `rgba(0,0,0,0.1)`, **fades in/out**, sized in `vw/vh` (escapes the bar's backdrop-filter containing block). Tablet: full-screen incl. bar. Mobile: below the bar.
- Logo +1.1×, logo/X kept above the dim. Resize-guard (`html.nav-no-anim`) prevents breakpoint-cross ghost.

**Batch 3 — Marketing pages** (build the 480 + 600 tier in pages.css):
- [ ] A3 Coaching: journey/loop stacking + 🐞 BUG-1 loop arrows; situation/testimonial/faq 600 collapse; "clients come to me for" + "clients say" → carousels (X4); consultation spacing
- [ ] A5 Resume: Download+Email one line right-aligned; ledger
- [ ] A1/A2/A4/A6/A7/A8: confirm at new tiers (mostly shared-rule beneficiaries)

**Batch 4 — Article/index family** (blocks.css adjust):
- [ ] X9 Read-time inline with date (structural — block partial markup + CSS)
- [ ] B2 category↔series-pill spacing · B3 entry-number keep-tablet + key-statement +1.1×
- [ ] X12 editorial bleed hero full-bleed (drop tablet/mobile gutters+underline) · X4 carousel fade∝gutter · X13 view-all inline-right · C3 within-hero image-on-top + framing rules
- [ ] 🐞 BUG-3 featured label category colour (`--c-current`)

---

## Test checklist (surface × breakpoint)

Legend: ⬜ todo · ✅ pass · ⚠️ issue (see bug log)

| Surface | 480 | 768 | 1024 | desktop | Decisions to confirm |
|---|:--:|:--:|:--:|:--:|---|
| Landing | ⬜ | ⬜ | ⬜ | ⬜ | nav drawer, footer, buttons, logo |
| About | ⬜ | ⬜ | ⬜ | ⬜ | header +1.3×, buttons |
| Coaching | ⬜ | ⬜ | ⬜ | ⬜ | journey/loop, carousels, 600 collapse, consult spacing, BUG-1 |
| Work with me | ⬜ | ⬜ | ⬜ | ⬜ | shared rules |
| Resume | ⬜ | ⬜ | ⬜ | ⬜ | download+email one line, ledger |
| Newsletter | ⬜ | ⬜ | ⬜ | ⬜ | shared rules |
| Newsletter confirmed | ⬜ | ⬜ | ⬜ | ⬜ | buttons same row |
| 404 | ⬜ | ⬜ | ⬜ | ⬜ | shared rules |
| Article — standard | ⬜ | ⬜ | ⬜ | ⬜ | title +1.1×, read-time inline, topic bar, BUG-2 |
| Article — series | ⬜ | ⬜ | ⬜ | ⬜ | category↔series spacing, blockquote |
| Journal entry | ⬜ | ⬜ | ⬜ | ⬜ | entry number, key statement +1.1× |
| Live session | ⬜ | ⬜ | ⬜ | ⬜ | event card, topic bar |
| Experiment | ⬜ | ⬜ | ⬜ | ⬜ | topic bar |
| Experiment — HTML body | ⬜ | ⬜ | ⬜ | ⬜ | read-time inline, containment |
| Basic Listing | ⬜ | ⬜ | ⬜ | ⬜ | index title +1.1×, topic bar |
| Editorial — bleed (`/fancy`) | ⬜ | ⬜ | ⬜ | ⬜ | full-bleed, carousel fade, view-all, BUG-3 |
| Editorial — within (`/fancy` btm) | ⬜ | ⬜ | ⬜ | ⬜ | image-on-top + framing rules |
| Series page | ⬜ | ⬜ | ⬜ | ⬜ | shared rules |

---

## ⏳ Deferred follow-up — CMS toggles (build these two together)

Two small schema+CMS features requested 2026-06-09, deferred out of mobile-polish scope. **Bundle into one "CMS toggles" task** (one migration pass).

### A · Nav item "hide on mobile" toggle
- **Scope:** **phone only (≤767)** — hidden at phone widths; still shows in the tablet drawer + desktop bar.
- **DB migration:** `hide_mobile` boolean on `nav_items`.
- **`lib/nav.php`:** persist it + emit a class (e.g. `is-hide-mobile`) on the `<a>` when on.
- **CMS Navigation editor:** per-item toggle.
- **CSS:** `@media (max-width:767px) .layout-nav-links a.is-hide-mobile { display: none }` (both nav surfaces).

### B · Bleed-hero blur toggle
- **DB migration:** new `hero_blur` column on the index hero settings.
- **`lib/indexes.php`:** read/write/default (default **dark = off, light = on**).
- **CMS editor:** toggle UI in `index-edit-section.php` + live preview in `index-edit.js` / `style-cms.css`.
- **Template:** `index-editorial.php` adds `editorial-hero--blur` when on.
- **CSS:** move the blur from `.editorial-hero--bleed-light::after` onto a `.editorial-hero--blur::after` toggle class (works for dark + light).
- Until built: light bleed keeps its bottom blur via CSS; dark has none.

Bleed gradient/eyebrow tuning (done): dark `0.15→0.6@40%→0.8`, light `0.10→0.65@40%→0.95`; dark eyebrow lightened `color-mix(...,#fff 50%)`; CMS preview gradients synced (`style-cms.css`). Editorial heroes now stack as **flush blocks** (no inter-section gap; framing rules divide).

## 🐞 Live bug log

Seeded from Pass 1; append new bugs found during implementation/testing.

| # | Bug | Status |
|---|-----|--------|
| BUG-1 | Coaching "Feedback loop" arrows overlap text (mobile) | ✅ fixed; also un-slanted the ↓ (was italic-serif) — approved |
| BUG-2 | Breadcrumb extra gutter (desktop) | ✅ fixed (space-64 → space-48, aligns to nav) |
| BUG-3 | Featured label not category-coloured (`--c-current`) | ✅ fixed — editorial-hero eyebrow set `--c-current:terracotta` (bare name = invalid colour); now `var(--c-terracotta)` like index-card. Template bug, fixes desktop too. |

## `/fancy` hero group — built (2026-06-09), for one-by-one review
- **X12 bleed hero full-bleed** ✅ root cause: `@media .index-section` padding re-overrode `.index-section--hero{padding:0}` at equal specificity → re-added hero `padding:0; border-bottom:0` at ≤900/≤600 (verified via composite).
- **C3 within hero** ✅ `.editorial-hero-side { order:-1 }` (image on top) + framing rules top/bottom at ≤900 (verified).
- **X13 view-all** ✅ section-header no longer stacks to column at ≤600 (stays inline, wraps only if needed) — verify.
- **X8 topic/filter bar** ✅ scoped to `.index-page` (CMS untouched): controller-row padding matches page gutter at ≤900/≤600; `.ctrl-label` hidden at ≤600 — verify.
- **X4 carousel fade** — left as-is (mask fade is generous on mobile); judgment call in review.

## Pass status (2026-06-09, on staging)

**Implemented + deployed (confident — verify in review):**
X1 nav drawer (both surfaces + hamburger + logo 1.1×) · X2 index/article title +1.1× · X3 page-header-title +1.3× · X6 footer · X7 buttons + resume one-row · X9 read-time inline (structural) · X10 blockquote +1.05× · BUG-1 loop arrows · BUG-2 breadcrumb · coaching carousels (clients-come-to / clients-say) · consultation spacing · B3 key-statement +1.1×.

**Best done WITH eyes on staging in the defect review (visual-alignment / shared-component / data — not guessed blind):**
- X8 topic/filter bar gutter + mobile hide-label — lives in shared `components.css` (also CMS); needs scoped fix + visual.
- X11b logo↔content gutter pairing — nav gutter set; content pairing needs visual.
- X12 editorial bleed hero full-bleed (drop tablet/mobile gutters + underline) — needs `/fancy` inspection.
- C3 within-hero image-on-top + framing rules — needs `/fancy` inspection.
- X13 view-all inline-right · X4 carousel fade tuning — verify whether already correct.
- B2 category↔series-pill spacing · B3 entry-number keep-tablet — minor; locate exact adjacency.
- BUG-3 featured eyebrow colour — data (`--c-current`) inspection.
