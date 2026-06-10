# Mobile decisions — Phase 23.1 (review + capture)

> **How this works.** Pass 1 of Phase 23. We go **section by section, in order**.
> For each, you open the staging URL in your browser and check it at the three
> widths (use DevTools device toolbar, or just narrow the window). I've attached
> the **§10 audit findings** to watch for and the **behaviour calls** that need a
> decision. You give notes; I fill the **Decision** line. When every section has a
> decision, this doc becomes the build spec for Pass 2 (Phase 23.2) — no new calls
> get made during implementation, only execution + a test checklist + a bug log.
>
> **Staging base:** `https://staging.alexmchong.ca` (Basic-Auth — log in once in
> the browser). **Check widths:** `390` (phone) · `768` (small tablet) · `1024`
> (large tablet). Implementation will *add* a `480` and a `600` tier per §10.
>
> Status legend per section: ⬜ not reviewed · 📝 notes captured · ✅ decided.

---

## 0. Cross-cutting calls (decide once, apply everywhere)

These ride across every surface — settle them first so per-section review is fast.

| # | Call | Options on the table | Decision |
|---|------|----------------------|----------|
| X1 | **Nav pattern (below desktop)** | hamburger → full-height drawer · sticky condensed bar · reveal-on-scroll | ✅ **Hamburger → full-height drawer for ALL widths below desktop — tablet (≤1024) AND phone**, not just ≤768. The drawer must work well at tablet too (full desktop nav shows only at desktop width). |
| X2 | **Editorial type scale** | desktop 56/40/26/16 → phone target (e.g. 28/22/18/14) | ✅ **Index page titles** (`.index-title` / `.index-page-header` / `.index-section-title-big`) → **+1.1× on mobile** (all Bucket C). **Article title (`.article-title`) → +1.1× on mobile** too. |
| X9 | **Read-time placement** (all article views — *structural*) | inside author section vs inline with date | ✅ **Read time goes INLINE with the date in the meta row — never inside the author/byline section.** Currently on mobile it lands in the author block, which reads oddly. *Authorized HTML/structure change (per 23.1).* Applies to every article-family template. |
| X10 | **Blockquote scale** (all article views) | one size vs per-breakpoint | ✅ **Desktop: +1.05×.** Tablet + mobile: keep current size. |
| X11 | **Gutter alignment** (site-wide) | logo / content / breadcrumb left edges | ✅ (a) **Mobile: the nav logo left-aligns to the tighter mobile page gutter.** (b) **Logo and page/post content share the SAME left gutter** (paired) — they currently differ in posts. (c) **🐞 Desktop: breadcrumbs (`.article-breadcrumb`) have an extra gutter** — remove it so they align to the content/logo gutter. |
| X3 | **Marketing type scale** | `.statement` 54→? · `.page-header-title` 48→? at ≤480 (§10 M6) | ✅ **`.page-header-title` on mobile is too small — bump ~1.3×.** **`.statement` reads fine as-is** (Landing + Work-with-me) — no special mobile step. |
| X4 | **Carousel on touch** (§10 M11) | native scroll-snap + edge fade · arrows · free scroll | ✅ **The page-index carousel aesthetic:** anchors at the left, **extends edge-to-edge to viewport width**, scroll-snap, with an **edge fade**. Match the existing `.cards-grid.is-carousel` (blocks.css ~1600–1641 / editorial-index). Reused wherever a grid becomes a carousel on mobile. **Edge-fade width scales with the gutter** — the mobile gutter is larger, so the fade must be proportionally **wider on mobile** so it actually reads. |
| X12 | **Editorial bleed hero** (C4/C5) | gutter-boxed vs full-bleed at tablet/mobile | ✅ **Full-bleed at ALL widths.** Currently at tablet + mobile it gets side gutters **and a line under it** — remove both so it bleeds edge-to-edge exactly like desktop. |
| X13 | **Index-section "View all"** (shared) | inline vs own line on mobile | ✅ **Mobile: stays inline, anchored to the right** (same line as the section title/label). Only wraps to its own line if it's too long to fit alongside the title. |
| X5 | **Sticky CTAs** | sticky bottom CTA on long pages? which pages? | ✅ **Not needed** — no sticky CTA requested on any surface during review. |
| X6 | **Footer (shared, all public pages)** | desktop split layout → mobile | ✅ **Mobile: centre-aligned. Links move to the top, copyright drops to the bottom.** |
| X7 | **CTA buttons on mobile** (shared) | stretch full-width + stack vs keep intrinsic width + same row | ✅ **Keep natural (desktop) width — never full-width. Keep multiple buttons on the SAME ROW (inline), don't stack them vertically;** right-align where appropriate (e.g. Resume). Only wrap to a new line if a row genuinely can't fit. (Confirmed on Landing, About, Coaching, Resume, Newsletter-confirmed.) |
| X8 | **Topic / filter bar** (shared — article + index pages) | label + "All" pill + category controls alignment | ✅ **Desktop + tablet:** the whole "Topic" bar must sit **flush to the page gutter** (currently not aligned at *either*). **Mobile:** **hide the "Topic" title** and align the **first control ("All" pill) to the page gutter**. (Confirmed on `/writing` and `/live-sessions`.) |

