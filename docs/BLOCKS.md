# Page Blocks — CMS ↔ Page Rendering Contract

**Status:** Draft v1
**Owner:** Page rendering work in `site/_templates/`
**Audience:** CMS implementation work elsewhere in the project

---

## ⚠️ Merge instructions — read this first

**This file is the source of truth** for the block convention shared by
the page-rendering templates in `site/_templates/` and the CMS implementation
in `docs/design-mockups/`. Every block, slug, field mapping, and toggleability mode
listed here must be respected by the eventual PHP templates and the CMS
toggle UI. Changes to a block here must be paired with corresponding
changes in `site/_templates/article.html` (and `site/_templates/layouts.html`)
plus the CMS mockup `docs/design-mockups/cms-ui.html` — these three files are linked.

**The companion file is `site/_templates/article.html`**, which visually
demonstrates every block in two complementary forms:

- A **Master view** that shows the union of every block across every
  content type. Some blocks are alternatives (e.g., Title vs Key
  Statement, Body vs Custom HTML); they only appear together in
  Master so the CMS worker can see the full block inventory.
- Four **realistic type renderings** (Article, Journal, Live Session,
  Experiment) that show how each content type composes its subset
  of blocks into the final layout.

Read both files together when implementing — `BLOCKS.md` for the spec,
`article.html` for the visual reference. Don't introduce new block
slugs or rename existing ones without updating both.

When the PHP templates are written, each block defined here maps to a
section of the corresponding template file (`templates/standard.php`,
`templates/series.php`, `templates/journal.php`, etc., per the CMS
context export). The CMS UI's block toggles read/write a per-content
`block_visibility` map keyed by the slugs defined here.

---

## 1. Purpose

This document defines the **block** convention used by the page templates in
`site/_pages/`. The CMS toggles content on/off at the block level (not at the
data-field level), and the page-rendering markup follows the slugs and
naming defined here.

If you are working on the CMS, treat this file as the contract for what is
toggleable, what each toggle controls, and how each block maps to the
underlying database fields and PHP variables. Do not introduce new block
slugs or rename existing ones without updating this document first.

This file lives in `site/_pages/` because the page rendering is the source of
truth for what blocks exist. The CMS reads from this contract; the contract
does not read from the CMS.

---

## 2. Concept

A **block** is a toggleable UI module on the rendered page. Each block has:

- A **name** — Title Case, human-readable. What the CMS author sees in
  toggle UI (e.g., `Author`, `Special Tag`, `Event Details`).
- A **slug** — kebab-case, stable identifier. Used in code, in the page's
  HTML `data-block` attribute, and as the toggle key in the CMS database
  (e.g., `author`, `special-tag`, `event-details`).
- An **HTML structure** — the markup pattern, defined in the page templates
  in `site/_pages/`. The CMS does not generate this; it includes or omits the
  block in the rendered page.
- A **CSS class** — follows the slug. `slug` → `.article-{slug}` for blocks
  scoped to the article wrapper, or `.{slug}`/dedicated class for blocks
  with their own scope (e.g., `.event-details`).
- A **composition** — the underlying fields it reads from `$article` to
  populate itself. Multiple fields may live inside one block.
- A **toggleability** mode (see §4).

A **field** is a single value (e.g., `$article['title']`). Fields are the
raw data; blocks are how that data renders. The CMS's toggle UI operates on
blocks, not on fields. A designer may still reference a field by its PHP
variable when working on layout — that is a designer aid, not a CMS toggle.

---

## 3. Naming convention

- **Block names** are Title Case, single or two words. Plural only when the
  block renders a collection (e.g., `Format Tags`).
- **Slugs** are kebab-case, no prefix. Stable forever. Renaming a slug is a
  database migration, not a rebrand — do not do it casually.
- **CSS classes** follow the slug. Default mapping is `.article-{slug}` for
  blocks inside the article wrapper. Some blocks have a dedicated class
  (e.g., `.event-details`, `.experiment-placeholder`); those exceptions are
  noted in the block reference.
