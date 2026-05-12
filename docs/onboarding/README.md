# Onboarding — Phase 0 lessons

This folder is your training wheels. Work through it once before any code gets written. Each lesson is short, scoped, and ends with one small thing you do to prove you've got it.

## Read in this order

1. **`01-git-and-github.md`** — What Git is, why we're using it, how to use it from VS Code without ever opening a terminal. The first lesson because everything else assumes the repo is on GitHub.
2. **`02-dreamhost-setup.md`** — Click-by-click walkthrough of the DreamHost panel to create the staging subdomain, the two MySQL databases, and to confirm your PHP versions. Also covers FTP credentials and the staging password gate.
3. **`03-vscode-claude-code.md`** — Configure VS Code for this project, install Claude Code, point it at this repo, learn what to ask it.
4. **`04-three-environments.md`** — The mental model for local / staging / production. How files actually flow between them with CloudMounter. What to test where.

## How long this should take

If you sit down with no interruptions: roughly **2 hours total** for all four lessons. Don't rush — the rest of the build assumes you're comfortable with everything here.

## Where this fits in the bigger plan

This is Phase 0 of `BUILD-PLAN.md`. No PHP gets written, no database tables get created. By the end of Phase 0 you'll have:

- The repo on GitHub, edited from VS Code, pushed once.
- Both MySQL databases created in DreamHost.
- The staging subdomain serving a basic test page.
- A working mental model of how your three environments relate.
- A clear idea of what to ask Claude Code (and what not to).

Then Phase 2 begins for real.

## Lesson conventions

Inside each lesson you'll see:

- **Goal** — one sentence: what you'll be able to do after the lesson.
- **What you need before starting** — required state.
- **The lesson itself** — the smallest possible explanation of *why*, followed by the concrete steps.
- **Try it** — a small exercise. Do it. Don't skip.
- **Common gotchas** — what trips people up.
- **What to ask Claude if you get stuck.**

## Tone

These are written assuming you've never used Git, never set up MySQL by hand, and have never edited a PHP file. None of that's a deficiency — you're a designer, not a backend engineer. The whole point of the system we're building is that *future you*, six months from now, can come back to it and not feel lost. So this is the foundation that future-you stands on.
