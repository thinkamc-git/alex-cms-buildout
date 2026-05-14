# Deployment — alexmchong.ca

**Status:** SSH-key-based rsync deploy via [bin/deploy.sh](../bin/deploy.sh). Replaces the manual CloudMounter workflow used during Phase 1's first ship. The deploy script was pulled forward from Phase 3 once Phase 2 (Auto-tier) was about to start, because manual CloudMounter every CSS tweak would have broken the auto-tier promise.

This document covers:

1. Environments
2. What ships
3. One-time SSH setup (skip if already done)
4. Running a deploy
5. Rollback
6. Database deploys (Phase 3)
7. Auth bootstrap (Phase 4 — setup.php and lockouts)
8. What still changes in later phases

---

## 1. Environments

| Environment | URL | Server path | Auth |
|---|---|---|---|
| Staging | `https://staging.alexmchong.ca` | `/home/alexmchong/staging.alexmchong.ca/` | Basic Auth (`alex` + 1Password "alexmchong.ca staging") |
| Production | `https://alexmchong.ca` | `/home/alexmchong/alexmchong.ca/` | Public |

Both live on DreamHost shared hosting at `iad1-shared-b7-12.dreamhost.com` (Apache 2.4 + PHP 8.3 as of 2026-05-13).

**Promotion rule:** never ship to production without first verifying on staging. The deploy script reads two different `.htaccess` files (`deploy/staging.htaccess` adds Basic Auth on top of the production rules) — same content + same redirects, different gate.

---

## 2. What ships

```
site/_pages/*.html           → webroot/                  (landing.html renamed to index.html during deploy)
site/_pages/_layout/*        → webroot/_layout/          (CSS, fonts, images)
site/_design-system/*        → webroot/_ds/              (design system showcase)
site/index.php               → webroot/index.php         (Phase 3: front controller)
site/lib/*.php               → webroot/lib/              (Phase 3: db.php, router.php)
site/config/{config,config.example}.php
                             → webroot/config/           (Phase 3: env resolver + template)
site/config/.htaccess        → webroot/config/.htaccess  (Phase 3: deny direct web access)
site/db/migrate.php          → webroot/db/migrate.php    (Phase 3: CLI migration runner)
site/db/migrations/*.sql     → webroot/db/migrations/    (Phase 3: schema migrations)
site/lib/auth.php            → webroot/lib/auth.php      (Phase 4: session + login + lockout)
site/lib/csrf.php            → webroot/lib/csrf.php      (Phase 4: per-session CSRF tokens)
site/setup.php               → webroot/setup.php         (Phase 4: one-shot bootstrap — see §7)
site/cms/*.php               → webroot/cms/              (Phase 4: login.php, logout.php, account.php, index.php placeholder)
site/.htaccess               → webroot/.htaccess         (PRODUCTION ONLY)
deploy/staging.htaccess      → webroot/.htaccess         (STAGING ONLY — adds Basic Auth gate)
```

Phase 3 added one fallback rule to both `.htaccess` files: any URL that isn't a real file or directory rewrites to `/index.php` for routing. Static marketing pages still serve directly.

**Never deployed:** `docs/`, `reference/`, `bin/`, `deploy/staging.htpasswd.example`, anything under `landing-postcms.html`, and the per-environment config files `site/config/config.{local,staging,production}.php` (each lives on its own server only — see §6.2).

**Preserved on the server, never overwritten** (the rsync exclude list in [bin/deploy.sh](../bin/deploy.sh)):

- `.dh-diag`, `.dh-diag.txt`, `.ftpquota` — DreamHost system files
- `.well-known/` — Let's Encrypt SSL renewal challenges. Touching this breaks HTTPS auto-renewal.
- `.htpasswd` — staging Basic Auth credentials. Generated on the server, never in source.
- `_archive/`, `_labs/`, `_files/` — your live experimental folders, kept as-is per Phase 1 decisions.
- `config/config.{local,staging,production}.php` — per-environment DB credentials, hand-placed on each server. Without these excludes, a redeploy with `--delete` wipes them and the next request 500s with "Missing config file: …". (Bug discovered and fixed mid-Phase 3.)
- `uploads/`, `content/`, `logs/`, `backups/` — server-only runtime folders that the app writes to (per-content uploads, custom-HTML experiments, request logs, backup snapshots). The source-side `.gitignore` excludes them too.