- **HTML data attribute** is `data-block="{slug}"` on the block's root
  element. The master preview view reads this to render the field labels.
  A second attribute, `data-block-name="{Block Name}"`, is added in the
  master view only — the CMS production rendering would set neither.

---

## 4. Toggleability modes

| Mode | Meaning |
|---|---|
| `always` | The block renders whenever the content type includes it. The author cannot turn it off. |
| `optional` | The author toggles the block on or off per piece of content. |
| `auto-conditional` | The block auto-shows or hides based on the data — the author has no control. |
| `template-specific` | The block belongs to a specific template / content type and is unavailable elsewhere. Within its template, treat as `always` unless noted. |

---

## 5. Block reference

Every block in the system. Slug column is the stable key. Composition lists
the PHP variable(s) the block reads. Mode is the toggleability behaviour.

| Block | Slug | Mode | Composition | Notes |
|---|---|---|---|---|
| Category | `category` | always | `$article['category']` (primary, joined from `content_categories`) | Coloured pill in the meta line. Drives `--c-current` for category-tinted elements. |
| Publish Date | `publish-date` | always | `$article['published_at']` | Formatted in the meta line. |
| Read Time | `read-time` | optional | `$article['read_time']` | Auto-calculated at 200 wpm; manual in experiment-html (when there is no Body to count). Hidden in journal. |
| Updated Date | `updated-date` | auto-conditional | `$article['updated_at']` | Renders only when `updated_at` differs from `published_at` by more than 24 hours. |
| Title | `title` | always | `$article['title']` | Hidden in journal (replaced by Key Statement). Allows inline HTML (`<em>`, `<span class="m">`). |
| Key Statement | `key-statement` | always (journal) | `$article['key_statement']` | Replaces Title in the journal display. Single declarative sentence rendered in Instrument Serif italic with a left rule in the category colour. |
| Summary | `summary` | optional | `$article['summary']` | Single-line deck below the title. Drawn from the same field used for card excerpts and meta description. Block name and slug now match the underlying field name. |
| Author | `author` | optional | `$author['image']`, `$author['name']`, `$author['short_description']` *(uses three of the four Author config fields; renders as a single byline line: "Name – Short description")* | Single-author site → one config record; injected into every page that includes this block. Configure in the CMS Author tab. The fourth field, `extended_description`, is consumed by the Author Bio block. |
| Author Bio | `author-bio` | optional | `$author['image']`, `$author['extended_description']` | Footer-area block. The "About the author" panel — image plus the long-form bio paragraph. Independently toggleable from the Author byline; either, both, or neither can appear on a page. |
| Series | `series` | optional | `$article['series']` (joined from `series` table), `$article['series_order']` | Renders pill + "Part N of M" + progress dots. Required in the article-series template; optional in article-standard. Articles only. |
| Special Tag | `special-tag` | optional | `$article['special_tag']` (`null` / `principle` / `framework`) | Articles only. Renders as a small pill with the category-tinted border. |
| Hero Image | `hero-image` | optional | `$article['hero_image']`, `$article['hero_caption']`, `$article['hero_size']` (ENUM: 'default'/'wide'/'full') | Sits between the header and the body. Has size variants: default (column-width), wide (~1080px), full (100vw). Articles and live sessions only. |
| Body | `body` | always | `$article['body']` (rich-text HTML emitted by the Tiptap editor) | Rendered inside `.article-prose`. Hidden in the experiment-html template (replaced by Custom HTML). |
| Custom HTML | `custom-html` | always (experiment-html) | `$article['source_file']` | Replaces Body in the experiment-html template. Rendered in production via PHP readfile() — no template wrapper, no nav. The .experiment-placeholder frame on the preview page is scaffolding, not the production render. |
| Series Navigation | `series-nav` | auto-conditional | derived: prev/next where `series_id` matches and `series_order` is ±1 | Renders only when the article is part of a series and the `series` template is active. |
| Event Details | `event-details` | always (live-session) | `$article['event_start']` (DATETIME), `$article['location']` | When/Where panel. Live sessions only. |
| Format Tags | `format-tags` | always (live-session) | `$article['cost_pill']` (string: 'Free'/'Fee'/custom; NULL hides), `$article['attendance']` (ENUM 'in-person'/'remote'; NULL hides), `$article['custom_pill']` (any short string; NULL hides) | Three independent pills. Each NULL hides its own pill. format_type is no longer a separate field — it lives on the live session's category. |
| Entry Number | `entry-number` | auto-conditional | `$article['journal_number']` | Auto-incremented per category on publish. Journals only. Renders in the meta line as "Entry 14". |
| Tags | `tags` | auto-conditional | `$article['tags']` (comma-separated) | Renders only if any tags are present. Display only — not used for filtering. |

