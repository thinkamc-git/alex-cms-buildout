# Session Hygiene: Work Logs, Docs Freshness, Git Backups

> **Status:** canonical · **Added:** 2026-07-12 · **Scope:** every agent session
> working in this repo, especially when multiple agents run concurrently.

---

## 0. Why this exists

On 2026-07-12, a consolidation pass found: ~50 files modified/created across
several separate bodies of work (a content-type rename, a résumé system, a
portfolio import, a new block feature), all pushed directly to staging and/or
production over SSH by earlier sessions, **none of it committed to git**. A
project memory claimed production had never been touched — it had, fully, for
weeks. Reconstructing the truth required diffing every file against both live
servers by hand. Separately, 5 stray debug/draft files were found sitting on
the staging webroot root, orphaned by a session that never cleaned up after
itself, and local `main` was found to be 10 commits ahead of GitHub — a full
day-plus of work that existed only on one machine.

None of this was any single mistake — it's what happens by default when
several agent sessions work in parallel on the same repo with no shared
record of who did what, where. This doc is the fix.

---

## 1. Agent work logs

Every agent session doing non-trivial work (more than a quick fix) keeps a log
at `docs/_agent-logs/<date>_<short-task-slug>.md`.

**Concurrency rule: a log file is only ever written by the session that
created it.** No shared/central index file — two agents editing the same file
at the same time will silently clobber each other with no warning, since nothing
here has real locking. Wanting a combined view of "what's active right now"?
Read the directory listing and open the files — computed fresh, never a
maintained artifact that could go stale or collide.

**Header block**, same idea as a memory file's frontmatter:

```
PURPOSE: one-line what this session is working on
STATUS: active | handed-off | closed
LAST TOUCHED: <date>
```

**Body**, kept short: goal, what got touched (files / DB / **direct server
pushes — this is the field that would have prevented the 2026-07-12
incident**), decisions made, handoff notes for whoever picks this up next.

**On close-out:** update `STATUS: closed`, then move the file into
`docs/_agent-logs/_archive/`. Same shape as the existing sandbox workflow
(`docs/design-mockups/` → `_completed/`) — reuse the pattern, don't invent a
new one.

**Log any direct server write.** A one-off diagnostic script run over SSH and
deleted immediately after doesn't need a log entry (see §2 for the cleanup
rule that makes it safe to skip). Anything left running or referenced by a
live route — a feature deploy, a content publish, a config change — does.

---

## 2. Server scratch-file discipline

See the `feedback_server_scratch_cleanup` memory for the full rule. Summary:
any file pushed directly to a server outside the normal deploy path gets
deleted again in the *same session*, right after it's served its purpose.
While in use, it carries its own short header (purpose, created, status) so an
interrupted cleanup is still identifiable later, without having to diff and
read the file line-by-line to figure out whether it's safe to remove — which
is what 3 of the 5 stray files from the 2026-07-12 incident required.

---

## 3. Docs freshness

- Every canonical doc in `docs/` should be listed in **`CLAUDE.md`'s
  Documentation map** — that list is the map of what's current and worth
  reading. A doc that exists but isn't listed there is invisible to the next
  session and will drift unnoticed (this happened to `RESUME-SYSTEM.md`).
- **A new canonical doc gets added to CLAUDE.md's map in the same commit that
  creates it** — not a separate cleanup pass later.
- Where it's cheap to add, give a doc the same header block as §1 (`PURPOSE` /
  `STATUS` / `LAST TOUCHED`) so staleness is visible without reading the whole
  file. Retrofit opportunistically as docs get touched for real work, not as a
  mass rewrite.
- **Closing out an agent log is a natural moment to spot-check docs
  freshness** — does everything in `docs/` still map to CLAUDE.md or
  `_completed/`? Anything orphaned gets triaged then, while context is fresh.

---

## 4. Git: commit *and* push, promptly

A commit is a checkpoint saved only on this machine. A push sends it to
GitHub — the actual backup. **Commits get pushed promptly, not left
stranded locally.** After committing (or a small batch of related commits),
push immediately and say so plainly. Don't let local `main` run far ahead of
`origin/main` the way it did before 2026-07-12 (10 unpushed commits, discovered
only by accident).
