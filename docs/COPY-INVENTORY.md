# COPY-INVENTORY

Inventory of every user-facing string in the alex-cms-buildout CMS admin surface. Entries enumerate location (file:line) and current text — no recommendations.

File paths are relative to `site/cms/` unless noted otherwise.

---

## Sidebar

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 1 | Sidebar section label | partials/sidebar.php:41 | Overview |
| 2 | Sidebar item (placeholder) | partials/sidebar.php:44 | Dashboard |
| 3 | Tooltip / title | partials/sidebar.php:42 | Coming soon |
| 4 | Sidebar item (placeholder) | partials/sidebar.php:48 | Analytics |
| 5 | Tooltip / title | partials/sidebar.php:46 | Coming soon |
| 6 | Sidebar item (placeholder) | partials/sidebar.php:52 | Post History |
| 7 | Tooltip / title | partials/sidebar.php:50 | Coming soon |
| 8 | Sidebar section label | partials/sidebar.php:57 | Writer's Desk |
| 9 | Sidebar item | partials/sidebar.php:60 | Ideation Board |
| 10 | Sidebar item | partials/sidebar.php:64 | Draft Writing |
| 11 | Sidebar section label | partials/sidebar.php:69 | Library |
| 12 | Sidebar item | partials/sidebar.php:72 | Articles |
| 13 | Sidebar item | partials/sidebar.php:76 | Journals |
| 14 | Sidebar item | partials/sidebar.php:80 | Live Sessions |
| 15 | Sidebar item | partials/sidebar.php:84 | Experiments |
| 16 | Sidebar section label | partials/sidebar.php:89 | Site |
| 17 | Sidebar item | partials/sidebar.php:92 | Pages |
| 18 | Sidebar item | partials/sidebar.php:96 | Navigation |
| 19 | Sidebar item | partials/sidebar.php:100 | Redirects |
| 20 | Sidebar section label | partials/sidebar.php:105 | Collections |
| 21 | Sidebar item | partials/sidebar.php:108 | Categories |
| 22 | Sidebar item | partials/sidebar.php:112 | Series |
| 23 | Sidebar item | partials/sidebar.php:116 | Indexes |
| 24 | Sidebar section label | partials/sidebar.php:121 | Audience |
| 25 | Sidebar item | partials/sidebar.php:124 | Subscribers |
| 26 | Sidebar section label | partials/sidebar.php:129 | System |
| 27 | Sidebar item | partials/sidebar.php:132 | Post Templates |
| 28 | Sidebar item (placeholder) | partials/sidebar.php:136 | Settings |
| 29 | Tooltip / title | partials/sidebar.php:134 | Coming soon |
| 30 | Other (aria-label) | partials/sidebar.php:38 | CMS navigation |

---

## Topbar

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 31 | Other (skip link) | partials/topbar.php:53 | Skip to content |
| 32 | Other (logo text) | partials/topbar.php:55 | alexmchong |
| 33 | Other (logo text, italic) | partials/topbar.php:55 | cms |
| 34 | Status pill | partials/topbar.php:55 | staging |
| 35 | Tooltip / title | partials/topbar.php:55 | Staging environment |
| 36 | Tooltip / title | partials/topbar.php:65 | Back to `<?= $_first ?>` |
| 37 | Button label | partials/topbar.php:76 | Log out |

---

## Pipeline / Draft Writing view (views/pipeline.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 38 | Other (page title) | views/pipeline.php:225 | Draft Writing — alexmchong.ca CMS |
| 39 | Breadcrumb | views/pipeline.php:242 | Draft Writing |
| 40 | Other (header title) | views/pipeline.php:258 | Draft Writing |
| 41 | Other (header description) | views/pipeline.php:259 | All work in progress and recently shipped — Concept through Recently Published. Capture quickly, develop deliberately. Content moves left to right as it matures. |
| 42 | Status pill | views/pipeline.php:261 | In flight |
| 43 | Status pill | views/pipeline.php:263 | Concept |
| 44 | Status pill | views/pipeline.php:265 | Outline |
| 45 | Status pill | views/pipeline.php:267 | Draft |
| 46 | Status pill | views/pipeline.php:269 | Scheduled |
| 47 | Status pill | views/pipeline.php:271 | Live |
| 48 | Flash message | views/pipeline.php:274 | `<?= $e($flash) ?>` (dynamic flash from URL) |
| 49 | Type badge | views/pipeline.php:126 | Journal |
| 50 | Type badge | views/pipeline.php:127 | Session |
| 51 | Type badge | views/pipeline.php:128 | Experiment |
| 52 | Type badge | views/pipeline.php:129 | Article |
| 53 | Empty state | views/pipeline.php:121 | (untitled) |
| 54 | Tooltip / title | views/pipeline.php:176 | Scheduled to publish |
| 55 | Block label | views/pipeline.php:208 | Concepts |
| 56 | Block label | views/pipeline.php:209 | Outlines |
| 57 | Block label | views/pipeline.php:210 | Drafts |
| 58 | Block label | views/pipeline.php:214 | This Week |
| 59 | Block label | views/pipeline.php:215 | Next Week |
| 60 | Block label | views/pipeline.php:216 | Future |
| 61 | Empty state | views/pipeline.php:295 | Nothing here yet |
| 62 | Block label | views/pipeline.php:308 | Scheduled |
| 63 | Empty state | views/pipeline.php:313 | Nothing scheduled |
| 64 | Block label | views/pipeline.php:329 | Recently Published |
| 65 | Empty state | views/pipeline.php:334 | Nothing published yet |

---

## Ideation view (views/ideation.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 66 | Other (page title) | views/ideation.php:61 | Ideation — alexmchong.ca CMS |
| 67 | Breadcrumb | views/ideation.php:78 | Ideation |
| 68 | View title | views/ideation.php:92 | Ideation |
| 69 | View subtitle | views/ideation.php:93 | Capture raw ideas. Drag a card into a type column to assign it; drag within a column to reorder. |
| 70 | Type badge / Filter pill | views/ideation.php:48 | No type |
| 71 | Type badge / Filter pill | views/ideation.php:49 | Article |
| 72 | Type badge / Filter pill | views/ideation.php:50 | Journal |
| 73 | Type badge / Filter pill | views/ideation.php:51 | Live Session |
| 74 | Type badge / Filter pill | views/ideation.php:52 | Experiment |
| 75 | Field placeholder | views/ideation.php:102 | What's the idea? |
| 76 | Button label | views/ideation.php:103 | + Add |
| 77 | Flash message | views/ideation.php:106 | `<?= $e($flash) ?>` (dynamic flash from URL) |
| 78 | Empty state | views/ideation.php:125 | Drop here |
| 79 | Empty state | views/ideation.php:129 | (untitled) |

---

## Articles list view (views/articles.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 80 | Other (page title) | views/articles.php:57 | Articles — alexmchong.ca CMS |
| 81 | Breadcrumb | views/articles.php:74 | Articles |
| 82 | View title | views/articles.php:88 | Articles |
| 83 | View subtitle | views/articles.php:89 | Long-form writing. Create, edit, and ship drafts. Pipeline + transitions land in Phase 7. |
| 84 | Button label | views/articles.php:90 | View Index ↗ |
| 85 | Button label | views/articles.php:91 | + New Article |
| 86 | Status pill | views/articles.php:48 | `ucfirst($status)` — e.g. Idea / Concept / Outline / Draft / Published / Scheduled |
| 87 | Empty state | views/articles.php:164 | (untitled) |
| 88 | Tooltip / title | views/articles.php:197 | Open the live published page |
| 89 | Button label | views/articles.php:197 | Live ↗ |
| 90 | Button label | views/articles.php:204 | Edit |
| 91 | Confirm dialog | views/articles.php:205 | Delete this article? This cannot be undone. |
| 92 | Tooltip / title | views/articles.php:207 | Delete |
| 93 | Tooltip / aria-label | views/articles.php:207 | Delete |
| 94 | Button label | views/articles.php:207 | × |
| 95 | Column header | views/articles.php:139 | Article Title |
| 96 | Column header | views/articles.php:140 | Stage |
| 97 | Column header | views/articles.php:141 | Category |
| 98 | Column header | views/articles.php:142 | Special tag |
| 99 | Column header | views/articles.php:143 | Series |
| 100 | Column header | views/articles.php:144 | `$dateLabel` (Updated / Scheduled for / Published) |
| 101 | Block label | views/articles.php:234 | Drafts |
| 102 | Block sublabel | views/articles.php:235 | Concept · Outline · Draft |
| 103 | Block count suffix | views/articles.php:237 | entries |
| 104 | Empty state | views/articles.php:243 | No drafts in progress. Click + New Article to start. |
| 105 | Block label | views/articles.php:252 | Scheduled |
| 106 | Block sublabel | views/articles.php:253 | Queued for future publish — cron promotes to Live |
| 107 | Empty state | views/articles.php:261 | No scheduled articles. |
| 108 | Block label | views/articles.php:270 | Published |
| 109 | Block sublabel | views/articles.php:271 | Live on /writing/[slug] |
| 110 | Empty state | views/articles.php:279 | No published articles yet. |

---

## Journals list view (views/journals.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 111 | Other (page title) | views/journals.php:50 | Journals — alexmchong.ca CMS |
| 112 | Breadcrumb | views/journals.php:67 | Journals |
| 113 | View title | views/journals.php:81 | Journals |
| 114 | View subtitle | views/journals.php:82 | Short, declarative entries. Each gets a per-category Entry number when published. |
| 115 | Button label | views/journals.php:83 | View Index ↗ |
| 116 | Button label | views/journals.php:84 | + New Journal |
| 117 | Status pill | views/journals.php:41 | `ucfirst($status)` — Idea / Draft / Published / Scheduled |
| 118 | Empty state | views/journals.php:135 | (untitled) |
| 119 | Tooltip / title | views/journals.php:168 | Open the live published page |
| 120 | Button label | views/journals.php:168 | Live ↗ |
| 121 | Button label | views/journals.php:174 | Edit |
| 122 | Confirm dialog | views/journals.php:175 | Delete this journal? This cannot be undone. |
| 123 | Tooltip / title | views/journals.php:177 | Delete |
| 124 | Tooltip / aria-label | views/journals.php:177 | Delete |
| 125 | Button label | views/journals.php:177 | × |
| 126 | Column header | views/journals.php:121 | Key Statement |
| 127 | Column header | views/journals.php:122 | Stage |
| 128 | Column header | views/journals.php:123 | Category |
| 129 | Column header | views/journals.php:124 | Entry # |
| 130 | Column header | views/journals.php:125 | `$dateLabel` (Updated / Scheduled for / Published) |
| 131 | Block label | views/journals.php:199 | Drafts |
| 132 | Block sublabel | views/journals.php:200 | Concept · Outline · Draft |
| 133 | Block count suffix | views/journals.php:202 | entries |
| 134 | Empty state | views/journals.php:207 | No journal drafts. Click + New Journal to start. |
| 135 | Block label | views/journals.php:216 | Scheduled |
| 136 | Block sublabel | views/journals.php:217 | Queued for future publish — cron promotes to Live |
| 137 | Empty state | views/journals.php:224 | No scheduled journals. |
| 138 | Block label | views/journals.php:233 | Published |
| 139 | Block sublabel | views/journals.php:234 | Live on /journal/[slug] |
| 140 | Empty state | views/journals.php:241 | No published journals yet. |

---

## Live sessions list view (views/live-sessions.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 141 | Other (page title) | views/live-sessions.php:51 | Live Sessions — alexmchong.ca CMS |
| 142 | Breadcrumb | views/live-sessions.php:68 | Live Sessions |
| 143 | View title | views/live-sessions.php:82 | Live Sessions |
| 144 | View subtitle | views/live-sessions.php:83 | Talks, workshops, conversations. Past events stay live with a PAST badge. |
| 145 | Button label | views/live-sessions.php:84 | View Index ↗ |
| 146 | Button label | views/live-sessions.php:85 | + New Session |
| 147 | Status pill | views/live-sessions.php:42 | `ucfirst($status)` — Idea / Draft / Published / Scheduled |
| 148 | Empty state | views/live-sessions.php:217 | (untitled) |
| 149 | Other (past suffix) | views/live-sessions.php:210 |  (past) |
| 150 | Tooltip / title | views/live-sessions.php:233 | Open the live published page |
| 151 | Button label | views/live-sessions.php:233 | Live ↗ |
| 152 | Button label | views/live-sessions.php:239 | Edit |
| 153 | Confirm dialog | views/live-sessions.php:240 | Delete this session? This cannot be undone. |
| 154 | Tooltip / title | views/live-sessions.php:242 | Delete |
| 155 | Tooltip / aria-label | views/live-sessions.php:242 | Delete |
| 156 | Button label | views/live-sessions.php:242 | × |
| 157 | Column header | views/live-sessions.php:160 | Event Title |
| 158 | Column header | views/live-sessions.php:161 | Stage |
| 159 | Column header | views/live-sessions.php:162 | Category |
| 160 | Column header | views/live-sessions.php:163 | Event Date |
| 161 | Column header | views/live-sessions.php:164 | `$dateLabel` (Updated / Scheduled for / Published) |
| 162 | Block label | views/live-sessions.php:264 | Drafts |
| 163 | Block sublabel | views/live-sessions.php:265 | Concept · Outline · Draft |
| 164 | Block count suffix | views/live-sessions.php:267 | entries |
| 165 | Empty state | views/live-sessions.php:273 | No drafts. Click + New Session to add one. |
| 166 | Block label | views/live-sessions.php:282 | Scheduled |
| 167 | Block sublabel | views/live-sessions.php:283 | Queued for future publish — cron promotes to Live |
| 168 | Empty state | views/live-sessions.php:291 | No scheduled sessions. |
| 169 | Block label | views/live-sessions.php:300 | Published |
| 170 | Block sublabel | views/live-sessions.php:301 | Live on /live-sessions/[slug] |
| 171 | Empty state | views/live-sessions.php:309 | No published sessions yet. |

---

