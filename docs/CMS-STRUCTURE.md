# alexmchong.ca CMS — System Structure

**Status:** Schema locked. Ready to build.
**Reference files:** `docs/design-mockups/cms-ui.html` (canonical UI), `docs/BLOCKS.md` (block contract), `site/_templates/` (page rendering templates), `site/_design-system/` (tokens + components)
**Note:** `site/_pages/` in this repo holds standalone marketing pages (about, coaching, landing, etc.) and is **not** part of the CMS content flow. Do not confuse it with the page-rendering templates in `site/_templates/`.

---

## 1. What this document is

The canonical specification for the alexmchong.ca CMS. It captures every decision needed to build the database, the public site templates, and the CMS admin panel. Where the spec defers to another document (`BLOCKS.md` for the block contract, `site/_design-system/` for tokens), it links rather than duplicates.

A fresh Claude instance should be able to pick up the build from this document alone.

---

## 2. Technical Foundation

| | |
|---|---|
| **Host** | DreamHost (shared hosting) |
| **Stack** | PHP + MySQL |
| **Routing** | Apache `mod_rewrite` via `.htaccess` |
| **URL pattern** | `alexmchong.ca/[section]/[slug]` |

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```

**Key invariant:** the slug is permanent once set on a published piece. Source file is swappable. Router maps `slug → DB record → template → rendered page`.

---

## 3. Design System

The CMS is built on Alex M. Chong's personal design system. The canonical reference is `docs/DESIGN.md` and `site/_design-system/system.css`.

### Tokens used in the CMS

The CMS inlines a copy of the design-system tokens at the top of `cms-ui.html`'s `<style>` block. Two CMS-specific tokens are added beyond the public design system:

| Token | Value | Purpose |
|---|---|---|
| `--canvas-raised` | `#ECEAE8` | Filter bars, table headers, lane headers, editor aside, accordion headers, slug prefixes, stage bar |
| `--live-green` | `#3E7A54` | Live status pills + pulsing dot prefix on live slugs |

All other colours come from the `--c-*` palette (rust, terracotta, clay, amber, ochre, olive, moss, forest, sage, teal, ocean, denim, indigo, purple, violet, plum, mauve, rose). Categories pick from this pool by token name.

### CSS architecture (7 layers, in order)

1. `:root` tokens — single source of truth, no raw values elsewhere
2. Base reset + dot-grid
3. Shell — topbar, sidebar, layout frame
4. Components — buttons, filter bar, view header, content area, form fields
5. Table primitives — `.table-card`, `.cms-table`, `.td-title`, `.td-mono`, `.td-actions-inner`
6. Status + stage system — `.pill` variants, `.type-badge`, `.cat-label` with icon, `.tag`
7. View-specific — pipeline, ideation, published, editor, templates, categories, series, indexes

### Three design-system contexts during the build

The DS lives in three contexts while the CMS is being built (see `BUILD-PLAN.md` §2):

1. **Canonical (`site/_design-system/`)** — brand reference for the public marketing site. Deployed to `/_ds/` in Phase 1 so the parallel website project can reference it.
2. **CMS-admin variant (`_design-system-cms/`, temporary)** — created in Phase 2, mirrors the canonical DS plus a CMS Admin components tab (sidebar, topbar, view chrome, filter bars, tables, pills, stage system). Extended in Phase 8 with an Article Templates tab. CMS-scoped tokens (`--canvas-raised`, `--live-green`) are defined here, not in the canonical DS.
3. **Unified (eventual)** — Phase 11 merges the variant back into the canonical DS, promotes CMS tokens that earn their place, deprecates duplicates. Single source of truth.

### Open token question

Whether to promote `--canvas-raised` and `--live-green` into `site/_design-system/system.css` so the public site can reuse them is **deferred to Phase 11** (the design system unification phase). Until then they live as CMS-scoped tokens inside `_design-system-cms/`. See §17 Open Items.

---

## 4. Navigation

Sidebar groups, in order:

**Overview**
- Pipeline — kanban of all in-progress content
- Ideation — pre-pipeline holding space
- Published — everything live, scheduled, or hidden

**Content**
- Articles, Journals, Live Sessions, Experiments

**Structure**
- Content Template (the master template + per-template block toggles)
- Categories, Series, Redirects

**Indexes** (formerly "Editorial Indexes")
- `/digital-garden`, `/thoughts`, `/series/[slug]`, `/writing`, `/journal`, `/experiments`, `/live-sessions`
- `+ New Index` action at bottom
- Each item has an icon distinguishing **Editorial Page** layout (layered hero+grid icon) from **Basic Listing** layout (three stacked lines)

---

## 5. Views

