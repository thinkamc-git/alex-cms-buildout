PURPOSE: Journal category redesign, then full uncommitted-work consolidation, then the session-hygiene system itself
STATUS: active
LAST TOUCHED: 2026-07-12

## Objective: Journals category redesign (started 2026-07-12)

**Intent:** Alex wanted to rework the Journals category set (was
Inquiry/Observation/Principle) to actually match how he writes, not the
original spec. Explicitly wanted a real back-and-forth design conversation,
not a form/menu of options — pushed back hard when I tried to shortcut it
with a multiple-choice question early on.

### Timeline

- Long design conversation (not logged turn-by-turn — this file didn't exist
  yet). Landed on 4 categories split by domain on the practice side
  (UX Practice / Coaching Notes) and by theme on the reflection side
  (On Technology / About Life) — chosen because it matched Alex's actual
  writing (no grounded personal field-notes, no wandering professional
  contemplations existed in practice), not because it filled a symmetric grid.

- Attempting: apply new categories + reassign real content on staging
  (randomized, dummy content) via `migrate_journal_categories.php` over SSH
  → SUCCEEDED — no exact timestamp recorded (pre-dates this file). 4
  categories added, old ones retired once usage hit 0.

- Attempting: same migration on production, hand-mapped per real content
  → SUCCEEDED — no exact timestamp recorded. 5 real entries reassigned
  (#12→Coaching Notes, #13/#14→About Life, #15/#16→On Technology),
  `journal_number` recomputed per category on both environments.

- Attempting: diagnose Alex's report that category colours "look broken" at
  alexmchong.ca/field-notes/
  → Root cause found: `--c-current` (public card accent colour) was set via
  a static CSS list keyed to old category slugs (`views.css:9-19`), never
  actually read from the database — so renaming/recoloring a category
  silently did nothing on the public site.

- Attempting: fix `site/templates/partials/index-card.php` to set
  `--c-current` inline from the category's live DB colour on every card type
  → SUCCEEDED locally, then:
- Attempting: push the fix directly to staging + prod via scp (no full
  `bin/deploy.sh` run, since the working tree had many unrelated uncommitted
  changes that shouldn't ship yet)
  → BLOCKED — sandbox classifier denied the direct prod scp, citing the
  deploy-safeguard rule from a past incident (`incident_deploy_safeguards`
  memory).
- Attempting: same push, after Alex explicitly approved it in-conversation
  → SUCCEEDED — verified live via curl, `--c-current` now reflects real DB
  colours on all journal cards.

### Outstanding (as of 2026-07-12)
- none — fully resolved. (Articles/Experiments category plan — Case Study,
  Newsletter as a dedicated category — was discussed and decided but never
  implemented; carried forward as its own unresolved item below rather than
  under this objective, since it wasn't this objective's scope.)
- Articles: **Case Study** category (new) and **Newsletter** as a dedicated
  category (not just a Series) — decided in conversation, not yet built.

## Objective: Uncommitted-work consolidation (started 2026-07-12)

**Intent:** Alex realized several other agent sessions had been working on
this repo in parallel and had left ~50 files of uncommitted work sitting in
the working tree, plus (as it turned out) years — well, weeks — of direct
server pushes with no git record at all. Goal: figure out what's actually
live where, without shipping anything half-finished, then reconcile git.

### Timeline

- Attempting: classify every modified/untracked file as "already live" vs
  "genuinely in-progress" by diffing against both servers directly (not
  trusting git status or memory)
  → Initial pass had a shell-quoting bug (process substitution + `echo`
    mangled some file content) producing false positives.
  → SUCCEEDED on retry with a file-based diff method. Found: everything was
    already live on both servers **except** the Kicker/eyebrow block feature
    (5 code files) and its content (thinking-system experiment, id=43,
    staging only).

- Attempting: verify `bin/deploy.sh`, `deploy/staging.htaccess`, `cv.php`,
  `_bodies/cv.html` specifically (path-mapping was ambiguous for `_pages/`
  files — they deploy flat to webroot root, not into a subfolder)
  → First check on `cv.php` was WRONG (used the wrong remote path, reported
    "not deployed"). Re-verified correctly →  SUCCEEDED, actually already
    live on both servers.

- Attempting: check for redirect conflicts between `/cv` (static Pages-editor
  page, per Alex) and `/resume/` (CMS résumé builder) per Alex's question
  → SUCCEEDED — no conflicting redirects found. But surfaced two other real
    issues: (1) a stale `/portfolioforhire → Webflow` redirect still on prod
    (dead now that the static page serves directly — Alex to remove), and
    (2) `/cv` isn't actually wired into the new Pages Management System
    (`page_registry` table) at all — it's a static assembled page, same
    mechanism as `about.php`. Alex confirmed this is expected for now,
    proper Pages-system migration deferred to later.

- Attempting: review the 5 stray files found on staging's webroot root
  (`content.php`, `render.php`, `resume-edit.php`, `sidebar.php`,
  `test-page.php`)
  → SUCCEEDED — all 5 confirmed dead (orphaned copies or abandoned older
    drafts, none referenced by live routing). Alex said hold off deleting
    until he confirms — **not yet deleted.**

- **`96c0441`** — Content-type rename (article→essay/journal→field-note/
  experiment→field-work). Verified matching both live servers before
  committing. → SUCCEEDED, pushed.
- **`c780c77`** — Library → Résumés (CMS editor + public `/resume/`).
  → SUCCEEDED, pushed. ⚠️ Swept in some test-artifact PDFs under
  `docs/_cv/pdf-exports/` (`test2-...`, `print-template-...`, a few
  timestamp-hash duplicates) that shouldn't have been committed — flagged to
  Alex, not yet cleaned up.
- **`9da180e`** — `cv.php` marketing page. → SUCCEEDED, pushed.
- **`41d604c`** — Portfolio-for-hire (self-hosted, replaces Webflow
  redirect). Verified full 94/94 file-list match against prod first.
  → SUCCEEDED, pushed.
- **`21eb4cb`** — CMS admin polish (table `thclass`, drag-reorder tweaks,
  tiptap.css addition). → SUCCEEDED, pushed.

- Attempting: correct the `project_rename_content_types` memory, which
  claimed prod had never been touched by the rename
  → SUCCEEDED — corrected in place with a note that git status ≠ live state
  for this project. (Memory edit, not a commit — lives outside the repo.)

### Outstanding (as of 2026-07-12)
- Kicker/eyebrow block feature — complete, proven on staging only
  (`blocks.css`, `tiptap-setup.js`, `article-edit.php`, `experiment-edit.php`,
  `sanitize.php`, `docs/BLOCKS.md`), never reached prod, still uncommitted.
- thinking-system experiment content (content.id=43, staging only) — DB row
  + `content/experiment/thinking-system/main.html` (server-only, excluded
  from git/deploy by design). Needs: deploy Kicker code to prod first, copy
  `main.html` to the prod server manually, publish the content row via CMS.
  Also open: should `labs.alexmchong.ca/thinking-system` (the original
  source page, still live) get redirected/retired once this ships, or stay
  as a separate working sandbox? Not decided.
- 5 stray files on staging webroot root — confirmed dead, Alex to
  confirm/delete.
- Stale `/portfolioforhire → Webflow` redirect on prod — Alex to delete via
  `/cms/redirects`.
- Test-artifact PDFs in `docs/_cv/pdf-exports/` (see `c780c77` above) —
  flagged, not yet cleaned up.
- CV page → real Pages Management System migration — explicitly deferred by
  Alex to a later task.
- `publish_resume()` snapshot bug — its version-history snapshot logic never
  captures the outgoing `published_html` before overwriting it (both
  snapshot inserts save the current draft instead). Confirmed still unfixed,
  not started — backlog item, from another session's handoff, not this one.

## Objective: Session-hygiene system (started 2026-07-12)

**Intent:** Alex realized he has multiple agents open concurrently and it's
causing confusion — wants a system so it's obvious what each agent is doing,
so docs don't silently go stale, and so work is never stranded unbacked-up
locally. Co-designed through several rounds of Alex pushing back on my first
drafts (worried about concurrent-write collisions on a shared log; wanted
attempt→outcome logged, not just outcomes; wanted objectives/intent
captured, not just file diffs).

### Timeline

- Attempting: write `docs/SESSION-HYGIENE.md` + `docs/_agent-logs/` +
  CLAUDE.md doc-map/folder-map updates, first version (single shared-feeling
  index concept, prose-summary log body)
  → SUCCEEDED as a first draft, but Alex flagged two real gaps: no
    timestamps/commit-references, and it was a summary written after the
    fact rather than a real log.
- **`785ddd5`** — first version of the system, committed + pushed.
  → SUCCEEDED, pushed immediately.
- Attempting: fix timestamp/commit-reference gap
  → SUCCEEDED.
- **`7d155cb`** — timestamped-log fix, committed + pushed.
  → SUCCEEDED, pushed immediately.
- Attempting: incorporate Alex's refined model (attempt→outcome including
  failure; objectives+intent structure; push back on a shared
  outstanding-tasks file in favor of compute-on-demand; confirm chat=agent=log)
  → SUCCEEDED — this rewrite.

### Outstanding (as of 2026-07-12)
- This log itself needs to be committed + pushed (next step).
- Not yet tested in practice across a real multi-agent-concurrent scenario —
  the design is reasoned through, not battle-tested. Worth revisiting after
  Alex has actually used it for a few sessions.
