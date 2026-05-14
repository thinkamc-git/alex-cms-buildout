# Deployment — alexmchong.ca

**Status:** First draft (Phase 1). This document evolves over the build:
- **Phase 1** — manual CloudMounter workflow for the static marketing site (this document).
- **Phase 3** — extends with PHP + database plumbing and an `rsync` script that replaces the manual workflow.
- **Phase 13** — adds the backup cron and 404 hook.

The current document covers **Phase 1 only**.

---

## 1. Environments

| Environment | URL | Webroot (DreamHost) | Notes |
|---|---|---|---|
| Staging | `https://staging.alexmchong.ca` | `~/staging.alexmchong.ca/` | Basic-Auth gated. Subdomain configured in DreamHost panel. |
| Production | `https://alexmchong.ca` | `~/alexmchong.ca/` | Public. Final destination after staging passes. |

**Promotion rule:** Nothing reaches production until it has been verified on staging. Phase 1 enforces this by hand; Phase 3 enforces it via the deploy script's `--target` flag.

---

## 2. What ships in Phase 1

```
site/_pages/*.html              → webroot /                  (landing.html → index.html on upload)
site/_pages/_layout/*           → webroot /_layout/          (verbatim)
site/_design-system/*           → webroot /_ds/              (verbatim)
site/.htaccess                  → webroot /.htaccess         (production)
deploy/staging.htaccess         → webroot /.htaccess         (STAGING ONLY)
<generated> .htpasswd           → path referenced in staging .htaccess
```

**Not deployed in Phase 1:** `site/_templates/` (PHP renderers, Phase 3+), `docs/` (private), `landing-postcms.html` (design canvas, never deployed).

---

## 3. CloudMounter workflow

CloudMounter mounts the DreamHost SFTP target as a Finder volume. Drag-and-drop is the operative verb.

### 3.1 One-time setup

