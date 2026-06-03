# COPY-ACCEPTANCE

Generated from COPY-AUDIT.html. Source: docs/COPY-AUDIT.md.

## Vocabulary decisions

| Decision | Status | Note |
|---|---|---|
| Generic noun for "a row in the content table" | accept |  |
| "Journal" vs "Journal entry" | accept |  |
| "Live Session" vs "Live event" | accept |  |
| Action verbs | accept |  |
| "Mock" vs "version" vs "preview" (Pages CMS) | accept |  |
| "Card" vs "row" vs "item" | accept |  |
| "Published" vs "Live" | accept |  |
| "Stage" vs "Status" | accept |  |
| "Idea Notes" capitalization | accept |  |
| "Key Statement" capitalization | accept |  |
| "Schedule for Publish" | accept |  |

## Pattern decisions

| Pattern | Status | Note |
|---|---|---|
| View subtitle | accept |  |
| Field hint (below input) | accept |  |
| Field hint inline (next to label) | accept |  |
| Field placeholder | accept |  |
| Button label | accept |  |
| Confirm dialog | accept |  |
| Empty state | alternative | "this looks like a math operator, so if it refers to a button then click [+New Article] to start." |
| Modal/dialog heading | accept |  |
| Block sublabel | accept |  |
| Tooltip / `title` attr | accept |  |
| Flash message | accept |  |
| Stale build-language floor | accept |  |

## Rewrites

Ordered by impact score (highest first). Apply in this order to land the biggest wins first.

