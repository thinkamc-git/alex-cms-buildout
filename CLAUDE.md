# alexmchong.ca CMS — Project Context

This file is the orientation guide for any assistant working in this repo. Read it before making changes.

---

## What this project is

A custom CMS for **alexmchong.ca**, a single-author personal site (designer / writer / coach). The CMS lets the author capture, develop, and publish four content types — Articles, Journals, Live Sessions, and Experiments — through a Pipeline (Idea → Concept → Outline → Draft → Published).

**Stack:** PHP + MySQL on DreamHost shared hosting. Routing via Apache `mod_rewrite`. The CMS is desktop-only.

---

## Where we are right now

The schema is locked, the UI mockup is feature-complete, and the execution plan is drafted. We're at the start of Phase 0 of the build.

| Phase | Status |
|---|---|
| 1 — UI polish (mockup) | ✅ Complete |
| 2 — System decisions / edge cases | ✅ Complete |
| 3 — Documentation (spec + conventions) | ✅ Complete |
| 4 — Execution plan | ✅ Complete (`docs/BUILD-PLAN.md`) |
| 5 — Build (Phase 0 → Phase 15, vertical-slice) | ⏳ Phase 0 starting |

The single canonical spec is **`docs/CMS-STRUCTURE.md`**. Read it for any system-level question (schema, view inventory, content types, render rules).

## Documentation map (read-on-demand, not all at once)

**The reading flow is intentionally narrow to keep context cost low.** A fresh Claude Code session should:

1. **`CLAUDE.md`** (this file) — orientation. Read once.
2. **`docs/BUILD-PLAN.md`** §3 — find the current phase (the first unchecked box).
3. **The current phase's Session brief** (top of that phase's section in `docs/BUILD-PLAN.md`). The brief lists exactly which other files to read at start, which to touch, which to leave alone, and what to update on exit.
4. **Only the files the brief names.** Do not pre-load the full doc chain.

That's the standard entry. Don't read `docs/ENGINEERING.md`, `docs/CMS-STRUCTURE.md`, `docs/BLOCKS.md`, etc. unless the current phase's brief points you at them.

**Companion documents (referenced from briefs, not read by default):**

- **`docs/BUILD-PLAN.md`** — the 17-phase execution plan (15 active + 2 deferred), structured as vertical slices so each content type ships end-to-end. Each phase has its own Session brief with an autonomy tier and a Decisions block that Alex answers BEFORE the session starts. You only ever need the section for the current phase.
- **`docs/ENGINEERING.md`** — code conventions. Referenced from briefs in phases that write PHP/CSS/JS.
- **`docs/CMS-STRUCTURE.md`** — system spec. Referenced from briefs in phases that touch schema, render logic, or content types.
- **`docs/BLOCKS.md`** — block contract. Referenced from briefs in Phase 8a/8b.
- **`docs/AUTH-SECURITY.md`** — drafted at the start of Phase 4. Referenced from Phase 4 + Phase 12 briefs.
- **`docs/DEPLOYMENT.md`** — drafted during Phase 1, extended in Phase 3. Referenced from any phase touching deploy or cron.
- **`docs/LEGACY-ROUTES.md`** — pre-migration URL inventory. Drafted Phase 0, consumed by `.htaccess` in Phase 1, migrated into the CMS in Phase 10a.
- **`docs/onboarding/`** — Phase 0 lessons for Alex. A Claude doing build work doesn't need to read these unless Alex asks for help with one of those tools.

**Why this matters.** Reading 5+ docs at the start of every session burns ~30K tokens before any code is written. With the Session brief pattern, entry cost is ~2K–5K tokens — enough to do the phase right, not so much that the session runs out of room.

**The autonomy unlock.** Every phase brief opens with a **Decisions to capture before starting** block. Alex fills in the answers (or accepts the defaults) BEFORE the Claude Code session starts. Claude then reads the brief — Decisions included — and runs straight through without asking for clarification mid-stream. Auto and Semi-auto phases (roughly 70% of the build) execute unattended; Alex steps back in for the Verification checklist. Manual phases stay interactive throughout. See `docs/BUILD-PLAN.md` §1.2 and §1.3.

---

## Folder map

```
alex-cms-buildout/
├── CLAUDE.md                       ← you are here (orientation)
├── README.md                       ← repo entry point for humans
├── .gitignore
│
├── docs/                           ← reference material · NEVER deployed
│   ├── BUILD-PLAN.md               ← 17-phase execution plan
│   ├── CMS-STRUCTURE.md            ← canonical system spec (source of truth)
│   ├── ENGINEERING.md              ← code conventions
│   ├── LEGACY-ROUTES.md            ← legacy URL inventory + redirect plan
│   ├── BLOCKS.md                   ← block contract (slugs, modes, composition)
│   ├── DESIGN.md                   ← design system brief
│   ├── onboarding/                 ← Phase 0 lessons for Alex
│   └── design-mockups/
│       ├── cms-ui.html             ← canonical CMS UI mockup (admin panel)
│       └── landing-postcms.html    ← future-nav landing canvas (not live)
│
└── site/                           ← deployable source · rsynced to DreamHost
    ├── _pages/                     ← standalone marketing pages (NOT CMS)
    │   ├── about.html, coaching.html, landing.html, resume.html,
    │   ├── work-with-me.html, newsletter.html, newsletter-confirmed.html
    │   └── _layout/                ← shared assets (style-pages.css + images)
    ├── _design-system/             ← brand design system (served at /_ds/)
    │   ├── system.css              ← token source of truth
    │   ├── system.js
    │   └── index.html              ← showcase
    └── _templates/                 ← public-site rendering templates (PHP reads)
        ├── article.html            ← annotated article-template preview
        ├── layouts.html            ← multi-type layout reference
        └── style-articles.css      ← stylesheet for article-family templates
```