## Experiments list view (views/experiments.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 172 | Other (page title) | views/experiments.php:51 | Experiments — alexmchong.ca CMS |
| 173 | Breadcrumb | views/experiments.php:68 | Experiments |
| 174 | View title | views/experiments.php:82 | Experiments |
| 175 | View subtitle | views/experiments.php:83 | Prototypes, custom HTML, and standalone pieces. Three body modes: rich text, HTML body file, full HTML swap. |
| 176 | Button label | views/experiments.php:84 | View Index ↗ |
| 177 | Button label | views/experiments.php:85 | + New Experiment |
| 178 | Status pill | views/experiments.php:42 | `ucfirst($status)` — Idea / Draft / Published / Scheduled |
| 179 | Empty state | views/experiments.php:142 | (untitled) |
| 180 | Other (hint) | views/experiments.php:163 | no folder |
| 181 | Other (hint) | views/experiments.php:164 | no file picked |
| 182 | Tooltip / title | views/experiments.php:173 | Open the live published page |
| 183 | Button label | views/experiments.php:173 | Live ↗ |
| 184 | Button label | views/experiments.php:179 | Edit |
| 185 | Confirm dialog | views/experiments.php:180 | Delete this experiment? This cannot be undone. |
| 186 | Tooltip / title | views/experiments.php:182 | Delete |
| 187 | Tooltip / aria-label | views/experiments.php:182 | Delete |
| 188 | Button label | views/experiments.php:182 | × |
| 189 | Column header | views/experiments.php:120 | Experiment Title |
| 190 | Column header | views/experiments.php:121 | Stage |
| 191 | Column header | views/experiments.php:122 | Category |
| 192 | Column header | views/experiments.php:123 | Content Type |
| 193 | Column header | views/experiments.php:124 | `$dateLabel` (Updated / Scheduled for / Published) |
| 194 | Block label | views/experiments.php:204 | Drafts |
| 195 | Block sublabel | views/experiments.php:205 | Concept · Outline · Draft |
| 196 | Block count suffix | views/experiments.php:207 | entries |
| 197 | Empty state | views/experiments.php:212 | No experiment drafts. Click + New Experiment to start. |
| 198 | Block label | views/experiments.php:221 | Scheduled |
| 199 | Block sublabel | views/experiments.php:222 | Queued for future publish — cron promotes to Live |
| 200 | Empty state | views/experiments.php:229 | No scheduled experiments. |
| 201 | Block label | views/experiments.php:238 | Published |
| 202 | Block sublabel | views/experiments.php:239 | Live on /experiments/[slug] |
| 203 | Empty state | views/experiments.php:246 | No published experiments yet. |

---

## Pages list view (views/pages.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 204 | Other (page title) | views/pages.php:115 | Pages — alexmchong.ca CMS |
| 205 | Breadcrumb | views/pages.php:132 | Pages |
| 206 | View title | views/pages.php:146 | Pages |
| 207 | View subtitle | views/pages.php:147 | Marketing pages live on disk and ship via deploy. The CMS lets you save named mock versions for preview. For header.php / footer.php only, a mock can be published to override the file at runtime on staging. |
| 208 | Filter label | views/pages.php:157 | View |
| 209 | Filter pill | views/pages.php:160 | All |
| 210 | Filter pill | views/pages.php:161 | Archives |
| 211 | Flash message | views/pages.php:170 | `<?= $e($flash) ?>` |
| 212 | Other (mock count, none) | views/pages.php:82 | No mocks |
| 213 | Other (mock count, n) | views/pages.php:84 | `n mock` / `n mocks` |
| 214 | Status pill | views/pages.php:87 | LIVE: `<?= $e($published_name[$slug]) ?>` |
| 215 | Tooltip / title | views/pages.php:87 | A mock is currently published — overriding the file at runtime |
| 216 | Tooltip / title | views/pages.php:93 | Open the live page |
| 217 | Button label | views/pages.php:93 | Live ↗ |
| 218 | Button label | views/pages.php:94 | Edit |
| 219 | Column header (archives) | views/pages.php:176 | Archive name |
| 220 | Column header (archives) | views/pages.php:177 | Page |
| 221 | Column header (archives) | views/pages.php:178 | Captured |
| 222 | Column header (archives) | views/pages.php:179 | Actions |
| 223 | Button label | views/pages.php:193 | Preview ↗ |
| 224 | Block label | views/pages.php:203 | Archives |
| 225 | Block sublabel | views/pages.php:204 | Snapshots of past page versions · preview-only, no public URL |
| 226 | Block count suffix | views/pages.php:206 | archive / archives |
| 227 | Empty state | views/pages.php:212 | No archives yet. To archive a page, insert a mock whose name starts with "Archive ". |
| 228 | Column header | views/pages.php:220 | File |
| 229 | Column header | views/pages.php:221 | Meta title |
| 230 | Column header | views/pages.php:222 | Mocks |
| 231 | Column header | views/pages.php:223 | Last modified |
| 232 | Column header | views/pages.php:224 | Actions |
| 233 | Block label | views/pages.php:231 | Marketing pages |
| 234 | Block sublabel | views/pages.php:232 | Mock-only sandbox · files remain canonical |
| 235 | Block count suffix | views/pages.php:234 | files |
| 236 | Empty state | views/pages.php:239 | No marketing pages found. |
| 237 | Block label | views/pages.php:247 | Error pages |
| 238 | Block sublabel | views/pages.php:248 | Rendered when no route or file matches |
| 239 | Block count suffix | views/pages.php:250 | files |
| 240 | Empty state | views/pages.php:255 | No error pages found. |
| 241 | Block label | views/pages.php:263 | Layout partials |
| 242 | Block sublabel | views/pages.php:264 | Shared header + footer · publish-capable on staging |
| 243 | Block count suffix | views/pages.php:266 | files |
| 244 | Empty state | views/pages.php:271 | No layout partials found. |
| 245 | Other (just-now relative) | views/pages.php:60 | just now |
| 246 | Other (relative time) | views/pages.php:61 | `Nm ago` |
| 247 | Other (relative time) | views/pages.php:62 | `Nh ago` |
| 248 | Other (relative time) | views/pages.php:63 | `Nd ago` |

---

## Navigation editor (views/navigation.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 249 | Other (page title) | views/navigation.php:139 | Navigation — alexmchong.ca CMS |
| 250 | Breadcrumb | views/navigation.php:240 | Navigation |
| 251 | View title | views/navigation.php:254 | Navigation |
| 252 | View subtitle | views/navigation.php:255 | Header and footer link lists. Drag to reorder. Items whose target row no longer resolves are flagged BROKEN and hidden from the public site until you fix them. |
| 253 | Button label | views/navigation.php:256 | Open homepage ↗ |
| 254 | Error message (header) | views/navigation.php:262 | Couldn't save: |
| 255 | Error message (CSRF) | views/navigation.php:37 | Session expired. Reload the page and try again. |
| 256 | Flash message | views/navigation.php:57 | Item saved. |
| 257 | Flash message | views/navigation.php:57 | Item added. |
| 258 | Flash message | views/navigation.php:60 | Item deleted. |
| 259 | Block label | views/navigation.php:283 | `ucfirst($zone)` — Header / Footer |
| 260 | Block sublabel (header) | views/navigation.php:277 | Top nav rendered above every public page |
| 261 | Block sublabel (footer) | views/navigation.php:278 | Bottom links rendered in the page footer |
| 262 | Block count suffix | views/navigation.php:286 | item / items |
| 263 | Tooltip / title | views/navigation.php:292 | Drag to reorder |
| 264 | Field placeholder | views/navigation.php:298 | Label |
| 265 | Field placeholder | views/navigation.php:299 | nav_key |
| 266 | Field placeholder | views/navigation.php:306 | /path/ or https://… |
| 267 | Field placeholder | views/navigation.php:307 | page-slug (e.g. about) |
| 268 | Other (select option) | views/navigation.php:309 | — Choose index — |
| 269 | Other (select option) | views/navigation.php:315 | — Choose category — |
| 270 | Other (select option) | views/navigation.php:321 | — Choose series — |
| 271 | Other (select option) | views/navigation.php:327 | — Choose content — |
| 272 | Field placeholder (pill text) | views/navigation.php:128 | NEW |
| 273 | Field placeholder (color) | views/navigation.php:340 | #d63031 |
| 274 | Button label | views/navigation.php:341 | Save |
| 275 | Confirm dialog | views/navigation.php:343 | Delete "`<?= $e((string)$it['label']) ?>`"? |
| 276 | Button label | views/navigation.php:347 | Delete |
| 277 | Status pill | views/navigation.php:349 | Broken |
| 278 | Field placeholder | views/navigation.php:360 | New item label |
| 279 | Field placeholder | views/navigation.php:361 | nav_key |
| 280 | Field placeholder | views/navigation.php:368 | /path/ or https://… |
| 281 | Field placeholder | views/navigation.php:369 | page-slug |
| 282 | Other (select option) | views/navigation.php:371 | — Choose index — |
| 283 | Other (select option) | views/navigation.php:375 | — Choose category — |
| 284 | Other (select option) | views/navigation.php:379 | — Choose series — |
| 285 | Other (select option) | views/navigation.php:383 | — Choose content — |
| 286 | Field placeholder | views/navigation.php:394 | #d63031 |
| 287 | Button label | views/navigation.php:395 | Add |
| 288 | Button label (JS dynamic) | views/navigation.php:511 | Saved |
| 289 | Alert (JS) | views/navigation.php:571 | Reorder failed — refresh and try again. |
| 290 | Other (aria-label, role-related) | views/navigation.php:235 | (no live alert text) |

---

## Indexes list view (views/indexes.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 291 | Other (page title) | views/indexes.php:65 | Indexes — alexmchong.ca CMS |
| 292 | Breadcrumb | views/indexes.php:82 | Indexes |
| 293 | View title | views/indexes.php:96 | Indexes |
| 294 | View subtitle | views/indexes.php:97 | Configure the public index pages for each section of the site. Editorial Page adds hero + featured + multi-section layout; Basic Listing is title + feed. |
| 295 | Button label | views/indexes.php:98 | + New Index |
| 296 | Status pill (layout: editorial) | views/indexes.php:54 | Editorial |
| 297 | Status pill (layout: listing) | views/indexes.php:54 | Listing |
| 298 | Column header | views/indexes.php:109 | Index |
| 299 | Column header | views/indexes.php:110 | Layout |
| 300 | Column header | views/indexes.php:111 | Feed |
| 301 | Column header | views/indexes.php:112 | Updated |
| 302 | Empty state | views/indexes.php:135 | (untitled) |
| 303 | Other (feed all-types fallback) | views/indexes.php:128 | all types |
| 304 | Tooltip / title | views/indexes.php:143 | Open the live index page |
| 305 | Button label | views/indexes.php:143 | Live ↗ |
| 306 | Button label | views/indexes.php:145 | Edit |
| 307 | Confirm dialog | views/indexes.php:146 | Delete this index? The URL /`<?= $e($slug) ?>`/ will 404 unless you re-create it. |
| 308 | Tooltip / title | views/indexes.php:148 | Delete |
| 309 | Tooltip / aria-label | views/indexes.php:148 | Delete |
| 310 | Button label | views/indexes.php:148 | × |
| 311 | Block label | views/indexes.php:169 | Post Type indexes |
| 312 | Block sublabel | views/indexes.php:170 | The four built-in type pages |
| 313 | Block count suffix | views/indexes.php:172 | indexes |
| 314 | Empty state | views/indexes.php:176 | Seed missing. Run migration 0007 to restore the four built-in Post Type indexes. |
| 315 | Block label | views/indexes.php:185 | Series indexes |
| 316 | Block sublabel | views/indexes.php:186 | Auto-generated from /cms/series — not editable here |
| 317 | Block count suffix | views/indexes.php:188 | series |
| 318 | Column header | views/indexes.php:192 | Series |
| 319 | Column header | views/indexes.php:193 | Layout |
| 320 | Column header | views/indexes.php:194 | Parts |
| 321 | Other (parts count text) | views/indexes.php:209 | part / parts |
| 322 | Tooltip / title | views/indexes.php:212 | Open the live series index |
| 323 | Button label | views/indexes.php:212 | Live ↗ |
| 324 | Button label | views/indexes.php:214 | Manage |
| 325 | Block label | views/indexes.php:231 | Custom indexes |
| 326 | Block sublabel | views/indexes.php:232 | Author-created Editorial Pages and additional Basic Listings |
| 327 | Block count suffix | views/indexes.php:234 | indexes |
| 328 | Empty state | views/indexes.php:239 | No custom indexes yet. Click + New Index to add one (e.g. /digital-garden, /thoughts). |

---

## Index new view (views/index-new.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 329 | Other (page title) | views/index-new.php:66 | New Index — alexmchong.ca CMS |
| 330 | Breadcrumb | views/index-new.php:83 | Indexes / New |
| 331 | View title | views/index-new.php:96 | New Editorial Index |
| 332 | View subtitle | views/index-new.php:97 | Creates a configurable page at a custom URL. Slug is permanent — pick carefully. |
| 333 | Button label | views/index-new.php:98 | Cancel |
| 334 | Error message (CSRF) | views/index-new.php:31 | Session expired. Reload the page and try again. |
| 335 | Error message (header) | views/index-new.php:105 | Couldn't create: |
| 336 | Error message | views/index-new.php:50 | Could not create index: `<?= $ex->getMessage() ?>` |
| 337 | Field label | views/index-new.php:118 | Title |
| 338 | Field placeholder | views/index-new.php:119 | e.g. Digital Garden |
| 339 | Field hint | views/index-new.php:121 | Shown at the top of the index page. Also used to derive the slug if you leave that blank. |
| 340 | Field label | views/index-new.php:125 | Slug |
| 341 | Field hint inline | views/index-new.php:125 | required if no title |
| 342 | Field placeholder | views/index-new.php:128 | e.g. digital-garden |
| 343 | Field hint | views/index-new.php:132 | Becomes the URL. Permanent once set. Lowercase letters, numbers, and hyphens only. |
| 344 | Field label | views/index-new.php:136 | Layout |
| 345 | Other (radio strong) | views/index-new.php:140 | Basic Listing |
| 346 | Field hint | views/index-new.php:141 | Title, optional description, and a content feed. Best for catch-all section indexes (e.g. /writing, /journal). |
| 347 | Other (radio strong) | views/index-new.php:145 | Editorial Page |
| 348 | Field hint | views/index-new.php:146 | Hero feature + curated picks + content feed. Use for curated landing pages (e.g. /digital-garden). |
| 349 | Button label | views/index-new.php:152 | Create Index |
| 350 | Button label | views/index-new.php:153 | Cancel |
| 351 | Flash message | views/index-new.php:41 | Index created — configure below. |