---

## 3. One-time SSH setup

If you're setting up a new machine or revoking and reissuing keys, do this once. Skip if `ssh alexmchong-ca` already works.

### 3.1 Generate a dedicated deploy key

```bash
ssh-keygen -t ed25519 \
  -f ~/.ssh/id_ed25519_alexmchong_ca_deploy \
  -N "" \
  -C "alexmchong.ca deploy ($(date +%Y-%m-%d) from $(hostname -s))"
```

(Ed25519, no passphrase so automated runs don't prompt. The on-disk private key is mode 600 by default; FileVault on the Mac is the second layer.)

### 3.2 Add the SSH host alias

Append to `~/.ssh/config` (create the file if it doesn't exist; chmod 600):

```
Host alexmchong-ca
  HostName alexmchong.ca
  User alexmchong
  IdentityFile ~/.ssh/id_ed25519_alexmchong_ca_deploy
  IdentitiesOnly yes
  ServerAliveInterval 30
```

This lets you `ssh alexmchong-ca` and `rsync … alexmchong-ca:…` without typing the username, host, or key path every time.

### 3.3 Authorize the public key on DreamHost

The DreamHost panel doesn't expose a per-user "SSH Keys" page in the current redesign. Two paths that work:

**A. File Manager (web UI).** Panel → click the SFTP user `alexmchong` → File Manager → navigate to `/home/alexmchong/`. Show hidden files. Create or open `~/.ssh/authorized_keys` and paste the contents of `~/.ssh/id_ed25519_alexmchong_ca_deploy.pub` on its own line.

**B. CloudMounter.** Mount the SFTP volume, navigate to `/home/alexmchong/`, show hidden files, drag the prepared `.ssh/` bundle into the home directory. Permissions must end up at `700` on `.ssh/` and `600` on `authorized_keys` (use Get Info → Permissions if they land wrong).

### 3.4 Verify

```bash
ssh alexmchong-ca 'whoami && pwd && hostname'
```

Expected: `alexmchong`, `/home/alexmchong`, `iad1-shared-b7-12`. No password prompt.

### 3.5 Revoking access

If a machine is compromised or you stop using one, delete the corresponding line from `~/.ssh/authorized_keys` on the server. Takes ~10 seconds via SSH or File Manager. The key on disk on the old machine then becomes useless.

---

## 4. Running a deploy

```bash
bin/deploy.sh staging              # → https://staging.alexmchong.ca
bin/deploy.sh prod                 # → https://alexmchong.ca

bin/deploy.sh staging --dry-run    # preview every file rsync would touch
bin/deploy.sh prod --no-delete     # upsert only; do not remove server-side orphans
```

### 4.1 What the script does

1. **Assembles** a per-target deploy directory in `/tmp/alexmchong-deploy-XXXX/`. Marketing pages, the layout assets, the design system, and the right `.htaccess` for the target are copied in. `landing.html` is renamed to `index.html` during this step.
2. **Normalizes permissions** on the local stage (`644` for files, `755` for directories). Rsync `-a` preserves these on the server.
3. **Rsyncs** to the SSH host alias `alexmchong-ca`, with:
   - `--delete` (default): orphan files on the server that aren't in source are removed. The exclude list (§2 above) protects DreamHost system files, Let's Encrypt, and your experiments.
   - `--no-delete` (flag): skip the orphan cleanup. Use this if you're worried something on the server is going to be flagged that shouldn't be.
   - `--dry-run` (flag): show the transfer plan without writing anything to the server.

### 4.2 Typical session

```bash
# Confirm what's about to happen
bin/deploy.sh staging --dry-run

# Looks right → really deploy
bin/deploy.sh staging

# Visit https://staging.alexmchong.ca, verify in browser

# Ship to prod
bin/deploy.sh prod
```

### 4.3 Smoke check after deploy

```bash
for p in / /about/ /coaching/ /_layout/style-pages.css /_ds/ /nope-404; do
  curl -sS -o /dev/null -w "HTTP %{http_code}  %{url_effective}\n" -I -L "https://alexmchong.ca$p"
done
```

The first five should return `HTTP 200`. The last should return `HTTP 404` (and serve the themed 404 page from [site/_pages/404.html](../site/_pages/404.html)).

---

## 5. Rollback

Phase 1's only state is files. Rollback is a redeploy.

### 5.1 Single-file mistake (most common)

```bash
git revert <bad-commit>      # or fix the file directly in site/_pages/
bin/deploy.sh prod
```

Done. No database state to restore.

### 5.2 `.htaccess` 500ing the whole site

Two options:

```bash
# Option A — fix locally and redeploy
git revert <bad-commit>
bin/deploy.sh prod

# Option B — emergency: rename .htaccess on the server, redeploy
ssh alexmchong-ca 'mv ~/alexmchong.ca/.htaccess ~/alexmchong.ca/.htaccess.broken'
# Site reverts to no-rewrites Apache defaults. Static files still serve.
# Then fix locally and redeploy.
bin/deploy.sh prod
```

### 5.3 Whole-site catastrophe

If everything is broken, your latest snapshot lives at `/home/alexmchong/_backups/alexmchong.ca-2026-05-13/` (the Phase 1 pre-deploy backup you made via CloudMounter). New automated backups land in Phase 13.

---

## 6. Database deploys (Phase 3)

Phase 3 added the PHP runtime: front controller, env-aware config resolver, PDO helper, minimal router, and a migration tracker. The schema lives in `site/db/migrations/*.sql`; the runner is `site/db/migrate.php`.

### 6.1 First-time DB setup (one-time, per environment)

Create the MySQL database + user in the DreamHost panel, once per environment. Note the host, db name, username, and password — those go into `config/config.<env>.php` in §6.2.

### 6.2 Per-environment config files

Three files, never committed (all in `.gitignore`):

| Where | File | Created by |
|---|---|---|
| Local dev machine | `site/config/config.local.php` | You — `cp config.example.php config.local.php`, edit credentials |
| Staging server | `~/staging.alexmchong.ca/config/config.staging.php` | SSH in, copy + edit (see §6.3) |
| Production server | `~/alexmchong.ca/config/config.production.php` | SSH in, copy + edit (see §6.3) |

The resolver `config/config.php` picks the right file by `HTTP_HOST` for web requests, by the `APP_ENV` environment variable for CLI. Missing config file → the request 500s with a clear "Missing config file: …" message.

### 6.3 Placing config on a server (one-time)

```bash
# After the first `bin/deploy.sh staging`, the config folder exists but
# config.staging.php does not — it's gitignored. Place it now:
ssh alexmchong-ca
cd ~/staging.alexmchong.ca/config
cp config.example.php config.staging.php
nano config.staging.php   # fill in real DB credentials
exit
```

Repeat for production. The same `config.example.php` ships to both — only the per-env file differs.

### 6.4 Applying migrations

The deploy script ships migration files to the server but does **not** auto-apply them — the operator runs `migrate.php` manually so a botched schema change doesn't take the site down silently:

```bash
# Apply any pending migrations on staging
ssh alexmchong-ca 'cd ~/staging.alexmchong.ca && php db/migrate.php'

# Check what's been applied vs pending
ssh alexmchong-ca 'cd ~/staging.alexmchong.ca && php db/migrate.php --status'

# Then production
ssh alexmchong-ca 'cd ~/alexmchong.ca && php db/migrate.php'
```

Behaviour (per Phase 3 Decisions):
- Tracker table: `_migrations` (one row per applied filename).
- Error policy: **roll back on first error.** The runner stops as soon as one migration fails. Successful migrations stay applied; the failing one is the first thing that needs fixing.
- MySQL DDL is non-transactional, so a partial failure mid-file leaves partial state. The runner reports this in stderr.

### 6.5 Smoke check after deploy + migrate

```bash
# Static pages + design system + 404 still work (Phase 1 smoke check)
for p in / /about/ /coaching/ /_layout/style-pages.css /_ds/ /nope-404; do
  curl -sS -o /dev/null -w "HTTP %{http_code}  %{url_effective}\n" -I -L "https://alexmchong.ca$p"
done

# Phase 3 PHP+DB smoke check
curl -sS "https://alexmchong.ca/hello"   # should print "Database connected. Current time: …"
```

Staging adds Basic Auth, so wrap the curl with `-u alex:<password>` or test in a browser.

### 6.6 Adding a new migration

1. Create `site/db/migrations/0002_<short_name>.sql`. Numeric prefix enforces order.
2. Commit and push.
3. `bin/deploy.sh staging` ships the new SQL file.
4. `ssh alexmchong-ca 'cd ~/staging.alexmchong.ca && php db/migrate.php'` applies it on staging.
5. Verify on staging in the browser.
6. Repeat for production.

The schema source of truth is `docs/CMS-STRUCTURE.md` §9 — any schema change updates the doc first, then the migration.

---

## 7. Auth bootstrap (Phase 4)

Phase 4 added authentication: the `users` table (migration `0002_users_table.sql`), the auth library (`lib/auth.php` + `lib/csrf.php`), the CMS gate at `/cms/*`, and a one-shot `setup.php` that creates the first admin password. The contract is `docs/AUTH-SECURITY.md`.

### 7.1 First-time auth bootstrap (one-time, per environment)

After the first Phase 4 deploy on a fresh database, the `users` table is empty and there's no way to log in. Bootstrap the admin user once per environment:

```bash
# 1. Apply the 0002 migration (creates the users table).
ssh alexmchong-ca 'cd ~/alexmchong.ca && php db/migrate.php'

# 2. Visit /setup.php in a browser. It generates a 16-char temp password,
#    inserts the user row (email login@alexmchong.ca), and prints the password
#    on the page in plain text. Save it.
#
#    Production: https://alexmchong.ca/setup.php
#    Staging:    https://staging.alexmchong.ca/setup.php  (behind Basic Auth)

# 3. Go to /cms/login, sign in with login@alexmchong.ca + the temp password.

# 4. Visit /cms/account and change the password (min 12 chars, must include
#    upper, lower, and a digit).
#
#    The successful password change triggers setup.php to delete itself.
#    Next time you visit /setup.php it 404s.
```

If you missed step 4 and walked away with setup.php still on the server, the next visit to /setup.php detects the completed state (password_changed_at IS NOT NULL) and self-deletes anyway.

### 7.2 What happens to setup.php on every redeploy

`setup.php` is NOT excluded from rsync — every deploy re-uploads it from source. After first install it self-deletes again on first access (because the users table already has a row with `password_changed_at` set), so the resurrection window is the time between deploy completing and first HTTP request to the file. Net effect: harmless noise.

If you ever wipe the `users` table for a clean reinstall, setup.php is already in place — visit it once to seed a new password.

### 7.3 Recovering from a real lockout

If wrong passwords lock you out for real (5 failed attempts → 15 min lockout), wait it out or unlock by hand:

```bash
ssh alexmchong-ca
mysql -u cms_prod -p alexmchong_cms_production -e \
  "UPDATE users SET locked_until = NULL, failed_attempts = 0 WHERE email = 'login@alexmchong.ca';"
```

(Substitute `cms_staging` + `alexmchong_cms_staging` for staging.)

### 7.4 The /admin/ → /cms/ 301 redirect

The legacy `/admin/` path 301-redirects to `/cms/` (and `/admin/foo` to `/cms/foo`). Configured in both `site/.htaccess` and `deploy/staging.htaccess` above the front-controller fallback. 301 not 302 because `/admin/` is permanently retired — we want search engines and browsers to forget it.

---

## 8. What still changes in later phases

Phase 13 adds:

- **Automated backups** via a cron job, replacing the manual CloudMounter snapshots.
- **`status_code` column** on the redirects table — once redirects move from `.htaccess` into the DB.

Phase 14 adds:

- **`subscribers` table** for the newsletter signup flow.

This document gets extended at each of those points. Until then, the workflow above is canonical.
