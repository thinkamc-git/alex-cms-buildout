# CMS Copy Audit (Phase 21.5)

## Methodology

Every string in `docs/COPY-INVENTORY.md` (1,281 entries spanning the CMS sidebar, topbar, list views, edit views, system views, and auth surfaces) was evaluated against three criteria: (1) is it stale build-language now that the CMS has shipped to prod — Phase N references, "land in Phase X", "wires", "ship", "Phase 11 redirects" etc.; (2) is the function of the control or surface understandable from the string alone, without prior build context; (3) does it follow consistent vocabulary and format patterns with sibling surfaces. Strings that pass all three are kept as-is and omitted from the Rewrites section. Strings that fail any one are rewritten. Strings whose right answer needs author input are flagged in Open questions.

---

## Vocabulary decisions

| Term | Decision | Rationale |
|---|---|---|
| Generic noun for "a row in the content table" | **entry** | Already in heavy use as a count suffix ("3 entries"). Reads cleaner than "post" (loaded with blog connotations), "item" (too generic), or "record" (database language). Lets us write "No entries yet" as the table default without choosing between four content types. The four content type names (Article / Journal / Live Session / Experiment) stay as-is for type-specific surfaces. |
| "Journal" vs "Journal entry" | Sidebar/section/breadcrumb stays **Journals** (collection). A single row is **a journal entry** in confirm dialogs, empty states, and prose. The button is **+ New Journal** (consistent with + New Article, + New Live Session, + New Experiment). | Matches existing pattern. Each journal IS structurally a numbered entry, so "journal entry" reads naturally where a singular noun is needed; "Journals" reads naturally as a section. |
| "Live Session" vs "Live event" | **Live Session** everywhere user-facing. | Sidebar, list, edit, breadcrumb, and column headers all already say Live Session. Switching to "Live event" would require eight rewrites for no functional gain. The DB table, route (`/live-sessions/`), and `live_session_id` foreign keys all reinforce "session". "Event" stays as a sub-word inside Live Session surfaces (Event Date, Event Title, Event Details) since that names the calendar attribute, not the content type. |
| Action verbs | **Save** (commit current form state) · **Publish** (push live now) · **Schedule** (publish later, cron-driven) · **Unpublish** / **Move to draft** (remove from public site) · **Refresh** (re-scan, re-read, re-fetch from server) · **Add** (create a sub-row inline) · **+ New X** (create a top-level entry) · **Edit** (open edit view) · **Delete** (destroy permanently) · **Advance to / Move back to** (pipeline stage transitions). | None of these are build-language. The only verb to scrub is "ship" — replace with "publish". Drop "fold in", "wires", "lands", "land in Phase X" everywhere. |
| "Mock" vs "version" vs "preview" (Pages CMS) | **Mock** = stored body variant (db row). **Live version** = file on disk. **Preview** = iframe render of either. **Publish a mock** = staging-only file override. Drop the word "version" except in the dropdown label "Live Version (file on disk)" which reads naturally. | These are three distinct concepts and need three distinct words. Standardising clarifies the page-edit toolbar. |
| "Card" vs "row" vs "item" | **Card** = ideation tile (visual grid). **Row** = table row (lists, navigation, redirects). **Item** = navigation menu entry (already in flash messages: "Item saved"). No change needed — current usage is already consistent. | Each word maps to a distinct visual treatment; conflating them would lose clarity. |
| "Published" vs "Live" | **Published** is the stage name (status pill, column header, button "Publish"). **Live** is the public-site visibility state (status banner "Published on X · live now", button "View live", LIVE pill on overridden pages). | Already consistent. Don't merge — "Live" answers "is the public seeing it?" while "Published" answers "what stage is this in?". They coincide today but the words pull on different axes. |
| "Stage" vs "Status" | **Stage** for pipeline position (Idea → Concept → Outline → Draft → Published). **Status** for binary state (subscribed/unsubscribed). | Already consistent. |
| "Idea Notes" capitalization | **Idea notes** (sentence case) | Field labels elsewhere are sentence case (Primary category, Read time, Hero image). "Idea Notes" is the outlier. |
| "Key Statement" capitalization | **Key statement** (sentence case) | Same rationale. The Key Statement is a journal-specific field name; the lowercase form still reads as a defined term thanks to position. |
| "Schedule for Publish" | **Schedule publish** (button) / **Schedule** (field label) | "for Publish" is awkward; matches "Publish" / "Schedule →" button language elsewhere. |

---

## Pattern decisions

| Pattern | Rule | Example |
|---|---|---|
| View subtitle | 1–2 sentences, present tense, describes function. No build language. No future tense. No "Phase X". No "land in", "ship in", "wires". | "Long-form writing. Create, edit, and publish drafts." |
| Field hint (below input) | Short imperative or noun phrase. Sentence case. Period at end if it's a full sentence; no period on fragments. | "Lowercase letters, numbers, hyphens. Becomes part of /writing/<slug>." |
| Field hint inline (next to label) | Single word or short fragment. Lowercase. No period. Use for `optional`, `required`, `manual`, `in series`. | `optional` |
| Field placeholder | Example value or short prompt. Lowercase unless it's a proper noun example. No trailing period. | `auto-from-title` · `e.g. Toronto, ON` |
| Button label | Verb in Title Case. No "Click to". Tiny utility buttons can be one word (Save, Edit, Delete, Cancel, Add). Action + object only when ambiguous (Save Author, Save metadata, Create draft). | `Publish Now`, `Move to draft`, `Save`, `+ New Article` |
| Confirm dialog | `Verb subject? Consequence.` — name the thing being acted on; state the consequence in plain English. | `Delete article "<title>"? This can't be undone.` |
| Empty state | `No X yet.` standalone, or `No X yet — [next action].` with a prompt. Period at end. | `No drafts yet — click + New Article to start.` |
| Modal/dialog heading | Present-tense action verb + object. | `Publish article`, `Delete journal entry`. |
| Block sublabel | Descriptor (not full sentence). Period-less. | `Concept · Outline · Draft` |
| Tooltip / `title` attr | Short, present tense, no period. Drop if redundant with visible label. | `Drag to reorder` |
| Flash message | Past-tense success ("Saved.", "Published.", "Renamed."). Imperative for error ("Reload the page and try again."). Single sentence. | `Saved.` · `Mock created.` |
| Stale build-language floor | Zero "Phase N" references in user-facing copy. Zero "ship", "land in", "wires", "fold in", "Phase X brief". Zero TODO-style language ("not yet", "deferred to") except where it correctly describes current limitation as a function. | — |

