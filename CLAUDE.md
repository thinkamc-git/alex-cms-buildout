# alexmchong.ca CMS — Project Context

This file is the orientation guide for any assistant working in this repo. Read it before making changes.

---

## What this project is

A custom CMS for **alexmchong.ca**, a single-author personal site (designer / writer / coach). The CMS lets the author capture, develop, and publish four content types — Articles, Journals, Live Sessions, and Experiments — through a Pipeline (Idea → Concept → Outline → Draft → Published).

**Stack:** PHP + MySQL on DreamHost shared hosting. Routing via Apache `mod_rewrite`. The CMS is desktop-only.

---

## Where we are right now

**The CMS is built and live in production. The project is in maintenance.** Every planned phase shipped — the full content system (four content types end-to-end, categories / series / indexes, redirects, subscribers, scheduling), the v2.1 design-system reorg, the mobile pass, and the public cutover (2026-06-10). See **`docs/BUILD-PLAN.md`** for the lean build record + the forward backlog; the full historical plan and audits are archived in **`docs/_completed/`**.

The single canonical spec is **`docs/CMS-STRUCTURE.md`**. Read it for any system-level question (schema, view inventory, content types, render rules).

### How we work now (the maintenance loop)

Most new work follows the same loop — and this is the one to follow when picking work back up:

1. **Explore in a sandbox** under `docs/design-mockups/` — a throwaway HTML mock or spike. Play until the direction is right.
2. **Promote** the validated work into the real system (tokens, components, views) per **`docs/BUILD-DISCIPLINE.md`** (default to reuse; new patterns need sign-off; preview ≠ done).
3. **Archive the sandbox** into `docs/design-mockups/_completed/` so the working area stays clean — promoted sandboxes never linger. Finished audits/specs move to `docs/_completed/` the same way.

## Documentation map (read-on-demand, not all at once)

Keep context cost low: read this file, then only the canonical doc relevant to the task. The working set in `docs/` (everything build-era is archived in `docs/_completed/` and not needed for maintenance):

- **`docs/CMS-STRUCTURE.md`** — the canonical system spec (schema, view inventory, content types, render rules). The source of truth; check it first for any system-level question.
- **`docs/BUILD-PLAN.md`** — lean build record (what shipped) + the forward backlog / future ideas. Start here for "what's next."
- **`docs/ENGINEERING.md`** — code conventions. Read before writing PHP/CSS/JS.
- **`docs/BLOCKS.md`** — block contract (slugs, modes, composition). See the linkage rule below.
- **`docs/BUILD-DISCIPLINE.md`** — how to build leanly + the sandbox → promote → archive workflow. Read before any non-trivial change.
- **`docs/DESIGN.md`** · **`docs/DS-IA.md`** · **`docs/MOTION.md`** — design-system brief, information architecture, motion reference.
- **`docs/AUTH-SECURITY.md`** — auth + security model.
- **`docs/DEPLOYMENT.md`** — deploy + cron + backup ops. Read before any deploy.
- **`docs/APPLIED-SPECS.md`** — how to build "Applied" concept pages (the sandbox-driven process).
- **`docs/RELEASES.md`** — release log.

Build-era history (the full 24-phase plan, the DS + mobile audits, phase notes, onboarding) lives in **`docs/_completed/`** — history only, not part of the working set.

---

## Folder map