1. Install CloudMounter (App Store or [eltima.com/cloudmounter](https://www.eltima.com/cloudmounter/)).
2. Add a connection for each environment:
   - **Staging** — `staging.alexmchong.ca` over SFTP, user from DreamHost panel, port 22.
   - **Production** — `alexmchong.ca` over SFTP, same credentials.
3. Save both in CloudMounter so they appear in Finder.

### 3.2 First-time deploy (Phase 1)

Order: staging → verify → production → verify → cleanup → verify.

**Step 1. Stage the upload locally.**
Open a Finder window at the repo's `site/` folder.

**Step 2. Upload to staging.**
1. Mount the staging volume in Finder.
2. Copy contents of `site/_pages/` to the staging webroot:
   - Drag every `*.html` file from `site/_pages/` into the staging webroot root.
   - Drag the `_layout/` folder into the staging webroot root.
3. **Rename** `landing.html` → `index.html` on the staging server (in-place).
4. Copy `site/_design-system/` to the staging webroot as `_ds/`:
   - Create a folder `_ds` at the staging webroot root.
   - Drag the contents of `site/_design-system/` (`index.html`, `system.css`, `system.js`) into `_ds/`.
5. Copy `deploy/staging.htaccess` to the staging webroot, renaming to `.htaccess`.
6. Generate `.htpasswd`:
   - Use one of the methods in `deploy/staging.htpasswd.example`.
   - Username: `alex`.
   - Password: stored in 1Password under "alexmchong.ca staging".
   - Place the generated `.htpasswd` somewhere readable by Apache. Recommended: `~/staging.alexmchong.ca/.htpasswd` (inside the webroot — Apache refuses to serve dotfiles by default, so it is not web-exposed). If your shell access allows, `~/staging.alexmchong.ca.htpasswd` (one level above webroot) is safer.
7. Open `deploy/staging.htaccess` on the server in a text editor and replace `REPLACE_WITH_ABSOLUTE_PATH_TO/.htpasswd` with the absolute server path you used in step 6 (e.g., `/home/<user>/staging.alexmchong.ca/.htpasswd`).

**Step 3. Verify staging.**

Visit each of these in a browser and confirm:

- `https://staging.alexmchong.ca/` — prompts for username/password (Basic Auth), then renders the new landing.
- `https://staging.alexmchong.ca/about/` — renders the About page.
  (Trailing-slash variants depend on DreamHost; try both `/about` and `/about.html` if needed.)
- `https://staging.alexmchong.ca/coaching.html`, `/work-with-me.html`, `/resume.html`, `/newsletter.html`, `/newsletter-confirmed.html` — all render.
- `https://staging.alexmchong.ca/_ds/` — design system showcase renders.
- `https://staging.alexmchong.ca/landing.html` — 302-redirects to `/`.

From your terminal (the `-u` flag passes Basic Auth credentials):

```
curl -I -L -u alex:<password> https://staging.alexmchong.ca/portfolioforhire/
curl -I -L -u alex:<password> https://staging.alexmchong.ca/research/
curl -I -L -u alex:<password> https://staging.alexmchong.ca/talks/
curl -I -L -u alex:<password> https://staging.alexmchong.ca/meet/
curl -I -L -u alex:<password> https://staging.alexmchong.ca/linkedin/
```

Each should show a `302 Found` with the correct `Location:` from `docs/LEGACY-ROUTES.md` §2 before following the redirect.

**Step 4. Upload to production.**
1. Mount the production volume in Finder.
2. Repeat steps 2.1–2.4 above, **but use `site/.htaccess`** (not `deploy/staging.htaccess`) and do **not** create `.htpasswd`. Production is public.

**Step 5. Verify production.**

```
curl -I -L https://alexmchong.ca/portfolioforhire/
curl -I -L https://alexmchong.ca/research/
curl -I -L https://alexmchong.ca/talks/
curl -I -L https://alexmchong.ca/meet/
curl -I -L https://alexmchong.ca/linkedin/
```

Each should show `302 Found` followed by the expected destination.

Visit `https://alexmchong.ca/`, `https://alexmchong.ca/about/`, `https://alexmchong.ca/_ds/`, etc. Confirm every marketing page renders and every internal link works.

**Step 6. Cleanup (legacy folder deletion).**

**Only after step 5 passes**, delete the old production folders that previously served the legacy URLs. Per `docs/LEGACY-ROUTES.md` §3 the Phase 0 walkthrough found **only the five redirect subfolders** to remove:

```
~/alexmchong.ca/portfolioforhire/
~/alexmchong.ca/research/
~/alexmchong.ca/talks/
~/alexmchong.ca/meet/
~/alexmchong.ca/linkedin/
```

Delete each folder via CloudMounter. **Then re-run the five production `curl` checks from step 5 — they must still pass** (the `.htaccess` redirects, not the folders, are what makes them work now).

If any unexpected folder is found that was not in the inventory, **stop and add a row to `docs/LEGACY-ROUTES.md` §3 before deleting**. Do not delete anything that is not catalogued.

---

## 4. Rollback

If a production deploy breaks something during Phase 1:

- **Single page broken** — re-upload the previous version from your local git working copy. Each marketing page is independent.
- **`.htaccess` broken** (e.g., 500 across the site) — rename `.htaccess` to `.htaccess.broken` via CloudMounter; the site reverts to no-rewrites Apache defaults, then re-upload `site/.htaccess`.
- **Whole-site catastrophe** — re-upload from your local `site/` folder. There is no database to restore in Phase 1.

Phase 3 introduces the deploy script with automatic backup-on-deploy.

---

## 5. Decisions captured (Phase 1)

From `docs/BUILD-PLAN.md` §5:

- **Landing page approach:** rename `landing.html` → `index.html` on upload (with `.htaccess` rewrite from `/landing.html` to `/` to keep internal links resolving).
- **Legacy redirect status code:** `302` until Phase 13.
- **Staging gate username:** `alex`.
- **Staging gate password:** stored in 1Password under "alexmchong.ca staging".
- **Deletion order:** deploy redirects first, verify, **then** delete legacy folders.

---

## 6. What changes in Phase 3

Phase 3 introduces:
- A real deploy script (`deploy/deploy.sh` or similar) that `rsync`s `site/` to the target with the right exclusions, replacing manual CloudMounter steps.
- A PHP front controller and `index.php` at the webroot — both `.htaccess` files gain the `RewriteCond !-f / !-d / RewriteRule . /index.php [L]` block at the bottom.
- A separate writable `data/` directory above the webroot for uploads, the SQLite/MySQL connection config, and cron-managed state.

This document is updated at that time to reflect the new workflow. Until then, the CloudMounter dance above is canonical.