---

## Rewrites

### Sidebar (partials/sidebar.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 9 | Ideation Board | Ideation | Drop "Board" — UI implementation detail. Matches breadcrumb and view title which already say "Ideation". |

### Pipeline / Draft Writing view (views/pipeline.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 41 | All work in progress and recently shipped — Concept through Recently Published. Capture quickly, develop deliberately. Content moves left to right as it matures. | Everything in flight and recently published — Concept through to live. Content moves left to right as it matures. | Replace "shipped" (build verb) with "published". Tightens the second sentence. |
| 61 | Nothing here yet | No drafts yet | Bare "Nothing here yet" doesn't say what's missing. With three stacked stage columns, "drafts" makes the empty state self-describing. |

### Ideation view (views/ideation.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 69 | Capture raw ideas. Drag a card into a type column to assign it; drag within a column to reorder. | Capture raw ideas. Drag a card into a type column to assign it. Drag within a column to reorder. | Two actions; split the run-on with a period for scannability. |

### Articles list view (views/articles.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 83 | Long-form writing. Create, edit, and ship drafts. Pipeline + transitions land in Phase 7. | Long-form writing. Create, edit, and publish drafts. | Remove stale Phase 7 reference. Replace "ship" (build verb) with "publish" (user verb). Pipeline + transitions exist now — no need to forecast them. |
| 98 | Special tag | Tag | "Special tag" is internal jargon. The column shows Principle / Framework — calling it "Tag" matches the singular field label users actually pick from. |
| 104 | No drafts in progress. Click + New Article to start. | No article drafts yet — click + New Article to start. | Matches the empty-state pattern. "In progress" is redundant with "drafts". |
| 106 | Queued for future publish — cron promotes to Live | Queued — auto-publishes at the scheduled time | "Cron promotes to Live" is implementation language. The user just needs to know it auto-publishes. |
| 109 | Live on /writing/[slug] | Live at /writing/<slug> | Replace [slug] brackets with `<slug>` to match every other slug-template reference in the CMS (article hints, journal hints, etc.). |

### Journals list view (views/journals.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 114 | Short, declarative entries. Each gets a per-category Entry number when published. | Short, declarative entries. Each gets a per-category entry number on first publish. | Sentence-case "entry number" (it's not a proper noun). "On first publish" is more precise — the number doesn't change on re-publish. |
| 134 | No journal drafts. Click + New Journal to start. | No journal drafts yet — click + New Journal to start. | Match empty-state pattern. |
| 136 | Queued for future publish — cron promotes to Live | Queued — auto-publishes at the scheduled time | Apply pattern from articles. |
| 139 | Live on /journal/[slug] | Live at /journal/<slug> | Apply pattern from articles. |

### Live sessions list view (views/live-sessions.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 144 | Talks, workshops, conversations. Past events stay live with a PAST badge. | Talks, workshops, and conversations. Past events stay live with a PAST badge. | Oxford-comma fix. |
| 165 | No drafts. Click + New Session to add one. | No session drafts yet — click + New Session to start. | Match empty-state pattern + verb ("start" matches articles/journals/experiments). |
| 167 | Queued for future publish — cron promotes to Live | Queued — auto-publishes at the scheduled time | Apply pattern. |
| 170 | Live on /live-sessions/[slug] | Live at /live-sessions/<slug> | Apply pattern. |

### Experiments list view (views/experiments.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 175 | Prototypes, custom HTML, and standalone pieces. Three body modes: rich text, HTML body file, full HTML swap. | Prototypes, custom HTML, and standalone pieces. Three body modes: rich text, HTML body file, or full HTML swap. | Add "or" before the third item — reads as a list, not a fragment. |
| 197 | No experiment drafts. Click + New Experiment to start. | No experiment drafts yet — click + New Experiment to start. | Match pattern. |
| 199 | Queued for future publish — cron promotes to Live | Queued — auto-publishes at the scheduled time | Apply pattern. |
| 202 | Live on /experiments/[slug] | Live at /experiments/<slug> | Apply pattern. |