The split is intentional: anything under `site/` deploys; anything outside it does not. The deploy script (Phase 1) is literally `rsync -a site/ <target>:<webroot>/`. Add a folder under `site/`, it ships; add a folder under `docs/`, it stays private.

---

## ⚠️ The most important rule

**`docs/design-mockups/cms-ui.html`, `site/_templates/`, and `docs/BLOCKS.md` are tightly linked.** A change in one almost always requires matching changes in the other two.

- **`docs/BLOCKS.md`** is the **contract** — it defines every block, its slug, its toggleability mode, its composition (which DB fields it reads), and its rendering rules.
- **`site/_templates/article.html` and `site/_templates/layouts.html`** are the **rendering** — they're the actual HTML that the public site displays. The CMS feeds data into these.
- **`docs/design-mockups/cms-ui.html`** is the **editor** — the CMS admin panel where the author creates and edits the content that flows into the templates.

If you change a block (add one, rename a slug, change visibility behaviour, alter composition), you must:

1. Update `docs/BLOCKS.md` (the contract).
2. Update `site/_templates/article.html` and/or `site/_templates/layouts.html` (the rendering).
3. Update `docs/design-mockups/cms-ui.html` (the editor — `blocks[]` array, `blockMatrix`, relevant form fields).
4. Update `docs/CMS-STRUCTURE.md` if the change affects the schema, render rules, or any documented decision.

If a change touches only one of the three and not the others, it's almost certainly incomplete.

---

## What `site/_pages/` is (and isn't)

`site/_pages/` holds **standalone marketing pages** for alexmchong.ca — `about.html`, `coaching.html`, `landing.html`, `resume.html`, `work-with-me.html`, `newsletter.html`, `newsletter-confirmed.html`, plus shared assets in `site/_pages/_layout/` (`style-pages.css` + brand images). These are hand-built pages that exist outside the CMS content flow.

**`site/_pages/` is not part of the CMS.** Don't confuse it with `site/_templates/`. The CMS does not read from or write to `site/_pages/`.

**`landing-postcms.html`** is a parallel landing file that stages the future topbar nav (with CMS-served sections like *Thoughts*, *Talks*, *Experiments*) using `href="link"` placeholders. It exists alongside `landing.html` until the Phase 9 cutover, at which point the placeholder hrefs are replaced with real CMS URLs and the file collapses into `landing.html`. Don't deploy `landing-postcms.html` to production directly — it's a design canvas, not a live page.

---

## Quick reference for common edits

| Want to… | Touch… |
|---|---|
| Tweak the CMS admin UI (a button, a form, a view) | `docs/design-mockups/cms-ui.html` only |
| Tweak how an article renders on the public site | `site/_templates/article.html` and `site/_templates/style-articles.css` |
| Change what a block is or how it behaves | `docs/BLOCKS.md` + `site/_templates/*.html` + `docs/design-mockups/cms-ui.html` (all three — see rule above) |
| Add a new content type | `docs/CMS-STRUCTURE.md`, new template files in `site/_templates/`, new views in `docs/design-mockups/cms-ui.html`, schema change |
| Update the design system tokens | `site/_design-system/system.css` (and verify the CMS mockup's inlined token block stays in sync) |
| Add or edit a marketing page | `site/_pages/<page>.html` and `site/_pages/_layout/style-pages.css` |
| Change something about the database schema | `docs/CMS-STRUCTURE.md` §9, then plan the migration |

---

## Style + tone

- **Tokens, not raw values.** The mockup and the templates use design-system tokens for everything. No raw hex outside `:root` blocks.
- **Slug is the contract.** Once a piece of content is published, its slug is permanent. Changing it means a 301 redirect, not a rename.
- **Categories reference design-system colours by token name** (`terracotta`, `forest`, etc.), not by hex.
- **Author renders with placeholders, not silence.** Empty author fields render `{no author name}`, `{no short description}`, `{no extended description}`. Empty image renders an initials circle (or blank if no name).
- **Path A visibility.** The CMS does not store a `block_visibility` JSON. A block renders if its underlying field is non-NULL. Author and Author Bio are the only blocks with explicit per-content boolean toggles.

---

## When in doubt

Read `docs/CMS-STRUCTURE.md`. It captures every decision made up to this point and links out to the supporting files. If `docs/CMS-STRUCTURE.md` doesn't answer the question, ask before assuming.
