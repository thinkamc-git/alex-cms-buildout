# Legacy Routes — alexmchong.ca

**Status:** Inventory of every URL on the current alexmchong.ca that needs to keep resolving after the new site goes live. The handover plan is in `BUILD-PLAN.md` Phase 3 (immediate) and Phase 10 (migration into the CMS).

---

## 1. What this document is

The current alexmchong.ca has folders and files in its webroot that have been accumulating since the site was first set up. Each one corresponds to a URL that may have been shared in an email, on social media, on a business card, or indexed by a search engine. Those URLs must continue to resolve when the new site is deployed — otherwise we break inbound links and harm SEO.

This file lists every legacy route, where it currently lives on the server, and where it should redirect to under the new site. It is the single source of truth for the stopgap routing that lives in `.htaccess` during Phase 3 and migrates into the CMS `redirects` table during Phase 10.

**Alex's job:** keep this file up to date as you discover more routes on the current production server. The five below are confirmed; there may be more.

---

## 2. Known legacy redirects (external)

These five URLs were live on alexmchong.ca and need to point to their external destinations.

| Path on alexmchong.ca | Redirects to | HTTP code | Notes |
|---|---|---|---|
| `/portfolioforhire/` | `https://alexmchong-portfolio.webflow.io/` | 302 | Webflow-hosted portfolio. Could move; keep 302 until destination is permanent. |
| `/research/` | `https://alexmchong.notion.site/alexmchong/Alex-s-Master-s-Thesis-NTUT-3ef25dfbb1e145bb8ed9176171828f73` | 302 | Notion page. URL may change if the Notion workspace is reorganized. |
| `/talks/` | `https://alexmchong.notion.site/alexmchong/Alex-M-Chong-Design-Talks-455f32067df04918a18875321c3cc9fa` | 302 | Notion page. Same volatility risk. |
| `/meet/` | `https://calendly.com/alexmchong/meet` | 302 | Calendly booking link. Could move to a different scheduler later. |
| `/linkedin/` | `https://linkedin.com/in/alexmchong/` | 302 | Stable; could safely be 301 if you want better SEO inheritance. |

**Why 302 (temporary) and not 301 (permanent)?** A 301 tells browsers and search engines "this redirect is forever — cache it, update your records." Once a browser has seen a 301, it stops asking the server about that URL — so if the destination changes later, the user is stuck on the stale cached one. A 302 says "for now, look over there" and the browser re-checks every visit. For URLs that point at services we don't fully control (Webflow, Notion, Calendly), 302 is safer. Upgrade individual entries to 301 only after the destination has been stable for 6+ months.

---

## 3. Confirmed-removed paths

Folders / files that previously existed on alexmchong.ca and are being **deleted** during the Phase 3 production cleanup. These do not need redirects because no real URL was ever attached to them in public — they were internal scaffolding.

**Inventory status (Phase 0):** Complete. A walkthrough of the current production webroot found only the five redirect subfolders already captured in §2 above (`portfolioforhire/`, `research/`, `talks/`, `meet/`, `linkedin/`) plus the homepage `index.html`. There are no additional internal scaffolding folders to delete.

The current homepage `index.html` is **not** a removed path — it gets replaced (not deleted) by the new static site in Phase 1. See `BUILD-PLAN.md` Phase 1 for the deployment plan.

| Path | Why safe to delete | Confirmed by Alex |
|---|---|---|
| _(none — inventory complete, no additional folders found)_ | | ✓ 2026-05-12 |

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

### Phase 3 — `.htaccess` (immediate)

The webroot `.htaccess` is the first thing the new infrastructure ships, and it carries the legacy redirects as plain Apache rewrite rules:

```apache
RewriteEngine On

# Legacy redirects — see LEGACY-ROUTES.md
RewriteRule ^portfolioforhire/?$ https://alexmchong-portfolio.webflow.io/ [R=302,L]
RewriteRule ^research/?$ https://alexmchong.notion.site/alexmchong/Alex-s-Master-s-Thesis-NTUT-3ef25dfbb1e145bb8ed9176171828f73 [R=302,L]
RewriteRule ^talks/?$ https://alexmchong.notion.site/alexmchong/Alex-M-Chong-Design-Talks-455f32067df04918a18875321c3cc9fa [R=302,L]
RewriteRule ^meet/?$ https://calendly.com/alexmchong/meet [R=302,L]
RewriteRule ^linkedin/?$ https://linkedin.com/in/alexmchong/ [R=302,L]

# Front controller — everything else falls through to PHP
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
```

The legacy rules sit **above** the front-controller fallback so they short-circuit before PHP is invoked. No PHP, no database hit, fast redirect.

### Phase 10 — `redirects` table (long-term)

When the CMS Redirects management UI lands (Phase 10), every entry in §2 above migrates into the `redirects` table per `CMS-STRUCTURE.md` §9. The `.htaccess` rules are deleted; the front controller starts checking the table on each request. From that point on, Alex edits redirects through the CMS admin UI instead of by editing `.htaccess` over FTP.

Phase 10 also adds a `status_code` column to the `redirects` table (migration `000X_add_status_code_to_redirects.sql`) so the 301 vs 302 choice can be edited per-row.

---

## 6. Operational notes

- **Trailing slashes.** All rules above match both `/path/` and `/path` (the `/?$` regex). Pick one in your communications going forward (with-slash is recommended for category-style URLs).
- **Case sensitivity.** Apache rewrite rules on DreamHost are case-sensitive by default. If you've shared `/Research/` historically, add a second rule for the uppercased variant.
- **Subdomains.** These redirects live only on the root `alexmchong.ca` and `staging.alexmchong.ca` webroots. `cms.alexmchong.ca` and `ds.alexmchong.ca` (and `staging.*` equivalents) do not need them.
- **Verification.** Use `curl -I -L https://staging.alexmchong.ca/portfolioforhire/` from your terminal to confirm a `302 Found` with the correct `Location:` header before deploying to production.
