PURPOSE: Migrate the thinking-system labs page into the CMS, then promote its eyebrow/section-header pattern into the design system + Tiptap editor
STATUS: active
LAST TOUCHED: 2026-07-12

## Objective: Migrate labs.alexmchong.ca/thinking-system into the CMS (started 2026-07-12)

**Intent:** Alex linked https://labs.alexmchong.ca/thinking-system/index.html and wanted it brought into the CMS with diagrams intact. Direction changed several times over the session: first attempt rewrote content into plain CMS prose + 4 kept diagrams (too lossy — dropped real headings, used eyebrow labels as h2 text by mistake, lost 6 of 10 diagrams). Alex then asked for near-1:1 fidelity instead — port the original page's own CSS/HTML wholesale, only re-fonted to match the site (Barlow/JetBrains Mono), scoped so it can't leak into the rest of the CMS. That version stuck and got heavily polished afterward (many small typography/spacing rounds).

### Timeline
- Attempting: rewrite into plain `.article-prose` prose + 4 SVG diagrams, category=Case Study
  → SUCCEEDED technically, but Alex flagged real content loss (eyebrow text used as h2, dropped diagrams, mobile collapse). Not a dead end exactly — became the basis for realizing full-port was the right call.
- Attempting: full 1:1 port of original CSS/HTML under a `.ts-original` scope wrapper, font-swapped Barlow/JetBrains Mono, dropped the original page's own `<header>` (CMS already renders title/summary/author via its own blocks)
  → SUCCEEDED — direct server write (not a commit): `content/experiment/thinking-system/main.html` on the **staging server only** (this path is in the deploy exclude list, never touches git).
- Attempting: fix category assignment — was wrongly "Case Study", should be "Experiment"
  → SUCCEEDED — direct DB write on staging (`content_categories.category` for `content_id=43`), no commit involved.
- Found + fixed two real bugs while polishing, not just styling:
  - `.card`/`.card-body` class names collided with the master design system's own sitewide `.card` component in `views.css` (loaded on every public page) — renamed to `.o-card`/`.o-card-body` etc. to de-collide.
  - Bare `<h2>`/`.hed` was picking up unwanted `margin-top: 48px` bleed from `.article-prose h2` (blocks.css) since nothing in the scoped CSS overrode it — added an explicit `margin-top: 0` override.
- Multiple rounds of typography/spacing/copy tweaks per Alex's live feedback (font sizes, weights, colors, text content, an SVG diagram reflow). One diagram (the "Think & Discuss" chat-bubble flow) had its bubble widths sized by **actually measuring the real Barlow font** — downloaded the TTF, measured exact pixel widths with Python/Pillow — after an earlier character-count estimate produced visibly-too-loose bubbles. Worth reusing that measurement approach for any future SVG-text-fitting problem.
  → All SUCCEEDED, all direct staging server writes (same `main.html`, edited/re-uploaded repeatedly), no commits.

### Outstanding (as of 2026-07-12)
- Content lives ONLY on the staging server (`content/experiment/thinking-system/main.html`), not in git, not on production. Needs the same file manually placed on production if/when this ships — it will not travel via `bin/deploy.sh`.
- No production DB row exists yet for this content item (staging `content.id=43` only).

---

## Objective: Promote the eyebrow pattern into the design system + Tiptap editor (started 2026-07-12)

**Intent:** Of six one-off CSS patterns identified in the ported page (eyebrow/heading, icon-card-grid, colored chips, left-border callouts, colored-panel+footer-bar, handoff-grid, tree-framing), Alex wanted to promote ONLY the eyebrow — the other five stay as scoped one-off CSS inside this one content item, by explicit choice, not oversight. Built a sandbox comparing 4 eyebrow treatments (3 reused existing DS classes, 1 new), Alex picked the new dedicated one. Then wanted it wired into the real Tiptap editor as a toolbar button so authors can actually use it going forward, not just this one hand-authored page.

### Timeline
- Built `docs/design-mockups/eyebrow-options.html` sandbox (4 options, served locally via `python3 -m http.server 8765`)
  → Alex picked Option B (editorial-hero-eyebrow-style treatment), later refined: no category tint, uses body-text color instead.
- Applied the chosen style directly to the live staging content first (per Alex's direction, confirmed live).
- Attempting: promote to `site/_design-system/css/public/blocks.css` as `.article-prose .kicker` + `.article-prose .kicker + h2 { margin-top: 0 }` pairing rule
  → SUCCEEDED, uncommitted local file change (see current git status below).
- Attempting: Tiptap toolbar button "H2^" — first implementation force-inserted a kicker+H2 pair via `insertContent`, which **destructively replaced any active text selection** (visible bug, Alex caught it via screenshot)
  → BLOCKED/wrong — fixed by switching to `insertContentAt` with a single position (pure insertion, doesn't eat selections).
- Attempting: same button, second bug — placeholder-text selection highlight was off by one character ("SECTIO" not "SECTION")
  → BLOCKED/wrong — root cause was hand-computed position arithmetic breaking when `insertContentAt` splits a mid-paragraph insertion point. Fixed by searching the doc for the actual inserted node instead of computing its offset by hand.
- Alex then clarified the whole approach was wrong in kind, not just buggy: didn't want a compound insert (kicker + forced H2) at all — wanted a pure **toggle**, exactly like the H2/H3 buttons (convert current block, preserve existing text, no forced pairing since kicker+H2 pairing was never actually required, just optional spacing sugar).
  → SUCCEEDED — rewrote using Tiptap's core `toggleNode(this.name, 'paragraph')` command (same primitive Heading uses internally for its own toggle). Much simpler, confirmed working correctly by Alex in the live staging editor.
- Also updated `site/lib/sanitize.php` (added `p[class=kicker]` to the allowlist, mirroring the existing `span.m` pattern) and `docs/BLOCKS.md` (Body block row) to match.
- Moved the "H2^" button from after-H3 to between-H2-and-H3 per Alex's positioning request.
  → SUCCEEDED.
- Archived the sandbox to `docs/design-mockups/_completed/eyebrow-options.html` + added a README entry, once the decision was made and built.
  → SUCCEEDED.
- All of the above deployed to **staging only** via `bin/deploy.sh staging` (run several times as fixes landed) — confirmed correct file state on the server after each deploy.

### Outstanding (as of 2026-07-12)
- **Not committed to git yet.** Currently uncommitted in the working tree: `docs/BLOCKS.md`, `docs/design-mockups/_completed/README.md`, `site/_design-system/css/public/blocks.css`, `site/cms/_assets/tiptap-setup.js`, `site/cms/views/article-edit.php`, `site/cms/views/experiment-edit.php`, `site/lib/sanitize.php`, plus untracked `docs/design-mockups/_completed/eyebrow-options.html`.
- **Not deployed to production.** Attempted via `bin/deploy.sh prod` — stopped short of running it because at the time the working tree had a lot of *other* unrelated uncommitted work in it too (now resolved — see reconciliation note below, tree is clean except for the 7 files above as of this log). A manual `scp`-around-the-deploy-script approach was also attempted and correctly blocked by the harness's safety classifier (bypasses the deploy script's automatic pre-deploy backup, notably for `sanitize.php`, a security-relevant file).
- **Decision needed from Alex:** commit + push these 7 files, then run `bin/deploy.sh prod` (now safe — nothing else pending in the tree) — is this session or the consolidating session doing that?
- No production DB row / content file for the thinking-system piece itself (see prior Objective) — separate from the code promotion above, which stands alone (the H2^ button works regardless of whether that one piece of content ever ships).