---

## Index edit view (views/index-edit.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 352 | Other (page title) | views/index-edit.php:169 | /`<slug>`/ — Edit Index |
| 353 | Breadcrumb | views/index-edit.php:186 | Indexes / `<?= $index['slug'] ?>` |
| 354 | Error message (404) | views/index-edit.php:39 | Index not found. |
| 355 | Error message (CSRF) | views/index-edit.php:46 | Session expired. Reload the page and try again. |
| 356 | Flash message | views/index-edit.php:77 | Saved. |
| 357 | Error message | views/index-edit.php:83 | Could not save index: `<?= $ex->getMessage() ?>` |
| 358 | View title | views/index-edit.php:199 | /`<slug>`/ |
| 359 | View subtitle (editorial) | views/index-edit.php:201 | Editorial Page — configure the hero feature, curated picks, and content feed for this page. |
| 360 | View subtitle (listing) | views/index-edit.php:202 | Basic Listing — title, optional description, and a content feed. |
| 361 | Button label | views/index-edit.php:203 | View |
| 362 | Button label | views/index-edit.php:204 | All indexes |
| 363 | Error message (header) | views/index-edit.php:210 | Couldn't save: |
| 364 | Block label | views/index-edit.php:232 | Layout |
| 365 | Block sublabel | views/index-edit.php:233 | Flip between the two layout types. Hero + featured config is preserved when switched off. |
| 366 | Other (radio strong) | views/index-edit.php:239 | Basic Listing |
| 367 | Field hint | views/index-edit.php:240 | Title + content feed. |
| 368 | Other (radio strong) | views/index-edit.php:244 | Editorial Page |
| 369 | Field hint | views/index-edit.php:245 | Hero + featured + feed. |
| 370 | Block label | views/index-edit.php:254 | Page title |
| 371 | Block sublabel | views/index-edit.php:255 | Shown at the top of the index page. |
| 372 | Other (toggle label) | views/index-edit.php:259 | Show title |
| 373 | Other (note) | views/index-edit.php:263 | Always shown on Basic Listing |
| 374 | Field label | views/index-edit.php:268 | Title |
| 375 | Field label | views/index-edit.php:273 | Subtitle / description |
| 376 | Field hint inline | views/index-edit.php:273 | optional |
| 377 | Block label | views/index-edit.php:284 | Hero feature |
| 378 | Block sublabel | views/index-edit.php:285 | One published item to anchor the top of the page. |
| 379 | Other (select option) | views/index-edit.php:290 | — None — |
| 380 | Block label | views/index-edit.php:302 | Featured |
| 381 | Block sublabel | views/index-edit.php:303 | Curated picks shown above the feed. Drag to reorder. |
| 382 | Tooltip / title | views/index-edit.php:313 | Drag to reorder |
| 383 | Tooltip / title | views/index-edit.php:315 | Remove |
| 384 | Tooltip / aria-label | views/index-edit.php:315 | Remove |
| 385 | Other (select option) | views/index-edit.php:321 | + Add to featured… |
| 386 | Button label | views/index-edit.php:326 | Add |
| 387 | Block label | views/index-edit.php:335 | Content feed |
| 388 | Block sublabel | views/index-edit.php:336 | The main grid of cards. Type chips are OR — pick any combination. Empty = all types. |
| 389 | Field label | views/index-edit.php:341 | Types |
| 390 | Other (type label) | views/index-edit.php:148 | Articles |
| 391 | Other (type label) | views/index-edit.php:149 | Journals |
| 392 | Other (type label) | views/index-edit.php:150 | Live Sessions |
| 393 | Other (type label) | views/index-edit.php:151 | Experiments |
| 394 | Field label | views/index-edit.php:355 | Sort |
| 395 | Other (sort option) | views/index-edit.php:358 | Newest first |
| 396 | Other (sort option) | views/index-edit.php:359 | Oldest first |
| 397 | Field hint | views/index-edit.php:369 | Manual sort is reserved for a later phase — choose Newest or Oldest for now. |
| 398 | Field label | views/index-edit.php:373 | Rows shown |
| 399 | Other (rows option) | views/index-edit.php:380 | All |
| 400 | Field hint | views/index-edit.php:384 | One row ≈ 4 cards. Beyond the cap, items are simply hidden. |
| 401 | Field label | views/index-edit.php:388 | Filter pills |
| 402 | Other (filter mode label) | views/index-edit.php:391 | Categories |
| 403 | Other (filter mode desc) | views/index-edit.php:391 | One pill per category appearing in the feed. |
| 404 | Other (filter mode label) | views/index-edit.php:392 | Content types |
| 405 | Other (filter mode desc) | views/index-edit.php:392 | One pill per feed type (Articles · Journals · Talks · Experiments). |
| 406 | Other (filter mode label) | views/index-edit.php:393 | None |
| 407 | Other (filter mode desc) | views/index-edit.php:393 | No filter row. |
| 408 | Field hint | views/index-edit.php:404 | Filtering is client-side — pills hide/show cards without reloading. The "All" pill is rendered automatically. |
| 409 | Button label | views/index-edit.php:411 | Cancel |
| 410 | Button label | views/index-edit.php:412 | Save |

---

## Redirects view (views/redirects.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 411 | Other (page title) | views/redirects.php:89 | Redirects — alexmchong.ca CMS |
| 412 | Breadcrumb | views/redirects.php:141 | Redirects |
| 413 | View title | views/redirects.php:155 | Redirects |
| 414 | View subtitle | views/redirects.php:156 | Map an old URL to a new one. Default is 301 (permanent, browsers cache it). Use 302 when the destination might move — third-party services, A/B tests, or anything not yet stable. |
| 415 | Error message (CSRF) | views/redirects.php:33 | Session expired. Reload the page and try again. |
| 416 | Error message | views/redirects.php:44 | Could not add. Check for blank fields, duplicate old path, or old = new (would loop). |
| 417 | Flash message | views/redirects.php:46 | Redirect added. |
| 418 | Error message | views/redirects.php:55 | Could not update. Check for blank fields, duplicate old path, or old = new. |
| 419 | Flash message | views/redirects.php:57 | Redirect updated. |
| 420 | Flash message | views/redirects.php:60 | Redirect deleted. |
| 421 | Error message | views/redirects.php:62 | Unknown action. |
| 422 | Error message (header) | views/redirects.php:163 | Couldn't save: |
| 423 | Block label | views/redirects.php:179 | Redirects |
| 424 | Block sublabel | views/redirects.php:180 | From-path → to-path · 301 permanent, 302 temporary |
| 425 | Block count suffix | views/redirects.php:182 | redirect / redirects |
| 426 | Field placeholder | views/redirects.php:192 | /old-path |
| 427 | Field placeholder | views/redirects.php:193 | /new-path or https://… |
| 428 | Button label | views/redirects.php:198 | Save |
| 429 | Confirm dialog | views/redirects.php:200 | Delete redirect "`<?= $e((string)$r['old_slug']) ?>`"? |
| 430 | Button label | views/redirects.php:204 | Delete |
| 431 | Field placeholder | views/redirects.php:214 | /old-path |
| 432 | Field placeholder | views/redirects.php:215 | /new-path or https://example.com/foo |
| 433 | Button label | views/redirects.php:220 | Add |
| 434 | Button label (JS dynamic) | views/redirects.php:249 | Saved |

---

## Categories view (views/categories.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 435 | Other (page title) | views/categories.php:125 | Categories — alexmchong.ca CMS |
| 436 | Breadcrumb | views/categories.php:142 | Categories |
| 437 | View title | views/categories.php:156 | Categories |
| 438 | View subtitle | views/categories.php:157 | Value slugs are permanent — they're what the database stores. Labels and colours are editable anytime. A category can only be deleted when its usage count is zero. |
| 439 | Error message (CSRF) | views/categories.php:39 | Session expired. Reload the page and try again. |
| 440 | Flash message | views/categories.php:49 | Category added. |
| 441 | Flash message | views/categories.php:56 | Category updated. |
| 442 | Flash message | views/categories.php:60 | Category deleted. |
| 443 | Error message | views/categories.php:63 | Unknown action. |
| 444 | Error message (header) | views/categories.php:164 | Couldn't save: |
| 445 | Block label | views/categories.php:113 | Articles |
| 446 | Block sublabel | views/categories.php:113 | Primary category drives colour and card display. Secondary categories expand index inclusion. |
| 447 | Block label | views/categories.php:114 | Journals |
| 448 | Block sublabel | views/categories.php:114 | Single category per entry. Drives the sequential entry number within that category. |
| 449 | Block label | views/categories.php:115 | Live Sessions |
| 450 | Block sublabel | views/categories.php:115 | Single category per session. Displayed publicly as the event type. |
| 451 | Block label | views/categories.php:116 | Experiments |
| 452 | Block sublabel | views/categories.php:116 | Single category per experiment. Drives the grid treatment on the index. |
| 453 | Column header | views/categories.php:199 | Label |
| 454 | Column header | views/categories.php:200 | Value slug |
| 455 | Column header | views/categories.php:201 | Colour |
| 456 | Column header | views/categories.php:202 | Use |
| 457 | Column header | views/categories.php:203 | Actions |
| 458 | Empty state | views/categories.php:207 | No categories yet — add one below. |
| 459 | Tooltip / title | views/categories.php:225 | Save changes |
| 460 | Button label | views/categories.php:225 | Save |
| 461 | Tooltip / title | views/categories.php:227 | Delete category |
| 462 | Tooltip / aria-label | views/categories.php:227 | Delete |
| 463 | Confirm dialog | views/categories.php:227 | Delete category "`<?= $e((string)$cat['label']) ?>`"? |
| 464 | Tooltip / title | views/categories.php:231 | Delete (in use, cannot delete) |
| 465 | Tooltip / aria-label | views/categories.php:231 | Delete |
| 466 | Field placeholder | views/categories.php:244 | New label… |
| 467 | Button label | views/categories.php:246 | Add |

---

## Series view (views/series.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 468 | Other (page title) | views/series.php:131 | Series — alexmchong.ca CMS |
| 469 | Breadcrumb | views/series.php:148 | Series |
| 470 | View title | views/series.php:162 | Series |
| 471 | View subtitle | views/series.php:163 | Ordered groups of articles. Slugs are permanent — set on creation and used in /series/[slug]/ URLs. Phase 12 generates the matching editorial index page. |
| 472 | Error message (CSRF) | views/series.php:38 | Session expired. Reload the page and try again. |
| 473 | Flash message | views/series.php:49 | Series created. |
| 474 | Flash message | views/series.php:60 | Series updated. |
| 475 | Flash message | views/series.php:64 | Series deleted. |
| 476 | Flash message | views/series.php:71 | Article added to series. |
| 477 | Flash message | views/series.php:75 | Article removed from series. |
| 478 | Error message | views/series.php:78 | Unknown action. |
| 479 | Error message (header) | views/series.php:169 | Couldn't save: |
| 480 | Other (parts count) | views/series.php:196 | part / parts |
| 481 | Tooltip / title | views/series.php:202 | Open the live series index |
| 482 | Button label | views/series.php:202 | Launch ↗ |
| 483 | Field placeholder | views/series.php:205 | Optional description — shown on the series index page. |
| 484 | Button label | views/series.php:209 | Save name & description |
| 485 | Tooltip / title | views/series.php:221 | Drag to reorder |
| 486 | Status pill | views/series.php:122 | Live |
| 487 | Status pill | views/series.php:122 | `ucfirst($status)` (concept/outline/draft/idea) |
| 488 | Confirm dialog | views/series.php:226 | Remove "`<?= $e((string)$part['title']) ?>`" from this series? |
| 489 | Tooltip / title | views/series.php:230 | Remove from series |
| 490 | Tooltip / aria-label | views/series.php:230 | Remove |
| 491 | Empty state | views/series.php:236 | No parts yet — add an article below. |
| 492 | Other (select option) | views/series.php:244 | + Add article… |
| 493 | Button label | views/series.php:251 | Add |
| 494 | Confirm dialog | views/series.php:259 | Delete series "`<?= $e((string)$s['name']) ?>`"? |
| 495 | Button label | views/series.php:259 | Delete series |
| 496 | Tooltip / title | views/series.php:261 | Cannot delete — unassign all parts first |
| 497 | Button label | views/series.php:261 | Delete series |
| 498 | Other (placeholder card title) | views/series.php:277 | + New series |
| 499 | Field placeholder | views/series.php:281 | Series name (required) |
| 500 | Field placeholder | views/series.php:283 | slug (optional — auto from name) |
| 501 | Field placeholder | views/series.php:285 | Optional description |
| 502 | Button label | views/series.php:290 | Create series |
| 503 | Alert (JS) | views/series.php:386 | Reorder failed: `<?= err ?>` |

---