```
alex-cms-buildout/
├── CLAUDE.md                       ← you are here (orientation)
├── README.md                       ← repo entry point for humans
├── .gitignore
│
├── docs/                           ← reference material · NEVER deployed
│   ├── BUILD-PLAN.md               ← lean build record + forward backlog
│   ├── CMS-STRUCTURE.md            ← canonical system spec (source of truth)
│   ├── ENGINEERING.md              ← code conventions
│   ├── BLOCKS.md                   ← block contract (slugs, modes, composition)
│   ├── BUILD-DISCIPLINE.md         ← lean-build rule + sandbox→promote→archive loop
│   ├── DESIGN.md · DS-IA.md · MOTION.md      ← design system brief / IA / motion
│   ├── AUTH-SECURITY.md · DEPLOYMENT.md · APPLIED-SPECS.md · RELEASES.md
│   ├── _completed/                 ← build-era archive (full plan, audits, onboarding) · history only
│   └── design-mockups/             ← sandbox area for new work (intake → promote)
│       └── _completed/             ← archived sandboxes · do NOT reference as source of truth
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

**`docs/BLOCKS.md`, `site/_templates/`, and the CMS editor views under `site/cms/` are tightly linked.** A change in one almost always requires matching changes in the other two.

- **`docs/BLOCKS.md`** is the **contract** — it defines every block, its slug, its toggleability mode, its composition (which DB fields it reads), and its rendering rules.
- **`site/_templates/article.html` and `site/_templates/layouts.html`** are the **rendering** — they're the actual HTML that the public site displays. The CMS feeds data into these.
- **`site/cms/views/*-edit.php` (+ `site/cms/partials/`, `site/cms/_assets/style-cms.css`)** are the **editor** — the live CMS admin forms where the author creates and edits the content that flows into the templates. **This is the source of truth for the admin UI.**

If you change a block (add one, rename a slug, change visibility behaviour, alter composition), you must:

1. Update `docs/BLOCKS.md` (the contract).
2. Update `site/_templates/article.html` and/or `site/_templates/layouts.html` (the rendering).
3. Update the relevant CMS editor view(s) under `site/cms/views/` (the editor).
4. Update `docs/CMS-STRUCTURE.md` if the change affects the schema, render rules, or any documented decision.

If a change touches only one of the three and not the others, it's almost certainly incomplete.

> **Retired — do not use as a reference.** `docs/design-mockups/cms-ui.html` was the *pre-build* mockup that originally played the "editor" role above. The real CMS (`site/cms/`) has since diverged from it, and the mockup is **not** kept in sync — treating it as a source of truth produced styling that didn't match the real admin. It's archived, unmaintained, at `docs/design-mockups/_completed/cms-ui.html` (pristine original, for history only). Build the admin UI against `site/cms/`, never the mockup.

---

## 🔒 Build & Maintenance Discipline — applies to EVERYTHING

This governs **everything built or written in this repo** — code, styles, content, docs, CMS, future tools — not just the design system. The goal: the software stays **organized and lean**, built **systematically against the existing system**, never vibed into sloppiness that forces repeated refactors. It is honoured and managed **explicitly** on every change.

- **Default to reuse.** Build every new thing by referencing and fitting what already exists (tokens, components, modules, conventions, helpers). No new raw values, no duplicate patterns, no one-offs when the system already expresses it. Lean = no dead code, no duplication, smallest change that fits.
- **New things need explicit sign-off.** Introducing a new pattern / component / style, a raw value, or breaking a convention requires a brief **assessment** (what, why the system can't do it, where it belongs) **and Alex's explicit permission** before it counts as done. **No silent deviations** — surface and ask.
- **Prototyping ≠ done.** You may prototype quickly (inline styles, a stub, a throwaway preview) to validate — but it's provisional and quarantined. To finish, promote it into the system properly (named, tokenized, placed, documented/shown) and remove the prototype.
- **Done means:** fits the system; lean and organized; reusable additions documented where their kind lives (styles → the `/_ds/` showcase, rendered from the real CSS); linked surfaces updated; any exception assessed and approved.

**Full rule + the design-system specifics: `docs/BUILD-DISCIPLINE.md`.** The three-way linkage rule below is one specific case of this.

---

## What `site/_pages/` is (and isn't)

`site/_pages/` holds **standalone marketing pages** for alexmchong.ca — `about.html`, `coaching.html`, `landing.html`, `resume.html`, `work-with-me.html`, `newsletter.html`, `newsletter-confirmed.html`, plus shared assets in `site/_pages/_layout/` (`style-pages.css` + brand images). These are hand-built pages that exist outside the CMS content flow.

**`site/_pages/` is not part of the CMS.** Don't confuse it with `site/_templates/`. The CMS does not read from or write to `site/_pages/`.

**`landing-postcms.html`** is a parallel landing file that stages the future topbar nav (with CMS-served sections like *Thoughts*, *Talks*, *Experiments*) using `href="link"` placeholders. It exists alongside `landing.html` until the Phase 9 cutover, at which point the placeholder hrefs are replaced with real CMS URLs and the file collapses into `landing.html`. Don't deploy `landing-postcms.html` to production directly — it's a design canvas, not a live page.

---

## Quick reference for common edits

| Want to… | Touch… |
|---|---|
| Tweak the CMS admin UI (a button, a form, a view) | the relevant view in `site/cms/views/` (+ `site/cms/_assets/style-cms.css`) |
| Tweak how an article renders on the public site | `site/_templates/article.html` and `site/_templates/style-articles.css` |
| Change what a block is or how it behaves | `docs/BLOCKS.md` + `site/_templates/*.html` + the relevant `site/cms/views/*-edit.php` (all three — see rule above) |
| Add a new content type | `docs/CMS-STRUCTURE.md`, new template files in `site/_templates/`, new views in `site/cms/views/`, schema change |
| Update the design system tokens | `site/_design-system/css/tokens.css` |
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
