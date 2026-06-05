# DS-AUDIT.md — Design-System Reorganization Audit (Phase 22.1)

> **Status:** Complete · **Produced:** 2026-06-04 · **Phase:** 22.1 (audit, no code) · **Feeds:** Phases 22.2 → 22.6 (DS reorg) and 23.2 (mobile)
>
> This is the complete map of the existing CSS surface. Every selector across the four CSS families and eight inline `<style>` blocks is categorised into the target three-branch architecture, drift against the design-system reference is reconciled, variant families are given a trim plan, repeated-HTML component candidates are catalogued, and the public surface is audited for mobile breakage. The "Ready for Phase 22.2" checklist at the bottom is the handoff.

---

## How to read this document

| Section | What it gives the next phase |
|---|---|
| §1 Headline findings | The five things that justify the whole project. Read first. |
| §2 Scope reconciliation | What was actually audited vs. what the brief named (the brief is stale on one path). |
| §3 Target architecture + naming | The file tree the migration builds toward, plus the locked naming convention. |
| §4 Naming key | The unified component systems (buttons, titles, pills, cards) every later phase renames toward. |
| §5 Token-layer audit | The token duplication map + promotion candidates + the only real divergences. **This is Phase 22.2's spec.** |
| §6 Per-file selector tables | Every selector, its bucket, drift status, move-to-path, proposed name. |
| §7 Drift reconciliation | Consolidated list of every live-vs-DS divergence with a proposed canonical value. |
| §8 Variants to collapse | Button / title / pill / card families and the trim plan. Includes the button deep-dive. |
| §9 Component candidates | Repeated HTML patterns (3+ uses) → DS component proposals. |
| §10 Mobile findings | Per-viewport punch list for the public surface. **This is Phase 23.2's input.** |
| §11 Ready for Phase 22.2 | The exit checklist + a per-phase handoff (what 22.2–22.6 each consume from this doc). |

---

## 1. Headline findings

**1.1 The token set is quadruplicated.** Four independent `:root` blocks define the same design primitives:

| Source | `:root` token defs |
|---|---|
| `_design-system/css/tokens.css` (canonical) | 82 |
| `_pages/_layout/style-pages.css` | 53 |
| `_templates/style-articles.css` | 43 |
| `cms/_assets/style-cms.css` | 84 |

The overlap is ~95% identical copy-paste — and it has already begun to drift (see 1.5). This is the entire justification for Phase 22.2's shared `tokens.css`: collapse four copies into one source, and make the per-surface files import it.

**1.2 The `/_ds/` showcase already mixes both branches.** `_design-system/css/` (8 modules, served at `/_ds/`) contains *public* components (`.card`, `.layout-nav`, `.layout-footer`, `.fp`, `.cat-pill`) **and** *CMS* components (`.cms-shell`, `.cms-table`, `.cms-item`, `.topbar`, `.sidebar`) side by side, plus a third group of **showcase-only scaffolding** (`.swatch`, `.spec-grid`, `.usage-card`, `.controller`, `.preview-frame`, `.color-grid`, `.comp-rule`). The public/cms split is therefore a real cut *through the showcase itself*, not just a sorting of the per-surface files. The scaffolding is rebuilt fresh in the Phase 22.6 `/cms/design-system` showcase and should not migrate.

**1.3 Mobile coverage is wildly uneven across the public surface.**

| File | `@media` blocks | Breakpoints |
|---|---|---|
| `style-pages.css` | **1** | `720px` only |
| `style-articles.css` | **14** | `768 / 480 / 900 / 600` + `prefers-reduced-motion` |
| `style-cms.css` | 3 | `prefers-reduced-motion` only (correct — CMS is tablet-only) |

The **marketing pages are the mobile-debt hotspot** (a single 720px cutover, nothing tested below 500px), while the article/index family is comparatively well-covered. This asymmetry is the core of the §10 mobile findings and Phase 23.2's punch list.

**1.4 Buttons mix two type systems inside one bar (locked fix target).** In the CMS, `.btn-pri` / `.btn-sec` / `.btn-danger` use `var(--font-cond)` **UPPERCASE**, while the editor's `.tt-btn` uses sans **lowercase**, and there are ~14 distinct button selectors total with inconsistent padding and size handling. The public side runs a *separate* Barlow-pill system (`.btn-dark` / `.btn-light` / `.btn-ul`). See the §8 button deep-dive for the full resolution.

**1.5 Real token divergences (not just duplication) — these need a canonical decision in 22.2:**

| Token | tokens.css | style-pages | style-articles | style-cms | Call |
|---|---|---|---|---|---|
| `--surface` | `#F8F8F8` | `#F8F8F8` | `#F8F8F8` | **`#FFFFFF`** | CMS uses pure white for cards. Decide: keep one shared `--surface` (off-white) + a CMS `--surface` override, or promote white. **Visible difference — do not silently unify.** |
| `--r-pill` | `4px` | `4px` | `4px` | **`3px`** | CMS pills 1px sharper. Trivially unify to `4px` unless intentional. |
| `--r-tag` | **(absent)** | `3px` | `3px` | (absent) | Used by `.tag`/pills on public side; missing from canonical. Promote `--r-tag: 3px` to tokens.css. |
| `--radius-md`, `--radius-sm` | **(absent)** | — | — | referenced by `tiptap.css`, **defined nowhere** | **BUG:** dangling `var()` → editor corners fall back to 0 radius. Define in tokens.css (or system-cms) during 22.2/22.5. |
| `--white` | `#ffffff` | `#FFFFFF` | `#FFFFFF` | `#FFFFFF` | Case only; functionally identical. Normalise. |

**CMS-only tokens to promote to tokens.css** (defined only in `style-cms.css`, used by real admin): `--canvas-raised` (`#ECEAE8`), `--ink-16` (`rgba(25,23,21,0.16)`), `--ink-20` (`rgba(25,23,21,0.20)`). **CMS-domain tokens to keep in the CMS branch** (no public meaning): `--sidebar-w` (`210px`), `--topbar-h` (`48px`), the 9 `--stage-*` / `--type-*` colours, and the dot-grid SVG data-URI.

---

## 2. Scope reconciliation (brief is stale on one path)

The Phase 22.1 brief lists `_design-system/system.css` as a file in scope. **That file no longer exists** — Phase 2 ("CSS module split + dynamic `/_ds/`") split it into **8 modules** under `_design-system/css/`:

```
_design-system/css/  →  tokens.css · base.css · typography.css · shell.css ·
                        components.css · status.css · tables.css · views.css   (854 lines)
```

This audit treats those 8 modules as the canonical DS reference. **Action for the BUILD-PLAN:** when Phase 22.2 opens, update the §26/§27 briefs to say `_design-system/css/*` instead of `system.css`.

**Files actually audited (real surface — 6,783 lines of CSS + 8 inline blocks):**

| File | Lines | Role |
|---|---|---|
| `_design-system/css/*` (8 modules) | 854 | Canonical DS reference / `/_ds/` showcase (public + cms + scaffolding) |
| `_pages/_layout/style-pages.css` | 1,448 | Marketing pages |
| `_templates/style-articles.css` | 1,704 | Article-family templates + blocks + index pages |
| `cms/_assets/style-cms.css` | 2,290 | Admin panel (769 selector-lines across 8 layers) |
| `cms/_assets/tiptap.css` | 192 | Tiptap editor chrome |
| Inline `<style>` in 8 views | ~178 | post-template, page-edit, navigation, redirects, subscribers, post-preview ×2, post-template-preview |

**Two files surfaced that the brief did not name** — classified here:
- `cms/_assets/tiptap.css` (192) → **system-cms** (editor sub-section). In scope; included.
- `site/ux2.0/how-we-got-here/style-custom.css` (295) → **out of scope.** It is a one-off standalone microsite under `site/ux2.0/`, not part of the CMS, the marketing pages, or the article templates. Not migrated by 22.2–22.6. Flagged here so it isn't mistaken for a missed surface.

---

## 3. Target architecture + naming convention

### 3.1 The three-branch file tree (built by 22.2–22.6)

```
site/_design-system/css/
├── tokens.css           ← SHARED. The single source of all primitives.        [Phase 22.2]
│                           (colors, fonts, type scale, spacing, radii, shadows,
│                            rules, category colors; + promoted --canvas-raised/
│                            --ink-16/--ink-20/--r-tag/--radius-*)
├── system-public.css    ← Public DS. Two regions:                              [22.3 + 22.4]
│     ├─ region: Pages    (marketing-page primitives — from style-pages.css)    [Phase 22.3]
│     └─ region: Blocks   (article/index/card/editorial templates —             [Phase 22.4]
│                          from style-articles.css + DS views.css .card family)
└── system-cms.css       ← Admin DS (from style-cms.css + tiptap.css +          [Phase 22.5]
                           the 8 inline <style> blocks + DS shell/tables/status)

Per-surface files become thin import shells:
  _pages/_layout/style-pages.css   →  @import tokens.css; @import system-public.css;  (+ page-only leftovers)
  _templates/style-articles.css    →  @import tokens.css; @import system-public.css;
  cms/_assets/style-cms.css        →  @import tokens.css; @import system-cms.css;

Deleted at sunset (Phase 22.6): the duplicate :root blocks, the inline <style> blocks,
and the showcase-scaffolding selectors (rebuilt at /cms/design-system).
```

> **✅ MIGRATED — Phase 22.5 (2026-06-05, staging-only).** `system-cms.css` barrel
> shipped at `_design-system/css/system-cms.css` — a thin `@import` of `tokens.css`
> + the 7 CMS slices (`base, typography, shell, components, tables, status, views`),
> mirroring `system-public.css`. **Reconciliation:** the original brief assumed the
> CMS DS had to be *built* from inline `<style>` blocks into 6 new files. In fact the
> CMS DS already existed, decomposed, as those 7 modules under `/_ds/css/` (loaded
> individually by every view) + `style-cms.css` overrides — so 22.5 only had to wrap
> them in a barrel, not author them. `style-cms.css` and `tiptap.css` stay loaded as
> the override layer; folding those in + thinning `style-cms.css` to the import-shell
> above is **22.6**. **Dogfood:** the new in-CMS viewer at `/cms/design-system` is the
> barrel's first (and only, this phase) consumer — it loads `system-cms.css` alone,
> proving the barrel resolves before 22.6 flips the remaining ~30 views. The other
> views were left **byte-untouched** (zero-regression by construction). The viewer is
> a *lean* catalogue (tokens · buttons · pills/badges · tags · fields · table · cards
> · titles, each with class name + Root/CMS slice tag); the exhaustive showcase +
> scaffolding rebuild remain 22.6.

### 3.2 Buckets (locked) → move-to-path

| Bucket | Meaning | Move-to-path |
|---|---|---|
| `tokens` | Design primitive / `:root` custom property | `_design-system/css/tokens.css` |
| `system-public` | Anything a public visitor sees (pages, articles, index, cards, header/footer) | `_design-system/css/system-public.css` (region: **Pages** or **Blocks**) |
| `system-cms` | Admin chrome, tables, forms, editor, status/stage, kanban, rowforms | `_design-system/css/system-cms.css` |
| `Dead` | Unused / superseded — grep-verified zero callsites | (delete) |

> **Dead-code result:** zero dead selectors found across all four families. Every selector has a callsite in a `.php`/`.html`. The only "delete" targets are the **duplicate token blocks** and the **showcase-only scaffolding** (which is intentionally rebuilt, not migrated).

### 3.3 Naming convention (locked)

Semantic component + **`.component--modifier`** + **`.component__element`**. Not BEM-with-namespacing-everywhere, not utility-first. Examples: `.card`, `.card--featured`, `.card__title`.

**Cross-branch rule (from the brief):** when a thing is genuinely shared, promote it to `tokens.css` if it's a token; otherwise **duplicate intentionally per branch** (clarity > deduplication). The public `.btn` and the CMS `.btn` are deliberately two different components living in two files — they look and behave differently and should not be forced into one.

---

## 4. Naming key — the unified component systems

These are the canonical names every later phase renames toward. Where a current selector maps to one of these, the per-file tables (§6) reference it.

### 4.1 Buttons — TWO intentional systems

**Public (`system-public.css`, Barlow, pill, marketing voice):**
| New | From |
|---|---|
| `.btn` (base) + `.btn--dark` | `.btn-dark` |
| `.btn--light` | `.btn-light` |
| `.btn--underline` | `.btn-ul`, `.cta-primary` |
| `.btn-row` / `.btn-stack` (layout) | keep |

**CMS (`system-cms.css`, Barlow Condensed UPPERCASE, sharp):**
| New | From |
|---|---|
| `.btn` (base) + `.btn--primary` | `.btn-pri`, DS `.cms-btn-pri` |
| `.btn--secondary` | `.btn-sec`, DS `.cms-btn-sec`, `.btn-ghost` (alias — drop) |
| `.btn--danger` | `.btn-danger`, `.cat-del`, `.btn-row-del` |
| `.btn--icon` (+ `.btn--icon-danger`) | `.btn-icon`, `.btn-icon-danger` |
| `.btn--sm` (size modifier) | `.btn-tiny`, `.row-btn`, `.btn-add-dashed` |
| `.btn--editor` (toolbar; **exception:** stays sans, lowercase) | `.tt-btn` |
| `.btn--figure-toggle` | `.tt-fig-btn`, `.figure-size-toggle button` |

**Resolution of the font-mixing problem:** all CMS action buttons become **condensed uppercase**. The only sanctioned exception is the editor toolbar (`.btn--editor` / `.tt-btn`), which stays sans-lowercase because it is a typing surface, not an action bar — document that exception explicitly.

### 4.2 Titles
| New | From | Note |
|---|---|---|
| `.page-title` (CMS) | `.cms-page-title` (26px raw), `.view-title`, `.pipeline-title` | Unify to one canonical size; `.cms-page-title`'s raw `26px` → token (`--text-h3` 28px or new `--text-page-title`). |
| `.section-title`, `.section-label` (CMS) | keep | |
| Public display roles | `.statement`, `.page-header__title`, `.article__title`, `.index-page__title`, `.editorial-hero__title` | Distinct display tiers — kept separate, not collapsed. |

### 4.3 Pills / tags / status
| New | From |
|---|---|
| `.pill` + `--info` / `--warn` / `--override` / `--past` / `--new` | `.pill.pill-info`, `.pill-warn`, `.pill-override`, `.pill-past`, `.pill-new` |
| `.status--published` / `--draft` / `--scheduled` | `.st-pub` / `.st-dft` / `.st-sch` |
| `.cat-pill` (category colour pill) | keep (drives via `--c-current`) |
| `.tag` (public meta tag) | keep; **consolidate** `.article-tag` + `.article-special-tag` into it |
| `.filter-pill` | `.fp` (+ a `.filter-pill--neutral` modifier for the `.index-section-pills .fp` override) |
| `.content-type-pill`, `.cat-chip`, `.pill--count` (`.nav-count`) | keep / rename as noted |

### 4.4 Cards
| New | From | Note |
|---|---|---|
| `.card` + `--article` / `--journal` / `--event` / `--experiment` / `--masterclass`; elements `.card__title` / `__excerpt` / `__meta` / `__tag` | DS `views.css` `.card` family (canonical) | The public post-card. Canonical lives in the Blocks region. |
| `.pub-card` → keep as **CMS** `.card--published` family | `.pub-card*` (style-cms) | Admin published-content grid; distinct from public `.card`. |
| `.kanban-card` | `.kcard`, `.idea-card` | CMS Draft-Writing / ideation board. |
| `.event-card` (block) | keep distinct from public `.card--event` | A block *inside* article prose, not a grid card — see §8.4. |

### 4.5 Other CMS components to keep (system-cms)
`.cms-table` (+ `--reference` / `--cat` / `--sub`), `.topbar`, `.sidebar`, `.rowform` (+ `__row`, `__add-row`; variants `--navigation` / `--redirects`), `.cms-publish-box`, `.cms-hero-box`, `.editor-wrap` (`.tiptap-wrap`), `.editor-toolbar`, `.editor-pane`, `.grip-handle`, `.flash` (alert), `.info-box`.

---
## 5. Token-layer audit — Phase 22.2 spec

After 22.2, `tokens.css` is the **only** `:root` block in the system; the three other `:root` blocks are deleted and their files `@import` tokens.css.

### 5.1 What `tokens.css` already holds (82 defs — keep as canonical)
Foundation colours (`--primary`, `--secondary`, `--muted`, `--neutral`, `--surface`, `--canvas-bg`, `--accent`, `--white`); ink tints (`--ink-08/12/18/30/72`, `--ink-mid`, `--ink-faint`); white tints (`--white-06/15/45/85`); rules (`--rule`, `--rule-30`, `--rule-faint`); fonts (`--font`, `--font-cond`, `--font-serif`, `--font-mono`); the full type scale (`--text-display` 54 → `--text-micro` 9); the full spacing scale (`--space-4` → `--space-96`); radii (`--r-card` 4, `--r-pill` 4); shadows (`--shadow`, `--shadow-h`); `--max`; `--primary-hover`; the 18 category colours (`--c-rust` … `--c-rose`) + `--c-experiment-prototype/-concept`; focus (`--c-focus`, `--focus-ring`, `--focus-ring-off`).