### Pages list view (views/pages.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 207 | Marketing pages live on disk and ship via deploy. The CMS lets you save named mock versions for preview. For header.php / footer.php only, a mock can be published to override the file at runtime on staging. | Marketing pages live as files on disk and update via deploy. Use mocks to save and preview alternate body content without touching the file. For header.php and footer.php only, you can publish a mock to override the file on staging. | "Ship via deploy" is build-internal. Tighten the second sentence to describe function. Replace `/` separator with `and` for natural reading. Drop "at runtime" — implementation detail. |
| 215 | A mock is currently published — overriding the file at runtime | A mock is currently published — overriding the file on staging | "At runtime" is implementation language; "on staging" tells the user the actual scope. |
| 225 | Snapshots of past page versions · preview-only, no public URL | Past page snapshots · preview-only, no public URL | "Snapshots of past page versions" is wordy. "Past page snapshots" is direct. |
| 227 | No archives yet. To archive a page, insert a mock whose name starts with "Archive ". | No archives yet — to archive a page, create a mock whose name starts with "Archive ". | Match empty-state pattern. "Insert" reads like a code term; "create" matches the actual button label. |
| 234 | Mock-only sandbox · files remain canonical | Editable as mocks · the on-disk file stays canonical | "Mock-only sandbox" reads as build-language. Tells the user what they can do (edit as mocks) and what the constraint is (file is source of truth). |
| 238 | Rendered when no route or file matches | Shown when no route or file matches a request | "Rendered" is dev-speak; "shown" reads at user altitude. |
| 242 | Shared header + footer · publish-capable on staging | Shared header and footer · publishable on staging | "+" reads as code. "Publish-capable" is awkward — "publishable" is shorter and clearer. |

### Navigation editor (views/navigation.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 252 | Header and footer link lists. Drag to reorder. Items whose target row no longer resolves are flagged BROKEN and hidden from the public site until you fix them. | Header and footer link lists. Drag to reorder. Items whose target no longer exists are flagged BROKEN and hidden from the public site until you fix them. | "Target row no longer resolves" is database language. "Target no longer exists" is direct and equally accurate. |
| 260 | Top nav rendered above every public page | Top nav shown above every public page | "Rendered" → "shown" (user altitude). |
| 261 | Bottom links rendered in the page footer | Bottom links shown in the page footer | Same. |
| 275 | Delete "<label>"? | Delete "<label>"? This removes the link from the public site. | Add consequence to match confirm-dialog pattern. |

### Indexes list view (views/indexes.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 294 | Configure the public index pages for each section of the site. Editorial Page adds hero + featured + multi-section layout; Basic Listing is title + feed. | Configure the public index pages for each section of the site. Editorial Page adds a hero, featured picks, and multi-section layout. Basic Listing is title plus content feed. | Replace `+` (code-flavoured) with prose. Split the semicolon — two sentences are easier to skim. |
| 307 | Delete this index? The URL /<slug>/ will 404 unless you re-create it. | Delete index "<title>"? The URL /<slug>/ will 404 unless you re-create it. | Name the thing being deleted (matches navigation, redirects, categories, series confirm dialogs). |
| 312 | The four built-in type pages | The four built-in content-type indexes (one per content type) | "Type pages" is ambiguous if you haven't read BLOCKS.md. Spell out that there's one per content type. |
| 314 | Seed missing. Run migration 0007 to restore the four built-in Post Type indexes. | Seed missing. Run migration 0007 to restore the four built-in indexes. | Sentence-case "post type indexes" → drop "Post Type" (matches updated block label above). |
| 316 | Auto-generated from /cms/series — not editable here | Auto-generated from Series — manage them in Collections › Series | Use sidebar nav-path instead of URL fragment; reads as instruction not implementation. |
| 326 | Author-created Editorial Pages and additional Basic Listings | Editorial Pages and additional Basic Listings you've created | "Author-created" is meta-language (we know the user is the author). Re-cast as second person. |
| 328 | No custom indexes yet. Click + New Index to add one (e.g. /digital-garden, /thoughts). | No custom indexes yet — click + New Index to add one (e.g. /digital-garden, /thoughts). | Match empty-state pattern. |

### Index new view (views/index-new.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 332 | Creates a configurable page at a custom URL. Slug is permanent — pick carefully. | Creates a configurable page at a custom URL. The slug becomes the URL and is permanent — pick carefully. | Spell out what "slug is permanent" implies (it's part of the URL); first-time users may not know. |
| 339 | Shown at the top of the index page. Also used to derive the slug if you leave that blank. | Shown at the top of the index page. Also used to derive the slug below if you leave it blank. | "That" is vague; "the slug below" is concrete. |
| 346 | Title, optional description, and a content feed. Best for catch-all section indexes (e.g. /writing, /journal). | Title, optional description, and a content feed. Best for catch-all section indexes (e.g. /writing, /journal/). | Trailing slash matches the actual URL pattern. |
| 348 | Hero feature + curated picks + content feed. Use for curated landing pages (e.g. /digital-garden). | Hero feature, curated picks, and a content feed. Use for curated landing pages (e.g. /digital-garden/). | Replace `+` with prose. Trailing slash. |

### Index edit view (views/index-edit.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 359 | Editorial Page — configure the hero feature, curated picks, and content feed for this page. | Editorial Page · hero feature, curated picks, and content feed. | Tighter; matches the pipe-separated sublabel style already in use in other view subtitles. |
| 360 | Basic Listing — title, optional description, and a content feed. | Basic Listing · title, optional description, and a content feed. | Same pattern. |
| 365 | Flip between the two layout types. Hero + featured config is preserved when switched off. | Switch between the two layout types. Hero and featured settings are preserved when you switch back. | "Flip" is colloquial; "switch" matches the rest of the CMS. Replace `+` with prose. "When switched off" is ambiguous; "when you switch back" tells the user what they get. |
| 388 | The main grid of cards. Type chips are OR — pick any combination. Empty = all types. | The main grid of cards. Type chips are OR'd — pick any combination, or leave empty to include all types. | Spell out the all-types fallback in prose; "Empty = all types" reads as code. |
| 397 | Manual sort is reserved for a later phase — choose Newest or Oldest for now. | Manual sort isn't available yet — choose Newest or Oldest for now. | Drop "reserved for a later phase" (build language). Same meaning, no internal calendar reference. |
| 400 | One row ≈ 4 cards. Beyond the cap, items are simply hidden. | One row is about 4 cards. Items beyond the cap are hidden. | Replace `≈` (math symbol) with prose. Drop "simply" (filler word). |
| 405 | One pill per feed type (Articles · Journals · Talks · Experiments). | One pill per feed type (Articles · Journals · Live Sessions · Experiments). | "Talks" appears once here as a synonym for Live Sessions — confusing. Use the canonical name. |
| 408 | Filtering is client-side — pills hide/show cards without reloading. The "All" pill is rendered automatically. | Pills hide and show cards without reloading the page. The "All" pill is added automatically. | "Client-side" is dev jargon. "Rendered" → "added". |

