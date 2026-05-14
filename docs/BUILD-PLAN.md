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

**Design system note.** The canonical design system lives at `site/_design-system/`. There is no separate `_design-system-cms/` variant during the build — `docs/design-mockups/cms-ui.html` already serves as the visual reference for admin components. The deferred DS Unification phase (Phase 16) documents admin and article-template components into the canonical DS post-v1.0.

---

## 3. Phase index

Each row shows the phase, autonomy tier, hour estimate, and (where applicable) what ships publicly when it's complete.

- [x] **Phase 0** — Setup & onboarding · *Manual* · 3–5h
- [x] **Phase 1** — Static site deployment · *Manual* · 2–3h · **Ships:** new alexmchong.ca live publicly
- [x] **Phase 2** — CSS module split + dynamic `/_ds/` · *Auto* · 3–4h
- [ ] **Phase 3** — Deployment plumbing (PHP + DB + router) · *Semi-auto* · 4–5h
- [ ] **Phase 4** — Auth (minimal v1) · *Semi-auto* · 4–5h
- [ ] **Phase 5** — Admin shell port · *Auto* · 4–5h
- [ ] **Phase 6a** — Articles in CMS (CRUD + Tiptap) · *Semi-auto* · 5–7h
- [ ] **Phase 6b** — Articles public · *Manual* · 4–6h · **Ships:** first Article live at `/writing/[slug]`
- [ ] **Phase 7** — Articles full workflow (Pipeline + transitions) · *Semi-auto* · 3–4h
- [ ] **Phase 8** — Journals end-to-end · *Semi-auto* · 4–5h · **Ships:** Journals live at `/journal/[slug]`
- [ ] **Phase 9** — Live Sessions end-to-end · *Semi-auto* · 4–5h · **Ships:** Live Sessions live
- [ ] **Phase 10** — Experiments end-to-end (both variants) · *Semi-auto* · 4–6h · **Ships:** Experiments live
- [ ] **Phase 11** — Categories + Series + slug guard · *Manual* · 3–4h
- [ ] **Phase 12** — Indexes + topbar nav switchover · *Manual* · 4–5h · **Ships:** site content-complete
- [ ] **Phase 13** — Redirects DB + cron + 404 + image UX + backup · *Auto* · 4–5h
- [ ] **Phase 14** — Newsletter subscribers end-to-end · *Semi-auto* · 3–4h
- [ ] **Phase 15** — Accessibility pass + final polish · *Manual* · 3–4h · **Ships:** v1.0
- [ ] **Phase 16** *(deferred)* — Design system unification · *Semi-auto* · 4–5h
- [ ] **Phase 17** *(deferred)* — Transactional email · *Semi-auto* · 4–5h

**Rule:** finish a phase before starting the next. If a phase reveals a missing decision, capture it in `CMS-STRUCTURE.md` §17 (Open Items), make the call with Alex, then continue.

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

**Don't touch:** `site/_pages/`, `site/_templates/`, any PHP, any CMS-scoped tokens (those land in the canonical DS later via Phase 16).

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

**Out of scope:** No PHP. No database. No CMS-admin DS variant (eliminated from active build; absorbed into Phase 16).

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