---

## Bucket A — Marketing pages (the §10 hotspot: no breakpoint below 720px)

### A1. Landing ✅
- **Staging:** `/`
- **Watch for:** `.statement` 54px headline at 390 (§10 M6) · `.hero` + `.hero .lead` wrapping · nav (§10 M2 `.layout-nav-logo` min-width pressure <400px)
- **Decide:** nav pattern (→X1) · statement size step · hero stacking order
- **Decision:**
  1. **Nav** → collapses into a hamburger → drawer (→X1).
  2. **Logo** → ~**1.1×** larger on mobile (shared nav logo — applies to all pages).
  3. **CTA / "read about" buttons** → keep natural width, wrap if a row doesn't fit; **never full-width when stacked** (→X7).
  4. **Footer** → centre-aligned on mobile, links to top, copyright to bottom (→X6).
  5. **Tablet (1024)** reads well overall — but the nav must also be the hamburger drawer at tablet, so the drawer has to work well at tablet widths too (→X1).
  6. _Statement headline size step — still to confirm (carried into X3; flag if you saw it overflow at 390)._

### A2. About ✅
- **Staging:** `/about`
- **Watch for:** `.page-header-title` 48px at 390 (§10 M6) · prose measure
- **Decide:** header size step · any section grids that collapse late
- **Decision:**
  1. **"View coaching" CTA** → same full-width-button problem (→X7). Confirms X7 as a firm global rule: **avoid full-width buttons on mobile across all pages.**
  2. Header / prose otherwise read fine — nothing else page-specific.

### A3. Coaching ✅  ← heaviest marketing page
- **Staging:** `/coaching`
- **Watch for:** `.situation-grid` / `.testimonial-grid` / `.faq-list` collapse only at 720 — cramped in 500–700 band (§10 M4) · `.journey` (5-col) + `.loop` (4-col) stacked layout + rotated arrows at 390 (§10 M5) · `.hero` · `.page-header-title` (§10 M6)
- **Decide:** intermediate 600 collapse · journey/loop stacked direction + arrow treatment · grid column counts per width
- **Decision:**
  1. **Page-header-title** → bump ~**1.3×** on mobile (too small currently) (→X3).
  2. **🐞 BUG — "Feedback loop" (`.loop`) arrows overlap the text on mobile.** Fix the stacked-loop arrow positioning in Pass 2 so arrows don't collide with labels.
  3. **"Clients come to me for" cards → carousel on mobile** (not stacked). Use the page-index carousel aesthetic: edge-to-edge anchor + scroll-snap + edge fade (→X4).
  4. **"Clients say" testimonials → same carousel treatment** on mobile (→X4).
  5. **"Book a complimentary consultation"** → (a) **fix spacing** — not enough gap between the text and the first button; (b) the full-width button there becomes natural-width (→X7).

### A4. Work with me ✅
- **Staging:** `/work-with-me`
- **Watch for:** `.statement` size at 390 (§10 M6) · section grids
- **Decide:** statement step · CTA placement (→X5)
- **Decision:** Reads well — no page-specific changes. Shared rules apply. `.statement` confirmed fine as-is (settles X3).