---

## 6. Content type matrix

Which blocks each content type can include. Cells use the toggleability
mode for that content type, or `—` if the block is not applicable.

| Block | article-standard | article-series | journal-entry | live-session | experiment | experiment-html |
|---|---|---|---|---|---|---|
| Category | always | always | always | always | always | always |
| Publish Date | always | always | always | always | always | always |
| Read Time | optional | optional | — | optional | optional | optional (manual) |
| Updated Date | auto | auto | auto | auto | auto | auto |
| Title | always | always | — | always | always | always |
| Key Statement | — | — | always | — | — | — |
| Summary | optional | optional | — | optional | optional | optional |
| Author | optional | optional | optional | optional | optional | optional |
| Author Bio | optional | optional | optional | optional | optional | optional |
| Series | optional | required | — | — | — | — |
| Special Tag | optional | optional | — | — | — | — |
| Hero Image | optional | optional | — | optional | optional | — |
| Body | always | always | always | always | always | — |
| Custom HTML | — | — | — | — | — | always |
| Series Navigation | — | auto | — | — | — | — |
| Event Details | — | — | — | always | — | — |
| Format Tags | — | — | — | always | — | — |
| Entry Number | — | — | auto | — | — | — |
| Tags | auto | auto | auto | auto | auto | auto |

`required` means the data field is required to save the content; the block
is always shown when the field is populated.

---

## 7. Block order

Blocks render in a fixed order per content type. Reordering is **not** a v1
feature — the order is set by the page template in `site/_pages/`. The CMS
toggles blocks on or off; it does not move them.

Article block order (top-down):
1. **Topstrip** — Category (left) · Series row (right, when in series)
2. Title
3. Summary
4. Special Tag *(own row)*
5. Date row — Publish Date · Updated Date
6. **Byline row** — Author byline (left) · Read Time (right) *(faint rules above and below)*
7. Hero Image
8. Body
9. Series Navigation *(if series template)*
10. Author Bio *(footer)*
11. Tags

Journal block order:
1. **Topstrip** — Category (left) · Entry Number (right)
2. Key Statement *(replaces title)*
3. Date row — Publish Date
4. Byline row — Author byline only *(no read-time slot)*
5. Body
6. Author Bio *(footer)*
7. Tags

Live Session block order:
1. **Topstrip** — Category
2. Title
3. Summary
4. Date row — Publish Date *(when posted)*
5. **Event Card** — Event Details (When/Where) + Format Tags *(combined white card with the same surface treatment as Author Bio)*
6. Byline row — Author byline (left) · Duration (right)
7. Body
8. Author Bio *(footer)*
9. Tags

Experiment block order:
1. **Topstrip** — Category
2. Title
3. Summary
4. Date row — Publish Date · Read Time *(manual)*
5. Byline row — Author byline (left) · Read Time (right)
6. Custom HTML *(replaces body — in production rendered raw via `readfile()`)*
7. Author Bio *(footer)*
8. Tags

The header structure (steps 1–6 in Article) is the editorial layout
established in `article.html`'s Master view. The split topstrip,
prominent date row, and rule-bordered byline row are the visual
signature of the layout — preserve these patterns when implementing
the PHP templates.

---

## 7a. Author config record

Author data is **not** stored on the `content` row. It is a single config
record (the site is single-author today) injected into every page render.
The Author config has four fields:

