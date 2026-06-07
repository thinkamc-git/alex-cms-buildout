# Design System Showcase — Information Architecture

**Status:** proposal for review (2026-06-05). Grounds the `/_ds/` showcase IA in
how established design systems are actually organized, rather than ad-hoc.

---

## 1. How real design systems are organized (research)

Looked at Shopify Polaris, IBM Carbon, Atlassian, and Material Design 3.

**Top-level spine is near-universal** — the same 3 sections appear everywhere,
in this order, getting more composed as you go:

| Real systems | Top-level sections |
|---|---|
| **Polaris** | Foundations · Content · Components · Patterns |
| **Carbon** | Foundations · Components · Patterns · Guidelines |
| **Atlassian** | Foundations · Components · Patterns · Content |
| **Material 3** | Foundations/Styles · Components · Guidelines |

→ The convention: **Foundations → Components → Patterns → (Content/Guidelines) → Resources.**
Foundations is *always its own top section* (it's the tokens reference everyone
opens first); it is never nested under Components.

**Components are sub-grouped by FUNCTION, not by file or by visual type.**
Material 3's canonical groups (echoed across systems):

> **Actions · Communication (feedback) · Containment · Navigation · Selection · Text inputs**

The guiding principle from every source: **group by what a user looks for / by
use-case**, with clear labels — not by implementation detail.