## Subscribers view (views/subscribers.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 504 | Other (page title) | views/subscribers.php:111 | Subscribers — alexmchong.ca CMS |
| 505 | Breadcrumb | views/subscribers.php:153 | Subscribers |
| 506 | View title | views/subscribers.php:167 | Subscribers |
| 507 | View subtitle | views/subscribers.php:168 | Captured from the public newsletter form. Re-subscribers update in place — every row here is a unique email address. |
| 508 | Error message (CSRF) | views/subscribers.php:40 | Session expired. Reload the page and try again. |
| 509 | Flash message | views/subscribers.php:46 | Marked unsubscribed. |
| 510 | Flash message | views/subscribers.php:49 | Re-subscribed. |
| 511 | Flash message | views/subscribers.php:51 | Subscriber deleted. |
| 512 | Error message | views/subscribers.php:54 | Unknown action. |
| 513 | Error message (header) | views/subscribers.php:174 | Couldn't update: |
| 514 | Other (counts label) | views/subscribers.php:188 | subscribed |
| 515 | Other (counts label) | views/subscribers.php:189 | unsubscribed |
| 516 | Other (counts label) | views/subscribers.php:190 | new in 30d |
| 517 | Field label | views/subscribers.php:195 | Status |
| 518 | Other (select option) | views/subscribers.php:197 | All |
| 519 | Other (select option) | views/subscribers.php:198 | Subscribed |
| 520 | Other (select option) | views/subscribers.php:199 | Unsubscribed |
| 521 | Field label | views/subscribers.php:203 | Source |
| 522 | Other (select option) | views/subscribers.php:205 | All |
| 523 | Field label | views/subscribers.php:212 | From |
| 524 | Field label | views/subscribers.php:216 | To |
| 525 | Button label | views/subscribers.php:220 | Apply |
| 526 | Button label | views/subscribers.php:221 | Reset |
| 527 | Button label | views/subscribers.php:222 | Export CSV |
| 528 | Column header | views/subscribers.php:241 | Email |
| 529 | Column header | views/subscribers.php:242 | Name |
| 530 | Column header | views/subscribers.php:243 | Subscribed |
| 531 | Column header | views/subscribers.php:244 | Source |
| 532 | Column header | views/subscribers.php:245 | Status |
| 533 | Column header | views/subscribers.php:246 | Actions |
| 534 | Empty state | views/subscribers.php:251 | No subscribers match the current filters. |
| 535 | Status pill | views/subscribers.php:265 | subscribed |
| 536 | Status pill | views/subscribers.php:267 | unsubscribed |
| 537 | Tooltip / title | views/subscribers.php:272 | Mark unsubscribed |
| 538 | Button label | views/subscribers.php:272 | Unsubscribe |
| 539 | Tooltip / title | views/subscribers.php:274 | Mark re-subscribed |
| 540 | Button label | views/subscribers.php:274 | Re-subscribe |
| 541 | Tooltip / title | views/subscribers.php:276 | Delete |
| 542 | Tooltip / aria-label | views/subscribers.php:276 | Delete |
| 543 | Confirm dialog | views/subscribers.php:276 | Delete subscriber "`<?= $e((string)$r['email']) ?>`"? This cannot be undone. |
| 544 | Empty state (date dash) | views/subscribers.php:100 | — |

---

## Post Templates view (views/post-template.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 545 | Other (page title) | views/post-template.php:113 | Post Templates — alexmchong.ca CMS |
| 546 | Breadcrumb | views/post-template.php:154 | Post Templates |
| 547 | View title | views/post-template.php:167 | Post Templates |
| 548 | View subtitle | views/post-template.php:168 | Each content type uses a PHP layout file that controls how its fields render on the live site. The Master template lists every available field and its PHP variable — it doesn't turn anything on or off. Each sub-template inherits everything and can suppress specific fields. |
| 549 | Error message (CSRF) | views/post-template.php:40 | Session expired. Reload the page and try again. |
| 550 | Error message | views/post-template.php:53 | Unknown action. |
| 551 | Flash message | views/post-template.php:50 | Author saved. |
| 552 | Error message (header) | views/post-template.php:175 | Couldn't save: |
| 553 | Other (label) | views/post-template.php:191 | Reference |
| 554 | Other (name) | views/post-template.php:192 | Master Template |
| 555 | Other (description) | views/post-template.php:193 | Defines every block, field, and the author profile. Each sub-template inherits all of this — sub-templates only suppress optional blocks. |
| 556 | Other (label tag) | views/post-template.php:199 | system |
| 557 | Tab label | views/post-template.php:210 | Content Blocks |
| 558 | Tab label | views/post-template.php:211 | Field Reference |
| 559 | Tab label | views/post-template.php:212 | Author info |
| 560 | Tab label | views/post-template.php:213 | PHP Layout File |
| 561 | Tab label | views/post-template.php:214 | Preview |
| 562 | Other (info-box copy) | views/post-template.php:220 | The Master Template defines every block available across content types. Each block has a stable slug used in code (data-block) and a visibility mode. Always blocks render whenever applicable. Optional blocks are toggled on or off per content type from each sub-template. Auto blocks render based on the data (e.g. Tags renders only when tags exist). To inspect a sub-template's specific visibility, select it from the list on the left. |
| 563 | Column header | views/post-template.php:225 | Block |
| 564 | Column header | views/post-template.php:226 | Slug |
| 565 | Column header | views/post-template.php:227 | Composition |
| 566 | Column header | views/post-template.php:228 | Purpose |
| 567 | Other (info-box copy) | views/post-template.php:247 | Every database field underlying the blocks. Each row maps a field to its PHP variable. Blocks read these fields to populate themselves — so the field reference is for layout work, the Content Blocks tab is for visibility. |
| 568 | Column header | views/post-template.php:252 | Field |
| 569 | Column header | views/post-template.php:253 | PHP Variable |
| 570 | Column header | views/post-template.php:254 | Description |
| 571 | Other (info-box copy) | views/post-template.php:272 | The author block renders next to the byline on every template that includes the $author fields. Sub-templates can hide it on a per-content basis via the show_author / show_author_bio booleans on each content row. |
| 572 | Other (field note) | views/post-template.php:289 | 96×96 recommended. Paste the URL or relative path below. |
| 573 | Field label | views/post-template.php:293 | Image URL |
| 574 | Field placeholder | views/post-template.php:294 | /uploads/author.jpg or https://… |
| 575 | Field label | views/post-template.php:297 | Name |
| 576 | Field required indicator | views/post-template.php:297 | required |
| 577 | Field placeholder | views/post-template.php:298 | Alex M. Chong |
| 578 | Field label | views/post-template.php:301 | Short Description |
| 579 | Field placeholder | views/post-template.php:302 | A short bio that appears alongside articles… |
| 580 | Field hint (note) | views/post-template.php:303 | Displays beside the byline on every article that includes the author block. Keep it short — one or two sentences. |
| 581 | Field label | views/post-template.php:306 | Extended Description |
| 582 | Field placeholder | views/post-template.php:307 | The fuller bio rendered in the Author Bio block… |
| 583 | Field hint (note) | views/post-template.php:308 | Renders in the Author Bio block — the footer "About the author" panel. Independently toggleable from the inline Author byline. |
| 584 | Button label | views/post-template.php:311 | Save Author |
| 585 | Other (info-box copy) | views/post-template.php:321 | The master PHP layout file — used as the wrapper for every public-rendered article-family page. Read-only view; edits happen in code (site/templates/master-layout.php) and ship through deploy. |
| 586 | Other (file label) | views/post-template.php:325 | site/templates/master-layout.php |
| 587 | Error / missing | views/post-template.php:328 | master-layout.php not found at site/templates/ — check the deploy. |
| 588 | Other (info-box copy) | views/post-template.php:334 | Comprehensive preview — renders master-layout.php wrapping article-standard.php with every block populated. This is the live counterpart to site/_templates/article.html — a single page that exercises the full block inventory. |
| 589 | Tooltip / title (iframe) | views/post-template.php:340 | Master Template Preview — every block populated |
| 590 | Tab label | views/post-template.php:354 | Block Visibility |
| 591 | Tab label | views/post-template.php:355 | PHP Layout File |
| 592 | Tab label | views/post-template.php:356 | Preview |
| 593 | Column header | views/post-template.php:367 | Block |
| 594 | Column header | views/post-template.php:368 | Slug |
| 595 | Column header | views/post-template.php:369 | Visibility |
| 596 | Column header | views/post-template.php:370 | Notes |
| 597 | Other (readonly note) | views/post-template.php:390 | Per-sub-template visibility toggles are read-only in v1.0 (modes shown above are the BLOCKS.md matrix defaults). Editable per-sub-template suppression is deferred to a future phase — see docs/BUILD-PLAN.md §19.5. |
| 598 | Other (info-box copy) | views/post-template.php:397 | The PHP layout file for `<sub-template name>`. Read-only view; edits happen in code (site/templates/`<filename>`) and ship through deploy. |
| 599 | Other (file label) | views/post-template.php:400 | site/templates/`<?= $info['php_file'] ?>` |
| 600 | Error / missing | views/post-template.php:404 | `<filename>` not found in `site/templates/`. |
| 601 | Other (note) | views/post-template.php:406 | Likely folded into `article-standard.php` via a conditional, or pending creation. Flagged in Phase 14.5 brief. |
| 602 | Other (info-box copy) | views/post-template.php:415 | Live preview of `<sub-template name>` — renders site/templates/`<filename>` against synthetic content. Edits to the template file show up here immediately. |
| 603 | Other (note) | views/post-template.php:417 | Article-series renders via article-standard.php (series detail folds into the topstrip). |
| 604 | Other (note) | views/post-template.php:419 | Experiment-html bypasses master-layout in production and readfile()s a folder we don't have in preview — so the chrome shown here is experiment.php's. |
| 605 | Tooltip / title (iframe) | views/post-template.php:425 | Preview — `<sub-template name>` |

---