### A5. Resume ✅
- **Staging:** `/resume`
- **Watch for:** `.ledger` `grid:160px 1fr` → 96px 1fr still tight for long dates on phones (§10 M3) · `.page-header-title` (§10 M6)
- **Decide:** stack date-above-entry below 480? · header step
- **Decision:**
  1. **"Download PDF" + "Email me"** → on mobile keep them **on the same line, right-aligned** (don't stack / don't go full-width) (→X7).
  2. Everything else reads fine (ledger ok). `.page-header-title` 1.3× bump applies (→X3).

### A6. Newsletter ✅
- **Staging:** `/newsletter`
- **Watch for:** `.page-header-title` (§10 M6) · form field width + tap targets at 390
- **Decide:** form layout + button sizing on phone
- **Decision:** Looks good — no page-specific changes. Shared rules + `.page-header-title` 1.3× apply.

### A7. Newsletter confirmed ✅
- **Staging:** `/newsletter-confirmed`
- **Watch for:** `.page-header-title` (§10 M6) · centered confirmation block
- **Decide:** spacing/size on phone
- **Decision:** The two CTAs → **same row, not stacked**, natural width (→X7, now generalized to "keep buttons on one row").

### A8. 404 ✅
- **Staging:** any bad path, e.g. `/this-does-not-exist`
- **Watch for:** speech-bubble layout + CTA at 390
- **Decide:** size/centering on phone
- **Decision:** Good as-is — no changes. Shared rules apply.

---

## Bucket B — Article templates (§10: comparatively healthy — verify, don't rebuild)

> Open the matching index, click any item to get a real page. Verify line-length
> and heading rhythm at **390** especially.

### B1. Article — standard ✅
- **Staging:** open `/writing` → click any article
- **Watch for:** `.article-title.is-serif` 56→40→32 step (§10 M8) · `.article-byline-row` stacking · `.article-prose` h2/h3/blockquote rhythm + left/right padding at 390
- **Decide:** byline stack order · prose padding per width · confirm type steps read
- **Decision:**
  1. **🐞 Topic / filter bar — tablet:** the **"Topic" label doesn't left-align to the page gutter** — fix so it sits flush to the gutter. *(Shared bar → see X8; applies on index pages too.)*
  2. **Topic / filter bar — mobile:** **hide the "Topic" title** to free space for the controls; align the **first control ("All" pill) to the page gutter** instead.
  3. Article body (serif title / byline / prose) reads fine at 390 — no changes.
  4. **Index titles → boost ~1.1× on mobile** (→X2; applies to all index pages).

### B2. Article — series ✅
- **Staging:** a `/writing` article that belongs to a series (shows series pill / part-of)
- **Watch for:** everything in B1 + series pill / TOC behaviour on phone (§10 M8)
- **Decide:** series navigation / TOC on phone (inline vs collapsed)
- **Decision:**
  1. **More spacing** between the category label (e.g. "UX Industry") and the series pill in the article header.
  2. **Read-time inline with the date** in the meta — not in the author section (→X9, all views).
  3. **Article title → +1.1× on mobile** (→X2).
  4. **Blockquote → desktop +1.05×; tablet + mobile keep current** (→X10).
  5. Series pill / nav otherwise read fine on phone.

### B3. Journal entry ✅
- **Staging:** open `/journal` → click any entry
- **Watch for:** `.article-key-statement` (serif key statement replacing title) at 390 · category colour rule
- **Decide:** key-statement size on phone
- **Decision:**
  1. **Entry number ("Entry 002")** → currently stacks on mobile; it's small enough that it can just **keep the tablet layout** (don't stack it).
  2. **Key statement → +1.1×** from **tablet down to mobile**.
  3. Article-family rules carry over (read-time inline X9, blockquote X10).

### B4. Live session ✅
- **Staging:** open `/live-sessions` → click any session
- **Watch for:** `.event-card` grid→1fr at 480 (§10 M9) · `.event-date` / `.event-location` / time / cost stacking · format tags wrapping
- **Decide:** event-card field order on phone
- **Decision:** Topic/filter bar recurs (→X8): mobile hides "Topic" label; desktop + tablet aren't gutter-aligned — fix. Event card otherwise reads fine.

