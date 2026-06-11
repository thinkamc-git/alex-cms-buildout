# AUTH-SECURITY.md — alexmchong.ca CMS authentication spec

The security spec for the single-author CMS. Drafted at the start of Phase 4 (Auth, minimal v1); referenced from any phase that touches login, sessions, CSRF, or the `users` table.

This document is the contract. Code follows it; if the code disagrees with this file, the code is wrong.

---

## 1. Scope and threat model

**Scope.** One human (Alex) authenticates to `/cms/*` from a small number of trusted devices to author content. The public site is read-only and unauthenticated.

**In v1 (Phase 4):**
- Email + password login.
- Server-side sessions (PHP `$_SESSION`, cookie-backed).
- Per-session CSRF tokens on every POST.
- Failed-login throttle + temporary lockout.
- Self-service password change (requires current password).
- One-shot `setup.php` bootstrap that seeds the user row, prints a temp password, and self-deletes after the first successful password change.

**Not in v1 (deferred):**
- Email-based password reset → Phase 17 (transactional email).
- Multi-user, roles, or invitations → never. This is a single-author CMS.
- TOTP / hardware-key second factor → not planned; revisit if v1 sees abuse.
- Remember-me distinct from session cookie → sliding 14-day session covers it.

**Threats addressed.** Credential stuffing (throttle + lockout), session theft over insecure transport (Secure + HttpOnly + SameSite=Strict + HTTPS-only), CSRF on state-changing requests (per-session token), password disclosure at rest (Argon2id hash with PHP's default cost), and accidental exposure of `setup.php` after install (self-deletes after first password change).

**Threats explicitly out of scope.** Targeted XSS in the editor (handled per-block in Phase 6a), DB compromise (covered by DreamHost backups + Phase 13 nightly dump), and physical access to Alex's machine (out of band).

---

## 2. The `users` table

Migration: `db/migrations/0002_users_table.sql`. Schema:

```sql
CREATE TABLE users (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  email                VARCHAR(255) NOT NULL UNIQUE,
  password_hash        VARCHAR(255) NOT NULL,
  last_login           TIMESTAMP NULL,
  failed_attempts      INT NOT NULL DEFAULT 0,
  locked_until         TIMESTAMP NULL,
  password_changed_at  TIMESTAMP NULL,
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Single-row table in practice — there is never more than one user. The `UNIQUE` constraint on `email` is belt-and-braces.

`password_hash` is the full `password_hash($pw, PASSWORD_DEFAULT)` output (currently 60–97 bytes; PHP guarantees ≤255 for future algorithms).

`failed_attempts` and `locked_until` drive the throttle (see §6). On a successful login, both reset to 0 / NULL.

`password_changed_at` is set by `setup.php` on first password change AND by `/cms/account` on every subsequent change. Used by `setup.php` to decide whether to self-delete (NULL → still in bootstrap mode; non-NULL → seed flow complete, delete).

---

## 3. Password storage

- Hashing: `password_hash($pw, PASSWORD_DEFAULT)` — currently Argon2id on PHP 8.2.
- Verification: `password_verify($pw, $stored_hash)`.
- Rehash check: after a successful login, call `password_needs_rehash($hash, PASSWORD_DEFAULT)` and re-hash in place if it returns true. Keeps stored hashes on the current algorithm without forcing a password reset.
- Passwords are never logged, never echoed back to the user (login error messages are deliberately generic — see §4).

---

## 4. Login flow

**Form:** `/cms/login`. POST fields: `email`, `password`, `csrf_token`.

**Steps (in order):**

1. Verify the CSRF token (per §5). On failure, render the form with a generic error and a fresh token.
2. Reject empty email or password with a generic error ("Invalid email or password.").
3. Look up the user by email. If no row, run `password_verify()` against a dummy hash anyway to keep timing consistent (avoid leaking which emails exist), then return the generic error.
4. Check `locked_until`. If set and in the future, return "Account temporarily locked. Try again in N minutes." Do NOT increment `failed_attempts`.
5. Run `password_verify($posted, $row['password_hash'])`. On failure:
   - `failed_attempts = failed_attempts + 1`.
   - If `failed_attempts >= 5`, set `locked_until = NOW() + INTERVAL 15 MINUTE` and reset `failed_attempts = 0`.
   - Return the generic error.
6. On success:
   - `failed_attempts = 0`, `locked_until = NULL`, `last_login = NOW()`.
   - Re-hash if needed (per §3).
   - Regenerate session ID: `session_regenerate_id(true)` — defeats session fixation.
   - Rotate the CSRF token (per §5).
   - Set `$_SESSION['uid']` to the user id.
   - 302 to `/cms/` (or to a `?next=` path if present and same-origin).

**Error messages are deliberately generic.** Wrong email and wrong password produce the same string ("Invalid email or password."). This avoids telling an attacker which half they got right.

**Generic-error policy applies only to the login form.** Lockout messages name the wait time because the throttle is itself the defense; hiding the wait time helps no one.

---

## 5. Sessions and CSRF

### Session cookie

```php
session_set_cookie_params([
  'lifetime' => 0,            // session cookie; sliding refresh handled per-request
  'path'     => '/',
  'domain'   => '',           // host-only
  'secure'   => true,         // HTTPS only — both envs serve over TLS
  'httponly' => true,         // no JS access
  'samesite' => 'Strict',     // no cross-site CSRF surface even without tokens
]);
session_name('amc_sid');
session_start();
```

`session.use_strict_mode = 1` is set via `ini_set` at the top of `lib/auth.php` so unknown session IDs from clients are discarded rather than adopted.

### Session lifetime: sliding 14 days

A `$_SESSION['last_seen']` timestamp is checked on every request that touches `lib/auth.php`. If `now - last_seen > 14 days`, the session is destroyed and the user is bounced to `/cms/login?next=…`. On every authenticated request, `last_seen` is updated to `now`. Result: 14 days of inactivity logs out; continuous use renews indefinitely.

### CSRF tokens

- One token per session, generated lazily via `random_bytes(32)` and base64-encoded.
- Stored at `$_SESSION['csrf']`.
- Regenerated on login (per §4 step 6). Constant for the lifetime of a session otherwise — simpler than per-form rotation and adequate for a single-author CMS.
- Verification uses `hash_equals()` for constant-time comparison.
- Every POST handler in `/cms/*` must call `Csrf::verify($_POST['csrf_token'] ?? '')` before doing anything else. A mismatch returns a 400 with a one-line error.
- Forms render the token via `<input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">`.

---

## 6. Login throttle (adaptive, per-IP) — Phase 24

The original per-account lockout (5 strikes → 15-min lock on the `users` row) was **replaced in Phase 24**. A per-account lock can be weaponised: an attacker who floods wrong passwords against the single user's email locks out the *legitimate* user (a denial of service). The throttle now keys off the client IP, so the attacker can only ever lock *themselves* out.

Implementation: `lib/login_throttle.php` + the `login_attempts` table (migration `0028`). Client IP is `REMOTE_ADDR` (the real TCP peer on DreamHost — not a spoofable `X-Forwarded-For`).

- **Decaying budget by IP.** Within a rolling 15-minute window, each *distinct failing IP* gets a smaller budget than the last, by order of appearance: **1st → 10, 2nd → 5, 3rd → 3, 4th+ → 1.** This is a self-recovery runway for the author (10→5→3→1 across IP switches) and a hard clamp on an attacker rotating IPs.
- **Correct credentials are never throttled.** The budget governs *failures* only — a correct password or valid recovery code always gets in (the check runs before any password work; a clean IP always reaches the verify step).
- **Trusted IPs keep the full budget.** Any IP that has successfully logged in within 90 days always gets the full 10, so the author's known locations have their full runway even mid-attack.
- **Auto-decay.** The window is rolling; 15 minutes of quiet resets an IP to a fresh 10. No manual unlock needed — but a real worst case is covered by recovery codes / SSH reset (§13).
- **Messaging.** A throttled attempt shows how long sign-in from that location is paused and points to recovery (recovery code / SSH). When ≥2 IPs are failing and the requester isn't trusted, an "unusual sign-in activity" note is shown. Password/email correctness stays generic (§4).
- **Audit log.** `login_attempts` records every attempt (IP, outcome, email, timestamp), satisfying the §12 audit-log open item. Rows older than 90 days are pruned automatically on successful login.
- **Manual reset.** `DELETE FROM login_attempts WHERE outcome='fail'` clears the throttle (also exposed as the staging "Clear login throttle" button). Documented in `DEPLOYMENT.md` §7.3.

The `users.failed_attempts` / `locked_until` columns are retained but no longer drive any lockout.

---

## 7. Logout

- Endpoint: `/cms/logout` (POST only, CSRF-protected).
- Action: `$_SESSION = []; session_destroy(); setcookie(session_name(), '', time()-3600, '/');`
- Redirect: `/cms/login`.
- A GET to `/cms/logout` 405s — prevents drive-by logout via image tag or third-party link.

Logout button lives in the CMS topbar partial (top-right), wired in Phase 5 when the topbar partial is built. Phase 4 ships the endpoint and a minimal form; the topbar gets its final placement in Phase 5.

### Sign out other sessions — Phase 24

For the "I left myself logged in somewhere" case. Each user row has a `session_epoch` (migration `0029`) that is stamped into the session at login (`$_SESSION['epoch']`); `Auth::require_login()` re-checks it on every CMS request. **Settings → Account → "Sign out other sessions"** calls `Auth::logout_other_sessions()`, which increments `session_epoch` — invalidating *every* existing session on its next request — then re-stamps the **current** session with the new epoch so it survives. Net effect: all *other* devices are signed out, the device you clicked from stays in (it's the trusted one). Pre-existing sessions with no stamp adopt the current epoch (so a deploy doesn't spuriously log everyone out). `db/reset-password.php` also bumps the epoch (signing out everything, including current, since a reset implies a possible compromise).

---

## 8. Password change

**Form:** `/cms/account`. POST fields: `current_password`, `new_password`, `new_password_confirm`, `csrf_token`.

**Rules:**
1. CSRF verify first.
2. Verify `current_password` against the stored hash. On failure, generic error ("Current password is incorrect.").
3. `new_password` must be ≥12 characters, contain at least one uppercase, at least one lowercase, and at least one digit. No special-char requirement (length is the win; complexity rules drive users to predictable patterns).
4. `new_password === new_password_confirm`.
5. On success: `password_hash(new, PASSWORD_DEFAULT)`, write to `users`, set `password_changed_at = NOW()`, regenerate the session ID, render a success message inline (no redirect; keeps the form visible for verification).

The same complexity rules apply to the temp password generated by `setup.php` (§10).

---

## 9. Route protection

`/cms/*` is protected at the PHP layer, not via Apache `.htaccess` Basic Auth:

- The front controller `index.php` registers `/cms/*` routes. Each `/cms/*` handler calls `Auth::require_login()` first.
- `Auth::require_login()` checks `$_SESSION['uid']`. If missing or session is expired (per §5), it 302s to `/cms/login?next=<current_path>`.
- `/cms/login` itself is the only `/cms/*` route that does NOT call `require_login()`. `/cms/logout` requires a session (no point logging out if not logged in).
- `cms/.htaccess` was originally drafted as a belt-and-braces deny, but was removed during Phase 4 verification: a directory-level `Require all denied` fires at Apache's access-check phase BEFORE the webroot front-controller rewrite runs, which blocked legitimate `/cms/login` URLs. The PHP-level `Auth::require_login()` in every handler is the real gate, and every `cms/*.php` file internally gates itself (login is the only one that doesn't require a session, and it's the gate itself).

### `/admin/` → `/cms/` 301

The legacy admin path. Handled at the Apache level (added to `site/.htaccess` and `deploy/staging.htaccess`):

```
RewriteRule ^admin/?$ /cms/ [R=301,L]
RewriteRule ^admin/(.*)$ /cms/$1 [R=301,L]
```

301 (not 302) because `/admin/` will never come back; we want browsers and search engines to forget it.

---

## 10. `setup.php` — one-shot bootstrap

**Purpose:** the very first time the CMS is deployed to a fresh DB, there are no users. `setup.php` creates the single user row.

**Behavior on GET (no params):**
1. Check the database. If `users` already has at least one row with `password_changed_at IS NOT NULL`, render: "Setup already complete. This file should have been deleted — delete it manually." Do NOT do anything else.
2. If no user row exists, generate a temp password (16 chars, mixed case + digits, cryptographically random via `random_bytes`), hash it, INSERT a row with `email = 'login@alexmchong.ca'`, `password_hash = $hash`, `password_changed_at = NULL`.
3. Render the temp password in plain HTML with instructions: "Log in at /cms/login with the temp password, change it on the account page, then this file will self-delete on your next visit."
4. If a user row exists but `password_changed_at IS NULL` (temp password phase), do nothing — print "Setup in progress. Log in and change your password to complete setup."

**Self-deletion:**
After `/cms/account` successfully writes a new password and sets `password_changed_at`, on the NEXT request to `setup.php` (or as a fire-and-forget side-effect of `/cms/account` itself — see implementation note below), `setup.php` calls `unlink(__FILE__)` and returns 404.

Implementation note: the most reliable trigger is for `/cms/account` to attempt `unlink(__DIR__ . '/../setup.php')` itself after a successful password change. The `setup.php`-side check on next-visit is a fallback. Both should exist so a partial flow still cleans up.

**Why one-shot and not a CLI command?** DreamHost shared hosting has no convenient CLI for first-time install; Alex provisions the DB and then visits a URL. Browser-driven bootstrap is the cleanest path.

**Why self-delete instead of leaving it in place?** A leftover `setup.php` is a permanent footgun — if anyone ever discovers it and the `users` table is somehow truncated, they could seed a user. Self-delete closes the window.

---

## 11. Files (Phase 4)

- `docs/AUTH-SECURITY.md` ← this file.
- `site/db/migrations/0002_users_table.sql` — the `users` table.
- `site/lib/auth.php` — `Auth::start_session()`, `Auth::login()`, `Auth::logout()`, `Auth::require_login()`, `Auth::current_user()`, `Auth::change_password()`.
- `site/lib/csrf.php` — `Csrf::token()`, `Csrf::verify()`.
- `site/cms/login.php` — login form + POST handler.
- `site/cms/logout.php` — POST handler.
- `site/cms/account.php` — change-password form + POST handler.
- `site/cms/index.php` — placeholder dashboard (Phase 5 replaces with the real chrome).
- `site/setup.php` — one-shot bootstrap.
- `site/index.php` — wires `/cms/*` routes + `/admin/` → `/cms/` redirect (Apache handles `/admin/` actually; index.php only sees what falls through).
- `site/.htaccess` + `deploy/staging.htaccess` — add the `/admin/` → `/cms/` redirect block.
- `bin/deploy.sh` — extended to ship the new tree.

---

## 12. Open items (revisit in Phase 12+ or later)

- **Audit log.** ~~Right now there's no log of login attempts.~~ **Done in Phase 24** — `login_attempts` (§6) records every attempt with IP, outcome, email, and timestamp.
- **Session storage.** PHP's default file-based sessions are fine on shared hosting. If a second device is ever added, revisit.
- **Per-request CSRF rotation.** Currently per-session; if a stored-XSS surface is ever found in the editor, tighten to per-form.

---

## 13. Password recovery (single-user) — Phase 24

There is **no web-facing "forgot password" form.** For a one-user site where the user owns the server, a public reset endpoint is a net loss — it's a permanent account-takeover surface and binds the site's security to the email account. Recovery is kept server-side and offline instead. Three paths, fastest first:

1. **Recovery codes (no SSH).** `lib/recovery_codes.php` + the `recovery_codes` table (migration `0030`). The author generates a set of 8 one-time codes in **Settings → Account** (`xxxx-xxxx`, shown once, stored only as Argon2id hashes; regenerating invalidates the old set). At `/cms/login`, "Lost access? Use a recovery code" swaps the password field for a code field. A successful code login consumes that code, establishes the session, and sets `$_SESSION['must_change_pw']` — `require_login()` then confines the session to the account surface until a new password is set. Recovery-code login goes through the same throttle (§6).

2. **SSH reset (primary).** `db/reset-password.php` — CLI-only (refuses HTTP, like `migrate.php`). Generates a strong temp password (or takes `--password=`), writes the new hash + `password_changed_at`, clears the throttle counters on the row, and bumps `session_epoch` (signs out all sessions). This is the "ask Claude to reset me" path, since the assistant has SSH.

3. **Raw SQL.** Last-resort `UPDATE users SET password_hash=…`. Documented in `DEPLOYMENT.md` §7.3.

The `db/` directory is denied to the web (`db/.htaccess`), so neither the reset script nor the `.sql` files are reachable over HTTP.