## Article edit view (views/article-edit.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 606 | Other (page title) | views/article-edit.php:650 | Edit: `<?= $titleHdr ?>` — alexmchong.ca CMS |
| 607 | Error message (CSRF) | views/article-edit.php:124 | Session expired. Reload the page and try again. |
| 608 | Error message | views/article-edit.php:190 | Title is required. |
| 609 | Error message | views/article-edit.php:195 | Invalid type. |
| 610 | Error message | views/article-edit.php:293 | Primary category is not a known article category. |
| 611 | Error message | views/article-edit.php:308 | Series no longer exists — pick another or set to None. |
| 612 | Error message | views/article-edit.php:315 | Title is required. |
| 613 | Error message | views/article-edit.php:320 | Slug is required. |
| 614 | Error message | views/article-edit.php:327 | Special tag must be empty, principle, or framework. |
| 615 | Error message | views/article-edit.php:339 | Read time must be a whole number of minutes. |
| 616 | Error message | views/article-edit.php:355 | Hero image: `<?= $up['error'] ?>` |
| 617 | Error message | views/article-edit.php:466 | A schedule date/time is required. |
| 618 | Flash message | views/article-edit.php:109 | Advanced to `<?= ucfirst($next) ?>`. |
| 619 | Flash message | views/article-edit.php:113 | Stepped back to `<?= ucfirst($prev) ?>`. |
| 620 | Flash message | views/article-edit.php:115 | Published — live now. |
| 621 | Flash message | views/article-edit.php:117 | Moved back to draft — no longer publicly visible. |
| 622 | Flash message | views/article-edit.php:138 | Slug required before setting up a folder. |
| 623 | Flash message | views/article-edit.php:153 | Folder created at: `<?= $absPath ?>` |
| 624 | Flash message | views/article-edit.php:154 | Folder already exists at: `<?= $absPath ?>` |
| 625 | Flash message | views/article-edit.php:159 | Refreshed. |
| 626 | Flash message | views/article-edit.php:175 | Reverted to `<?= ucfirst($prev) ?>`. |
| 627 | Flash message | views/article-edit.php:216 | Saved. |
| 628 | Flash message | views/article-edit.php:447 | Saved. |
| 629 | Flash message | views/article-edit.php:455 | Published — live now. |
| 630 | Flash message | views/article-edit.php:480 | Scheduled for `<?= date(...) ?>` |
| 631 | Flash message | views/article-edit.php:501 | Published — live at /writing/`<?= $slug ?>` |
| 632 | Breadcrumb | views/article-edit.php:698 | `<nav label> → Edit` |
| 633 | View title | views/article-edit.php:717 | `<?= $titleHdr ?>` (or "Untitled") |
| 634 | View subtitle | views/article-edit.php:719 | Article · `<stageLabel>` · last saved `<updated_at>` |
| 635 | Other (stage variant) | views/article-edit.php:718 | Scheduled for Publish |
| 636 | Tooltip / title (Undo) | views/article-edit.php:729 | Reverts the last advance. Unsaved changes at this stage are lost. |
| 637 | Button label | views/article-edit.php:729 | ↶ Undo |
| 638 | Button label (back) | views/article-edit.php:742-749 | Back to Ideation / Back to Draft Writing / Back to Articles / Back to Journals / Back to Live Sessions / Back to Experiments / Back to list |
| 639 | Status pill (step) | views/article-edit.php:764 | Idea / Concept / Outline / Draft / Published / Scheduled |
| 640 | Tab label | views/article-edit.php:773 | Edit |
| 641 | Tab label | views/article-edit.php:777 | Preview |
| 642 | Tooltip / title (iframe) | views/article-edit.php:784 | Preview — `<?= $titleHdr ?>` |
| 643 | Error message (header) | views/article-edit.php:795 | Couldn't save: |
| 644 | Other (info-box) | views/article-edit.php:810 | Idea stage — capture the title and any early notes. Slug and full editor unlock once you advance to Concept. |
| 645 | Field label | views/article-edit.php:813 | Title |
| 646 | Field required indicator | views/article-edit.php:813 | required |
| 647 | Field label | views/article-edit.php:825 | Idea Notes |
| 648 | Field hint inline | views/article-edit.php:825 | optional |
| 649 | Field placeholder | views/article-edit.php:832 | Jot down what this idea is about, possible angles, references, anything you'd lose otherwise… |
| 650 | Field hint | views/article-edit.php:833 | Private scratchpad — viewable as reference at Concept, then archived. Never appears on the public site. |
| 651 | Field label | views/article-edit.php:837 | Type |
| 652 | Other (select option) | views/article-edit.php:841 | — No type — |
| 653 | Other (select option) | views/article-edit.php:842 | Article |
| 654 | Other (select option) | views/article-edit.php:843 | Journal |
| 655 | Other (select option) | views/article-edit.php:844 | Live Session |
| 656 | Other (select option) | views/article-edit.php:845 | Experiment |
| 657 | Field hint | views/article-edit.php:853 | Required before advancing. You can also drag the card into a type column in Ideation. |
| 658 | Button label | views/article-edit.php:857 | Save `<?= ucfirst($status) ?>` (or "Save changes") |
| 659 | Button label | views/article-edit.php:858 | Cancel |
| 660 | Button label | views/article-edit.php:859 | Delete |
| 661 | Button label | views/article-edit.php:861 | Advance to `<nextStage>` → |
| 662 | Other (schedule banner) | views/article-edit.php:896 | Scheduled for publish on `<date>` · `<countdown>` |
| 663 | Other (countdown placeholder) | views/article-edit.php:897 | computing… |
| 664 | Other (live banner) | views/article-edit.php:904 | Published on `<date>` |
| 665 | Button label | views/article-edit.php:908 | View live ↗ |
| 666 | Field label | views/article-edit.php:916 | Title |
| 667 | Field required indicator | views/article-edit.php:916 | required |
| 668 | Field label | views/article-edit.php:928 | Slug |
| 669 | Field required indicator | views/article-edit.php:928 | required |
| 670 | Field hint (warning) | views/article-edit.php:940 | Warning: This article is published. Changing the slug will create a 301 redirect from the old URL in Phase 11. |
| 671 | Field hint | views/article-edit.php:943 | Lowercase letters, numbers, hyphens. Becomes part of /writing/`<slug>`. |
| 672 | Field label | views/article-edit.php:950 | Summary |
| 673 | Field placeholder | views/article-edit.php:957 | One- to two-sentence summary for cards and meta description. |
| 674 | Field label | views/article-edit.php:963 | Idea Notes |
| 675 | Field label | views/article-edit.php:970 | Concept |
| 676 | Field label | views/article-edit.php:977 | Concept |
| 677 | Field placeholder | views/article-edit.php:984 | What is this piece about? What's the angle? Write enough to know whether it's worth developing further. |
| 678 | Field label | views/article-edit.php:990 | Outline |
| 679 | Field placeholder | views/article-edit.php:997 | Structure the piece — section headers, key points, supporting examples. |
| 680 | Field label | views/article-edit.php:1004 | Body |
| 681 | Other (body source toggle) | views/article-edit.php:1008 | Rich text |
| 682 | Other (body source toggle) | views/article-edit.php:1012 | HTML file |
| 683 | Button label (toolbar) | views/article-edit.php:1021 | B |
| 684 | Tooltip / title | views/article-edit.php:1021 | Bold |
| 685 | Button label (toolbar) | views/article-edit.php:1022 | I |
| 686 | Tooltip / title | views/article-edit.php:1022 | Italic |
| 687 | Button label (toolbar) | views/article-edit.php:1023 | H2 |
| 688 | Tooltip / title | views/article-edit.php:1023 | Heading 2 |
| 689 | Button label (toolbar) | views/article-edit.php:1024 | H3 |
| 690 | Tooltip / title | views/article-edit.php:1024 | Heading 3 |
| 691 | Button label (toolbar) | views/article-edit.php:1025 | • List |
| 692 | Tooltip / title | views/article-edit.php:1025 | Bullet list |
| 693 | Button label (toolbar) | views/article-edit.php:1026 | 1. List |
| 694 | Tooltip / title | views/article-edit.php:1026 | Numbered list |
| 695 | Button label (toolbar) | views/article-edit.php:1027 | Link |
| 696 | Tooltip / title | views/article-edit.php:1027 | Link |
| 697 | Button label (toolbar) | views/article-edit.php:1028 | " Quote |
| 698 | Tooltip / title | views/article-edit.php:1028 | Blockquote |
| 699 | Button label (toolbar) | views/article-edit.php:1029 | Code |
| 700 | Tooltip / title | views/article-edit.php:1029 | Inline code |
| 701 | Button label (toolbar) | views/article-edit.php:1030 | m |
| 702 | Tooltip / title | views/article-edit.php:1030 | Muted word (m) |
| 703 | Button label (toolbar) | views/article-edit.php:1031 | Image |
| 704 | Tooltip / title | views/article-edit.php:1031 | Insert image |
| 705 | Other (aria-label) | views/article-edit.php:1039 | Article body (HTML) |
| 706 | Field hint | views/article-edit.php:1042 | The editor strips any HTML outside the toolbar allowlist on save. |
| 707 | Other (folder status) | views/article-edit.php:1053 | Folder not set up yet |
| 708 | Other (folder status) | views/article-edit.php:1055 | Folder is empty |
| 709 | Other (folder file count) | views/article-edit.php:1057 | `N` file / files |
| 710 | Field hint | views/article-edit.php:1063 | No folder exists yet for this slug. Click Set up folder to create `<path>` on the server. Then drop your .html file into it via SSH/CloudMounter and click Refresh. |
| 711 | Button label | views/article-edit.php:1067 | Set up folder |
| 712 | Other (select option) | views/article-edit.php:1073 | — no .html files in folder — |
| 713 | Other (select option) | views/article-edit.php:1077 | — Pick a file — |
| 714 | Button label | views/article-edit.php:1083 | ↺ Refresh |
| 715 | Field hint | views/article-edit.php:1086 | The article chrome (breadcrumb, title, byline, hero, tags) stays as edited above. Only the body slot is replaced by the contents of the selected file. The file's HTML inherits the public .article-prose typography rules. |
| 716 | Field label | views/article-edit.php:1108 | Hero image |
| 717 | Field hint inline | views/article-edit.php:1109 | optional |
| 718 | Empty state | views/article-edit.php:1117 | No image yet |
| 719 | Tooltip / aria-label | views/article-edit.php:1124 | Remove hero image |
| 720 | Tooltip / title | views/article-edit.php:1125 | Remove hero image |
| 721 | Button label | views/article-edit.php:1141 | Replace image / Choose image |
| 722 | Other (hero controls label) | views/article-edit.php:1148 | Size |
| 723 | Other (aria-label) | views/article-edit.php:1149 | Hero size |
| 724 | Button label (size) | views/article-edit.php:1154 | Column / Wide / Full |
| 725 | Field placeholder | views/article-edit.php:1166 | Caption (optional) |
| 726 | Field hint | views/article-edit.php:1170 | JPEG, PNG, WebP, GIF · max 5 MB. |
| 727 | Field label | views/article-edit.php:1175 | Primary category |
| 728 | Other (select option) | views/article-edit.php:1177 | — None |
| 729 | Field hint | views/article-edit.php:1182 | Drives card colour on /writing/ and the breadcrumb. Manage at /cms/categories. |
| 730 | Field label | views/article-edit.php:1186 | Special tag |
| 731 | Field hint inline | views/article-edit.php:1186 | optional |
| 732 | Other (select option) | views/article-edit.php:1190 | — None |
| 733 | Other (select option) | views/article-edit.php:1190 | Principle |
| 734 | Other (select option) | views/article-edit.php:1190 | Framework |
| 735 | Field label | views/article-edit.php:1199 | Series |
| 736 | Field hint inline | views/article-edit.php:1199 | optional |
| 737 | Other (select option) | views/article-edit.php:1202 | — None |
| 738 | Field hint | views/article-edit.php:1209 | Manage the list at /cms/series. |
| 739 | Field label | views/article-edit.php:1215 | Part number |
| 740 | Field hint inline | views/article-edit.php:1215 | in series |
| 741 | Other (unset hint) | views/article-edit.php:1221 | unset (save once to assign) |
| 742 | Field hint | views/article-edit.php:1224 | Auto-assigned. Drag-reorder parts at /cms/series. |
| 743 | Field label | views/article-edit.php:1240 | Tags |
| 744 | Field hint inline | views/article-edit.php:1240 | optional |
| 745 | Field placeholder | views/article-edit.php:1248 | comma, separated, list |
| 746 | Field hint | views/article-edit.php:1249 | Display only — not used for filtering yet. |
| 747 | Field label | views/article-edit.php:1254 | Read time |
| 748 | Field hint inline | views/article-edit.php:1255 | minutes / minutes · set at Draft |
| 749 | Button label | views/article-edit.php:1271 | ↻ Get estimate |
| 750 | Other (live indicator) | views/article-edit.php:1281 | Live |
| 751 | Button label | views/article-edit.php:1286 | View live ↗ |
| 752 | Field label (sublabel) | views/article-edit.php:1289 | Published |
| 753 | Field hint | views/article-edit.php:1295 | Editable. Changes the publish date displayed on the live page. |
| 754 | Other (checkbox label) | views/article-edit.php:1300 | Show "Updated" date on the article |
| 755 | Tooltip / title | views/article-edit.php:1313 | Reset to actual last update date |
| 756 | Button label | views/article-edit.php:1314 | × |
| 757 | Field hint | views/article-edit.php:1316 | Default: actual last save date. Override to display a different date. |
| 758 | Field label | views/article-edit.php:1324 | Schedule for Publish |
| 759 | Other (checkbox label) | views/article-edit.php:1328 | Schedule for later |
| 760 | Field hint | views/article-edit.php:1338 | Must be at least one minute in the future. The cron promotes scheduled rows to Live at this time. |
| 761 | Button label | views/article-edit.php:1347 | `<?= $saveLabel ?>` |
| 762 | Button label | views/article-edit.php:1348 | Cancel |
| 763 | Button label | views/article-edit.php:1350 | Delete |
| 764 | Button label | views/article-edit.php:1353 | Advance to `<nextStage>` → |
| 765 | Button label | views/article-edit.php:1357 | Publish → |
| 766 | Button label | views/article-edit.php:1358 | Schedule → |
| 767 | Button label | views/article-edit.php:1359 | Schedule Publish |
| 768 | Confirm dialog | views/article-edit.php:1364 | Publish this now? It will go live immediately at the current time. |
| 769 | Button label | views/article-edit.php:1363 | Publish Now |
| 770 | Button label | views/article-edit.php:1370 | Move back to Draft |
| 771 | Button label | views/article-edit.php:1379 | Move to draft |
| 772 | Confirm dialog (unpublish JS) | views/article-edit.php:1565 | Move this article back to draft? It will be removed from the public site immediately. |
| 773 | Prompt dialog (published delete) | views/article-edit.php:1578 | Deleting a published article is permanent.\n\nType the slug to confirm:\n\n  `<slug>` |
| 774 | Alert (slug mismatch) | views/article-edit.php:1583 | Slug did not match — nothing deleted. |
| 775 | Confirm dialog (delete) | views/article-edit.php:1589 | Delete this article? This cannot be undone. |
| 776 | Confirm dialog (hero remove) | views/article-edit.php:1475 | Remove this hero image? You'll need to Save to confirm. |
| 777 | Other (read-time result, JS) | views/article-edit.php:1546 | No body content yet. |
| 778 | Other (read-time result, JS) | views/article-edit.php:1547 | ↻ Get estimate |
| 779 | Other (read-time result, JS) | views/article-edit.php:1552 | Estimate: `<N>` min from `<W>` words |
| 780 | Other (read-time button JS) | views/article-edit.php:1553 | ↻ Refresh |

---

## Article new views — Draft (views/article-new.php) + Idea handler (views/article-new-idea.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 781 | Flash message | views/article-new-idea.php:33 | Session expired — try again. |
| 782 | Flash message | views/article-new-idea.php:41 | Add a title before capturing. |
| 783 | Flash message | views/article-new-idea.php:47 | Title needs at least one letter or number. |
| 784 | Flash message | views/article-new-idea.php:67 | Captured — `<title>` |
| 785 | Other (page title) | views/article-new.php:78 | New Article — alexmchong.ca CMS |
| 786 | Error message (CSRF) | views/article-new.php:35 | Session expired. Reload the page and try again. |
| 787 | Error message | views/article-new.php:41 | Title is required. |
| 788 | Error message | views/article-new.php:49 | Slug could not be generated — provide a title or slug containing letters or numbers. |
| 789 | Flash message | views/article-new.php:62 | Draft created — keep going. |
| 790 | Breadcrumb | views/article-new.php:95 | Articles → New |
| 791 | View title | views/article-new.php:109 | New article |
| 792 | View subtitle | views/article-new.php:110 | Set a title and slug. You can write the body, add a hero, and edit metadata on the next screen. |
| 793 | Button label | views/article-new.php:111 | Cancel |
| 794 | Error message (header) | views/article-new.php:118 | Couldn't save: |
| 795 | Field label | views/article-new.php:131 | Title |
| 796 | Field required indicator | views/article-new.php:131 | required |
| 797 | Field hint | views/article-new.php:141 | Used to auto-generate the slug if you leave it blank. |
| 798 | Field label | views/article-new.php:145 | Slug |
| 799 | Field hint inline | views/article-new.php:145 | optional |
| 800 | Field placeholder | views/article-new.php:154 | auto-from-title |
| 801 | Field hint | views/article-new.php:155 | Lowercase letters, numbers, hyphens. The slug becomes part of the public URL (/writing/`<slug>`) and is permanent once published. |
| 802 | Button label | views/article-new.php:162 | Create draft |
| 803 | Button label | views/article-new.php:163 | Cancel |

---

