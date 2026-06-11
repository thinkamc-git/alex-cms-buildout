# Lesson 01 — Git and GitHub, the friendly version

**Goal:** By the end of this lesson you can open this project in VS Code, make a change, save it to GitHub, and see the history of every change you've ever made.

**What you need:**
- A Mac.
- A GitHub account (you have one).
- VS Code installed.
- About 30 minutes.

---

## 1. The 60-second mental model

Forget every Git tutorial you've half-read. Here's the only model you need:

**Git is a snapshot machine.** Every time you tell it to, it takes a snapshot of your entire project folder. Those snapshots form a timeline. You can walk backward through the timeline, see what changed between snapshots, or restore the project to any previous snapshot.

**GitHub is a backup of that timeline that lives on the internet.** It's also where you'd let other people see your timeline if you ever wanted to.

That's it. Everything else is vocabulary on top of that.

| Word | What it actually means |
|---|---|
| **Commit** | "Take a snapshot now, with this message describing what changed." |
| **Repository** (or "repo") | The folder Git is tracking, plus all the snapshots. |
| **Branch** | A parallel timeline. You can experiment on a branch without affecting the main timeline. We won't use branches in Phase 0. |
| **Remote** | "GitHub's copy of the timeline." |
| **Push** | "Upload my new snapshots to GitHub." |
| **Pull** | "Download GitHub's snapshots to my Mac." |
| **Clone** | "Make a fresh copy of a GitHub repo on my Mac." |

You will almost only ever do three things: **edit files**, **commit** (snapshot), and **push** (upload). The rest is for emergencies.

## 2. Why we're bothering

You could skip Git and just edit files in CloudMounter directly on the server. People do. Here's what you'd lose:

- **Undo.** If a CSS change breaks the layout and you don't notice for two days, Git lets you see exactly what changed and roll back. Without Git, you're squinting at file timestamps in CloudMounter.
- **A safety net.** If your laptop dies, the entire project is on GitHub. Buy a new laptop, clone the repo, you're back.
- **A history of your thinking.** Each commit message is a note to future-you about *why* a change was made. Six months from now you'll thank past-you.
- **Working with Claude smoothly.** When Claude edits files in this project, you can see exactly what changed (the "diff") before accepting the change. Without Git, you can't.

The cost is learning four buttons in VS Code. Worth it.

## 3. One-time setup

Do these once on your Mac. After this you never think about them again.

### 3.1 Install Git

Open the Terminal app (Cmd-Space, type "Terminal"). Paste this and press Enter:

```
git --version
```

If you see something like `git version 2.39.x`, you already have Git. Done — skip to 3.2.

If you see "command not found" or macOS prompts you to install Xcode Command Line Tools, click **Install** and wait. That's it; that installs Git.

### 3.2 Tell Git who you are

In Terminal, run these two commands (substitute your real name and the email you used on GitHub):

```
git config --global user.name "Alex M. Chong"
git config --global user.email "alex.m.chong@gmail.com"
```

This stamps your name on every commit you make. You can change it later.

### 3.3 Connect VS Code to GitHub

Open VS Code. Click the **person icon** in the very bottom-left corner of the window. Click **Sign in to GitHub**. A browser window opens — sign in. VS Code now talks to GitHub on your behalf. Done forever.

## 4. Make this folder a Git repository

Right now your `alex-cms-buildout/` folder has files but isn't tracked by Git. Let's fix that.

