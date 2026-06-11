# Lesson 04 — The three environments

**Goal:** You can explain in one sentence what local / staging / production are, and you know exactly how a file gets from one to the next.

**What you need:**
- DreamHost panel set up (Lesson 02).
- CloudMounter installed with both production and staging mounts (Lesson 02 §8).
- About 15 minutes.

---

## 1. The one-sentence model

> **Local** is where you write code, **staging** is where you test it under real conditions, and **production** is where the world sees it.

Anything else is detail. Keep that model in your head every time you touch a file.

## 2. The table

| Environment | URL | Server path | PHP | DB | Who sees it |
|---|---|---|---|---|---|
| **Local** | `http://localhost:8000` (when running) | `~/Claude/alex-cms-buildout/` | 8.2+ | none yet (later: local MySQL or remote staging DB) | you only |
| **Staging** | `https://staging.alexmchong.ca/` | `/alexmchong.ca/_staging/` | 8.4 | `alexmchong_cms_staging` | you + anyone with the staging password |
| **Production** | `https://alexmchong.ca/` | `/alexmchong.ca/` | 8.2 | `alexmchong_cms_production` | the public internet |

The two DreamHost folders live inside the same FTP user's home, but they are *not* nested in each other — `/_staging/` is a sibling of the production webroot. This is why CloudMounter mounts them as two separate drives (Lesson 02 §8).

## 3. How a change flows

The whole loop, end-to-end, for any code change:

```
local edit
   ↓ (Git commit + push)
GitHub
   ↓ (Git pull on your other Mac, or just kept as backup)
   ↓ (manual upload via CloudMounter — drag from local folder to staging mount)
staging
   ↓ (test in browser at https://staging.alexmchong.ca/)
   ↓ (manual upload via CloudMounter — drag from local folder to production mount)
production
```

**Yes, the staging → production step is a second manual drag.** Phase 1 introduces a small deploy script that handles both uploads with one command, but the mental model is: every change crosses each line deliberately, by your hand. Nothing auto-deploys.

## 4. The hard rules

These rules exist to prevent the worst-case incidents. Memorize them.

1. **Never edit files directly in CloudMounter.** Open the file locally in VS Code, edit, commit, then drag the changed file onto the mount. If you edit in CloudMounter, your change is not in Git and you will lose it the next time you upload from local.
2. **Never edit `/_staging/`'s database with production-only data.** The staging DB is for fake/scratch records. Real published articles live only in the production DB.
3. **Never test risky changes on production first.** Risky = anything that touches the database, the `.htaccess`, or the auth code. Always upload to staging first, click around for ten minutes, then upload to production.
4. **Never commit credentials.** `.gitignore` blocks `site/config/config.staging.php` and `site/config/config.production.php`. If you ever paste a real DB password into a file that isn't in that ignore list, stop, undo the paste, and double-check before committing.
5. **The production webroot has a working site right now.** Don't drop files into it casually. The Phase 1 deploy is a deliberate, planned event, not a Monday-morning impulse.

## 5. What to test where

| If you're changing… | Test it on… | Why |
|---|---|---|
| HTML, CSS, JS (no PHP, no DB) | local in VS Code's preview or a local HTTP server | Instant feedback, no upload needed |
| PHP code | staging | Production PHP 8.2 vs staging 8.4 — staging catches version-specific issues |
| Anything touching the database | staging first, then production | The staging DB is sacrificial; production is not |
| `.htaccess` (Apache routing) | staging first | A bad `.htaccess` rule can take the whole site down |
| Content (articles, journals, etc.) | production directly in the CMS | Once Phase 6a is live, content lives in the production DB — staging is for *code*, not *content* |

That last row is the one most people get wrong. Once the CMS is running, you write articles **on production**, not staging. Staging is for testing changes to the *system*. Production is for running the *site*.

## 6. The CMS subdomain structure

Each environment has the same three sub-apps:

