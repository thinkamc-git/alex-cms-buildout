# Editorial Index Builder — Feature Requirements

Brief a designer to mock up the admin UI for building an Editorial Index page. This document describes *what* the UI needs to accomplish and *what data it operates on*. It deliberately avoids prescribing layout, typography, colour, or specific components — the designer should bring the visual logic.

---

## 1. Context

The product is a custom CMS for a personal site belonging to a single author (a designer / writer). The CMS manages four content types — Articles, Journals, Live Sessions, Experiments — plus standalone collection pages called **Indexes**.

Indexes come in two layouts:

- **Basic Listing** — a single filtered feed (one page = one filterable stream of posts). Already shipped; not part of this brief.
- **Editorial Page** — a hand-composed page where the author assembles their own layout from typed building blocks. This is what we're designing.

The author wants Editorial Pages because Basic Listing is too rigid for curated landing pages — things like a "digital garden" page that mixes a featured hero, a hand-picked recommended list, and a filtered "everything else" feed.

The new model: **an Editorial Page is an ordered stack of typed sections.** The author composes the page by adding sections, configuring each one, and arranging them. This brief is for the admin UI that lets them do that.

### The product's existing aesthetic — please match it

The CMS has a distinct, considered visual language already established across its other views (subscribers, articles editor, navigation editor, pages editor, etc.). The new view must feel like part of the same product. Concretely:

- **Single-author, editorial product.** Reads like a writer's workshop — quiet, focused, no marketing flourishes. Mood: "a serious tool for someone who cares about craft."
- **Type palette:**
  - **Barlow** (sans) — body copy, input text, primary text
  - **Barlow Condensed** (uppercase, tracked) — labels, section headings, button text, small caps tags
  - **Instrument Serif** italic — page titles, emphasis spans within headings
  - **JetBrains Mono** — small status text, slug paths, metadata lines, technical labels
- **Colour palette:**
  - Warm off-white surfaces (`#fafaf7`-ish), ink-dark text (`#1a1a1a`-ish), muted greys for secondary text
  - A single primary accent (a muted brick-red / terracotta tone) reserved for *active states* and *primary actions* — used sparingly
  - A small set of accent colours (forest green, denim blue, terracotta orange) reserved for *category badges and content type tags* — not for chrome
  - Borders are thin (1px), low-contrast, sometimes dashed for "empty / add new" affordances
- **Component vocabulary already in use:**
  - **Content cards** — rectangular containers with a 1px border, very subtle drop shadow, generous internal padding, a header bar at the top with a uppercase mono caps label
  - **Field groups** — a small uppercase mono caps label above a generously-padded text input with pill-shaped borders
  - **Status pills** — small rounded badges with low-saturation tinted background + matching border colour (e.g. "Published" = forest tint, "Draft" = grey)
  - **Pill toggles / filter rails** — horizontal rows of small pill-shaped buttons used as multi-select or single-select controls (the same control is used for category filters, type filters, sort order, etc.). Active state inverts: filled with primary colour.
  - **Drag-reorderable lists** — vertical lists where each row has a grip handle on the left (rendered as two vertical dots `⋮⋮` in muted mono) and the rest of the row contains the row's content
  - **Hover-revealed actions** — destructive or secondary actions (delete, edit) only appear when the user hovers the row, to keep the list visually quiet
  - **Sticky save bar** — every edit view has a save bar pinned to the bottom edge with `Save` + `Cancel` buttons (and sometimes a `Delete` button right-aligned)
- **Density:** moderate. Generous breathing room around fields, but not airy. Lists are dense enough to scan many items at once.
- **Restraint:** the CMS is deliberately quiet. No icons-everywhere, no rainbow categorical colour-coding, no decorative graphics. A label is almost always typography, not a badge.

The design should feel like it grew inside this existing product — not bolted on.

---

## 2. User goal

> "I want to compose a custom editorial page out of typed content blocks — a featured banner, a hand-picked grid, a filtered feed — arrange them in the order I want, configure how each one behaves, and see what the public page will look like before I save."

---

## 3. The data model

An Editorial Page has **page-level fields**:
- `title` — the page heading shown at the top of the public page (e.g. "Latest thinking"). Optional. Supports `*italic emphasis*` syntax (asterisk-wrapped words render in italic serif).
- `subtitle` — short descriptor under the title. Optional.
- `show_title` — boolean. When off, the public page renders the section stack only, with no page header.

And an **ordered stack of sections**. Each section has a `type` (one of three) plus fields specific to that type.

### Section type 1 — Hero

A single hand-picked published item rendered as a large banner on the public page.

Fields:
- `title` — optional heading shown above the hero on the public page.
- `item` — exactly one content item, picked by the author from any published content (Article / Journal / Live Session / Experiment).

### Section type 2 — Curated

A hand-picked, drag-ordered set of items.

Fields:
- `title` — optional heading.
- `items[]` — an ordered list of picked content items (mix of types allowed). The author drags to reorder.
- **Display:**
  - `format` — Grid or Carousel
  - If Grid: `rows` — 1, 2, 3, 4, or All
  - If Carousel: `item_count` — integer (how many to show in the strip)
- **See more** (optional trailing card):
  - `target` — an existing index slug (e.g. `/writing`) or an absolute URL
  - `label` — text on the see-more card (e.g. "See all writing")
  - If `target` is blank → no see-more card renders.