1. Open VS Code.
2. **File → Open Folder…** Pick `alex-cms-buildout`.
3. Look at the left sidebar. You'll see a row of icons. Click the **Source Control** icon — it looks like a small branching diagram (third icon down by default).
4. You'll see a button: **Initialize Repository**. Click it.
5. The Source Control panel will now show a list of every file in the project, each marked with a green **U** (for "untracked", meaning Git hasn't seen it yet).

Git is now tracking the folder. Nothing has been committed yet — Git is just watching.

### Add a .gitignore first

Before your first commit, we need to tell Git which files to *not* track. Things like editor settings and macOS hidden files shouldn't go to GitHub.

1. In VS Code, **File → New File**, name it `.gitignore` (the leading dot is intentional), save it at the project root.
2. Paste this content:

```
# macOS noise
.DS_Store

# VS Code workspace files
.vscode/

# Editor swap files
*.swp
*.swo

# Local config (will exist later, never commit)
config/config.local.php
config/config.staging.php
config/config.production.php

# Runtime folders (will exist later, never commit)
uploads/
content/
logs/
backups/

# Anything from a Node toolchain (in case)
node_modules/
```

3. Save. Notice in the Source Control panel that `.DS_Store` entries (if any) have disappeared — Git now ignores them.

## 5. Your first commit

1. In the Source Control panel, you'll see "Changes" with the file list.
2. Hover over the heading "Changes". A `+` button appears. Click it. This **stages** every change — meaning "include these in the next snapshot".
3. Above the file list there's a text box that says "Message". Type something like:

   ```
   Initial commit: project spec, design system, CMS mockup, templates, marketing pages
   ```

4. Click the big **Commit** button (with a checkmark icon).

That's it. You just took the first snapshot. Git has stored every file in the project as it exists right now.

## 6. Create the GitHub repository and push

The snapshot is on your Mac. Let's get it to GitHub.

1. In VS Code's Source Control panel, you'll see a button: **Publish Branch**. Click it.
2. VS Code asks: "Publish to GitHub private repository" or "public". **Pick private.** This project includes future config files you don't want public. Private is free.
3. Give it the name `alex-cms-buildout`.
4. Wait a few seconds.
5. VS Code shows a notification with a link to the new repo on github.com. Click it.

You're now looking at your code on GitHub. Every file you committed is visible. You can navigate, read, search.

## 7. The daily loop

Every time you change something in this project, the loop is the same:

1. Edit files in VS Code.
2. Open the Source Control panel — modified files show with an **M**.
3. Click each file to see exactly what changed (the diff appears in the main editor).
4. Stage everything you want to include (`+` next to "Changes").
5. Write a one-sentence commit message describing *why*.
6. Click **Commit**.
7. Click **Sync Changes** (or the up-arrow button) to push to GitHub.

That's the entire workflow.

## 8. Try it — a small exercise

Do this exact thing now. It takes five minutes.

1. Open `CLAUDE.md` in VS Code.
2. At the very bottom of the file, add a new line:

   ```
   _Last updated: 2026-05-11 by Alex — initial repo setup_
   ```
3. Save the file (Cmd-S).
4. Open the Source Control panel. Confirm `CLAUDE.md` shows as **M** (modified).
5. Click on it — you should see the diff with your new line highlighted green.
6. Stage it (`+` on the file).
7. Commit message: `docs: stamp CLAUDE.md with setup date`.
8. Commit.
9. Sync.
10. Open the repo on github.com. Click the commit count at the top of the file list. You should see two commits now — the initial one, and the one you just made. Click yours to see the diff.

If all of that worked, you're done with Lesson 01. Seriously.

## 9. Common gotchas

- **"Why is `.DS_Store` showing up everywhere?"** macOS creates these silently. The `.gitignore` you wrote handles it. If you see one anyway, you probably added the `.gitignore` *after* committing the file once — easy fix, Claude can help.
- **"Commit failed, no upstream."** Means you committed locally but haven't pushed yet. Click **Sync Changes** and it'll push.
- **"My commit messages feel awkward."** Convention: present tense, short, focused. `add login form`, `fix sidebar spacing`, `update spec for journal numbering`. Not `I added the login form yesterday`.
- **"I committed something I shouldn't have."** Don't panic. Don't push. Tell Claude. There are clean ways to undo locally before it gets to GitHub.

## 10. What to ask Claude if you get stuck

- "I see this error in VS Code's source control: \[paste it]. What's it telling me and how do I fix it without losing my changes?"
- "I committed a file I shouldn't have. It's `[filename]`. I haven't pushed yet. Walk me through removing it."
- "I edited a file in CloudMounter on the staging server instead of locally. How do I get that change into Git?"

## 11. What you don't need to learn yet

Don't worry about: **branches**, **merging**, **pull requests**, **rebasing**, **stash**, **HEAD**, **the staging area vs working directory distinction**. You won't need any of those in Phase 0. When you do, we'll cover them then.

---

**Next:** `02-dreamhost-setup.md`.