```
Local                Staging                              Production
─────                ────────                             ──────────
/                    https://staging.alexmchong.ca/       https://alexmchong.ca/
/cms/                https://staging.alexmchong.ca/cms/   https://alexmchong.ca/cms/
/_ds/                https://staging.alexmchong.ca/_ds/   https://alexmchong.ca/_ds/
```

Eventually `cms.alexmchong.ca` and `ds.alexmchong.ca` will resolve as proper subdomains (Phase 12), but until then the path-based routes work fine. The CMS login form is at `/cms/login` on whichever environment you're using.

## 7. A walkthrough you can do in your head

Suppose you want to fix a typo in `site/_pages/about.html`.

1. **Local:** open VS Code → edit `site/_pages/about.html` → save → Source Control panel → stage → commit "fix typo on about page" → Sync (push to GitHub).
2. **Staging:** open Finder → `alexmchong-staging` mount → navigate to `_pages/about.html` → drag your local file over → say yes to overwrite.
3. Open `https://staging.alexmchong.ca/about/` in a browser. Confirm the typo's gone. Click around the rest of the about page to confirm nothing else broke.
4. **Production:** open Finder → `alexmchong-production` mount → navigate to `_pages/about.html` → drag the same file over → confirm overwrite.
5. Open `https://alexmchong.ca/about/`. Confirm.
6. Done. The change is live.

Note the **local-vs-server path asymmetry**: in your repo the file lives at `site/_pages/about.html`, but on each server it lives at `_pages/about.html` (no `site/`). That's because the Phase 1 deploy script (`rsync -a site/ <target>:<webroot>/`) copies the *contents* of `site/` to each webroot, not the folder itself. The `site/` prefix exists only in your repo as a separator from `docs/`.

That's the entire loop for a static file change. PHP changes follow the same shape but with more deliberate testing in step 3.

## 8. Common gotchas

- **"I dragged a file to staging and it doesn't update in the browser."** Browser cache. Hard-reload with Cmd-Shift-R. If still stale, check that you actually dropped the file in the right server path — `/alexmchong.ca/_staging/_pages/` (the staging mount) vs `/alexmchong.ca/_pages/` (the production mount). Remember: server paths have no `site/` prefix — that lives only in the repo.
- **"I forgot to commit before uploading."** It worked but you now have an out-of-sync repo and server. Open VS Code, commit the local changes immediately, before you touch anything else.
- **"The staging password prompt keeps appearing."** That's correct — it's protecting the subdomain. Use the credentials you set in Lesson 02 §5.
- **"Production looks different from staging even though I uploaded the same file."** Three usual suspects: (1) PHP version difference, (2) cached `.htaccess`, (3) the file actually didn't upload (look at the timestamp on the server). Diff the local file against the mounted file to confirm.
- **"I dragged the wrong folder onto the wrong mount."** Stop. Don't overwrite further. Check what's now where, and roll back from Git if needed. CloudMounter doesn't have an undo.

## 9. Try it — verify the mounts

Open both mounts in Finder. Confirm:

1. `alexmchong-production` contains your current live site files (the existing alexmchong.ca content).
2. `alexmchong-staging` contains either nothing or a DreamHost placeholder file.
3. Open `https://staging.alexmchong.ca/` in a browser. Either a password prompt or a DreamHost default page appears (either is fine — staging is alive).
4. Open `https://alexmchong.ca/` in a browser. The current live site loads.

If all four are true, the environments are wired up and Phase 0 is essentially done.

## 10. What to ask Claude if you get stuck

- "CloudMounter says authentication failed when I try to reconnect. What's the most likely cause on DreamHost?"
- "I uploaded `index.html` to staging but `https://staging.alexmchong.ca/` still shows a 'site under construction' page. Why?"
- "I want to wipe the staging webroot and start fresh. What's the safe way to do that?"

---

**Next:** You're done with Phase 0 onboarding. Return to `BUILD-PLAN.md` and complete the Verification checklist in §4. Then start Phase 1.
