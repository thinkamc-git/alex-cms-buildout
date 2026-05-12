# Lesson 03 — VS Code + Claude Code for this project

**Goal:** VS Code is configured for this project, Claude Code is installed and pointed at the repo, and you know the right way to talk to it.

**What you need:**
- VS Code installed (you already have it).
- The repo cloned locally and pushed to GitHub (Lesson 01).
- About 20 minutes.

**Assumption:** This lesson skips installation — VS Code, Git, and Claude Code are already on your Mac. It focuses on *configuring them for this project*.

---

## 1. Open the project (once)

1. **File → Open Folder…** Pick `alex-cms-buildout/`.
2. VS Code asks "Do you trust the authors of the files in this folder?" — pick **Yes, I trust the authors**.
3. **View → Explorer** (Cmd-Shift-E) so you can see the file tree on the left.
4. **View → Source Control** (Ctrl-Shift-G) so you can see commits as you work.

That's it. Every future session starts by reopening this folder from VS Code's **File → Open Recent**.

## 2. Recommended VS Code extensions

Open **Extensions** (Cmd-Shift-X) and install these three. Each one is searchable by the name shown.

| Extension | Why |
|---|---|
| **PHP Intelephense** | Autocomplete and red-squiggly error checking for PHP. The CMS is PHP — you'll want this from Phase 3 onward. |
| **Tailwind-style CSS / IntelliSense for CSS class names** *(optional)* | Hovering over a class name in HTML shows you what CSS rules it has. Useful when editing `site/_templates/` and `docs/design-mockups/`. |
| **Markdown All in One** | Better Markdown preview and TOC generation. Useful because most of this project's design lives in `.md` files. |

You don't need a PHP debugger, a Live Server, or any framework-specific tools. The project is small and the browser plus DreamHost staging are enough.

## 3. VS Code settings for this project

Open the Command Palette (Cmd-Shift-P) → **Preferences: Open Workspace Settings (JSON)**. Paste this:

```json
{
  "editor.tabSize": 2,
  "editor.insertSpaces": true,
  "editor.rulers": [100],
  "files.trimTrailingWhitespace": true,
  "files.insertFinalNewline": true,
  "files.exclude": {
    "**/.DS_Store": true
  }
}
```

These match the conventions in `ENGINEERING.md`. Save the file — VS Code will create `.vscode/settings.json` automatically. (That folder is in `.gitignore`, so it stays local.)

## 4. Install Claude Code in VS Code

If you haven't already:

1. **Extensions** (Cmd-Shift-X) → search **Claude Code** → **Install**.
2. Sign in when prompted. Use the same Anthropic account that owns this Cowork session.
3. Open the Claude Code side panel from the activity bar (the Claude icon).

## 5. Point Claude Code at this repo

Claude Code automatically reads `CLAUDE.md` at the root of whatever folder is open in VS Code. That file is the orientation guide — it tells Claude where everything lives, which files are tightly coupled, and which rules to follow. **You don't need to do anything else to "configure" Claude — opening the folder is the configuration.**

To confirm it's working: open a new chat in the Claude Code panel and type:

```
Read CLAUDE.md and BUILD-PLAN.md §3, then tell me which phase we're on and what its autonomy tier is.
```

A correct answer mentions Phase 0 and "Manual." If Claude is off-topic or asks where to start, it didn't pick up `CLAUDE.md` — double-check that the folder you opened in VS Code is `alex-cms-buildout/` and not its parent.

## 6. How to ask Claude well in this project

The whole plan is structured to make Claude useful with minimal hand-holding. The pattern, every time:

1. **Find the current phase in `BUILD-PLAN.md` §3.** It's the first row with an unchecked `[ ]` box.
2. **Read that phase's Session brief at the top of its section.** Note the autonomy tier.
3. **Fill in the Decisions block** if the brief has unanswered rows. Save the file.
4. **Start the Claude Code chat with:** "Start [Phase N] — read its session brief and execute."
5. **Step back.** Auto and Semi-auto phases will run unattended. Manual phases will need you in the loop.
6. **Review at Verification.** Every phase ends with a numbered Verification checklist. Walk through it before checking the box.

## 7. Prompting patterns that work in this repo

| Want to… | Say something like… |
|---|---|
| Start a phase | "Begin Phase 4. Read its Session brief and the Decisions block I just filled in, then go." |
| Ask a system question | "What does `CMS-STRUCTURE.md` say about how Author Bio renders when the bio field is empty?" |
| Fix something specific | "In `site/_templates/article.html`, the byline is rendering above the title. Per `BLOCKS.md`, byline should be below subtitle. Move it." |
| Check coupling | "I'm about to add a new block called `pull-quote`. Walk me through every file I have to touch per the rule in `CLAUDE.md`." |
| Recover from a mistake | "I edited `docs/design-mockups/cms-ui.html` without updating `BLOCKS.md`. Diff the two, tell me what's now inconsistent, and propose the matching change." |

## 8. Prompting anti-patterns

These will waste your time:

- **"Build the CMS."** Too big. Pick a phase.
- **"What should I do next?"** Claude will guess. You decide by looking at `BUILD-PLAN.md` §3.
- **"Read everything in this repo and summarize it."** Burns 30K tokens before any work gets done. The `CLAUDE.md` reading flow is intentionally narrow to avoid this.
- **"Fix this without explanation."** You lose the audit trail. Always let Claude show its diff before committing.

## 9. Common gotchas

- **Claude can't see staging or production.** It only sees files in the VS Code workspace. Deployment is your job (drag from local → CloudMounter mount).
- **Claude has an older code training cutoff than the live web.** If it says "DreamHost's panel looks like X" and the panel looks different, trust your eyes and re-describe what you actually see.
- **Long sessions drift.** If Claude starts contradicting itself or forgetting earlier context after an hour, start a fresh session. The Session brief pattern is designed to make fresh sessions cheap.
- **Claude will follow `.gitignore` rules** when proposing file edits — it won't suggest editing files there. If something looks "missing," confirm the file actually exists.

## 10. Try it — a small exercise

1. Open `BUILD-PLAN.md` in VS Code. Jump to §3 (Phase index).
2. In the Claude Code panel, ask:
   ```
   Per BUILD-PLAN.md §3, what's the next phase after Phase 0? What's its autonomy tier and hour estimate?
   ```
3. Expected answer: Phase 1, Manual, 2–3 hours, ships the new alexmchong.ca live publicly.
4. If Claude answers something different, it's not reading the file. Re-open the folder root and try again.

If that worked, you're done.

## 11. What to ask Claude if you get stuck

- "VS Code isn't showing the Source Control panel — what menu is it under?"
- "Claude Code keeps asking me where the project is. Why isn't it auto-detecting `CLAUDE.md`?"
- "I see this PHP error in the Intelephense extension: \[paste]. What does it mean and is it real?"

---

**Next:** `04-three-environments.md`.