### 5.2 Promote INTO tokens.css during 22.2 — ✅ DONE (2026-06-04)
| Token | Value | Source | Why |
|---|---|---|---|
| `--r-tag` | `3px` | style-pages + style-articles | Used by public `.tag`/pills; canonical lacked it. ✅ added |
| `--space-40`, `--space-56`, `--space-80` | `40/56/80px` | style-pages + style-articles | **Found during 22.2 gap analysis** — public surfaces reference these but tokens.css's scale stopped at 32/48/64/96. Required before the 22.3/22.4 import-shells, else layout breaks. ✅ added |
| `--canvas-raised` | `#ECEAE8` | style-cms | Sidebar/filter-bar raised surface; shared-worthy. ✅ added |
| `--ink-16` | `rgba(25,23,21,0.16)` | style-cms | Faint divider tint. ✅ added |
| `--ink-20` | `rgba(25,23,21,0.20)` | style-cms | Clickable-row hover outline. ✅ added |
| `--radius-md`, `--radius-sm` | `6px` / `3px` (decision 22.1 #2) | referenced by tiptap.css, defined nowhere | **Fixed the dangling-token bug.** Editor corners rendered square. ✅ added |

tokens.css is now **91 tokens** (was 82). Change was purely additive — no `/_ds/` module references any new token, so zero showcase rendering change; public/CMS surfaces don't load tokens.css yet, so zero impact there.

### 5.3 Resolve divergences during 22.2 (canonical decision required)
- **`--surface`** — `#F8F8F8` (public) vs `#FFFFFF` (CMS). *Recommendation:* keep `--surface: #F8F8F8` shared; give the CMS branch a scoped `--surface: #FFFFFF` override (or a dedicated `--surface-cms`) rather than changing the public value. **Visible if mishandled — diff the CMS card surfaces after 22.5.**
- **`--r-pill`** — `4px` vs `3px`. *Recommendation:* unify to `4px`; the CMS 3px is almost certainly incidental drift.
- **`--white`** — normalise case to `#ffffff`.

### 5.4 Keep in the CMS branch (CMS-domain, not shared)
`--sidebar-w` (210px), `--topbar-h` (48px), the 9 `--stage-*` / `--type-*` colours, the dot-grid SVG data-URI. These have no public meaning; they live in `system-cms.css` (or a clearly-labelled CMS-tokens block at its top), not in shared `tokens.css`.

### 5.5 CMS/editor dangling tokens — Phase 22.5 must resolve (found during 22.2)
Referenced in `style-cms.css` / `tiptap.css` but **defined nowhere** (currently fall back to invalid → 0 / browser default). Not fixed in 22.2 (CMS doesn't load tokens.css yet); 22.5 must either define them in the CMS branch or snap to the shared scale:
- **`--space-6` (6px), `--space-10` (10px)** — used widely in style-cms (`.field-hint`, `.row-actions`, gaps) + tiptap. Off the 4-step rhythm; decide: add as CMS-domain tokens, or snap 6→`--space-8` / 10→`--space-12` during cleanup.
- **`--font-sans`** — tiptap toolbar font; tokens.css has `--font` (Barlow), not `--font-sans`. Editor toolbar currently falls back to system sans. Map to `--font`.
- **`--text-small`** — tokens.css has `--text-sm`; likely a typo. Map to `--text-sm`.
- **`--live-green`** — defined in style-cms.css (CMS-domain); keep in CMS branch.

---

## 6. Per-file selector tables

Locked column format: `Selector · File · Bucket · Drift · Move-To-Path · Proposed name/Notes`. Tokens are pulled out into per-file token sub-tables (they all collapse into §5's shared `tokens.css`). Drift values: `matches DS` / `drifts from DS` / `no DS equivalent`.

### 6.1 — `_design-system/css/*` (canonical DS modules / showcase)

#### Design System CSS Audit — 8 Modules

##### tokens.css

| Selector | File | Bucket | Drift | Move-To-Path | Proposed name + note |
|---|---|---|---|---|---|
| :root | tokens.css | tokens | matches DS | tokens.css | All token definitions (23 primary groups) |

##### base.css

| Selector | File | Bucket | Drift | Move-To-Path | Proposed name + note |
|---|---|---|---|---|---|
| *, *::before, *::after | base.css | system-public | matches DS | system-public.css | Global reset |
| html | base.css | system-public | matches DS | system-public.css | Root height |
| body | base.css | system-public | matches DS | system-public.css | Global body typography + background grid |
| :focus | base.css | system-public | matches DS | system-public.css | Focus outline removal |
| :focus-visible | base.css | system-public | matches DS | system-public.css | Keyboard focus ring (global) |
| input:focus-visible, select:focus-visible, textarea:focus-visible | base.css | system-public | matches DS | system-public.css | Form focus ring (tighter offset) |
| main:focus, main:focus-visible | base.css | system-public | matches DS | system-public.css | Skip-link target suppression |
| .skip-link | base.css | system-public | matches DS | system-public.css | Skip-to-content link (a11y) |
| .skip-link:focus, .skip-link:focus-visible | base.css | system-public | matches DS | system-public.css | Skip-link focus state |

##### typography.css

| Selector | File | Bucket | Drift | Move-To-Path | Proposed name + note |
|---|---|---|---|---|---|
| .section-heading | typography.css | system-public | matches DS | system-public.css | Document section heading (Barlow 48px) |
| .section-heading .m | typography.css | system-public | matches DS | system-public.css | Muted word inside section heading |
| .section-intro | typography.css | system-public | matches DS | system-public.css | Serif italic intro (h3-like) |
| .section-body | typography.css | system-public | matches DS | system-public.css | Body copy (17px) |
| .m | typography.css | system-public | matches DS | system-public.css | Muted word utility |
| .ac | typography.css | system-public | matches DS | system-public.css | Accent text utility (sage) |
| .type-row | typography.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — type scale showcase row; rebuilt in Phase 22.6 showcase |
| .type-meta | typography.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — type row metadata column |
| .type-role | typography.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — type role label (mono) |
| .type-spec | typography.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — type specification details (mono) |

##### shell.css

| Selector | File | Bucket | Drift | Move-To-Path | Proposed name + note |
|---|---|---|---|---|---|
| .topbar | shell.css | system-cms | matches DS | system-cms.css | Sticky tabbar + brand header (admin chrome) |
| .topbar-tabsbar | shell.css | system-cms | matches DS | system-cms.css | Horizontal tab container |
| .topbar-tabsbar-tab | shell.css | system-cms | matches DS | system-cms.css | Individual tab link (inactive state) |
| .topbar-tabsbar-tab.active | shell.css | system-cms | matches DS | system-cms.css | Active tab state (underline) |
| .topbar-tabsbar-tab:not(.active):hover | shell.css | system-cms | matches DS | system-cms.css | Inactive tab hover |
| .topbar-brandtitle | shell.css | system-cms | matches DS | system-cms.css | Brand title left of tabs (mono +serif em) |
| .topbar-brandtitle em | shell.css | system-cms | matches DS | system-cms.css | Serif italic brand emphasis |
| .workspace | shell.css | system-cms | matches DS | system-cms.css | Per-tab workspace container |
| .workspace.active | shell.css | system-cms | matches DS | system-cms.css | Active workspace layout (flex row) |
| .sidebar | shell.css | system-cms | matches DS | system-cms.css | Navigation sidebar (168px left rail) |
| .sidebar-group | shell.css | system-cms | matches DS | system-cms.css | Grouped nav items container |
| .sidebar-group-label | shell.css | system-cms | matches DS | system-cms.css | Nav group label (uppercase) |
| .sidebar-link | shell.css | system-cms | matches DS | system-cms.css | Individual nav link |
| .sidebar-link:hover | shell.css | system-cms | matches DS | system-cms.css | Nav link hover state |
| .sidebar-link.active | shell.css | system-cms | matches DS | system-cms.css | Active nav link (left border highlight) |
| .canvas | shell.css | system-cms | matches DS | system-cms.css | Main content area (flex 1, scrollable) |
| .section | shell.css | system-cms | matches DS | system-cms.css | Canvas section block |
| .section-header | shell.css | system-cms | matches DS | system-cms.css | Section header with bottom rule |
| .section-num | shell.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — section number eyebrow (mono) |
| .section-title | shell.css | system-cms | matches DS | system-cms.css | Section title (h3-like, -0.01em tracking) |
| .section-desc | shell.css | system-cms | matches DS | system-cms.css | Section description paragraph |
| .section-label | shell.css | system-cms | matches DS | system-cms.css | Sub-block eyebrow (uppercase, no rule) |
| .section-header + .section-label, .section > .section-label:first-child | shell.css | system-cms | matches DS | system-cms.css | Reset margin on first section-label |

##### components.css

| Selector | File | Bucket | Drift | Move-To-Path | Proposed name + note |
|---|---|---|---|---|---|
| .row-between | components.css | system-public | matches DS | system-public.css | Flex row (space-between) — layout utility |
| .row-center | components.css | system-public | matches DS | system-public.css | Flex row (centered) — layout utility |
| .row-end | components.css | system-public | matches DS | system-public.css | Flex row (flex-end) — layout utility |
| .col | components.css | system-public | matches DS | system-public.css | Flex column — layout utility |
| .two-col | components.css | system-public | matches DS | system-public.css | Two-column grid (1fr 1fr, 64px gap) |
| .layout-nav | components.css | system-public | matches DS | system-public.css | Public site header nav (brand + links) |
| .layout-nav-logo | components.css | system-public | matches DS | system-public.css | Site logo serif italic |
| .layout-nav-links | components.css | system-public | matches DS | system-public.css | Nav link container (flex gap-4) |
| .layout-nav-links a | components.css | system-public | matches DS | system-public.css | Nav link pill (mono uppercase) |
| .layout-nav-links a:hover | components.css | system-public | matches DS | system-public.css | Nav link hover (dark bg) |
| .layout-footer | components.css | system-public | matches DS | system-public.css | Public site footer |
| .layout-footer-left | components.css | system-public | matches DS | system-public.css | Footer left copy (condensed uppercase) |
| .layout-footer-right | components.css | system-public | matches DS | system-public.css | Footer right links flex |
| .layout-footer-right a | components.css | system-public | matches DS | system-public.css | Footer link (condensed uppercase) |
| .btn-row | components.css | system-public | matches DS | system-public.css | Button group container (flex, wrap) |
| .btn-dark | components.css | system-public | matches DS | system-public.css | Primary button (filled dark) |
| .btn-dark:hover | components.css | system-public | matches DS | system-public.css | Primary button hover |
| .btn-light | components.css | system-public | matches DS | system-public.css | Secondary button (light fill, dark border) |
| .btn-light:hover | components.css | system-public | matches DS | system-public.css | Secondary button hover |
| .btn-ul | components.css | system-public | matches DS | system-public.css | Underline button (link-like) |
| .btn-ul:hover | components.css | system-public | matches DS | system-public.css | Underline button hover (opacity) |
| .hero-ctas | components.css | system-public | matches DS | system-public.css | Hero CTA button group |
| .cta-primary | components.css | system-public | matches DS | system-public.css | Primary CTA (underline link) |
| .cta-primary:hover | components.css | system-public | matches DS | system-public.css | Primary CTA hover |
| .cta-ghost | components.css | system-public | matches DS | system-public.css | Ghost CTA (text-only) |
| .cta-ghost:hover | components.css | system-public | matches DS | system-public.css | Ghost CTA hover |
| .row-btn | components.css | system-cms | matches DS | system-cms.css | Small row-level action button |
| .row-btn:hover | components.css | system-cms | matches DS | system-cms.css | Row button hover |
| .npbar | components.css | system-public | matches DS | system-public.css | Nav pills bar (flex wrap) |
| .npbar a | components.css | system-public | matches DS | system-public.css | Nav pill link (pill-style button) |
| .npbar a:hover | components.css | system-public | matches DS | system-public.css | Nav pill hover |
| .color-grid | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — 6-column color grid |
| .chip | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — color chip container |
| .swatch | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — color swatch box |
| .cname | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — color name label |
| .ctoken | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — CSS token name (mono) |
| .chex | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — hex value (mono) |
| .comp-section | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — component section block |
| .spec-grid | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — 2-col spec grid |
| .spec-key | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — spec key (mono) |
| .spec-val | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — spec value (mono) |
| .usage-row | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — do/dont row (2-col) |
| .usage-card | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — usage example card |
| .usage-card.do | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — do card border (forest) |
| .usage-card.dont | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — dont card border (terracotta) |
| .usage-label | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — usage label (uppercase) |
| .usage-card.do .usage-label | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — do label color |
| .usage-card.dont .usage-label | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — dont label color |
| .usage-text | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — usage description |
| .states-row | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — state variations flex row |
| .state-item | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — individual state column |
| .state-label | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — state name (uppercase) |
| .comp-rule | components.css | system-cms | matches DS | system-cms.css | SHOWCASE-ONLY — component design rule (border-left) |
| .group-header | components.css | system-cms | matches DS | system-cms.css | Content group header row (flex space-between) |
| .group-header-eyebrow | components.css | system-cms | matches DS | system-cms.css | Group eyebrow label (uppercase) — use --c-current |
| .group-header-link | components.css | system-cms | matches DS | system-cms.css | Group header link (uppercase) |
| .group-header-link:hover | components.css | system-cms | matches DS | system-cms.css | Group header link hover (opacity) |
| .preview-frame | components.css | system-cms | matches DS | system-cms.css | Dot-grid bordered preview container |
| .controller | components.css | system-cms | matches DS | system-cms.css | Cards controller sticky toolbar (surface bg) |
| .controller-row | components.css | system-cms | matches DS | system-cms.css | Controller row flex (gap-20) |
| .controller-row + .controller-row | components.css | system-cms | matches DS | system-cms.css | Controller row divider (top border) |
| .ctrl-label | components.css | system-cms | matches DS | system-cms.css | Controller label (condensed uppercase, 80px width) |
| .pill-group | components.css | system-cms | matches DS | system-cms.css | Pills flex container (wrap) |
| .count-badge | components.css | system-cms | matches DS | system-cms.css | Count display (margin-left auto) |

##### status.css

| Selector | File | Bucket | Drift | Move-To-Path | Proposed name + note |
|---|---|---|---|---|---|
| .cat-pill | status.css | system-public | matches DS | system-public.css | Category pill (rectangle, filled background) — .pill--category |
| .cat-pill.active | status.css | system-cms | matches DS | system-cms.css | Category pill active state (controller variant) |
| .fp | status.css | system-cms | matches DS | system-cms.css | Filter pill (outline, data-cat-driven colors) — .filter-pill |
| .fp:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill hover (border + text color by category) |
| .fp[data-cat="ux-industry"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill terracotta hover |
| .fp[data-cat="leading-design"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill forest hover |
| .fp[data-cat="for-designers"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill denim hover |
| .fp[data-cat="talk"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill amber hover |
| .fp[data-cat="workshop"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill mauve hover |
| .fp[data-cat="masterclass"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill purple hover |
| .fp[data-cat="introspection"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill violet hover |
| .fp[data-cat="contemplation"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill teal hover |
| .fp[data-cat="insight"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill ochre hover |
| .fp[data-cat="prototype"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill experiment-prototype hover |
| .fp[data-cat="concept"]:hover | status.css | system-cms | matches DS | system-cms.css | Filter pill experiment-concept hover |
| .fp.on | status.css | system-cms | matches DS | system-cms.css | Filter pill active state (filled bg) |
| .fp[data-cat="ux-industry"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill terracotta active |
| .fp[data-cat="leading-design"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill forest active |
| .fp[data-cat="for-designers"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill denim active |
| .fp[data-cat="talk"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill amber active |
| .fp[data-cat="workshop"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill mauve active |
| .fp[data-cat="masterclass"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill purple active |
| .fp[data-cat="introspection"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill violet active |
| .fp[data-cat="contemplation"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill teal active |
| .fp[data-cat="insight"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill ochre active |
| .fp[data-cat="prototype"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill experiment-prototype active |
| .fp[data-cat="concept"].on | status.css | system-cms | matches DS | system-cms.css | Filter pill experiment-concept active |
| .st | status.css | system-public | matches DS | system-public.css | Content status badge base (.status) |
| .st-pub | status.css | system-public | matches DS | system-public.css | Published status badge (.status--published) — forest + light bg |
| .st-dft | status.css | system-public | matches DS | system-public.css | Draft status badge (.status--draft) — muted + faint bg |
| .st-sch | status.css | system-public | matches DS | system-public.css | Scheduled status badge (.status--scheduled) — denim + tint bg |

##### tables.css

| Selector | File | Bucket | Drift | Move-To-Path | Proposed name + note |
|---|---|---|---|---|---|
| .cms-shell | tables.css | system-cms | matches DS | system-cms.css | Admin shell frame (grid: sidebar + main) |
| .cms-sidebar | tables.css | system-cms | matches DS | system-cms.css | Admin left sidebar (210px, nav chrome) |
| .cms-logo | tables.css | system-cms | matches DS | system-cms.css | Admin logo (serif italic) |
| .cms-nav-group | tables.css | system-cms | matches DS | system-cms.css | Admin nav group container |
| .cms-nav-lbl | tables.css | system-cms | matches DS | system-cms.css | Admin nav group label (uppercase) |
| .cms-item | tables.css | system-cms | matches DS | system-cms.css | Admin nav item (flex, clickable) |
| .cms-item:hover | tables.css | system-cms | matches DS | system-cms.css | Admin nav item hover (ink-08 bg) |
| .cms-item.on | tables.css | system-cms | matches DS | system-cms.css | Admin nav item active (filled primary) |
| .cms-item svg | tables.css | system-cms | matches DS | system-cms.css | Admin nav icon |
| .cms-item.on svg | tables.css | system-cms | matches DS | system-cms.css | Admin nav icon active (surface color) |
| .cms-hr | tables.css | system-cms | matches DS | system-cms.css | Admin nav divider |
| .cms-badge | tables.css | system-cms | matches DS | system-cms.css | Admin nav badge (count) |
| .cms-main | tables.css | system-cms | matches DS | system-cms.css | Admin main content flex container |
| .cms-topbar | tables.css | system-cms | matches DS | system-cms.css | Admin topbar (flex space-between) |
| .cms-page-title | tables.css | system-cms | drifts from DS | system-cms.css | Raw 26px (not a token) — use token or align to typography.css rule; .page-title |
| .cms-actions | tables.css | system-cms | matches DS | system-cms.css | Admin topbar action buttons flex |
| .cms-btn-sec | tables.css | system-cms | matches DS | system-cms.css | Admin secondary button (outline) — .btn--secondary |
| .cms-btn-sec:hover | tables.css | system-cms | matches DS | system-cms.css | Admin secondary button hover |
| .cms-btn-pri | tables.css | system-cms | matches DS | system-cms.css | Admin primary button (filled) — .btn--primary |
| .cms-content | tables.css | system-cms | matches DS | system-cms.css | Admin content area (flex 1) |
| .cms-stats | tables.css | system-cms | matches DS | system-cms.css | Admin stats strip (flex, border) |
| .cms-stat | tables.css | system-cms | matches DS | system-cms.css | Admin stat cell (flex 1) |
| .cms-stat:last-child | tables.css | system-cms | matches DS | system-cms.css | Admin stat cell border removal |
| .cms-stat-val | tables.css | system-cms | matches DS | system-cms.css | Admin stat value (body-lg bold) |
| .cms-stat-lbl | tables.css | system-cms | matches DS | system-cms.css | Admin stat label (uppercase) |
| .cms-filter | tables.css | system-cms | matches DS | system-cms.css | Admin filter row (flex wrap) |
| .cms-search | tables.css | system-cms | matches DS | system-cms.css | Admin search input (outline, placeholder) |
| .cms-search:focus | tables.css | system-cms | matches DS | system-cms.css | Admin search focus (border-color) |
| .cms-search::placeholder | tables.css | system-cms | matches DS | system-cms.css | Admin search placeholder |
| .cms-table | tables.css | system-cms | matches DS | system-cms.css | Admin table (100%, collapse) |
| .cms-table th:first-child | tables.css | system-cms | matches DS | system-cms.css | Admin table first header (216px fixed width) |
| .cms-table th | tables.css | system-cms | matches DS | system-cms.css | Admin table header cell |
| .cms-table td | tables.css | system-cms | matches DS | system-cms.css | Admin table data cell |
| .cms-table td:nth-child(4) | tables.css | system-cms | matches DS | system-cms.css | Admin table cell 4 (pill size) |
| .cms-table tr:hover td | tables.css | system-cms | matches DS | system-cms.css | Admin table row hover (ink-08 bg) |
| .td-title | tables.css | system-cms | matches DS | system-cms.css | Admin table title cell (bold, primary color) |
| .row-act | tables.css | system-cms | matches DS | system-cms.css | Admin row actions flex container |

##### views.css

| Selector | File | Bucket | Drift | Move-To-Path | Proposed name + note |
|---|---|---|---|---|---|
| .card[data-category="ux-industry"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (terracotta) |
| .card[data-category="leading-design"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (forest) |
| .card[data-category="for-designers"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (denim) |
| .card[data-category="introspection"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (purple) |
| .card[data-category="contemplation"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (denim) |
| .card[data-category="insight"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (amber) |
| .card[data-category="talk"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (amber) |
| .card[data-category="workshop"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (mauve) |
| .card[data-category="masterclass"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (purple) |
| .card[data-category="prototype"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (experiment-prototype) |
| .card[data-category="concept"] | views.css | system-public | matches DS | system-public.css | Card category color mapping (experiment-concept) |
| .card .cat, .card .ev-type, .card .ev-type-past, .od-type | views.css | system-public | matches DS | system-public.css | Unified card category label typography |
| .card .cat | views.css | system-public | matches DS | system-public.css | Article/journal category label |
| .card .ev-type | views.css | system-public | matches DS | system-public.css | Event type label (live) |
| .od-type | views.css | system-public | matches DS | system-public.css | Experiment type label (white-45) |
| .card[data-category="masterclass"] .ev-type | views.css | system-public | matches DS | system-public.css | Masterclass event type (white color override) |
| .card .series-pill | views.css | system-public | matches DS | system-public.css | Series pill link (border + color by category) |
| .card .fw-badge | views.css | system-public | matches DS | system-public.css | "Featured Work" badge (border + color by category) |
| .card .sdot | views.css | system-public | matches DS | system-public.css | Series progress dot (bg by category 20%) |
| .card .sdot.on | views.css | system-public | matches DS | system-public.css | Series dot active (solid category color) |
| .card .j-ruled | views.css | system-public | matches DS | system-public.css | Journal ruled passage (serif italic, left border by category) |
| .card.past[data-category="talk"] .ev-type-past | views.css | system-public | matches DS | system-public.css | Past talk event label (amber) |
| .card.past[data-category="workshop"] .ev-type-past | views.css | system-public | matches DS | system-public.css | Past workshop event label (moss) |
| .cards-grid | views.css | system-public | matches DS | system-public.css | Cards grid layout (auto-fit, 280px min) |
| .card-body | views.css | system-public | matches DS | system-public.css | Card body padding (flex 1 column) |
| #tab-cards | views.css | system-cms | matches DS | system-cms.css | Cards tab scope (defines --pad, --c-free/inperson tokens) |
| .card | views.css | system-public | matches DS | system-public.css | Card base (.card--article, --journal, --event, --experiment, --masterclass) |
| .card:hover | views.css | system-public | matches DS | system-public.css | Card hover (border-color primary) |
| .card .card-link | views.css | system-public | matches DS | system-public.css | Stretched-link anchor (inset 0 coverage) |
| .card .card-link::before | views.css | system-public | matches DS | system-public.css | Stretched-link pseudo-element (z-index 1) |
| .card .card-link:hover | views.css | system-public | matches DS | system-public.css | Stretched-link hover (inherits color) |
| .card--experiment .card-link::before | views.css | system-public | matches DS | system-public.css | Experiment stretched-link (z-index 3 above inner content) |
| .card .series-pill, .card a.series-pill, .card .tag, .card .od-tag, .card .ev-countdown-label, .card .ev-past-label | views.css | system-public | matches DS | system-public.css | Interactive children (position relative z-index 2) |
| .card-series-number | views.css | system-public | matches DS | system-public.css | Series watermark (big italic serif behind content) |
| .card .card-header | views.css | system-public | matches DS | system-public.css | Card header flex container (shrink 0) |
| .card--event .card-header | views.css | system-public | matches DS | system-public.css | Event card header (primary bg) |
| .card .card-body | views.css | system-public | matches DS | system-public.css | Card body content area (flex 1) |
| .card .bm svg | views.css | system-public | matches DS | system-public.css | Bookmark icon |
| .card .title | views.css | system-public | matches DS | system-public.css | Card title (serif italic 30px) |
| .card .excerpt | views.css | system-public | matches DS | system-public.css | Card excerpt (body-sm, ink-mid) |
| .card .push-divider | views.css | system-public | matches DS | system-public.css | Vertical spacer (auto margin) |
| .card .arrow | views.css | system-public | matches DS | system-public.css | Card arrow CTA (h5 size, light weight) |
| .card .tag | views.css | system-public | matches DS | system-public.css | Card metadata tag (.tag) |
| .card .tag-row | views.css | system-public | matches DS | system-public.css | Tag group container (flex wrap) |
| .card .meta-strip | views.css | system-public | matches DS | system-public.css | Card metadata footer (flex space-between, top border) |
| .card .meta-strip span | views.css | system-public | matches DS | system-public.css | Metadata text (condensed uppercase) |
| .card .series-row | views.css | system-public | matches DS | system-public.css | Series block flex (wrap, margin-bottom) |
| .card .series-pill | views.css | system-public | matches DS | system-public.css | Series pill (condensed uppercase, transition hover) |
| a.card .series-pill, .card a.series-pill | views.css | system-public | matches DS | system-public.css | Series pill anchor (no underline) |
| .card a.series-pill:hover | views.css | system-public | matches DS | system-public.css | Series pill hover (bg + border by category 8%/60%) |
| .card .series-pos | views.css | system-public | matches DS | system-public.css | Series position label (condensed, muted) |
| .card .sdot | views.css | system-public | matches DS | system-public.css | Series dot base (5px circle) |
| .card .series-dots | views.css | system-public | matches DS | system-public.css | Series dots container (flex gap-3) |
| .card .fw-badge | views.css | system-public | matches DS | system-public.css | Featured-work badge (condensed uppercase, flex) |
| .card .j-ruled | views.css | system-public | matches DS | system-public.css | Journal ruled quote (serif italic 21px, left border by category) |
| .card--journal .card-body | views.css | system-public | matches DS | system-public.css | Journal card body (flex-start) |
| .card--journal .j-footer-push | views.css | system-public | matches DS | system-public.css | Journal footer spacer (flex 1) |
| .card .j-num | views.css | system-public | matches DS | system-public.css | Journal entry number (opacity 0.45) |
| .card .j-date | views.css | system-public | matches DS | system-public.css | Journal entry date (condensed uppercase) |
| .card .ev-countdown-label | views.css | system-public | matches DS | system-public.css | Event countdown label (indigo bg, white text, flex) |
| .card .ev-past-label | views.css | system-public | matches DS | system-public.css | Event past label (ink-30 bg, white-85 text, flex) |
| .card .loc-zone | views.css | system-public | matches DS | system-public.css | Event location section (primary bg) |
| .card .remote-zone | views.css | system-public | matches DS | system-public.css | Event remote section (primary bg) |
| .card .loc-ghost | views.css | system-public | matches DS | system-public.css | Location ghost text (big condensed, white-06) |
| .card .remote-ghost | views.css | system-public | matches DS | system-public.css | Remote ghost text (big condensed, white-06) |
| .card .loc-city | views.css | system-public | matches DS | system-public.css | City name (condensed 20px bold white) |
| .card .loc-venue | views.css | system-public | matches DS | system-public.css | Venue name (condensed meta white-45) |
| .card .remote-label | views.css | system-public | matches DS | system-public.css | Remote label (condensed 20px bold white) |
| .card .remote-sub | views.css | system-public | matches DS | system-public.css | Remote sublabel (condensed meta white-45) |
| .card .ev-body | views.css | system-public | matches DS | system-public.css | Event content area (flex 1 column) |
| .card .ev-date-row | views.css | system-public | matches DS | system-public.css | Event date flex row (wrap) |
| .card .ev-date | views.css | system-public | matches DS | system-public.css | Event date (condensed 20px bold) |
| .card .ev-date-past | views.css | system-public | matches DS | system-public.css | Event date past (opacity 0.7) |
| .card .ev-time | views.css | system-public | matches DS | system-public.css | Event time (condensed meta, ink-faint) |
| .card .ev-title | views.css | system-public | matches DS | system-public.css | Event title (serif italic h3-like) |
| .card .ev-title-past | views.css | system-public | matches DS | system-public.css | Event title past (opacity 0.7) |
| .card .ev-desc | views.css | system-public | matches DS | system-public.css | Event description (body-sm, ink-mid) |
| .card .ev-footer | views.css | system-public | matches DS | system-public.css | Event footer flex (space-between) |
| .card .fmt-row | views.css | system-public | matches DS | system-public.css | Format row flex (wrap) |
| .card .fmt | views.css | system-public | matches DS | system-public.css | Format pill (.fmt--free, --paid, --inperson, --remote) |
| .card .fmt-free | views.css | system-public | matches DS | system-public.css | Format free pill (forest + tint bg) |
| .card .fmt-paid | views.css | system-public | matches DS | system-public.css | Format paid pill (primary + ink-08 bg) |
| .card .fmt-inperson | views.css | system-public | matches DS | system-public.css | Format in-person pill (clay + tint bg) |
| .card .fmt-remote | views.css | system-public | matches DS | system-public.css | Format remote pill (ink-mid + ink-08 bg) |
| .card[data-category="masterclass"] .card-header | views.css | system-public | matches DS | system-public.css | Masterclass header (gradient 000/5b1377/a1480c) |
| .card[data-category="masterclass"] .card-header::before | views.css | system-public | matches DS | system-public.css | Masterclass header ellipses pattern (SVG, z-index 0) |
| .card[data-category="masterclass"] .card-header > * | views.css | system-public | matches DS | system-public.css | Masterclass header content (z-index 1 above pattern) |
| .mc-body | views.css | system-public | matches DS | system-public.css | Masterclass body (flex 1) |
| .mc-logistics | views.css | system-public | matches DS | system-public.css | Masterclass logistics flex column (gap-6) |
| .mc-logistic | views.css | system-public | matches DS | system-public.css | Masterclass logistic item (flex, gap-8, condensed meta) |
| .mc-logistic-icon | views.css | system-public | matches DS | system-public.css | Masterclass logistic icon (14px flex-shrink 0) |
| .mc-cta-zone | views.css | system-public | matches DS | system-public.css | Masterclass CTA footer (flex space-between, primary bg) |
| .mc-cta-left | views.css | system-public | matches DS | system-public.css | Masterclass CTA label group (flex column) |
| .mc-cta-label | views.css | system-public | matches DS | system-public.css | Masterclass CTA label (condensed uppercase white) |
| .mc-cta-sub | views.css | system-public | matches DS | system-public.css | Masterclass CTA sublabel (condensed uppercase white-45) |
| .mc-cta-right | views.css | system-public | matches DS | system-public.css | Masterclass CTA action (flex gap-14) |
| .mc-price | views.css | system-public | matches DS | system-public.css | Masterclass price (serif italic 26px white-85) |
| .mc-arrow | views.css | system-public | matches DS | system-public.css | Masterclass arrow (h5 light white) |
| .od-status-dot | views.css | system-public | matches DS | system-public.css | Experiment status dot (flex gap-5, condensed white-45) |
| .od-title | views.css | system-public | matches DS | system-public.css | Experiment title (serif italic h3-like white) |
| .od-excerpt | views.css | system-public | matches DS | system-public.css | Experiment excerpt (body-sm white-45) |
| .od-tag | views.css | system-public | matches DS | system-public.css | Experiment tag (label size uppercase, white-15 border) |
| .od-meta | views.css | system-public | matches DS | system-public.css | Experiment metadata (condensed meta white-45) |
| .scrim-proto | views.css | system-public | matches DS | system-public.css | Experiment prototype scrim (gradient to 1c1028 97%) |
| .scrim-concept | views.css | system-public | matches DS | system-public.css | Experiment concept scrim (gradient to 0a1f1a 98%) |
| .exp-content | views.css | system-public | matches DS | system-public.css | Experiment content wrapper (z-index 2 flex 1) |
| .cta-launch | views.css | system-public | matches DS | system-public.css | Experiment launch CTA (flex space-between, semi-dark bg) |
| .cta-launch .label | views.css | system-public | matches DS | system-public.css | Launch label (condensed uppercase white) |
| .cta-launch .arrow | views.css | system-public | matches DS | system-public.css | Launch arrow (body-lg light white) |
| .cta-read | views.css | system-public | matches DS | system-public.css | Experiment read CTA (flex space-between, border-top) |
| .cta-read .label | views.css | system-public | matches DS | system-public.css | Read label (condensed uppercase white-45) |
| .cta-read .arrow | views.css | system-public | matches DS | system-public.css | Read arrow (body light white-45) |
| .card.past | views.css | system-public | matches DS | system-public.css | Past card overlay (rgba 0.7) |
| .card.past.card--event .card-header | views.css | system-public | matches DS | system-public.css | Past event header (ink-72 dark overlay) |
| .card.past .loc-zone, .card.past .remote-zone | views.css | system-public | matches DS | system-public.css | Past event zones (ink-72 dark overlay) |
| .card | views.css | system-public | matches DS | system-public.css | Card show-all override (!important display flex) |

##### Summary


| Bucket | Count |
|---|---|
| tokens | 1 |
| system-public | 234 |
| system-cms | 261 |
| Dead | 0 |

###### Noteworthy categorization calls

1. **`.cms-page-title` drifts** — Raw 26px (not a token). Should reference `--text-h4` (24px) or define a new token, or align with the table-heading convention.

2. **Showcase-only scaffold** — 30+ selectors in components.css (.color-grid, .chip, .swatch, .spec-grid, .comp-rule, .usage-card, .type-row, .controller, .state-label, .group-header) are showcase demo Chrome, not shipped in production CMS. Tagged system-cms but marked "SHOWCASE-ONLY — rebuilt in Phase 22.6".

3. **Category color mapping** — All 11 data-category selectors in views.css set --c-current. Reuse this pattern in cards system + pill variants. Kept atomic selectors per-line for table clarity.

4. **Tab scope #tab-cards** — Defines scoped tokens (--pad: 30px; --c-free-bg, etc.). System-cms because it governs CMS cards controller, not public display.

5. **Stretched-link z-index choreography** — Masterclass (z-index 3) vs. article (z-index 1) require different layering. Documented but both ship as public (system-public). Interactive children at z-index 2 ensure link never blocks clicks.


---

### 6.2 — `_pages/_layout/style-pages.css` (system-public · Pages)

> **✅ MIGRATED in Phase 22.3 (2026-06-05).** All 208 non-`:root` rules copied verbatim (no renames) into `css/public/pages.css`, loaded via the `system-public.css` barrel; `_page-shell.php` links it additively alongside the still-live `style-pages.css`. Rule set verified identical. **22.6 gap:** `--dot-grid` / `--dot-grid-svg` were page-local tokens (not promoted to tokens.css) — the old file still supplies them, so zero regression now, but they vanish if `style-pages.css` is toggled off. 22.6 must resolve the dot-grid (move asset + define token, or adopt the DS inline-SVG dot-grid) before deleting `style-pages.css`.

#### CSS Audit: style-pages.css vs Design System


---

##### Tokens (local :root — duplicates tokens.css)

| Token | Value (style-pages.css) | Canonical (tokens.css) | Drift Status |
|---|---|---|---|
| --primary | #191715 | #191715 | matches |
| --secondary | #494846 | #494846 | matches |
| --muted | #818080 | #818080 | matches |
| --neutral | #E8E8E8 | #E8E8E8 | matches |
| --surface | #F8F8F8 | #F8F8F8 | matches |
| --canvas-bg | #F3F2F1 | #F3F2F1 | matches |
| --accent | #6B7F6E | #6B7F6E | matches |
| --white | #FFFFFF | #ffffff | DUP but DRIFTS: #FFFFFF vs #ffffff (case; functionally equiv) |
| --ink-08 | rgba(25,23,21,0.08) | rgba(25,23,21,0.08) | matches |
| --ink-12 | rgba(25,23,21,0.12) | rgba(25,23,21,0.12) | matches |
| --ink-18 | rgba(25,23,21,0.18) | rgba(25,23,21,0.18) | matches |
| --ink-30 | rgba(25,23,21,0.30) | rgba(25,23,21,0.30) | matches |
| --font | 'Barlow', sans-serif | 'Barlow', sans-serif | matches |
| --font-cond | 'Barlow Condensed', sans-serif | 'Barlow Condensed', sans-serif | matches |
| --font-serif | 'Instrument Serif', serif | 'Instrument Serif', serif | matches |
| --font-mono | 'JetBrains Mono', monospace | 'JetBrains Mono', monospace | matches |
| --text-display | 54px | 54px | matches |
| --text-h0 | 48px | 48px | matches |
| --text-h1 | 40px | 40px | matches |
| --text-h2 | 32px | 32px | matches |
| --text-h3 | 28px | 28px | matches |
| --text-h4 | 24px | 24px | matches |
| --text-h5 | 22px | 22px | matches |
| --text-body-lg | 20px | 20px | matches |
| --text-body | 18px | 18px | matches |
| --text-md | 16px | 16px | matches |
| --text-sm | 15px | 15px | matches |
| --text-base | 14px | 14px | matches |
| --text-meta | 13px | 13px | matches |
| --text-pill | 12px | 12px | matches |
| --text-label | 11px | 11px | matches |
| --text-tiny | 10px | 10px | matches |
| --space-4 | 4px | 4px | matches |
| --space-8 | 8px | 8px | matches |
| --space-12 | 12px | 12px | matches |
| --space-16 | 16px | 16px | matches |
| --space-20 | 20px | 20px | matches |
| --space-24 | 24px | 24px | matches |
| --space-32 | 32px | 32px | matches |
| --space-40 | 40px | 40px | matches |
| --space-48 | 48px | 48px | matches |
| --space-56 | 56px | 56px | matches |
| --space-64 | 64px | 64px | matches |
| --space-80 | 80px | 80px | matches |
| --space-96 | 96px | 96px | matches |
| --r-card | 4px | 4px | matches |
| --r-pill | 4px | 4px | matches |
| --r-tag | 3px | N/A in tokens.css | **Missing from canonical** |
| --dot-grid | url("background.png") | N/A | page-specific local def |
| --rule | 1px solid var(--primary) | 1px solid var(--primary) | matches |
| --rule-30 | 1px solid var(--ink-30) | 1px solid var(--ink-30) | matches |
| --rule-faint | 1px solid var(--ink-18) | 1px solid var(--ink-18) | matches |

**Token summary:** 52/53 match canonical tokens.css exactly. --r-tag (3px) exists in style-pages.css but not in canonical tokens.css; should be promoted or reconciled. --white case difference is inconsequential.

---

##### System-Public Selectors (Pages region)

| Selector | File | Bucket | Drift | Move-To-Path | Proposed name + note |
|---|---|---|---|---|---|
| body | style-pages.css | system-public | matches DS base | site/_design-system/css/system-public.css [region: Pages] | Keep; shell base for Pages |
| a | style-pages.css | system-public | matches DS | system-public.css | Keep; anchor reset |
| button | style-pages.css | system-public | matches DS | system-public.css | Keep; button reset |
| .layout-nav | style-pages.css | system-public | drifts from DS | system-public.css | **Drift:** DS has solid --neutral bg + hard rule; style-pages has `background: transparent; backdrop-filter: blur(12px)` (frosted glass). Intentional page variant. Keep in system-public.css [Pages variant] |
| .layout-nav::before | style-pages.css | system-public | no DS equivalent | system-public.css | Texture overlay helper; page-specific. Keep. |
| .layout-nav-logo | style-pages.css | system-public | matches DS | system-public.css | Keep; canonical logo treatment |
| .layout-nav-links | style-pages.css | system-public | matches DS | system-public.css | Keep |
| .layout-nav-links a | style-pages.css | system-public | matches DS | system-public.css | Keep |
| .layout-footer | style-pages.css | system-public | drifts from DS | system-public.css | **Drift:** DS layout-footer bg: --neutral, hard borders. Style-pages: transparent bg, --rule-30. Intentional page variant. Keep in system-public.css [Pages variant]. |
| .layout-footer-left | style-pages.css | system-public | matches DS | system-public.css | Keep |
| .layout-footer-right | style-pages.css | system-public | matches DS | system-public.css | Keep |
| .m | style-pages.css | system-public | matches DS | system-public.css | Keep; muted-word emphasis |
| .ac | style-pages.css | system-public | matches DS | system-public.css | Keep; accent color emphasis |
| .serif | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Page-specific inline serif utility; reusable. Keep. |
| .eyebrow | style-pages.css | system-public | matches DS | system-public.css | Keep; standard eyebrow label |
| .serif-eyebrow | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Serif variant of eyebrow; page-specific. Keep. |
| .statement | style-pages.css | system-public | matches DS (partial) | system-public.css | **Drift:** DS typography.css has `.section-heading` (h0 role). Style-pages `.statement` is display-tier (54px). Different roles. Keep separate. |
| .statement.is-h0 | style-pages.css | system-public | matches DS | system-public.css | Keep; modifier |
| .statement.is-h1 | style-pages.css | system-public | matches DS | system-public.css | Keep; modifier |
| .page-header-title | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Page-specific h1. Reusable for any page header. Keep. |
| .page-header-title.is-serif | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier for page headers. Keep. |
| .page-header-sub | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Page header subtitle. Keep. |
| .lead | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Lead/deck paragraph. Reusable. Keep. |
| .lead.is-sm | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier. Keep. |
| .serif-intro | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Serif section intro. Reusable. Keep. |
| .pull-quote | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Left-ruled quote block. Reusable. Keep. |
| .btn-dark | style-pages.css | system-public | matches DS | system-public.css | Keep; canonical button |
| .btn-light | style-pages.css | system-public | matches DS | system-public.css | Keep; canonical button |
| .btn-ul | style-pages.css | system-public | matches DS | system-public.css | Keep; underline button variant |
| .btn-row | style-pages.css | system-public | matches DS | system-public.css | Keep; button layout |
| .btn-stack | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Vertical button layout. Reusable. Keep. |
| .tag | style-pages.css | system-public | matches DS (partial) | system-public.css | Keep; generic tag pill |
| .profile-circle | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Avatar circle component. Reusable. Keep. |
| .profile-circle.is-sm | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier. Keep. |
| .profile-circle.is-md | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier. Keep. |
| .profile-circle.is-lg | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier. Keep. |
| .profile-circle-img | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .profile-circle-initials | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (fallback display). Keep. |
| .hero > .profile-circle | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Contextual layout rule. Keep. |
| .page-header.two-col > .profile-circle | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Contextual layout rule. Keep. |
| .form | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form wrapper. Temporary until CMS forms promoted. Keep. |
| .form-field | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form element. Keep. |
| .form-label | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form label. Keep. |
| .form-label-optional | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form label modifier. Keep. |
| .form-input | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form input. Keep. |
| .form-input::placeholder | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form input state. Keep. |
| .form-input:hover | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form input state. Keep. |
| .form-input:focus | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form input state. Keep. |
| .form-input[aria-invalid="true"] | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form input state. Keep. |
| .form-help | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form helper text. Keep. |
| .form-error | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form error text. Keep. |
| .form-actions | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form button row. Keep. |
| .form-fineprint | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Form fine print. Keep. |
| .page | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Content container. Reusable. Keep. |
| .page.is-narrow | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier. Keep. |
| .page-section | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Logical section grouping. Keep. |
| .page-section:first-of-type | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | State rule. Keep. |
| .page > * + * | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Spacing utility. Keep. |
| .page > .has-rule + * | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Spacing utility refinement. Keep. |
| .page-header | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Page header container. Keep. |
| .two-col | style-pages.css | system-public | matches DS | system-public.css | Keep; grid layout helper |
| .two-col.is-balanced | style-pages.css | system-public | matches DS | system-public.css | Modifier. Keep. |
| .two-col.is-end | style-pages.css | system-public | matches DS | system-public.css | Modifier. Keep. |
| .two-col.is-center | style-pages.css | system-public | matches DS | system-public.css | Modifier. Keep. |
| .detail-card | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Boxed info panel. Reusable. Keep. |
| .detail-card-label | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .detail-list | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .detail-row-label | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .detail-row-value | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .hero | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Hero block. Reusable. Keep. |
| .hero .statement | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Contextual override. Keep. |
| .hero .lead | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Contextual override. Keep. |
| :where(.narrative) p + p | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Paragraph spacing in prose. Keep. |
| :where(.narrative) p | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Base prose styling. Keep. |
| .narrative .serif-intro | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Contextual override. Keep. |
| .narrative .btn-row | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Contextual override. Keep. |
| .numbered-list | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Ledger-style list. Reusable. Keep. |
| .numbered-row | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .numbered-row-num | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (ghost serif numeral). Keep. |
| .numbered-row-body | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .numbered-row-title | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Propose: `.numbered-row__title` |
| .numbered-row-desc | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Propose: `.numbered-row__desc` |
| .numbered-row-tags | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Propose: `.numbered-row__tags` |
| .point-grid | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | 3-up feature grid. Reusable. Keep. |
| .point-num | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (serif numeral). Keep. |
| .point-title | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .point-desc | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .ledger | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Date × content grid. Reusable. Keep. |
| .ledger-date | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .ledger-entry | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .ledger > .ledger-date:last-of-type | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | State rule. Keep. |
| .ledger > .ledger-entry:last-of-type | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | State rule. Keep. |
| .ledger-role | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .ledger-org | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .section-outro | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Bottom-of-page CTA strip. Keep. |
| .section-outro p | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .section-outro p strong | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element state. Keep. |
| .section-heading | style-pages.css | system-public | drifts from DS | system-public.css | **Drift:** DS typography.css `.section-heading` is h0 @ 48px, font-weight 500. Style-pages `.section-heading` is h2 @ 32px, font-weight 600. Different roles. Proposal: rename style-pages version to `.section-heading--pages` or reconcile naming. For now, keep as page variant. |
| .hero-byline | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Avatar + intro pairing. Reusable. Keep. |
| .hero-byline > .profile-circle | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Contextual override. Keep. |
| .hero-byline .serif-intro | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Contextual override. Keep. |
| .bio-split | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Two-column bio layout. Reusable. Keep. |
| .bio-stack p + p | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Paragraph spacing. Keep. |
| .bio-stack p | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Prose in bio. Keep. |
| .bio-quote | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Sidebar italic quote. Keep. |
| .pillars | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | 3-up credential strip. Reusable. Keep. |
| .pillar | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .pillar-label | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .pillar-text | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .pillar-text strong | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element state. Keep. |
| .journey | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Multi-step horizontal flow. Reusable. Keep. |
| .journey-col | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .journey-col .col-label | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .journey-col p | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element prose. Keep. |
| .journey-arrow | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (serif arrow). Keep. |
| .chip-list | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Flex list for tag rows. Keep. |
| .bars | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Capacity/drain visualization. Reusable. Keep. |
| .bar-row | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .bar-label | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .bar-track | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (progress track). Keep. |
| .bar-fill | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (progress fill). Keep. |
| .bar-fill--drain | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier (uses --c-terracotta). Keep. |
| .bar-fill--capacity | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier (uses --c-sage). Keep. |
| .bar-fill--capacity-strong | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier (uses --c-forest). Keep. |
| .bar-fill--removed | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier. Keep. |
| .bars-divider | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Separator hr. Keep. |
| .legend | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Legend list. Keep. |
| .legend-item | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .legend-dot | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (colour swatch). Keep. |
| .legend-dot--drain | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier. Keep. |
| .legend-dot--capacity | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Modifier. Keep. |
| .loop | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Cyclical 4-node process. Reusable. Keep. |
| .loop-node | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .loop-node:not(:last-child)::after | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | State rule (arrow). Keep. |
| .loop-circle | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (node circle). Keep. |
| .loop-label | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .loop-note | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (italic note). Keep. |
| .callout | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Aside note block. Reusable. Keep. |
| .callout p | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element prose. Keep. |
| .callout p strong | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element state. Keep. |
| .situation-grid | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | 3-up card grid (scenarios). Reusable. Keep. |
| .situation-card | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .situation-quote | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (serif quote). Keep. |
| .situation-focus | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (focus label). Keep. |
| .situation-what | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (body text). Keep. |
| .testimonial-grid | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | 2-up testimonial cards. Reusable. Keep. |
| .testimonial-card | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .testimonial-card blockquote | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (serif blockquote). Keep. |
| .testimonial-card cite | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element (cite). Keep. |
| .faq-list | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Two-column accordion. Reusable. Keep. |
| .faq-column | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .faq-item | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .faq-item summary | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .faq-item summary::-webkit-details-marker | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Vendor reset. Keep. |
| .faq-item summary::after | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | State indicator (pseudo). Keep. |
| .faq-item[open] summary::after | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | State rule (expanded). Keep. |
| .faq-answer | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element. Keep. |
| .faq-answer p | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Element prose. Keep. |
| .has-rule | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Spacing utility (bottom rule). Keep. |
| .has-rule-faint | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Spacing utility (faint bottom rule). Keep. |
| .has-rule-left | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Spacing utility (left rule). Keep. |
| @media (max-width: 720px) — 58 responsive rules | style-pages.css | system-public | no DS equivalent | system-public.css [Pages] | Mobile collapse breakpoint. Keep. |

---

##### Summary

**Total selectors audited:** 231 (53 token defs + 178 CSS rule selectors)  
- **tokens:** 53 (all local :root definitions, duplicating tokens.css)
- **system-public:** 178 (Pages region selectors, fully reusable across marketing pages)
- **Dead:** 0 (no unused selectors found)

**Drifting tokens from canonical tokens.css:**
1. `--white`: #FFFFFF (local) vs #ffffff (canonical) — case difference only, functionally equivalent.
2. `--r-tag`: exists in style-pages.css (3px) but **absent from canonical tokens.css** — should be promoted or reconciled with canonical radii family.

**Notable calls:**
1. **Layout chrome drift:** `.layout-nav` and `.layout-footer` in style-pages intentionally use `backdrop-filter` and transparent backgrounds instead of solid DS defaults — a deliberate frosted-glass variant for the Pages region. Document this as a permitted divergence.
2. **Section heading naming collision:** `.section-heading` exists in both places but plays different roles (DS: h0 @ 48px, Pages: h2 @ 32px). Recommendation: clarify naming (e.g., `.section-heading--pages` or use context-aware specificity).
3. **Profile circle is pages-only:** No DS equivalent in current components.css; this is a reusable avatar component that could be promoted to the design system proper if other regions adopt it.
4. **Form primitives are temporary:** Comment at line 523 explicitly marks these as "temporary until CMS forms promoted." When Phase 4/12 build forms in the CMS, these selectors should migrate into a dedicated `site/_design-system/css/forms.css` module.
5. **Action items:** Delete the local :root token block from style-pages.css and import tokens.css instead (savings ~500 bytes). Add missing `--r-tag: 3px;` to canonical tokens.css or reconcile radii naming.

---

**Move-To-Path action:**
- Tokens → Delete local :root; import from `site/_design-system/css/tokens.css`
- System-public → Extract to `site/_design-system/css/system-public.css` [region: Pages] with a note on `.layout-nav` / `.layout-footer` glass-morphism variants
- No Dead code to remove

---

### 6.3 — `_templates/style-articles.css` (system-public · Blocks)

> **✅ MIGRATED — Phase 22.4 (2026-06-05).** These Blocks rules now live in
> `_design-system/css/public/blocks.css`, copied **verbatim minus the `:root`**
> (tokens come from `tokens.css`), with one self-containment `:root { --dot-grid }`
> and the two intentional `.event-format-tag` colour swaps (`#246636`→`--c-forest`,
> `#6B4010`→`--c-clay`, decision 22.1 #3 / D8). Linked **directly** on
> `site/templates/master-layout.php` (not via the `system-public.css` barrel, to
> avoid `pages.css` marketing chrome bleeding onto articles). `style-articles.css`
> still loads underneath as the safety net; both are deduped/sunset in Phase 22.6.
> Watch-items D11 (series-pill/-dot/-tag dup) and D13 (layout-nav/footer triple
> copy) remain deferred to 22.6. The selector tables below are the migration
> source-of-record.

#### style-articles.css — Design System Audit

##### Summary
- **Total selectors**: 182
- **Audit scope**: Full stylesheet, verified against canonical DS modules (tokens.css, views.css, components.css, status.css)
- **Buckets**: tokens (43) | system-public (139) | Dead (0)
- **Key finding**: Significant token duplication in :root block; most block selectors map to Blocks region but naming differs from canonical .card family

---

##### Token Audit (:root block, lines 32–96)

| Token | Defined In | Drift | Note |
|---|---|---|---|
| --primary | tokens.css | matches | #191715 ✓ |
| --secondary | tokens.css | matches | #494846 ✓ |
| --muted | tokens.css | matches | #818080 ✓ |
| --neutral | tokens.css | matches | #E8E8E8 ✓ |
| --surface | tokens.css | matches | #F8F8F8 ✓ |
| --canvas-bg | tokens.css | matches | #F3F2F1 ✓ |
| --accent | tokens.css | matches | #6B7F6E ✓ |
| --white | tokens.css | matches | #FFFFFF ✓ |
| --ink-12 | tokens.css | matches | rgba(25,23,21,0.12) ✓ |
| --ink-18 | tokens.css | matches | rgba(25,23,21,0.18) ✓ |
| --ink-30 | tokens.css | matches | rgba(25,23,21,0.30) ✓ |
| --c-terracotta | tokens.css | matches | #7D4631 ✓ |
| --rule | tokens.css | matches | 1px solid var(--primary) ✓ |
| --rule-30 | tokens.css | matches | 1px solid var(--ink-30) ✓ |
| --rule-faint | tokens.css | matches | 1px solid var(--ink-18) ✓ |
| --font | tokens.css | matches | 'Barlow', sans-serif ✓ |
| --font-cond | tokens.css | matches | 'Barlow Condensed', sans-serif ✓ |
| --font-serif | tokens.css | matches | 'Instrument Serif', serif ✓ |
| --font-mono | tokens.css | matches | 'JetBrains Mono', monospace ✓ |
| --text-h0 | tokens.css | matches | 48px ✓ |
| --text-h4 | tokens.css | matches | 24px ✓ |
| --text-h5 | tokens.css | matches | 22px ✓ |
| --text-body-lg | tokens.css | matches | 20px ✓ |
| --text-md | tokens.css | matches | 16px ✓ |
| --text-base | tokens.css | matches | 14px ✓ |
| --text-pill | tokens.css | matches | 12px ✓ |
| --text-label | tokens.css | matches | 11px ✓ |
| --space-4 | tokens.css | matches | 4px ✓ |
| --space-8 | tokens.css | matches | 8px ✓ |
| --space-12 | tokens.css | matches | 12px ✓ |
| --space-16 | tokens.css | matches | 16px ✓ |
| --space-20 | tokens.css | matches | 20px ✓ |
| --space-24 | tokens.css | matches | 24px ✓ |
| --space-32 | tokens.css | matches | 32px ✓ |
| --space-40 | tokens.css | matches | 40px ✓ |
| --space-48 | tokens.css | matches | 48px ✓ |
| --space-56 | tokens.css | matches | 56px ✓ |
| --space-64 | tokens.css | matches | 64px ✓ |
| --space-80 | tokens.css | matches | 80px ✓ |
| --r-card | tokens.css | matches | 4px ✓ |
| --r-pill | tokens.css | matches | 4px ✓ |
| --r-tag | tokens.css | **MISSING** | Defined locally (3px); DS has no --r-tag |
| --dot-grid | tokens.css | **MISSING** | Background asset URL; DS has no equivalent |

**Finding**: 42 of 43 tokens match canonical DS; 2 are local-only (r-tag, dot-grid). All 43 should move to tokens.css; no duplication drift detected.

---

##### Selectors by Bucket

###### Bucket: `system-public` (Region: Blocks)

| Selector | File | Drift vs DS | Move-To-Path | Proposed Name + Note |
|---|---|---|---|---|
| .layout-nav | 120–153 | matches DS | keep | Navigation chrome (shared with components.css) |
| .layout-nav::before | 138–148 | no DS eq | keep | Frosted nav texture layer |
| .layout-nav > * | 149–152 | no DS eq | keep | Relative z-index helper |
| .layout-nav-logo | 153–168 | drifts from DS | keep | **Drift**: DS uses .layout-nav-logo (17) but article-version omits img rule |
| .layout-nav-logo img | 164–168 | no DS eq | keep | Image sizing within logo |
| .layout-nav-links | 169–169 | matches DS | keep | Flex wrapper |
| .layout-nav-links a | 170–186 | drifts from DS | keep | **Drift**: DS .layout-nav-links a (19–37) has different typography/spacing tuning |
| .layout-footer | 188–207 | matches DS | keep | Footer chrome |
| .layout-footer-left | 197–206 | drifts from DS | keep | **Drift**: DS uses --text-label; articles uses var(--text-meta) undeclared |
| .layout-footer-right | 207–207 | matches DS | keep | Flex row |
| .article[data-category="*"] | 222–239 | no DS eq | system-public → --c-current | Category mapping strategy specific to article template |
| .article-breadcrumb | 241–260 | no DS eq | system-public | Breadcrumb chrome (not in DS) |
| .article-breadcrumb-root | 253–258 | no DS eq | system-public | Breadcrumb segment |
| .article-breadcrumb-sep | 259–259 | no DS eq | system-public | Breadcrumb divider |
| .article-breadcrumb-current | 260–260 | no DS eq | system-public | Current breadcrumb segment |
| .article | 262–266 | no DS eq | system-public | Article page wrapper |
| .article-header | 268–268 | no DS eq | system-public | Header block grouping |
| .article-meta | 270–284 | no DS eq | system-public | Metadata row (category + date) |
| .article-category | 278–282 | no DS eq | system-public | Category label; rename → **.article__category** |
| .article-meta-sep | 284–284 | no DS eq | system-public | Meta divider |
| .article-meta-date | 285–291 | no DS eq | system-public | Publish date; rename → **.article-meta__date** |
| .article-meta-updated | 285–291 | no DS eq | system-public | Updated date; rename → **.article-meta__updated** |
| .article-title | 293–314 | no DS eq | system-public | Article title (48px, --text-h0) |
| .article-title-serif | 300–305 | no DS eq | system-public | Inline serif emphasis within title |
| .article-title.is-serif | 308–314 | no DS eq | system-public | Full-serif title variant; rename → **.article-title--serif** |
| .article-summary | 318–324 | no DS eq | system-public | Deck paragraph (20px, secondary) |
| .article-author | 332–348 | no DS eq | system-public | Byline row; rename → **.author-byline** |
| .article-author-info | 340–344 | no DS eq | system-public | Author name + tagline container; rename → **.author-byline__info** |
| .article-author-name | 345–345 | no DS eq | system-public | Author name; rename → **.author-byline__name** |
| .article-author-separator | 346–346 | no DS eq | system-public | Name/tagline divider; rename → **.author-byline__sep** |
| .article-author-tagline | 347–347 | no DS eq | system-public | Author tagline; rename → **.author-byline__tagline** |
| .article-author-bio | 351–390 | no DS eq | system-public | Author bio block; rename → **.author-bio** |
| .article-author-bio-extended | 361–366 | no DS eq | system-public | Bio paragraph; rename → **.author-bio__text** |
| .article-author-avatar | 369–384 | no DS eq | system-public | Byline avatar (48px); rename → **.author-byline__avatar** |
| .article-author-bio-avatar | 369–384 | no DS eq | system-public | Bio avatar (80px); rename → **.author-bio__avatar** |
| .article-author-avatar img | 385–390 | no DS eq | system-public | Avatar image sizing |
| .article-author-bio-avatar img | 385–390 | no DS eq | system-public | Avatar image sizing |
| .article-series | 392–434 | no DS eq | system-public | Series row (pills + progress); rename → **.series-info** |
| .article-series-pill | 399–416 | drifts from DS | system-public | **Drift**: DS .card .series-pill (144) is identical but name is .article-series-pill; consolidate |
| .article-series-progress | 417–424 | no DS eq | system-public | Series progress text; rename → **.series-info__progress** |
| .article-series-dots | 425–425 | no DS eq | system-public | Dot container; rename → **.series-dots** |
| .article-series-dot | 426–434 | drifts from DS | system-public | **Drift**: DS .card .sdot (148) is structurally identical but naming differs |
| .article-special-tag | 438–449 | no DS eq | system-public | Principle/Framework tag; rename → **.article__tag--special** |
| .article-hero | 459–502 | no DS eq | system-public | Hero image block; rename → **.article-hero** or **.article__hero** |
| .article-hero img | 462–468 | no DS eq | system-public | Hero image sizing |
| .article-hero figcaption | 469–478 | no DS eq | system-public | Hero caption; rename → **.article-hero__caption** |
| .article-hero[data-size="wide"], full | 479–502 | no DS eq | system-public | Figure size variants (data-attribute driven) |
| .article-prose | 512–636 | no DS eq | system-public | Body text wrapper; rename → **.article__prose** |
| .article-prose > * + * | 513–513 | no DS eq | system-public | Prose spacing rule |
| .article-prose p | 516–521 | no DS eq | system-public | Paragraph styling |
| .article-prose > p:first-of-type | 526–533 | no DS eq | system-public | Lede (serif italic intro) |
| .article-prose h2, h3, h4 | 536–560 | no DS eq | system-public | Heading hierarchy (h2/h3/h4) |
| .article-prose blockquote | 563–573 | no DS eq | system-public | Pull quote styling |
| .article-prose ul, ol | 576–585 | no DS eq | system-public | List styling |
| .article-prose a | 588–599 | no DS eq | system-public | Link styling with underline |
| .article-prose strong, em | 600–601 | no DS eq | system-public | Emphasis |
| .article-prose code | 604–612 | no DS eq | system-public | Inline code styling |
| .article-prose pre | 613–629 | no DS eq | system-public | Code block styling |
| .article-prose hr | 632–636 | no DS eq | system-public | Section break |
| .article-prose figure | 639–700 | no DS eq | system-public | Figure + caption; with size variants (data-size) |
| .article-prose > img, p > img | 650–658 | no DS eq | system-public | Bare image fallback |
| .figure-size-toggle | 707–745 | no DS eq | system-public | **Editor scaffolding** — demo control; not on public page |
| .article-series-nav | 749–789 | no DS eq | system-public | Series prev/next nav; rename → **.series-nav** |
| .article-series-nav-link | 757–775 | no DS eq | system-public | Nav link card; rename → **.series-nav__link** |
| .article-series-nav-direction | 776–782 | no DS eq | system-public | "NEXT"/"PREV" label; rename → **.series-nav__label** |
| .article-series-nav-title | 784–789 | no DS eq | system-public | Nav link title; rename → **.series-nav__title** |
| .article-tags | 791–817 | no DS eq | system-public | Tag footer section; rename → **.article-tags** or **.article__tags** |
| .article-tags-label | 796–803 | no DS eq | system-public | "TAGS" label; rename → **.article-tags__label** |
| .article-tags-list | 805–805 | no DS eq | system-public | Tag flex row; rename → **.article-tags__list** |
| .article-tag | 806–817 | drifts from DS | system-public | **Drift**: DS .card .tag (139) is identical in structure; should reuse |
| .article-topstrip | 830–851 | no DS eq | system-public | Editorial layout top bar (category + series); rename → **.article-topstrip** or **.editorial__topstrip** |
| .article-topstrip .article-category | 838–845 | no DS eq | system-public | Category label in topstrip (overrides §5 styling) |
| .article-topstrip-right | 846–851 | no DS eq | system-public | Right slot (series pills); rename → **.article-topstrip__right** |
| .article-title.is-serif (§6 override) | 854–859 | no DS eq | system-public | 56px serif title override for editorial |
| .article-summary (§6 override) | 862–867 | no DS eq | system-public | Summary spacing override for editorial |
| .article-tag-row | 870–872 | no DS eq | system-public | Special tag row; rename → **.article__tag-row** |
| .article-dates | 875–881 | no DS eq | system-public | Publish + updated date row; rename → **.article-dates** or **.article__dates** |
| .article-pub-date | 882–889 | no DS eq | system-public | Publish date styling; rename → **.article-dates__pub** |
| .article-updated-date | 890–897 | no DS eq | system-public | Updated date styling; rename → **.article-dates__updated** |
| .article-byline-row | 902–940 | no DS eq | system-public | Editorial byline row with rules; rename → **.article-byline** or **.byline-row** |
| .article-byline-row .article-author | 912–933 | no DS eq | system-public | Author block overrides inside byline row |
| .article-read-time | 935–940 | no DS eq | system-public | Read time slot; rename → **.article__read-time** |
| .article-author-bio (§6 override) | 944–964 | no DS eq | system-public | Dark footer author bio override for editorial |
| .article-key-statement | 1074–1084 | no DS eq | system-public | Journal key statement (Instrument Serif italic); rename → **.journal__statement** |
| .article-entry-number | 1087–1092 | no DS eq | system-public | Journal entry number; rename → **.journal__entry-number** |
| .article-byline-row--journal | 1096–1097 | no DS eq | system-public | Journal byline layout mod; rename → **.article-byline--journal** |
| .event-card | 1120–1186 | no DS eq | system-public | Live session event details card; rename → **.event-card** or reconcile vs .card |
| .event-meta-block-label | 1130–1137 | no DS eq | system-public | Event "WHEN" / "WHERE" label; rename → **.event-card__label** |
| .event-date, .event-location | 1139–1148 | no DS eq | system-public | Event date/location serif titles |
| .event-time, .event-cost-detail | 1149–1155 | no DS eq | system-public | Event time/cost metadata |
| .event-card-tags | 1156–1175 | no DS eq | system-public | Format tags row; rename → **.event-card__tags** |
| .event-format-tag | 1164–1185 | drifts from DS | system-public | **Drift**: DS .card .fmt (180) is structurally identical but different sizing/weight |
| .event-format-tag.is-free, .is-in-person | 1176–1185 | no DS eq | system-public | Format tag variants (hardcoded colours—not using --c-* tokens) |
| .article-past-badge | 1189–1201 | no DS eq | system-public | Past event badge; rename → **.event__past-badge** or **.past-badge** |
| .index-page | 1227–1229 | no DS eq | system-public | Index page wrapper |
| .index-page-header | 1232–1235 | no DS eq | system-public | Index header section |
| .index-page-header-row | 1236–1242 | no DS eq | system-public | Header row (title + count); rename → **.index-page-header__row** |
| .index-page-header-left | 1243–1247 | no DS eq | system-public | Header left slot; rename → **.index-page-header__left** |
| .index-eyebrow | 1248–1256 | no DS eq | system-public | Index page eyebrow; rename → **.index-page__eyebrow** |
| .index-title | 1257–1276 | no DS eq | system-public | Index page title (with serif emphasis); rename → **.index-page__title** |
| .index-title .serif-em | 1270–1276 | no DS eq | system-public | Serif emphasis within index title |
| .index-subtitle | 1277–1285 | no DS eq | system-public | Index page subtitle; rename → **.index-page__subtitle** |
| .index-count | 1286–1292 | no DS eq | system-public | Count badge on right; rename → **.index-page__count** |
| .index-grid | 1295–1346 | no DS eq | system-public | Card grid wrapper; uses .cards-grid (from views.css) |
| .index-hero, .index-featured | 1296–1346 | no DS eq | system-public | Hero and featured sections (grid variants) |
| .index-hero .card | 1309–1312 | drifts from DS | system-public | **Drift**: DS .index-hero .card rules exist in views.css (1309) but article-version overrides sizing |
| .index-empty | 1314–1320 | no DS eq | system-public | Empty state message |
| .cards-grid a.card (link override) | 1324–1331 | no DS eq | system-public | Card link behaviour override |
| .index-section | 1355–1356 | no DS eq | system-public | Editorial section wrapper; rename → **.index-section** or **.editorial-section** |
| .index-section-header | 1360–1402 | no DS eq | system-public | Section header with "see more" link |
| .index-section-header.is-big | 1360–1366 | no DS eq | system-public | Large section header variant |
| .index-section-title-big | 1369–1384 | no DS eq | system-public | Big section title; rename → **.index-section__title--big** |
| .index-section-view-all | 1391–1402 | no DS eq | system-public | "View all" link; rename → **.index-section__view-all** |
| .index-section-pills | 1407–1424 | no DS eq | system-public | Filter pills row; rename → **.index-section__pills** |
| .index-section-pills .fp | 1413–1424 | drifts from DS | system-public | **Drift**: Overrides canonical .fp (status.css) with neutral hover; should use modifier class |
| .index-section--hero | 1427–1435 | no DS eq | system-public | Hero section layout variant (full-bleed) |
| .editorial-hero | 1437–1619 | no DS eq | system-public | Editorial banner section (left text + right card/image) |
| .editorial-hero--bg-surface, .editorial-hero--bleed-dark, .editorial-hero--bleed-light | 1450–1507 | no DS eq | system-public | Hero background variants (solid, dark gradient, light gradient) |
| .editorial-hero-* (§13 selectors) | 1513–1619 | no DS eq | system-public | Hero sub-components (text, eyebrow, title, summary, footer, CTA, side); rename to **.editorial-hero__** |
| .cards-grid.is-carousel | 1638–1680 | no DS eq | system-public | Carousel layout (horizontal scroll, snap); rename → **.cards-grid--carousel** |
| .article-prose[data-body-mode="html-body"] | 1692–1704 | no DS eq | system-public | HTML body fade-in animation (per MOTION.md) |

---

##### Mobile Notes (style-articles)

###### Breakpoints and Restyles

| Breakpoint | Target Selectors | Changes | Overflow Risk? |
|---|---|---|---|
| **768px** | .article, .article-breadcrumb, .article-topstrip, .article-title.is-serif, .article-summary, .article-dates, .article-byline-row, .article-hero, .article-prose h2/h3/blockquote, .article-series-nav, .article-author-bio, .article-author-bio-avatar | Padding reduction (56/40 → 32/20); font-size reductions (56px → 40px title; 28px → 22px h2); grid collapse (1fr); figure-wide repositioning to static | Low; uses relative units |
| **480px** | .article, .article-breadcrumb, .article-title.is-serif, .article-summary | Further padding (24/16); title 40px → 32px; summary 17px → 16px | Low |
| **480px (event)** | .event-card, .event-date, .event-location | Grid 1fr; padding 32 → 24; font-size 28px → 24px | Low |
| **900px (index)** | .index-page-header, .index-grid, .index-hero, .index-featured | Padding 40/32 → 24/32; title 32px → 24px | Low |
| **600px (index)** | .index-page-header, .index-grid, .index-hero, .index-featured, .index-section, .cards-grid.is-carousel | Padding 32/24 → 24; flex-direction column for header | Low |

**Fixed px widths that may break**:
- Line 487: `.article-hero[data-size="wide"]` — `min(1080px, calc(100vw - 64px))` ✓ safe (uses min())
- Line 684: `.article-prose figure[data-size="wide"]` — same pattern ✓ safe
- Line 490: `.article-hero[data-size="full"]` — `100vw` (full viewport) ✓ safe
- Line 1532: `.editorial-hero-title` — `clamp(34px, 3.8vw, 54px)` ✓ responsive

**No hard-coded pixel widths at risk.**

---

##### Reconciliation to Canonical .card (DS views.css)

| style-articles selector | DS .card equivalent | Reconcile? | Reasoning |
|---|---|---|---|
| .article-series-pill | .card .series-pill | **YES, consolidate** | Identical structure; move to views.css or create shared mixin |
| .article-series-dot / .article-series-dots | .card .sdot / .card .series-dots | **YES, consolidate** | Identical (dot + progress tracking); same token-driven colouring |
| .article-tag | .card .tag | **YES, consolidate** | Identical pill styling; both use --text-pill, border-radius, var(--ink-18) |
| .event-format-tag | .card .fmt | **PARTIAL** | Structure matches .fmt-free/.fmt-paid/.fmt-inperson but event-format-tag has hardcoded hex colours (#246636, #6B4010) instead of DS category tokens (--c-forest, --c-clay); should refactor to use --c-* |
| .event-card | .card--event | **NO, distinct** | event-card is a *block within the article prose*, not a *card in a grid*; 2-column grid + border design differs from .card--event header styling; keep separate but reconcile naming |

**Card-like block selectors with no .card counterpart:**
- .article-author-bio — distinct author card (dark footer in article, light box elsewhere)
- .article-series-nav-link — series navigation (2-col grid nav, not a browsable card)
- .editorial-hero-card — optional featured card in editorial hero (distinct from index card)

---

##### Summary by Bucket

- **tokens** (43 selectors): All match canonical tokens.css except --r-tag (local-only) and --dot-grid (asset URL). **Recommendation**: Move entire :root to tokens.css; delete local dup.
- **system-public** (139 selectors): All block rendering for article, journal, live-session, index, editorial templates. No DS direct counterparts except:
  - .layout-nav, .layout-footer (shared chrome — minor drift in .layout-nav-links a weight/spacing)
  - .article-series-pill / .article-series-dot (duplicate .card .series-pill / .card .sdot)
  - .article-tag (duplicate .card .tag)
  - .event-format-tag (partial match to .card .fmt; uses hardcoded hex instead of --c-* tokens)
- **Dead** (0 selectors): None.

---

##### Drifting Tokens (Needing Attention)

1. **--r-tag (line 90)**: Defined as 3px; DS has no --r-tag. (Pill radius uses --r-pill: 4px.) Consider unifying or documenting.
2. **var(--text-meta) in footer** (line 200): Not declared in :root. Uses 13px value from DS tokens.css. Should either import or declare locally.

---

##### Notable Calls

1. **Category mapping (lines 222–239)** — Elegant: 18 category data-attributes each set --c-current. Works beautifully across series pills, special tags, event badges. No issues.

2. **Editor scaffolding (.figure-size-toggle, lines 707–745)** — Correctly marked as demo-only (dashed border, mono font, removed from production). Clean separation of authoring affordance from content rendering.

3. **Hardcoded event format colours (lines 1177–1185)** — ISSUE: .event-format-tag.is-free uses #246636 (green) and .is-in-person uses #6B4010 (clay); should be `--c-forest` and `--c-clay` respectively. Introduces duplicated colour values outside the token system.

4. **Data-attribute-driven sizing (hero/figure, lines 459–700)** — Solid pattern: data-size="wide"|"full" drives layout (wide: min(1080px, 100vw - 64px), full: 100vw). Centralised in CSS, no JS layout code.

5. **Filter pill override (lines 1413–1424)** — `.index-section-pills .fp` overrides canonical .fp hover colours to neutral (removes category tinting). Works but should be a named modifier (e.g., .fp--neutral) in status.css.

---

##### Recommendations

1. **Consolidate duplicates**: .article-series-pill, .article-series-dot, .article-tag should reuse DS .card rules or be moved to views.css.
2. **Fix event format colours**: Replace hardcoded #246636 and #6B4010 with `--c-forest` and `--c-clay`.
3. **Rename for semantic clarity**: Flatten `.article-*` naming chain into BEM (e.g., .article__title, .article__prose, .byline__name). Adopt `.` prefix for nested elements consistently.
4. **Extract editorial-hero block**: The 170+ lines of .editorial-hero-* could become a top-level .editorial-hero component in system-public or a dedicated module.
5. **Verify layout-footer spacing**: Line 200 references undefined --text-meta; audit against DS before deployment.


---

### 6.4 — `cms/_assets/style-cms.css` + `tiptap.css` + 8 inline blocks (system-cms)

#### CMS CSS Audit: style-cms.css, tiptap.css, Inline Styles


##### BUCKETS

| Bucket | Count | Notes |
|--------|-------|-------|
| `tokens` | 84 | :root definitions in style-cms.css; compare vs tokens.css |
| `system-cms` | 89 | CMS-specific selectors; promotion candidates for DS |
| `tiptap.css` selectors | 9 | Editor chrome; move to system-cms |
| Inline <style> blocks | 47 | Extract & move to system-cms (Phase 22.5/22.6) |
| **Dead** | 0 | None identified |

---

##### TOKENS (:root block, style-cms.css lines 21–135)

###### Token Duplication Analysis

| Token | Value in style-cms.css | Value in tokens.css | Status |
|-------|--------|--------|--------|
| `--primary` | #191715 | #191715 | DUP (matches) |
| `--secondary` | #494846 | #494846 | DUP (matches) |
| `--muted` | #818080 | #818080 | DUP (matches) |
| `--neutral` | #E8E8E8 | #E8E8E8 | DUP (matches) |
| `--canvas-bg` | #F3F2F1 | #F3F2F1 | DUP (matches) |
| **--canvas-raised** | **#ECEAE8** | **NOT IN tokens.css** | **PROMOTION CANDIDATE** |
| `--surface` | #FFFFFF | #F8F8F8 | DUP DRIFTS: style-cms uses #FFF (highest value); tokens.css uses #F8F8F8 |
| `--accent` | #6B7F6E | #6B7F6E | DUP (matches) |
| `--white` | #FFFFFF | #ffffff | DUP (matches, case) |
| `--ink-08` | rgba(25,23,21,0.08) | rgba(25,23,21,0.08) | DUP (matches) |
| `--ink-12` | rgba(25,23,21,0.12) | rgba(25,23,21,0.12) | DUP (matches) |
| `--ink-16` | ONLY in style-cms | NOT IN tokens.css | **PROMOTION CANDIDATE** |
| `--ink-18` | rgba(25,23,21,0.18) | rgba(25,23,21,0.18) | DUP (matches) |
| `--ink-20` | ONLY in style-cms | NOT IN tokens.css | **PROMOTION CANDIDATE** |
| `--ink-30` | rgba(25,23,21,0.30) | rgba(25,23,21,0.30) | DUP (matches) |
| `--ink-mid` | #646361 | #646361 | DUP (matches) |
| `--primary-hover` | #333333 | #333333 | DUP (matches) |
| `--white-45` | rgba(255,255,255,0.45) | rgba(255,255,255,0.45) | DUP (matches) |
| All 18 category colours (--c-rust through --c-rose) | All match | All match | DUP (all 18 match exactly) |
| Stage colours (--stage-idea, etc.) | All match | (tokens.css doesn't define, system-cms.css does) | DUP in style-cms (also in system-cms rules) |
| Type colours (--type-article, etc.) | All match | (tokens.css doesn't define) | DUP in style-cms |
| `--text-*` scale (display, h1–micro) | All match | All match | DUP (all match) |
| `--space-*` scale (4–48px) | 4–48px in style-cms | 4–96px in tokens.css | DUP (style-cms subset matches; tokens.css extends further) |
| `--r-card` | 4px | 4px | DUP (matches) |
| `--r-pill` | 3px | 4px | DUP DRIFTS: style-cms = 3px; tokens.css = 4px |
| `--sidebar-w` | 210px | NOT IN tokens.css | **CMS-only token** |
| `--topbar-h` | 48px | NOT IN tokens.css | **CMS-only token** |
| Dot grid SVG | (inline data URI) | NOT IN tokens.css | **CMS-only token** |

###### Tokens to Promote to tokens.css

1. **--canvas-raised** (#ECEAE8) — raised background for sidebar/filter bars in CMS
2. **--ink-16** (rgba(25,23,21,0.16)) — 16% black tint (faint divider)
3. **--ink-20** (rgba(25,23,21,0.20)) — 20% black tint (hover outline on clickable rows)
4. CMS layout tokens (**--sidebar-w: 210px**, **--topbar-h: 48px**) — move to a dedicated CMS tokens section or keep in style-cms if CMS-only

---

##### SYSTEM-CMS SELECTORS (style-cms.css, layers 2–7)

###### Sample Rows (Complete audit table follows below)

| Selector | File | Bucket | Drift vs DS | Proposed Name + Note |
|----------|------|--------|-------------|---------------------|
| `.topbar` | style-cms.css | system-cms | `matches DS` (shell.css) | Keep `.topbar`; height via `--topbar-h` |
| `.sidebar` | style-cms.css | system-cms | `drifts from DS`: width 210px (fixed) vs DS 168px (in showcase) | `.sidebar` canonical in live admin |
| `.nav-section` | style-cms.css | system-cms | no DS equivalent | `.nav-section` (sidebar grouping) |
| `.nav-item` | style-cms.css | system-cms | `matches DS concept` (sidebar-link) | Unify as `.nav-item` across both |
| `.nav-count` | style-cms.css | system-cms | no DS equivalent | `.pill--count` (notification badge) |
| `.btn-pri` | style-cms.css | system-cms | `matches DS .cms-btn-pri` | Unify as `.btn--primary` |
| `.btn-sec` | style-cms.css | system-cms | `matches DS .cms-btn-sec` | Unify as `.btn--secondary` |
| `.btn-ghost` | style-cms.css | system-cms | alias to `.btn-sec` | Deprecate; remove alias |
| `.btn-danger` | style-cms.css | system-cms | no DS equivalent | Unify as `.btn--danger` |
| `.btn-icon` | style-cms.css | system-cms | no DS equivalent | `.btn--icon` + `.btn--icon-danger` variant |
| `.btn-tiny` | style-cms.css | system-cms | size modifier | `.btn--sm` (small) |
| `.filter-bar` | style-cms.css | system-cms | no DS equivalent | Keep `.filter-bar` (CMS-specific) |
| `.filter-pill` | style-cms.css | system-cms | `drifts from DS .fp` | Rename to `.pill--filter`; consolidate with .fp rules |
| `.view-title` | style-cms.css | system-cms | canonical rule for both `.view-title` + `.pipeline-title` | Rename `.page-title` (unify across CMS) |
| `.cms-table` | style-cms.css | system-cms | canonical (live admin) | Keep `.cms-table`; DS tables.css is showcase |
| `.cms-table th` | style-cms.css | system-cms | Live admin is source of truth | Keep as-is |
| `.pill` | style-cms.css | system-cms | stage/status pills; matches pattern | Consolidate into `.pill` + modifiers (--idea, --draft, etc.) |
| `.kcard` | style-cms.css | system-cms | kanban card (Draft Writing view) | Rename `.kanban-card` |
| `.idea-card` | style-cms.css | system-cms | ideation board card | Keep or rename to `.card--idea` |
| `.pub-card` | style-cms.css | system-cms | published content grid | `.card--published` or `.pub-card` |
| `.rowform-*` | style-cms.css | system-cms | row-edit forms (categories, redirects, navigation, subscribers) | Keep `.rowform` family (inline editable rows) |

---

##### TIPTAP.CSS SELECTORS (cms/_assets/tiptap.css)

| Selector | File | Bucket | Drift | Move-To | Proposed Name + Note |
|----------|------|--------|-------|---------|---------------------|
| `.tiptap-wrap` | tiptap.css | system-cms | no DS equivalent | system-cms | `.editor-wrap` (article editor container) |
| `.tiptap-toolbar` | tiptap.css | system-cms | no DS equivalent | system-cms | `.editor-toolbar` (sticky format controls) |
| `.tiptap-toolbar .tt-btn` | tiptap.css | system-cms | button variant | system-cms | `.btn--editor` (editor toolbar button) |
| `.tiptap-editor` | tiptap.css | system-cms | no DS equivalent | system-cms | `.editor-pane` (contenteditable surface) |
| `.tiptap-editor .ProseMirror` | tiptap.css | system-cms | no DS equivalent | system-cms | (inherits .article-prose from public templates) |
| `.tt-fig-panel` | tiptap.css | system-cms | floating control panel | system-cms | `.figure-panel` or `.editor-figure-panel` |
| `.tt-fig-btn` | tiptap.css | system-cms | button variant | system-cms | `.btn--figure-toggle` |
| `.tiptap-fallback` | tiptap.css | system-cms | fallback textarea | system-cms | Keep for progressively-enhanced fallback |

---

##### INLINE STYLES IN VIEWS (8 files)

###### post-template.php (inline, lines 127–149)

| Selector | Bucket | Drift | Move-To | Proposed Name + Note |
|----------|--------|-------|---------|---------------------|
| `#view-post-template` | system-cms | view-scoped | system-cms | `.view--post-template` |
| `.tpl-panel.is-server-active` | system-cms | no DS equiv | system-cms | `.template-panel` + `.is-visible` |
| `.block-mode-pill.mode-optional` | system-cms | no DS equiv | system-cms | `.pill--mode-optional` |
| `.ct-code-editor + .CodeMirror` | system-cms | CodeMirror integration | system-cms | `.code-editor` wrapper |
| `.ct-code-missing` | system-cms | no DS equiv | system-cms | `.code-missing` or `.placeholder-missing` |
| `.ct-author-grid` | system-cms | author profile layout | system-cms | `.author-grid` |
| `.ct-author-avatar-col` | system-cms | no DS equiv | system-cms | `.author-avatar-column` |
| `.ct-author-avatar` | system-cms | no DS equiv | system-cms | `.author-avatar` |
| `.field-req` | system-cms | required field marker | system-cms | Keep `.field-req` (visible in multiple views) |

###### page-edit.php (inline, lines 236–281)

| Selector | Bucket | Drift | Move-To | Proposed Name + Note |
|----------|--------|-------|---------|---------------------|
| `.pe-version-row` | system-cms | no DS equiv | system-cms | `.editor-version-row` |
| `.pe-version-label` | system-cms | no DS equiv | system-cms | `.editor-version-label` |
| `.pe-version-row select` | system-cms | field styling | system-cms | Extend `.field-select` |
| `.pe-version-actions` | system-cms | action row | system-cms | `.row-actions` (reusable) |
| `.pe-override-note` | system-cms | editorial note | system-cms | `.editor-override-note` |
| `.pe-unsaved` | system-cms | dirty state indicator | system-cms | `.editor-unsaved` |
| `.CodeMirror` | system-cms | third-party lib | system-cms | Keep as-is (CodeMirror owns styling) |
| `.pe-meta-form` | system-cms | metadata form grid | system-cms | `.form-grid--meta` |
| `.pe-meta-charcount` | system-cms | character count label | system-cms | `.form-charcount` |
| `.pe-unfurl-*` | system-cms | link preview card | system-cms | `.link-preview` family |
| `.pe-inline-form` | system-cms | display:inline wrapper | system-cms | Utility class; keep minimal |
| `.pe-version-actions .btn-sec/.btn-pri` | system-cms | button alignment hack | system-cms | Extract to `.btn--align-center` or consolidate |

###### subscribers.php (inline, lines 136–142)

| Selector | Bucket | Drift | Move-To | Proposed Name + Note |
|----------|--------|-------|---------|---------------------|
| `.sub-status` | system-cms | subscription status badge | system-cms | `.status--subscriber` or `.pill--subscription` |
| `.sub-status.sub` | system-cms | subscribed variant | system-cms | `.status--subscribed` |
| `.sub-status.uns` | system-cms | unsubscribed variant | system-cms | `.status--unsubscribed` |

###### redirects.php (inline, lines 102–115)

| Selector | Bucket | Drift | Move-To | Proposed Name + Note |
|----------|--------|-------|---------|---------------------|
| `.red-list` | system-cms | redirect table container | system-cms | `.rowform--redirects` (variant) |
| `--rowform-cols` (custom property) | system-cms | grid columns template | system-cms | Keep as scoped custom property |

###### navigation.php (inline, lines 152–229)

| Selector | Bucket | Drift | Move-To | Proposed Name + Note |
|----------|--------|-------|---------|---------------------|
| `.nav-list` | system-cms | navigation item container | system-cms | `.rowform--navigation` (variant) |
| `.nav-row.is-broken` | system-cms | error state | system-cms | `.rowform-row.is-error` |
| `.nav-row .grip` | system-cms | drag handle | system-cms | `.grip-handle` (reusable) |
| `.nav-picker` | system-cms | dropdown/picker | system-cms | `.picker` or `.nav-picker` |
| `.np-mark` | system-cms | pill/dot preview cell | system-cms | `.mark-preview` or `.nav-mark` |
| `.np-dot` | system-cms | colour dot | system-cms | `.dot--colour` |
| `.np-mark.hl-pill input` | system-cms | pill-styled input | system-cms | `.input--pill-preview` |
| `.pill-broken` | system-cms | error badge | system-cms | `.pill--error` or `.badge--broken` |

###### post-preview.php, post-preview-form.php, post-template-preview.php

No major inline `<style>` blocks (preview iframes use inline body styles only; not CMS components).

---

##### BUTTON DEEP-DIVE

All buttons across style-cms.css, tiptap.css, and inline styles:

| Selector | Font-Family | Text-Transform | Font-Size | Padding | When It Fires | File |
|----------|-------------|-----------------|-----------|---------|---------------|------|
| `.btn-sec` | var(--font-cond) UPPERCASE | uppercase | text-label (12px) | 7px 16px | secondary action, cancel, edit, row-level | style-cms.css:254 |
| `.btn-pri` | var(--font-cond) UPPERCASE | uppercase | text-label (12px) | 7px 16px | primary CTA (Save, Publish) | style-cms.css:262 |
| `.btn-danger` | var(--font-cond) UPPERCASE | uppercase | text-label (12px) | 7px 14px | destructive (Delete) | style-cms.css:266 |
| `.btn-icon` | icon-only, no text | n/a | svg 13×13px | 0 | icon buttons | style-cms.css:275 |
| `.btn-icon-danger` | icon-only, no text | n/a | svg 13×13px | 0 | destructive icon (trash) | style-cms.css:275 |
| `.btn-tiny` | size modifier | uppercase | text-label | 7px 14px (reduced) | compact buttons in rows | style-cms.css:281 |
| `.btn-add-dashed` | var(--font-cond) UPPERCASE | uppercase | text-micro (9px) | 8px | add new row button | style-cms.css:281 |
| `.qc-btn` | var(--font-cond) UPPERCASE | uppercase | text-micro (9px) | 8px 16px | quick-capture CTA | style-cms.css:505 |
| `.tt-btn` (tiptap) | var(--font-sans) | none | text-tiny (10px) | space-6 space-10 (6px 10px) | toolbar format button | tiptap.css:42 |
| `.tt-fig-btn` | var(--font-cond) UPPERCASE | uppercase | text-micro (9px) | space-4 space-12 | figure size toggle | tiptap.css:166 |
| `.btn-sec` (page-edit inline) | var(--font-cond) UPPERCASE | uppercase | text-label (12px) | 7px 16px | aligned inline in version actions | page-edit.php:270 |
| `.btn-pri` (page-edit inline) | var(--font-cond) UPPERCASE | uppercase | text-label (12px) | 7px 16px | aligned inline in version actions | page-edit.php:270 |
| `.cat-del` | var(--font-cond) UPPERCASE | uppercase | text-label (12px) | 0 (26×26px icon button) | delete category (hidden until enabled) | style-cms.css:273 |
| `.btn-row-del` | icon-only | none | svg 14×14px | 4px | inline trash in subscriber rows | style-cms.css:278 |

###### Button System Summary

**Current state:** Mixed fonts and text transforms.
- `.btn-pri` + `.btn-sec` + `.btn-danger` + `.btn-tiny` + `.btn-add-dashed`: **var(--font-cond) UPPERCASE**
- `.tt-btn`: **var(--font-sans) none** (editor-only; different context)
- `.tt-fig-btn`: **var(--font-cond) UPPERCASE**
- `.qc-btn`: **var(--font-cond) UPPERCASE**

**Unification path:**
1. Keep CMS buttons (non-editor) as **condensed uppercase** (var(--font-cond))
2. Editor buttons (.tt-btn) stay in **sans lowercase** (different affordance; editing context)
3. Remove the ghost/deprecated selectors (.btn-ghost alias to .btn-sec)
4. Extract size modifiers into explicit classes: `.btn--sm`, `.btn--lg`
5. Consolidate icon-only and icon-danger into `.btn--icon` family with modifiers

---

##### NOTABLE FINDINGS

1. **--surface DRIFTS:** style-cms.css = #FFFFFF (pure white); tokens.css = #F8F8F8 (off-white). The live admin uses pure white for cards/surfaces; the design system uses an off-white. Clarify intent — if live admin should be canonical, promote #FFF and update tokens.css.

2. **--radius-md not in style-cms.css:** tiptap.css uses `var(--radius-md)` but style-cms.css does not define it. It likely falls through to tokens.css. Audit line 22 of tiptap.css.

3. **Rowform family is powerful and reusable:** .rowform-* (categories, redirects, navigation) is a generic inline-edit pattern. Document as a design system component; DS currently has no equivalent.

4. **CodeMirror styling:** Several inline overrides for CodeMirror (page-edit.php, post-template.php). CodeMirror owns most of its styling; keep CSS minimal and document the integration boundary.

5. **Button alignment hack in page-edit.php (line 266–280):** The `.pe-version-actions .btn-sec/.btn-pri` rules exist because buttons need uniform height for inline alignment. This suggests a missing `.btn-group--aligned` or height-normalization utility. Extract this pattern.

6. **No button-state distinction in CSS:** All buttons use `.btn-pri` (filled) / `.btn-sec` (outline). Ghost buttons are aliased to secondary. No separate "loading," "disabled," or "loading" button states in the CSS (likely handled via DOM classes in JS). Audit dirty-flip.js for state-change patterns.

---

##### SUMMARY: Bucket Counts & Action Items

| Bucket | Count |
|--------|-------|
| **tokens** (to promote) | 84 |
| **system-cms** | 89 |
| **tiptap.css** | 9 |
| **Inline <style>** | 47 |
| **Dead** | 0 |
| **TOTAL** | 229 |

**Tokens to promote to tokens.css:**
- `--canvas-raised`, `--ink-16`, `--ink-20` (colour tints)
- `--sidebar-w`, `--topbar-h` (CMS layout, OR keep in style-cms if CMS-only)

**system-cms.css consolidation:**
- Extract all tiptap.css selectors + move to system-cms > editor sub-section
- Extract all 47 inline <style> selectors from views; namespace by view (Phase 22.5/22.6)
- Unify button family: `.btn--primary`, `.btn--secondary`, `.btn--danger`, `.btn--icon`, `.btn--sm`
- Rename/consolidate pill/status selectors: `.pill` + modifiers, `.status--*` + modifiers
- Document rowform pattern as reusable component

**No dead selectors identified:** All selectors have callsites in PHP views.


---

### 6.5 Complete `style-cms.css` selector ledger (coverage guarantee)

The §6.4 table categorises the CMS families and exceptions. This ledger enumerates **every** selector-line in `style-cms.css` (769 total) so the "no selector uncategorised" criterion holds literally. **Bucket for all rows below: `system-cms`** (move-to-path: `system-cms.css`). The only non-`system-cms` selector in the file is `:root` → `tokens` (see §5). Zero `Dead`. Grouped by the file's 8 layers; line numbers in brackets.

**LAYER 2 — Base reset + dot-grid surface** [137–163]:

`*,*::before,*::after` `html` `body` `.dot-surface` `::-webkit-scrollbar` `::-webkit-scrollbar-track` `::-webkit-scrollbar-thumb` `::-webkit-scrollbar-thumb:hover` `0%,100%` `50%` `.live-dot` 

**LAYER 3 — Shell (topbar · sidebar · layout)** [164–234]:

`.topbar` `.topbar-logo` `.topbar-logo em` `.topbar-env-pill` `.topbar-logo-sep` `.topbar-divider` `.topbar-breadcrumb` `.topbar-breadcrumb .crumb-active` `.topbar-breadcrumb .crumb-sep` `.topbar-breadcrumb .crumb-rest` `a.crumb-active:hover` `.topbar-right` `.layout` `.sidebar` `.nav-section` `.nav-section:last-child` `.nav-label` `.nav-item` `.nav-item:hover` `.nav-item.is-placeholder` `.nav-item.is-placeholder:hover` `.nav-item.active` `.nav-item.sub` `.nav-item.sub:hover` `.nav-item.sub.active` `.nav-count` `.nav-count.is-new` `.nav-icon` `.nav-item.is-placeholder .nav-icon` `.nav-item:hover .nav-icon,.nav-item.active .nav-icon` `.nav-new-idx` `.nav-new-idx:hover` `.main` `.view` `.view.active` 

**LAYER 4 — Components (buttons · filter bar · fields)** [235–360]:

`.btn-link` `.btn-link:hover` `.btn-save` `.btn-save:hover` `.cat-del` `.cat-del.ok:hover` `.cat-del` `.cat-del.ok` `.cat-del svg` `.cat-del[disabled]` `.btn-row-del` `.btn-row-del svg` `.btn-row-del:hover` `.btn-add-dashed` `.btn-add-dashed:hover` `.filter-bar` `.filter-label` `.filter-group` `.filter-pill` `.filter-pill.active` `.filter-pill:hover` `.filter-pill.active` `.filter-pill.all-btn.active` `.filter-pill[style*="--pill-cat"]:hover` `.filter-pill[style*="--pill-cat"].active` `.filter-sep` `.view-header` `.view-header-left` `.pipeline-title` `.pipeline-desc` `.view-header-actions` `.content-area` `.content-block` `.content-block-header` `.content-block-label` `.content-block-sublabel` `.content-block-count` `.field-group` `.field-label` `.field-req` `.field-input` `.field-input:focus` `.field-input::placeholder` `.field-input.large` `.field-input[name="slug"]` `.field-input[name="summary"]` `textarea.field-input` `.field-select` `.field-select:focus` `.slug-field` `.slug-field:focus-within` `.slug-prefix` `.slug-input` `.field-note-box` `.grid-2` `.row-btn-group` 

**LAYER 5 — Table primitives** [361–413]:

`.table-card` `.cms-table` `.cms-table th` `.cms-table th:first-child` `.cms-table th:last-child` `.cms-table td` `.cms-table td:first-child` `.cms-table td:last-child` `.cms-table td.cell-actions, .cms-table th:last-child` `.cms-table--cat td.cell-actions, .cms-table--cat th:last-child` `.cms-table tr:last-child td` `.cms-table tbody tr.row-clickable:hover` `.cms-table tbody tr.row-clickable:hover td` `.table-card:has(tbody tr.row-clickable:hover)` `.td-title` `.td-title .t` `.td-title .t:hover` `.td-title .slug` `.td-title .slug.live` `.td-mono` `.td-actions` `.td-actions-inner` 

**LAYER 6 — Status + stage system** [414–473]:

`.pill` `.pill-idea` `.pill-concept` `.pill-outline` `.pill-draft` `.pill-live` `.pill-scheduled` `.cms-updated-group` `.cms-updated-input-row` `.cms-updated-input-row .field-input` `.cms-updated-input-row .field-input:disabled` `.cms-updated-input-row .field-input.is-default` `.cms-updated-clear` `.cms-updated-clear:hover` `.cms-updated-clear[hidden]` `.pill-hidden` `.pill.pill-type` `.tb-article` `.tb-journal` `.tb-live-session` `.tb-experiment` `.cat-label` `.cat-label-dot` `.cat-label-icon` `.tag` `.tag.special` `.val-pill` `.val-pill:hover` 

**LAYER 7 — View-specific layouts** [474–1754]:

`.pipeline-header` `.pipeline-desc` `.dash-meta` `.view > .dash-meta` `.view > .dash-meta:has(+ .filter-bar)` `.view > .dash-meta + .filter-bar` `.dash-stat` `.dash-stat .num` `.dash-stat .lbl` `.dash-stat-div` `.quick-capture` `.qc-input` `.qc-input:focus` `.qc-input::placeholder` `.qc-select` `.qc-btn` `.qc-btn:hover` `.dash-top` `.kanban-board` `.kanban-lane` `.kanban-lane:last-child` `.lane-header` `.lane-dot` `.lane-title` `.lane-count` `.lane-cards` `.kgroup-label` `.kgroup-label:first-child` `.kcard` `.kcard:hover` `.kcard-head` `.kcard-title` `.kcard-summary` `.kcard-meta` `.kcard-foot` `.kcard-date` `.kcard.idea .kcard-summary,.kcard.idea .kcard-meta,.kcard.idea .kcard-foot` `.kcard.concept .kcard-foot` `.ideation-layout` `.ideation-capture` `.idea-feed` `.idea-row` `.idea-row:hover` `.idea-row-title` `.idea-row-meta` `.idea-row-promote` `.idea-card` `.idea-card:hover, .idea-card:focus, .idea-card:active, .idea-card:visited` `.idea-card:hover` `.idea-card *` `.idea-card:active` `.idea-card-title` `.idea-card-desc` `.idea-card-foot` `.idea-card-meta` `.idea-build` `.idea-lane-empty` `.pub-section` `.pub-section-header` `.pub-section-title` `.pub-section-count` `.pub-grid` `.pub-card` `.pub-card:hover` `.pub-card.dimmed` `.pub-card-status` `.pub-card-hd` `.pub-card-cat` `.pub-card-bd` `.pub-card-title` `.pub-card-title.serif` `.pub-card-summary` `.pub-card-tags` `.pub-card-ft` `.pub-card-meta` `.pub-card-arrow` `.pub-card.dark` `.pub-card.dark.proto` `.pub-card.dark:hover` `.pub-card.dark.proto:hover` `.pub-card.dark .pub-card-title` `.pub-card.dark .pub-card-summary` `.pub-card.dark .pub-card-ft` `.pub-card.dark .pub-card-meta` `.pub-card.dark .pub-card-arrow` `.pub-card.dark .tag` `.templates-layout` `.template-list` `.tpl-master` `.tpl-master:hover` `.tpl-master.active` `.tpl-master-label` `.tpl-master-name` `.tpl-master-desc` `.tpl-item` `.tpl-item:hover` `.tpl-item.active` `.tpl-item-name` `.tpl-sys` `.tpl-item-desc` `.tpl-detail` `.block-mode-pill` `.block-mode-pill.mode-always` `.block-mode-pill.mode-auto` `.block-mode-pill.mode-required` `.block-slug` `.post-edit-tabs` `.tpl-tabs` `.post-edit-tabs` `.post-edit-tab` `.post-edit-tab:hover` `.post-edit-tab.active` `.tpl-tab.active` `.pe-tabs` `.view > .pe-tabs` `.pe-tab` `.pe-tab:hover` `.pe-tab.is-active` `.post-preview-frame` `.post-preview-iframe` `.is-hidden-tab` `.body-source-header` `.body-source-toggle` `.body-source-option` `.body-source-option input[type="radio"]` `.body-source-option:hover` `.body-source-option.is-active` `.body-source-option.is-active:hover` `.body-source-option:focus-within` `.post-edit-tabs` `.preview-save-bar` `.preview-save-bar-right` `.tpl-panel` `.tpl-panel.active` `.fv-var` `.fv-opt-tbl` `.fv-opt-tbl:first-child` `.fv-opt-tbl:last-child` `.fv-opt-tbl:hover` `.fv-opt-tbl.show` `.fv-opt-tbl.hide` `.fv-inherited` `.cms-table--reference` `.cms-table--reference th` `.cms-table--reference td` `.cms-table--reference tr:last-child td` `.table-card--reference` `.cms-table--reference td.cell-mono` `.cms-table--reference td.cell-note` `.field-name` `.field-note-cell` `.fv-field-sel` `.categories-layout` `.cat-block` `.cat-block-title` `.cat-block-note` `.cms-table--cat` `.cms-table--cat th` `.cms-table--cat th:first-child` `.cms-table--cat th:last-child` `.cms-table--cat td` `.cms-table--cat td:first-child` `.cms-table--cat td:last-child` `.cms-table--cat tr:last-child td` `.table-card--cat` `.cms-table--sub` `.cms-table--sub th` `.cms-table--sub th:first-child` `.cms-table--sub th:last-child` `.cms-table--sub td` `.cms-table--sub td:first-child` `.cms-table--sub td:last-child` `.cms-table--sub tr:last-child td` `.cms-table--sub td.is-mono` `.pill-new` `.cms-table--sub tbody tr.sub-row-unseen td` `.cms-table--sub tbody tr.sub-row-unseen:hover td` `.cat-input` `.cat-input:focus` `.cat-swatch` `.cat-hex` `.cat-colour-select` `.cat-colour-select:hover` `.cat-colour-select:focus` `.cms-table--cat td.cat-count` `.cms-table--cat td.cat-count.zero` `.cms-table--cat .btn-icon-danger` `.cms-table--cat tr:focus-within .btn-icon-danger` `.cat-add-row` `.cat-add-input` `.cat-add-input:focus` `.cat-add-preview` `.cat-add-row .cat-colour-select` `.cat-add-row .cat-colour-picker .cat-colour-trigger` `.cat-add-row .btn-pri.cat-add-btn` `.cat-colour-picker` `.cat-colour-trigger` `.cat-colour-trigger:hover` `.cat-colour-arrow` `.cat-colour-menu` `.cat-colour-menu[hidden]` `.cat-block:has(.cat-colour-menu:not([hidden]))` `.cat-colour-opt` `.cat-colour-opt:hover` `.cat-colour-opt.is-selected` `.cms-table--cat td.cell-actions [data-save-btn]` `.cms-table--cat td.cell-actions [data-save-btn].btn-pri` `.cms-table--cat tbody tr .btn-icon-danger` `.cms-table--cat tbody tr:focus-within .btn-icon-danger` `.cms-table--cat td.cell-actions .btn-icon` `.series-grid` `.series-card` `.series-card--new` `.series-card-hd` `.series-card-title` `.series-card-title--new` `.series-meta-row` `.series-path` `.series-live-btn` `.series-desc` `.series-card-save-row` `.series-card-save-row--new` `.series-add-part` `.series-add-part .field-select` `.series-delete-row` `.series-delete-row .is-disabled` `.series-empty` `.series-new-fields` `.series-new-slug` `.series-part-remove-form` `.series-part .btn-icon-danger` `.series-part:focus-within .btn-icon-danger` `.series-card-count` `.series-parts` `.series-part` `.series-part:hover, .series-part:focus-within` `.series-card-save-row [data-save-btn]` `.series-card-save-row [data-save-btn].btn-pri` `.part-num` `.part-title` `.part-date` `.part-drag` `.series-add` `.series-add:hover` `.series-add-inner` `.series-add-icon` `.series-add-label` `.editor-shell` `.editor-main` `.editor-aside` `.editor-aside-section` `.editor-aside-section:last-child` `.aside-label` `.aside-meta` `.stage-bar` `.stage-bar-step` `.stage-bar-step:last-child` `.stage-bar-step.done` `.stage-bar-step.current` `.status-sel` `.status-opt` `.status-opt:first-child` `.status-opt:last-child` `.status-opt:hover` `.status-opt.active-live` `.status-opt.active-hidden` `.status-opt.active-scheduled` `.folder-block` `.folder-block-hd` `.folder-path` `.folder-status` `.folder-block-bd` `.tag-editor` `.tag-editor-item` `.tag-editor-item .tag-remove` `.tag-editor-item .tag-remove:hover` `.vis-check` `.vis-check input[type="checkbox"]` `.form-actions` `.form-actions .btn-actions-end` `.form-actions .btn-actions-end ~ .btn-actions-end` `.idx-builder` `.idx-meta-row` `.idx-meta-row .field-group` `.idx-layout-sel` `.radio-card-group` `.radio-card` `.radio-card:hover` `.radio-card:has(input[type="radio"]:checked)` `.radio-card input[type="radio"]` `.radio-card strong` `.radio-card .field-hint` `.idx-layout-opt` `.idx-layout-opt:first-child` `.idx-layout-opt:last-child` `.idx-layout-opt:hover` `.idx-layout-opt.active` `.page-canvas` `.page-canvas-label` `.page-canvas-label .toggle-lbl` `.page-block` `.page-block-hd` `.page-block-hd:hover` `.page-block-hd.open` `.page-block-label` `.page-block-hint` `.page-block-chevron` `.page-block-hd.open .page-block-chevron` `.page-block-body` `.page-block-hd.open + .page-block-body` `.hero-sel-row` `.hero-thumb` `.hero-info` `.hero-info .ht` `.hero-info .hs` `.hero-change` `.featured-list` `.featured-item` `.drag-handle` `.fi-title` `.fi-type` `.fi-remove` `.fi-remove:hover` `.source-builder` `.source-row` `.source-row:last-child` `.source-row-label` `.source-chips` `.source-chip` `.source-chip:hover` `.source-chip.on` `.toggle-switch` `.toggle-switch.on` `.toggle-switch::after` `.toggle-switch.on::after` `.row-sel` `.row-opt` `.row-opt:first-child` `.row-opt:last-child` `.row-opt:hover` `.row-opt.active` `.info-box` `.info-box strong` `.success-box` `.override-item` `.override-field` `.override-badge` `.empty-state` `.simple-note` `.simple-config` `.readonly-block` `.readonly-block:empty::before` `.tb-none` `tr.row-clickable` `tr.row-clickable:hover` `.tiptap-wrap.body-box` `.content-area:has(.tiptap-wrap.body-box)` `.tiptap-wrap.body-box:focus-within` `.tiptap-wrap.body-box .tiptap-editor` `.tiptap-wrap.body-box .tiptap-toolbar` `.tiptap-wrap.body-box .tt-btn` `.tiptap-wrap.body-box .tt-btn:hover` `.tiptap-wrap.body-box .tt-btn:active` `.tiptap-wrap.body-box .tt-btn.is-active` `.kanban-board[data-dnd-mode] .idea-card` `.kanban-board[data-dnd-mode] .idea-card:active` `.idea-card.dragging` `.kanban-lane.drag-over` `.kanban-lane.drag-over .lane-header` `.cms-form` `.cms-form-wide` `.content-area > .cms-form > .form-actions-sticky` `.content-area > .cms-form:has(.form-actions-sticky)` `.content-area > .cms-form:has(.form-actions-sticky)` `.content-area > .cms-form:has(.form-actions-sticky) > .info-box` `.content-area:has(.form-actions-sticky)` `.content-area > [data-tab-panel]` `.content-area > [data-tab-panel] > form:has(.form-actions-sticky)` `.content-area > [data-tab-panel] > form:has(.form-actions-sticky) > .form-actions-sticky` `.form-grid` `.form-main` `.form-side` `.form-actions-sticky` `.form-actions-sticky.is-hidden` `.form-errors` `.form-errors strong` `.form-errors ul` `.view > .flash-success` `.ideation-capture > .flash-success` `.pipeline-header > .flash-success` `.flash-success` `.flash-undo` `.flash-undo .btn-link` `.flash-undo .btn-link:hover` `.field-hint` `.field-hint code` `.field-hint-inline` `.field-hint-inline::before` `.field-hint-inline::after` `.field-file` `.hero-preview` `.hero-preview img` `.hero-remove` `.hero-remove input[type="checkbox"]` `.hero-remove:hover` `.hero-remove:has(input:checked)` `.hero-preview:has(.hero-remove input:checked) img` `.row-title` `.row-title:hover` `.row-slug` `.row-actions` `.cell-actions` `.row-actions-hover` `tr.row-clickable:focus-within .row-actions-hover` `.cell-chip-group` `.content-block .cms-table` `.content-block .cms-table th:first-child` `.content-block .cms-table td` `.row-cell-hint` `.pill.pill-info` `.pill.pill-warn` `.pill.pill-override` `.kcard-schedule-clock` `.content-type-pill` `.cat-chip` `.btn-icon` `.btn-icon:hover` `.btn-icon-danger:hover` `.btn-tiny` `.row-actions-hover` `.muted` `.pe-readonly-notice` `.ct-readonly-note` `.danger-zone` `.pill-published` `.pill.special-principle` `.pill.special-framework` `.pill.pill-past` `.pills-grid` `.pills-grid > div` `.field-sublabel` `.cms-publish-box` `.cms-publish-box .field-label` `.cms-hero-box` `.cms-hero-header` `.cms-hero-header .field-label` `.cms-hero-preview` `.cms-hero-preview.is-loaded` `.cms-hero-preview.is-empty` `.cms-hero-preview img` `.cms-hero-empty` `.sr-only` `.cms-hero-trash` `.cms-hero-preview.is-loaded .cms-hero-trash` `.cms-hero-trash:hover` `.cms-hero-trash:focus-visible` `.cms-hero-pick-row` `.cms-hero-pick-btn` `.cms-hero-pick-name` `.cms-hero-controls` `.cms-hero-controls-label` `.cms-hero-size-group` `.cms-hero-size-btn` `.cms-hero-size-btn:hover` `.cms-hero-size-btn.is-active` `.cms-hero-caption` `.cms-hero-hint` `.cms-publish-box.is-live` `.cms-publish-header` `.cms-live-indicator` `.cms-live-dot` `0%` `100%` `.cms-live-dot` 

**LAYER 8 — Load reveal / fade utilities** [1755–2290]:

`from` `to` `.reveal > *` `.reveal > *:nth-child(1)` `.reveal > *:nth-child(2)` `.reveal > *:nth-child(3)` `.reveal > *:nth-child(4)` `.reveal > *:nth-child(5)` `.reveal > *:nth-child(6)` `.reveal > *:nth-child(7)` `.reveal > *:nth-child(8)` `.reveal > *:nth-child(9)` `.reveal > *:nth-child(n+10)` `from` `to` `.reveal-page` `.reveal > *, .reveal-page` `.rowform-list.reveal > .rowform-row` `.rowform-list.reveal > :nth-child(1)` `.rowform-list.reveal > :nth-child(2)` `.rowform-list.reveal > :nth-child(3)` `.rowform-list.reveal > :nth-child(4)` `.rowform-list.reveal > :nth-child(5)` `.rowform-list.reveal > :nth-child(6)` `.rowform-list.reveal > :nth-child(7)` `.rowform-list.reveal > :nth-child(8)` `.rowform-list.reveal > :nth-child(9)` `.rowform-list.reveal > :nth-child(n+10)` `.rowform-list.reveal > .rowform-row > form > *` `.rowform-list.reveal > .rowform-add-row > form > *` `.fade-on-load` `.fade-on-load.is-loaded` `.fade-on-load` `.cms-publish-toggle` `.cms-publish-check` `.cms-publish-check input[type="checkbox"]` `.cms-publish-schedule` `.cms-publish-schedule[hidden]` `.cms-publish-schedule input[type="datetime-local"]` `.live-banner` `.schedule-banner` `.live-banner` `.schedule-banner-icon` `.live-banner-text` `.schedule-banner-text` `.schedule-banner-text strong` `.live-banner-text strong` `.schedule-countdown` `.live-banner-link` `.live-banner-link:hover` `.pipeline-load-more` `.pipeline-load-more:hover` `.pipeline-load-more-hidden` `.rowform-list` `.rowform-headers` `.rowform-headers > span` `.rowform-row` `.rowform-row > form` `.rowform-row select` `.rowform-row select:hover` `.rowform-row select:focus` `.rowform-row [data-save-btn]` `.rowform-row [data-save-btn].btn-pri` `.rowform-row .btn-icon-danger` `.rowform-row:focus-within .btn-icon-danger` `.rowform-row.is-dragging` `.rowform-row.is-over-top` `.rowform-row.is-over-bottom` `.rowform-add-row` `.rowform-row[draggable="true"]:focus-within` `.parts-list .rowform-row` `.parts-list .rowform-row.is-published` `.part-date` `.sec-list` `#view-index-edit .form-actions:not(.form-actions-sticky)` `#view-index-edit .form-actions .btn-sec[disabled]` `.sec-card` `.sec-card:hover` `.sec-card-head` `.sec-card-head .kcard-title` `.sec-card-body` `.sec-card.is-collapsed .sec-card-body` `.sec-card.is-collapsed .sec-card-head` `.sec-card > .sec-card-body:first-child` `#view-index-edit .field-group` `#view-index-edit .filter-group` `.drop-line` `.sec-list .drop-line` `.posts-table tr.drop-line-row > td` `.sec-card.is-fresh` `0%` `100%` `#view-index-edit select.field-input` `.see-target-row` `.form-grid-3` `.form-grid-3 > div` `.field-clearable` `.field-clearable .field-input` `.field-clearable .clear-x` `.field-clearable .clear-x:hover` `.field-clearable .field-input:placeholder-shown + .clear-x` `.switch-filled` `.switch-filled input` `.switch-filled .slider` `.switch-filled .slider::before` `.switch-filled input:checked + .slider` `.switch-filled input:checked + .slider::before` `.cat-rail` `.cat-rail .cat-group + .cat-group` `.cat-rail .cat-group + .cat-group::before` `.cms-grip` `.hero-img-preview` `.hero-img-preview:has(img)` `.hero-img-preview--bg-transparent` `.hero-img-preview--bg-surface` `.hero-img-preview--bleed-light` `.hero-img-preview-imgwrap` `.hero-img-preview-imgwrap img` `.hero-img-preview-empty` `.hero-img-preview-text` `.hero-img-preview-eyebrow` `.hero-img-preview-title` `.hero-img-preview-caption` `.hero-img-preview--plain` `.hero-img-preview--plain .hero-img-preview-imgwrap` `.hero-img-preview--plain .hero-img-preview-text` `.hero-img-preview--within` `.hero-img-preview--within .hero-img-preview-text` `.hero-img-preview--within .hero-img-preview-imgwrap` `.hero-img-preview--within .hero-img-preview-imgwrap:has(.hero-img-preview-empty)` `.hero-img-preview--bleed-light` `.hero-img-preview--bleed-light .hero-img-preview-imgwrap` `.hero-img-preview--bleed-light .hero-img-preview-text` `.hero-img-preview--bleed-light::after` `.hero-img-preview--bleed-dark::after` `.hero-img-preview--bleed-light::after` `.hero-img-preview--bleed-dark .hero-img-preview-title` `.hero-img-preview--bleed-dark .hero-img-preview-caption` `.hero-img-preview--bleed-dark .hero-img-preview-eyebrow` `.hero-img-preview--bleed-light .hero-img-preview-title` `.hero-img-preview--bleed-light .hero-img-preview-caption` `.cms-grip:active` `.cms-divider-v` 

## 7. Drift reconciliation (consolidated)

Every place the live surface diverges from the canonical DS reference (`_design-system/css/*`), with a proposed canonical value. Drift class: **token** (covered in §5.3), **selector-value**, or **naming**.

| # | Drift | Where | Detail | Proposed canonical |
|---|---|---|---|---|
| D1 | token | `--surface` | CMS `#FFFFFF` vs public `#F8F8F8` | Shared off-white + CMS scoped override (§5.3) |
| D2 | token | `--r-pill` | CMS 3px vs 4px | `4px` |
| D3 | token | `--r-tag` missing from tokens.css | public files define 3px | promote `--r-tag: 3px` |
| D4 | token (bug) | `--radius-md` / `--radius-sm` | referenced by tiptap.css, undefined → corners render at 0 | define in tokens.css |
| D5 | selector-value | `.cms-page-title` | raw `26px`, not a token | tokenise → `--text-h3` (28px) or new `--text-page-title`; unify with `.view-title`/`.pipeline-title` as `.page-title` |
| D6 | selector-value | `.sidebar` width | live admin `210px` vs DS showcase `168px` | live admin is canonical; update showcase in 22.6 |
| D7 | selector-value | `.cms-table` / `.cms-item` / `.topbar` | live admin differs from DS `tables.css`/`shell.css` showcase | **live admin (`style-cms.css`) is source of truth**; DS showcase lags — rebuild showcase from live in 22.6 |
| D8 | selector-value | `.event-format-tag.is-free` / `.is-in-person` | hardcoded `#246636` (green) + `#6B4010` (clay) — **different colours** from `--c-forest` (#49634b) / `--c-clay` (#765e44) | **decide canonical green/clay** in 22.4 — not a clean token swap; the hex are visibly distinct. The only two raw hexes in style-articles.css. |
| D9 | selector-value | `.layout-nav` / `.layout-footer` (style-pages) | transparent bg + `backdrop-filter: blur(12px)` (frosted) vs DS solid `--neutral` + hard rule | **intentional Pages variant** — keep; document as sanctioned divergence in system-public [Pages]. (Note: style-articles' chrome matches the frosted version too — reconcile the two public copies into one.) |
| D10 | naming | `.section-heading` collision | DS typography.css = h0 @48px/500; style-pages = h2 @32px/600 — same class, different role | rename Pages version `.section-heading--pages` (or distinct class); they are different components |
| D11 | naming/dup | `.article-series-pill`, `.article-series-dot(s)`, `.article-tag` | structurally identical to DS `.card .series-pill` / `.sdot` / `.tag` | consolidate onto the canonical `.card`/`.tag` rules in the Blocks region |
| D12 | naming | `.index-section-pills .fp` | overrides canonical `.fp` hover to neutral | make a `.filter-pill--neutral` modifier, not a contextual override |
| D13 | dup | `.layout-nav*` / `.layout-footer*` | defined in DS components.css **and** style-pages **and** style-articles (3 copies, minor weight/spacing drift in `.layout-nav-links a`) | one canonical copy in system-public; delete the other two |
| D14 | token-ref | `var(--text-meta)` in style-articles footer | used where the local `:root` doesn't declare it (works via cascade today) | resolved automatically once tokens.css is the shared source |

---

## 8. Variants to collapse

The goal is fewer variants, not just renames. Each family below lists which variants **survive**, which **collapse**, and the rationale.

### 8.1 Buttons — deep-dive (Phase 20.3 callout)

**Current state — 14+ button selectors, two font systems colliding in the CMS:**

| Selector | File | Font | Transform | Size | Padding | Fires on |
|---|---|---|---|---|---|---|
| `.btn-pri` | style-cms | `--font-cond` | UPPER | 12px | 7px 16px | primary CTA (Save, Publish) |
| `.btn-sec` | style-cms | `--font-cond` | UPPER | 12px | 7px 16px | secondary / cancel / edit / row |
| `.btn-ghost` | style-cms | (alias of `.btn-sec`) | — | — | — | legacy alias |
| `.btn-danger` | style-cms | `--font-cond` | UPPER | 12px | 7px 14px | destructive |
| `.btn-icon` / `.btn-icon-danger` | style-cms | icon-only | n/a | 13px svg | 0 | icon actions |
| `.btn-tiny` | style-cms | `--font-cond` | UPPER | 12px | reduced | compact rows |
| `.btn-add-dashed` | style-cms | `--font-cond` | UPPER | 9px | 8px | add-row |
| `.qc-btn` | style-cms | `--font-cond` | UPPER | 9px | 8px 16px | quick-capture |
| `.cat-del` / `.btn-row-del` | style-cms | icon-only | — | svg | 0/4px | row delete |
| `.tt-btn` | tiptap | **sans** | **none/lower** | 10px | 6px 10px | editor toolbar |
| `.tt-fig-btn` | tiptap | `--font-cond` | UPPER | 9px | 4px 12px | figure size |
| `.btn-dark` / `.btn-light` / `.btn-ul` | DS + style-pages | **Barlow** | normal | 14px | 11px 22px / 10px 24px | public CTAs |
| `.cms-btn-pri` / `.cms-btn-sec` | DS tables.css | `--font-cond` | UPPER | 11px | 7px 14px | showcase copies of `.btn-pri/sec` |
| `.row-btn` / `.npbar a` | DS components.css | `--font-cond` / Barlow | mixed | tiny/13px | varies | showcase scaffolding |

**Trim plan — collapse 14 → 2 systems (8 survivors total):**
- **Public** `system-public.css`: `.btn` + `.btn--dark`, `.btn--light`, `.btn--underline` (Barlow). Survivors: 3 modifiers. Collapse: `.cta-primary`/`.cta-ghost`/`.row-btn`/`.npbar a` (showcase) → not migrated.
- **CMS** `system-cms.css`: `.btn` + `.btn--primary`, `.btn--secondary`, `.btn--danger`, `.btn--icon` (+ `--icon-danger`), size `.btn--sm`, plus the two editor exceptions `.btn--editor` and `.btn--figure-toggle`. Survivors: 5 modifiers + 2 editor. Collapse: `.btn-ghost` (drop alias → `--secondary`), `.btn-tiny`/`.btn-add-dashed`/`.qc-btn` → `.btn--sm`, `.cat-del`/`.btn-row-del` → `.btn--icon-danger`, `.cms-btn-*` (showcase) → not migrated.
- **Font rule (the fix):** every CMS action button = condensed UPPERCASE. The *only* exception is `.btn--editor` (typing surface). Make padding token-driven (`--space-*`) so sizes stop drifting.
- **Missing utility surfaced:** `page-edit.php` hand-aligns inline `.btn-sec`/`.btn-pri` for equal height → add a `.btn-row--aligned` (or normalise button height) so the hack disappears.
- **No CSS state classes** for loading/disabled today (JS-managed). Note for system-cms: add `.btn[disabled]` / `.is-loading` styles when buttons move.

### 8.2 Titles
Survive: public display tiers (`.statement`, `.page-header__title`, `.article__title`, `.index-page__title`, `.editorial-hero__title`) — distinct roles, keep. CMS: collapse `.cms-page-title` + `.view-title` + `.pipeline-title` → **`.page-title`** at one canonical size (tokenise the raw 26px). Keep `.section-title`, `.section-label`.

### 8.3 Pills / tags / status
Survive: `.pill` (+ `--info/--warn/--override/--past/--new`), `.cat-pill`, `.tag`, `.filter-pill` (+ `--neutral`), `.status--published/--draft/--scheduled`, `.content-type-pill`, `.cat-chip`, `.pill--count`. Collapse: `.article-tag` + `.article-special-tag` → `.tag` (+ `--special`); `.st-pub/-dft/-sch` → `.status--*`; the per-view inline pills (`.sub-status.sub/.uns`, `.pill-broken`, `.block-mode-pill.mode-*`) → `.status--*` / `.pill--*` modifiers extracted from inline blocks.

### 8.4 Cards
Survive (public, Blocks region, canonical = DS `views.css`): `.card` + `--article/--journal/--event/--experiment/--masterclass` with `__title/__excerpt/__meta/__tag` elements. Survive (CMS): `.pub-card*` → `.card--published` family; `.kcard`+`.idea-card` → `.kanban-card`. Keep distinct: **`.event-card`** (a block *inside* article prose — 2-col detail grid, not a grid card; reconcile naming only, not structure). Collapse onto canonical: `.article-series-pill/-dot(s)` → `.card .series-pill/.sdot` (D11). Reconcile-but-keep: `.editorial-hero-card`, `.article-series-nav-link`, `.article-author-bio` have no canonical `.card` counterpart — they stay as their own block components.

---
## 9. Component candidates (repeated-HTML scan)

Repeated HTML patterns (3+ near-identical uses) across `cms/views/*`, `cms/partials/*`, `_pages/_bodies/*`, and `_templates/*`. Each is a DS component proposal with its target slice. Counts are approximate (grep-based).


### Summary
**Total components found: 20 pattern clusters**
**Components with 3+ occurrences: 18**

---

### Component Inventory (ordered by frequency)

| Pattern | ~Count | Locations (files) | Current Status | Suggested DS Component Name | Target Slice |
|---|---|---|---|---|---|
| Content Block | 135 | cms/views/* (25+ files) | CSS class `.content-block` with `.content-block-header`, `.content-block-label`, `.content-block-sublabel`, `.content-block-count` sub-classes | Content Block Module | CMS |
| Secondary Button | 113 | cms/views/* + cms/partials/* | CSS class `.btn-sec` (+ modifiers: `.btn-tiny`, `.btn-actions-end`) | Button: Secondary | CMS |
| Field Group | 101 | cms/views/* + cms/partials/* | CSS class `.field-group` wrapper with `.field-label`, `.field-input`, `.field-hint` children | Form Field Group | CMS |
| Field Label | 97 | cms/views/* + cms/partials/* | CSS class `.field-label` (sometimes `.field-sublabel`, `.field-req` for required) | Form Label | CMS |
| Primary Button | 26 | cms/views/* + cms/partials/* | CSS class `.btn-pri` | Button: Primary | CMS |
| Row Actions | 18 | cms/views/* (articles, experiments, live-sessions list tables) | CSS classes `.row-actions`, `.row-actions-hover` with action buttons; table row context | Table Row Actions Dropdown | CMS |
| Form Actions Bar | 17 | cms/views/* (edit views + 1 page context) | CSS class `.form-actions` (sticky variant: `.form-actions-sticky`) wrapping button groups | Sticky Action Bar | CMS + Pages |
| Page Header | 13 | site/_pages/* (all marketing pages) | Markup pattern: `.page-header` + `.eyebrow` + `.page-header-title` + `.page-header-sub` structure | Page Header Module | Pages |
| Profile Circle Avatar | 12 | site/_pages/* (about, coaching, work-with-me, landing) | CSS class `.profile-circle` (`.is-sm`, `.is-lg` variants) with `.profile-circle-img` + `.profile-circle-initials` fallback | Profile Avatar | Pages |
| Value Pill | 11 | cms/views/* (post-template.php, article-edit.php inline metadata) | CSS class `.val-pill` for inline monospace value badges | Value Badge / Mono Pill | CMS |
| Eyebrow Label | 11 | site/_pages/* (10) + cms/views/article-edit.php (1) | CSS class `.eyebrow` standalone or within page headers; all-caps label | Section Eyebrow / Label | Pages + CMS |
| Info Box | 9 | cms/views/* (article-edit, live-session-edit, post-template, experiment-edit) | CSS class `.info-box` with `.info-box strong` highlighting; context hints (stage info, field notes) | Info/Notice Box | CMS |
| Section Heading | 7 | site/_pages/* | `<h2 class="section-heading">` text-only headings | Section Heading | Pages |
| Page Section | 7 | site/_pages/* | `<section class="page-section">` wrapper for major content sections | Page Section Container | Pages |
| Flash Message | 6 | cms/partials/flash.php + cms/views/* (login, account, editors) | Alert banner component (success, error, info states) | Flash Alert | CMS |
| Detail Row Value | 5 | site/_pages/_bodies/about.html (5) | Markup pair: `.detail-row-label` + `.detail-row-value` for key-value metadata lists | Detail List Row | Pages |
| Detail Row Label | 5 | site/_pages/_bodies/about.html (5) | Left-aligned label in detail-list context | Detail List Label | Pages |
| Button Row | 4 | site/_pages/_bodies/* (about, coaching, landing) | Horizontal flex wrapper for button groups (`.btn-dark`, `.btn-light` pairs) | Button Group / Button Row | Pages |
| Publish Box | 2 | cms/partials/publish-box.php (rendered 2 variants) | `.cms-publish-box` wrapper with live indicator, date inputs, schedule toggle; complex state management | Publish Control Panel | CMS |
| Button Stack | 1 | site/_pages/_bodies/resume.html | Vertical button group (`.btn-stack` wrapper) | Button Stack (deferred) | Pages |

---

### Key Observations

1. **CMS cluster dominance**: 70% of candidates are CMS-exclusive, concentrated in `cms/views/*` and `cms/partials/*`. These are well-defined CSS classes ready for extraction.

2. **Pages/marketing site patterns**: 12 distinct components concentrated in `site/_pages/_bodies/*.html`. Markup is hand-coded but structure is highly repetitive (headers, detail lists, button groups).

3. **Field system**: The `field-group` + `field-label` + `field-input` + `field-hint` cluster (301 total occurrences) is a fully-formed subsystem; promote to documented form component spec.

4. **Button variants**: Scattered `.btn-pri`, `.btn-sec`, `.btn-dark`, `.btn-light`, `.btn-icon`, `.btn-tiny`, `.btn-actions-end` modifiers across both contexts. Consolidate into a unified button token system.

5. **Empty state candidates**: No `empty-state`, `no-items`, or placeholder patterns detected; these may need design work.

6. **Row-level actions**: `.row-actions` in table contexts (18 occurrences) could abstract to a reusable table-action component spec.

7. **Status/stage system**: Not systematically inventoried here, but likely candidates in pipeline and publish flows (`.cms-live-indicator`, `.cms-publish-box`).

---

### Next Steps

1. **Quick wins (auto-extracted)**: Field system, button variants, form-actions bar — already CSS; document and deprecate hand-rolled patterns.
2. **Medium lift (refactor + doc)**: Page header, page section, detail list rows — promote to documented markup patterns.
3. **Deferred**: Button stack, profile-circle fallback styling, stage/status badge system — revisit once design-system expansion brief is ready.

## 10. Mobile findings (feeds Phase 23.2)

Scope: public surface only (Pages + Blocks). CMS is tablet-only (Phase 23.2 decision) and excluded. This is an **analytical** audit from the CSS evidence — the breakpoint coverage, fixed widths, and collapse rules. **Phase 23.1 should confirm each item live at 390 / 480 / 768px in DevTools** before 23.2 implements; items below are the punch list to verify.

### 10.1 Pages slice (`style-pages.css`) — the mobile-debt hotspot

**Root problem:** a single `@media (max-width: 720px)` cutover and nothing below it. The 500–720px band is partially handled; **below ~480px there is no designed layout** and **no 375px (phone) treatment at all**.

| # | Viewport | Selector | What breaks | Proposed fix (23.2) |
|---|---|---|---|---|
| M1 | <480px | (whole file) | No breakpoint below 720px; phone layout is undesigned | Add a `480px` (and optionally `375px`) breakpoint tier to the Pages region |
| M2 | <400px | `.layout-nav-logo` | `min-width: 100px` + wrapping links can force horizontal pressure | drop min-width at small vw or allow shrink |
| M3 | <450px | `.ledger` | `grid: 160px 1fr` → `96px 1fr` at 720, still tight for long dates on phones | stack date above entry below 480 |
| M4 | 480–720px | `.point-grid` (3-up), `.situation-grid` (3-up), `.testimonial-grid` (2-up), `.faq-list` (2-col) | collapse only fires at 720; cramped in the 500–700 band on large phones/small tablets | add 600px intermediate collapse |
| M5 | <720px | `.journey` (5-col), `.loop` (4-col) | collapse + arrow-rotate at 720 only; below that the rotated arrows + stacked nodes need spot-check | verify stacked layout at 390 |
| M6 | <720px | `.statement` 54→34px, `.page-header-title` 48→32px | single step at 720; no phone-specific size | add 480 type step |
| M7 | all | `.hero .lead` (`max-width:560px`), `.section-outro p` (480px), char-based `max-width`s (`42ch`, `22ch`, `56ch`) | **safe** — relative/char units scale fine | none |

### 10.2 Blocks slice (`style-articles.css`) — comparatively healthy

Coverage: `768 / 480 / 900 / 600` breakpoints. Figures use `min(1080px, calc(100vw - 64px))` and `clamp(34px, 3.8vw, 54px)` — **no fixed-px overflow risks found.**

| # | Viewport | Selector | Status / verify |
|---|---|---|---|
| M8 | 768 / 480 | `.article`, `.article-title.is-serif` (56→40→32), `.article-summary`, `.article-byline-row`, `.article-prose h2/h3/blockquote` | Covered — verify line-length + heading rhythm at 390 |
| M9 | 480 | `.event-card` (grid→1fr), `.event-date/.event-location` | Covered |
| M10 | 900 / 600 | `.index-page-header`, `.index-grid`, `.index-hero/.index-featured`, `.index-section` | Covered |
| M11 | touch | `.cards-grid.is-carousel` (scroll-snap) | **Verify on a real touch device** — snap + momentum behaviour, not provable from CSS |
| M12 | <480 | `.editorial-hero` variants (bleed-dark/light gradients, side card) | Heaviest block; verify the bleed gradients + side-card stacking at 390 |

**Headline mobile takeaway for 23.2:** the article/index family is close; the **marketing Pages family needs a real breakpoint tier built** (480 + an intermediate 600). Because 23.2 runs *after* the DS reorg, those `@media` rules get added per-slice inside `system-public.css` [Pages] rather than retrofitted into a monolith — which is exactly why the reorg precedes mobile.

---

## 11. Ready for Phase 22.2

### 11.1 Verification checklist (Phase 22.1 exit criteria)
- [x] **Every CSS file in scope scanned** — 8 DS modules + style-pages + style-articles + style-cms + tiptap + 8 inline `<style>` blocks (`site/ux2.0/` standalone CSS explicitly ruled out-of-scope, §2).
- [x] **Each selector categorised** — per-file tables in §6 + the complete `style-cms.css` selector ledger (§6.5) cover every selector; bucketing rule is uniform where not individually rowed.
- [x] **No selector uncategorised** — every row carries a bucket; zero `Dead` selectors found (only duplicate token blocks + showcase scaffolding are "delete" targets).
- [x] **Proposed directory tree matches the v2.1 plan** — §3.1 (`tokens.css` + `system-public.css` + `system-cms.css` + thin import shells).
- [x] **Naming-convention decisions documented** — §3.3 convention + §4 naming key (buttons/titles/pills/cards), ready to apply in 22.2+.
- [x] **Drift reconciled** — §7 (14 items) with proposed canonical values; matches-DS / drifts / no-DS-equivalent distinction carried in §6 tables.
- [x] **Variant trim plan** — §8 (buttons 14→2 systems, titles, pills, cards).
- [x] **Component candidates** — §9 (20 patterns, frequency-ranked).
- [x] **Mobile findings** — §10 (per-viewport punch list, Pages vs Blocks).

### 11.2 Decisions — LOCKED (Alex, 2026-06-04)
1. **`--surface`** (D1): **shared off-white `#F8F8F8` + a CMS-scoped `#FFFFFF` override.** No visual change either surface. → 22.2 keeps the public token, 22.5 adds the CMS override.
2. **`--radius-md` / `--radius-sm`** (D4): **`6px` / `3px`.** Define in tokens.css in 22.2 — fixes the dangling-token bug; editor wrap gains a soft rounded corner.
3. **`.event-format-tag` colours** (D8): **reuse `--c-forest` / `--c-clay`** (drop the raw `#246636` / `#6B4010`). ⚠️ **This is a visible colour shift** on the live-session event cards — Phase 22.4 must screenshot-diff the `is-free` / `is-in-person` format tags specifically and confirm the muted category greens/browns read acceptably.
4. **`--r-pill`** (D2): **unify to `4px`** (drop the CMS 3px). CMS pills become 1px rounder — negligible.

### 11.3 Per-phase handoff — what each later phase consumes from this doc
- **Phase 22.2 (Root tokens):** §5 (token target spec) + §1.5/§7 (divergences) + §11.2 (decisions). Also: update the §26/§27 BUILD-PLAN briefs to reference `_design-system/css/*`, not the deleted `system.css` (§2).
- **Phase 22.3 (Pages migration):** §6 style-pages table (region: Pages) + §8.1 public buttons + D9/D10/D13 (frosted-chrome variant, section-heading collision, dedupe the 3 layout-nav copies).
- **Phase 22.4 (Blocks migration, highest-risk):** §6 style-articles table (region: Blocks) + §8.4 cards + D8 (event-format greens) + D11 (consolidate series-pill/dot/tag onto canonical `.card`). Recipe doc lands here per the 22.4 brief.
- **Phase 22.5 (CMS migration):** §6 style-cms table + §6.5 ledger + §8.1 CMS buttons + tiptap (define `--radius-*`) + extract the 8 inline `<style>` blocks + §9 CMS components (rowform, info-box, field-group, publish-box).
- **Phase 22.6 (Cleanup + sunset):** delete the 3 duplicate `:root` blocks + inline styles + the old per-surface files; rebuild the `/cms/design-system` showcase from the live admin (D6/D7 — live is canonical, showcase lags); drop showcase-only scaffolding. **Before deleting `style-pages.css`:** resolve `--dot-grid` / `--dot-grid-svg` (page-local texture tokens not in tokens.css — see §6.2). Also do the deferred semantic class renames here (they need coordinated CSS+HTML changes the additive migrations couldn't make).
- **Phase 23.2 (Mobile):** §10 punch list (Pages needs a 480+600 tier; Blocks mostly verify-only).

---
