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

**Chat = agent = log, 1:1:1.** Every agent session doing non-trivial work
(more than a quick fix) keeps exactly one log at
`docs/_agent-logs/YYMMDD_XXX_short-task-slug.md` for its whole lifetime, where
`XXX` is the session's 3-letter name (see below). **Create it as soon as the
work becomes non-trivial — not at the end.** The point is mid-flight
visibility (Alex asking "what's the agent on X doing right now"), which only
works if the log exists *while* the work happens.

### Agent naming

Remembering "the agent working on categories" across several concurrent
sessions is hard — a short name is a much easier handle than a filename or a
task description. Every session picks a **3-letter name** for itself the
moment it creates its log:

1. **Scan `docs/_agent-logs/` (active) and recently-archived logs, read-only,
   for names already in use.** This is a read, not a write — safe even with
   other sessions active concurrently (see the concurrency rule above; only
   simultaneous *writes* to the same file are the hazard).
2. **Pick an unused name that doesn't visually resemble one already in
   play** (avoid picking TIM when ROX is already active, but also avoid
   picking TOM when TIM is active — they read as the same shape at a
   glance). A starting pool of reasonably distinct 3-letter names:
   `TIM, ROX, ZED, KAI, LUX, JAX, NIA, PIP, VEX, ORO, FIN, WYN, ACE, BEN,
   IVO, MOX, QIN, RYE, SAL, UMA, DOV, ELI, GUS, HOP` — add more as needed,
   same principle (short, pronounceable, visually distinct as a set).
3. **Introduce yourself in chat once the log exists** — a short, natural
   "Hi, I'm TIM — starting on the categories redesign" the first time you'd
   otherwise just start working. Gives Alex an immediate, low-effort handle
   to reference the session by, without opening any files.

**Concurrency rule: a log file is only ever written by the session that
created it.** No shared/central index file — two agents editing the same file
at the same time will silently clobber each other with no warning, since
nothing here has real locking. Wanting a combined view of "what's active
right now," or "what's outstanding across every closed-out session"? Scan the
directory (active logs) or `_archive/` (closed ones) and compute the answer
fresh, on request — never a maintained file that could go stale or collide.
This applies to outstanding-task rollups too: don't create a shared
`OUTSTANDING.md` that gets appended to at close-out — same collision risk,
just rarer. Every archived log keeps its own Outstanding section; "what's
outstanding" is a question any agent answers on demand by reading them all,
not a standing file.

**Header block**, same idea as a memory file's frontmatter:

```
NAME: XXX (the 3-letter name, e.g. TIM)
PURPOSE: one-line summary (can span multiple objectives over the log's life)
STATUS: active | closed
LAST TOUCHED: <date>
```

### Structure: Objectives → Timeline → Outstanding

A single chat can cover more than one objective over its life (this session
did — categories redesign, then consolidation, then this hygiene system).
That doesn't mean multiple files — it means multiple `## Objective` sections
inside the one log:

```
## Objective: <name> (started <date>)

**Intent:** what Alex actually asked for, in plain terms — not just the
mechanical output. Update this if the ask evolves mid-objective.

### Timeline
- Attempting: <what's about to be tried>
  → SUCCEEDED — <commit hash, or timestamp if not a commit>
- Attempting: <what's about to be tried>
  → BLOCKED — <why, e.g. sandbox denied a direct prod write>
- Attempting: <retry, with whatever changed>
  → SUCCEEDED — <commit hash>

### Outstanding (as of <date>)
- <unresolved item, or "none — fully resolved">
```

**Log the attempt, then the outcome — including failure.** Don't just record
what worked; a blocked/failed attempt is exactly the information that stops
another agent from repeating a known dead end, and it's the honest shape of
what actually happened (see the SSH permission-denial → retry arc in the
2026-07-12 log for a real example).

**Two kinds of outcome reference:**
- **Committed to git** → reference the commit hash, don't hand-write a
  timestamp. The hash is verifiable; a hand-typed time next to it is just a
  second copy that can drift or be wrong.
- **Not a commit** (a direct server push, a DB write, an SSH session) → has
  no other record anywhere, so it needs an explicit timestamp.

**Update Outstanding continuously, not just at a final ritual.** This is the
backstop for the failure mode where Alex just closes a chat tab without
running a formal close-out — if the log was kept live throughout, it's still
useful even when abandoned mid-work. The periodic staleness check (below) is
what surfaces it later.

**On close-out** (Alex says "close this out," or a staleness check flags it
as done): finalize each open Objective's Outstanding section. **Every item
gets an explicit disposition — no bare, untagged bullets once a log is
actually closed:**

```
### Outstanding (as of <date>)
- <item> — HANDED OFF (open for another session to pick up — normal,
  expected, not a sign anything went wrong)
- <item> — DROPPED (Alex decided not to pursue)
- <item> — RESOLVED, see 260712_TIM_categories-plan-and-consolidation.md
- none — fully resolved
```

An untagged item in a *closed* log is itself a sign something's wrong —
either it should have been resolved before closing, or it should have been
tagged HANDED OFF. Set `STATUS: closed`, move the file into
`docs/_agent-logs/_archive/`. Same shape as the existing sandbox workflow
(`docs/design-mockups/` → `_completed/`) — reuse the pattern, don't invent a
new one.

**On request, staleness review:** scan active (non-archived) logs for old
`LAST TOUCHED` dates (rule of thumb: 4-5+ days) where the content reads as
resolved, and propose closing them. This is an educated guess from reading
the log, always — logs are a helpful signal, never ground truth. The only
ground truth in this project is git commits and live server state (see
§0 — this is exactly what a stale memory got wrong on 2026-07-12).

**Don't trust a single archived log's Outstanding section in isolation —
cross-check newer logs first.** A HANDED-OFF or untagged item might already
be done: the RESOLVED annotation (below) depends on whoever finishes the
work remembering to write it, which is a manual step that can be missed
(nearly was, for ROX's log, on 2026-07-12). Before reporting something as
genuinely still outstanding — to Alex, or in a new log's Intent section —
search *other* logs (active and archived, not just the one being read) for
any mention of that same task. Treat "no RESOLVED tag found" as "probably
still open," not "definitely still open."

**On consolidation** (Alex merging several open agents into one): the new
agent reads the relevant logs' Objectives + Outstanding sections to
reconstruct pending work. This gets most of the way there but not all of
it — a log can't capture something an agent was about to raise but hadn't
yet. Treat it as "read the logs, then a quick human gut-check," not as a
complete transfer.

**Amending a closed session's log — two states, two triggers.** Archived
logs are otherwise left alone as a historical record, but this is a narrow,
explicit exception with two forms:

- **PICKED UP** — Alex explicitly says an active session is taking on a
  closed session's outstanding work (e.g. "TIM, we're taking on ROX's
  tasks"). That statement is what authorizes TIM to write into ROX's
  archived file — not just "it's closed so it's fair game." Annotate the
  item: `**PICKED UP** — being handled by 260712_TIM_..., not done yet`.
- **RESOLVED** — once that work is actually finished (this happened
  2026-07-12: a closed session had real outstanding work, a different
  session picked it up and finished it, but the original log's Outstanding
  section still read as unresolved). Annotate:
  `**RESOLVED** — see 260712_TIM_categories-plan-and-consolidation.md`.

Both are one-line additions directly next to the original entry, never
deleting or rewriting the original text. Safe specifically *because* the
session being amended is closed — there's no other live session
concurrently editing that file, so the single-writer concurrency rule isn't
actually being violated; a closed log has no writer left to collide with.

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
