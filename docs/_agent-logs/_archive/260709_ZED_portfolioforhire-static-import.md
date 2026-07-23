NAME: ZED
PURPOSE: Import the Webflow portfolio-for-hire page as a self-hosted static file at /portfolioforhire/
STATUS: closed
LAST TOUCHED: 2026-07-17

---

## Objective: Static portfolio import + production deploy (started 2026-07-09)

**Intent:** Replace the CMS redirect `/portfolioforhire` → Webflow with a self-hosted
static copy of the portfolio, cleaned up and served directly from alexmchong.ca. Alex
edits content in Webflow; periodic manual syncs pull in deltas. No CMS integration —
manual deploy process.

### Timeline

- Attempting: CSS cleanup — strip Webflow boilerplate, filter dead rules by class whitelist, localise CDN background images
  → SUCCEEDED — 7,591 → 2,011 lines (74% reduction); 4 CDN assets downloaded to assets/ (ico-map-pin.svg, ico-circle-bg-1/2/3.png)

- Attempting: HTML cleanup — remove Webflow noise, format with indentation + section comments
  → SUCCEEDED — 1 minified line → 621 readable lines; 12 section comments injected

- Attempting: Restore Webflow IX2 animations after cleanup stripped data-w-id attributes
  → BLOCKED — animations froze; data-w-id values had been stripped during formatting
  → SUCCEEDED — restore_wids.py re-injected all 15 data-w-id values from webflow-snapshot.html; data-wf-domain/page/site/status also restored on <html> tag

- Attempting: Copy cleaned files to site/portfolioforhire/ and deploy to staging
  → BLOCKED — bin/deploy.sh is an explicit assembly script, not a blanket rsync; portfolioforhire/ was absent from it so nothing was deployed
  → SUCCEEDED — added explicit mkdir-p + cp -R block to deploy.sh; deployed to staging

- Attempting: Remove CMS redirect /portfolioforhire → Webflow (DB entry)
  → SUCCEEDED — Alex deleted via /cms/redirects UI (2026-07-09); Apache serves the real directory directly, bypassing index.php anyway

- Attempting: Hide Webflow badge
  → SUCCEEDED — .w-webflow-badge { display: none !important; } added to styles.css

- Attempting: Fix CSS path bug — local background images not loading (circle numbers, map pin)
  → BLOCKED — paths were url("assets/ico-circle-bg-1.png") relative to HTML root, but CSS file is inside assets/ so correct path is url("ico-circle-bg-1.png")
  → SUCCEEDED — corrected all 4 paths with sed

- Attempting: Fix text rendering issues from HTML formatter (apostrophe split, &amp; without spaces, inline element spacing gaps)
  → SUCCEEDED — fixed Hudson's Bay apostrophe split; added spaces around all unspaced &amp; instances (13 fixed); fixed missing spaces around <strong>/<em>/<a> throughout Q&A and history sections; fixed QuestionsAnswers gap

- Attempting: Add animated grain/noise texture overlay to editorial photos
  → SUCCEEDED — tex-noise.png (256×256 from Framer CDN) added to assets/; CSS @keyframes grain + ::after pseudo-elements on .last-project-image, .last-project-image-mini, .bio-right-image; steps(10) timing gives discrete flicker vs smooth slide; opacity 0.08

- Attempting: Deploy to production
  → SUCCEEDED — 2026-07-09 (initial deploy); 2026-07-13 (grain + text fixes); 2026-07-13 (asset updates + .htaccess)

- Attempting: Block public directory listing of /portfolioforhire/assets/
  → SUCCEEDED — assets/.htaccess with Options -Indexes; verified 403 on production 2026-07-13

- Attempting: Webflow delta assessment — diff live Webflow vs local copy
  → SUCCEEDED — 2026-07-09 diff run; results logged in docs/design-mockups/_portfolioforhire/DELTA-MIGRATION.md §"Pending delta — assessed 2026-07-09"

### Outstanding (as of 2026-07-17, updated)

- Pull pending Webflow delta into site/portfolioforhire/index.html — new role text ("Director of User Experience, UX Practice & Advisory, Outwitly Inc."), 13+ years, 3 new photos (6a50… CDN prefix) — **HANDED OFF**, still open (documented in DELTA-MIGRATION.md, ready to pick up any time).
- ~~Nothing committed to git this session~~ — **RESOLVED** by TIM, per Alex's
  direction to centralize all open agents (2026-07-17). Verified all 8
  changed/new files byte-for-byte against production before committing.
  See commit `b867aa6`.
