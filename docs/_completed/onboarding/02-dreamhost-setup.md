# Lesson 02 — DreamHost panel walkthrough

**Goal:** By the end of this lesson your DreamHost account is fully configured for this project — staging subdomain exists, both MySQL databases exist, PHP versions are confirmed, and you know your FTP credentials.

**What you need:**
- Your DreamHost account login (https://panel.dreamhost.com).
- About 30 minutes.

---

## 1. What we're doing and why

A DreamHost shared-hosting account gives you a Linux server with files served from folders, plus some control-panel buttons for things like "create a subdomain" and "create a MySQL database". We're going to use those buttons to set up exactly the environment our CMS needs.

By the end of this lesson, the server will have:

- **One main domain**, `alexmchong.ca`, serving from `/alexmchong.ca/`. Already configured (your existing site).
- **One subdomain**, `staging.alexmchong.ca`, serving from `/alexmchong.ca/_staging/`. We'll set this up if it isn't already.
- **Two MySQL databases:** one for production, one for staging.
- **One FTP user** with access to both folders. You may already have this; we'll confirm.
- **Confirmed PHP versions:** 8.2 on production, 8.4 on staging.

We won't touch the production site's existing files. Everything we add is additive.

## 2. Quick map of the DreamHost panel

Log into https://panel.dreamhost.com. The left sidebar has a lot of items. The ones we care about:

| Sidebar item | What it does |
|---|---|
| **Websites → Manage Websites** | Where domains, subdomains, and PHP versions are configured. |
| **Websites → Manage Domains** *(older panel name — same thing)* | Older label. Some accounts still see this. |
| **Websites → Files (FTP/SSH)** | Where FTP users live. |
| **Websites → MySQL Databases** | Where databases and DB users live. |

Everything in this lesson happens in those four screens.

## 3. Confirm PHP versions

1. Sidebar → **Websites → Manage Websites**.
2. Find the row for `alexmchong.ca`. Click **Manage** (or the gear icon).
3. Scroll to the **PHP version** section.
4. Confirm it says **PHP 8.2** (or higher). If not, change it and save.
5. Go back to the website list. Find the row for `staging.alexmchong.ca` (or create it — next section). Confirm it's set to **PHP 8.4**.

> **Why two different PHP versions?** Staging deliberately runs ahead of production so we can catch any PHP-version-specific issues before they hit your live site. Code is written to work on both — see `ENGINEERING.md` §4.1.

## 4. Create the staging subdomain (if it doesn't already exist)

You mentioned the staging subdomain is `staging.alexmchong.ca` served from `/alexmchong.ca/_staging/`. Check whether it's already set up:

1. Sidebar → **Websites → Manage Websites**.
2. Look for a row labeled `staging.alexmchong.ca`. If it's there, skip to §5.

**If it's not there, create it:**

1. Click **Add A Website** (button near the top).
2. Pick **Add Subdomain** (some panels phrase it as "Add a new website to existing domain").
3. Subdomain: `staging`
4. Parent domain: `alexmchong.ca`
5. **Web directory:** this is where you tell DreamHost which folder serves the subdomain. Set it to `/home/<your-user>/alexmchong.ca/_staging/`. Replace `<your-user>` with your actual DreamHost shell username (visible at the top of the panel).
   - The folder doesn't have to exist yet — DreamHost will create it.
6. **HTTPS:** turn on. Use Let's Encrypt (free).
7. **PHP version:** 8.4 (latest available).
8. Click **Add Website**.

DreamHost takes a few minutes to provision the SSL certificate. You'll get an email when it's ready.

## 5. Password-protect the staging site

Staging shouldn't be public. We'll add a username/password gate.

> Note: we'll do the actual password gate during Phase 3 with a `.htaccess` + `.htpasswd` file dropped into `/_staging/`. For now, just be aware. If you want it locked down immediately even before the CMS exists, do this:

1. Sidebar → **Websites → Manage Websites**.
2. `staging.alexmchong.ca` → **Manage**.
3. Find **Password Protection** (sometimes under "Advanced"). Click **Manage**.
4. Add a username (e.g. `alex`) and a strong password.
5. Save.

Visiting `https://staging.alexmchong.ca/` should now prompt for that login.

## 6. Create the two MySQL databases

This is the most important part of this lesson. We'll create two completely separate databases — one for staging, one for production. They never share data.

1. Sidebar → **Websites → MySQL Databases**.
2. Scroll to **Create a New MySQL Database**.

### 6.1 First database — staging

Fill in:

- **Database name:** `alexmchong_cms_staging`
- **Use Hostname:** create a new one. Name it `mysql.alexmchong.ca` if it doesn't exist yet (DreamHost likes one MySQL hostname per top-level domain). It might be auto-suggested.
- **First User:** create a new user.
  - **Username:** `cms_staging`
  - **Password:** click **Generate password**. **Copy this password somewhere safe immediately — DreamHost won't show it again.** Save it to your password manager with a label like "MySQL — staging".
  - **Allowable hosts:** leave the default (`%`).

Click **Add new database now**.

DreamHost says "this can take up to 5 minutes". Wait. Refresh the database list to confirm it appears.

### 6.2 Second database — production

Repeat:

- **Database name:** `alexmchong_cms_production`
- **Use Hostname:** same `mysql.alexmchong.ca` you just created (or whichever DreamHost suggests).
- **First User:** new user.
  - **Username:** `cms_prod`
  - **Password:** generate, **copy and save** as "MySQL — production".
- Click **Add new database now**.

Wait for it.

### 6.3 What you now have

Two databases:

| Database | Host | User | Password | Where |
|---|---|---|---|---|
| `alexmchong_cms_staging` | `mysql.alexmchong.ca` | `cms_staging` | (saved in your password manager) | staging only |
| `alexmchong_cms_production` | `mysql.alexmchong.ca` | `cms_prod` | (saved in your password manager) | production only |

You won't put these into any file yet — that's Phase 3. But save them. We'll paste them into `config/config.staging.php` and `config/config.production.php` when we get there.

## 7. Confirm your FTP user

1. Sidebar → **Websites → Files (FTP/SSH)**.
2. You should see at least one user listed. The username probably starts with letters and has access to `/home/<that-username>/`.
3. Note the **username**, the **server name** (something like `iad1-shared-bN-NN.dreamhost.com`), and confirm you have the **password** saved somewhere.
4. If you don't have the password, click **Edit User** → **Change Password**. Generate a strong one. Save it.

This is the username/password CloudMounter uses to mount the server as a drive.

## 8. Mount the folders with CloudMounter

You already have CloudMounter (you mentioned this). For each environment, create a mount:

### 8.1 Production mount

- Protocol: **SFTP** (port 22) if SSH is enabled on your account, otherwise **FTP** (port 21).
- Server: the server name from step 7.
- Username + password: the FTP user from step 7.
- Remote path: `/home/<your-user>/alexmchong.ca/`
- Label this mount: `alexmchong-production`.

### 8.2 Staging mount

- Same protocol / server / credentials.
- Remote path: `/home/<your-user>/alexmchong.ca/_staging/`
- Label: `alexmchong-staging`.

Now when you open Finder, you'll see two mounted drives. Drag files into them like any folder.

> **Why two separate mounts instead of one mount at `/home/<your-user>/` and navigating?** Mental hygiene. Two drives = two distinct mental targets. Less chance of dragging a file into the wrong environment by accident.

## 9. Try it — three small verifications

Do these now to confirm everything's wired up.

### 9.1 Confirm staging serves

In a browser, go to `https://staging.alexmchong.ca/`. You should see either (a) a "this site is protected" password prompt if you enabled §5, or (b) a default placeholder page from DreamHost saying the site is set up but no `index.html` exists. Either result means the subdomain is alive.

### 9.2 Confirm both databases exist

In the DreamHost panel, sidebar → **MySQL Databases**. You should see both `alexmchong_cms_staging` and `alexmchong_cms_production` listed.

### 9.3 Confirm CloudMounter mounts

Open Finder. Both `alexmchong-production` and `alexmchong-staging` should appear in the sidebar under Locations. Click each. You should see the folder contents (the production one will have your existing site files; the staging one will be empty or near-empty).

If all three work, this lesson is done.

## 10. Common gotchas

- **"DreamHost says the subdomain is provisioning, but the SSL certificate is taking forever."** Let's Encrypt provisioning can take up to an hour after the subdomain is created. Wait it out. The site will technically be available over HTTP first.
- **"I can't remember which MySQL password is which."** Generate new ones. DreamHost lets you regenerate a user's password without breaking anything — just update your `config.*.php` next time it's referenced.
- **"CloudMounter says authentication failed."** Most common reason: the FTP user's home directory doesn't include `alexmchong.ca/`. In **Files (FTP/SSH)**, edit the user and confirm its "Directories which this user can access" includes `alexmchong.ca/`.
- **"I see a `.htaccess` already in the production folder I shouldn't touch."** Correct, don't touch it for now. The existing site has its own routing. In Phase 3 we'll figure out how to merge or replace.
- **"My panel doesn't look like this walkthrough."** DreamHost changes the panel labels every couple years. The buttons might be one menu deeper. Search the panel for the keywords (e.g. "MySQL") if a menu name has moved.

## 11. What to ask Claude if you get stuck

- "DreamHost is showing me \[paste the error or screen]. What is it asking?"
- "I clicked the wrong button and \[describe]. Is anything broken?"
- "I see a file called `[name]` in the production folder. What is it and should I touch it?"

## 12. What you don't need to do yet

You will *not*, in this lesson:

- Upload any PHP files.
- Run any SQL.
- Configure cron jobs.
- Set up email forwarding.

All of those come in later phases. Phase 0 ends with the panel set up and the credentials saved, period.

---

**Next:** `03-vscode-claude-code.md`.
