PURPOSE: Journal category redesign (planning + migration) + full uncommitted-work consolidation
STATUS: active
LAST TOUCHED: 2026-07-12

## Goal

Two threads that merged into one session:
1. Redesign the Journals category set with Alex (was Inquiry/Observation/Principle).
2. Reconcile ~50 files of uncommitted work across the repo — several past
   sessions had pushed directly to staging/prod without ever committing,
   and a project memory incorrectly claimed prod was untouched.

## What got touched

### Journals category migration (data only, no schema change)
- New categories (both staging + prod, via `save_category()`/`assign_primary_category()`):
  **UX Practice, Coaching Notes, On Technology, About Life** — replacing
  Inquiry/Observation/Principle (+ Contemplation, prod-only).
- Prod: 5 real published entries hand-mapped and reassigned; staging: randomized
  (dummy content). `journal_number` recomputed per new category on both.
- Old categories deleted once usage hit 0 on both environments.

### Bug found + fixed: category colour not rendering on public cards
- Root cause: card accent colour (`--c-current`) was set via a **static CSS
  list keyed to old category slugs** (`views.css:9-19`), not from the database
  — so any category rename/recolor silently did nothing on the public site.
- Fix: `site/templates/partials/index-card.php` now sets `--c-current` inline
  from the category's live DB colour on every card type (article/journal/event).
  Deployed directly to staging + prod (scp, approved by Alex — see git log for
  the file, not yet as its own commit at time of writing).

### Uncommitted-work consolidation — 5 commits made, pushed to `origin/main`
1. Content-type rename (article→essay/journal→field-note/experiment→field-work)
2. Library → Résumés (CMS editor + public /resume/)
3. `cv.php` marketing page
4. Portfolio-for-hire (self-hosted, replaces Webflow redirect)
5. CMS admin polish (table th classes, drag-reorder tweaks)

All 5 were verified byte-for-byte against both live servers **before**
committing — nothing here changed live behavior, it only caught git up to
reality.

### Corrected a stale memory
`project_rename_content_types.md` said prod had never been touched — false;
migrations 0038–0041 are applied on both environments and have been for a
while. Corrected in place, with a note that git status ≠ live state for this
project.

### New session-hygiene system (this doc is the first output of it)
- `docs/SESSION-HYGIENE.md` — canonical doc: agent work logs, docs freshness,
  git push discipline.
- `docs/_agent-logs/` — this folder. No shared index file (concurrency risk
  with multiple agents editing the same file — see SESSION-HYGIENE.md §1).
- New memory: `feedback_server_scratch_cleanup.md`.

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
5. **Test-artifact PDFs** got swept into the résumé commit
   (`docs/_cv/pdf-exports/test2-...`, `print-template-...` and a few
   timestamp-hash duplicates) — flagged to Alex, not yet cleaned up.
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
