# alexmchong.ca CMS — Build Plan

**Status:** Phase 0 ready to start.
**Owner:** Alex M. Chong.
**Companion documents:** `CMS-STRUCTURE.md` (system spec), `ENGINEERING.md` (code conventions), `AUTH-SECURITY.md` (auth + security spec, drafted before Phase 4), `DEPLOYMENT.md` (deploy workflow, drafted at Phase 1, extended at Phase 3), `docs/onboarding/` (Phase 0 lessons).

---

## 1. How to read this document

This is the master plan for the build. It defines **what gets built in what order**, with each phase ending in a small thing you can see working before the next phase starts.

The plan is structured as **vertical slices** — each content type (Articles, Journals, Live Sessions, Experiments) is built end-to-end (admin + editor + public render) before the next one begins. The first Article goes live publicly around hour 30, not hour 50. Each subsequent type ships as a discrete public milestone.

Two audiences:

- **Alex** — read this top-to-bottom once to get the shape. After that, refer to whichever phase you're in and ignore the rest.
- **A Claude instance** (e.g. inside VS Code via Claude Code) — see the Session brief pattern below. Read **only the brief for the current phase plus the files it points to**. Do not preload the whole documentation chain.

Each phase has the same shape: a **Session brief** (autonomy tier → decisions → reading list → guardrails → exit handoff) followed by **Goal**, **Scope**, **Deliverables**, **Verification**, **Out of scope**.

### 1.1 The Session brief pattern

Each phase opens with a Session brief block. **It is the entire reading contract for that phase.** A fresh Claude Code session should:

1. Open this file (`BUILD-PLAN.md`).
2. Find the current phase in §3 (the first unchecked box).
3. Read **only** that phase's section — starting with the Session brief.
4. Open **only** the files the brief lists under "Read at start." Do not chain through `CLAUDE.md` → `CMS-STRUCTURE.md` → `ENGINEERING.md` → `BLOCKS.md` etc. unless the brief explicitly names them.
5. Touch **only** the files listed under "Touch." Treat "Don't touch" as a hard fence.
6. Before closing the session, complete the "On exit" checklist.

### 1.2 The Decisions block (autonomy unlock)

Every Session brief opens with a **Decisions to capture before starting** block. This is Alex's first action — *not* Claude's.

**Workflow:**

1. Alex opens the current phase's brief.
2. Alex reviews the **Decisions** block. Each row has a sensible default already filled in. Alex edits any he wants to override, then saves the file.
3. Alex starts the Claude Code session.
4. Claude reads the brief (Decisions answers included), then executes top to bottom without asking for clarification.
5. When Claude reaches the Verification checklist, Alex steps back in for the in-depth review.

This pattern shifts decision-making from mid-stream interruptions to upfront prep. A semi-auto phase with 7 decisions pre-answered becomes 4 hours of unattended building. Without this, the same phase becomes 7 round-trips with Claude waiting on each.

For phases where no real decisions need answering, the block reads "None — refer to [authoritative spec section]."

### 1.3 Autonomy tiers

Each phase is tagged with an autonomy tier:

- **Auto** — pure execution, no decisions, start it and walk away. Claude reads the brief, builds, reports back. Alex reviews at Verification.
- **Semi-auto** — Alex answers the Decisions block, then the session runs unattended. Same review pattern at Verification.
- **Manual** — Alex's hands or judgment are needed throughout. The Decisions block still exists to front-load anything answerable, but the session is interactive by nature.

**Roughly 70% of the build (Auto + Semi-auto) can run with minimal interaction once decisions are captured.** Manual phases are the moments that genuinely benefit from Alex being in the loop — setup, first dynamic render, navigation cutover, accessibility judgment.

---

## 2. The big picture

We are building a custom PHP + MySQL content management system for a single-author personal website. The author signs in, captures and develops ideas through a pipeline, publishes four content types (Articles, Journals, Live Sessions, Experiments), and the public site renders the result.

**Three environments**, all on DreamHost shared hosting:

| Environment | URL | Server folder | PHP | Purpose |
|---|---|---|---|---|
| Local | (file paths on Alex's Mac) | `~/Claude/alex-cms-buildout/` (and a local PHP server) | 8.2+ | Where code is written |
| Staging | `https://staging.alexmchong.ca/` | `/alexmchong.ca/_staging/` | 8.4 | Test changes before they go live; password-protected |
| Production | `https://alexmchong.ca/` | `/alexmchong.ca/` | 8.2 | The public site |

**Three sub-apps inside each environment:**

| Path | Eventual subdomain | What lives here |
|---|---|---|
| `/` (root) | `alexmchong.ca` | The public site — articles, journals, indexes |
| `/cms/` | `cms.alexmchong.ca` | The admin panel (CMS) |
| `/_ds/` | `ds.alexmchong.ca` | The design system reference (dynamic showcase) |

The CMS login form is at `/cms/login`. The path `/admin/` is a convenience redirect to `/cms/`.

**Seventeen phases (15 active + 2 deferred), structured as vertical slices.** Phase 0 is setup; Phase 1 deploys the static marketing site (first public ship); Phases 2–5 are the internal foundation (CSS split, PHP plumbing, auth, admin shell); Phases 6a/6b/7 are Articles end-to-end (second public ship); Phases 8–10 are the other three content types as vertical slices; Phases 11–15 complete the site (categories, indexes, polish); Phases 16 and 17 are deferred enhancements.

**Design system note.** The canonical design system lives at `site/_design-system/`. There is no separate `_design-system-cms/` variant during the build — `docs/design-mockups/cms-ui.html` already serves as the visual reference for admin components. The deferred DS Unification phase (Phase 17) documents admin and article-template components into the canonical DS post-v1.0.

---

## 3. Phase index

Each row shows the phase, autonomy tier, hour estimate, and (where applicable) what ships publicly when it's complete.

- [x] **Phase 0** — Setup & onboarding · *Manual* · 3–5h
- [x] **Phase 1** — Static site deployment · *Manual* · 2–3h · **Ships:** new alexmchong.ca live publicly
- [x] **Phase 2** — CSS module split + dynamic `/_ds/` · *Auto* · 3–4h
- [x] **Phase 3** — Deployment plumbing (PHP + DB + router) · *Semi-auto* · 4–5h
- [x] **Phase 4** — Auth (minimal v1) · *Semi-auto* · 4–5h
- [x] **Phase 5** — Admin shell port · *Auto* · 4–5h
- [x] **Phase 6a** — Articles in CMS (CRUD + Tiptap) · *Semi-auto* · 5–7h
- [x] **Phase 6b** — Articles public · *Manual* · 4–6h · **Ships:** first Article live at `/writing/[slug]`
- [x] **Phase 7** — Articles full workflow (Pipeline + transitions) · *Semi-auto* · 3–4h
- [x] **Phase 8** — Journals end-to-end · *Semi-auto* · 4–5h · **Ships:** Journals live at `/journal/[slug]`
- [x] **Phase 9** — Live Sessions end-to-end · *Semi-auto* · 4–5h · **Ships:** Live Sessions live
- [x] **Phase 10** — Experiments end-to-end (both variants) · *Semi-auto* · 4–6h · **Ships:** Experiments live
- [x] **Phase 11** — Categories + Series + slug guard · *Manual* · 3–4h
- [x] **Phase 12** — Indexes (staging-only ship) · *Manual* · 4–5h
- [x] **Phase 13** — Redirects DB + cron + 404 + image UX + backup · *Auto* · 4–5h · **Staging-only**
- [x] **Phase 14** — Newsletter subscribers end-to-end · *Semi-auto* · 3–4h · **Staging-only**
- [x] **Phase 14.5** — Content Template view (read-only port + Author editable) · *Semi-auto* · ~5h · **Staging-only**
- [x] **Phase 14.6** — Scheduled-publish UX (CMS-side, completes Phase 13 infra) · *Semi-auto* · ~3–4h · **Staging-only**
- [x] **Phase 15** — Accessibility pass + final polish · *Manual* · 3–4h · **Staging-only**

**═══ PROJECT: CMS Reorganization (v2.0) — sidebar IA + new admin surfaces ═══**

- [ ] **Phase 19** — Nav reorg + Writer's Desk · *Semi-auto* · 3h · **Staging-only**
- [ ] **Phase 20** — Pages mocks + Navigation editor · *Manual* · 6–8h · **Staging-only**
- [ ] **Phase 21** — Post Templates rename + Settings · *Semi-auto* · 2–3h · **Ships:** v2.0 public

**═══ PROJECT: DS Reorganization (v2.1) — design-system separation ═══**

- [ ] **Phase 22** — DS-1: Audit · *Semi-auto* · 2h · **No code** (deliverable: `docs/DS-AUDIT.md`)
- [ ] **Phase 23** — DS-2: Root tokens · *Semi-auto* · 2h · **Staging-only**
- [ ] **Phase 24** — DS-3: Pages migration · *Semi-auto* · 2.5h · **Staging-only**
- [ ] **Phase 25** — DS-4: Blocks migration · *Manual* · 3h · **Staging-only** *(highest risk)*
- [ ] **Phase 26** — DS-4.5: Block recipe doc · *Semi-auto* · 1.5h · **No public ship**
- [ ] **Phase 27** — DS-5: CMS migration · *Semi-auto* · 2.5h · **Staging-only**
- [ ] **Phase 28** — DS-6: Cleanup + sunset · *Manual* · 1.5h · **Ships:** v2.1 public
- [ ] **Phase 29** — Public cutover (v1.0 ship, marketing nav + indexes flip) · *Manual* · 2–3h · **Ships:** v1.0 public

**═══ DEFERRED ═══**

- [ ] **Phase 17** *(superseded by Phases 22–28)* — original single-phase DS unification, now expanded into the v2.1 project above. Kept for historical reference.
- [ ] **Phase 18** *(deferred)* — Transactional email · *Semi-auto* · 4–5h

**Rule:** finish a phase before starting the next. If a phase reveals a missing decision, capture it in `CMS-STRUCTURE.md` §17 (Open Items), make the call with Alex, then continue.

**Prod-freeze rule (Phases 12 → 15).** Starting with Phase 12, no phase ships visible changes to the public prod site. CMS, lib code, and migrations deploy to prod as usual so the author can manage real content; the public surface stays frozen. The freeze is enforced by:
- `APP_ENV === 'staging'` gate around the new public index routes in `site/index.php`
- `bin/deploy.sh prod` skip-list for `_pages/_layout/header.html`, `_pages/_bodies/landing.html`, `templates/partials/nav.php`

**Phase 29** is the single moment all of that flips — see its session brief for the exact unfreeze checklist.

---

## 4. Phase 0 — Setup & onboarding

**Session brief**

- **Autonomy:** Manual
- **Environment:** Cowork (this Cowork session). Phase 1 onward shifts to Claude Code in VS Code.

**Decisions to capture before starting** *(answer/confirm before kicking off)*
- Production DB name: `alexmchong_cms_production`
- Staging DB name: `alexmchong_cms_staging`
- DB user pattern: `cms_prod` (production) and `cms_staging` (staging) — separate user per DB
- GitHub repository visibility: `private`
- GitHub repo name: `alex-cms-buildout`
- Staging subdomain: `staging.alexmchong.ca`

**Read at start (only):** This phase section.

**Touch:** `docs/onboarding/*.md` (new), `LEGACY-ROUTES.md` (new), `README.md` (new, short), `.gitignore` (new).

**Don't touch:** `site/_pages/`, `site/_design-system/`, `docs/design-mockups/`, `site/_templates/`, any PHP (none exists yet).

**On exit:** Check Phase 0 in §3. No application code is written this phase — code work begins Phase 1.

**Goal:** Tooling is ready and a legacy-route inventory exists.

**Scope:**
- Install / verify Visual Studio Code, configure for this project.
- Install / verify Git on macOS, configure with name + email.
- Connect VS Code to GitHub; create the repo using the name in Decisions; push the existing local repo as the first commit.
- Learn the basic Git workflow: pull → edit → stage → commit → push. Practice once.
- Walk through the DreamHost panel: confirm PHP version per environment, create the staging subdomain, create both MySQL databases using the Decisions values, confirm FTP credentials.
- Install / configure CloudMounter to mount both `/_staging/` and `/` (production) as drives.
- Install Claude Code in VS Code and read `CLAUDE.md` together to orient it.
- **Legacy-route inventory.** Walk through the current production `alexmchong.ca/` folder. For every folder, sub-folder, or HTML file currently in the webroot, decide whether the URL it serves needs to be preserved (kept as a redirect) or whether it can be safely deleted. Record every preserved URL in `LEGACY-ROUTES.md` at the repo root. The five external redirects already known (portfolioforhire, research, talks, meet, linkedin) are seeded — extend the list as you discover more.

**Deliverables:**
- `docs/onboarding/README.md` — index to the lessons
- `docs/onboarding/01-git-and-github.md`
- `docs/onboarding/02-dreamhost-setup.md`
- `docs/onboarding/03-vscode-claude-code.md`
- `docs/onboarding/04-three-environments.md`
- `LEGACY-ROUTES.md` at repo root
- `.gitignore` at repo root
- `README.md` at repo root (short — pointer to `CLAUDE.md` and `BUILD-PLAN.md`)

**Verification:**
1. Alex can open this repo in VS Code, make a tiny edit, commit it from the VS Code Source Control panel, push to GitHub, and see the commit on github.com.
2. Both MySQL databases exist in the DreamHost panel.
3. Both `/_staging/` and the root are mounted as drives via CloudMounter.
4. Alex can explain in one sentence what the three environments are and how a file gets from local → staging → production.
5. `LEGACY-ROUTES.md` lists every URL on the current alexmchong.ca that must keep resolving after the new site goes live.

**Out of scope:** No PHP. No CSS changes. No database tables yet. No code changes to production.

---

## 5. Phase 1 — Static site deployment

**Session brief**

- **Autonomy:** Manual
- **Ships:** New alexmchong.ca live publicly; legacy redirects working; `/_ds/` static copy deployed.

**Decisions to capture before starting**
- Landing page approach: `rename site/_pages/landing.html → index.html on upload` (alternative: keep filename + `DirectoryIndex landing.html` directive)
- Legacy redirect status code: `302` (allows future changes without browser cache locking; switches to 301 in Phase 13 once stable)
- Staging gate username: `alex` (your call)
- Staging gate password: `[pick now, save in 1Password]`
- Deletion order for legacy folders: `deploy redirects first, verify, THEN delete folders` (never invert)

**Read at start (only):** This phase section. `LEGACY-ROUTES.md`.

**Touch:** Webroot `.htaccess` (new), `/_staging/.htaccess` + `.htpasswd` (new), `LEGACY-ROUTES.md` §3 (append confirmed-removed paths), `DEPLOYMENT.md` (new — first draft).

**Don't touch:** `site/_pages/*.html` content (Alex iterates on these in parallel — do not refactor markup), `site/_design-system/` source (only deploying a copy), `docs/design-mockups/`, `site/_templates/`.

**On exit:** Check Phase 1 in §3. `DEPLOYMENT.md` exists with the CloudMounter workflow documented. **Public ship #1 confirmed live.**

**Goal:** The new `alexmchong.ca` is live as a static marketing site, every legacy URL still resolves, and the old folder structure on production is cleaned out. **The CMS is not yet deployed.**

**Scope:**
- Write the webroot `.htaccess` with two responsibilities, in this order:
  1. **Legacy redirect block** — `RewriteRule … [R=302,L]` for every URL in `LEGACY-ROUTES.md` §2.
  2. **Direct-file serving** — default Apache behaviour. No PHP front controller yet.
- Apply the landing-page approach from Decisions.
- Upload to staging via CloudMounter (all `site/_pages/*.html`, `site/_pages/_layout/`, `.htaccess`).
- Verify on staging.
- Push the same files to production via CloudMounter; verify on production.
- **Clean out old production folders** per the Decisions order: deploy redirects first, verify, then delete the legacy folders. Record what was deleted in `LEGACY-ROUTES.md` §3.
- Set up the staging password gate (`.htaccess` + `.htpasswd` inside `/_staging/`).
- **Deploy the canonical design system to `/_ds/` (static copy)** on both environments. Phase 2 replaces this with the dynamic version.

**Deliverables:**
- Webroot `.htaccess` on staging and production
- All `site/_pages/*.html` and `site/_pages/_layout/` content live at both webroots
- `site/_design-system/` content live at `/_ds/` on both environments
- Staging password gate
- Updated `LEGACY-ROUTES.md` §3
- First-draft `DEPLOYMENT.md` at repo root

**Verification:**
1. Visit `https://staging.alexmchong.ca/` — new landing renders.
2. Visit each `site/_pages/` URL on staging — all render correctly.
3. `curl -I -L https://staging.alexmchong.ca/portfolioforhire/` returns 302 to the destination in `LEGACY-ROUTES.md` §2. Repeat for the other four legacy paths.
4. Same drag to production. Visit `https://alexmchong.ca/` — see the new site.
5. Re-run the five `curl` checks against production.
6. **Only after step 5 passes,** delete the legacy folders from production. Re-run the five `curl` checks; they should still pass.
7. Visiting staging prompts for username/password.
8. Visit `/_ds/` on both environments — design system showcase loads.

**Out of scope:** No PHP. No database. No CMS routes. The newsletter form posts to a static `newsletter-confirmed.html` (real subscriber capture lands in Phase 14).

---

## 6. Phase 2 — CSS module split + dynamic `/_ds/`

**Session brief**

- **Autonomy:** Auto

**Decisions to capture before starting**
- None — the 7-layer module split is specified in `CMS-STRUCTURE.md` §3. The showcase rendering pattern (`getComputedStyle` runtime reader) is specified in this phase's Scope.

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §3 (CSS architecture).

**Touch:** `site/_design-system/css/*.css` (new modules), `site/_design-system/js/showcase.js` (new), `site/_design-system/index.html` (rewrite to load modules + dynamic JS), `docs/design-mockups/cms-ui.html` (remove the inlined `<style>` block, add `<link>` tags pointing at the modules).

**Don't touch:** `site/_pages/`, `site/_templates/`, any PHP, any CMS-scoped tokens (those land in the canonical DS later via Phase 17).

**On exit:** Check Phase 2 in §3. Re-deploy the new dynamic `/_ds/` to staging + production (overwriting the Phase 1 static copy).

**Goal:** The canonical design system CSS is split into maintainable modules and the showcase renders every token's live value at runtime.

**Scope:**
- Split `site/_design-system/system.css` into modules per `CMS-STRUCTURE.md` §3.
- Rewrite the showcase's rendering JS to read CSS custom properties at runtime via `getComputedStyle`. Edit a token, refresh, see the change.
- Split the inlined `<style>` block in `docs/design-mockups/cms-ui.html` so the mockup imports the modular CSS files.
- Re-deploy `/_ds/` from the new dynamic version.

**Deliverables:**
- `site/_design-system/css/tokens.css`, `base.css`, `typography.css`, `shell.css`, `components.css`, `tables.css`, `status.css`, `views.css`
- `site/_design-system/js/showcase.js`
- `site/_design-system/index.html` — updated showcase
- `docs/design-mockups/cms-ui.html` — inlined CSS removed; `<link>` tags added
- `/_ds/` redeployed dynamically

**Verification:**
1. Open `site/_design-system/index.html` locally. Every token shows its live value.
2. Change `--c-rust` in `tokens.css` to `#000000`. Refresh. Swatch is black. Revert.
3. Open `docs/design-mockups/cms-ui.html`. Visually identical to before the split.
4. Visit `/_ds/` on both environments — dynamic showcase serves.

**Out of scope:** No PHP. No database. No CMS-admin DS variant (eliminated from active build; absorbed into Phase 17).

---

## 7. Phase 3 — Deployment plumbing (PHP + DB + router)

**Session brief**

- **Autonomy:** Semi-auto

**Decisions to capture before starting**
- Character set / collation: `utf8mb4_unicode_ci` (supports emoji + international text)
- Migration error handling: `roll back on first error` (alternative: continue and log)
- Migration tracker table name: `_migrations`
- Test route path: `/hello`
- `config.example.php` content: `placeholders only — never commit real credentials`
- Schema source of truth: `CMS-STRUCTURE.md §9 (do not deviate without updating the spec first)`

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §9 (schema).

**Touch:** `db/migrate.php` (new), `db/migrations/0001_initial_schema.sql` (new), `config/config.php` + `config.example.php` + `.htaccess` (new), webroot `.htaccess` (extend Phase 1's), `index.php` (new), `lib/db.php` (new), `lib/router.php` (new), `DEPLOYMENT.md` (extend — add PHP+DB chapter).

**Don't touch:** `site/_pages/`, `site/_design-system/`, `docs/design-mockups/`, `site/_templates/`, `cms/` (Phase 4 creates it).

**On exit:** Check Phase 3 in §3. `DEPLOYMENT.md` covers both static deploy and PHP+DB deploy.

**Goal:** Both staging and production can serve a real PHP page from a real MySQL database via a front controller. Phase 1's static pages and legacy redirects still work.

**Scope:**
- Create the initial schema as `db/migrations/0001_initial_schema.sql` — the `content`, `series`, `redirects`, `categories`, `author`, `users` tables exactly as documented in `CMS-STRUCTURE.md` §9.
- Write `db/migrate.php` — reads migration files, applies any not-yet-applied. Tracks applied files in `_migrations`.
- Create the environment-config pattern in `config/` (`config.php` central include + per-env overrides + `.htaccess` deny).
- Extend the webroot `.htaccess` with a front-controller fallback below the legacy redirect block.
- Write `index.php` — front controller. Dispatches `/hello` to a page that queries DB for the current time.

**Deliverables:**
- `db/migrations/0001_initial_schema.sql`
- `db/migrate.php`
- `config/config.php`, `config/config.example.php`, `config/.htaccess`
- Updated webroot `.htaccess`
- `index.php` (skeleton with one route)
- `lib/db.php` — PDO connection helper
- `lib/router.php` — minimal router
- Updated `DEPLOYMENT.md`

**Verification:**
1. `php -S localhost:8000` then visit `http://localhost:8000/hello`. See "Database connected. Current time: …".
2. Drag to `/_staging/`. Visit `https://staging.alexmchong.ca/hello`. Same response with staging's DB time.
3. Phase 1 static pages still render on staging.
4. Phase 1 legacy redirects still 302 correctly.
5. Same drag to production. Visit `/hello`. Same response, production DB time.
6. Static pages + legacy redirects still work on production.
7. `_migrations` table has one row: `0001_initial_schema.sql`.

**Out of scope:** No auth. No admin UI. No content types. `redirects` table exists but isn't read by the router yet (Phase 13 migrates legacy redirects into it).

---

## 8. Phase 4 — Auth (minimal v1)

**Session brief**

- **Autonomy:** Semi-auto

**Decisions to capture before starting** *(answers below become the spec written into `AUTH-SECURITY.md` at the start of this phase)*
- Login email: `login@alexmchong.ca`
- Failed-attempt threshold: `5 in 15 minutes`
- Lockout duration: `15 minutes`
- Session duration: `14 days, sliding refresh on activity`
- Password minimum length: `12 characters`
- Password complexity: `mixed case + at least one digit (no special-char requirement — length is the win)`
- CSRF token TTL: `per-session (regenerate on login)`
- `setup.php` behavior: `self-delete after first successful password change`
- Logout button placement: `top-right of CMS topbar, in the topbar partial`
- `/admin/` → `/cms/` redirect: `301`

**Read at start (only):** This phase section. `AUTH-SECURITY.md` if it already exists; otherwise draft it from this phase's Scope and the Decisions answers.

**Touch:** `AUTH-SECURITY.md` (new at start of phase), `db/migrations/0002_users_table.sql` (new), `cms/login.php`, `cms/logout.php`, `cms/account.php` (new), `cms/.htaccess` (new), `lib/auth.php` (new), `lib/csrf.php` (new), `setup.php` (new — self-deleting).

**Don't touch:** `site/_pages/`, `site/_design-system/`, `docs/design-mockups/`, `site/_templates/`, `templates/` (Phase 6b creates), content-type code (Phase 6+).

**On exit:** Check Phase 4 in §3. Confirm `setup.php` has self-deleted on staging + production after first use.

**Goal:** Alex can log in to the CMS with email + password and change their password. Wrong passwords get throttled. No email reset yet.

**Scope:** Per the Decisions answers and `AUTH-SECURITY.md`:
- Login form at `/cms/login` (email + password).
- Session cookie on success (httpOnly + secure + samesite-strict).
- `/cms/*` routes redirect to `/cms/login` when there's no session.
- `/cms/account` change-password form (requires current password).
- Login throttle per Decisions.
- CSRF token on every POST form.
- `/admin/` redirects to `/cms/`.
- Logout button per Decisions.
- `setup.php` seeds the single `users` row with the Decisions email + a temp password printed to screen; self-deletes per Decisions.

**Deliverables:**
- `AUTH-SECURITY.md`
- `db/migrations/0002_users_table.sql` (id, email, password_hash, last_login, failed_attempts, locked_until, password_changed_at, created_at)
- `cms/login.php`, `cms/logout.php`, `cms/account.php`
- `lib/auth.php`, `lib/csrf.php`
- `setup.php`
- `cms/.htaccess`

**Verification:**
1. Run `setup.php` once on staging. See the temp password.
2. Visit `/cms/`. Redirected to `/cms/login`.
3. Log in with email + temp password. Land on placeholder dashboard.
4. Change password. Log out. Log back in with the new password.
5. Five wrong passwords → 6th attempt says "locked, try again in 15 minutes".
6. `/admin/` redirects to `/cms/`.
7. `setup.php` no longer exists on the server.

**Out of scope:** No email password reset (Phase 18). No multi-user (forever — that's a different system).

---

## 9. Phase 5 — Admin shell port

**Session brief**

- **Autonomy:** Auto

**Decisions to capture before starting**
- None — port `docs/design-mockups/cms-ui.html` directly to PHP partials, keep visual fidelity to the mockup.

**Read at start (only):** This phase section. `docs/design-mockups/cms-ui.html` (source of truth for layout + styling).

**Touch:** `cms/partials/sidebar.php`, `topbar.php`, `view-header.php`, `filter-bar.php`, `table.php` (new). Possibly `cms/index.php` (post-login dashboard placeholder) if not yet created.

**Don't touch:** Articles CRUD or any content-type views (Phase 6a). Tiptap (Phase 6a). Public templates (Phase 6b). Anything outside `cms/`.

**On exit:** Check Phase 5 in §3. Visual diff: the empty-state CMS view should look identical to the mockup (minus actual data).

**Goal:** The CMS admin chrome — sidebar, topbar, view container, filter bar primitives, table primitives — exists as PHP partials. Every later content-type phase consumes these.

**Scope:**
- Port the layout structure of `docs/design-mockups/cms-ui.html` into PHP partials. One partial per stable region.
- Wire the partials into a placeholder dashboard (visible after login).

**Deliverables:**
- `cms/partials/sidebar.php`
- `cms/partials/topbar.php`
- `cms/partials/view-header.php`
- `cms/partials/filter-bar.php`
- `cms/partials/table.php`
- A placeholder dashboard that uses all the partials together

**Verification:**
1. Log into staging. Sidebar matches the mockup.
2. Empty dashboard view uses every partial without breakage.
3. Visual diff against a screenshot of `docs/design-mockups/cms-ui.html` (loaded locally).

**Out of scope:** Any content-type CRUD. Real data. Tiptap.

---

## 10. Phase 6a — Articles in CMS (CRUD + Tiptap)

**Session brief**

- **Autonomy:** Semi-auto

**Decisions to capture before starting**
- Slug auto-generation: `yes — generate from title on first save`
- Slug editable after first save: `yes (with warning that published-slug changes create redirects in Phase 11)`
- Default state on `+ New Article`: `Draft` (per spec)
- Image upload max size: `5 MB`
- Allowed image mime types: `image/jpeg, image/png, image/webp, image/gif`
- Tiptap toolbar order: `bold, italic, H2, H3, ul, ol, link, blockquote, code, muted-word (m), image`
- Sanitizer allowlist: `matches the toolbar exactly — no extras`
- Body required at Draft: `no (title + slug enough to save)`
- Delete UX: `confirmation modal, hard-delete (no soft-delete in v1)`

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §9 (the `content` table). `ENGINEERING.md` (the one-function-per-entity rule + escaping rules).

**Touch:** `cms/views/articles.php`, `article-new.php`, `article-edit.php` (new — Draft stage). `lib/content.php` (new — article CRUD only). `cms/assets/js/tiptap-setup.js` + `cms/assets/css/tiptap.css` (new). `lib/sanitize.php` (new). `lib/uploads.php` (new — for hero image).

**Don't touch:** Pipeline / Ideation / Idea-stage views (Phase 7). Other content types (Phase 8+). Public templates (Phase 6b).

**On exit:** Check Phase 6a in §3. `lib/content.php` only contains article CRUD; do not pre-stub other types.

**Goal:** Articles can be created, listed, edited (at Draft) with a proper rich text editor and hero image upload.

**Scope:**
- Implement Articles list, New (Draft), Edit (Draft) views using the Phase 5 partials.
- Load Tiptap and ProseMirror from CDN via `<script>` tags (no bundler). Configure the toolbar per Decisions.
- Custom mark: `<span class="m">…</span>` for muted-word.
- Inline image upload: button → upload to `lib/uploads.php` → URL inserted as `<img src="…">`.
- Hero image upload: separate field on the article form, posts to `lib/uploads.php`, lands in `/uploads/content/article/[slug]/`.
- `lib/sanitize.php`: HTML allowlist matching the toolbar.
- `lib/content.php`: `get_article($slug)`, `list_articles($filters)`, `save_article($data)`, `delete_article($id)`.

**Deliverables:**
- `cms/views/articles.php`, `article-new.php`, `article-edit.php`
- `cms/assets/js/tiptap-setup.js`
- `cms/assets/css/tiptap.css`
- `lib/content.php` (article CRUD only)
- `lib/sanitize.php`
- `lib/uploads.php`

**Verification:**
1. Log into staging. Click "Articles" → empty list.
2. Click `+ New Article`. Fill title + slug + body. Save. See it in the list.
3. Edit the article. Bold a word, italicize a word, wrap a phrase in muted-word mark, insert an H2, insert an inline image. Save. Refresh — content survives.
4. Upload a hero image. Confirm it lands in `/uploads/content/article/[slug]/`.
5. Inspect saved body HTML in DB. Only allowlisted tags present.
6. Delete an article. Confirmation modal appears. After delete, gone from DB.

**Out of scope:** Pipeline / Ideation / Idea stage (Phase 7). Public rendering (Phase 6b). Other content types.

---

## 11. Phase 6b — Articles public

**Session brief**

- **Autonomy:** Manual *(first dynamic public render — watch closely)*
- **Ships:** First Article goes live at `/writing/[slug]`.

**Decisions to capture before starting**
- URL pattern: `/writing/[slug]`
- Block partial filename convention: `templates/partials/block-[slug].php`
- Hero image absence: `skip the block entirely (Path A visibility, per spec §11)`
- Author block when fields are empty: `render placeholders — {no author name}, {no short description} (per spec §11)`
- Initials-circle fallback for empty author image: `yes — render first letter of first + last name`
- Master layout `<head>` includes: `viewport meta, favicon, font links, the deployed canonical CSS at /_ds/css/*.css`

**Read at start (only):** This phase section. `docs/BLOCKS.md` (the block contract). `site/_templates/article.html` (visual reference). `CMS-STRUCTURE.md` §8 (template inventory) and §11 (Author block).

**Touch:** `templates/master-layout.php`, `partials/nav.php`, `partials/footer.php` (new). `templates/article-standard.php` (new). `templates/partials/block-*.php` — only the blocks Articles need (~12 of 19; the type-specific 7 land in Phases 8/9/10). `lib/render.php` (new). `index.php` (extend with `/writing/[slug]` route).

**Don't touch:** Journal, Live Session, Experiment templates (Phases 8/9/10). The slug-permanence guard (Phase 11). Index templates (Phase 12).

**On exit:** Check Phase 6b in §3. **Public ship #2 confirmed live.** First Article renders cleanly at its URL on production.

**Goal:** Articles render on the public site at `/writing/[slug]` with all expected blocks.

**Scope:**
- Implement the master layout, public-site nav and footer partials.
- Implement `templates/article-standard.php`.
- Implement the ~12 shared block partials Articles need (topstrip, title, lede, byline, hero, body, tags, author bio, etc. — exact list from `BLOCKS.md`).
- `lib/render.php` → `render_content($slug)`.
- Add `/writing/[slug]` route to `index.php`.

**Deliverables:**
- `templates/master-layout.php`
- `templates/partials/nav.php`, `footer.php`
- `templates/article-standard.php`
- ~12 `templates/partials/block-*.php` files
- `lib/render.php`
- Updated `index.php`

**Verification:**
1. Publish an Article in the CMS. Visit `https://staging.alexmchong.ca/writing/[slug]`. Renders with all expected blocks per the matrix.
2. Toggle Author Bio off on the article. Confirm the block disappears. Re-toggle on. It reappears.
3. Publish an Article without a hero image. Confirm the hero block is skipped (not a broken `<img>`).
4. Publish an Article with the Author Bio block on but Author fields empty. Confirm placeholders render.
5. Same Article renders identically on production after deploy.

**Out of scope:** Pipeline / Ideation polish (Phase 7). Slug-permanence guard (Phase 11). Other content types.

---

## 12. Phase 7 — Articles full workflow

**Session brief**

- **Autonomy:** Semi-auto

**Decisions to capture before starting**
- Idea quick-capture fields: `title only (optional note field included but not required)`
- Stage transition order: `locked — Idea → Concept → Outline → Draft → Published, no skipping`
- Allow backwards transitions: `yes (Published → Draft requires confirmation)`
- Pipeline kanban card content: `title, slug, category color, last-modified relative time`
- Delete confirmation: `modal with typed-slug confirmation for Published items, simple OK for non-Published`

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §9 (Pipeline stages + gating fields). `docs/design-mockups/cms-ui.html` (Pipeline + Ideation + Edit-Article-Idea — visual reference).

**Touch:** `cms/views/pipeline.php`, `ideation.php` (new). `cms/views/article-edit.php` (extend — handle Idea-stage variant). `lib/content.php` (extend — `transition_stage($id, $to)`, and update `delete_article` with confirmation logic).

**Don't touch:** Public templates. Other content types.

**On exit:** Check Phase 7 in §3.

**Goal:** Articles flow through the full pipeline (Idea → Concept → Outline → Draft → Published), can be quick-captured from Pipeline, and can be deleted with appropriate confirmation.

**Scope:**
- Pipeline view (Articles only): kanban with five stage columns; cards per Decisions.
- Ideation view (Articles only): quick-capture form creating content at Idea stage.
- Extend `article-edit.php` to handle the Idea-stage variant (shorter form).
- Implement stage transitions per Decisions.
- Implement delete confirmation per Decisions.

**Deliverables:**
- `cms/views/pipeline.php`
- `cms/views/ideation.php`
- Extended `cms/views/article-edit.php`
- Extended `lib/content.php`

**Verification:**
1. Quick-capture an idea on Ideation. It appears in the Idea column on Pipeline.
2. Walk it Idea → Concept → Outline → Draft. Each transition reflected in the Pipeline kanban.
3. Try to skip Concept → Draft directly. Confirm it's blocked per Decisions.
4. Move a Published article back to Draft. Confirmation modal appears.
5. Delete an unpublished article. Simple confirmation. Delete a Published one. Typed-slug confirmation.

**Out of scope:** Other content types. Public rendering changes.

---

## 13. Phase 8 — Journals end-to-end

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Journals live at `/journal/[slug]`.

**Decisions to capture before starting**
- URL pattern: `/journal/[slug]`
- `journal_number` assignment: `at publish time, per-category counter (per spec §6)`
- Key Statement max length: `280 characters`
- Body required at Draft: `no (Key Statement alone is enough)`
- Journal-specific block partials: `key-statement, entry-number, journal-meta` (~3 new blocks on top of the shared 12)

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §6 (Journal content type). `docs/design-mockups/cms-ui.html` (Journals views — visual reference). `docs/BLOCKS.md` (journal-specific blocks).

**Touch:** `cms/views/journals.php`, `journal-new.php`, `journal-edit.php` (new). `lib/content.php` (extend with `get_journal`, `list_journals`, `save_journal`, `delete_journal`, `assign_journal_number`). `templates/journal-entry.php` (new). `templates/partials/block-key-statement.php`, `block-entry-number.php`, `block-journal-meta.php` (new). `index.php` (extend with `/journal/[slug]` route).

**Don't touch:** Articles, Live Sessions, Experiments work. Indexes.

**On exit:** Check Phase 8 in §3. **Public ship #3 confirmed live.**

**Goal:** Journals are fully implemented end-to-end — admin CRUD, Tiptap reuse, public render.

**Scope:**
- CMS views: Journals list, new, edit. Key Statement field replaces Title in the form.
- `assign_journal_number`: increments the per-category counter on publish.
- Public template + journal-specific block partials.
- Route: `/journal/[slug]`.

**Deliverables:**
- `cms/views/journals.php`, `journal-new.php`, `journal-edit.php`
- Extended `lib/content.php`
- `templates/journal-entry.php`
- ~3 journal-specific block partials
- Updated `index.php`

**Verification:**
1. Publish a Journal in Introspection. Confirm `journal_number = 1`. Publish another. Confirm `2`. Publish one in Contemplation. Confirm its number is 1.
2. Visit `https://staging.alexmchong.ca/journal/[slug]`. Key Statement renders (not Title). `Entry N` appears in the meta.
3. Same on production.

**Out of scope:** Other content types. Series auto-index (Phase 11).

---

## 14. Phase 9 — Live Sessions end-to-end

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Live Sessions live at `/live-sessions/[slug]`.

**Decisions to capture before starting**
- URL pattern: `/live-sessions/[slug]`
- Event Details fields: `date, time, timezone, location, link, optional capacity`
- Format Tags master list: `Workshop, Talk, Conversation, Office Hours, Critique` (edit to taste)
- Past events behavior: `stay live with "PAST" badge — no archive, no redirect`
- Live Session-specific block partials: `event-card, format-tags` (~2 new blocks)

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §7 (Live Sessions). `docs/design-mockups/cms-ui.html` (Live Sessions views). `docs/BLOCKS.md` (event-specific blocks).

**Touch:** `cms/views/live-sessions.php`, `live-session-new.php`, `live-session-edit.php` (new). `lib/content.php` (extend with live_session_* functions). `templates/live-session.php` (new). `templates/partials/block-event-card.php`, `block-format-tags.php` (new). `index.php` (extend with `/live-sessions/[slug]`).

**Don't touch:** Other content-type code. Indexes.

**On exit:** Check Phase 9 in §3. **Public ship #4 confirmed live.**

**Goal:** Live Sessions are fully implemented end-to-end with Event Details + Format Tags.

**Scope:**
- CMS views: list, new, edit. Event Details fields per Decisions.
- Three Format Tag pills (or whichever set Decisions specifies).
- Public template + event-specific block partials.
- Route: `/live-sessions/[slug]`.

**Deliverables:**
- `cms/views/live-sessions.php`, `live-session-new.php`, `live-session-edit.php`
- Extended `lib/content.php`
- `templates/live-session.php`
- ~2 event-specific block partials
- Updated `index.php`

**Verification:**
1. Create a Live Session with all Event Details fields and one Format Tag selected. Save, refresh — fields persist.
2. Publish. Visit `/live-sessions/[slug]`. Event Details + Format Tags render.
3. Set the event date to the past. Confirm "PAST" badge appears.
4. Same on production.

**Out of scope:** Other content types. Indexes.

---

## 15. Phase 10 — Experiments end-to-end

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Experiments (both variants) live at `/experiments/[slug]`.

**Decisions to capture before starting**
- URL pattern: `/experiments/[slug]` for both variants
- experiment-html folder location: `/content/experiment/[slug]/`
- Allowed file types in experiment-html folders: `text/html, text/css, text/javascript, image/*, application/json`
- experiment-html rendering: `raw passthrough, no template wrapper, no CMS chrome`
- Default variant on `+ New`: `experiment` (article-format)
- Experiment-specific block partials: minimal — most blocks shared with Articles

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §12 (Custom HTML folder system). `docs/design-mockups/cms-ui.html` (Experiments views).

**Touch:** `cms/views/experiments.php`, `experiment-new.php`, `experiment-edit.php` (new). `lib/content.php` (extend with experiment_* functions). `lib/folders.php` (new — Custom HTML folder management). `templates/experiment.php`, `experiment-html.php` (new). `index.php` (extend route handling).

**Don't touch:** Other content-type code. Indexes.

**On exit:** Check Phase 10 in §3. **Public ship #5 confirmed live.**

**Goal:** Both Experiment variants render end-to-end.

**Scope:**
- CMS views: list, new, edit for both variants. The `experiment-html` flow includes the Custom HTML folder system (`Set Up Folder`, `Refresh`).
- `lib/folders.php`: create/list/refresh/delete `/content/experiment/[slug]/`.
- `templates/experiment.php` for the article-format variant.
- `templates/experiment-html.php` for the raw passthrough.
- Routing: dispatcher chooses template based on `content.subtype`.

**Deliverables:**
- `cms/views/experiments.php`, `experiment-new.php`, `experiment-edit.php`
- Extended `lib/content.php`
- `lib/folders.php`
- `templates/experiment.php`, `experiment-html.php`
- Updated `index.php`

**Verification:**
1. Create one of each variant. Both appear in the Experiments list.
2. Create an `experiment-html`. Click "Set Up Folder". Confirm the folder exists on the server.
3. Upload an HTML file via CloudMounter. Click "Refresh" — the file appears in the picker.
4. Publish. Visit `/experiments/[slug]`. Raw HTML serves with no wrapper.
5. Publish an `experiment` variant. Visit. Article-format template wraps the content.

**Out of scope:** Categories, Series (Phase 11). Indexes (Phase 12).

---

## 16. Phase 11 — Categories + Series + slug guard

**Session brief**

- **Autonomy:** Manual *(category color and series structure decisions emerge during use)*

**Decisions to capture before starting**
- Category color source: `design-system token names only — terracotta, forest, etc. (no raw hex)`
- Series auto-index slug pattern: `/series/[slug]/`
- Slug-guard behavior on miss: `check redirects table → 301 if found, 404 otherwise`
- Initial categories per content type: `seed from CMS-STRUCTURE.md §10 — Articles: Strategy, Craft, Coaching; Journals: Introspection, Contemplation, Reflection` (edit to taste at start of phase)

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §10 (Categories), the Series subsection, and §14 (301/302 + slug permanence).

**Touch:** `cms/views/categories.php`, `series.php` (new). `lib/content.php` (extend with category_* and series_* functions, plus the slug-guard helper). `lib/render.php` (extend — slug guard reads `redirects` table). `db/migrations/000X_seed_initial_categories.sql` if seeding.

**Don't touch:** Indexes (Phase 12). Other content-type code.

**On exit:** Check Phase 11 in §3.

**Goal:** Categories and Series management work in the CMS. Slug-permanence guard catches old URLs.

**Scope:**
- Categories view: read/write the `categories` table per `CMS-STRUCTURE.md` §10.
- Series view: read/write the `series` table; series creation auto-creates a `/series/[slug]/` row in the (future) indexes table OR a placeholder record agreed on at phase start.
- Slug guard in `lib/render.php`: on unresolved slug, check `redirects` → 301 or 404.

**Deliverables:**
- `cms/views/categories.php`, `series.php`
- Extended `lib/content.php`
- Extended `lib/render.php`
- Optional seed migration

**Verification:**
1. Add a Category to Articles with a token-named color. Confirm it appears in the New Article category dropdown.
2. Create a Series. Confirm a row exists for `/series/[slug]/`.
3. Add a redirect row for an old slug → new slug. Visit the old URL. Confirm 301 to the new.
4. Visit a non-existent slug. Confirm 404 (themed page comes in Phase 13).

**Out of scope:** Indexes builder UI (Phase 12). Themed 404 (Phase 13).

---

## 17. Phase 12 — Indexes (staging-only ship)

**Session brief**

- **Autonomy:** Manual *(nav + index pages are design-heavy; design-system reference must be applied verbatim)*
- **Ships:** **Staging** content-complete. Prod gets CMS + lib + migrations only — public-facing chrome stays frozen until **Phase 29 (Public Cutover)**.

**Why staging-only.** The original Phase 12 brief shipped the public nav switchover and `landing.html` port on the same flight as the indexes. We've since pulled both into **Phase 29**: every phase from 12 onward ships to staging first, prod gets only CMS-side changes, and the public flip happens once at the end after a content audit. See §3 for the renumbered phase index.

**Decisions to capture before starting**
- Topbar nav structure (post-CMS): `What's UX 2.0 (with red dot) / Thoughts / Talks / Work with me`
- Section URL bindings (staging only — prod nav unchanged):
  - `What's UX 2.0` → `/ux2.0/how-we-got-here/`
  - `Thoughts` → `/writing/`
  - `Talks` → `/live-sessions/`
  - `Work with me` → `/work-with-me/`
- Default sort in index feeds: `published_at DESC`
- Series auto-index layout: `Editorial Page (hero = highest-numbered part, feed = the rest)`
- Filter pill mode: per-index choice in CMS builder — `categories` | `types` | `none`. Built-ins default to `categories`. Series indexes hardcoded to `none`.
- Title emphasis: author wraps a word in `*asterisks*` → renders as Instrument Serif italic in-line.

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §16 (Indexes). `docs/design-mockups/landing-postcms.html`. `docs/design-mockups/cms-ui.html`. **`site/_design-system/index.html` §"Content Cards" + §04 Full Page Index + §06 Card Navigation & Filtering** — the canonical card markup. Don't approximate; mirror it.

**Touch:** `db/migrations/0007_indexes_table.sql`, `0008_indexes_filter_mode.sql`, `0009_live_session_venue.sql`. `cms/views/indexes.php`, `index-new.php`, `index-edit.php`, `index-delete.php` (new). `cms/views/{article,journal,live-session,experiment}-edit.php` (add Primary Category dropdown). `cms/views/live-sessions.php` (split list into Drafts/Published). `templates/index-editorial.php`, `index-listing.php`, `partials/index-card.php` (new). `lib/indexes.php` (new). `lib/content.php` (add `assign_primary_category`, `get_primary_category`, venue column). `lib/render.php` (add `render_index`, `render_series_index`). `templates/master-layout.php` (load views + status + components CSS). `_templates/style-articles.css` (nav fixes + `.index-*` layout). `_design-system/css/status.css` (filter pill colours for missing categories). `bin/deploy.sh` (ship the new templates + lib files; **add prod-skip list for `_pages/_layout/header.html`, `_pages/_bodies/landing.html`, `templates/partials/nav.php` — those flip in Phase 29**). **DO NOT touch:** `site/_pages/_layout/header.html`, `site/_pages/_bodies/landing.html`, `site/templates/partials/nav.php` (deferred to Phase 29). `docs/design-mockups/landing-postcms.html` (delete in Phase 29).

**Don't touch:** Anything in `_pages/`, the public-facing nav partial. Polish phases (13–15). The deferred items in §Deferred backlog.

**On exit:** Check Phase 12 in §3. New public index routes work on staging; same routes 404 on prod. CMS works identically on prod. Marketing pages on prod unchanged. **No public ship — that's Phase 29.**

**Goal:** Indexes + Editorial Page layouts work on staging end-to-end. CMS gains per-content category assignment (the data the cards need to render in colour). Prod stays visually identical to its pre-Phase-12 state.

**Scope:**
- `indexes` table + `filter_mode` column + content `venue` column.
- Index Builder UI: page-title block, hero feature (Editorial only), featured articles (Editorial only), content feed with type chips / sort / rows-shown / filter-pill-mode chooser.
- Public-side rendering for both layouts, matching the DS reference exactly (no approximation).
- Series auto-index: every series gets `/series/[slug]/` Editorial Page listing its parts in `series_order`.
- Primary-Category dropdown on each content-type edit form, writing to `content_categories` so the cards have colour data.
- Live Sessions list view sectioned by stage (Drafts / Published), location split into `location` + `venue` columns.
- Masterclass card uses `mc-body / mc-logistics / mc-cta-zone` per the DS reference.
- Filter pills wired to the DS `.fp[data-cat]` colour system in `status.css`.
- Topbar nav structure designed and tested on staging only — actual switch to prod is Phase 29.

**Deliverables:**
- All migrations + lib + CMS views + public templates listed above
- `bin/deploy.sh` prod-skip list for the three public-facing files
- `APP_ENV === 'staging'` gate around the new public routes in `site/index.php`
- Deferred backlog (§ at end of this doc) updated with items cut from Phase 12

**Verification (staging only):**
1. Visit `https://staging.alexmchong.ca/writing/`, `/journal/`, `/live-sessions/`, `/experiments/`, `/series/[slug]/` — every layout renders with cards in colour, filter pills working, count chip updating.
2. CMS: `/cms/indexes` lists the four built-in indexes + any custom; each editable.
3. Per-content edit forms have the Primary Category dropdown; saving writes to `content_categories`.
4. After prod deploy: `https://alexmchong.ca/` and all marketing pages unchanged; `https://alexmchong.ca/writing/[existing-slug]` still works with the old nav; `https://alexmchong.ca/writing/` 404s (not yet live).

**Out of scope:** Public-facing nav swap, `landing.html` port, activating new index routes on prod — all of that is **Phase 29**. Cron, redirects DB, newsletter, a11y (Phases 13–15). The deferred backlog at the bottom of this doc.

---

## 18. Phase 13 — Redirects DB + cron + 404 + image UX + backup

**Session brief**

- **Autonomy:** Auto

**Decisions to capture before starting**
- Backup retention: `7 days`
- Backup file location on server: `/backups/`
- Backup filename pattern: `backup-YYYY-MM-DD.sql.gz`
- Cron interval for scheduled publish: `5 minutes`
- 404 page tone: `friendly with link to homepage and a note that the URL may have moved`
- Image upload UX: `progress bar with percentage`
- Redirect status code default (in CMS form): `301 (alternative: 302)`
- Legacy redirects post-migration: `removed from .htaccess; served from DB`

**Read at start (only):** This phase section. `LEGACY-ROUTES.md` §2 (legacy redirects to migrate). `CMS-STRUCTURE.md` §14 (301/302 distinction).

**Touch:** `cms/views/redirects.php` (new). `db/migrations/000X_add_status_code_to_redirects.sql` (new). `lib/redirects.php` (new). `lib/render.php` (extend slug guard to read DB redirects). Webroot `.htaccess` (remove legacy redirect block). `LEGACY-ROUTES.md` (note migration complete). `cron/scheduled-publish.php`, `cron/backup.php` (new). `templates/404.php` (new). `cms/assets/js/tiptap-setup.js` (extend image upload UX). `DEPLOYMENT.md` (extend with cron setup).

**Don't touch:** Newsletter (Phase 14). A11y (Phase 15).

**On exit:** Check Phase 13 in §3. Cron jobs verified running on production. Backup file present in `/backups/`.

**Goal:** The CMS handles redirects via DB, schedules publishing, themes its 404, and runs daily backups.

**Scope:**
- Redirects view in CMS (read/write the `redirects` table). Add `status_code` column.
- Migrate every legacy redirect from `.htaccess` into the `redirects` table. Remove the legacy block from `.htaccess`. Update `LEGACY-ROUTES.md`.
- `cron/scheduled-publish.php`: every 5 min, flip rows where `published_status = 'scheduled'` and `published_at <= NOW()` to `live`. Log each flip.
- Themed 404 page.
- Tiptap image upload: progress bar + error handling.
- `cron/backup.php`: daily mysqldump → `/backups/`, retain last 7 days.

**Deliverables:**
- `cms/views/redirects.php`
- `lib/redirects.php`
- `db/migrations/000X_add_status_code_to_redirects.sql`
- `cron/scheduled-publish.php`, `cron/backup.php`
- `templates/404.php`
- Extended `cms/assets/js/tiptap-setup.js`
- DreamHost cron configuration in `DEPLOYMENT.md`

**Verification:**
1. Schedule a post for 10 minutes from now. Wait. Confirm it goes live without intervention.
2. Add a redirect via the CMS. Visit the old URL. 301 → new URL. `.htaccess` no longer contains the legacy block.
3. Visit a non-existent slug. Themed 404 renders.
4. Run backup script manually. `.sql.gz` lands in `/backups/`.
5. Upload a large image in Tiptap. Progress bar appears, completes, image inserts. Force an error (oversize file). Error message renders cleanly.

**Out of scope:** Newsletter (Phase 14). A11y (Phase 15).

---

## 19. Phase 14 — Newsletter subscribers end-to-end

**Session brief**

- **Autonomy:** Semi-auto

**Decisions to capture before starting**
- Source label on form: `newsletter-page`
- Honeypot field name: `website`
- Rate-limit (per IP): `1 per minute, 10 per day`
- Duplicate email behavior: `update subscribed_at, set unsubscribed_at = NULL (re-subscribe)`
- CSV export columns: `email, name, source, subscribed_at, ip, user_agent, status`
- Sidebar placement: `new "Audience" group, above "Structure"`
- Subscriber page redirect after success: `/subscribe/confirmed/`

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §20 (subscribers data model) and §21 (file reference).

**Touch:** `db/migrations/000X_subscribers_table.sql` (new). `lib/subscribers.php` (new). `index.php` (extend with `POST /subscribe` and `GET /subscribe/confirmed/`). `site/_pages/newsletter.html` (change form action + method). `cms/views/subscribers.php` (new). `cms/partials/sidebar.php` (add Audience group entry).

**Don't touch:** Phase 13's work. A11y (Phase 15). Double opt-in via email (Phase 18).

**On exit:** Check Phase 14 in §3.

**Goal:** Newsletter signups captured end-to-end. Static newsletter page wired to live endpoint.

**Scope:** Per `CMS-STRUCTURE.md` §20:
- `subscribers` table.
- `lib/subscribers.php` with `get_subscriber`, `list_subscribers`, `save_subscriber`, `unsubscribe`, `export_subscribers_csv`.
- `POST /subscribe` + `GET /subscribe/confirmed/` routes. Server-side validation, honeypot, rate-limit per Decisions, duplicate-email handling per Decisions.
- Update `site/_pages/newsletter.html` form action → `/subscribe`, method → `POST`. CSRF field added.
- `cms/views/subscribers.php`: list, filter (status / source / date), mark unsubscribed, delete, CSV export.
- Sidebar entry per Decisions.

**Deliverables:**
- `db/migrations/000X_subscribers_table.sql`
- `lib/subscribers.php`
- `cms/views/subscribers.php`
- Updated `site/_pages/newsletter.html`
- Extended `index.php`
- Updated `cms/partials/sidebar.php`

**Verification:**
1. Submit the form. Row in `subscribers`, confirmation page renders, source captured per Decisions, IP + user-agent captured.
2. Submit the same email again. `subscribed_at` updates; no duplicate row.
3. Open `/cms/subscribers/`. New subscriber visible. CSV export downloads with the columns from Decisions.
4. Submit with honeypot field filled. No row created. No error shown to the bot.
5. Rapid-fire 11 submits from one IP in an hour. 11th rate-limited.

**Out of scope:** Double opt-in by email (Phase 18). One-click unsubscribe via email link (Phase 18). The CMS can manually mark people unsubscribed here.

---

## 19.5. Phase 14.5 — Content Template view (read-only port + Author editable)

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Staging-only. Goes to prod at Phase 29 cutover.

**Decisions captured (locked at plan time, defaults accepted)**
- **Scope:** α — read-only port of the mockup with Author info editable. Sub-template visibility toggles are read-only documentation; "Save Template" button hidden. Full edit-with-persistence (β) deferred to a future phase.
- **Block-data source:** hardcoded PHP array in `lib/blocks_data.php`, sourced from `docs/BLOCKS.md`. Update both together if blocks change.
- **PHP Layout File tab:** shows real template file content (read-only, monospace) from `site/templates/*.php` corresponding to the selected sub-template. Master Template shows `master-layout.php`.
- **`article-series.php` status:** file missing from `site/templates/`. For v1.0 read-only view: render the article-series sub-template panel from the BLOCKS.md content-type matrix (matrix data exists; file does not). Flag whether `article-series` is folded into `article-standard` via a conditional or genuinely needs its own file as a follow-up — does NOT block this phase.
- **Sub-template Save button:** hidden in v1.0. No `template_block_settings` table, no render-layer changes.

**Read at start (only):** This phase section. `docs/BLOCKS.md` (the contract — the data source). `docs/design-mockups/cms-ui.html` lines 2198–2302 (the UI design). One existing CMS view (e.g. `subscribers.php`) for the view pattern.

**Touch:**
- `db/migrations/` — none (no new schema; uses existing `author` table from 0001)
- `lib/blocks_data.php` (new) — hardcoded port of BLOCKS.md §5, §6, §7a into PHP arrays
- `lib/author.php` (new) — `get_author()`, `save_author(...)`
- `cms/views/content-template.php` (new) — the full view (Master + sub-template panels + 4 master tabs)
- `cms/partials/sidebar.php` — replace `href="#"` on the Content Template link with `/cms/content-template`
- `site/index.php` — add GET `/cms/content-template` route + POST `/cms/content-template/save-author`
- `bin/deploy.sh` — `cp` lines for the three new files
- `docs/BUILD-PLAN.md` — mark Phase 14.5 complete on exit; update Phase 21 Decisions block (Content Template view now exists, rename-only)

**Don't touch:**
- `templates/*.php` (sub-template files — viewed but not edited)
- `lib/render.php` (no render-layer changes in α)
- `docs/BLOCKS.md` (it's the source of truth; this phase consumes it)
- Phase 15 a11y scope
- Any unfinished v2.0+ work

**On exit:** Phase 14.5 checked in §3. `/cms/content-template` route live on staging. Sidebar link goes there (no `href="#"`). Master Template view renders 4 working tabs (Content Blocks / Field Reference / Author info / PHP Layout File). Author info form saves to the `author` table. Sub-template panels show read-only visibility tables. PHP Layout File tab shows real file content. Phase 21's Decisions block updated to reflect that the view exists.

**Goal:** Ship the Content Template view to v1.0. Honor the design work in `docs/design-mockups/cms-ui.html`. Get the broken `href="#"` stub off the sidebar. Make Author info editable (the one piece of user-meaningful state in this view).

**Scope:**

**`lib/blocks_data.php`** — pure data, no I/O. Functions:
- `blocks_reference(): array` — keyed by slug. Each entry: `name`, `composition`, `purpose`. Sourced from BLOCKS.md §5 (19 blocks).
- `fields_reference(): array` — keyed by field name. Each entry: `php_var`, `description`. Sourced from BLOCKS.md §5 + §7a.
- `sub_templates_reference(): array` — keyed by sub-template slug. Each entry: `name`, `desc`, `php_file`. 6 sub-templates.
- `content_type_matrix(): array` — `matrix[sub_template_slug][block_slug] = mode` where mode is `always`/`optional`/`auto`/`required`/`—`. Sourced from BLOCKS.md §6.

**`lib/author.php`** — single-row CRUD for the `author` table (id=1, NULL-seeded):
- `get_author(): array` — returns the single row.
- `save_author(string $name, string $short, string $extended, ?string $image = null): void` — UPDATE. Trim inputs; convert empty strings to NULL.

**`cms/views/content-template.php`** — port the mockup with light state:
- URL state: `?tpl=<slug>` (master|article-standard|...) and `?tab=<key>` (blocks|fields|author|php) for master.
- POST `/cms/content-template/save-author` with CSRF → calls `save_author()` → 302 back to `?tpl=master&tab=author&saved=1`.
- Layout: two-pane (left list of templates, right detail panel). Active state on the selected template.
- Master detail = 4 tabs:
  - **Content Blocks:** read-only table of all 19 blocks (name / slug / composition / purpose). Info-box explaining what Master is.
  - **Field Reference:** read-only table of every field with PHP variable + description.
  - **Author info:** editable form with image picker (link to existing uploads if available, else file path text input — text input is acceptable for v1.0), name, short_description (textarea), extended_description (textarea), Save Author button. CSRF token. Success flash when `?saved=1`.
  - **PHP Layout File:** read content of `site/templates/master-layout.php`, display in monospace `<pre>` (escaped via `htmlspecialchars`). No editing.
- Sub-template detail = single panel (no tabs):
  - Info-box describing the sub-template (from `desc`).
  - Visibility table: for each block in the matrix where mode != `—`, show name / slug / mode pill / notes. Pill styling per mode (always / optional / auto / required) matching the mockup's `.mode-*` styles.
  - PHP Layout File preview: read the corresponding `templates/<sub>.php` file and show in monospace (same as master tab). If file missing (e.g. article-series), show a dashed-border placeholder noting the gap.
  - **No Save Template button.**

**`cms/partials/sidebar.php`** — change `href="#"` to `href="/cms/content-template"` on the Content Template nav item.

**`site/index.php`** — add two routes:
- GET `/cms/content-template` → require `cms/views/content-template.php`
- POST `/cms/content-template/save-author` → CSRF verify, call `save_author()`, redirect

**`bin/deploy.sh`** — add three `cp` lines (lib/blocks_data.php, lib/author.php, cms/views/content-template.php).

**Deliverables:**
- 2 new lib files
- 1 new CMS view
- 1 sidebar update (3-char edit)
- 1 router update
- 1 deploy.sh update
- Phase 14.5 checked in §3; Phase 21 Decisions block updated

**Verification:**
1. Staging: `/cms/content-template` loads (was 404 / dead link).
2. Sidebar "Content Template" link goes to the view (not `#`).
3. Master Template card selected by default; right panel shows Content Blocks tab with all 19 blocks listed.
4. Click Field Reference tab → table of every field with its PHP variable.
5. Click Author info tab → form pre-filled with current `author` row data. Save with edits → row updates → flash success.
6. Click PHP Layout File tab → contents of `templates/master-layout.php` shown in monospace.
7. Click each sub-template (article-standard, article-series, journal-entry, live-session, experiment, experiment-html):
   - Info-box visible
   - Visibility table renders blocks applicable to that sub-template with correct mode pills
   - PHP file preview shows the sub-template's PHP file (or placeholder for missing article-series)
   - No Save Template button visible
8. CSRF: POST without valid token → 403.
9. No new schema. Confirm only existing tables touched (`author` UPDATE on save).
10. No render-layer changes. Confirm `lib/render.php` and `templates/*.php` unchanged. Public articles still render identically.

**Out of scope:**
- Persistent block-visibility toggles per sub-template (β scope — deferred)
- `template_block_settings` table
- Render-layer suppression logic
- Editing template `.php` files from the CMS (PHP Layout File tab is read-only display)
- Image-upload integration for Author info (file path text input is sufficient for v1.0)
- A11y polish (Phase 15 will cover this view in the sweep)

**`article-series` template — resolved at implementation time.** No separate `article-series.php` file needed. Series articles render through `article-standard.php`; the series block (`block-series.php`) handles part-of-N rendering from within the standard template. The Content Template view's article-series sub-template panel shows the BLOCKS.md matrix as documentation with a dashed-border placeholder noting "no separate template file — composed inside article-standard."

**Deferred follow-up** (new entry for §3 Deferred block when triggered): "Content Template — editable visibility per sub-template" (β scope) — when a real use case for sub-template-wide block suppression emerges.

---

## 19.6. Phase 14.6 — Scheduled-publish UX (staging-only)

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Staging-only. Public ship at Phase 29 cutover (along with the rest of the CMS).

**Background — why this phase exists**

Phase 13 built the scheduled-publish *infrastructure* (the `cron/scheduled-publish.php` cron, the `published_status='scheduled'` schema column, the public-route gate that hides scheduled rows) but did NOT add the CMS UI to create a scheduled row. The capability is on prod from Phase 13, but only reachable via raw SQL. This phase closes the gap by adding the admin-facing UX across all four content types.

**Decisions captured (locked at plan time)**
- **Schedule state of truth:** `published_status='scheduled'` (existing ENUM value since migration 0001). `status='published'` AND `published_at` set to a future date. The cron promotes `'scheduled'` → `'live'` when `published_at <= NOW()`.
- **No schema change.** Everything used here is in 0001.
- **Right-side "Publish" section** in the draft edit view, radio pair:
  - `Publish immediately` (default)
  - `Schedule for later` → reveals a `<input type="datetime-local">`
- **Action row buttons** at the bottom of the form:
  - Default state (radio = Publish immediately): **Publish** + secondary **Set a schedule**
  - "Set a schedule" is a JS-driven shortcut: clicks the schedule radio + focuses the datetime input
  - After radio flips to Schedule: action row shows **Schedule →** (renamed Publish button) and the "Set a schedule" secondary disappears
- **Server-side rules:**
  - `action=schedule` requires a future `published_at`. Past date or empty → 422 with error flash, focus the input.
  - `action=publish` ignores the date input — captures NOW() and goes live immediately.
  - Unschedule path: edit + flip radio back to "Publish immediately" + Save → moves the row to Draft (`status='draft'`, `published_status=NULL`).
- **Pipeline view** gains a Scheduled column between Draft and Published:
  - Filters `published_status='scheduled'` ORDER BY `published_at` ASC
  - Cards show the scheduled date prominently
  - Drag Scheduled → Draft: unschedules
  - Drag Scheduled → Published: immediately publishes (sets `published_status='live'`, optionally bumps `published_at` to NOW if it's still in the future; preserve original date if already passed)
- **Published column query tightens:** from `status='published'` to `status='published' AND (published_status IS NULL OR published_status='live')`. NULL-guard is defensive — every current published row has `published_status='live'`.
- All 4 content types (article, journal, live-session, experiment) get the same UX. Single phase.

**Read at start (only):** This phase section. `lib/content.php` (the `transition_stage` function — the model for the new `schedule_content` helper). `cms/views/article-edit.php` (the right-side panel + action row pattern to mirror in the other 3 views). `cms/views/pipeline.php` (the column pattern to extend). `cron/scheduled-publish.php` (read-only — confirm what it expects).

**Touch:**
- `lib/content.php` — add `schedule_content(int $id, string $datetime): array` helper (parallel to `transition_stage`)
- `cms/views/article-edit.php` — add right-side Publish section, `action=schedule` handler, "Set a schedule" button
- `cms/views/journal-edit.php` — same
- `cms/views/live-session-edit.php` — same
- `cms/views/experiment-edit.php` — same
- `cms/views/pipeline.php` — Scheduled column + refined Published query
- `cms/_assets/style-cms.css` — styles for the new right-side Publish section + Pipeline Scheduled column
- `cms/_assets/scroll-actions.js` (or a new small JS file) — "Set a schedule" button → radio flip + datetime focus; radio-change → button label swap
- `docs/BUILD-PLAN.md` — mark Phase 14.6 complete on exit; correct Phase 19's Scheduled state Decision (it currently says derived from `status+published_at` — that's wrong; it's `published_status='scheduled'`)

**Don't touch:**
- `cron/scheduled-publish.php` — already works
- Public route gating in `lib/content.php` (lines 274, 626, 777 — already filters scheduled correctly)
- BLOCKS.md, render-layer, or any rendering templates
- Phase 19's broader sidebar/Writer's Desk reshape
- Any schema

**On exit:** Phase 14.6 checked in §3. Right-side Publish section visible in all 4 edit views at the Draft stage. "Set a schedule" affordance works. A scheduled row appears in Pipeline's new Scheduled column and is hidden from public until the cron flips it. v1.0 ships with full scheduling.

**Goal:** Complete the CMS-side scheduling UX so the infrastructure built in Phase 13 becomes usable from the admin panel.

**Scope:**

**`lib/content.php`** — new helper:
```
schedule_content(int $id, string $datetime): array
  - parses $datetime, rejects if not parseable or not in future
  - UPDATE content SET status='published', published_status='scheduled',
                       published_at=$datetime WHERE id=:id
  - returns ['ok' => bool, 'error' => string]
```

**Edit-view changes (all 4 views, same pattern):**
- New right-side panel section "Publish" with radio pair + datetime-local (revealed when "Schedule for later" selected)
- POST handler branch: `case 'schedule':` → validates future date → calls `schedule_content()` → 302 to listing with flash
- Action row at Draft stage: **Publish** (primary) + **Set a schedule** (secondary). When schedule mode is active (server-side or JS-driven), the Publish button label becomes "Schedule →" and "Set a schedule" hides.

**Pipeline view changes:**
- Insert Scheduled column between Draft and Published in the column order
- Lane query: `WHERE status='published' AND published_status='scheduled' ORDER BY published_at ASC`
- Lane card variant: shows `published_at` formatted as "Mon, Jun 12 · 2:00 PM" prominently
- Tighten Published lane to `status='published' AND (published_status IS NULL OR published_status='live')`

**JS for "Set a schedule" affordance:**
- On click: check the radio, dispatch a change event, focus the datetime input
- On radio change: toggle visibility of the datetime input + flip the Publish button label

**Deliverables:**
- 1 new helper in `lib/content.php`
- 4 edit-view patches
- 1 pipeline.php patch
- Small CSS for the right-side Publish section + Scheduled lane card variant
- Small JS for the radio/button choreography
- Phase 14.6 entry in §3; Phase 19 Decision corrected

**Verification:**
1. Article at Draft stage: right-side "Publish" section shows two radios; "Publish immediately" selected by default. Action row shows **Publish** + **Set a schedule**.
2. Click **Set a schedule** → radio flips to "Schedule for later", datetime input appears focused, action row's Publish button becomes **Schedule →**.
3. Enter a future date (5 minutes out) → click **Schedule →** → row appears in Pipeline → Scheduled column with date label.
4. Public `/writing/<slug>` still 404s.
5. Run cron manually (`php cron/scheduled-publish.php`) after the date passes → row flips to Pipeline → Published, public URL serves the page, log line written.
6. Repeat the flow for a Journal, Live Session, Experiment — same UX on all four.
7. Edit a scheduled row → flip radio back to "Publish immediately" + Save → row moves back to Draft, `published_status=NULL`, Pipeline reflects.
8. Edit a scheduled row → change the date → click **Schedule →** again → date updates, row stays in Scheduled.
9. Pipeline drag: drag a Scheduled card to Published → immediately publishes (`published_status='live'`), public URL serves.
10. Past date rejected: enter a date in the past + click Schedule → 422 with flash error, row unchanged.

**Out of scope:**
- New schema
- The full Writer's Desk reshape (Phase 19) — this only patches the existing Pipeline; the broader Ideation/Draft Writing split lands later
- "Recently Published" 5-row cap (Phase 19's Draft Writing concept)
- Render-layer changes
- Any non-scheduling polish

---

## 20. Phase 15 — Accessibility pass + final polish (staging-only)

**Session brief**

- **Autonomy:** Manual *(judgment-heavy)*
- **Ships:** Staging-only. Public ship of v1.0 is **Phase 29**.

**Decisions to capture before starting**
- Skip-to-content link target: `#main`
- Focus ring style: `2px solid var(--c-focus) with 2px offset (define --c-focus in tokens.css if missing)`
- Modal focus-trap library: `none — write a tiny vanilla helper, ~30 LOC`
- Screen reader to test with: `VoiceOver (macOS built-in)`

**Read at start (only):** This phase section. `ENGINEERING.md` (anything related to a11y conventions).

**Touch:** `cms/views/*.php` (ARIA, focus management, keyboard handlers). `cms/partials/*.php` (landmark roles, keyboard nav). `templates/*.php` (public-side a11y pass). `site/_design-system/css/components.css` (focus styles if refinement needed). `DEPLOYMENT.md` or new `RELEASES.md` (v1.0 notes).

**Don't touch:** New feature work. Public-facing nav / landing files (Phase 29). Deferred phases (17, 18).

**On exit:** Check Phase 15 in §3. Staging-only ship. The public v1.0 ship moment is **Phase 29**.

**Goal:** The CMS is keyboard-navigable, screen-reader-friendly, and visually polished. Public site (on staging) has the same pass.

**Scope:**
- Keyboard nav: logical tab order in every view; every interactive element reachable; focus indicators visible.
- ARIA: form labels, error announcements, modal focus trap, sidebar landmark roles.
- Visual polish: anything that's been bugging Alex through the build.
- Public a11y: skip-to-content, heading hierarchy, alt text enforced at save.

**Deliverables:**
- Updated `cms/views/*.php`, `cms/partials/*.php`
- Updated `templates/*.php`
- Any CSS refinements
- "v1.0 release notes" entry (in `RELEASES.md`)

**Verification:**
1. Walk the entire CMS using only the keyboard. Every action completes.
2. VoiceOver through Pipeline → Article edit → Save. Each step announced sensibly.
3. Visual diff (on staging) against pre-pass screenshots — nothing regressed.

**Out of scope:** The public cutover itself (Phase 29). Deferred phases 17, 18.

---

## 21. CMS Reorganization (v2.0) — project intro

The next three phases reshape the CMS admin around a new information architecture. The sidebar gets re-grouped (Overview / Writer's Desk / Library / Site / Collections / Audience / System); the Pipeline view splits into Ideation Board (existing, unchanged) and a new Draft Writing view; a Pages mock-edit surface and a Navigation editor land under the Site group; Content Templates gets renamed; and a new Settings view captures site-wide config.

The Overview and System groups arrive as visible placeholders to preview the future IA without committing to implementation. Most v2.0 work ships staging-only; the v2.0 prod ship happens at the end of Phase 21.

**Why this project exists.** v1.0 (Phases 0–16) was about hitting feature parity with the old site through a usable CMS. v2.0 is the first round of post-launch organizational polish — taking what was built fast and giving it a cleaner shape. No data model changes; mostly UI restructuring + two small new admin surfaces.

---

## 22. Phase 19 — Nav reorg + Writer's Desk (staging-only)

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Staging-only. v2.0 prod ship is end of Phase 21.

**Decisions to capture before starting**
- Sidebar group order: `Overview · Writer's Desk · Library · Site · Collections · Audience · System`
- OVERVIEW placeholders (Dashboard, Analytics, Post History): rendered as disabled items with `[future]` badge
- SITE placeholders (Pages, Navigation, Homepage, Redirects): Redirects stays live; Pages/Navigation/Homepage rendered as disabled `[future]` until Phase 20 enables them
- SYSTEM placeholders (Post Templates, Settings): both disabled `[future]` until Phase 21
- "Ideation Board" label = existing Ideation view, no functional change
- "Draft Writing" view: reshape of existing Pipeline. Columns: `Concept · Outline · Draft · Scheduled · Recently Published`
- Scheduled state derivation: `published_status='scheduled'` (existing column since migration 0001; the CMS UI for creating these rows ships in Phase 14.6, before this phase)
- Scheduled column sub-grouping: This Week / Next Week / Future, by calendar week (Mon 00:00 → Sun 23:59), in the author's timezone (America/Vancouver)
- Recently Published count: `5` (last 5 with `status='published' AND published_at <= NOW()`)
- Old `/cms/pipeline` URL: keep as-is and rename the view label, OR add `/cms/draft-writing` with 301 from old URL — *(default: keep old URL, rename label only — simpler, lower risk)*
- Group rename: Structure → Collections

**Read at start (only):** This phase section. `cms/partials/sidebar.php`. `cms/views/pipeline.php`. `CMS-STRUCTURE.md` §4 (sidebar IA) and §6 (Pipeline view).

**Touch:**
- `cms/partials/sidebar.php` — full restructure to new groups + disabled placeholders
- `cms/views/pipeline.php` — reshape to 5-column Draft Writing
- `lib/content.php` — add `list_scheduled_content()` (grouped by calendar week) and `list_recently_published($n=5)` helpers
- `cms/views/ideation.php` — no functional change; verify sidebar label flip is only visible diff
- `cms/_assets/style-cms.css` — week-group headers, Recently Published column styling, `[future]` placeholder styling
- `docs/BUILD-PLAN.md` — mark Phase 19 complete on exit

**Don't touch:**
- Library views per type (Articles/Journals/Live Sessions/Experiments)
- Any Phase 20 surface (Pages, Navigation editor, Homepage)
- Any Phase 21 surface (Post Templates, Settings)
- Any DS work
- `content` table schema

**On exit:** Phase 19 checked in §3. New sidebar IA visible on staging CMS. Ideation Board accessible (label flipped). Draft Writing shows 5 columns; Scheduled sub-groups by week; Recently Published shows top 5. Placeholders in OVERVIEW/SITE/SYSTEM render as disabled `[future]` badges. v2.0 prod ship deferred to Phase 21.

**Goal:** Sidebar matches new IA. Writer's Desk concept lands. Disabled placeholders preview future surfaces.

**Scope:**
- Sidebar restructure to new groups in the specified order
- Disabled placeholder treatment for not-yet-built items
- Pipeline → Draft Writing reshape per columns above
- Calendar-week bucketing inside Scheduled column
- Recently Published column on the right (last 5)

**Deliverables:**
- Updated `cms/partials/sidebar.php`
- Reshaped `cms/views/pipeline.php`
- New helpers in `lib/content.php`
- CSS for week groups + placeholder badges

**Verification:**
1. Sidebar on staging matches new IA, in specified group order.
2. Click "Ideation Board" → existing Ideation view loads.
3. Click "Draft Writing" → 5-column board: Concept / Outline / Draft / Scheduled / Recently Published.
4. Set a content row's `published_at` to a date in the current Mon–Sun window → appears in Scheduled → This Week with correct date label.
5. Same with a next-Mon–Sun date → Scheduled → Next Week.
6. Same with a 3-weeks-out date → Scheduled → Future.
7. Content row with `status=published` + past `published_at` → Recently Published. Only top 5 shown.
8. Disabled placeholder items don't navigate; carry `[future]` badge.
9. Existing `/cms/pipeline` URL still works (or 301s if rename chosen).

**Out of scope:** Pages CMS, Navigation editor, Homepage (Phase 20). Post Templates, Settings (Phase 21). DS work. Schema changes. Public site changes.

---

## 23. Phase 20 — Pages mocks + Navigation editor (staging-only)

**Session brief**

- **Autonomy:** Manual *(largest phase of v2.0 — two new admin surfaces with real complexity)*
- **Ships:** Staging-only. v2.0 prod ship is end of Phase 21.

**Decisions to capture before starting**
- Pages in scope for mock-edit view: scan `site/_pages/*.html` and `site/_pages/*.php` (excludes `_pages/_layout/`, `_pages/_bodies/`)
- Default page-mock view: Live Version (read-only, pulled from on-disk file content)
- Mock versioning: Create New Mock / Duplicate requires a name; Save overwrites the current mock; Rename available
- CodeMirror version: vendor locally to `cms/_assets/codemirror/` from official 6.x release (no CDN — avoids network dependency on every CMS load)
- Preview gating: `?_preview=<mock_id>` + valid CMS session cookie required
- Nav default highlight color: `--c-terracotta` (or whatever the current red-dot token resolves to — confirm exact token at phase start)
- Nav per-item color override: optional `highlight_color` field (hex or token name)
- Broken-target soft-flag UX: BROKEN badge in nav editor; `is_active=0` auto-set by nightly cron sweep
- `bin/deploy.sh`: **no changes** to deploy behavior for `_pages/*.html` — files remain canonical and ship as today
- Header/footer file rename: `_pages/_layout/header.html` → `header.php`, `footer.html` → `footer.php`
- Footer layout: flat single list (no columns/nested groups in v1)

**Read at start (only):** This phase section. v2.0 planning conversation context (extensive). `CMS-STRUCTURE.md` §16 (Indexes — for nav target resolution).

**Touch:**
- `db/migrations/0013_page_mock_versions.sql` (new — schema below)
- `db/migrations/0014_nav_items.sql` (new — schema below)
- `lib/pages.php` (new) — file scanning, mock CRUD, preview hook
- `lib/nav.php` (new) — nav items CRUD, target resolver, broken-target sweep
- `cms/views/pages.php` (new) — list of pages from filesystem + mock status
- `cms/views/page-edit.php` (new) — editor view (version dropdown, CodeMirror, Save/Rename/Duplicate/Delete/Preview, Metadata tab, unfurl preview)
- `cms/views/navigation.php` (new) — header + footer flat lists + add/edit panel
- `cms/partials/sidebar.php` — un-disable SITE group items (Pages, Navigation, Homepage)
- `cms/_assets/codemirror/` (new — vendored)
- `site/index.php` — add `/:slug/?_preview=<id>` route hook
- `_pages/_layout/header.html` → renamed `header.php`, calls `render_nav('header')`
- `_pages/_layout/footer.html` → renamed `footer.php`, calls `render_nav('footer')`
- `_pages/_layout/_page-shell.php` — pass-through hook for preview-mode body injection
- `bin/deploy.sh` — add `cp` lines for new lib files; update existing references from `header.html`/`footer.html` to `.php`
- Migration seed: initial nav items for header matching current static header
- `docs/BUILD-PLAN.md` — mark Phase 20 complete on exit

**Don't touch:**
- File content of any `_pages/*.html` (CMS reads them; never writes)
- Phase 19 surfaces
- Post Templates / Settings (Phase 21)
- Any DS work
- The `content` table or any per-type CMS views

**On exit:** Phase 20 checked in §3. CMS Pages view lists every scanned `_pages/*.html|.php` file with mock-edit status. Page editor functional with Live/mock dropdown, CodeMirror editing, Save/Rename/Duplicate/Delete/Preview, Metadata tab with og:image/title/description, unfurl preview card. Navigation editor: header + footer drag-orderable lists; add/edit panel with polymorphic targets; broken-target soft-flag working. **No file on disk is ever written by the CMS.** `bin/deploy.sh staging` continues to ship `_pages/*.html` unchanged.

**Goal:** Two new admin surfaces ship to staging. Pages stays mock-only (DB sandbox; files canonical). Navigation goes live (nav items CMS-data; wrapper PHP in code).

**Scope:**

**Schema — `page_mock_versions`**
```sql
CREATE TABLE page_mock_versions (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(120) NOT NULL,
  name              VARCHAR(255) NOT NULL,
  body_html         LONGTEXT NOT NULL,
  meta_title        VARCHAR(255) NULL,
  meta_description  TEXT NULL,
  og_image          VARCHAR(500) NULL,
  og_type           VARCHAR(40) NULL DEFAULT 'website',
  twitter_card      VARCHAR(40) NULL DEFAULT 'summary_large_image',
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX ix_slug_updated (slug, updated_at)
);
```

**Schema — `nav_items`**
```sql
CREATE TABLE nav_items (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  zone            ENUM('header','footer') NOT NULL,
  label           VARCHAR(120) NOT NULL,
  target_type     ENUM('index','category','series','content','page','custom') NOT NULL,
  target_id       INT NULL,
  target_slug     VARCHAR(120) NULL,
  custom_url      VARCHAR(500) NULL,
  highlight       ENUM('none','dot','pill') DEFAULT 'none',
  highlight_text  VARCHAR(40) NULL,
  highlight_color VARCHAR(20) NULL,
  position        INT NOT NULL DEFAULT 0,
  is_active       BOOLEAN DEFAULT TRUE,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX ix_zone_position (zone, position)
);
```

**Pages flow**
- List view scans `_pages/*.html|.php`, shows filename + last-modified + mock-count badge
- Edit view: version dropdown (Live Version pinned at top; mocks sorted `updated_at DESC` with name + relative date)
- Live Version selected: textarea read-only; action row = **Preview Live · Create New Mock**
- Mock selected: textarea editable (CodeMirror HTML mode + on-save validation warning); action row = **Save · Rename · Duplicate · Delete · Preview**
- Tabs: **Body HTML · Metadata**
- Metadata tab: meta_title, meta_description (with char counter), og_image (with uploads picker), og_type, twitter_card, live unfurl preview card
- Switching dropdown with unsaved edits: confirm "Discard unsaved edits?"
- No auto-save; soft "(unsaved changes)" indicator when textarea diverges from selected version

**Navigation flow**
- Two stacked drag-orderable lists (header + footer)
- Each item shows drag handle, label, resolved URL preview, highlight indicator, edit/delete
- Add-new panel: label, target-type dropdown, dependent picker (or custom URL field), highlight (none/dot/pill), pill text (when pill), color (default + custom hex)
- Custom URL validation: starts with `/` (internal, must end in `/`) or `https://` (external)
- Resolver returns URL for non-custom types; uses custom_url for custom; returns NULL if target row deleted
- Broken-target sweep: nightly cron checks resolver per item; sets `is_active=0` on broken; surfaces BROKEN badge in editor

**Preview integration**
- `site/index.php` `/:slug/` handler: if `?_preview=<id>` present, look up `page_mock_versions[id]`, verify slug matches, check CMS session via Auth::check(); if valid, pass `body_html` + metadata to `_page-shell.php` as overrides; else fall back to file rendering

**Header/footer integration**
- `header.php` calls `render_nav('header')` where the static `<a>` list used to be
- `footer.php` same for `render_nav('footer')`
- Initial nav-items seed migration ensures visible nav doesn't disappear when rename ships

**Deliverables:**
- 2 migrations
- 2 new lib files
- 3 new CMS views + sidebar update
- 2 file renames (`header.html`/`footer.html` → `.php`)
- CodeMirror vendored
- Cron extension for broken-target sweep
- Migration seed for initial header nav items

**Verification:**
1. CMS Pages view lists 7 marketing pages from filesystem with last-modified timestamps.
2. Click "about" → editor shows Live Version content (read-only) pulled from `_pages/about.html`.
3. Click "Create New Mock" → name dialog → editable mock created with current Live content.
4. Edit body in CodeMirror → Save → reload → mock persists.
5. Rename mock → name updates in dropdown.
6. Duplicate mock with new name → new mock with current textarea content; original unchanged.
7. Click Preview → opens `/about/?_preview=<id>` → page-shell renders mock body.
8. Delete mock → confirms → row removed → dropdown returns to Live Version.
9. Metadata tab: edit og_image → unfurl preview card updates instantly.
10. Navigation view: header list shows 4 seeded items in correct order.
11. Add nav item with type=Page, target=newsletter → resolves to `/newsletter/`.
12. Add nav item with type=Custom URL, value `/contact/` → renders correctly on staging header.
13. Add nav item with type=Custom URL, value `https://github.com/...` → renders correctly.
14. Drag-reorder items → positions update; refresh persists.
15. Add dot-only highlight → red dot renders next to label on staging header.
16. Add pill highlight with text "NEW" → pill renders next to label.
17. Set custom highlight color hex → pill uses that color.
18. Manually delete a referenced category from DB → next cron run marks dependent nav items broken with BROKEN badge; item hidden from public.
19. `_pages/about.html` file on disk: byte-identical before and after all of the above.
20. `bin/deploy.sh staging` succeeds normally; ships unchanged `_pages/*.html`.

**Out of scope:**
- Full CMS-published Pages (DB body served to public) — future
- File-write-back from CMS edits to repo
- Footer columns / nested nav groups
- Per-mock revision history beyond named snapshots
- Mock auto-save / localStorage
- Real-time unfurl scraper testing (Slack/Twitter bots can't pass the preview cookie)
- DS work
- Post Templates / Settings (Phase 21)

---

## 24. Phase 21 — Post Templates rename + Settings (v2.0 ship)

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** **v2.0 public.** Final v2.0 deploy; unfreeze v2.0-specific gates on prod.

**Decisions to capture before starting**
- **Content Template view status:** built in Phase 14.5 (read-only port + Author editable). Phase 21 work is now a true rename: change sidebar label from "Content Template" to "Post Templates" and update the page heading; the view itself stays as-is. No `[soon]` badge.
- Settings keys (v1): `site_title`, `site_tagline`, `default_og_image`, `default_og_type`, `default_twitter_card`, `footer_copyright`, `analytics_script`
- Settings inheritance: per-page-mock-metadata → Settings-default → hardcoded shell fallback
- v2.0 ship moment: end of this phase; same mechanics as Phase 29

**Read at start (only):** This phase section. `cms/partials/sidebar.php` (label flip). `_pages/_layout/_page-shell.php` (Settings integration site).

**Touch:**
- `db/migrations/0015_settings_table.sql` (new) — `settings` table
- `lib/settings.php` (new) — `get_setting`, `set_setting`, `list_settings`
- `cms/views/settings.php` (new) — form editor
- `cms/partials/sidebar.php` — flip "Content Template" → "Post Templates"; un-disable Settings; Post Templates label points at the existing `/cms/content-template` route (built in Phase 14.5) — no `[soon]` badge
- `_pages/_layout/_page-shell.php` — read settings for `<title>` suffix, og defaults, footer text, analytics script injection
- Migration seed: initial settings keys with current hardcoded values
- `docs/BUILD-PLAN.md` — mark Phase 21 complete on exit

**Don't touch:**
- Phase 19 / 20 work
- DS work
- Content Template view internals (built in Phase 14.5 — rename only, no functional changes here)

**On exit:** Phase 21 checked in §3. Settings view live and functional. "Post Templates" label live in sidebar, points at the Phase 14.5 view. Settings values propagate into public rendering. **v2.0 prod-shipped.**

**Goal:** Add Settings view with v1 keys. Rename Content Template → Post Templates (label only — the view itself shipped in Phase 14.5). Ship v2.0 to prod.

**Scope:**

**Schema — `settings`**
```sql
CREATE TABLE settings (
  `key`       VARCHAR(120) PRIMARY KEY,
  value       TEXT,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Settings view**
- One form, one input per setting, grouped by purpose:
  - **Identity:** site_title, site_tagline, footer_copyright
  - **Social preview defaults:** default_og_image (upload picker), default_og_type (radio), default_twitter_card (radio)
  - **Integrations:** analytics_script (textarea — accepts full `<script>` tag)
- CSRF-protected save action

**Shell integration**
- `_page-shell.php` reads `site_title` (used as title-suffix), `default_og_image` / `default_og_type` / `default_twitter_card` (rendered as `<meta>` tags when a page doesn't supply its own), `footer_copyright` (rendered in footer if present), `analytics_script` (injected raw before `</body>` if non-empty)

**Post Templates rename** *(view itself was built in Phase 14.5)*
- Rename sidebar.php label from "Content Template" to "Post Templates"
- Update the view's page heading to match the new label
- Link stays pointed at the existing `/cms/content-template` route — no `[soon]` badge, no disabled styling. The view is real and shipped.

**v2.0 ship checklist**
- Smoke-test every public route on prod after deploy
- Verify settings propagation: change site_title in staging Settings → confirm prod still serves prod settings (separate envs)
- Pre-cutover backup: mysqldump + webroot rsync snapshot, stored locally

**Deliverables:**
- 1 migration
- 1 new lib file
- 1 new view
- Sidebar label flip + `[soon]` badge
- `_page-shell.php` integration
- v2.0 prod deploy log + smoke results

**Verification:**
1. Settings view renders with 7 fields populated from seeded values.
2. Edit site_title → save → public page `<title>` reflects new value after reload.
3. Edit default_og_image → save → page without own og_image renders default in `<meta property="og:image">`.
4. Edit analytics_script with a `<script>` tag → save → script renders before `</body>` on every public page.
5. Sidebar: "Post Templates" under SYSTEM with `[soon]` badge; clicking does nothing.
6. v2.0 prod smoke: every CMS view accessible; every public page renders; nav items correct; subscribe flow still works.
7. **No regressions** from v1.0 prod baseline.

**Rollback plan:**
1. `git revert` Phase 21 commits and re-deploy.
2. Settings table can stay (unused); migration forward-only and safe.
3. Restore pre-cutover backup as last resort.

**Out of scope:**
- Building the actual Content Template / Post Templates view (deferred — separate phase)
- Master template + sub-template editing logic
- DS work
- Phase 18

---

## 25. DS Reorganization (v2.1) — project intro

The next seven phases (DS-1 through DS-6, with DS-4.5 in the middle) reorganize the design system into a clean three-branch structure: **Root** (shared tokens), **Pages** (marketing-page slice), **Blocks** (article-template slice), **CMS** (admin slice). Redundancy across the three branches is intentional — the goal is *clarity*, not deduplication.

**Why this project exists.** The CSS surface grew organically during v1.0. System tokens, marketing-page styles, article-template styles, and admin styles are scattered across `_design-system/system.css`, `_pages/_layout/style-pages.css`, `_templates/style-articles.css`, and inline `<style>` blocks in `cms/views/*.php`. There's no clean ownership boundary. This project draws those lines.

**Risk posture.** This is a refactor that touches every visual surface — public pages, articles, CMS. High blast radius if done as a single phase. We split into seven phases with these safety patterns:
- **Audit-first** — DS-1 produces a complete map before any code moves
- **Additive migration** — DS-2 through DS-5 add new structure alongside the old; old files keep loading
- **Per-phase visual diff** — every migration phase ships only after a screenshot diff of its affected surface
- **Staging-only through DS-5** — nothing public-facing on prod until DS-6 final ship
- **Manual sign-off on the riskiest phase** — DS-4 (Blocks) is highest-risk and is Manual tier

The existing deferred Phase 17 ("Design system unification") was the single-phase version of this same intent. It's superseded by this 7-phase project.

---

## 26. Phase 22 — DS-1: Audit (no code)

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Nothing — deliverable is `docs/DS-AUDIT.md`.

**Decisions to capture before starting**
- Audit document location: `docs/DS-AUDIT.md` (markdown, committed)
- Categorization buckets: `Root · Pages · Blocks · CMS · Dead`
- Files in scope: `_design-system/system.css`, `_pages/_layout/style-pages.css`, `_templates/style-articles.css`, all inline `<style>` blocks in `cms/views/*.php`, inlined token block in `docs/design-mockups/cms-ui.html`
- Audit row format: markdown table with columns `Selector · File · Category · Notes · Move-To-Path`

**Read at start (only):** This phase section. `docs/BUILD-PLAN.md` §26 (project intro). Every CSS file listed above.

**Touch:** `docs/DS-AUDIT.md` (new). Nothing else.

**Don't touch:** Any CSS. Any HTML or PHP. Research only.

**On exit:** Phase 22 checked in §3. DS-AUDIT.md complete with every selector categorized, file-layout proposed, naming decisions captured.

**Goal:** Complete map of the existing CSS surface so DS-2 through DS-6 know exactly what moves where.

**Scope:**
- Inventory every CSS file and inline block listed above
- For each selector, decide: Root / Pages / Blocks / CMS / Dead, with one-line reasoning
- Sketch the proposed `_design-system/` directory layout
- Flag selectors that span categories and propose resolution
- Capture naming conventions: prefix scheme per slice, file naming, token-vs-class boundaries
- Identify dead code (grep-verify before flagging)

**Deliverables:**
- `docs/DS-AUDIT.md` covering all of the above
- "Ready for DS-2" checklist at the bottom

**Verification:**
1. Every CSS file in the listed sources scanned.
2. Each selector has a row in the audit.
3. No selector uncategorized.
4. Proposed directory tree matches the v2.1 plan.
5. Naming-convention decisions documented and ready to apply in DS-2+.

**Out of scope:** Any code changes. JS audit. HTML structural changes.

---

## 27. Phase 23 — DS-2: Root tokens (staging-only)

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Staging-only. Additive — no consumer rewiring yet.

**Decisions to capture before starting**
- New directory: `site/_design-system/root/` with `colors.css`, `fonts.css`, `base.css`
- Approach: additive — existing `system.css` continues to load alongside new root files
- Verification page: `/_ds/__root-test.html` — imports only root, renders every token
- Old `system.css`: untouched (deletion happens in DS-6)

**Read at start (only):** This phase section. `docs/DS-AUDIT.md` (Root-tier rows). `_design-system/system.css`.

**Touch:**
- `site/_design-system/root/colors.css` (new)
- `site/_design-system/root/fonts.css` (new)
- `site/_design-system/root/base.css` (new)
- `site/_design-system/__root-test.html` (new)
- `bin/deploy.sh` — extend to ship new root files

**Don't touch:**
- `system.css`
- Any consumer CSS or PHP
- DS showcase site (DS-6 work)

**On exit:** Phase 23 checked in §3. Root files deployed to staging. `/_ds/__root-test.html` renders every token. No visual regression elsewhere.

**Goal:** Build the shared root layer in isolation, verify it independently, zero impact on running surfaces.

**Scope:**
- Per DS-AUDIT.md, populate `colors.css` with every Root-tier color token
- Populate `fonts.css` with `@font-face` declarations + font-family/weight/size tokens
- Populate `base.css` with spacing scale, radii, breakpoints, shadows, transition durations
- Build `__root-test.html` rendering each token as a labeled swatch/sample

**Deliverables:**
- 3 new CSS files in `_design-system/root/`
- 1 verification HTML page
- bin/deploy.sh updated

**Verification:**
1. `/_ds/__root-test.html` renders all colors as labeled swatches.
2. Renders all fonts as labeled samples at each size.
3. Renders spacing scale as visual rulers.
4. `system.css` still loads on every existing page.
5. Visual diff: every existing page (5 marketing, 1 article, 1 CMS view) identical before/after.

**Out of scope:** Anything not Root. Showcase site rebuild. Consumer rewiring.

---

## 28. Phase 24 — DS-3: Pages migration (staging-only)

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Staging-only. Additive — old `style-pages.css` keeps loading.

**Decisions to capture before starting**
- New directory: `site/_design-system/pages/` with `typography.css`, `layouts.css`
- Migration approach: copy Pages-categorized rules into new files; leave `style-pages.css` intact (additive)
- Consumer change: `_pages/_layout/_page-shell.php` adds new `<link>` tags alongside existing ones
- Visual diff: screenshot each marketing page before/after, save pairs to `docs/DS-VERIFY/pages/`

**Read at start (only):** This phase section. `docs/DS-AUDIT.md` (Pages-tier rows). `_pages/_layout/style-pages.css`.

**Touch:**
- `site/_design-system/pages/typography.css` (new)
- `site/_design-system/pages/layouts.css` (new)
- `site/_pages/_layout/_page-shell.php` — add new `<link>` imports
- `docs/DS-AUDIT.md` — annotate Pages migration complete
- `bin/deploy.sh` — ship new pages slice files
- `docs/DS-VERIFY/pages/` (new directory) — screenshot pairs

**Don't touch:**
- `style-pages.css`
- Any CMS or block CSS
- Any HTML structure

**On exit:** Phase 24 checked in §3. New pages slice loaded by `_page-shell.php` alongside old `style-pages.css`. Visual diff confirms zero regression. Staging-only.

**Goal:** Set up Pages branch and rewire `_page-shell.php`, old setup still loaded as safety net.

**Scope:**
- Per DS-AUDIT.md, copy Pages-categorized rules into `typography.css` and `layouts.css`
- Each file `@import`s `../root/*.css` first
- `_page-shell.php` adds new link tags after existing style-pages.css link

**Deliverables:**
- 2 new CSS files in `_design-system/pages/`
- Updated `_page-shell.php`
- Screenshot pairs (5 marketing pages × before/after)
- DS-AUDIT.md annotated

**Verification:**
1. Each marketing page on staging renders identically to its pre-DS-3 screenshot.
2. Network panel shows new pages slice files loading.
3. Toggling off `style-pages.css` in DevTools: page mostly retains layout (note gaps for DS-6).
4. CMS and article pages unaffected.

**Out of scope:** Removing `style-pages.css`. CMS or blocks work. Showcase updates.

---

## 29. Phase 25 — DS-4: Blocks migration (staging-only, Manual)

**Session brief**

- **Autonomy:** **Manual** *(highest-risk DS phase — touches every published article)*
- **Ships:** Staging-only. Additive — old `style-articles.css` keeps loading.

**Decisions to capture before starting**
- New directory: `site/_design-system/blocks/` with `blocks.css` (single file unless audit suggests splitting)
- Migration approach: copy Blocks-categorized rules from `style-articles.css` into `blocks.css`; leave original intact
- Consumer change: article template loader adds new `<link>` alongside existing one
- Visual diff: screenshot every published article + journal + live session + experiment (both variants); manual sign-off from Alex before phase exit

**Read at start (only):** This phase section. `docs/DS-AUDIT.md` (Blocks-tier rows). `_templates/style-articles.css`. `docs/BLOCKS.md` (block contract).

**Touch:**
- `site/_design-system/blocks/blocks.css` (new)
- `site/templates/master-layout.php` (or wherever article styles load) — add new `<link>`
- `docs/DS-AUDIT.md` — annotate Blocks migration complete
- `bin/deploy.sh` — ship new blocks slice
- `docs/DS-VERIFY/blocks/` (new directory) — screenshot pairs

**Don't touch:**
- `style-articles.css`
- Block HTML structure (pure CSS reorganization)
- Any Pages or CMS work
- `docs/BLOCKS.md` — recipe content is DS-4.5

**On exit:** Phase 25 checked in §3. New blocks slice loaded alongside old `style-articles.css`. Visual diff confirms zero regression on every published content row. **Manual sign-off from Alex before exit.** Staging-only.

**Goal:** Set up Blocks branch and rewire article rendering; old setup still loaded as safety net.

**Scope:**
- Per DS-AUDIT.md, copy Blocks-categorized rules into `blocks.css`
- `@import`s root first
- Article template loader adds new link tag after existing `style-articles.css` link
- Screenshot every published content row → save before-pair → ship to staging → save after-pair → manual diff

**Deliverables:**
- 1 new CSS file in `_design-system/blocks/`
- Updated article template loader
- Screenshot pairs (every published content row × before/after)
- DS-AUDIT.md annotated
- Manual sign-off note appended to phase exit

**Verification:**
1. Every published article on staging renders identically to its pre-DS-4 screenshot.
2. Every block (Hero, Quote, Gallery, etc. — all in BLOCKS.md) renders correctly in context.
3. Network panel shows `blocks.css` loading.
4. Toggling off `style-articles.css` in DevTools: page mostly retains structure (note gaps).
5. CMS pages and marketing pages unaffected.
6. **Manual sign-off:** Alex eyeballs each screenshot pair before phase exit.

**Out of scope:** Removing `style-articles.css`. New block types. BLOCKS.md recipe (DS-4.5). CMS work. Marketing-page work.

---

## 30. Phase 26 — DS-4.5: Block recipe doc

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Nothing public-facing. Documentation only.

**Decisions to capture before starting**
- Extend existing `docs/BLOCKS.md` with a "Recipe: Adding a new block" section
- Recipe covers: block contract entry, CSS scaffold, HTML scaffold, CMS editor field, showcase entry
- Worked example: one fully-described addition of a hypothetical new block

**Read at start (only):** This phase section. `docs/BLOCKS.md`. DS-AUDIT.md (Blocks section).

**Touch:** `docs/BLOCKS.md` (extend).

**Don't touch:** Any CSS. Any HTML. Any blocks themselves.

**On exit:** Phase 26 checked in §3. BLOCKS.md has a self-contained "Recipe" section.

**Goal:** Codify how to add new blocks, leveraging the cleaner Blocks structure from DS-4.

**Scope:**
- Recipe section in BLOCKS.md covering:
  1. Defining the block in the contract (slug, mode, composition, fields)
  2. Adding CSS to `_design-system/blocks/blocks.css`
  3. Adding HTML scaffold to article template
  4. Adding the CMS editor field
  5. Adding a showcase entry to the DS site (forward-ref to DS-6)
- Worked example using real selectors and real files

**Deliverables:**
- Extended `docs/BLOCKS.md`

**Verification:**
1. Recipe section is self-contained — readable without other context.
2. Worked example references real files and real selectors.

**Out of scope:** Actually adding a new block. CSS or code changes.

---

## 31. Phase 27 — DS-5: CMS migration (staging-only)

**Session brief**

- **Autonomy:** Semi-auto
- **Ships:** Staging-only. Additive — inline `<style>` blocks remain for now.

**Decisions to capture before starting**
- New directory: `site/_design-system/cms/` with `tables.css`, `navigation.css`, `buttons.css`, `fields.css`, `pills.css`, `states.css`
- Migration approach: collect CMS-categorized rules from inline `<style>` blocks + any CMS-relevant shared CSS; copy into new files; leave originals intact
- Consumer change: CMS header partial (or `cms/index.php`) adds `<link>` tags for new slice files
- Visual diff: screenshot each CMS view before/after

**Read at start (only):** This phase section. `docs/DS-AUDIT.md` (CMS-tier rows). All `cms/views/*.php`.

**Touch:**
- `site/_design-system/cms/tables.css` (new)
- `site/_design-system/cms/navigation.css` (new)
- `site/_design-system/cms/buttons.css` (new)
- `site/_design-system/cms/fields.css` (new)
- `site/_design-system/cms/pills.css` (new)
- `site/_design-system/cms/states.css` (new)
- `cms/partials/header.php` (or wherever CMS shell loads) — add `<link>` tags
- `docs/DS-AUDIT.md` — annotate CMS migration complete
- `bin/deploy.sh` — ship new cms slice
- `docs/DS-VERIFY/cms/` (new directory) — screenshot pairs

**Don't touch:**
- Inline `<style>` blocks in `cms/views/*.php` — left intact (cleanup is DS-6)
- Any Pages or Blocks work
- Any HTML structure

**On exit:** Phase 27 checked in §3. New CMS slice loaded alongside existing inline styles. Every CMS view renders identically. Staging-only.

**Goal:** Set up CMS branch and add imports; old inline styles remain as safety net.

**Scope:**
- Per DS-AUDIT.md, distribute CMS-categorized rules across the 6 cms-slice files
- Each file `@import`s root first
- CMS shell adds link tags
- Screenshot every CMS view before/after

**Deliverables:**
- 6 new CSS files in `_design-system/cms/`
- Updated CMS shell loader
- Screenshot pairs (~16 views × before/after)
- DS-AUDIT.md annotated

**Verification:**
1. Every CMS view on staging renders identically to its pre-DS-5 screenshot.
2. Public site unaffected.
3. Network panel shows the 6 cms slice files loading on every CMS view.

**Out of scope:** Removing inline `<style>` blocks. Showcase rebuild. Pages or Blocks work.

---

## 32. Phase 28 — DS-6: Cleanup + sunset (v2.1 ship)

**Session brief**

- **Autonomy:** Manual *(removes safety nets; ships v2.1 to prod)*
- **Ships:** **v2.1 public.** Final DS reorganization ship.

**Decisions to capture before starting**
- Remove `_design-system/system.css` entirely (root replaces it)
- Remove `_pages/_layout/style-pages.css` entirely (pages slice replaces it)
- Remove `_templates/style-articles.css` entirely (blocks slice replaces it)
- Strip inline `<style>` blocks from `cms/views/*.php` (one view at a time, per-view verification)
- Rebuild `_design-system/index.html` as 4-tab showcase: Root / Pages / Blocks / CMS
- Pre-cutover backup: mysqldump + webroot rsync snapshot

**Read at start (only):** This phase section. `docs/DS-AUDIT.md` (final state).

**Touch:**
- DELETE `site/_design-system/system.css`
- DELETE `site/_pages/_layout/style-pages.css`
- DELETE `site/_templates/style-articles.css`
- `cms/views/*.php` — strip inline `<style>` blocks (sequentially with verification)
- `_pages/_layout/_page-shell.php` — remove `<link>` tags for deleted files
- Article template loader — remove `<link>` for style-articles.css
- `site/_design-system/index.html` — full rebuild as 4-tab showcase
- `site/_design-system/index.js` — if needed for showcase interactivity
- `docs/DS-AUDIT.md` — final annotation
- `bin/deploy.sh` — remove `cp` lines for deleted files

**Don't touch:**
- Block recipe doc (DS-4.5 work done)
- Anything outside DS scope

**On exit:** Phase 28 checked in §3. Old CSS files deleted. Inline styles removed. Showcase rebuilt. **v2.1 prod-shipped.**

**Goal:** Remove redundant old CSS, rebuild showcase as four tabs, ship v2.1 to prod.

**Scope:**
- Delete old CSS files (3 files)
- Strip inline styles from each `cms/views/*.php` in sequence with per-view verification
- Rebuild `_design-system/index.html` with four tabs (Root / Pages / Blocks / CMS), each showing live-rendered examples + token swatches
- Deploy to prod

**Deliverables:**
- Deletions of old CSS
- Strip-cleaned cms/views/*.php
- Rebuilt showcase
- Updated bin/deploy.sh
- v2.1 prod deploy log
- Final visual-diff verification (every surface re-shot post-cleanup)

**Verification:**
1. `grep -r "system.css" site/` returns zero matches.
2. `grep -r "style-pages.css" site/` returns zero matches.
3. `grep -r "style-articles.css" site/` returns zero matches.
4. `grep -rn "<style>" site/cms/views/` returns zero matches (or only documented exceptions).
5. Every public page on prod renders identically to its v2.0 baseline.
6. Every CMS view on prod renders identically to its v2.0 baseline.
7. `/_ds/` showcase has 4 tabs (Root / Pages / Blocks / CMS), each rendering live components and tokens.

**Rollback plan:** If broken on prod after deploy:
1. `git revert` Phase 28 commit (reverses deletions + showcase rebuild).
2. Re-deploy. DS-2 through DS-5 setups still in place (additive); old files come back. Site returns to v2.0 visual state.
3. Pre-cutover backup is last-resort fallback.

**Out of scope:** New features. Deferred backlog items. Phase 18.

---

## 33. Phase 29 — Public cutover (v1.0 ship)

**Session brief**

- **Autonomy:** Manual *(high-stakes — the single moment public prod changes)*
- **Ships:** **v1.0 public.** The new marketing nav, the new landing copy, the new public index URLs all go live on alexmchong.ca.

**Decisions to capture before starting**
- All content audited: `every published article / journal / live session / experiment reviewed for production`
- Categories assigned: `every published row has a primary category set`
- Indexes configured: `each of /writing/, /journal/, /live-sessions/, /experiments/ has its desired title / subtitle / filter mode in CMS`
- Series ordering verified: `every series' parts are in the order Alex wants them to appear at /series/[slug]/`
- Marketing nav final structure confirmed: `What's UX 2.0 (red dot) / Thoughts / Talks / Work with me`
- Landing page copy final
- Pre-cutover backup taken: `mysqldump of prod DB and rsync snapshot of webroot, stored locally`

**Read at start (only):** This phase section. The current state of `site/_pages/_layout/header.html`, `site/_pages/_bodies/landing.html`, `site/templates/partials/nav.php` (the three files about to flip). `bin/deploy.sh` (the prod-skip list to remove).

**Touch:** `site/index.php` (remove the `APP_ENV === 'staging'` gate around public index routes). `bin/deploy.sh` (remove the three entries from the prod-skip list). `docs/design-mockups/landing-postcms.html` (delete — the canvas is now the live landing). `BUILD-PLAN.md` (mark Phase 29 complete).

**Don't touch:** Anything else. This phase is exclusively the unfreeze.

**On exit:** Check Phase 29 in §3. `https://alexmchong.ca/writing/`, `/journal/`, `/live-sessions/`, `/experiments/`, `/series/[slug]/` all live. Marketing nav across every `_pages/` page shows the new structure. Landing page shows the new copy. **Public ship #6 (and final v1.0 ship) confirmed.**

**Goal:** Flip the public-facing surface to its final state. Zero new features — only the unfreeze.

**Scope:**
- Remove the env-gate around the public index routes in `site/index.php`.
- Remove the prod-skip list entries in `bin/deploy.sh` for `_pages/_layout/header.html`, `_pages/_bodies/landing.html`, `templates/partials/nav.php`.
- Delete `docs/design-mockups/landing-postcms.html` (the future-canvas no longer needed; current state is `_pages/_bodies/landing.html`).
- Deploy to prod.
- Smoke-test every public route + every marketing page.

**Deliverables:**
- One diff removing the gate + the deploy skip-list entries
- Deleted `landing-postcms.html`
- Prod deploy log
- Smoke-test results

**Verification:**
1. `https://alexmchong.ca/` shows the new landing copy with the new marketing nav.
2. Every marketing page (`/about/`, `/coaching/`, `/work-with-me/`, `/resume/`, `/newsletter/`) shows the new nav with the active state matching the current page.
3. `https://alexmchong.ca/writing/`, `/journal/`, `/live-sessions/`, `/experiments/` each render the configured index with cards in colour, filter pills working.
4. `https://alexmchong.ca/series/[slug]/` renders for every series.
5. `https://alexmchong.ca/writing/[existing-slug]` still works (existing articles unaffected).
6. Every Phase 12-15 CMS capability still works after the flip.

**Out of scope:** New features. Migrations (all should be applied by this point). Deferred phases.

**Rollback plan.** If something visibly broken on prod after deploy:
1. Re-add the three files to `bin/deploy.sh` skip-list.
2. Re-deploy `bin/deploy.sh prod` to restore the old marketing chrome.
3. Re-add the env-gate to `site/index.php` and re-deploy to disable the new public routes.
4. The pre-cutover backup taken in Decisions is the last-resort fallback.

---

## 34. Phase 17 *(superseded)* — Design system unification

> **Superseded by the v2.1 project (Phases 22–28).** This was the original single-phase version of the DS reorganization. It's been expanded into a seven-phase project for risk control. Kept here for historical reference. The decisions and approach below are NOT current — see Phases 22–28 for the live plan.

**Session brief**

- **Autonomy:** Semi-auto

**Decisions to capture before starting** *(when triggered)*
- Promote `--canvas-raised` to brand-wide? *(default: yes — public site may want it for cards)*
- Promote `--live-green` to brand-wide? *(default: no — keep CMS-only, define inside a CMS-admin scope)*
- DS showcase nav structure for 3 audiences: `Brand / Admin / Article Templates` as top-level tabs *(alternative: nested)*

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §3.

**Touch:** `site/_design-system/css/tokens.css` (promote tokens). `site/_design-system/index.html` (add Admin and Article Templates sections). `site/_design-system/css/*.css` (reconcile naming). Every consumer importing CMS-admin-flavored CSS (`docs/design-mockups/cms-ui.html`, any CMS partials, `CLAUDE.md` folder map, `CMS-STRUCTURE.md` §3).

**Don't touch:** Visual treatments — this is reorganization only.

**On exit:** Check Phase 17 in §3. `grep -r "_design-system-cms" .` returns zero matches (it shouldn't exist; this is sanity-check that nothing snuck back).

**Goal:** Single canonical design system documenting Brand + Admin + Article Templates in one showcase.

**Scope (when triggered):**
- Promote per Decisions.
- Add Admin and Article Templates sections to the canonical showcase.
- Walk components in both areas, reconcile naming where the admin and brand sides have diverged.
- Update deployed `/_ds/`.

**Deliverables:**
- Canonical `site/_design-system/index.html` covering all three contexts
- Promoted tokens in `site/_design-system/css/tokens.css`
- Renamed/reconciled component CSS
- All consumers repointed
- Updated `CLAUDE.md` and `CMS-STRUCTURE.md` §3 (CMS-token promotion resolved)

**Verification:**
1. `/_ds/` shows every component previously documented for admin and every article-template block, plus the original brand tabs.
2. CMS admin renders identically before and after the merge (visual diff).
3. Public site article and index pages render identically.

**Out of scope:** Visual changes. Reorganization only.

---

## 35. Phase 18 *(deferred)* — Transactional email

**Session brief**

- **Autonomy:** Semi-auto

**Decisions to capture before starting** *(when triggered)*
- SMTP provider: `DreamHost SMTP` *(alternative: external like Postmark/Mailgun)*
- SMTP host: `[per DreamHost panel]`
- "From" address: `hello@alexmchong.ca`
- Password reset link TTL: `60 minutes`
- Subscriber confirmation link TTL: `7 days`
- Unsubscribe link: `signed token, no expiry` (alternative: 30 days)
- Email template style: `plain-ish, matches site typography (no heavy branding)`

**Read at start (only):** This phase section. `AUTH-SECURITY.md` (password-reset spec). `CMS-STRUCTURE.md` §20.

**Touch:** `lib/vendor/phpmailer/` (manual download). `lib/mailer.php` (new). `db/migrations/000X_password_reset_columns.sql` (new). `cms/login.php` (add Forgot link). `cms/reset.php` (new). `index.php` (extend with `/subscribe/confirm`, `/unsubscribe`). `cms/views/subscribers.php` (add confirmed filter).

**On exit:** Check Phase 18 in §3.

This phase lands when any of three triggers fires:
- Password recovery without admin access becomes a real need.
- Subscriber list grows enough to want double opt-in.
- Subscribers ask to be unsubscribed often enough that a one-click link saves real time.

**Scope (when triggered):**
- Vendor PHPMailer (manual download, no Composer).
- `lib/mailer.php`: `send_email($to, $subject, $body_html, $body_text)`.
- **Password reset:** new columns on `users`; Forgot link; signed token; `/cms/reset?token=…`; rate-limit (1 per email per hour).
- **Subscriber double opt-in:** confirm token on insert; `/subscribe/confirm?token=…` sets `confirmed_at`. Subscribers view gains "confirmed" filter; CSV export defaults to confirmed-only.
- **One-click unsubscribe:** every outgoing email footer includes `https://alexmchong.ca/unsubscribe?token=…`.

Not built in v1.

---

## 36. Canonical folder structure

This is the shape the repo will have by end of Phase 6b. Everything below is created across Phases 0–15 in the order needed. The split is intentional: **everything under `docs/` is reference and never deploys; everything under `site/` is rsynced to the DreamHost webroot.**

```
alex-cms-buildout/
├── .gitignore
├── README.md                          ← Phase 0
├── CLAUDE.md                          ← orientation (Claude auto-detects)
│
├── docs/                              ← REFERENCE · never deployed
│   ├── BUILD-PLAN.md                  ← (this file)
│   ├── CMS-STRUCTURE.md               ← canonical system spec
│   ├── ENGINEERING.md                 ← code conventions
│   ├── AUTH-SECURITY.md               ← Phase 4
│   ├── DEPLOYMENT.md                  ← Phase 1 (initial), 3 (extended), 13 (cron)
│   ├── LEGACY-ROUTES.md               ← Phase 0 / 1 / 13
│   ├── DESIGN.md                      ← brand design-system brief
│   ├── BLOCKS.md                      ← block contract
│   ├── onboarding/                    ← Phase 0
│   │   ├── README.md
│   │   ├── 01-git-and-github.md
│   │   ├── 02-dreamhost-setup.md
│   │   ├── 03-vscode-claude-code.md
│   │   └── 04-three-environments.md
│   └── design-mockups/                ← visual reference, not deployed
│       ├── cms-ui.html                ← canonical CMS UI mockup
│       └── landing-postcms.html       ← future-nav landing canvas
│
└── site/                              ← DEPLOYABLE · `rsync -a site/ <target>:<webroot>/`
    ├── .htaccess                      ← Phase 1 / 3 / 13
    ├── index.php                      ← Phase 3 (extended every phase that adds routes)
    ├── _pages/                        ← marketing pages (NOT CMS)
    │   ├── about.html, coaching.html, landing.html, resume.html,
    │   ├── work-with-me.html, newsletter.html, newsletter-confirmed.html
    │   └── _layout/
    │       ├── style-pages.css
    │       └── (brand images: logo, favicon, profile, background)
    ├── _design-system/                ← served at /_ds/ via routing (Phase 1 static → Phase 2 dynamic)
    │   ├── index.html                 ← showcase
    │   ├── system.css                 ← token source of truth
    │   └── system.js
    ├── _templates/                    ← PHP reads these (Phase 6b onward)
    │   ├── article.html               ← annotated reference (current)
    │   ├── layouts.html
    │   └── style-articles.css
    ├── config/                        ← Phase 3
    │   ├── config.example.php
    │   ├── config.php                 ← gitignored (each env has its own)
    │   └── .htaccess                  ← deny-all
    ├── db/                            ← Phase 3
    │   ├── migrate.php
    │   └── migrations/
    │       ├── 0001_initial_schema.sql
    │       ├── 0002_users_table.sql   ← Phase 4
    │       └── …
    ├── lib/                           ← Phase 3 onward (PHP modules)
    │   ├── db.php, router.php, auth.php, csrf.php
    │   ├── content.php, render.php, sanitize.php
    │   ├── uploads.php, folders.php, indexes.php
    │   ├── redirects.php
    │   └── subscribers.php
    ├── cms/                           ← Phase 4 onward (admin panel)
    │   ├── .htaccess
    │   ├── index.php, login.php, logout.php, account.php
    │   ├── reset.php                  ← Phase 18 (deferred)
    │   ├── views/, partials/, assets/
    ├── templates/                     ← Phase 6b onward (PHP rendering)
    │   ├── master-layout.php
    │   ├── article-standard.php, article-series.php
    │   ├── journal-entry.php, live-session.php
    │   ├── experiment.php, experiment-html.php
    │   ├── index-editorial.php, index-listing.php
    │   ├── 404.php
    │   └── partials/
    ├── cron/                          ← Phase 13
    │   ├── scheduled-publish.php
    │   └── backup.php
    ├── static/                        ← Phase 2 (image-pipeline cache)
    ├── uploads/                       ← runtime · gitignored
    │   ├── author/
    │   └── content/[type]/[slug]/
    └── content/                       ← runtime · gitignored (experiment-html source)
        └── experiment/[slug]/*.html
```

**Folder-naming conventions:**
- **`docs/` vs `site/`** — the deploy boundary. Anything under `docs/` is for humans + Claude; anything under `site/` ships to the server.
- **`_underscore` folders inside `site/`** (`_pages`, `_design-system`, `_templates`) — source files PHP reads, not URL paths the public sees. The underscore signals "don't expect a direct URL match." PHP's front controller turns these into clean URLs (`_pages/about.html` → `/about/`, `_design-system/` → `/_ds/`).
- **`lowercase` folders inside `site/`** (`config`, `db`, `lib`, `cms`, `templates`, `cron`, `static`, `uploads`, `content`) — runtime app code or runtime data. Either PHP code that executes, or directories the server writes into.

---

## 37. Cross-cutting principles

These apply in every phase. See `ENGINEERING.md` for the full rulebook.

- **The slug is the contract.** Never rename a published slug. If a URL must change, add a redirect.
- **One data-access function per entity.** Templates and views never write SQL. They call `get_article($slug)`, etc.
- **No raw hex outside `tokens.css`.** Every colour, every spacing value, every type size comes from a CSS variable.
- **No build step.** Files on the server are byte-identical to files in the repo.
- **No external libraries unless vendored.** PHP uses only built-in functions and PDO. JS uses Tiptap from CDN; no Node modules in the running app.
- **Migrations are append-only.** Never edit a past migration. Add a new one.
- **Every form is CSRF-protected.** Every input is escaped on render. Every DB query is parameterized.
- **Three-environment hygiene.** Code is identical across environments. Only `config/config.[env].php` differs.
- **Decisions captured upfront.** Every semi-auto phase's Session brief has a Decisions block. Answer it before starting the Claude Code session.

---

## 38. Working with this plan

**For Alex.**

1. Open the current phase's section in this file.
2. Review the Session brief, especially the **Decisions to capture before starting** block. Edit the defaults to your preference.
3. Save the file.
4. Start your Claude Code session.
5. Walk away (Auto / Semi-auto) or stay in the loop (Manual).
6. Return for the Verification checklist. This is your in-depth review.
7. Complete the brief's "On exit" checklist. Check the phase's box in §3. Open the next phase.

**For a Claude instance in the repo.**

1. Open `CLAUDE.md` (one-page orientation).
2. Open this file. Find the current phase in §3.
3. Read the **Session brief** at the top of that phase — Decisions answers included.
4. Open **only** the files the brief names.
5. Confine your work to the current phase's Deliverables.
6. If a decision was missed in the Decisions block, ask Alex once and add it to the brief for future reference.
7. Walk through the Verification list with Alex before declaring the phase complete.

**Why this works.** Earlier drafts of this plan required Claude to read 5+ docs at the start of every session (~30K tokens). The Session brief pattern drops that to 2K–5K. The Decisions block then collapses ~7 mid-stream clarification round-trips into one upfront review. Combined, ~70% of build time runs unattended.


---

## Deferred backlog (post-phases)

Items intentionally cut from a numbered phase to keep its scope tight. Each
captures the **what**, **why deferred**, and **rough cost** so the eventual
unblock is a near-zero spin-up. None of these block site launch — they're
polish, scale, or developer-quality concerns.

### From Phase 12

- **Routed category URLs** (e.g. `/writing/category/ux-industry/`).
  *Why deferred:* index pages currently filter client-side via pill toggles.
  Routed URLs add SEO + shareability but require a new route pattern and
  a reserved-slug rule. *Cost:* ~2h. Decision needed: `/writing/category/<slug>/`
  vs. `/writing/<slug>/` with category-first lookup.

- **Index page pagination.** Bottom "Showing N of M" + page-button strip,
  per the DS Full Page Index showcase. *Why deferred:* at v1 traffic and
  post count (<50 of any one type), pagination is real overhead with no
  user-visible benefit. *Cost:* ~1h when needed.

- **"+ Add Section" in editorial index builder** (per `CMS-STRUCTURE.md`
  §16 — stackable curated sections within an Editorial Page). *Why
  deferred:* the v1 Editorial layout supports hero + featured + one feed,
  which covers every page Alex has sketched. *Cost:* ~3h.

- **Manual sort for index feeds.** The `feed_sort` enum reserves `'manual'`
  but the builder doesn't surface a manual-ordering UI; `list_index_feed()`
  falls back to newest-first when manual is selected. *Cost:* ~2h
  (drag-orderable card list + per-index ordering table).

### Documentation drift

- **`CMS-STRUCTURE.md` §9 schema is stale.** It still lists `event_start
  DATETIME` for live-session rows; migration 0005 split that into
  `event_date / event_time / event_end_time` back in Phase 9. The
  divergence cost a debug round trip during Phase 12. *Fix:* one-pass
  reconciliation against the real `DESCRIBE content` output.