## Journal edit view (views/journal-edit.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 804 | Other (page title) | views/journal-edit.php:328 | Edit journal: `<key_statement / title>` — alexmchong.ca CMS |
| 805 | Error (404) | views/journal-edit.php:49 | Journal not found. |
| 806 | Error message (CSRF) | views/journal-edit.php:97 | Session expired. Reload the page and try again. |
| 807 | Error message | views/journal-edit.php:132 | Primary category is not a known journal category. |
| 808 | Error message | views/journal-edit.php:137 | Key Statement is required. |
| 809 | Error message | views/journal-edit.php:139 | Key Statement is too long — 280 characters max. |
| 810 | Error message | views/journal-edit.php:147 | Slug is required. |
| 811 | Error message | views/journal-edit.php:213 | A schedule date/time is required. |
| 812 | Flash message | views/journal-edit.php:82 | Advanced to `<next>`. |
| 813 | Flash message | views/journal-edit.php:86 | Stepped back to `<prev>`. |
| 814 | Flash message | views/journal-edit.php:88 | Published — live now. |
| 815 | Flash message | views/journal-edit.php:90 | Moved back to draft — no longer publicly visible. |
| 816 | Flash message | views/journal-edit.php:112 | Reverted to `<prev>`. |
| 817 | Flash message | views/journal-edit.php:197 | Saved. |
| 818 | Flash message | views/journal-edit.php:223 | Scheduled for `<date>` |
| 819 | Flash message | views/journal-edit.php:240 | Published — live at /journal/`<slug>` |
| 820 | Breadcrumb | views/journal-edit.php:371 | `<nav label> → Edit` |
| 821 | View title | views/journal-edit.php:390 | `<titleHdr>` |
| 822 | View subtitle | views/journal-edit.php:393 | Journal · `<stageLabel>` · Entry `<entry#>` · last saved `<updated_at>` |
| 823 | Other (stage variant) | views/journal-edit.php:392 | Scheduled for Publish |
| 824 | Tooltip / title (Undo) | views/journal-edit.php:402 | Reverts the last advance. Unsaved changes at this stage are lost. |
| 825 | Button label | views/journal-edit.php:402 | ↶ Undo |
| 826 | Status pill (step) | views/journal-edit.php:430 | Idea / Draft / Published / Scheduled |
| 827 | Tab label | views/journal-edit.php:439 | Edit |
| 828 | Tab label | views/journal-edit.php:443 | Preview |
| 829 | Tooltip / title (iframe) | views/journal-edit.php:450 | Preview — Journal entry |
| 830 | Error message (header) | views/journal-edit.php:461 | Couldn't save: |
| 831 | Other (schedule banner) | views/journal-edit.php:480 | Scheduled for publish on `<date>` · `<countdown>` |
| 832 | Other (countdown placeholder) | views/journal-edit.php:481 | computing… |
| 833 | Other (live banner) | views/journal-edit.php:488 | Published on `<date>` |
| 834 | Button label | views/journal-edit.php:492 | View live ↗ |
| 835 | Field label | views/journal-edit.php:500 | Slug |
| 836 | Field required indicator | views/journal-edit.php:500 | required |
| 837 | Field hint (warning) | views/journal-edit.php:512 | Warning: Changing the slug on a published journal will create a 301 redirect (Phase 11). |
| 838 | Field hint | views/journal-edit.php:514 | Lowercase letters, numbers, hyphens. Becomes part of /journal/`<slug>`. |
| 839 | Field label | views/journal-edit.php:521 | Idea Notes |
| 840 | Field label | views/journal-edit.php:527 | Key Statement |
| 841 | Field hint inline | views/journal-edit.php:527 | required · max 280 chars |
| 842 | Field placeholder | views/journal-edit.php:535 | One declarative sentence. This is what readers see at the top of the page. |
| 843 | Field hint | views/journal-edit.php:536 | Renders in Instrument Serif italic with a left rule in the category colour. |
| 844 | Field label | views/journal-edit.php:541 | Body |
| 845 | Field hint inline | views/journal-edit.php:541 | optional |
| 846 | Button label (toolbar) | views/journal-edit.php:544 | B |
| 847 | Button label (toolbar) | views/journal-edit.php:545 | I |
| 848 | Button label (toolbar) | views/journal-edit.php:546 | H2 |
| 849 | Button label (toolbar) | views/journal-edit.php:547 | H3 |
| 850 | Button label (toolbar) | views/journal-edit.php:548 | • List |
| 851 | Button label (toolbar) | views/journal-edit.php:549 | 1. List |
| 852 | Button label (toolbar) | views/journal-edit.php:550 | Link |
| 853 | Button label (toolbar) | views/journal-edit.php:551 | " Quote |
| 854 | Button label (toolbar) | views/journal-edit.php:552 | Code |
| 855 | Button label (toolbar) | views/journal-edit.php:553 | m |
| 856 | Other (aria-label) | views/journal-edit.php:561 | Journal body (HTML) |
| 857 | Field hint | views/journal-edit.php:563 | Key Statement alone is enough — body is for expansion. |
| 858 | Field label | views/journal-edit.php:570 | Primary category |
| 859 | Other (select option) | views/journal-edit.php:572 | — None |
| 860 | Field hint | views/journal-edit.php:577 | Drives card colour on /journal/ and the per-category entry counter. |
| 861 | Field label | views/journal-edit.php:581 | Tags |
| 862 | Field hint inline | views/journal-edit.php:581 | optional |
| 863 | Field placeholder | views/journal-edit.php:589 | comma, separated, list |
| 864 | Field hint | views/journal-edit.php:590 | Display only — not used for filtering yet. |
| 865 | Field label | views/journal-edit.php:595 | Entry number |
| 866 | Other (readonly value) | views/journal-edit.php:596 | Entry `<NNN>` |
| 867 | Field hint | views/journal-edit.php:597 | Assigned on first publish. Permanent identifier. |
| 868 | Other (live indicator) | views/journal-edit.php:606 | Live |
| 869 | Button label | views/journal-edit.php:611 | View live ↗ |
| 870 | Field label (sublabel) | views/journal-edit.php:614 | Published |
| 871 | Field hint | views/journal-edit.php:620 | Editable. Changes the publish date displayed on the live page. |
| 872 | Other (checkbox label) | views/journal-edit.php:625 | Show "Updated" date on the article |
| 873 | Tooltip / title | views/journal-edit.php:638 | Reset to actual last update date |
| 874 | Button label | views/journal-edit.php:639 | × |
| 875 | Field hint | views/journal-edit.php:641 | Default: actual last save date. Override to display a different date. |
| 876 | Field label | views/journal-edit.php:649 | Schedule for Publish |
| 877 | Other (checkbox label) | views/journal-edit.php:653 | Schedule for later |
| 878 | Field hint | views/journal-edit.php:663 | Must be at least one minute in the future. The cron promotes scheduled rows to Live at this time. |
| 879 | Button label | views/journal-edit.php:672 | `<?= $saveLabel ?>` |
| 880 | Button label | views/journal-edit.php:673 | Cancel |
| 881 | Button label | views/journal-edit.php:675 | Delete |
| 882 | Button label | views/journal-edit.php:678 | Publish → |
| 883 | Button label | views/journal-edit.php:679 | Schedule → |
| 884 | Button label | views/journal-edit.php:680 | Schedule Publish |
| 885 | Confirm dialog | views/journal-edit.php:685 | Publish this now? It will go live immediately at the current time. |
| 886 | Button label | views/journal-edit.php:684 | Publish Now |
| 887 | Button label | views/journal-edit.php:691 | Move back to Draft |
| 888 | Button label | views/journal-edit.php:700 | Move to draft |
| 889 | Confirm dialog (unpublish JS) | views/journal-edit.php:740 | Move this journal back to draft? It will be removed from the public site immediately. |
| 890 | Prompt dialog (published delete) | views/journal-edit.php:750 | Deleting a published journal is permanent.\n\nType the slug to confirm:\n\n  `<slug>` |
| 891 | Alert (slug mismatch) | views/journal-edit.php:755 | Slug did not match — nothing deleted. |
| 892 | Confirm dialog (delete) | views/journal-edit.php:761 | Delete this journal? This cannot be undone. |
| 893 | Button (back) | views/journal-edit.php:415 | Back to Ideation / Draft Writing / Articles / Journals / Live Sessions / Experiments / Back to list |
| 894 | Other (back to list) | views/journal-edit.php:418 | Back to list |

---

## Journal new view (views/journal-new.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 895 | Other (page title) | views/journal-new.php:68 | New Journal — alexmchong.ca CMS |
| 896 | Error message (CSRF) | views/journal-new.php:29 | Session expired. Reload the page and try again. |
| 897 | Error message | views/journal-new.php:35 | Working title is required. |
| 898 | Error message | views/journal-new.php:40 | Slug could not be generated — provide a title or slug containing letters or numbers. |
| 899 | Flash message | views/journal-new.php:52 | Draft created — write your Key Statement. |
| 900 | Breadcrumb | views/journal-new.php:85 | Journals → New |
| 901 | View title | views/journal-new.php:99 | New journal |
| 902 | View subtitle | views/journal-new.php:100 | Set a working title and slug. The Key Statement and body are on the next screen. |
| 903 | Button label | views/journal-new.php:101 | Cancel |
| 904 | Error message (header) | views/journal-new.php:108 | Couldn't save: |
| 905 | Field label | views/journal-new.php:121 | Working title |
| 906 | Field required indicator | views/journal-new.php:121 | required |
| 907 | Field hint | views/journal-new.php:131 | Internal label so you can find this row in lists. Not rendered on the public page — the Key Statement is. |
| 908 | Field label | views/journal-new.php:135 | Slug |
| 909 | Field hint inline | views/journal-new.php:135 | optional |
| 910 | Field placeholder | views/journal-new.php:144 | auto-from-title |
| 911 | Field hint | views/journal-new.php:145 | Becomes part of /journal/`<slug>`. Permanent once published. |
| 912 | Button label | views/journal-new.php:151 | Create draft |
| 913 | Button label | views/journal-new.php:153 | Cancel |

---

## Live session edit view (views/live-session-edit.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 914 | Other (page title) | views/live-session-edit.php:422 | Edit live session: `<title>` — alexmchong.ca CMS |
| 915 | Error (404) | views/live-session-edit.php:51 | Live session not found. |
| 916 | Error message (CSRF) | views/live-session-edit.php:99 | Session expired. Reload the page and try again. |
| 917 | Error message | views/live-session-edit.php:145 | Primary category is not a known live-session category. |
| 918 | Error message | views/live-session-edit.php:150 | Title is required. |
| 919 | Error message | views/live-session-edit.php:158 | Slug is required. |
| 920 | Error message | views/live-session-edit.php:170 | Event date is required. |
| 921 | Error message | views/live-session-edit.php:172 | Event date could not be parsed. |
| 922 | Error message | views/live-session-edit.php:176 | Start time must be in HH:MM format. |
| 923 | Error message | views/live-session-edit.php:180 | End time must be in HH:MM format. |
| 924 | Error message | views/live-session-edit.php:184 | End time requires a start time. Clear End or set Start. |
| 925 | Error message | views/live-session-edit.php:190 | End time must be after the start time. |
| 926 | Error message | views/live-session-edit.php:195 | Attendance must be in-person, remote, or blank. |
| 927 | Error message | views/live-session-edit.php:215 | Hero image: `<?= $up['error'] ?>` |
| 928 | Error message | views/live-session-edit.php:283 | A schedule date/time is required. |
| 929 | Flash message | views/live-session-edit.php:84 | Advanced to `<next>`. |
| 930 | Flash message | views/live-session-edit.php:87 | Stepped back to `<prev>`. |
| 931 | Flash message | views/live-session-edit.php:90 | Published — live now. |
| 932 | Flash message | views/live-session-edit.php:92 | Moved back to draft — no longer publicly visible. |
| 933 | Flash message | views/live-session-edit.php:114 | Reverted to `<prev>`. |
| 934 | Flash message | views/live-session-edit.php:268 | Saved. |
| 935 | Flash message | views/live-session-edit.php:275 | Published — live now. |
| 936 | Flash message | views/live-session-edit.php:294 | Scheduled for `<date>` |
| 937 | Flash message | views/live-session-edit.php:311 | Published — live at /live-sessions/`<slug>` |
| 938 | Breadcrumb | views/live-session-edit.php:463 | `<nav label> → Edit` |
| 939 | View title | views/live-session-edit.php:480 | `<titleHdr>` |
| 940 | View subtitle | views/live-session-edit.php:483 | Live Session · `<stageLabel>`(· PAST) · last saved `<updated_at>` |
| 941 | Other (past suffix) | views/live-session-edit.php:481 |  · PAST |
| 942 | Other (stage variant) | views/live-session-edit.php:482 | Scheduled for Publish |
| 943 | Tooltip / title (Undo) | views/live-session-edit.php:492 | Reverts the last advance. Unsaved changes at this stage are lost. |
| 944 | Button label | views/live-session-edit.php:492 | ↶ Undo |
| 945 | Button (back) | views/live-session-edit.php:505 | Back to Ideation / Draft Writing / Articles / Journals / Live Sessions / Experiments |
| 946 | Other (back to list) | views/live-session-edit.php:508 | Back to list |
| 947 | Status pill (step) | views/live-session-edit.php:520 | Idea / Draft / Published / Scheduled |
| 948 | Tab label | views/live-session-edit.php:529 | Edit |
| 949 | Tab label | views/live-session-edit.php:533 | Preview |
| 950 | Tooltip / title (iframe) | views/live-session-edit.php:540 | Preview — Live session |
| 951 | Error message (header) | views/live-session-edit.php:551 | Couldn't save: |
| 952 | Other (schedule banner) | views/live-session-edit.php:571 | Scheduled for publish on `<date>` · `<countdown>` |
| 953 | Other (countdown placeholder) | views/live-session-edit.php:572 | computing… |
| 954 | Other (live banner) | views/live-session-edit.php:579 | Published on `<date>` |
| 955 | Button label | views/live-session-edit.php:583 | View live ↗ |
| 956 | Field label | views/live-session-edit.php:591 | Slug |
| 957 | Field required indicator | views/live-session-edit.php:591 | required |
| 958 | Field hint (warning) | views/live-session-edit.php:603 | Warning: Changing the slug on a published live-session will create a 301 redirect (Phase 11). |
| 959 | Field hint | views/live-session-edit.php:605 | Lowercase letters, numbers, hyphens. Becomes part of /live-sessions/`<slug>`. |
| 960 | Field label | views/live-session-edit.php:612 | Idea Notes |
| 961 | Field hint | views/live-session-edit.php:614 | Private scratchpad from the Idea stage. Archived once published. |
| 962 | Field label | views/live-session-edit.php:619 | Event Title |
| 963 | Field required indicator | views/live-session-edit.php:619 | required |
| 964 | Field label | views/live-session-edit.php:631 | Summary |
| 965 | Field hint inline | views/live-session-edit.php:631 | optional |
| 966 | Field placeholder | views/live-session-edit.php:638 | One-line deck below the title. |
| 967 | Field label | views/live-session-edit.php:642 | Event Details |
| 968 | Field hint inline | views/live-session-edit.php:642 | date required · times optional · Eastern (Toronto) timezone |
| 969 | Field label (sublabel) | views/live-session-edit.php:645 | Date |
| 970 | Field required indicator | views/live-session-edit.php:645 | required |
| 971 | Field label (sublabel) | views/live-session-edit.php:655 | Start |
| 972 | Field label (sublabel) | views/live-session-edit.php:664 | End |
| 973 | Field label (sublabel) | views/live-session-edit.php:675 | Location |
| 974 | Field hint inline | views/live-session-edit.php:675 | city / region |
| 975 | Field placeholder | views/live-session-edit.php:683 | e.g. Toronto, ON |
| 976 | Field label (sublabel) | views/live-session-edit.php:686 | Venue |
| 977 | Field hint inline | views/live-session-edit.php:686 | subline · optional |
| 978 | Field placeholder | views/live-session-edit.php:694 | e.g. Centre for Social Innovation · 16 seats |
| 979 | Field hint | views/live-session-edit.php:697 | Publish Date is separate — that's stamped when the session goes live. Past events stay live with a PAST badge. |
| 980 | Field label | views/live-session-edit.php:701 | Format Pills |
| 981 | Field hint inline | views/live-session-edit.php:701 | all optional · leave blank to hide each pill |
| 982 | Field label (sublabel) | views/live-session-edit.php:704 | Cost |
| 983 | Field placeholder | views/live-session-edit.php:712 | Free · Fee · $300 · … |
| 984 | Field label (sublabel) | views/live-session-edit.php:715 | Attendance |
| 985 | Other (select option) | views/live-session-edit.php:720 | — No pill — |
| 986 | Other (select option) | views/live-session-edit.php:721 | In-Person |
| 987 | Other (select option) | views/live-session-edit.php:722 | Remote |
| 988 | Field label (sublabel) | views/live-session-edit.php:726 | Custom |
| 989 | Field placeholder | views/live-session-edit.php:734 | Any short tag |
| 990 | Field label | views/live-session-edit.php:740 | Body |
| 991 | Field hint inline | views/live-session-edit.php:740 | optional |
| 992 | Button label (toolbar) | views/live-session-edit.php:743-752 | B / I / H2 / H3 / • List / 1. List / Link / " Quote / Code / m |
| 993 | Other (aria-label) | views/live-session-edit.php:760 | Live session body (HTML) |
| 994 | Field label | views/live-session-edit.php:774 | Hero image |
| 995 | Field hint inline | views/live-session-edit.php:775 | optional |
| 996 | Empty state | views/live-session-edit.php:782 | No image yet |
| 997 | Tooltip / aria-label | views/live-session-edit.php:786 | Remove hero image |
| 998 | Tooltip / title | views/live-session-edit.php:787 | Remove hero image |
| 999 | Button label | views/live-session-edit.php:801 | Replace image / Choose image |
| 1000 | Other (hero controls label) | views/live-session-edit.php:807 | Size |
| 1001 | Other (aria-label) | views/live-session-edit.php:808 | Hero size |
| 1002 | Button label (size) | views/live-session-edit.php:813 | Column / Wide / Full |
| 1003 | Field placeholder | views/live-session-edit.php:824 | Caption (optional) |
| 1004 | Field hint | views/live-session-edit.php:828 | JPEG, PNG, WebP, GIF · max 5 MB. |
| 1005 | Field label | views/live-session-edit.php:832 | Primary category |
| 1006 | Other (select option) | views/live-session-edit.php:834 | — None |
| 1007 | Field hint | views/live-session-edit.php:839 | Drives card colour on /live-sessions/. |
| 1008 | Field label | views/live-session-edit.php:843 | Tags |
| 1009 | Field hint inline | views/live-session-edit.php:843 | optional |
| 1010 | Field placeholder | views/live-session-edit.php:851 | workshop, talk, … |
| 1011 | Field hint | views/live-session-edit.php:852 | Display only — not used for filtering yet. |
| 1012 | Other (live indicator) | views/live-session-edit.php:860 | Live |
| 1013 | Button label | views/live-session-edit.php:865 | View live ↗ |
| 1014 | Field label (sublabel) | views/live-session-edit.php:868 | Published |
| 1015 | Field hint | views/live-session-edit.php:874 | Editable. Changes the publish date displayed on the live page. |
| 1016 | Other (checkbox label) | views/live-session-edit.php:879 | Show "Updated" date on the article |
| 1017 | Tooltip / title | views/live-session-edit.php:892 | Reset to actual last update date |
| 1018 | Button label | views/live-session-edit.php:893 | × |
| 1019 | Field hint | views/live-session-edit.php:895 | Default: actual last save date. Override to display a different date. |
| 1020 | Field label | views/live-session-edit.php:903 | Schedule for Publish |
| 1021 | Other (checkbox label) | views/live-session-edit.php:907 | Schedule for later |
| 1022 | Field hint | views/live-session-edit.php:917 | Must be at least one minute in the future. The cron promotes scheduled rows to Live at this time. |
| 1023 | Button label | views/live-session-edit.php:926 | `<?= $saveLabel ?>` |
| 1024 | Button label | views/live-session-edit.php:927 | Cancel |
| 1025 | Button label | views/live-session-edit.php:929 | Delete |
| 1026 | Button label | views/live-session-edit.php:932 | Publish → |
| 1027 | Button label | views/live-session-edit.php:933 | Schedule → |
| 1028 | Button label | views/live-session-edit.php:934 | Schedule Publish |
| 1029 | Confirm dialog | views/live-session-edit.php:939 | Publish this now? It will go live immediately at the current time. |
| 1030 | Button label | views/live-session-edit.php:938 | Publish Now |
| 1031 | Button label | views/live-session-edit.php:945 | Move back to Draft |
| 1032 | Button label | views/live-session-edit.php:954 | Move to draft |
| 1033 | Confirm dialog (unpublish JS) | views/live-session-edit.php:992 | Move this session back to draft? It will be removed from the public site immediately. |
| 1034 | Prompt dialog (published delete) | views/live-session-edit.php:1002 | Deleting a published live session is permanent.\n\nType the slug to confirm:\n\n  `<slug>` |
| 1035 | Alert (slug mismatch) | views/live-session-edit.php:1007 | Slug did not match — nothing deleted. |
| 1036 | Confirm dialog (delete) | views/live-session-edit.php:1013 | Delete this live session? This cannot be undone. |
| 1037 | Confirm dialog (hero remove) | views/live-session-edit.php:1079 | Remove this hero image? You'll need to Save to confirm. |