### Section type 3 — Feed

A filter-driven, self-updating set of items.

Fields:
- `title` — optional heading.
- **Content query** (defines the pool the section pulls from; never shown to visitors):
  - `types[]` — zero or more of Article / Journal / Live Session / Experiment (empty = all four)
  - `categories[]` — zero or more category slugs (OR semantics; empty = any)
  - `sort` — Newest or Oldest
- **Display** — same as Curated (format + rows OR item_count)
- **See more** — same as Curated
- **Visitor filter** (a separate layer — the author decides if visitors can re-filter the section):
  - `show` — boolean. When off, no pills render on the public page.
  - If on:
    - `by` — Types or Categories (what the pills represent)
    - `options[]` — explicit subset of pills to show. If blank, auto-derive all available. Lets the author hide pills they don't want offered.
    - Pre-selection: the section's own content query (above) is reflected as the pre-selected pill state on page load.

### A page can:
- Have zero sections (valid, just renders the title/subtitle).
- Have any number of sections.
- Repeat section types — multiple Hero sections, multiple Feeds, etc., in any order.

---

## 4. User tasks

The author needs to:

1. **See the entire stack at a glance** — what sections are on the page, in what order, what type each is, and a quick sense of what's in each (a recognisable summary of the configuration without expanding it).
2. **Add a section** — pick a type, the section appears at the bottom of the stack ready to configure.
3. **Configure a section** — set its title, edit its type-specific fields.
4. **Reorder sections** — drag a section up or down within the page.
5. **Inside a Curated section, reorder picked items** — drag picks within the section.
6. **Delete a section** — with confirmation (deleting is destructive — the configuration is lost).
7. **Preview the page** — see what the public Editorial page will look like before saving.
8. **Save** — persist changes.
9. **Cancel** — discard unsaved changes and return to the Indexes list.

### Edge cases the design should handle:
- An **empty page** (zero sections) — give the author a clear path to add their first section.
- A **section with no picks yet** (e.g. a Hero with no item selected) — show that state explicitly so it can't ship empty by accident.
- A **broken pick** (the author picked an item that's since been unpublished or deleted) — surface it as broken in the section's summary so the author knows.
- A **long page** (5+ sections, multiple expanded) — the page should remain usable; scrolling is acceptable; collapse-all might help.

---

## 5. Required interactions

- **Drag-to-reorder** the section stack itself.
- **Drag-to-reorder** picked items inside a Curated section.
- **Expand / collapse** an individual section to reveal/hide its config.
- **Live summary** in each section's collapsed state — when the author tunes a value (e.g. switches Format from Grid to Carousel), the summary line in the collapsed header updates to reflect it.
- **Tabs:** the view has two tabs — **Edit** (the builder) and **Preview** (renders the public page).
- **Sticky save bar** at the bottom.
- **Unsaved-changes warning** when the author navigates away with edits pending.

---

## 6. Where this view sits in the CMS

The view sits inside the CMS's standard admin chrome:
- A **left sidebar** with navigation links to other CMS areas (Articles, Journals, Pages, Indexes — Indexes is active when this view is open). The sidebar already exists and should not be redesigned.
- A **top bar** with an environment badge (e.g. "STAGING") + breadcrumb (e.g. "Indexes → Edit"). Already exists, don't redesign.
- Below the topbar, a **view header** with the page's title (the Index's name, e.g. "Digital Garden"), a subtitle line (its URL slug + a short description), and two action buttons (`← Back to Indexes`, `Live ↗`). This pattern already exists in the rest of the CMS.
- Under the view header, **two tabs** — Edit | Preview — using the existing tabs pattern.

**What needs designing:** everything below the tabs in the Edit tab — page metadata fields + the section stack builder + the "+ Add section" affordance + the sticky save bar at the bottom.

The Preview tab will iframe the live rendered page — no design needed for it.

---

## 7. Out of scope (do not design)

- The **Basic Listing** edit view (already shipped, untouched).
- The **public Editorial render** — how the sections actually appear on the live site to visitors.
- The **Indexes list view** — the page that links into this editor.
- The **CMS sidebar, topbar, view header, tabs pattern** — all already exist.
- The **new-index creation flow** — the user lands on this view after creating elsewhere.
- The **content-item picker UX** — assume a standard typeahead dropdown is fine; we can deepen that later.

---

## 8. Constraints

- **Desktop only.** Designed for ≥1280px width. No mobile or tablet considerations.
- **Match the existing CMS aesthetic** (described in §1). The design must read as part of the same product — same typographic scale, same colour discipline, same component vocabulary. No new visual language.
- **No flashy interactions or decorative motion.** This is a working tool used many times a day; restraint over delight.
- **Single-author tool.** No concepts like "who edited last", "live collaboration", "permissions", "history", or "comments" need to appear.

---

## 9. What a finished design looks like

The deliverable from this brief is a static mockup (HTML, Figma, image — any format) showing:

1. The **Edit tab** with the section stack, populated with at least one of each section type (Hero, Curated, Feed) so the differences are visible.
2. An **empty-state** version (no sections yet) showing the "add your first section" path.
3. The **"+ Add section"** affordance in its open state, showing the three type choices.
4. A section in **expanded** state showing its full configuration form.
5. A section in **collapsed** state showing what the author sees at a glance.
6. The **sticky save bar**.

Hover and drag states are nice but not required for the first round.
