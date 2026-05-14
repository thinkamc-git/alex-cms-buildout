# Legacy Routes — alexmchong.ca

**Status:** Inventory of every URL on the current alexmchong.ca that needs to keep resolving after the new site goes live. The handover plan is in `BUILD-PLAN.md` Phase 3 (immediate) and Phase 10 (migration into the CMS).

---

## 1. What this document is

The current alexmchong.ca has folders and files in its webroot that have been accumulating since the site was first set up. Each one corresponds to a URL that may have been shared in an email, on social media, on a business card, or indexed by a search engine. Those URLs must continue to resolve when the new site is deployed — otherwise we break inbound links and harm SEO.

This file lists every legacy route, where it currently lives on the server, and where it should redirect to under the new site. It is the single source of truth for the stopgap routing that lives in `.htaccess` during Phase 3 and migrates into the CMS `redirects` table during Phase 10.

**Alex's job:** keep this file up to date as you discover more routes on the current production server. The five below are confirmed; there may be more.

---

## 2. Known legacy redirects

These URLs were live on alexmchong.ca and need to keep resolving.

**External redirects** — point to off-site destinations:

| Path on alexmchong.ca | Redirects to | HTTP code | Notes |
|---|---|---|---|
| `/portfolioforhire/` | `https://alexmchong-portfolio.webflow.io/` | 302 | Webflow-hosted portfolio. Could move; keep 302 until destination is permanent. |
| `/portfolio/` | `https://alexmchong-portfolio.webflow.io/` | 302 | Friendlier duplicate of `/portfolioforhire/`. Discovered in the Phase 1 production audit (2026-05-12) — was not in the original Phase 0 inventory. |
| `/research/` | `https://alexmchong.notion.site/alexmchong/Alex-s-Master-s-Thesis-NTUT-3ef25dfbb1e145bb8ed9176171828f73` | 302 | Notion page. URL may change if the Notion workspace is reorganized. |
| `/talks/` | `https://alexmchong.notion.site/alexmchong/Alex-M-Chong-Design-Talks-455f32067df04918a18875321c3cc9fa` | 302 | Notion page. Same volatility risk. |
| `/meet/` | `https://calendly.com/alexmchong/meet` | 302 | Calendly booking link. Could move to a different scheduler later. |
| `/linkedin/` | `https://linkedin.com/in/alexmchong/` | 302 | Stable; could safely be 301 if you want better SEO inheritance. |

**Internal redirects** — point at the new static marketing pages. Added in the Phase 1 production audit (2026-05-12) to preserve ~7-year-old inbound links to URLs that used to serve Webflow-built pages. Each gets removed in Phase 10 when (and if) the CMS takes over the bare path as a real route.

| Path on alexmchong.ca | Redirects to | HTTP code | Notes |
|---|---|---|---|
| `/about/` | `/about.html` | 302 | Old Webflow page replaced by the new static `about.html`. Phase 10 CMS-served `/about/` removes this rule. |
| `/coaching/` | `/coaching.html` | 302 | Same shape as `/about/`. |
| `/cv/` | `/resume.html` | 302 | The new static page is named `resume.html`, not `cv.html`. |
| `/community/` | `/` | 302 | No successor page. Send to the landing rather than 404 inbound links. |
| `/consulting/` | `/` | 302 | Same as `/community/`. |

**Why 302 (temporary) and not 301 (permanent)?** A 301 tells browsers and search engines "this redirect is forever — cache it, update your records." Once a browser has seen a 301, it stops asking the server about that URL — so if the destination changes later, the user is stuck on the stale cached one. A 302 says "for now, look over there" and the browser re-checks every visit. For URLs that point at services we don't fully control (Webflow, Notion, Calendly), 302 is safer. Upgrade individual entries to 301 only after the destination has been stable for 6+ months.

---

## 3. Confirmed-removed paths

Folders / files that previously existed on alexmchong.ca and were **moved out of the webroot** during the Phase 1 production cleanup (2026-05-13). They were not permanently deleted — they were relocated to `/home/alexmchong/alexmchong.ca.backup/` (outside the webroot, so web-unreachable) so the old contents can be inspected or recovered without an SFTP-based restore. Once Alex is confident nothing of value is in there, the backup folder can be deleted in a later session.