### B5. Experiment ✅
- **Staging:** open `/experiments` → click any experiment
- **Watch for:** constellation background scaling · meta + body at 390
- **Decide:** background behaviour on phone
- **Decision:** Same as live-session (→X8 Topic/filter bar); nothing experiment-specific. Constellation + body read fine.

### B6. Experiment — HTML body ✅
- **Staging:** an `/experiments` item that uses the custom-HTML body
- **Watch for:** the embedded custom HTML overflowing the column at 390 (author content, hardest to constrain)
- **Decide:** how to contain / scroll custom HTML on phone
- **Decision:** Confirms **read-time inline applies everywhere** (→X9). Otherwise reads fine.

---

## Bucket C — Index pages (§10: covered at 900/600 — verify; editorial hero is heaviest)

### C1. Basic Listing ✅
- **Staging:** a built-in index in *listing* mode (e.g. `/journal` or `/experiments`)
- **Watch for:** `.index-page-header` · `.index-grid` card density at 390 (§10 M10)
- **Decide:** card columns per width · header stacking
- **Decision:** Good as-is. Shared rules apply (X2 index title +1.1×, X8 topic bar).

### C2. Editorial Page — plain hero ✅ (N/A)
- **Staging:** `/writing` — has no plain editorial hero configured.
- **Decision:** Not present on staging. If a plain hero is ever built, the shared rules apply (index title +1.1× X2, view-all X13). Nothing to decide now.

### C3. Editorial Page — within hero ✅
- **Staging:** https://staging.alexmchong.ca/fancy (within hero is the **bottom** section)
- **Watch for:** side card / thumb stacking at 390 (§10 M12)
- **Decision:**
  1. **Mobile: the image/card stacks ABOVE the text** (not under it).
  2. Because it's a hero, give it **distinct rules above and below** the block to define its shape on mobile.

### C4. Editorial Page — bleed hero ✅
- **Staging:** https://staging.alexmchong.ca/fancy (bleed hero + carousel sections + view-all)
- **Watch for:** full-bleed image + gradient overlay + text legibility at 390 (§10 M12, heaviest block)
- **Decision:** (reviewed via `/fancy`)
  1. **Hero → full-bleed at tablet + mobile** — currently gets side gutters + a line under it; remove both, behave like desktop (→X12).
  2. **Carousel edge-fade width proportional to gutter** — wider on mobile so the fade reads (→X4).
  3. **"View all" → inline, anchored right** on mobile; only wraps if too long (→X13).

### C5. Editorial Page — bleed-light hero ✅
- **Decision:** Same hero family as C4 — the full-bleed rule (X12) + carousel (X4) + view-all (X13) apply identically. Flag if a light variant needs a contrast-specific tweak; otherwise covered.

### C6. Series page ✅
- **Staging:** `/series/[slug]`
- **Decision:** Looks fine — no changes. Shared rules apply (X2 title, X4 carousel).

---

## 🐞 Bug log (found during Pass 1 — fix in Pass 2)

Bugs surfaced while reviewing. Some are mobile, some are pre-existing desktop bugs
noticed along the way; all get fixed in Pass 2 unless pulled forward.

| # | Bug | Where | Scope |
|---|-----|-------|-------|
| BUG-1 | "Feedback loop" (`.loop`) connector arrows **overlap the text** when stacked | Coaching, mobile | mobile (A3) |
| BUG-2 | **Breadcrumbs (`.article-breadcrumb`) have an extra gutter** — don't align to content/logo gutter | article posts, desktop | desktop (X11c) |
| BUG-3 | Featured eyebrow + category label (e.g. **"— FEATURED TEST — UX INDUSTRY"**) **not coloured to the category colour** — should inherit `--c-current` | editorial index (`/fancy`), all widths | desktop bug, unrelated to mobile |

---

## Pass-2 handoff (filled when Pass 1 is done)

Once every section above reads ✅, the decisions get distilled into the Phase 23.2
build order + `MOBILE-AUDIT.md` (the view × breakpoint test checklist + bug log).