| Field | PHP variable | Used by |
|---|---|---|
| Image | `$author['image']` | Author byline, Author Bio |
| Name | `$author['name']` | Author byline |
| Short description | `$author['short_description']` | Author byline (renders as "Name – Short description") |
| Extended description | `$author['extended_description']` | Author Bio (the footer-area paragraph) |

Configure all four fields in the CMS **Author** tab. Whenever multi-author
becomes a real requirement, this section will need to be revisited — each
content row would gain an `author_id` foreign key, and the Author block
would resolve the record per article rather than as a global include.

---

## 8. Notes for CMS implementation

- Each content type has its own list of available blocks (per the matrix in
  §6). The CMS UI should hide blocks that don't apply.
- Blocks in `optional` mode appear in the toggle UI as on/off controls.
- Blocks in `always` mode are listed for reference but not toggleable.
- Blocks in `auto-conditional` mode are not toggleable; show "auto" or
  similar in the UI so the author understands they appear when the data
  warrants.
- Blocks in `template-specific` mode appear only in the template they
  belong to. Within their template, treat as `always`.
- Visibility for optional blocks is determined by data presence: a block
  renders if its underlying field is non-NULL, otherwise it's omitted.
  The CMS does NOT store a per-content block_visibility JSON.
- The Author and Author Bio blocks are the only exceptions to data-presence
  visibility. Each has a per-content boolean column on content
  (show_author, show_author_bio) defaulting to TRUE. The CMS toggle UI
  for either is disabled when the global Author config is empty (nothing
  meaningful to render).
- When the page template renders, it consults the block matrix (§6) for
  the content type and emits each applicable block whose data field is
  non-NULL, plus Author / Author Bio whose visibility is gated by
  show_author / show_author_bio.

---

## 9. Naming this preview's annotations

The static preview page at `site/_templates/article.html` includes a "Master" tab
that renders **the union of every block across every content type** —
the most complete possible page. Each variant tab (Article, Journal,
Live Session, Experiment) is then a subset of Master with the
type-specific blocks omitted. Some blocks are alternatives to each other
(e.g., Title vs Key Statement, Body vs Custom HTML); Master shows both
side by side for documentation purposes, even though no single rendered
page would ever include both at once.

The label chip beside each annotated block displays its slug:

```
title
special-tag
hero-image
```

The HTML wires this via a single attribute on the block's root element:

```html
<h1 class="article-title is-serif" data-block="title">…</h1>
```

The `data-block` attribute is scaffolding and is removed when the
preview toolbar is removed from `site/_templates/article.html`. The CMS
production rendering does not emit it.

---

## 10. Changelog

- **2026-05-09 (later)** — CMS schema decisions captured. Template enum
  landed (article-standard, article-series, journal-entry, live-session,
  experiment, experiment-html). Hero Image, Event Details, and Format
  Tags compositions specified. block_visibility JSON dropped in favour
  of data-presence visibility plus show_author / show_author_bio
  booleans. categories and author moved from JSON config files to MySQL
  tables. source_file stores just the filename; the path
  /content/[type]/[slug]/ is derived from type and slug.
- **2026-05-09 (latest)** — Editorial layout finalised. Merge note added
  at the top of this doc. §7 Block Order updated to reflect the new
  visual structure: split topstrip (category + series row), prominent
  date row in condensed-uppercase, byline row with rules above and
  below carrying author + read time. Live Session's Event Details and
  Format Tags now combine into a single white card sitting between the
  date row and the byline row.
- **2026-05-09 (later)** — Renamed `standfirst` block to `summary`. The
  block name now matches the underlying PHP field (`$article['summary']`),
  removing journalism jargon. CSS class renamed in tandem
  (`.article-standfirst` → `.article-summary`). Block count: 19 (no change).
- **2026-05-09 (later)** — Author block split into two: `author` (byline:
  image + name + short description) and `author-bio` (footer: image +
  extended description). Author config now has four fields. New §7a
  documents the Author config record. Block count: 19.
- **2026-05-09** — Initial block list (18 blocks). Header Meta split into
  4 atomic blocks (Category, Publish Date, Read Time, Updated Date) per
  decision. Hero Image confirmed as its own block (separate from Body).
