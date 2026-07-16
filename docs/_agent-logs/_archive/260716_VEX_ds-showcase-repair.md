NAME: VEX
PURPOSE: Diagnose and fix ds.alexmchong.ca (public DS mirror) — CMS/Applied/CSS Library tabs broken
STATUS: closed
LAST TOUCHED: 2026-07-16

---

## Objective: fix ds.alexmchong.ca CMS/Applied/CSS Library breakage (started 2026-07-15)

**Intent:** Alex reported https://ds.alexmchong.ca/ "all broken" for the CMS, Applied,
and CSS Library tabs. Diagnose root cause and fix, with staging verification and
explicit go-ahead before each production deploy.

**Note:** this log was created retroactively, partway through the session, after
Alex asked why it hadn't been created up front per SESSION-HYGIENE.md §1. See
Outstanding — the git-commit gap below is a direct consequence of the same miss.

### Timeline

- Attempting: diagnose why ds.alexmchong.ca CMS/Applied/CSS-Library tabs were broken
  → SUCCEEDED (diagnosis) — root cause: `ds.alexmchong.ca` is DreamHost-mapped to
  `alexmchong.ca/_ds/` (a subfolder, not a real deploy target — never touched by
  `bin/deploy.sh`, which only knows `staging`/`prod`). It inherits production's
  `.htaccess` (written for the site root), and the showcase iframe pages
  (`site/_design-system/showcase/*.html`) referenced `/cms/...` and `/_ds/...` as
  root-relative paths — which don't exist under that subfolder mapping, so requests
  fell through to a missing `/index.php`, then a missing `/error.php`, producing raw
  Apache 500s.
- Attempting: present two fix options (rewrite showcase asset paths to fully-qualified
  production URLs, vs. remap the DreamHost docroot) — Alex chose option 1.
  → SUCCEEDED — decision made in chat, no server/doc change yet.
- Attempting: rewrite `/cms/...` and `/_ds/...` references in 16
  `site/_design-system/showcase/*.html` files to `https://alexmchong.ca/...`
  → SUCCEEDED — deployed to staging via `bin/deploy.sh staging`
    (backup: `/home/alexmchong/_backups/deploy-20260715-230711/`), verified file
    content on the server directly (staging is Basic-Auth gated, no credentials
    available this session). **Not committed to git.**
  → SUCCEEDED — deployed to prod via `bin/deploy.sh prod` after explicit Alex
    approval (backup: `/home/alexmchong/_backups/deploy-20260715-230828/`).
    Verified via curl + Playwright screenshot against the live
    `ds.alexmchong.ca`. **Not committed to git.**
- Attempting: full tab-by-tab visual check (Playwright + local Chromium install)
  → SUCCEEDED — Foundations/Components/Patterns/Pages/CMS/Applied all render;
    found a second, separate bug: CSS Library tab rendered blank.
- Attempting: diagnose CSS Library blank-panel bug
  → SUCCEEDED (diagnosis) — `.cl-frame` (CSS Library) and `#cms-frame` (CMS tab)
    iframes measure their own content height via a `fit()` function that only
    runs on the iframe's `load` event. The "root" CSS Library pane and the CMS
    pane both set `src` directly in the initial HTML, so `load` fires while
    still hidden behind the default "Foundations" tab — `scrollHeight` reads 0
    at that point and is never recomputed. The `live-frame` iframes (Applied tab)
    don't have this bug because their script re-fits on every topbar-tab click;
    `.cl-frame`/`#cms-frame` never got that same treatment. Confirmed pre-existing
    (unrelated to the path fix above) via `git log` on the affected file
    (`8ddb8c5`, Phase 22.6b, 2026-06-06).
- Attempting: fix by adding a re-fit-on-tab-click listener for both `.cl-frame`
  and `#cms-frame`, mirroring the existing `live-frame` pattern, in
  `site/_design-system/index.html`
  → SUCCEEDED — verified locally first (served `site/_design-system/` via
    `python3 -m http.server 8765`, replayed the exact bug-triggering click path
    with Playwright: frame height went from stuck-at-`0px` to correctly
    `3530px`/`11809px`). Deployed to staging, then prod after explicit approval
    (backup: `/home/alexmchong/_backups/deploy-20260716-...`). Verified live.
    **Not committed to git.**
- Attempting: full re-check after both fixes — Alex flagged a third issue:
  "Shell" demo section on the CMS tab had a long blank stretch below the
  filter bar.
  → SUCCEEDED (diagnosis) — the Shell demo (`site/_design-system/showcase/cms.html`)
    deliberately shows only chrome (topbar/sidebar/header/filter-bar, no table
    rows — real rows are shown later in "Containment"). Its `.layout` container
    had `min-height: 320px` and no cap, so flexbox `align-items: stretch`
    (default) made the empty content column match the sidebar's full nav-list
    height, producing a long blank rectangle. Confirmed pre-existing (June 6
    commit, unrelated to prior fixes) via `git diff` scoping.
  → SUCCEEDED — added `max-height: 420px` to `.shell-demo .layout` in
    `site/_design-system/showcase/cms.html`. Verified locally with Playwright
    (height capped at exactly 420px, screenshot confirmed clean layout).
    Deployed to staging, then prod after explicit approval. Verified live via
    curl. **Not committed to git.**

### Outstanding (as of 2026-07-16)
- **Git commit for all of the above** — three rounds of prod deploys, zero
  commits. 17 files modified (`site/_design-system/index.html` +
  16 `site/_design-system/showcase/*.html`), currently sitting as uncommitted
  local changes while already live on staging and production. This is the
  exact failure mode SESSION-HYGIENE.md §0 documents. — **PICKED UP by TIM**
  (Alex explicitly directed this, 2026-07-16: "look at everything from VEX
  and make sure that's all closed out"). Verified all 17 files match
  production byte-for-byte before touching anything.
- Agent log itself was created retroactively (this file), not at the start of
  non-trivial work as SESSION-HYGIENE.md §1 specifies. — **RESOLVED**, see
  `feedback_session_hygiene_trigger` memory (written this session to prevent
  recurrence).