### Pipeline
Kanban board: **Ideas / Concepts / Outlines / Drafts** (plurals).
- White header with italic-serif "Pipeline" title, description, stage-coloured stat row, and a quick-capture bar (input + type select + Add).
- Lane headers use `--canvas-raised` background. Cards are white with subtle shadow.
- Card variants per stage: Idea cards show title + type badge only; Concept onward show title + summary + meta + foot (with category and date).
- Category text in card meta is **coloured per category** (using the category's design-system token).
- Series indicator uses the standard `.tag` pill.

### Ideation
Per-type kanban (Articles / Journals / Live Sessions / Experiments) — same `.kanban-board` shell as Pipeline.
- Smaller `.idea-card` variant: title, optional description, footer with date + secondary "Build →" button.
- "Build" button is white-outlined (`.btn-sec`), advances the idea to **Concept** in Pipeline.
- Cards have `cursor: grab` to communicate drag-and-drop reclassification across type lanes.
- Empty type column shows `.idea-lane-empty` placeholder.

### Published
Scrollable page with type sections (Articles / Journals / Live Sessions / Experiments).
- Filter bar: **Jump to** anchors (Top / Articles / Journals / Live Sessions / Experiments) + **Status** (All / Live / Scheduled / Hidden, with All preselected).
- Section headers are `<type-badge> only` — no redundant text label after the pill.
- Cards: white surface, `box-shadow: 0 1px 3px var(--ink-08)`, hover border accent.
- Live status: pulsing green dot in the pill; live slugs in `--live-green` with the dot prefix.
- Hidden cards: `opacity: 0.55`.
- **Experiment cards use a darkened-green surface** sourced from the design system: `var(--c-experiment-concept)` (`#0a1f1a`) for concept-category experiments, with `.pub-card.dark.proto` switching to `var(--c-experiment-prototype)` (`#1c1028`) for prototype-category. Border colours derived from `--c-forest` / `--c-violet` via `color-mix`.
- Journal card titles render in **Barlow** like other card types — no italic serif (consistency).

### Articles
Three sections: **Preparing** (Idea / Concept / Outline) | **Drafts** | **Published**.
- Filter bar: Category (OR logic) + Special Tag (OR logic).
- Live slug treatment in Published section: `--live-green` text with pulsing dot prefix.
- Actions column: always last, `white-space: nowrap`, never wraps.
- Edit button on a row opens the appropriate stage editor (`view-edit-article-idea` or `view-edit-article-draft`).

### Journals
Sections: **Preparing** | **Published**, organised by pipeline stage (not category).
- Category column shows `<svg> + label` using design-system journal icons:
  - **Introspection**: two opposing parentheses curves (`--c-purple`)
  - **Contemplation**: circle with two arc segments (`--c-teal`)
  - **Insight**: sun-rays icon (`--c-amber`)
- Key Statement column rendered in Barlow semibold (NOT italic serif) for table consistency.

### Live Sessions
Sections: **Draft** | **Upcoming** | **Past**.
- Filter bar label: "Category" (Talk / Workshop / Masterclass).
- Category column shows just the label — no dot, no icon.

### Experiments
Sections: **Preparing** | **Published**.
- Filter bar label: "Category" (Prototype / Concept).
- Category column shows just the label.

### Content Template
The master template + sub-templates view. See §8.

### Categories
Two-column grid of `.cat-block` cards (one per content type).
- Each block: italic-serif block title, description note, `.cat-table` (Label / Value slug / Colour / Use / Del).
- Colour column is a clickable **coloured pill** with white text showing the design-system token name (without the `--c-` prefix). Click to dropdown all 18 design-system colours.
- Use column shows mono numerals at `--text-label` size.
- Del column is a small trash-icon button. Disabled (muted, `cursor: not-allowed`) when use count > 0; active terracotta when count is zero.

### Series
3-column card grid.
- Each card: title, parts count, Edit button.
- Part rows use `--ink-08` background.
- Creating a new series automatically sets up a `/series/[slug]` index page.

### Redirects
Table (Old URL → New URL → Added → Del) plus inline add form.

### New X (per content type)
Four dedicated views, opened from `+ New X` buttons in each content tab. Each creates content **directly at Draft stage**, skipping Idea / Concept / Outline.
- `view-new-article` — full article Draft form (title, summary, category, publish date, slug, special tag, series, template, hero image, body, display tags).
- `view-new-journal-entry` — Key Statement, category, publish date, slug, body. Entry Number auto-assigned at publish.
- `view-new-live-session` — title, summary, category, publish date, slug, event details (date+time, location), three Format Pills (Cost / Attendance / Custom), hero image, body.
- `view-new-experiment` — template selector (`experiment` vs `experiment-html`), title, summary, category, publish date, slug, hero image (article-format only), body or Custom HTML based on template.

Each form has a back button to its content tab, a **Create Draft** primary action, and a **Cancel** secondary action.

### Edit X (existing examples)
- `view-edit-article-idea` — minimal Idea-stage editor (title + notes only).
- `view-edit-article-draft` — full Draft-stage editor (matches the New Article form, pre-filled).
- `view-edit-experiment-draft` — existing experiment editor (legacy; will be reworked when full editor specs land).

### Index Builder views
Two layout types:
- **Editorial Page** — page title block + hero feature + featured articles + content feed + custom sections.
- **Basic Listing** — page title block + content feed only.
All index configuration happens inside a white `.page-canvas` card that visually constructs the page.

---

## 6. Components

### Buttons (6 variants)
| Class | Style | Use |
|---|---|---|
| `.btn-pri` | Dark fill, Barlow Condensed uppercase | Primary actions (Save, New X) |
| `.btn-sec` | Outline, Barlow Condensed uppercase | Secondary (Preview, Cancel, Build) |
| `.btn-ghost` | Subtle outline, JetBrains Mono | Back navigation, Log out |
| `.btn-danger` | Transparent/terracotta text | Destructive actions |
| `.btn-row-action` | Small outline | Inline table Edit buttons |
| `.btn-save` | Dark fill | Save within forms |

### Filter bar
Background: `--canvas-raised`. OR logic — All is default; selecting specifics deselects All. Pills: Barlow Condensed, uppercase, 11px. `.filter-sep` separates groups. The Jump-to group on Published is scoped (`data-jump-group`) so its active state doesn't bleed into the Status group.

### Tables
Wrapper `.table-card`: white, faint border, 4px radius. `thead th`: `--canvas-raised` background, condensed uppercase 12px. `tbody td`: white, 12px vertical padding. Hover: `--ink-08` row tint. Last column always Actions, right-aligned.

`.td-title .t` and `.td-title .slug` each have 4px bottom margin for breathing room.

### Status pills
`.pill` with stage variants (`.pill-idea`, `.pill-concept`, `.pill-outline`, `.pill-draft`, `.pill-live`, `.pill-scheduled`, `.pill-hidden`). All use `color-mix()` for tinted backgrounds.

### Live dot
6px green circle with 2s pulse animation. Used inside live status pills and as a prefix on live slugs in tables.

### Category labels
- Articles: `.cat-label` text-only (already coloured by surrounding context where needed).
- Journals: `.cat-label` with `<svg class="cat-label-icon">` — design-system icons keyed to category.
- Live Sessions / Experiments: `.cat-label` text-only — no dot, no icon.

### Type badges
`.type-badge` with `.tb-article` / `.tb-journal` / `.tb-live-session` / `.tb-experiment` — small chips using `color-mix()` against the type colour.

### Block-mode pill
`.block-mode-pill` with `.mode-always`, `.mode-auto`, `.mode-required`. Used in the per-template block list to indicate visibility mode where there's no toggle.

### Category colour select
`.cat-colour-select` — `<select>` styled as a coloured pill (token colour as background, white text + chevron). Options auto-populated from the 18-colour palette via JS.

---

## 7. Content Blocks

The block contract is defined in **`docs/BLOCKS.md`** — that document is the source of truth. Highlights:

- **19 blocks** total. Each has a Title-Case name and a kebab-case slug (stable forever).
- **Toggleability modes:** `always` (renders whenever the content type includes it), `optional` (toggleable per content piece), `auto-conditional` (renders based on data), `template-specific` (belongs to one content type).
- **Visibility (Path A):** the CMS does **not** store a per-content `block_visibility` JSON. A block renders if its slug is in the template's matrix AND its underlying field is non-NULL. Auto blocks consult their derived condition.
- **Author exception:** the Author and Author Bio blocks are the only blocks with explicit per-content toggles (`show_author`, `show_author_bio` columns on `content`). UI disables both toggles when the global Author config is empty.
- **Block ordering** is fixed per content type (defined in `docs/BLOCKS.md` §7). The CMS does not reorder.

Within the CMS, the **Master template** has a Content Blocks tab listing all 19 blocks with slug, composition, and purpose. Each sub-template (article-standard, article-series, journal-entry, live-session, experiment, experiment-html) has its own block list rendered with show/hide toggles for `optional` blocks and mode pills (Always / Auto / Required) for the rest.

---

## 8. Templates

Six templates, replacing the original five-template system:

| Template ID | Display name | Description |
|---|---|---|
| `article-standard` | article: standard | Default long-form article. Body is Tiptap rich text. |
| `article-series` | article: series | Series-bound article. Series field is required; prev/next navigation auto-renders. |
| `journal-entry` | journal entry | Short reflective entry. Key Statement replaces Title; Entry Number auto-increments per category. |
| `live-session` | live session | Talks, workshops, masterclasses. Includes Event Details and Format Tags blocks. |
| `experiment` | experiment | Article-format experiment. Uses Body. No Series, no Special Tag. |
| `experiment-html` | experiment: html | Raw HTML import. Body is replaced by Custom HTML rendered via PHP `readfile()`. |

The previous `custom` article variant has been removed.

### Master template

The Master template documents every available block, every database field, and the Author config. It is **not selectable** at content creation — it's the reference layer. Master has four tabs:

1. **Content Blocks** — full 19-block reference.
2. **Field Reference** — every database field underlying the blocks, with PHP variables.
3. **Author info** — single-author config form (image, name, short_description, extended_description). Empty fields render as placeholders on the live site (`{no author name}`, etc.). Empty image renders an initials circle, or blank circle if name is also empty.
4. **PHP Layout File** — read-only PHP source for the master layout.

Sub-templates have **no tabs** — they show one panel: their applicable blocks with show/hide toggles or mode pills.

### Per-template block matrix

See `docs/BLOCKS.md` §6. The mockup's `blockMatrix` JS object in `cms-ui.html` is the runtime mirror.

---

## 9. Database Schema

### `content` table

```sql
CREATE TABLE content (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(255) NOT NULL UNIQUE,           -- permanent
  type              ENUM('article','journal','live-session','experiment') NOT NULL,
  status            ENUM('idea','concept','outline','draft','published') NOT NULL,
  published_status  ENUM('live','hidden','scheduled') DEFAULT NULL,
  template          ENUM('article-standard','article-series','journal-entry',
                         'live-session','experiment','experiment-html'),
  -- Editorial
  title             VARCHAR(500),
  key_statement     TEXT,                                   -- journals only
  summary           VARCHAR(500),                           -- Summary block source
  body              LONGTEXT,                               -- rich text HTML (Tiptap)
  source_file       VARCHAR(255),                           -- experiment-html: filename only
  thumbnail         VARCHAR(255),                           -- card display image (separate from hero)
  -- Hero Image block
  hero_image        VARCHAR(500),
  hero_caption      TEXT,
  hero_size         ENUM('default','wide','full') NOT NULL DEFAULT 'default',
  -- Live session fields
  event_start       DATETIME,                               -- combined date + time
  location          VARCHAR(255),
  cost_pill         VARCHAR(50),                            -- 'Free' / 'Fee' / custom string. NULL hides
  attendance        ENUM('in-person','remote'),             -- NULL hides
  custom_pill       VARCHAR(50),                            -- any short string. NULL hides
  -- Author block toggles (per content)
  show_author       BOOLEAN NOT NULL DEFAULT TRUE,
  show_author_bio   BOOLEAN NOT NULL DEFAULT TRUE,
  -- Article-specific
  special_tag       ENUM('principle','framework'),
  series_id         INT,                                    -- FK series.id
  series_order      INT,
  -- Journal-specific
  journal_number    INT,                                    -- per-category counter, assigned on publish
  -- Common metadata
  read_time         INT,
  tags              VARCHAR(500),                           -- comma-separated, display only
  published_at      TIMESTAMP NULL,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Pipeline progressive disclosure
  concept_text      TEXT,
  outline_text      TEXT,
  -- Indexes
  INDEX ix_type_status (type, status),
  INDEX ix_published_at (published_at),
  INDEX ix_series_id (series_id)
);
```

### `content_categories` table (article secondary categories)

```sql
CREATE TABLE content_categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  content_id  INT NOT NULL,
  type        VARCHAR(50),                                  -- always 'article' for v1
  category    VARCHAR(100),                                 -- value_slug from categories table
  is_primary  BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
);
```

### `series` table

```sql
CREATE TABLE series (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255),
  slug        VARCHAR(255) UNIQUE,
  description TEXT
);
```

### `redirects` table

```sql
CREATE TABLE redirects (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  old_slug    VARCHAR(500) UNIQUE,                          -- e.g. /writing/old-slug or /portfolioforhire/
  new_slug    VARCHAR(500),                                 -- internal path OR full external URL
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- A `status_code TINYINT NOT NULL DEFAULT 301` column is added
-- in Phase 10 (see BUILD-PLAN.md) so the 301 vs 302 choice is
-- editable per row.
```

`new_slug` accepts two shapes:
- **Internal path** — starts with `/` (e.g. `/writing/new-slug`). The router rewrites the URL to the new path on the same domain and serves the resolved content. Used for slug renames within the site.
- **External URL** — starts with `http://` or `https://` (e.g. `https://alexmchong-portfolio.webflow.io/`). The router issues an HTTP redirect to the external destination. Used for legacy paths that hand off to third-party services (Webflow, Notion, Calendly, LinkedIn). See `LEGACY-ROUTES.md` for the inventory.

The router detects the shape by checking the URL prefix; no separate flag column is needed.

### `categories` table (replaces `config/categories_config.json`)

```sql
CREATE TABLE categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  type        VARCHAR(50)  NOT NULL,    -- 'articles' | 'journals' | 'live-sessions' | 'experiments'
  value_slug  VARCHAR(100) NOT NULL,    -- permanent
  label       VARCHAR(255) NOT NULL,    -- editable
  colour      VARCHAR(50)  NOT NULL,    -- design-system token name e.g. 'terracotta'
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_type_slug (type, value_slug)
);
```

### `author` table (replaces `config/author.json`)

```sql
CREATE TABLE author (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  image                VARCHAR(500),     -- /uploads/author/<filename> or NULL
  name                 VARCHAR(255),     -- NULL → renders {no author name}
  short_description    TEXT,             -- NULL → renders {no short description}
  extended_description TEXT,             -- NULL → renders {no extended description}
  updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- App logic enforces single-row. INSERT one row at install with all NULLs.
```

### Coupling notes

- `content.category` and `content_categories.category` store the **value_slug string** (loose-coupled to `categories.value_slug`). Not an FK relationship — the UNIQUE on `(type, value_slug)` enforces integrity. This avoids migration if rows ever need to be touched independently of the categories table.
- `author` is separate from `content` for v1 (single-author site). Multi-author would add `author_id` FK on `content` and re-resolve per article.

### Render-layer rules (no schema impact)

| Rule | Behaviour |
|---|---|
| **Visibility (Path A)** | A block renders if its slug is in the template's matrix AND its underlying field is non-NULL. Auto-conditional blocks render based on derived data. |
| **Author + Author Bio** | Render only if `show_author` (resp. `show_author_bio`) is TRUE AND the author config has at least one non-empty field. Empty author fields render placeholder text (`{no author name}`, `{no short description}`, `{no extended description}`). Empty image → initials circle from `name`, or blank circle if name is also empty. |
| **Custom HTML path** | `/content/[type]/[slug]/[source_file]`. Type and slug come from the content row. |
| **Image uploads** | `/uploads/author/[filename]` for the author config; `/uploads/content/[type]/[slug]/[filename]` for hero images and Tiptap inline images. |

---

## 10. Category System

Categories now live in MySQL (the `categories` table) — moved out of `config/categories_config.json`.

### Initial seed

```
articles:        UX Industry (terracotta), Leading Design (forest), For Designers (denim)
journals:        Introspection (purple),   Contemplation (teal),    Insight (amber)
live-sessions:   Talk (amber),             Workshop (mauve),        Masterclass (purple)
experiments:     Prototype (violet),       Concept (teal)
```

`colour` is the **design-system token name** (no `--c-` prefix). The CMS dropdown enumerates all 18 colours from the design-system palette.

### Rules

- `value_slug` is permanent. Set once, never renamed.
- `label` and `colour` are freely editable.
- Deletion requires usage count = 0 (UI disables the trash icon otherwise).
- The CMS reads from the `categories` table to populate every category dropdown and colour rendering.

---

## 11. Author Config

Single-row `author` table (single-author site). Configured from the **Author info** tab on the master Content Template view.

| Field | Storage | Use |
|---|---|---|
| Image | `image` (VARCHAR 500) | Author byline + Author Bio — `/uploads/author/<filename>` |
| Name | `name` (VARCHAR 255) | Author byline |
| Short description | `short_description` (TEXT) | Author byline (renders inline as "Name – Short description") |
| Extended description | `extended_description` (TEXT) | Author Bio (footer panel, fuller paragraph) |

### Empty-state behaviour

The CMS surfaces missing fields via deliberate placeholder text rather than hiding the block:

- Empty `name` → renders `{no author name}`
- Empty `short_description` → renders `{no short description}`
- Empty `extended_description` → renders `{no extended description}`
- Empty `image` → initials circle derived from `name`; blank circle if `name` is also empty

### Per-content toggles

Each content row has `show_author` and `show_author_bio` BOOLEAN columns (default TRUE). The CMS's per-content toggles are **disabled** in the UI when every author field is empty (nothing to render).

### Image management

- Upload writes to `/uploads/author/<filename>`.
- Replace overwrites or stores a new path; no version history.
- Remove deletes the file and clears the `image` column.

### Multi-author future

Out of scope for v1. When it becomes needed: add `author_id INT` FK on `content`, drop the single-row constraint on `author`, and resolve the author record per piece in the render layer.

---

## 12. Custom HTML Folder System

Used by the `experiment-html` template (and the optional Custom HTML block in any future template that opts in).

### Workflow

1. Slug must exist on the content row first.
2. **Set Up Folder** button shows a confirmation modal with the exact server path.
3. On confirm, PHP creates `/content/[type]/[slug]/` server-side.
4. The folder dropdown populates with `.html` files inside.
5. **Refresh** button re-scans (for FTP drops).
6. **Delete Folder** requires confirmation; only allowed when the folder is empty.
7. If the toggle is removed but the folder still exists, the picker remains visible (non-destructive).

### Storage

`content.source_file` stores **just the filename** (e.g. `main.html`). The full path is derived as `/content/[type]/[slug]/[source_file]`.

### Render

The `experiment-html` template renders the file via PHP `readfile()` — no nav, no footer, no template wrapper. Raw passthrough.

---

## 13. Image Uploads

| Use | Path | Notes |
|---|---|---|
| Author photo | `/uploads/author/<filename>` | Single file. Replace overwrites; remove deletes. |
| Hero image | `/uploads/content/[type]/[slug]/<filename>` | One per content row. Stored in `content.hero_image` as the path. |
| Tiptap inline images | `/uploads/content/[type]/[slug]/<filename>` | Co-located with the hero image folder. URL returned to the editor for `<img>` insertion. |

---

## 14. URL Structure

```
alexmchong.ca/digital-garden/        Editorial Page layout
alexmchong.ca/thoughts/              Basic Listing layout (articles only)
alexmchong.ca/writing/               Basic Listing layout (all articles)
alexmchong.ca/journal/               Basic Listing layout (all journals)
alexmchong.ca/live-sessions/         Basic Listing layout
alexmchong.ca/experiments/           Basic Listing layout
alexmchong.ca/series/[slug]/         Auto-generated series index (Editorial Page layout)
alexmchong.ca/writing/[slug]         Article content
alexmchong.ca/journal/[slug]         Journal entry
alexmchong.ca/live-sessions/[slug]   Live session
alexmchong.ca/experiments/[slug]     Experiment (article-format OR raw HTML passthrough)
```

Redirects: stored in `redirects` table, served by the router. Default status code is **301 Permanent Redirect** (used for slug renames within the site, where the new URL is the canonical home of the content). Legacy external redirects — paths inherited from the previous alexmchong.ca that point at third-party services (Webflow, Notion, Calendly, LinkedIn) — default to **302 Found** instead so the destination can change without browsers caching a stale permanent redirect. The `status_code` column on `redirects` (added in Phase 10) makes this editable per row. See `LEGACY-ROUTES.md` for the full inventory.

---

## 15. Pipeline Stage Matrix

| Stage | Articles | Journals | Live Sessions | Experiments |
|---|---|---|---|---|
| Idea | ✓ | ✓ | ✓ | ✓ |
| Concept | ✓ | — | — | — |
| Outline | ✓ | — | — | — |
| Draft | ✓ | ✓ | ✓ | ✓ |
| Published | ✓ | ✓ | ✓ | ✓ |

**Published sub-states:** Live | Hidden | Scheduled.
- **Live**: public, appears in every applicable index.
- **Hidden**: URL resolves but not listed anywhere.
- **Scheduled**: future `published_at`; goes live automatically when the datetime matches (cron vs check-on-load decision deferred — see §17).

### Stage entry from the CMS

- **Pipeline view**: quick-capture creates at **Idea**.
- **Ideation view**: cards represent ideas; "Build →" advances to **Concept**.
- **Content tab `+ New X` buttons**: create directly at **Draft**, skipping early stages. The form surfaces all required Draft-stage fields. The author can step back via the stage bar if needed.

---

## 16. Editorial Index System

### Two layout types

- **Editorial Page** — page title + hero feature + featured articles + content feed + custom sections. Used by `/digital-garden`, `/series/[slug]`, optionally `/thoughts`.
- **Basic Listing** — page title + content feed only. Used by `/writing`, `/journal`, `/live-sessions`, `/experiments`, `/thoughts` by default.

### Builder structure

All index configuration happens inside a white `.page-canvas` card. Common elements:

- **Page Title block** (hideable on Editorial Page; required on Basic Listing) — title + optional subheader/description.
- **Hero Feature** (Editorial only) — manually select one published item.
- **Featured Articles** (Editorial only) — drag-ordered curated picks.
- **Content Feed** — type chips (OR), sort chips (Newest / Oldest / Manual), rows-shown selector (Editorial: 1/2/3/4/All).
- **+ Add Section** (Editorial) — stackable sections.

### New Index creation

- Entry: **+ New Index** in the sidebar Indexes section.
- Required: slug (permanent) + layout choice.
- Creating a new Series automatically creates a corresponding `/series/[slug]` Editorial Page index.

### Filter logic (global)

OR semantics. All is the default. Selecting a specific filter deselects All. Multiple specific filters = OR. Clicking All resets. The **Jump to** group on Published is scoped via `data-jump-group` so its active state is independent of the Status group.

---

## 17. Journal Sequential Numbering

```sql
SELECT COALESCE(MAX(journal_number), 0) + 1
FROM content
WHERE type = 'journal' AND category = '<category_slug>';
```

Result is stored permanently on publish. Gaps remain if entries are deleted. Numbers are **archival identities, not positions** — never reclaimed.

Renders as `Entry 14` in the meta line. The Entry Number block (auto-conditional in BLOCKS.md) appears whenever `journal_number` is non-null.

---

## 18. Key Design Principles

- **Slug is the contract.** Permanent once published. Routing, linking, redirects all hinge on this.
- **`get_article($slug)` is the only data access point.** Migrations and refactors only touch this function; everything else stays the same.
- **Templates are PHP shells.** Content is injected. Templates control layout and field rendering; they do not store content.
- **Master template is documentation.** It defines every available block and field. Sub-templates only suppress — they never add.
- **Categories are MySQL records, not config.** Permanent value_slug; editable label / colour. Colour values reference design-system tokens by name.
- **Author config is single-row, single-author.** Renders with placeholders for empty fields. Per-content toggles control its appearance, gated by config-non-emptiness.
- **OR logic everywhere.** No AND filtering in the CMS.
- **Visibility = data presence (Path A).** No `block_visibility` JSON column. Author and Author Bio are the only blocks with explicit per-content boolean toggles.
- **Block ordering is fixed per content type.** Defined in templates, not the CMS.
- **Image uploads go under `/uploads/`.** Author files in `/uploads/author/`; per-content files in `/uploads/content/[type]/[slug]/`.

---

## 19. Open Items

These remain unresolved or deferred and will need decisions during the build. None block the database schema.

- **Tiptap toolbar config** — what blocks/marks does the rich text editor allow? Image insertion flow? Embed/iframe support? Decide before the editor is wired up.
- **Scheduled-post auto-publish** — cron job vs check-on-load. Decide before Phase 8 (automation).
- **CMS auth approach** — single-user `users` table with email-as-username and bcrypt password hash. Sessions via PHP native handler on httpOnly+secure+samesite-strict cookies. Login throttling (5 fails → 15 min lock). CSRF on every POST. Password-reset by email **deferred to Phase 11** of `BUILD-PLAN.md`; v1 ships with in-CMS change-password only. Full spec in `AUTH-SECURITY.md` (drafted at the start of Phase 4).
- **CMS-token promotion** — should `--canvas-raised` and `--live-green` be moved into `site/_design-system/system.css` for public-site reuse? Currently CMS-scoped. Revisit if the public site grows a "live now" indicator or similar.
- **Multi-author** — out of scope. Future change: add `author_id` FK on `content`, drop single-row constraint on `author`.
- **Mobile/responsive CMS** — explicitly out of scope. Desktop only for v1.
- **`thumbnail` vs `hero_image`** — currently two separate fields (card-level vs page-level). If author wants "use hero as thumbnail" as a UI shortcut, that's a frontend convenience, not a schema change.

---

## 20. Email Subscribers

A small but distinct data model: the public site collects newsletter signups. The CMS stores them so the author can later export, segment, or hand off to a third-party email service (Mailchimp, Buttondown, etc.) when the list grows.

This is **not** a content type — subscribers don't have slugs, statuses, or templates. It's a flat administrative table that the CMS reads from and writes to.

### `subscribers` table

```sql
CREATE TABLE subscribers (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(255) NOT NULL,
  name            VARCHAR(255),                       -- optional
  subscribed_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at TIMESTAMP    NULL,                  -- NULL = still subscribed
  source          VARCHAR(100),                       -- e.g. 'newsletter-page', 'live-session-rsvp'
  confirm_token   VARCHAR(64)  NULL,                  -- reserved for future double opt-in
  confirmed_at    TIMESTAMP    NULL,                  -- reserved; NULL = unconfirmed
  ip_address      VARCHAR(45),                        -- IPv6-safe, for spam debugging
  user_agent      VARCHAR(500),                       -- for spam debugging
  UNIQUE KEY uk_subscribers_email (email),
  INDEX ix_subscribers_subscribed_at (subscribed_at)
);
```

### Public flow (v1)

1. Subscriber visits `/subscribe/` (the static signup page lives at `site/_pages/newsletter.html` today; it will move into the CMS routing as `/subscribe/` when wired up in Phase 10).
2. They submit email + optional first name.
3. The handler inserts a row into `subscribers` with `source = 'newsletter-page'`, captures `ip_address` and `user_agent` for spam debugging, and 302-redirects to `/subscribe/confirmed/`.
4. The confirmation page (`site/_pages/newsletter-confirmed.html`) renders.
5. **No confirmation email is sent in v1.** Double opt-in is reserved (the `confirm_token` / `confirmed_at` columns exist for it) and will be enabled when Phase 11's SMTP setup lands.

### Validation

- Email is required and must match a reasonable regex (or `filter_var($email, FILTER_VALIDATE_EMAIL)`).
- Name is optional, capped at 255 characters.
- Duplicate emails are not an error: if `email` already exists, update `subscribed_at = NOW()`, set `unsubscribed_at = NULL`, and proceed to the confirmation page. The subscriber re-subscribing is a feature, not a 409.

### Anti-spam

- Honeypot field (a CSS-hidden `<input>` that real users never fill). If submitted with a value, silently discard.
- Server-side rate limit: 1 submission per IP per minute, 10 per IP per day.
- CSRF token if the form is rendered from a PHP view; not required for the static `site/_pages/` preview.

### CMS view

A `/cms/subscribers/` view in the admin:

- Table: email · name · subscribed_at · source · status (subscribed | unsubscribed).
- Filters: status, source, date range.
- Row actions: mark unsubscribed (sets `unsubscribed_at = NOW()`), delete.
- Export: button that downloads CSV of all subscribed rows (`email, name, subscribed_at, source`).
- Counts in the view header: total subscribed, total unsubscribed, signups in last 30 days.

### Unsubscribe

For v1, unsubscribe is **manual**: a subscriber emails Alex, and he marks the row in the CMS. When transactional email lands (Phase 11), every outgoing email gets a one-click `/unsubscribe/?token=…` link that sets `unsubscribed_at` automatically.

### Eventual hand-off

When the list outgrows manual management (typical threshold: a few hundred subscribers, or when sending volume requires deliverability infrastructure), the CSV export feeds a third-party (Buttondown, Postmark Broadcasts, Mailchimp, etc.). The `subscribers` table becomes the source of truth; the third-party becomes the delivery mechanism. No data is lost in that transition.

### Files involved

- `site/_pages/newsletter.html` — static signup page (v1, hand-built, lives outside CMS).
- `site/_pages/newsletter-confirmed.html` — static confirmation page.
- `site/_pages/_layout/style-pages.css` §9a — form primitives.
- (Phase 10) `cms/views/subscribers.php` — CMS list/manage view.
- (Phase 10) `lib/subscribers.php` — data access (`get_subscriber($email)`, `list_subscribers($filters)`, `save_subscriber($data)`, `unsubscribe($email)`, `export_subscribers_csv()`).
- (Phase 10) `db/migrations/000X_subscribers_table.sql`.
- (Phase 10) `POST /subscribe` route in `index.php`.
- (Phase 10) `GET /subscribe/confirmed/` route (moves the static confirmation into the dynamic router so it can be customised per signup if needed).

---

## 21. File Reference

| File / Folder | Role |
|---|---|
| `docs/design-mockups/cms-ui.html` | Canonical CMS UI mockup. Build PHP against this. |
| `docs/BLOCKS.md` | Block contract — source of truth for what blocks exist, their slugs, modes, and rendering rules. |
| `site/_templates/article.html` | Static preview of the article template with annotated blocks. |
| `site/_templates/layouts.html` | Multi-layout reference (article / journal / live session / experiment renderings). |
| `site/_templates/style-articles.css` | Public-site stylesheet for article-family templates. |
| `site/_pages/` | Standalone marketing pages (about, coaching, landing, resume, work-with-me, newsletter, newsletter-confirmed) and their stylesheet (`style-pages.css`). **Not** part of the CMS content flow. |
| `site/_pages/newsletter.html` | Static newsletter-signup page. Form posts statically to `newsletter-confirmed.html` until Phase 10 wires the real endpoint. |
| `site/_pages/newsletter-confirmed.html` | Static post-signup confirmation page. |
| `docs/DESIGN.md` | Design system documentation. |
| `site/_design-system/system.css` | Design system stylesheet — token source of truth. |
| `site/_design-system/system.js` | Tab routing, scroll-spy, filter pill logic. |
| `site/_design-system/index.html` | Design system showcase. |
| `BUILD-PLAN.md` | 10-phase execution plan. Single source of truth for "what gets built next". |
| `ENGINEERING.md` | Code conventions. Read before any PHP/CSS/JS work. |
| `AUTH-SECURITY.md` | Auth + security spec. Drafted at the start of Phase 4. |
| `DEPLOYMENT.md` | DreamHost deploy workflow. Drafted during Phase 3. |
| `LEGACY-ROUTES.md` | Inventory of every alexmchong.ca URL that needs to keep resolving after migration. Drafted during Phase 0; consumed by `.htaccess` in Phase 3; migrated into the `redirects` table in Phase 10. |
| `docs/onboarding/` | Phase 0 lessons (Git, DreamHost, VS Code + Claude Code, three-environment workflow). |
| `CLAUDE.md` | Project context for assistants. Lists the file map and the coupling rule between `docs/design-mockups/` and `site/_templates/`. |
| `CMS-STRUCTURE.md` | This document. Canonical system spec. |