**Inventory status:** Re-walked during the Phase 1 production deploy (2026-05-12 audit; 2026-05-13 cleanup). The original Phase 0 walkthrough significantly understated the contents of the production webroot — it found only five legacy folders, but the actual webroot is a multi-era graveyard layered as follows:

- **Early-2000s era:** plain-PHP retro homepage variants (`index.php`, `index0.php`, `index1.php`, `index2.php`, `desktop2.php`), retro `404.html`, retro `favicon.gif`.
- **2019 era:** Webflow-built site (`/about/`, `/coaching/`, `/cv/`, `/community/`, `/consulting/`, original `index.html`) plus asset folders (`_layout/`, `images/`, `js/`, `css/`) and the JS-redirect shells (`/research/`, `/talks/`, `/linkedin/`).
- **Recent era:** modern hand-coded experiments (`/_archive/`, `/_labs/`, `/_files/`) plus a scaffolding directory listing (`/_cms/`) and an empty `/m/` folder.
- **DreamHost system:** `.dh-diag`, `.ftpquota`, `.well-known/` — left alone, not our files.

The `index.html` homepage gets **replaced** by the new static landing (not deleted).

| Path | Why safe to delete | Confirmed by Alex |
|---|---|---|
| `index.php` | Old PHP homepage variant — early-2000s aesthetic. Not linked from anywhere current. | ✓ 2026-05-12 |
| `index0.php` | Older still — table-based 2000s layout. | ✓ 2026-05-12 |
| `index1.php` | Same era as `index0.php`. | ✓ 2026-05-12 |
| `index2.php` | Same era. | ✓ 2026-05-12 |
| `desktop2.php` | Old desktop-only landing variant. | ✓ 2026-05-12 |
| `404.html` | Retro custom 404. Apache reverts to its default 404 page after deletion. Phase 13 ships a proper themed 404. | ✓ 2026-05-12 |
| `favicon.gif` | Old branding favicon. New `_layout/favicon.png` is referenced from the new pages. | ✓ 2026-05-12 |
| `_layout/` (old) | Old asset folder from the 2019 site. Our upload replaces it with the new `_layout/`. | ✓ 2026-05-12 |
| `m/` | Empty (two 0-byte favicons). Origin unclear. | ✓ 2026-05-12 |
| `js/`, `css/`, `images/` | Asset folders referenced only by the 2019 Webflow pages we're deleting. Orphaned after `/about/`, `/coaching/` etc. go. | ✓ 2026-05-12 |
| `_cms/` | Leftover scaffolding from an earlier CMS exploration. Directory listing was web-visible — potential info-leak. | ✓ 2026-05-12 |
| `portfolioforhire/` | Legacy redirect shell. `.htaccess` rule now handles `/portfolioforhire/`. | ✓ 2026-05-12 |
| `portfolio/` | Same — `.htaccess` rule now handles `/portfolio/`. | ✓ 2026-05-12 |
| `research/`, `talks/`, `linkedin/` | Client-side JS redirect shells. `.htaccess` rule replaces them with faster server-side 302. | ✓ 2026-05-12 |
| `meet/` | Same — `.htaccess` rule handles `/meet/`. | ✓ 2026-05-12 |
| `about/`, `coaching/`, `cv/` | Old Webflow pages. `.htaccess` rule now redirects to the new static `.html` counterparts. | ✓ 2026-05-12 |
| `community/`, `consulting/` | Old Webflow pages with no successor; `.htaccess` redirects to `/`. | ✓ 2026-05-12 |

**Not deleted (kept live as-is):** `/_archive/`, `/_labs/`, `/_files/` — Alex's recent experiments. They remain at their current URLs and are not part of the CMS scope.

---

## 4. Newly preserved paths (new site)

Paths that the new alexmchong.ca will own directly (CMS-served), so they don't need a legacy redirect — they get content. Listed here for completeness so we don't accidentally write a redirect that conflicts with a CMS route.