Sources: [Polaris](https://polaris-react.shopify.com/), [Material 3 Components](https://m3.material.io/components), [Atlassian](https://atlassian.design/components), [Carbon](https://carbondesignsystem.com/), [Sparkbox — layers of a design system](https://sparkbox.com/foundry/design_system_makeup_design_system_layers_parts_of_a_design_system), [UXPin — documentation guide](https://www.uxpin.com/studio/blog/design-system-documentation-guide/).

---

## 2. Audit of our current IA

Current tabs: **Components (Foundations + Elements + Cards) · Sections · Templates · CMS · CSS Library**.

| Item | Verdict |
|---|---|
| "Patterns"/"Pages" as names | ✅ on-convention (we're renaming Sections→Patterns, Templates→Pages) |
| **Foundations merged into Components** | ⚠️ **off-convention** — every major system keeps Foundations its own top section |
| Components grouped as "Elements + Cards" | ⚠️ ad-hoc — convention groups components **by function** (Actions / Inputs / Status / Containment / Navigation); Cards = a Containment group |
| **CMS** as its own top section | ➕ non-standard but **justified** — we have two products (public site + admin); keeping the admin DS separate is a deliberate, defensible split |
| **CSS Library** (by CSS file) | ➕ non-standard — real systems expose code via a per-component "Develop" tab or a "Resources/Code" area, not a file-indexed tab. Keep it as our dev lens, but know it's an addition, not a convention |

---

## 3. Recommended IA (6 tabs — at the cap, convention-grounded)

| # | Tab | Sub-nav (left) | Basis |
|---|---|---|---|
| 1 | **Foundations** | Colour · Typography · Spacing · Radii & Elevation · Motion | universal |
| 2 | **Components** | **Actions** (Buttons & CTAs) · **Inputs** (Form Fields · Filter Controls) · **Status & Feedback** (Pills/Badges · Tags · Category Labels) · **Navigation** (Nav · Top Bar) · **Containment** (Content Cards · Dividers & Headers) | Material-style functional grouping |
| 3 | **Patterns** | Heroes & Statements · Content Blocks · Page Chrome (Top Bar/Footer) · Dividers | composed page sections (was "Sections") |
| 4 | **Pages** | Marketing (Landing · Services · About · Resume) · Content (Article · Index · Editorial) | full page templates (was "Templates") |
| 5 | **CMS** | Actions · Inputs · Status & Stage · Containment (Tables · Cards) · Assembled Layout | the admin mini-DS, by function; one shared source w/ the in-CMS viewer |
| 6 | **CSS Library** | Root · Pages · Blocks · CMS | the technical/by-file lens (our addition) |

**Key decision:** keep **Foundations** as its own top tab (convention) vs. the
current merge into Components. Recommendation: **separate** — it's what every
real system does and it's the most-referenced page.

**Open naming choices:** Patterns vs "Page Blocks"; whether "Content Cards" sit
under Components/Containment (recommended) or get their own tab.

---

## 4. Applied tab + "Future Components" (added 2026-06-06)

**Applied is a sandbox** — the design system used in contexts *beyond the website*
(CMS admin, coaching dashboard, analytics, mobile app). These explorations surface
**new components that are not in the production DS**. That's allowed, but governed:

- **Each Applied example is a self-contained iframe page** (`_design-system/showcase/*.html`)
  that loads the canonical DS CSS **plus any new/"future" component styles scoped to
  that page only**. New-component CSS NEVER goes into the production slices
  (`tokens/pages/blocks/cms.css`) until it's been promoted.
- **"Future Components"** is a section *inside* Applied that catalogues the new
  components these explorations introduce — candidates for later promotion into the
  real DS (at which point they get real slice CSS + a Components entry).
- Examples: **CMS Panel** = the real CMS admin assembled from existing components
  (`showcase/cms-panel.html`, isolated because `style-cms.css` owns `.topbar`/`.sidebar`).
  Coaching Dashboard / Analytics / Mobile = explorations that may introduce future
  components.

**Build method — parity first, then promote (Alex, 2026-06-06):**
1. **Parity:** when an Applied example uses something that's basically an existing
   component, align it to the *real* component — don't reinvent a near-duplicate.
2. **Define genuinely-new things as Future Components:** only the truly new
   elements become Future Components. Example: **Analytics View introduces a
   slightly-new card style + progress bars** → those are defined as new components
   (scoped CSS in the example page, catalogued under Future Components), as
   candidates to promote into the real DS later.

**This protects production CSS:** exploratory styling is quarantined in Applied's
iframe pages; nothing leaks to the live site.

> **Codified (2026-06-06):** the management rule for future components — page-scoped
> `<style>` per concept, `fc-` prefix, tokens-only, documented per section, promote
> via Stage 2 — now lives in `docs/BUILD-DISCIPLINE.md` §6.1. Build to that.

## 5. Existence audit — TODO (flagged 2026-06-06)

After Applied, audit for things that EXIST in the CSS/markup but aren't documented
in the showcase, e.g.:
- the **header/footer "dot"** (the `--dot-grid` / dot-surface treatment, separator dots)
- a **list of icons** used across components (nav icons, action icons, the inline SVGs)
- any other selectors/treatments present in the slices but missing from the tabs.

---

## 0. PURPOSE & PHILOSOPHY OF THE DESIGN SYSTEM (from Alex, 2026-06-06)

> **Read this first.** This is the *why* behind the whole `/_ds/` showcase and
> every word written into it. It was lost across earlier compactions, which led
> to hollow "vibe" copy. It is the grounding for all DS messaging.

**Purpose — twofold:**
1. A **structured system for building** alexmchong.ca and its parts — the CMS and
   future tools. Constraints that create the freedom to maintain.
2. A **public showcase of a systematic way of building** — shown, not just used.

**What makes it real (the load-bearing distinction):**
- It **pulls from the real, live CSS**. It is **referenceable**, not an aspirational
  style guide; when the CSS changes, the showcase reflects it live. It is a source
  of truth that stays true. This is the opposite of vibe-coding / vibe-design.

**Aesthetic & philosophy:**
- A structured, **Bauhaus** approach to the **basics of materiality** — texture,
  structure, typography. **It does NOT mimic paper** (correction to earlier drafts).
- Lineage of **1980s corporate computer manuals / brand guidelines**; the grey
  alludes to that classic manual. It moves **off soulless fonts** to carry
  personality, so the **message leads**.
- A deliberate **stance against contemporary theatrics** — loud colour, bold lines,
  thick text. *"Thick text doesn't communicate boldness if the message is thin."*
- Reflects Alex as a **systems thinker**: structured and thoughtful, not theatrical.
  **Authentic**, against portfolios that are vibecoded smokescreen.

**Working process (Alex, 2026-06-06):** NO vibe iteration (implement → react).
Always **outline / spec → confirm direction → implement**. Simpler plans, test on
direction first.

---

## 6. LOCKED PLAN — Phase 22.6b remaining (2026-06-06)

> Captured before a context compact. This section is the source of truth for
> resuming. Nothing here is prod-shipped: **staging (`/_ds/`) is Alex's working
> reference; do NOT prod-ship 22.6b until the whole plan is done.**

### Current state (done, on staging)
- **7 tabs:** Foundations · Components · Patterns · Pages · CMS · Applied · CSS Library
  (over the 6 soft-cap; accepted for now — may merge later).
- Sub-navs grouped by function; scroll fixed (stage `overflow-y:auto` + JS-fit iframes).
- Content refreshed: **event cards** (past-event hatch/clock) + **experiment constellations** match prod.
- **CMS** uses a single shared source `showcase/cms.html` (CMS tab + CSS Library CMS slice + in-CMS viewer); **CMS Panel model built** at `showcase/cms-panel.html` (real CMS, isolated).
- Showcase load motion (cascade reveal) added.

### Remaining work (the plan)
- **A · Foundations → Welcome section** ✅ DONE (on staging, 2026-06-06). Final approved copy is live in `index.html` #comp-welcome — lead + how-it-works (live/referenceable, constraints→freedom) + 2 philosophy paras (Bauhaus materiality, 80s brand-guideline structure not paper, grey=computer manual, message-over-theatrics "thick type doesn't make a point bold if the point is thin", systems thinker, anti-vibecoded-portfolio) + brief at-a-glance + purpose/map close. Voice: non-first-person. Built spec-first per [[feedback_no_vibe_spec_first]].
- **B · CMS tab → rich documentation.** Rework to read like the **Components** tab — components shown *in context with writing/usage*, NOT class-name boxes. (Stays an isolated iframe; `style-cms.css` collides with showcase chrome.) The terse box-catalog belongs in **CSS Library → CMS slice**, not the CMS tab.
- **C · Applied → light concept showcases.** Inspiration only — capture the *essence*, not a functional UI (depth = today's CMS Panel / Coaching). Each concept = one section, its own isolated `showcase/*.html` (DS CSS + page-scoped future CSS, never in production slices). **New components are documented inside their own concept's section** (not a separate global catalog). Parity first: reuse real components; only genuinely-new things get called out as new (e.g. Analytics' card + progress bars). Split a concept in two if it gets heavy.
  - Set: refresh **CMS Panel (✓ built), Coaching, Analytics, Mobile** + add **Command Center (View A)** + **Together (View B)** (specs below).
- **D · In-CMS viewer** (`/cms/design-system`) = the **CSS Library** (4 by-file slices: Root/Pages/Blocks/CMS) **+ a 5th tab that launches the full `/_ds/` DS**. Nothing else. (Currently it iframes cms.html — needs rebuilding to this.)
- **E · Existence audit** — document what EXISTS but isn't shown: header/footer **dot / dot-surface** (`--dot-grid`), an **icon list** (nav + action inline SVGs), other undocumented selectors.
- **F · Close 22.6** — annotate DS-AUDIT, check §3 box, then the **gated prod ship** (backup + deploy), only once A–E are done.

### A · Welcome section — extracted insight + draft
Insight pulled from Alex (2026-06-06):
- **Feeling (all four):** timeless & archival · bold & opinionated · calm & deliberate · crafted & tactile → synthesis = **"quietly bold."**
- **The foil (what to move away from):** coldness / soulless minimalism · trend-chasing / disposable · decorative noise. **NOT** anti-SaaS-sameness (he did not pick that).
- **Print DNA to carry:** paper texture & ink · editorial measure & rhythm · rules/lines as structure. (Less about rigid grid.)
- **Personal:** the **writer / coach / designer tension** is the soul of it.

**WRITING TONE RULES (Alex, 2026-06-06):**
- **No "good" moral dichotomies** (don't frame as good-vs-bad).
- **Voice: artist statement** — confident, clear, evocative; state what the work *is* and trust it (not a defensive justification).
- **No comparative negation** — avoid "X over Y", "rather than", "instead of", "avoids/not". State what it **IS**, declaratively.

**Draft copy (revised to the tone rules — for review):**

> ## How this is built
> This is a design system made the way books are made — with structure, restraint, and a point of view. Its roots are in the Bauhaus and in the printed design manuals of the 1980s and early '90s: work built for ink and paper, brought whole to the screen.
>
> It is quietly bold. The type is confident, the contrast is real, and everything is given room to breathe. It is meant to feel made, and meant to last.
>
> The page behaves like paper. A faint texture and ink-dark type give the screen a surface; hairline rules do the organizing; line length, spacing, and rhythm are measured so the work reads like a printed page.
>
> Three sensibilities hold it together: the writer, who lets language lead; the coach, who keeps it calm and intentional; the designer, who keeps it disciplined. The system lives where they meet.

**Open question:** voice — keep the confident **system voice** (above) or shift to **first-person (Alex)**? (Undecided.)

### C · New Applied views — specs
**View A — Command Center** (personal life-rhythm surface). Essence: "what am I oriented toward, and how am I moving" — calm, accomplishment-first.
- Zones: Season banner (current season + its 6 intentions) · Cadence spine (Season›Month›Week) · This Month · This Week (active) · Rituals (open/close week·month·season) · Reflected progress (accomplishment, narrative).
- Parity: serif statements, cards, tags/pills, section headers + dividers, buttons, meta.
- New components (kept in this section): `Intention Card` · `Cadence Nav` · `Ritual Prompt` · `Accomplishment Meter`.

**View B — Together** (private shared dashboard for two). Essence: staying aligned as an act of love.
- Zones: Paired presence (both people + each other's energy) · Shared week · Shared projects & plans · Touchpoints.
- Parity: circular author avatar, cards, week layout, pills/tags, section headers.
- New components: `Energy Indicator` · `Paired Presence` · `Shared Project Card`.

Shared-across-views future components: `Accomplishment / progress bar` (Analytics + Command Center), `Energy Indicator` (Together + Command Center).

---

## 7. SITE-vs-SHOWCASE AUDIT — gap list (2026-06-06)

> The real site has moved ahead of the showcase. Full read-only audit done 2026-06-06.
> This is the spec for the new **AUDIT** step (added before B). Source files are
> reference-only; the showcase to update is `site/_design-system/index.html`.

**Priority 1 — missing product features:**
- **Article Hero Image** — renders between byline and body; sizes default/wide/full + optional caption. Real: `site/templates/partials/block-hero-image.php`, `article-standard.php:58`, `css/public/blocks.css:417-474` (`.article-hero[data-size]`). → Patterns ▸ Heroes & Statements (new subsection, 3 size variants ± caption).
- **Editorial Index full-bleed Hero** — 4+ variants: `--bleed-dark`, `--bleed-light`, `--within` (+ `--bg-surface`/`--bg-transparent`, with `editorial-hero-thumb` image OR `editorial-hero-card` series card), `.is-solo`. Real: `index-editorial.php:102-208`, `blocks.css:1399-1585`. → Patterns ▸ new "Editorial Index Heroes" subsection (all variants + responsive collapse).
- **Icons** — no icon inventory exists. 7+ inline SVGs: bookmark `.bm` (24×24, all card headers), journal category glyphs introspection/contemplation/insight (14×14, colour = `var(--c-…)`), clock/past-event (24×24), experiment constellation bg (380×320, seeded), plus unicode `→ ◷ ○`. Real: `index-card.php:67,186-211,315,363,452-463`. → Foundations ▸ new "Icons" section (each icon, viewBox, usage, colour mapping).

**Priority 2 — underrepresented:**
- **Per-section Filter Pills (editorial)** — visitor type/category filters, OR-logic, "All" reset. Real: `index-editorial.php:88-100`, `blocks.css:1358-1385` (`.index-section-pills .fp`). → Components ▸ Nav & Filtering (distinct from global nav).
- **Carousel display mode** — `.cards-grid.is-carousel` (`blocks.css:1600-1641`). → Pages ▸ Full Page Index note.
- **Series watermark on cards** — two-digit position badge (`index-card.php:100-128`, `.card-series-number`). → Components ▸ Article Cards variant.
- **Journal Key Statement block** — `.article-key-statement` (Instrument Serif italic + left rule, replaces title on journal entries). → Patterns/Blocks.

**Priority 3 — clarifications:**
- "Featured Article" pattern is a static card-hero; clarify vs the dynamic editorial hero family.
- Editorial hero responsive collapse / bleed stacking notes.
- `.event-card` (when/where/format white card) distinct from event *cards*.

**Undocumented selector families** (in `blocks.css`, absent from showcase + CSS Library): `.index-page(--editorial/--series)`, `.index-section(--hero/--curated/--feed)`, `.index-section-header.is-big`, `.index-section-pills`, `.editorial-hero*` (~20), `.cards-grid(.is-carousel)`, `.article-hero*`, `.article-key-statement`.

**STATUS (2026-06-06, on staging):** ✅ A Welcome+preview · ✅ Icons · ✅ Article Hero · ✅ Editorial Index Heroes · ✅ Editorial Index refresh (+filter pills) · ✅ P2 (carousel, series watermark, key-statement) · ✅ B CMS tab rich doc (+ cms-classes split) · ✅ D in-CMS viewer (CSS Library 4 slices + launch). ✅ **C Applied** — rebuilt as 6 isolated real-CSS concept iframes (CMS Panel, Coaching, Analytics, Mobile + NEW Command Center & Together); future components page-scoped + `fc-` prefixed per BUILD-DISCIPLINE §6.1. ⏳ **Gated prod ship** only (staging-only until Alex signs off 22.6b). Closing note in `docs/DS-AUDIT.md`.