**Out of scope:** No email password reset (Phase 17). No multi-user (forever — that's a different system).

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

## 17. Phase 12 — Indexes + topbar nav switchover

**Session brief**

- **Autonomy:** Manual *(nav structure is a design call; atomic landing.html swap is high-stakes)*
- **Ships:** Site is content-complete. `landing-postcms.html` collapses into `landing.html`.

**Decisions to capture before starting**
- Topbar nav structure (post-CMS): defaults from `docs/design-mockups/landing-postcms.html` — `What's UX 2.0 / Thoughts / Talks / Work with me` *(edit to taste)*
- Section URL bindings (fill in the right side):
  - `What's UX 2.0` → `[your call — landing section or /writing/category/ux-2/]`
  - `Thoughts` → `/writing/`
  - `Talks` → `/live-sessions/`
  - `Work with me` → `/work-with-me.html`
- Default sort in index feeds: `published_at DESC`
- Series auto-index layout: `Editorial Page (with hero feature for the latest part)`

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §16 (Indexes — table schema, layout types, builder controls). `docs/design-mockups/landing-postcms.html`. `docs/design-mockups/cms-ui.html` (Indexes views).

**Touch:** `db/migrations/000X_indexes_table.sql` (new). `cms/views/indexes.php`, `index-new.php`, `index-edit.php` (new). `templates/index-editorial.php`, `index-listing.php` (new). `lib/indexes.php` (new). `docs/design-mockups/landing-postcms.html` (replace placeholders, then atomically rename to `landing.html`). `site/_pages/about.html`, `coaching.html`, `work-with-me.html`, `resume.html`, `newsletter.html` (propagate the new nav).

**Don't touch:** Polish phases (13–15). `_design-system*` (already done).

**On exit:** Check Phase 12 in §3. `landing-postcms.html` no longer exists. Nav structure identical across every `site/_pages/*.html`. **Public ship #6 confirmed.**

**Goal:** Every editorial index renders. The Index Builder works in the CMS. Topbar nav points at the new CMS-served sections.

**Scope:**
- `indexes` table (slug, layout type, config JSON).
- Index Builder UI: page-title block, hero feature (Editorial only), featured articles (Editorial only), content feed with filter chips / sort chips / rows-shown, `+ Add Section` (Editorial only).
- Public-side rendering for both layout types.
- Series auto-index: every series gets a `/series/[slug]/` Editorial Page listing its parts in `series_order`.
- **Topbar nav cutover** per Decisions, via `landing-postcms.html` → `landing.html` rename.

**Deliverables:**
- `db/migrations/000X_indexes_table.sql`
- `cms/views/indexes.php`, `index-new.php`, `index-edit.php`
- `templates/index-editorial.php`, `index-listing.php`
- `lib/indexes.php`
- Updated topbar nav across all `site/_pages/*.html`
- `landing-postcms.html` collapsed into `landing.html`

**Verification:**
1. Visit `/writing/`. Basic Listing renders every published article.
2. Visit `/digital-garden/` (or whichever Editorial index). Editorial Page renders with hero + featured + feed sections.
3. Visit `/series/[slug]/`. Series index lists every part in `series_order`.
4. Topbar nav on every public page matches the Decisions structure with current section marked `is-active`.

**Out of scope:** Cron, redirects DB migration, newsletter, a11y (Phases 13–15).

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

**Don't touch:** Phase 13's work. A11y (Phase 15). Double opt-in via email (Phase 17).

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

**Out of scope:** Double opt-in by email (Phase 17). One-click unsubscribe via email link (Phase 17). The CMS can manually mark people unsubscribed here.

---

## 20. Phase 15 — Accessibility pass + final polish

**Session brief**

- **Autonomy:** Manual *(judgment-heavy)*
- **Ships:** v1.0.

**Decisions to capture before starting**
- Skip-to-content link target: `#main`
- Focus ring style: `2px solid var(--c-focus) with 2px offset (define --c-focus in tokens.css if missing)`
- Modal focus-trap library: `none — write a tiny vanilla helper, ~30 LOC`
- Screen reader to test with: `VoiceOver (macOS built-in)`

**Read at start (only):** This phase section. `ENGINEERING.md` (anything related to a11y conventions).

**Touch:** `cms/views/*.php` (ARIA, focus management, keyboard handlers). `cms/partials/*.php` (landmark roles, keyboard nav). `templates/*.php` (public-side a11y pass). `site/_design-system/css/components.css` (focus styles if refinement needed). `DEPLOYMENT.md` or new `RELEASES.md` (v1.0 notes).

**Don't touch:** New feature work. Deferred phases (16, 17).

**On exit:** Check Phase 15 in §3. **Public ship #7 — v1.0 — confirmed.**

**Goal:** The CMS is keyboard-navigable, screen-reader-friendly, and visually polished. Public site has the same pass.

**Scope:**
- Keyboard nav: logical tab order in every view; every interactive element reachable; focus indicators visible.
- ARIA: form labels, error announcements, modal focus trap, sidebar landmark roles.
- Visual polish: anything that's been bugging Alex through the build.
- Public a11y: skip-to-content, heading hierarchy, alt text enforced at save.

**Deliverables:**
- Updated `cms/views/*.php`, `cms/partials/*.php`
- Updated `templates/*.php`
- Any CSS refinements
- "v1.0 release notes" entry

**Verification:**
1. Walk the entire CMS using only the keyboard. Every action completes.
2. VoiceOver through Pipeline → Article edit → Save. Each step announced sensibly.
3. Visual diff against pre-pass screenshots — nothing regressed.

**Out of scope:** Phases 16, 17.

---

## 21. Phase 16 *(deferred)* — Design system unification

**Session brief**

- **Autonomy:** Semi-auto

**Decisions to capture before starting** *(when triggered)*
- Promote `--canvas-raised` to brand-wide? *(default: yes — public site may want it for cards)*
- Promote `--live-green` to brand-wide? *(default: no — keep CMS-only, define inside a CMS-admin scope)*
- DS showcase nav structure for 3 audiences: `Brand / Admin / Article Templates` as top-level tabs *(alternative: nested)*

**Read at start (only):** This phase section. `CMS-STRUCTURE.md` §3.

**Touch:** `site/_design-system/css/tokens.css` (promote tokens). `site/_design-system/index.html` (add Admin and Article Templates sections). `site/_design-system/css/*.css` (reconcile naming). Every consumer importing CMS-admin-flavored CSS (`docs/design-mockups/cms-ui.html`, any CMS partials, `CLAUDE.md` folder map, `CMS-STRUCTURE.md` §3).

**Don't touch:** Visual treatments — this is reorganization only.

**On exit:** Check Phase 16 in §3. `grep -r "_design-system-cms" .` returns zero matches (it shouldn't exist; this is sanity-check that nothing snuck back).

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

## 22. Phase 17 *(deferred)* — Transactional email

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

**On exit:** Check Phase 17 in §3.

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

## 23. Canonical folder structure

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
    │   ├── reset.php                  ← Phase 17 (deferred)
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

## 24. Cross-cutting principles

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

## 25. Working with this plan

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