Per `CMS-STRUCTURE.md` §14:

```
/                        Public landing (the new site)
/about/                  About page (CMS or static)
/coaching/               Coaching page (CMS or static)
/work-with-me/           Services page (CMS or static)
/resume/                 Resume page (CMS or static)
/writing/                Articles index
/writing/[slug]          Article
/journal/                Journals index
/journal/[slug]          Journal entry
/live-sessions/          Live sessions index
/live-sessions/[slug]    Live session
/experiments/            Experiments index
/experiments/[slug]      Experiment
/series/[slug]/          Series index
/digital-garden/         Curated editorial index
/thoughts/               Articles-only listing
/subscribe/              Newsletter signup (Phase 10)
/cms/                    Admin panel (also at cms.alexmchong.ca)
/_ds/                    Design system showcase (also at ds.alexmchong.ca)
/admin/                  301 → /cms/ (alias)
```

If a legacy path collides with a future CMS path (e.g. you discover an old `/about/` directory that was a separate site), **the legacy redirect wins** during the transition — note it here and we'll plan the migration deliberately.

---

## 5. Implementation

### Phase 1 — `.htaccess` (live)

The webroot `.htaccess` carries every entry in §2 as a plain Apache rewrite rule. The canonical files are `site/.htaccess` (production) and `deploy/staging.htaccess` (staging — same rules plus Basic Auth). The shape is:

```apache
RewriteEngine On

# External redirects
RewriteRule ^portfolioforhire/?$ https://alexmchong-portfolio.webflow.io/ [R=302,L]
RewriteRule ^portfolio/?$ https://alexmchong-portfolio.webflow.io/ [R=302,L]
RewriteRule ^research/?$ https://alexmchong.notion.site/... [R=302,L]
RewriteRule ^talks/?$ https://alexmchong.notion.site/... [R=302,L]
RewriteRule ^meet/?$ https://calendly.com/alexmchong/meet [R=302,L]
RewriteRule ^linkedin/?$ https://linkedin.com/in/alexmchong/ [R=302,L]

# Internal redirects from old folder URLs to new static pages
RewriteRule ^about/?$ /about.html [R=302,L]
RewriteRule ^coaching/?$ /coaching.html [R=302,L]
RewriteRule ^cv/?$ /resume.html [R=302,L]
RewriteRule ^community/?$ / [R=302,L]
RewriteRule ^consulting/?$ / [R=302,L]

# Landing canonicalization
RewriteRule ^landing\.html$ / [R=302,L]
```

### Phase 3 — adds the PHP front controller

When PHP lands in Phase 3, the same `.htaccess` gains a front-controller fallback at the bottom:

```apache
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```

The legacy rules sit **above** the fallback so they short-circuit before PHP is invoked. No PHP, no database hit, fast redirect.

### Phase 10 — `redirects` table (long-term)

When the CMS Redirects management UI lands (Phase 10), every entry in §2 above migrates into the `redirects` table per `CMS-STRUCTURE.md` §9. The `.htaccess` rules are deleted; the front controller starts checking the table on each request. From that point on, Alex edits redirects through the CMS admin UI instead of by editing `.htaccess` over FTP.

Phase 10 also adds a `status_code` column to the `redirects` table (migration `000X_add_status_code_to_redirects.sql`) so the 301 vs 302 choice can be edited per-row.

---

## 6. Operational notes

- **Trailing slashes.** All rules above match both `/path/` and `/path` (the `/?$` regex). Pick one in your communications going forward (with-slash is recommended for category-style URLs).
- **Case sensitivity.** Apache rewrite rules on DreamHost are case-sensitive by default. If you've shared `/Research/` historically, add a second rule for the uppercased variant.
- **Subdomains.** These redirects live only on the root `alexmchong.ca` and `staging.alexmchong.ca` webroots. `cms.alexmchong.ca` and `ds.alexmchong.ca` (and `staging.*` equivalents) do not need them.
- **Verification.** Use `curl -I -L https://staging.alexmchong.ca/portfolioforhire/` from your terminal to confirm a `302 Found` with the correct `Location:` header before deploying to production.
