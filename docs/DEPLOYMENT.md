# Deployment — alexmchong.ca

**Status:** SSH-key-based rsync deploy via [bin/deploy.sh](../bin/deploy.sh). Replaces the manual CloudMounter workflow used during Phase 1's first ship. The deploy script was pulled forward from Phase 3 once Phase 2 (Auto-tier) was about to start, because manual CloudMounter every CSS tweak would have broken the auto-tier promise.

This document covers:

1. Environments
2. What ships
3. One-time SSH setup (skip if already done)
4. Running a deploy
5. Rollback
6. What still changes in Phase 3 and beyond

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
site/_pages/*.html       → webroot/             (landing.html renamed to index.html during deploy)
site/_pages/_layout/*    → webroot/_layout/     (CSS, fonts, images)
site/_design-system/*    → webroot/_ds/         (design system showcase)
site/.htaccess           → webroot/.htaccess    (PRODUCTION ONLY)
deploy/staging.htaccess  → webroot/.htaccess    (STAGING ONLY — adds Basic Auth gate)
```

**Never deployed:** `docs/`, `reference/`, `bin/`, `deploy/staging.htpasswd.example`, anything under `landing-postcms.html`.

**Preserved on the server, never overwritten** (the rsync exclude list in [bin/deploy.sh](../bin/deploy.sh)):

- `.dh-diag`, `.dh-diag.txt`, `.ftpquota` — DreamHost system files
- `.well-known/` — Let's Encrypt SSL renewal challenges. Touching this breaks HTTPS auto-renewal.
- `.htpasswd` — staging Basic Auth credentials. Generated on the server, never in source.
- `_archive/`, `_labs/`, `_files/` — your live experimental folders, kept as-is per Phase 1 decisions.

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

## 6. What still changes in later phases

Phase 3 (Deployment plumbing) was originally going to ship the deploy script — that landed early. Phase 3's remaining scope:

- **Database deploys.** `db/migrate.php` runs migrations on the server, hooked into `bin/deploy.sh` so a deploy that includes new migrations applies them too.
- **`config/config.php` rotation.** Per-environment config files (currently empty placeholders; the `.gitignore` already excludes `config.local.php`, `config.staging.php`, `config.production.php`).
- **PHP front controller.** `index.php` lands at the webroot; both `.htaccess` files extend with the `RewriteCond !-f / !-d / RewriteRule . /index.php [L]` block at the bottom.

Phase 13 adds:

- **Automated backups** via a cron job, replacing the manual CloudMounter snapshots.
- **`status_code` column** on the redirects table — once redirects move from `.htaccess` into the DB.

This document gets extended at each of those points. Until then, the workflow above is canonical.