| Impact | # | Location | Decision | Final text |
|---|---|---|---|---|
| 22 | 429 | views/redirects.php:200 | accept | Delete redirect "<old_slug>"? Anyone hitting that URL will get a 404 unless you re-add it. |
| 21 | 597 | views/post-template.php:390 | accept | Per-sub-template visibility toggles are read-only — the modes shown above are the BLOCKS.md matrix defaults. Editable suppression isn't available yet. |
| 20 | 275 | views/navigation.php:343 | accept | Delete "<label>"? This removes the link from the public site. |
| 18 | 83 | views/articles.php:89 | accept | Long-form writing. Create, edit, and publish drafts. |
| 18 | 601 | views/post-template.php:406 | accept | Likely folded into article-standard.php via a conditional, or not yet created. |
| 16 | 494 | views/series.php:259 | accept | Delete series "<name>"? This can't be undone. The articles in it are not deleted. |
| 16 | 488 | views/series.php:226 | accept | Remove "<title>" from this series? The article stays — only the series link is removed. |
| 16 | 471 | views/series.php:163 | accept | Ordered groups of articles. Slugs are permanent — set on creation and used in /series/<slug>/ URLs. Each series gets a matching editorial index page automatically. |
| 16 | 1157 | views/experiment-edit.php:939 | reject | (keep current) |
| 16 | 889 | views/journal-edit.php:740 | reject | (keep current) |
| 16 | 1033 | views/live-session-edit.php:992 | alternative | Move this live session back to draft? It will be removed from the public site immediately. |
| 13 | 463 | views/categories.php:227 | accept | Delete category "<label>"? This can't be undone. |
| 11 | 772 | views/article-edit.php:1565 | reject | (keep current) |
| 11 | 307 | views/indexes.php:146 | accept | Delete index "<title>"? The URL /<slug>/ will 404 unless you re-create it. |
| 11 | 706 | views/article-edit.php:1042 | accept | Any HTML outside the toolbar's allowlist is stripped on save. |
| 11 | 1226 | views/page-edit.php:369 | accept | Delete mock "<name>"? This can't be undone. |
| 11 | 543 | views/subscribers.php:276 | accept | Delete subscriber "<email>"? This can't be undone. |
| 10 | 746 | views/article-edit.php:1249 | accept | Display only — not used for filtering. |
| 9 | 41 | views/pipeline.php:259 | accept | Everything in flight and recently published — Concept through to live. Content moves left to right as it matures. |
| 9 | 165 | views/live-sessions.php:273 | alternative | No live session drafts yet — click [+ New Session] to start. |
| 9 | 106 | views/articles.php:253 | accept | Queued — auto-publishes at the scheduled time |
| 9 | 199 | views/experiments.php:222 | accept | Queued — auto-publishes at the scheduled time |
| 9 | 136 | views/journals.php:217 | accept | Queued — auto-publishes at the scheduled time |
| 9 | 167 | views/live-sessions.php:283 | accept | Queued — auto-publishes at the scheduled time |
| 9 | 760 | views/article-edit.php:1338 | accept | Must be at least one minute in the future. The system auto-publishes scheduled entries at this time. |
| 9 | 1146 | views/experiment-edit.php:832 | accept | Must be at least one minute in the future. The system auto-publishes scheduled entries at this time. |
| 9 | 878 | views/journal-edit.php:663 | accept | Must be at least one minute in the future. The system auto-publishes scheduled entries at this time. |
| 9 | 1022 | views/live-session-edit.php:917 | accept | Must be at least one minute in the future. The system auto-publishes scheduled entries at this time. |
| 8 | 359 | views/index-edit.php:201 | alternative | Editorial Page · configure the hero feature, curated picks, and content feed for this page. |
| 8 | 670 | views/article-edit.php:940 | accept | Warning: this article is published. Changing the slug will create a 301 redirect from the old URL. |
| 8 | 1099 | views/experiment-edit.php:590 | accept | Warning: changing the slug on a published experiment will create a 301 redirect. |
| 8 | 837 | views/journal-edit.php:512 | accept | Warning: changing the slug on a published journal will create a 301 redirect. |
| 8 | 958 | views/live-session-edit.php:603 | accept | Warning: changing the slug on a published live session will create a 301 redirect. |
| 8 | 197 | views/experiments.php:212 | alternative | No experiment drafts yet — click [+ New Experiment] to start. |
| 8 | 134 | views/journals.php:207 | alternative | No journal drafts yet — click [+ New Journal] to start. |
| 8 | 710 | views/article-edit.php:1063 | accept | No folder exists yet for this slug. Click "Set up folder" to create <path> on the server. Drop your .html file into it via SSH or CloudMounter, then click Refresh. |
| 8 | 1120 | views/experiment-edit.php:696 | accept | No folder exists yet for this slug. Click "Set up folder" to create <path> on the server. Drop your .html files into it via SSH or CloudMounter, then click Refresh. |
| 8 | 104 | views/articles.php:243 | alternative | No article drafts yet — click [+ New Article] to start. |
| 8 | 328 | views/indexes.php:239 | alternative | No custom indexes yet — click [+ New Index] to add one (e.g. /digital-garden, /thoughts). |
| 8 | 227 | views/pages.php:212 | accept | No archives yet — to archive a page, create a mock whose name starts with "Archive ". |
| 8 | 1279 | partials/table.php:27 | accept | No entries yet. |
| 7 | 801 | views/article-new.php:155 | accept | Lowercase letters, numbers, and hyphens. Becomes part of /writing/<slug> and is permanent once published. |
| 7 | 294 | views/indexes.php:97 | accept | Configure the public index pages for each section of the site. Editorial Page adds a hero, featured picks, and multi-section layout. Basic Listing is title plus content feed. |
| 7 | 742 | views/article-edit.php:1224 | accept | Auto-assigned. Re-order parts in Collections › Series (drag handles). |
| 7 | 598 | views/post-template.php:397 | accept | The PHP layout file for <sub-template name>. Read-only here; edit at site/templates/<filename> and deploy to ship. |
| 7 | 872 | views/journal-edit.php:625 | accept | Show "Updated" date on the journal entry |
| 6 | 567 | views/post-template.php:247 | accept | Every database field that backs a block. Each row maps a field to its PHP variable. Use this tab for layout work; use the Content Blocks tab for visibility. |
| 6 | 408 | views/index-edit.php:404 | accept | Pills hide and show cards without reloading the page. The "All" pill is added automatically. |
| 6 | 1208 | views/page-edit.php:303 | accept | Marketing page. Edit as mocks for preview — the CMS never writes to disk, so the file stays canonical. |
| 6 | 397 | views/index-edit.php:369 | accept | Manual sort isn't available yet — choose Newest or Oldest for now. |
| 6 | 234 | views/pages.php:232 | accept | Editable as mocks · the on-disk file stays canonical |
| 6 | 902 | views/journal-new.php:100 | accept | Set a working title and slug. Write the Key Statement and body on the next screen. |
| 5 | 588 | views/post-template.php:334 | accept | Full-block preview — renders master-layout.php wrapping article-standard.php with every block populated. This is the live counterpart to site/_templates/article.html. |
| 5 | 1220 | views/page-edit.php:357 | accept | Revert to file? This un-publishes the active mock and falls back to the on-disk file. |
| 5 | 738 | views/article-edit.php:1209 | accept | Manage the list in Collections › Series. |
| 5 | 1158 | views/experiment-edit.php:949 | accept | Deleting a published experiment is permanent.\n\nType the slug exactly to confirm:\n\n  <slug> |
| 5 | 890 | views/journal-edit.php:750 | accept | Deleting a published journal is permanent.\n\nType the slug exactly to confirm:\n\n  <slug> |
| 5 | 1034 | views/live-session-edit.php:1002 | accept | Deleting a published live session is permanent.\n\nType the slug exactly to confirm:\n\n  <slug> |
| 5 | 238 | views/pages.php:248 | accept | Shown when no route or file matches a request |
| 5 | 252 | views/navigation.php:255 | accept | Header and footer link lists. Drag to reorder. Items whose target no longer exists are flagged BROKEN and hidden from the public site until you fix them. |
| 5 | 424 | views/redirects.php:180 | accept | Map old paths to new ones · 301 permanent, 302 temporary |
| 5 | 604 | views/post-template.php:419 | accept | Experiment-html bypasses master-layout in production and serves a folder we don't have in preview — so the chrome shown here is experiment.php's. |
| 5 | 1135 | views/experiment-edit.php:767 | accept | Display only — not used for filtering. |
| 5 | 864 | views/journal-edit.php:590 | accept | Display only — not used for filtering. |
| 5 | 1011 | views/live-session-edit.php:852 | accept | Display only — not used for filtering. |
| 5 | 202 | views/experiments.php:239 | accept | Live at /experiments/<slug> |
| 5 | 360 | views/index-edit.php:202 | accept | Basic Listing · title, optional description, and a content feed. |
| 5 | 139 | views/journals.php:234 | accept | Live at /journal/<slug> |
| 5 | 170 | views/live-sessions.php:301 | accept | Live at /live-sessions/<slug> |
| 5 | 215 | views/pages.php:87 | accept | A mock is currently published — overriding the file on staging |
| 4 | 1004 | views/live-session-edit.php:828 | accept | JPEG, PNG, WebP, or GIF · max 5 MB |
| 3 | 585 | views/post-template.php:321 | accept | The master PHP layout file — the wrapper for every public article-family page. Read-only here; edit at site/templates/master-layout.php and deploy to ship. |
| 3 | 416 | views/redirects.php:44 | accept | Could not add. Check for blank fields, a duplicate old-path, or an old-path that equals the new-path (which would loop). |
| 3 | 312 | views/indexes.php:170 | accept | The four built-in content-type indexes (one per content type) |
| 3 | 98 | views/articles.php:142 | reject | (keep current) |
| 3 | 61 | views/pipeline.php:295 | accept | No drafts yet |
| 2 | 1229 | views/page-edit.php:386 | accept | Publish "<name>"? This overrides <filename> on staging until you un-publish or revert. |
| 2 | 207 | views/pages.php:147 | accept | Marketing pages live as files on disk and update via deploy. Use mocks to save and preview alternate body content without touching the file. For header.php and footer.php only, you can publish a mock to override the file on staging. |
| 2 | 715 | views/article-edit.php:1086 | accept | The article chrome (breadcrumb, title, byline, hero, tags) stays as edited above. Only the body slot is replaced by the file. The file's HTML inherits the public .article-prose typography rules. |
| 2 | 332 | views/index-new.php:97 | accept | Creates a configurable page at a custom URL. The slug becomes the URL and is permanent — pick carefully. |
| 2 | 1277 | account.php:83 | accept | At least 12 characters, with at least one uppercase letter, one lowercase letter, and one digit. |
| 2 | 388 | views/index-edit.php:336 | accept | The main grid of cards. Type chips are OR'd — pick any combination, or leave empty to include all types. |
| 2 | 748 | views/article-edit.php:1255 | accept | minutes · set at Draft stage |
| 2 | 346 | views/index-new.php:141 | accept | Title, optional description, and a content feed. Best for catch-all section indexes (e.g. /writing, /journal/). |
| 2 | 109 | views/articles.php:271 | accept | Live at /writing/<slug> |
| 1 | 857 | views/journal-edit.php:563 | accept | The Key Statement alone is enough — the body is optional, for expansion. |
| 1 | 1127 | views/experiment-edit.php:736 | accept | Drives card colour on /experiments/. The Concept category renders with the dark variant. |
| 1 | 600 | views/post-template.php:404 | accept | <filename> not found in site/templates/. Check the deploy. |
| 1 | 446 | views/categories.php:113 | accept | Primary category drives colour and card display. Secondary categories add the article to more index pages. |
| 1 | 316 | views/indexes.php:186 | accept | Auto-generated from Series — manage them in Collections › Series |
| 1 | 1263 | login.php:105 | accept | Locked out after too many bad guesses? Clear the lock and try again. Staging only — the production login never shows this button. |
| 1 | 225 | views/pages.php:204 | accept | Past page snapshots · preview-only, no public URL |
| 1 | 562 | views/post-template.php:220 | accept | The Master Template defines every block available across content types. Each block has a stable slug used in code (data-block) and a visibility mode. **Always** blocks render whenever applicable. **Optional** blocks are toggled on or off per content type from each sub-template. **Auto** blocks render based on the data (e.g. Tags renders only when tags exist). To inspect a sub-template's specific visibility, select it from the list on the left. |
| 1 | 1114 | views/experiment-edit.php:670 | accept | Article-format body. Any HTML outside the toolbar's allowlist is stripped on save. |
| 1 | 1207 | views/page-edit.php:302 | accept | Layout partial. Publish a mock to override the file on staging — the file stays canonical until you do. |
| 1 | 365 | views/index-edit.php:233 | accept | Switch between the two layout types. Hero and featured settings are preserved when you switch back. |
| 1 | 314 | views/indexes.php:176 | accept | Seed missing. Run migration 0007 to restore the four built-in indexes. |
| 0 | 729 | views/article-edit.php:1182 | accept | Drives card colour on /writing/ and the breadcrumb. Manage in Collections › Categories. |
| 0 | 773 | views/article-edit.php:1578 | accept | Deleting a published article is permanent.\n\nType the slug exactly to confirm:\n\n  <slug> |
| 0 | 657 | views/article-edit.php:853 | accept | Required before you can advance. You can also assign a type by dragging the card in Ideation. |
| 0 | 405 | views/index-edit.php:392 | accept | One pill per feed type (Articles · Journals · Live Sessions · Experiments). |
| 0 | 9 | partials/sidebar.php:60 | reject | (keep current) |
| 0 | 507 | views/subscribers.php:168 | accept | Captured from the public newsletter form. Re-subscribers update the existing row — every email here is unique. |
| 0 | 634 | views/article-edit.php:719 | accept | Article · <stageLabel> · saved <updated_at> |
| 0 | 1082 | views/experiment-edit.php:470 | accept | Experiment · <template> · <stageLabel> · saved <updated_at> |
| 0 | 822 | views/journal-edit.php:393 | accept | Journal · <stageLabel> · Entry <entry#> · saved <updated_at> |
| 0 | 940 | views/live-session-edit.php:483 | accept | Live Session · <stageLabel>(· PAST) · saved <updated_at> |
| 0 | 968 | views/live-session-edit.php:642 | accept | date required · times optional · Toronto (ET) timezone |
| 0 | 1016 | views/live-session-edit.php:879 | accept | Show "Updated" date on the live session |
| 0 | 1177 | views/experiment-new.php:141 | accept | Rich text uses the TipTap editor (same blocks as Articles). HTML body keeps the article chrome and replaces the body slot with a hand-built file from /content/experiment/<slug>/. HTML swap serves the file directly with no template wrapper. You can switch between modes later from the edit screen. |
| 0 | 339 | views/index-new.php:121 | accept | Shown at the top of the index page. Also used to derive the slug below if you leave it blank. |
| 0 | 899 | views/journal-new.php:52 | accept | Draft created — write the Key Statement next. |
| 0 | 144 | views/live-sessions.php:83 | accept | Talks, workshops, and conversations. Past events stay live with a PAST badge. |
| 0 | 438 | views/categories.php:157 | accept | Value slugs are permanent — they're what the database stores. Labels and colours are editable any time. A category can only be deleted when nothing is using it. |
| 0 | 1140 | views/experiment-edit.php:794 | accept | Show "Updated" date on the experiment |
| 0 | 175 | views/experiments.php:83 | accept | Prototypes, custom HTML, and standalone pieces. Three body modes: rich text, HTML body file, or full HTML swap. |
| 0 | 260 | views/navigation.php:277 | accept | Top nav shown above every public page |
| 0 | 261 | views/navigation.php:278 | accept | Bottom links shown in the page footer |
| 0 | 602 | views/post-template.php:415 | accept | Live preview of <sub-template name> — renders site/templates/<filename> against sample content. Edits to the template file show up here immediately. |
| 0 | 1232 | views/page-edit.php:401 | alternative | This is the on-disk file. The CMS never writes here — click [+ New Mock] to start editing. |
| 0 | 242 | views/pages.php:264 | accept | Shared header and footer · publishable on staging |
| 0 | 548 | views/post-template.php:168 | accept | Each content type has a PHP layout file that controls how its fields render on the public site. The Master template lists every available field and its PHP variable — it's a reference, not a switchboard. Each sub-template inherits everything and can suppress specific fields. |
| 0 | 482 | views/series.php:202 | accept | Live ↗ |
| 0 | 400 | views/index-edit.php:384 | accept | One row is about 4 cards. Items beyond the cap are hidden. |
| 0 | 979 | views/live-session-edit.php:697 | accept | Publish Date is separate — it's stamped when the session is published. Past events stay live with a PAST badge. |
| 0 | 414 | views/redirects.php:156 | accept | Map an old URL to a new one. 301 is the default (permanent — browsers cache it). Use 302 when the destination might move — third-party links, A/B tests, anything not yet stable. |
| 0 | 69 | views/ideation.php:93 | accept | Capture raw ideas. Drag a card into a type column to assign it. Drag within a column to reorder. |
| 0 | 326 | views/indexes.php:232 | accept | Editorial Pages and additional Basic Listings you've created |
| -2 | 1116 | views/experiment-edit.php:677 | accept | Full-page passthrough — the file is served directly at /experiments/<slug>/ with no template wrapper. |
| -3 | 348 | views/index-new.php:146 | accept | Hero feature, curated picks, and a content feed. Use for curated landing pages (e.g. /digital-garden/). |
| -3 | 114 | views/journals.php:82 | accept | Short, declarative entries. Each gets a per-category entry number on first publish. |
| -3 | 1180 | views/experiment-new.php:160 | accept | e.g. "Decision scaffolding tool". Shown in /experiments/ and on the public page. |
| -3 | 1050 | views/live-session-new.php:128 | accept | e.g. "Designing for Human Agency". Shown on the public page and in /live-sessions/ listings. |
| -3 | 1245 | views/page-edit.php:593 | accept | Name this mock (e.g. "Tighter intro") |
| -3 | 1091 | views/experiment-edit.php:528 | accept | Preview · Experiment |
| -3 | 829 | views/journal-edit.php:450 | accept | Preview · Journal entry |
| -3 | 950 | views/live-session-edit.php:540 | accept | Preview · Live session |
| -3 | 1233 | views/page-edit.php:420 | accept | Preview · <filename> |
| -3 | 589 | views/post-template.php:340 | accept | Master Template preview · every block populated |
| -3 | 605 | views/post-template.php:425 | accept | Preview · <sub-template name> |

## Open questions

### Q1: Sidebar items 2/4/6/28 (Dashboard, Analytics, Post History, Settings)
A: yes keep

### Q2: Topbar staging pill on production
A: hidden in production.

### Q3: Ideation "+ Add" button
A: add idea

### Q4: "View Index ↗" buttons on the four list views
A: view live index

### Q5: "+ New Session" on Live Sessions list
A: new live session yes

### Q6: Pages "Archives" filter pill
A: yes, confirm, it not a filter actually but it has the same styling

### Q7: Relative-time format ("Nm ago", "Nh ago", "Nd ago")
A: change to min/hr/d for legibility, unless its a really tight space.

### Q8: "Back to Draft Writing" back-button canonical name
A: no answer

### Q9: Type select hint on Idea stage
A: no need to mention this inside the edit, if thats what this means

### Q10: Punctuation consistency on char-allow hints
A: no answer

### Q11: Live indicator badge
A: no answer

### Q12: JS prompt() dialogs for mock naming
A: no answer

### Q13: Pipeline view "This Week / Next Week / Future" buckets
A: it's fine for now.

### Q14: BLOCKS.md reference in #597
A: unclear what this means so doesnt need reference for the user