### Redirects view (views/redirects.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 414 | Map an old URL to a new one. Default is 301 (permanent, browsers cache it). Use 302 when the destination might move — third-party services, A/B tests, or anything not yet stable. | Map an old URL to a new one. 301 is the default (permanent — browsers cache it). Use 302 when the destination might move — third-party links, A/B tests, anything not yet stable. | Tighter phrasing; "third-party services" is jargon — "third-party links" is more concrete. |
| 416 | Could not add. Check for blank fields, duplicate old path, or old = new (would loop). | Could not add. Check for blank fields, a duplicate old-path, or an old-path that equals the new-path (which would loop). | "Old = new" reads as code; spell it out. |
| 424 | From-path → to-path · 301 permanent, 302 temporary | Map old paths to new ones · 301 permanent, 302 temporary | "From-path → to-path" is technical shorthand; reads as developer doc. |
| 429 | Delete redirect "<old_slug>"? | Delete redirect "<old_slug>"? Anyone hitting that URL will get a 404 unless you re-add it. | Add the consequence — matches confirm-dialog pattern. |

### Categories view (views/categories.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 438 | Value slugs are permanent — they're what the database stores. Labels and colours are editable anytime. A category can only be deleted when its usage count is zero. | Value slugs are permanent — they're what the database stores. Labels and colours are editable any time. A category can only be deleted when nothing is using it. | "Usage count is zero" is database-shaped; "nothing is using it" is plain. "Anytime" → "any time" (correct two-word form for adverb phrase here). |
| 446 | Primary category drives colour and card display. Secondary categories expand index inclusion. | Primary category drives colour and card display. Secondary categories add the article to more index pages. | "Expand index inclusion" is internal phrasing; "add to more index pages" is direct. |
| 463 | Delete category "<label>"? | Delete category "<label>"? This can't be undone. | Add consequence (already in use elsewhere). |

### Series view (views/series.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 471 | Ordered groups of articles. Slugs are permanent — set on creation and used in /series/[slug]/ URLs. Phase 12 generates the matching editorial index page. | Ordered groups of articles. Slugs are permanent — set on creation and used in /series/<slug>/ URLs. Each series gets a matching editorial index page automatically. | Drop "Phase 12" reference (build language, and it's already shipped). Replace `[slug]` brackets with `<slug>`. Make the auto-generation fact present-tense. |
| 482 | Launch ↗ | Live ↗ | Match every other "go to public site" button in the CMS (every list view uses Live ↗). |
| 488 | Remove "<title>" from this series? | Remove "<title>" from this series? The article stays — only the series link is removed. | Add consequence so user knows the article isn't being deleted. |
| 494 | Delete series "<name>"? | Delete series "<name>"? This can't be undone. The articles in it are not deleted. | Add consequence. Clarify that articles survive. |

### Subscribers view (views/subscribers.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 507 | Captured from the public newsletter form. Re-subscribers update in place — every row here is a unique email address. | Captured from the public newsletter form. Re-subscribers update the existing row — every email here is unique. | "Update in place" is technical; "update the existing row" is clearer. "Every row is a unique email address" reads awkwardly. |
| 543 | Delete subscriber "<email>"? This cannot be undone. | Delete subscriber "<email>"? This can't be undone. | Standardise contraction across all confirm dialogs (replace "cannot" with "can't" everywhere — see global note in Open questions). |

