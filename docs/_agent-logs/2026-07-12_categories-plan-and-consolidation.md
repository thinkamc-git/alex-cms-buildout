PURPOSE: Journal category redesign (planning + migration) + full uncommitted-work consolidation
STATUS: active
LAST TOUCHED: 2026-07-12

## Goal

Two threads that merged into one session:
1. Redesign the Journals category set with Alex (was Inquiry/Observation/Principle).
2. Reconcile ~50 files of uncommitted work across the repo — several past
   sessions had pushed directly to staging/prod without ever committing,
   and a project memory incorrectly claimed prod was untouched.

## Timeline

**Note on precision:** the entries below covering work *before* the first
commit were reconstructed from conversation, not logged in real time as they
happened — this file itself didn't exist yet. Times are approximate/omitted
where I don't have a real clock reference for them. Everything from the first
commit onward has an exact, verifiable timestamp via `git log`. This gap is
itself the lesson: going forward, this file should be started and appended to
*from the first action*, not written after the fact.

- **Journals category migration** (approx. mid-session, before any commit) —
  ran `migrate_journal_categories.php` via SSH on staging then production
  (scp'd, deleted after use). Added 4 new categories (UX Practice, Coaching
  Notes, On Technology, About Life), reassigned 5 real prod entries by hand,
  randomized staging (dummy content), recomputed `journal_number` per
  category, deleted the old categories once usage hit 0. No git commit —
  pure data, no code changed. No exact timestamp recorded.

- **Category-colour rendering bug found + fixed** (approx. mid-session,
  before any commit) — root cause: `--c-current` (the public card's accent
  colour) was set by a static CSS list keyed to old category slugs
  (`views.css:9-19`), never actually read from the database, so any
  category rename/recolor silently did nothing on the public site. Fixed in
  `site/templates/partials/index-card.php` — now sets `--c-current` inline
  from the category's live DB colour on every card type. Deployed directly
  to staging + prod via scp (approved by Alex in-conversation). No exact
  timestamp recorded; this predates the first commit below.

- **`96c0441`** (2026-07-12 20:10) — Content-type rename
  (article→essay/journal→field-note/experiment→field-work). Verified
  byte-for-byte against both live servers before committing — the rename
  had been live on both for a while, this only caught git up to reality.
  Pushed to `origin/main` in the same batch as the commits below.

- **`c780c77`** (2026-07-12 20:11) — Library → Résumés: CMS résumé editor +
  public `/resume/`. Same verify-then-commit approach.
  ⚠️ Swept in some test-artifact PDFs under `docs/_cv/pdf-exports/`
  (`test2-...`, `print-template-...`, a few timestamp-hash duplicates) that
  shouldn't have been committed — flagged to Alex, not yet cleaned up.

- **`9da180e`** (2026-07-12 20:12) — `cv.php` marketing page. Also verified
  live on both servers first (initially mis-checked as undeployed due to a
  path-mapping mistake on my end; re-verified correctly before committing).

- **`41d604c`** (2026-07-12 20:12) — Portfolio-for-hire: self-hosted static
  portfolio, replaces the old Webflow redirect. Verified full file-list
  match against prod (94/94 files) before committing.

- **`21eb4cb`** (2026-07-12 20:13) — CMS admin polish (table `thclass`
  support, drag-reorder tweaks, a tiptap.css addition). Small, unrelated
  to the other four, grouped because git status showed them together.

- **`785ddd5`** (2026-07-12 20:26) — Session hygiene system itself:
  `docs/SESSION-HYGIENE.md`, this `_agent-logs/` folder, CLAUDE.md doc-map +
  push-rule updates. Pushed immediately.

- All 6 commits above were pushed to `origin/main` promptly after committing
  (confirmed: `git log` shows local `main` and `origin/main` in sync as of
  `785ddd5`).

- **Stale memory corrected** — `project_rename_content_types.md` said prod
  had never been touched; false, migrations 0038–0041 are applied on both
  environments and have been for a while. Corrected in place with a note
  that git status ≠ live state for this project. (Memory edit, not a commit
  — memory lives outside the repo.)

## NOT done yet — handoff for whoever picks this up

1. **Kicker/eyebrow block feature** — complete, proven on staging only
   (`blocks.css`, `tiptap-setup.js`, `article-edit.php`, `experiment-edit.php`,
   `sanitize.php`, `docs/BLOCKS.md`), never reached prod, still uncommitted.
2. **thinking-system experiment content** (content.id=43, staging only) — DB
   row + `content/experiment/thinking-system/main.html` (server-only file,
   excluded from git/deploy by design). Needs manual promotion to prod: copy
   `main.html`, publish the content row via CMS, deploy the Kicker code first.
3. **5 stray files on staging webroot root** — confirmed dead (old draft
   `resume-edit.php`/`sidebar.php`, orphaned `content.php`/`render.php`
   copies, a `test-page.php` stub) — identified, not yet deleted, Alex to
   confirm/handle.
4. **Stale `/portfolioforhire → Webflow` redirect on prod** — dead now that
   the static page serves directly, Alex to delete via `/cms/redirects`.
5. **Test-artifact PDFs** in `docs/_cv/pdf-exports/` (see `c780c77` above) —
   flagged to Alex, not yet cleaned up.
6. **CV page → real Pages Management System migration** — currently a static
   assembled page, not backed by `page_registry`; Alex wants this properly
   wired into the Pages editor eventually, explicitly deferred to later.
7. **`publish_resume()` snapshot bug** — its version-history snapshot logic
   never captures the outgoing `published_html` before overwriting it (both
   snapshot inserts save the current draft instead). Confirmed still
   unfixed — not started, backlog item.
8. Articles/Experiments category plan (Case Study, Newsletter as dedicated
   category) — discussed and decided earlier in-session, not yet implemented.

## Decisions made (for anyone resuming)

- Journals: 4 categories split by domain on the practice side (UX/Coaching)
  and by theme on the reflection side (Tech/Life) — not a uniform grid, chosen
  because it matched Alex's actual writing (no grounded personal notes, no
  wandering professional contemplations existed in practice).
- Categories are a shared substructure different audiences draw different
  blends from — never name a category after an audience segment.
- Git backups: going forward, commits get pushed to `origin` promptly and
  automatically (Alex confirmed 2026-07-12), not held for approval each time.
