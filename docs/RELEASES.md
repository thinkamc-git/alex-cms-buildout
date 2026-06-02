# Releases

A short, human-readable changelog of public-facing ships. For phase-by-phase build history see `BUILD-PLAN.md`.

---

## v1.0 — alexmchong.ca CMS (2026-06)

The first public release. Replaces a hand-built static site with a custom PHP + MySQL CMS, hosted on DreamHost. Single-author, desktop-only admin.

### What shipped

**Content types.** Four content types each render through their own template:
- **Articles** — long-form essays, with optional series grouping and category colour.
- **Journals** — short, dated entries.
- **Live Sessions** — talks / workshops with event-card metadata (when, where, cost, format).
- **Experiments** — sketches and prototypes rendered from raw HTML.

**Editor (CMS).** Pipeline view groups every in-flight piece by stage (Concept · Outline · Draft · Scheduled · Published). Per-type list views slice the same data with a 3-section split (Drafts · Scheduled · Published). Ideas live in a separate Ideation board. Every edit view shares a common shell: title + summary + body editor on the left, publish controls + categories + series + hero image in the right aside, action row at the bottom.

**Publish flow.** Save · Publish · Schedule Publish · Cancel · Delete. Scheduled posts run a cron at the chosen time; before then a banner with a live countdown appears above the title and the primary action becomes Publish Now. Published posts expose an editable published-at date and an optional Updated date (off by default; date-only override).

**Public site.** CMS-rendered routes:
- `/writing/`, `/journal/`, `/live-sessions/`, `/experiments/` (index views, configurable per index)
- `/writing/[slug]`, `/journal/[slug]`, `/live-sessions/[slug]`, `/experiments/[slug]`
- `/series/[slug]/` (series index with auto-watermarked card grid)

Each content type renders a Content Template — a fixed set of blocks (title, byline, hero image, body, key statement, tags, author bio, etc.) drawn from a single canonical contract in `docs/BLOCKS.md`. Hand-built marketing pages (`/about/`, `/coaching/`, `/work-with-me/`, `/resume/`, `/newsletter/`) live alongside as `_pages/` outside the CMS.

**Newsletter.** Public subscribe form at `/subscribe/` with double opt-in, honeypot + time-trap, MX-record check, and confirmation page. Subscribers managed from the CMS Subscribers view.

**Categories.** 18-hue palette baked into the design system. Categories pick a colour by token name; the chosen colour propagates through tag pills, journal key-statement borders, and series watermarks.

**Redirects.** Editable redirect table (legacy URL → live URL) honoured by Apache's mod_rewrite chain.

**Auth + security.** Session-based login, CSRF tokens on every state-changing form, password hashing via PHP `password_hash()`, brute-force lockout, full `docs/AUTH-SECURITY.md` write-up.

**Deploy.** `bin/deploy.sh staging|prod` rsyncs an explicit-cp staging dir to DreamHost. Excludes protect server-owned content (`/content/`, `_archive/`, `uploads/`, etc.).

**Accessibility (Phase 15).** Global `:focus-visible` ring + `--c-focus` token. Skip-to-content link on every page (CMS + public). Sidebar nav with `aria-label` + `aria-current`. SVG icons marked `aria-hidden`. CMS topbar wrapped in `<header role="banner">`. Every form input labelled.

### Known v1.0 limits (to address in v2.x)

- No image alt-text enforcement at save (defaults are reasonable: hero uses caption, author uses name).
- Sidebar IA is functional but unsorted; v2.0 reorganises it (Phases 19–21).
- Content Template view is read-only in v1.0 (mock-edit + versioning planned in v2.0).
- No public Settings page / nav editor / pages manager (v2.0 scope).
- No tags taxonomy management UI (categories cover the main grouping need; tags remain free-text).

### Migration history

Schema migrations applied 0001 → 0013. See `site/db/migrations/` for the trail; each migration is idempotent and runs in order via the deploy step.

---