---

## Live session new view (views/live-session-new.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 1038 | Other (page title) | views/live-session-new.php:65 | New Live Session — alexmchong.ca CMS |
| 1039 | Error message (CSRF) | views/live-session-new.php:26 | Session expired. Reload the page and try again. |
| 1040 | Error message | views/live-session-new.php:32 | Title is required. |
| 1041 | Error message | views/live-session-new.php:37 | Slug could not be generated — provide a title or slug containing letters or numbers. |
| 1042 | Flash message | views/live-session-new.php:49 | Draft created — add the event details. |
| 1043 | Breadcrumb | views/live-session-new.php:82 | Live Sessions → New |
| 1044 | View title | views/live-session-new.php:96 | New live session |
| 1045 | View subtitle | views/live-session-new.php:97 | Set a title and slug. Event details, format pills, and body are on the next screen. |
| 1046 | Button label | views/live-session-new.php:98 | Cancel |
| 1047 | Error message (header) | views/live-session-new.php:105 | Couldn't save: |
| 1048 | Field label | views/live-session-new.php:118 | Title |
| 1049 | Field required indicator | views/live-session-new.php:118 | required |
| 1050 | Field hint | views/live-session-new.php:128 | e.g. "Designing for Human Agency". Shown on the public page and in /live-sessions listings. |
| 1051 | Field label | views/live-session-new.php:132 | Slug |
| 1052 | Field hint inline | views/live-session-new.php:132 | optional |
| 1053 | Field placeholder | views/live-session-new.php:141 | auto-from-title |
| 1054 | Field hint | views/live-session-new.php:142 | Becomes part of /live-sessions/`<slug>`. Permanent once published. |
| 1055 | Button label | views/live-session-new.php:148 | Create draft |
| 1056 | Button label | views/live-session-new.php:149 | Cancel |

---

## Experiment edit view (views/experiment-edit.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 1057 | Other (page title) | views/experiment-edit.php:407 | Edit experiment: `<title>` — alexmchong.ca CMS |
| 1058 | Error (404) | views/experiment-edit.php:51 | Experiment not found. |
| 1059 | Error message (CSRF) | views/experiment-edit.php:100 | Session expired. Reload the page and try again. |
| 1060 | Error message | views/experiment-edit.php:169 | Primary category is not a known experiment category. |
| 1061 | Error message | views/experiment-edit.php:174 | Title is required. |
| 1062 | Error message | views/experiment-edit.php:179 | Slug is required. |
| 1063 | Error message | views/experiment-edit.php:187 | Read time must be a whole number of minutes. |
| 1064 | Error message | views/experiment-edit.php:208 | Selected file no longer exists in the folder. Refresh and pick again. |
| 1065 | Error message | views/experiment-edit.php:274 | A schedule date/time is required. |
| 1066 | Flash message | views/experiment-edit.php:85 | Advanced to `<next>`. |
| 1067 | Flash message | views/experiment-edit.php:88 | Stepped back to `<prev>`. |
| 1068 | Flash message | views/experiment-edit.php:91 | Published — live now. |
| 1069 | Flash message | views/experiment-edit.php:93 | Moved back to draft — no longer publicly visible. |
| 1070 | Flash message | views/experiment-edit.php:109 | Slug required before setting up a folder. |
| 1071 | Flash message | views/experiment-edit.php:117 | Folder created at: `<absPath>` |
| 1072 | Flash message | views/experiment-edit.php:118 | Folder already exists at: `<absPath>` |
| 1073 | Flash message | views/experiment-edit.php:120 | Could not set up folder. |
| 1074 | Flash message | views/experiment-edit.php:123 | Refreshed. |
| 1075 | Flash message | views/experiment-edit.php:140 | Reverted to `<prev>`. |
| 1076 | Flash message | views/experiment-edit.php:258 | Saved. |
| 1077 | Flash message | views/experiment-edit.php:265 | Published — live now. |
| 1078 | Flash message | views/experiment-edit.php:284 | Scheduled for `<date>` |
| 1079 | Flash message | views/experiment-edit.php:301 | Published — live at /experiments/`<slug>` |
| 1080 | Breadcrumb | views/experiment-edit.php:451 | `<nav label> → Edit` |
| 1081 | View title | views/experiment-edit.php:468 | `<titleHdr>` |
| 1082 | View subtitle | views/experiment-edit.php:470 | Experiment · `<template>` · `<stageLabel>` · last saved `<updated_at>` |
| 1083 | Other (stage variant) | views/experiment-edit.php:469 | Scheduled for Publish |
| 1084 | Tooltip / title (Undo) | views/experiment-edit.php:480 | Reverts the last advance. |
| 1085 | Button label | views/experiment-edit.php:480 | ↶ Undo |
| 1086 | Button (back) | views/experiment-edit.php:493 | Back to Ideation / Draft Writing / Articles / Journals / Live Sessions / Experiments |
| 1087 | Other (back to list) | views/experiment-edit.php:496 | Back to list |
| 1088 | Status pill (step) | views/experiment-edit.php:508 | Idea / Draft / Published / Scheduled |
| 1089 | Tab label | views/experiment-edit.php:517 | Edit |
| 1090 | Tab label | views/experiment-edit.php:521 | Preview |
| 1091 | Tooltip / title (iframe) | views/experiment-edit.php:528 | Preview — Experiment |
| 1092 | Error message (header) | views/experiment-edit.php:539 | Couldn't save: |
| 1093 | Other (schedule banner) | views/experiment-edit.php:558 | Scheduled for publish on `<date>` · `<countdown>` |
| 1094 | Other (countdown placeholder) | views/experiment-edit.php:559 | computing… |
| 1095 | Other (live banner) | views/experiment-edit.php:566 | Published on `<date>` |
| 1096 | Button label | views/experiment-edit.php:570 | View live ↗ |
| 1097 | Field label | views/experiment-edit.php:578 | Slug |
| 1098 | Field required indicator | views/experiment-edit.php:578 | required |
| 1099 | Field hint (warning) | views/experiment-edit.php:590 | Warning: Changing the slug on a published experiment will create a 301 redirect (Phase 11). |
| 1100 | Field hint | views/experiment-edit.php:592 | Lowercase letters, numbers, hyphens. Becomes part of /experiments/`<slug>`. |
| 1101 | Field label | views/experiment-edit.php:599 | Idea Notes |
| 1102 | Field hint | views/experiment-edit.php:601 | Private scratchpad from the Idea stage. Archived once published. |
| 1103 | Field label | views/experiment-edit.php:606 | Experiment Title |
| 1104 | Field required indicator | views/experiment-edit.php:606 | required |
| 1105 | Field label | views/experiment-edit.php:618 | Summary |
| 1106 | Field hint inline | views/experiment-edit.php:618 | optional |
| 1107 | Field placeholder | views/experiment-edit.php:625 | One-line deck below the title. |
| 1108 | Field label | views/experiment-edit.php:630 | Body |
| 1109 | Other (body source toggle) | views/experiment-edit.php:634 | Rich text |
| 1110 | Other (body source toggle) | views/experiment-edit.php:638 | HTML body |
| 1111 | Other (body source toggle) | views/experiment-edit.php:642 | HTML swap |
| 1112 | Button label (toolbar) | views/experiment-edit.php:651-660 | B / I / H2 / H3 / • List / 1. List / Link / " Quote / Code / m |
| 1113 | Other (aria-label) | views/experiment-edit.php:668 | Experiment body (HTML) |
| 1114 | Field hint | views/experiment-edit.php:670 | Article-format body. Strips HTML outside the toolbar allowlist on save. |
| 1115 | Field hint (folder html-body) | views/experiment-edit.php:676 | The article chrome stays; the body slot reads the selected file. |
| 1116 | Field hint (folder html-swap) | views/experiment-edit.php:677 | Full-page passthrough — readfile() serves at /experiments/`<slug>` with no template wrapper. |
| 1117 | Other (folder status) | views/experiment-edit.php:686 | Folder not set up yet |
| 1118 | Other (folder status) | views/experiment-edit.php:688 | Folder is empty |
| 1119 | Other (folder file count) | views/experiment-edit.php:690 | `N` file / files |
| 1120 | Field hint | views/experiment-edit.php:696 | No folder exists yet for this slug. Click Set up folder to create `<path>` on the server. Then drop your .html files into it via SSH/CloudMounter and click Refresh. |
| 1121 | Button label | views/experiment-edit.php:700 | Set up folder |
| 1122 | Other (select option) | views/experiment-edit.php:706 | — no .html files in folder — |
| 1123 | Other (select option) | views/experiment-edit.php:710 | — Pick a file — |
| 1124 | Button label | views/experiment-edit.php:716 | ↺ Refresh |
| 1125 | Field label | views/experiment-edit.php:729 | Primary category |
| 1126 | Other (select option) | views/experiment-edit.php:731 | — None |
| 1127 | Field hint | views/experiment-edit.php:736 | Drives card colour on /experiments/ (Prototype vs Concept dark variant). |
| 1128 | Field label | views/experiment-edit.php:740 | Read time |
| 1129 | Field hint inline | views/experiment-edit.php:740 | manual |
| 1130 | Other (unit suffix) | views/experiment-edit.php:752 | min |
| 1131 | Field hint | views/experiment-edit.php:754 | Manual estimate. Optional. |
| 1132 | Field label | views/experiment-edit.php:758 | Tags |
| 1133 | Field hint inline | views/experiment-edit.php:758 | optional |
| 1134 | Field placeholder | views/experiment-edit.php:766 | prototype, tool, … |
| 1135 | Field hint | views/experiment-edit.php:767 | Display only — not used for filtering yet. |
| 1136 | Other (live indicator) | views/experiment-edit.php:775 | Live |
| 1137 | Button label | views/experiment-edit.php:780 | View live ↗ |
| 1138 | Field label (sublabel) | views/experiment-edit.php:783 | Published |
| 1139 | Field hint | views/experiment-edit.php:789 | Editable. Changes the publish date displayed on the live page. |
| 1140 | Other (checkbox label) | views/experiment-edit.php:794 | Show "Updated" date on the article |
| 1141 | Tooltip / title | views/experiment-edit.php:807 | Reset to actual last update date |
| 1142 | Button label | views/experiment-edit.php:808 | × |
| 1143 | Field hint | views/experiment-edit.php:810 | Default: actual last save date. Override to display a different date. |
| 1144 | Field label | views/experiment-edit.php:818 | Schedule for Publish |
| 1145 | Other (checkbox label) | views/experiment-edit.php:822 | Schedule for later |
| 1146 | Field hint | views/experiment-edit.php:832 | Must be at least one minute in the future. The cron promotes scheduled rows to Live at this time. |
| 1147 | Button label | views/experiment-edit.php:841 | `<?= $saveLabel ?>` |
| 1148 | Button label | views/experiment-edit.php:842 | Cancel |
| 1149 | Button label | views/experiment-edit.php:844 | Delete |
| 1150 | Button label | views/experiment-edit.php:847 | Publish → |
| 1151 | Button label | views/experiment-edit.php:848 | Schedule → |
| 1152 | Button label | views/experiment-edit.php:849 | Schedule Publish |
| 1153 | Confirm dialog | views/experiment-edit.php:854 | Publish this now? It will go live immediately at the current time. |
| 1154 | Button label | views/experiment-edit.php:853 | Publish Now |
| 1155 | Button label | views/experiment-edit.php:860 | Move back to Draft |
| 1156 | Button label | views/experiment-edit.php:869 | Move to draft |
| 1157 | Confirm dialog (unpublish JS) | views/experiment-edit.php:939 | Move this experiment back to draft? It will be removed from the public site immediately. |
| 1158 | Prompt dialog (published delete) | views/experiment-edit.php:949 | Deleting a published experiment is permanent.\n\nType the slug to confirm:\n\n  `<slug>` |
| 1159 | Alert (slug mismatch) | views/experiment-edit.php:954 | Slug did not match — nothing deleted. |
| 1160 | Confirm dialog (delete) | views/experiment-edit.php:960 | Delete this experiment? This cannot be undone. |