### Post Templates view (views/post-template.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 548 | Each content type uses a PHP layout file that controls how its fields render on the live site. The Master template lists every available field and its PHP variable — it doesn't turn anything on or off. Each sub-template inherits everything and can suppress specific fields. | Each content type has a PHP layout file that controls how its fields render on the public site. The Master template lists every available field and its PHP variable — it's a reference, not a switchboard. Each sub-template inherits everything and can suppress specific fields. | "Live site" → "public site" (matches the rest of the CMS). "Doesn't turn anything on or off" is awkward; "reference, not a switchboard" is more vivid and shorter. |
| 562 | The Master Template defines every block available across content types. Each block has a stable slug used in code (data-block) and a visibility mode. Always blocks render whenever applicable. Optional blocks are toggled on or off per content type from each sub-template. Auto blocks render based on the data (e.g. Tags renders only when tags exist). To inspect a sub-template's specific visibility, select it from the list on the left. | The Master Template defines every block available across content types. Each block has a stable slug used in code (data-block) and a visibility mode. **Always** blocks render whenever applicable. **Optional** blocks are toggled on or off per content type from each sub-template. **Auto** blocks render based on the data (e.g. Tags renders only when tags exist). To inspect a sub-template's specific visibility, select it from the list on the left. | Bold the three mode names so they read as terms, not adjectives. No content change. |
| 567 | Every database field underlying the blocks. Each row maps a field to its PHP variable. Blocks read these fields to populate themselves — so the field reference is for layout work, the Content Blocks tab is for visibility. | Every database field that backs a block. Each row maps a field to its PHP variable. Use this tab for layout work; use the Content Blocks tab for visibility. | "Underlying the blocks" is awkward; "backs a block" is clearer. Replace the "so… —" connective with a direct contrast — easier to skim. |
| 585 | The master PHP layout file — used as the wrapper for every public-rendered article-family page. Read-only view; edits happen in code (site/templates/master-layout.php) and ship through deploy. | The master PHP layout file — the wrapper for every public article-family page. Read-only here; edit at site/templates/master-layout.php and deploy to ship. | "Ship through deploy" is awkward; "deploy to ship" is what actually happens. |
| 588 | Comprehensive preview — renders master-layout.php wrapping article-standard.php with every block populated. This is the live counterpart to site/_templates/article.html — a single page that exercises the full block inventory. | Full-block preview — renders master-layout.php wrapping article-standard.php with every block populated. This is the live counterpart to site/_templates/article.html. | "Comprehensive" → "Full-block" (says what's comprehensive). Drop the trailing redundant clause. |
| 589 | Master Template Preview — every block populated | Master Template preview · every block populated | Sentence-case "preview" (it's not a proper noun); pipe separator matches other CMS sublabels. |
| 597 | Per-sub-template visibility toggles are read-only in v1.0 (modes shown above are the BLOCKS.md matrix defaults). Editable per-sub-template suppression is deferred to a future phase — see docs/BUILD-PLAN.md §19.5. | Per-sub-template visibility toggles are read-only — the modes shown above are the BLOCKS.md matrix defaults. Editable suppression isn't available yet. | Drop "v1.0", "deferred to a future phase", and the BUILD-PLAN cross-reference (all build-language). The user just needs to know they can't edit it yet. |
| 598 | The PHP layout file for <sub-template name>. Read-only view; edits happen in code (site/templates/<filename>) and ship through deploy. | The PHP layout file for <sub-template name>. Read-only here; edit at site/templates/<filename> and deploy to ship. | Apply the pattern from #585. |
| 600 | <filename> not found in `site/templates/`. | <filename> not found in site/templates/. Check the deploy. | Add the same actionable note as #587 (master-layout's parallel error already has it). |
| 601 | Likely folded into article-standard.php via a conditional, or pending creation. Flagged in Phase 14.5 brief. | Likely folded into article-standard.php via a conditional, or not yet created. | Drop "Phase 14.5 brief" (build language). "Pending creation" → "not yet created" (clearer). |
| 602 | Live preview of <sub-template name> — renders site/templates/<filename> against synthetic content. Edits to the template file show up here immediately. | Live preview of <sub-template name> — renders site/templates/<filename> against sample content. Edits to the template file show up here immediately. | "Synthetic content" is technical; "sample content" is clearer and equally accurate. |
| 604 | Experiment-html bypasses master-layout in production and readfile()s a folder we don't have in preview — so the chrome shown here is experiment.php's. | Experiment-html bypasses master-layout in production and serves a folder we don't have in preview — so the chrome shown here is experiment.php's. | `readfile()` is implementation language. "Serves" reads at user altitude. |
| 605 | Preview — <sub-template name> | Preview · <sub-template name> | Use the consistent pipe separator (matches #589). |

### Article edit view (views/article-edit.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 634 | Article · <stageLabel> · last saved <updated_at> | Article · <stageLabel> · saved <updated_at> | "Last saved" is redundant — it's always the last save. |
| 657 | Required before advancing. You can also drag the card into a type column in Ideation. | Required before you can advance. You can also assign a type by dragging the card in Ideation. | Direct second-person; "advancing" alone is ambiguous about what advances. |
| 670 | Warning: This article is published. Changing the slug will create a 301 redirect from the old URL in Phase 11. | Warning: this article is published. Changing the slug will create a 301 redirect from the old URL. | Drop Phase 11 reference (it's shipped). Sentence-case after the colon. |
| 706 | The editor strips any HTML outside the toolbar allowlist on save. | Any HTML outside the toolbar's allowlist is stripped on save. | Cleaner — passive voice here puts the consequence first. |
| 710 | No folder exists yet for this slug. Click Set up folder to create <path> on the server. Then drop your .html file into it via SSH/CloudMounter and click Refresh. | No folder exists yet for this slug. Click "Set up folder" to create <path> on the server. Drop your .html file into it via SSH or CloudMounter, then click Refresh. | Quote the button label so it reads as a UI reference. Split the run-on. "/" between SSH and CloudMounter is code-style; use "or". |
| 715 | The article chrome (breadcrumb, title, byline, hero, tags) stays as edited above. Only the body slot is replaced by the contents of the selected file. The file's HTML inherits the public .article-prose typography rules. | The article chrome (breadcrumb, title, byline, hero, tags) stays as edited above. Only the body slot is replaced by the file. The file's HTML inherits the public .article-prose typography rules. | "Replaced by the contents of the selected file" is wordy; "replaced by the file" suffices given the dropdown context. |
| 729 | Drives card colour on /writing/ and the breadcrumb. Manage at /cms/categories. | Drives card colour on /writing/ and the breadcrumb. Manage in Collections › Categories. | Use sidebar nav-path instead of a URL — reads as navigation, not implementation. |
| 738 | Manage the list at /cms/series. | Manage the list in Collections › Series. | Same pattern. |
| 742 | Auto-assigned. Drag-reorder parts at /cms/series. | Auto-assigned. Re-order parts in Collections › Series (drag handles). | Same pattern, and "drag-reorder" reads as a compound verb that doesn't exist. |
| 746 | Display only — not used for filtering yet. | Display only — not used for filtering. | Drop "yet" (the filtering use was scoped out, not deferred). Apply to all four edit views (articles, journals, live-sessions, experiments). |
| 748 | minutes / minutes · set at Draft | minutes · set at Draft stage | "Minutes / minutes" is a JS singular/plural hint that surfaces here as duplicate text. Just say "minutes". |
| 760 | Must be at least one minute in the future. The cron promotes scheduled rows to Live at this time. | Must be at least one minute in the future. The system auto-publishes scheduled entries at this time. | "Cron promotes scheduled rows to Live" is database/sysadmin language; "auto-publishes scheduled entries" is plain. Apply to all four edit views. |
| 772 | Move this article back to draft? It will be removed from the public site immediately. | Move this article back to draft? It is removed from the public site immediately. | Tense agreement — drop the future "will" so it reads as a present-tense consequence (matches the publish-now confirm). |
| 773 | Deleting a published article is permanent.\n\nType the slug to confirm:\n\n  <slug> | Deleting a published article is permanent.\n\nType the slug exactly to confirm:\n\n  <slug> | Add "exactly" — makes it obvious that whitespace/case must match. |

### Article new view (views/article-new.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 801 | Lowercase letters, numbers, hyphens. The slug becomes part of the public URL (/writing/<slug>) and is permanent once published. | Lowercase letters, numbers, and hyphens. Becomes part of /writing/<slug> and is permanent once published. | Tighter; "the public URL" is redundant when you've shown the URL pattern. Apply matching brevity in journal-new and experiment-new where the variant has more words. |

### Journal edit view (views/journal-edit.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 822 | Journal · <stageLabel> · Entry <entry#> · last saved <updated_at> | Journal · <stageLabel> · Entry <entry#> · saved <updated_at> | Drop "last" (see #634). |
| 829 | Preview — Journal entry | Preview · Journal entry | Pipe separator (consistent). |
| 837 | Warning: Changing the slug on a published journal will create a 301 redirect (Phase 11). | Warning: changing the slug on a published journal will create a 301 redirect. | Drop Phase 11 reference (shipped). Sentence-case after colon. |
| 857 | Key Statement alone is enough — body is for expansion. | The Key Statement alone is enough — the body is optional, for expansion. | Adds articles; clarifies that body is optional. |
| 864 | Display only — not used for filtering yet. | Display only — not used for filtering. | Pattern from #746. |
| 872 | Show "Updated" date on the article | Show "Updated" date on the journal entry | The hint says "on the article" inside the journal editor — wrong noun. Same fix needed in live-session-edit (#1016) and experiment-edit (#1140). |
| 878 | Must be at least one minute in the future. The cron promotes scheduled rows to Live at this time. | Must be at least one minute in the future. The system auto-publishes scheduled entries at this time. | Pattern from #760. |
| 889 | Move this journal back to draft? It will be removed from the public site immediately. | Move this journal back to draft? It is removed from the public site immediately. | Pattern from #772. |
| 890 | Deleting a published journal is permanent.\n\nType the slug to confirm:\n\n  <slug> | Deleting a published journal is permanent.\n\nType the slug exactly to confirm:\n\n  <slug> | Pattern from #773. |

### Journal new view (views/journal-new.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 899 | Draft created — write your Key Statement. | Draft created — write the Key Statement next. | "Your" is fine but "the Key Statement next" reads more like a system prompt. |
| 902 | Set a working title and slug. The Key Statement and body are on the next screen. | Set a working title and slug. Write the Key Statement and body on the next screen. | More imperative; matches the article-new subtitle voice. |

### Live session edit view (views/live-session-edit.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 940 | Live Session · <stageLabel>(· PAST) · last saved <updated_at> | Live Session · <stageLabel>(· PAST) · saved <updated_at> | Drop "last" (see #634). |
| 950 | Preview — Live session | Preview · Live session | Pipe separator. |
| 958 | Warning: Changing the slug on a published live-session will create a 301 redirect (Phase 11). | Warning: changing the slug on a published live session will create a 301 redirect. | Drop Phase 11 + hyphen in "live-session" (the canonical term is unhyphenated). Sentence-case after colon. |
| 968 | date required · times optional · Eastern (Toronto) timezone | date required · times optional · Toronto (ET) timezone | The label name first, abbreviation in parens — matches how dates are read in headers and reduces ambiguity ("Eastern" is regional for non-NA audiences but redundant here since Toronto IS Eastern). |
| 979 | Publish Date is separate — that's stamped when the session goes live. Past events stay live with a PAST badge. | Publish Date is separate — it's stamped when the session is published. Past events stay live with a PAST badge. | "That's stamped when the session goes live" is awkward (live = state, publish = action); "stamped when the session is published" is precise. |
| 1004 | JPEG, PNG, WebP, GIF · max 5 MB. | JPEG, PNG, WebP, or GIF · max 5 MB | Add "or" before last item; drop trailing period (pattern with other hints). |
| 1011 | Display only — not used for filtering yet. | Display only — not used for filtering. | Pattern. |
| 1016 | Show "Updated" date on the article | Show "Updated" date on the live session | Wrong noun (see #872). |
| 1022 | Must be at least one minute in the future. The cron promotes scheduled rows to Live at this time. | Must be at least one minute in the future. The system auto-publishes scheduled entries at this time. | Pattern. |
| 1033 | Move this session back to draft? It will be removed from the public site immediately. | Move this live session back to draft? It is removed from the public site immediately. | Match the noun the user sees in the rest of the UI ("session" alone is ambiguous now that "Session" is also the security/login term). Pattern from #772. |
| 1034 | Deleting a published live session is permanent.\n\nType the slug to confirm:\n\n  <slug> | Deleting a published live session is permanent.\n\nType the slug exactly to confirm:\n\n  <slug> | Pattern. |

### Live session new view (views/live-session-new.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 1050 | e.g. "Designing for Human Agency". Shown on the public page and in /live-sessions listings. | e.g. "Designing for Human Agency". Shown on the public page and in /live-sessions/ listings. | Trailing slash matches the actual route. |

### Experiment edit view (views/experiment-edit.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 1082 | Experiment · <template> · <stageLabel> · last saved <updated_at> | Experiment · <template> · <stageLabel> · saved <updated_at> | Drop "last". |
| 1091 | Preview — Experiment | Preview · Experiment | Pipe separator. |
| 1099 | Warning: Changing the slug on a published experiment will create a 301 redirect (Phase 11). | Warning: changing the slug on a published experiment will create a 301 redirect. | Drop Phase 11. Sentence-case. |
| 1114 | Article-format body. Strips HTML outside the toolbar allowlist on save. | Article-format body. Any HTML outside the toolbar's allowlist is stripped on save. | Match #706. |
| 1116 | Full-page passthrough — readfile() serves at /experiments/<slug> with no template wrapper. | Full-page passthrough — the file is served directly at /experiments/<slug>/ with no template wrapper. | `readfile()` is implementation; "served directly" is plain. Add trailing slash. |
| 1120 | No folder exists yet for this slug. Click Set up folder to create <path> on the server. Then drop your .html files into it via SSH/CloudMounter and click Refresh. | No folder exists yet for this slug. Click "Set up folder" to create <path> on the server. Drop your .html files into it via SSH or CloudMounter, then click Refresh. | Pattern from #710. |
| 1127 | Drives card colour on /experiments/ (Prototype vs Concept dark variant). | Drives card colour on /experiments/. The Concept category renders with the dark variant. | "Prototype vs Concept dark variant" is too compressed — splits two facts into one. |
| 1135 | Display only — not used for filtering yet. | Display only — not used for filtering. | Pattern. |
| 1140 | Show "Updated" date on the article | Show "Updated" date on the experiment | Wrong noun (#872). |
| 1146 | Must be at least one minute in the future. The cron promotes scheduled rows to Live at this time. | Must be at least one minute in the future. The system auto-publishes scheduled entries at this time. | Pattern. |
| 1157 | Move this experiment back to draft? It will be removed from the public site immediately. | Move this experiment back to draft? It is removed from the public site immediately. | Pattern. |
| 1158 | Deleting a published experiment is permanent.\n\nType the slug to confirm:\n\n  <slug> | Deleting a published experiment is permanent.\n\nType the slug exactly to confirm:\n\n  <slug> | Pattern. |

### Experiment new view (views/experiment-new.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 1177 | Rich text uses the TipTap editor (same blocks as Articles). HTML body keeps the article chrome and replaces the body slot with a hand-built file from /content/experiment/<slug>/. HTML swap serves the file directly with no template wrapper. All three are switchable later from the edit screen. | Rich text uses the TipTap editor (same blocks as Articles). HTML body keeps the article chrome and replaces the body slot with a hand-built file from /content/experiment/<slug>/. HTML swap serves the file directly with no template wrapper. You can switch between modes later from the edit screen. | Last sentence reads as system documentation; second-person makes it instructional. |
| 1180 | e.g. "Decision scaffolding tool". Shown in /experiments and on the public page. | e.g. "Decision scaffolding tool". Shown in /experiments/ and on the public page. | Trailing slash. |

### Page edit view (views/page-edit.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 1207 | Layout partial. Mocks can be published to override the file on staging — file remains canonical until you publish. | Layout partial. Publish a mock to override the file on staging — the file stays canonical until you do. | More active. "Until you publish" → "until you do" (avoids the awkward repetition of "publish"). |
| 1208 | Marketing page. Mock-only sandbox: the CMS never writes to disk. Files remain canonical. | Marketing page. Edit as mocks for preview — the CMS never writes to disk, so the file stays canonical. | "Mock-only sandbox" is internal language; replace with what the user can do and why. |
| 1220 | Revert to file? This un-publishes all mocks for this slug. | Revert to file? This un-publishes the active mock and falls back to the on-disk file. | "All mocks for this slug" is misleading (only one mock can be published at a time). Reword to match what actually happens. |
| 1226 | Delete mock "<name>"? This cannot be undone. | Delete mock "<name>"? This can't be undone. | Standardise contraction. |
| 1229 | Publish "<name>"? This will override <filename> on staging. | Publish "<name>"? This overrides <filename> on staging until you un-publish or revert. | Tense; clarify the duration. |
| 1232 | This is the on-disk file. The CMS never writes here — click + New Mock to start editing. | This is the on-disk file. The CMS never writes here — click "+ New Mock" to start editing. | Quote the button label so it reads as a UI reference. |
| 1233 | Preview — <filename> | Preview · <filename> | Pipe separator. |
| 1245 | Name this mock (e.g. "Tighter intro"): | Name this mock (e.g. "Tighter intro") | Drop trailing colon — JS prompt() already adds visual separation. |

### Auth views (login.php, account.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 1263 | Locked out after too many bad guesses? Clear it and try again. Staging only — the prod login never shows this button. | Locked out after too many bad guesses? Clear the lock and try again. Staging only — the production login never shows this button. | "Clear it" is vague (clear what?); "clear the lock" is concrete. "Prod" → "production" (full word; this is user-facing copy, not internal). |
| 1277 | At least 12 characters, with one uppercase, one lowercase, and one digit. | At least 12 characters, with at least one uppercase letter, one lowercase letter, and one digit. | Add "letter" — "one uppercase" alone reads as ambiguous units. Adding "at least" mirrors the leading requirement. |

### Cross-cutting / partials (partials/table.php)

| # | Current | Recommended | Reason |
|---|---|---|---|
| 1279 | No entries yet. | No entries yet. | Kept as-is — already matches the chosen "entry" vocabulary and empty-state pattern. (Listed only to confirm decision; no rewrite needed.) |

### Global pattern: "cannot be undone" → "can't be undone"

Apply to all confirm dialogs that use the phrase. Locations: views/articles.php:205, views/journals.php:175, views/live-sessions.php:240, views/experiments.php:180, views/article-edit.php:1589, views/journal-edit.php:761, views/live-session-edit.php:1013, views/experiment-edit.php:960, views/subscribers.php:276, views/page-edit.php:369. Contraction matches the voice of every other CMS string ("don't", "isn't", "won't").

### Global pattern: Stale Phase-number references

Confirmed scrubbed (covered in per-section rewrites above): #83 (Phase 7), #471 (Phase 12), #597 (v1.0 + §19.5), #601 (Phase 14.5), #670, #837, #958, #1099 (Phase 11 × 4). No further "Phase N" references remain in the inventory.

---

## Open questions

These either need an author decision or are too borderline for the audit pass to commit to without confirmation.

| # | Current | Question |
|---|---|---|
| Sidebar items 2/4/6/28 (Dashboard, Analytics, Post History, Settings) | All show tooltip "Coming soon" | Are these placeholders staying as visible nav items? If yes, "Coming soon" is fine. If they should be hidden until built, that's a behaviour change (out of scope for this audit) but worth flagging. |
| 32–34 Topbar logo | "alexmchong" + italic "cms" + status pill "staging" | The pill says "staging" on the staging environment. Confirm: on production, is the pill hidden entirely, or replaced with something else (e.g. "prod")? If the latter, the prod label needs a copy decision. |
| 76 (Ideation), 85/116/146/177 (list New buttons) | Mixed: "+ Add", "+ New Article", "+ New Journal", "+ New Session", "+ New Experiment" | The four list-view buttons say "+ New <Type>"; ideation's inline-create says "+ Add". Should we align ideation to "+ Add idea" so the verb is consistent (always specifying object)? Lean: yes, "+ Add idea" — but flagging because it's a tiny UI shift not strictly a copy fix. |
| 84/115/145/176 | "View Index ↗" appears next to "+ New <Type>" on every list view | Strong: this button takes the author to the live index page for that type. Should it be labelled "View live index ↗" to disambiguate from the CMS Indexes page (which is a different surface)? Lean: yes — author confusion risk between Library › Articles and Collections › Indexes. |
| 146 | + New Session | Sidebar and list view say "Live Sessions" — should this button say "+ New Live Session" for consistency? Lean: yes, but it's longer. |
| 211 (Pages filter pills "All / Archives") | The All pill is unlabelled in the inventory but the filter pattern elsewhere uses category + content-type filtering | Confirm that "Archives" filter is the only non-All filter on Pages. If more filters land, this label set will need re-audit. |
| 246–248 / 1249–1251 | "Nm ago", "Nh ago", "Nd ago" | Standard relative-time format. Keep, or change "m" / "h" / "d" to "min" / "hr" / "d" for legibility? Lean: keep — single-letter units match common conventions (GitHub, Linear, etc.). Flagging only because Alex may have a preference. |
| 638 / 893 / 945 / 1086 | Mixed "Back to <list>" / "Back to list" / "Back to Ideation" / "Back to Draft Writing" | The back-button cascade is dynamic based on referrer. Generally good. One concern: "Back to Draft Writing" — is the Draft Writing view still called that, or did the rename to "Pipeline / Draft Writing" happen? Confirm canonical name to use in the back button. |
| 657 | "Required before advancing. You can also drag the card into a type column in Ideation." | This appears on a Type select that's required to leave the Idea stage. Lean to keeping the recommendation rewrite, but flagging because some authors prefer dragging vs the type dropdown and the second sentence may be unnecessary noise for daily use. |
| 671 | "Lowercase letters, numbers, hyphens. Becomes part of /writing/<slug>." | Currently has no period after "hyphens" but does after `<slug>`. Should every list-of-allowed-chars hint use the same punctuation? Lean: yes, use "Lowercase letters, numbers, and hyphens." (oxford comma + period) everywhere. |
| 750 / 868 / 1012 / 1136 | "Live" indicator badge on edit views | Currently just the word "Live". On a published-and-live entry, this is the visible state. Consider whether to add a green dot or keep text-only. (Out of copy-audit scope but flagging since the design system may have a published-state token unused here.) |
| 1245 / 1246 / 1247 | Browser-native `prompt()` dialogs for naming mocks ("Name this mock…", "Rename to:", "Name the duplicate:") | These are JS prompts, capped by the browser's prompt() UI. The current strings are functionally clear. Flagging because the entire prompt() pattern is a UX weak spot — a real modal would let us improve the copy more substantially, but that's out of scope. |
| Pipeline view #58/59/60 "This Week / Next Week / Future" | "Future" is broad — does it include weeks 3+, or specifically "more than 2 weeks out"? | Copy is fine if the bucket definition matches user intuition. If "Future" actually means "next 30 days" or similar, consider a more precise label. Flagging for author confirmation. |
| 597 | "Per-sub-template visibility toggles are read-only — the modes shown above are the BLOCKS.md matrix defaults." | The rewrite drops the BUILD-PLAN reference but keeps "BLOCKS.md" (a developer doc). Should this also drop "BLOCKS.md" and just say "the matrix defaults"? Lean: yes — most surfaces shouldn't name docs at all. |