---

## Experiment new view (views/experiment-new.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 1161 | Other (page title) | views/experiment-new.php:82 | New Experiment — alexmchong.ca CMS |
| 1162 | Error message (CSRF) | views/experiment-new.php:38 | Session expired. Reload the page and try again. |
| 1163 | Error message | views/experiment-new.php:45 | Pick a valid body source. |
| 1164 | Error message | views/experiment-new.php:48 | Title is required. |
| 1165 | Error message | views/experiment-new.php:53 | Slug could not be generated — provide a title or slug containing letters or numbers. |
| 1166 | Flash message | views/experiment-new.php:66 | Draft created. |
| 1167 | Breadcrumb | views/experiment-new.php:99 | Experiments → New |
| 1168 | View title | views/experiment-new.php:113 | New experiment |
| 1169 | View subtitle | views/experiment-new.php:114 | Pick a template, give it a title and slug. Body / folder picker live on the edit screen. |
| 1170 | Button label | views/experiment-new.php:115 | Cancel |
| 1171 | Error message (header) | views/experiment-new.php:122 | Couldn't save: |
| 1172 | Field label | views/experiment-new.php:135 | Body source |
| 1173 | Field required indicator | views/experiment-new.php:135 | required |
| 1174 | Other (select option) | views/experiment-new.php:137 | Rich text body (article-format) |
| 1175 | Other (select option) | views/experiment-new.php:138 | HTML body file (article chrome + html file) |
| 1176 | Other (select option) | views/experiment-new.php:139 | HTML swap (full passthrough, no template) |
| 1177 | Field hint | views/experiment-new.php:141 | Rich text uses the TipTap editor (same blocks as Articles). HTML body keeps the article chrome and replaces the body slot with a hand-built file from /content/experiment/`<slug>`/. HTML swap serves the file directly with no template wrapper. All three are switchable later from the edit screen. |
| 1178 | Field label | views/experiment-new.php:150 | Experiment Title |
| 1179 | Field required indicator | views/experiment-new.php:150 | required |
| 1180 | Field hint | views/experiment-new.php:160 | e.g. "Decision scaffolding tool". Shown in /experiments and on the public page. |
| 1181 | Field label | views/experiment-new.php:164 | Slug |
| 1182 | Field hint inline | views/experiment-new.php:164 | optional |
| 1183 | Field placeholder | views/experiment-new.php:173 | auto-from-title |
| 1184 | Field hint | views/experiment-new.php:174 | Becomes part of /experiments/`<slug>`. Permanent once published. |
| 1185 | Button label | views/experiment-new.php:180 | Create draft |
| 1186 | Button label | views/experiment-new.php:181 | Cancel |

---

## Page edit view (views/page-edit.php)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 1187 | Other (page title) | views/page-edit.php:222 | `<filename>` — alexmchong.ca CMS |
| 1188 | Breadcrumb | views/page-edit.php:288 | Pages → `<filename>` |
| 1189 | Error (404 plain) | views/page-edit.php:35 | Unknown page slug: `<slug>` |
| 1190 | Error message (CSRF) | views/page-edit.php:44 | Session expired. Reload the page and try again. |
| 1191 | Error message | views/page-edit.php:52 | A name is required. |
| 1192 | Error message | views/page-edit.php:79 | A name is required. |
| 1193 | Error message | views/page-edit.php:88 | A name is required for the duplicate. |
| 1194 | Error message | views/page-edit.php:92 | Could not duplicate. |
| 1195 | Error message | views/page-edit.php:107 | Could not publish (publishing is only available for header / footer partials). |
| 1196 | Error message | views/page-edit.php:117 | Unknown action. |
| 1197 | Flash message | views/page-edit.php:56 | Mock created. |
| 1198 | Flash message | views/page-edit.php:63 | Saved. |
| 1199 | Flash message | views/page-edit.php:74 | Metadata saved. |
| 1200 | Flash message | views/page-edit.php:82 | Renamed. |
| 1201 | Flash message | views/page-edit.php:94 | Duplicated. |
| 1202 | Flash message | views/page-edit.php:100 | Mock deleted. |
| 1203 | Flash message | views/page-edit.php:104 | Published — this mock is now live on staging. |
| 1204 | Flash message | views/page-edit.php:110 | Un-published — fell back to file. |
| 1205 | Flash message | views/page-edit.php:114 | Reverted to file. |
| 1206 | View title | views/page-edit.php:301 | `<filename>` |
| 1207 | View subtitle (partial) | views/page-edit.php:302 | Layout partial. Mocks can be published to override the file on staging — file remains canonical until you publish. |
| 1208 | View subtitle (page) | views/page-edit.php:303 | Marketing page. Mock-only sandbox: the CMS never writes to disk. Files remain canonical. |
| 1209 | Button label | views/page-edit.php:305 | ← Back to Pages |
| 1210 | Error message (header) | views/page-edit.php:312 | Couldn't save: |
| 1211 | Tab label | views/page-edit.php:325 | Metadata |
| 1212 | Tab label | views/page-edit.php:327 | Body HTML |
| 1213 | Tab label | views/page-edit.php:329 | Preview |
| 1214 | Other (version label) | views/page-edit.php:338 | Version: |
| 1215 | Other (select option) | views/page-edit.php:340 | Live Version (file on disk) |
| 1216 | Other (live tag in option) | views/page-edit.php:343 | [LIVE] |
| 1217 | Other (override note) | views/page-edit.php:349 | ↪ Override active: `<name>` |
| 1218 | Other (unsaved indicator) | views/page-edit.php:351 | (unsaved changes) |
| 1219 | Button label | views/page-edit.php:355 | Preview Live ↗ |
| 1220 | Confirm dialog | views/page-edit.php:357 | Revert to file? This un-publishes all mocks for this slug. |
| 1221 | Button label | views/page-edit.php:361 | Revert to file |
| 1222 | Button label | views/page-edit.php:364 | + New Mock |
| 1223 | Button label | views/page-edit.php:366 | Rename |
| 1224 | Button label | views/page-edit.php:367 | Duplicate |
| 1225 | Button label | views/page-edit.php:368 | Preview ↗ |
| 1226 | Confirm dialog | views/page-edit.php:369 | Delete mock "`<?= $e($current_mock['name']) ?>`"? This cannot be undone. |
| 1227 | Button label | views/page-edit.php:374 | Delete version |
| 1228 | Button label | views/page-edit.php:383 | Un-publish |
| 1229 | Confirm dialog | views/page-edit.php:386 | Publish "`<?= $e($current_mock['name']) ?>`"? This will override `<?= $e($file_row['filename']) ?>` on staging. |
| 1230 | Button label | views/page-edit.php:391 | Publish → |
| 1231 | Button label | views/page-edit.php:395 | Save |
| 1232 | Other (readonly notice) | views/page-edit.php:401 | This is the on-disk file. The CMS never writes here — click + New Mock to start editing. |
| 1233 | Tooltip / title (iframe) | views/page-edit.php:420 | Preview — `<filename>` |
| 1234 | Field label | views/page-edit.php:444 | Meta title |
| 1235 | Other (char count format) | views/page-edit.php:447 | `N` / 60 |
| 1236 | Field label | views/page-edit.php:450 | Meta description |
| 1237 | Other (char count format) | views/page-edit.php:453 | `N` / 160 |
| 1238 | Field label | views/page-edit.php:456 | og:image URL |
| 1239 | Field placeholder | views/page-edit.php:458 | /uploads/og/about.jpg |
| 1240 | Other (char count hint) | views/page-edit.php:459 | Recommended 1200×630. Paths starting with / resolve relative to site root. |
| 1241 | Field label | views/page-edit.php:462 | og:type |
| 1242 | Field label | views/page-edit.php:469 | twitter:card |
| 1243 | Button label | views/page-edit.php:477 | Save metadata |
| 1244 | Block label | views/page-edit.php:480 | Unfurl preview |
| 1245 | Prompt dialog | views/page-edit.php:593 | Name this mock (e.g. "Tighter intro"): |
| 1246 | Prompt dialog | views/page-edit.php:600 | Rename to: |
| 1247 | Prompt dialog | views/page-edit.php:607 | Name the duplicate: |
| 1248 | Other (duplicate name suffix) | views/page-edit.php:607 |  (copy) |
| 1249 | Other (relative time) | views/page-edit.php:204 | just now |
| 1250 | Other (relative time) | views/page-edit.php:205 | `Nm ago` |
| 1251 | Other (relative time) | views/page-edit.php:206 | `Nh ago` |
| 1252 | Other (relative time) | views/page-edit.php:207 | `Nd ago` |

---

## Auth views (login, account, unlock-account)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 1253 | Other (page title) | login.php:58 | CMS Login – Staging — alexmchong.ca |
| 1254 | Other (page title) | login.php:58 | CMS Login — alexmchong.ca |
| 1255 | View title | login.php:57 | CMS Login – Staging |
| 1256 | View title | login.php:57 | CMS Login |
| 1257 | Error message | login.php:31 | Form expired. Please try again. |
| 1258 | Flash message | login.php:61 | `<?= $flash ?>` (dynamic) |
| 1259 | Field label | login.php:93 | Email |
| 1260 | Field label | login.php:96 | Password |
| 1261 | Button label | login.php:99 | Sign in |
| 1262 | View subtitle (staging) | login.php:104 | Staging tools |
| 1263 | Other (staging copy) | login.php:105 | Locked out after too many bad guesses? Clear it and try again. Staging only — the prod login never shows this button. |
| 1264 | Button label | login.php:108 | Unlock account |
| 1265 | Flash message | unlock-account.php:54 | Form expired — try again. |
| 1266 | Flash message | unlock-account.php:61 | Account unlocked. You can sign in now. |
| 1267 | Flash message | unlock-account.php:62 | Nothing to unlock — no users in the database. |
| 1268 | Other (page title) | account.php:50 | Account — alexmchong.ca CMS |
| 1269 | Error message | account.php:25 | Form expired. Please try again. |
| 1270 | Flash message (success) | account.php:31 | Password updated. |
| 1271 | Other (nav link) | account.php:68 | ← Dashboard |
| 1272 | View title | account.php:69 | Account |
| 1273 | Other (meta) | account.php:70 | Signed in as `<email>` |
| 1274 | Field label | account.php:74 | Current password |
| 1275 | Field label | account.php:77 | New password |
| 1276 | Field label | account.php:80 | Confirm new password |
| 1277 | Field hint | account.php:83 | At least 12 characters, with one uppercase, one lowercase, and one digit. |
| 1278 | Button label | account.php:84 | Change password |

---

## Cross-cutting / partials (shared text via partials or shared JS)

| # | Surface category | Location (file:line) | Current text |
|---|---|---|---|
| 1279 | Empty state (default) | partials/table.php:27 | No entries yet. |
| 1280 | Other (logout form) | partials/topbar.php:74-77 | (no visible text other than "Log out", already enumerated above) |
| 1281 | Aria-label (sidebar) | partials/sidebar.php:38 | CMS navigation |

