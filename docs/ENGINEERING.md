# alexmchong.ca CMS — Engineering Conventions

**Status:** Draft v1. Living document — update when a decision changes.
**Audience:** Alex, and every Claude instance that touches this repo.
**Companion documents:** `BUILD-PLAN.md` (what to build), `CMS-STRUCTURE.md` (system spec), `AUTH-SECURITY.md` (security spec).

---

## 1. Why this document exists

LLMs left unsupervised produce code that *looks* fine in the moment but accumulates rot: inconsistent naming, duplicated logic, magic numbers, CSS that fights itself, business logic scattered through templates. Six months later nobody — human or model — can confidently change anything.

This file is the antidote. It is a strict, narrow set of rules. **Every rule here is enforceable on inspection.** When Claude writes code in this repo, it must follow these rules. When a rule is unclear, Claude must ask before guessing.

---

## 2. The core principles

1. **One concern per file.** A file is named for what it does. If you can't write its purpose in one sentence, split it.
2. **One way to do each thing.** If we've decided how to do X, every X in the codebase does it that way.
3. **Tokens, not values.** Colours, spacing, type sizes, durations — all come from named variables. Magic numbers are a bug.
4. **Data access in one place per entity.** Templates render. Views orchestrate. `lib/*.php` queries. Never mix the layers.
5. **No quiet inventions.** If the spec doesn't cover a case, surface it. Don't invent a column, a slug, a token, or an endpoint without writing it down first.

---

## 3. Folder & file naming

The canonical structure is in `BUILD-PLAN.md` §15. Naming rules:

- **Folders:** lowercase, hyphen-separated if multi-word (`live-sessions`, not `liveSessions` or `live_sessions`).
- **Reference / source-of-truth folders:** prefixed with `_underscore` (`site/_design-system`, `site/_templates`, `site/_pages`, plus `docs/design-mockups/`). These are source files PHP reads, not URL paths the public sees.
- **Runtime folders:** no underscore (`lib`, `cms`, `templates`, `config`, `db`, `cron`, `uploads`, `content`, `static`).
- **PHP files:** lowercase, hyphen-separated (`article-edit.php`, not `articleEdit.php`).
- **CSS files:** lowercase, hyphen-separated, one purpose per file (see §6).
- **JS files:** lowercase, hyphen-separated (`tiptap-setup.js`).
- **SQL migrations:** zero-padded four-digit prefix + underscore + lowercase snake (`0001_initial_schema.sql`, `0002_users_table.sql`).

**Never** rename a runtime file once it has been deployed to staging — references break. Add new, redirect old.

---

## 4. PHP conventions

### 4.1 Language baseline

- PHP **8.2** is the floor (production runs 8.2; staging runs 8.4). Use `declare(strict_types=1);` at the top of every PHP file we write.
- Always use **typed signatures**: `function get_article(string $slug): ?array`. Untyped is forbidden.
- Use `match()` over long `switch` chains. Use named arguments when calling a function with more than three parameters.
- No `eval`. No `exec`. No `shell_exec`. No `system`. The CMS does not shell out.

### 4.2 The three layers

| Layer | What it does | Where it lives | What it must NOT do |
|---|---|---|---|
| **Lib** | Reads/writes the database. Returns plain arrays. | `lib/*.php` | Output HTML. Set headers. Read `$_POST` directly. |
| **Views / Controllers** | Receives request, calls lib, hands data to a template. | `cms/views/*.php`, `index.php`, `cms/*.php` | Write SQL. Define query strings inline. |
| **Templates** | Renders HTML from data. | `templates/*.php`, `cms/partials/*.php` | Query the database. Write to disk. Modify session. |

**The rule:** a template never contains the string `$pdo` or `PDO` or `->query` or `->prepare`. Ever. If you find yourself wanting to, the missing piece belongs in `lib/`.

### 4.3 Data access — the one-function rule

For each entity (`article`, `journal`, `live-session`, `experiment`, `series`, `category`, `redirect`, `index`, `author`, `user`), there is exactly one file in `lib/` that owns it. Inside that file, one function per operation:

```php
// lib/content.php

declare(strict_types=1);

function get_article(string $slug): ?array { /* … */ }
function list_articles(array $filters = []): array { /* … */ }
function save_article(array $data): int { /* returns id */ }
function delete_article(int $id): void { /* … */ }
function transition_stage(int $id, string $to_stage): void { /* … */ }
```

Naming convention:
- `get_<entity>($slug_or_id)` — single record, may return `null`.
- `list_<entity>($filters)` — collection.
- `save_<entity>($data)` — upsert (insert if no id, update if id present). Returns the id.
- `delete_<entity>($id)` — void.
- Verb-specific helpers (`transition_stage`, `publish_article`) — explicit names.

Nothing outside `lib/` ever writes SQL. **This is the single most important rule in this document.** It is what lets us migrate the database without rewriting the rest of the code.

### 4.4 SQL & PDO

- Every query goes through PDO. Configure with `ERRMODE_EXCEPTION` and `FETCH_ASSOC`.
- Every parameter is bound — no string concatenation into SQL. **No exceptions.**
- Schema changes go in `db/migrations/NNNN_description.sql`. Never edit a past migration. Never run schema changes from PHP at runtime.
- Loose-coupled FKs (e.g. `content.category` → `categories.value_slug` as a string, not a real FK) are explicit per `CMS-STRUCTURE.md` §9 — preserve that pattern.

### 4.5 Errors & output

- User-facing errors are friendly: a styled 404 / 500 page, never a stack trace.
- Internal errors log to `logs/error.log` (in the project, not the system log). The log file is gitignored.
- `display_errors = 0` in production. `display_errors = 1` in local + staging.
- Never `echo` or `print` from `lib/`. Lib functions return values or throw.

### 4.6 Sessions, headers, cookies

- All session cookies are `httpOnly`, `secure`, `samesite=Strict`.
- Session name is `cms_session`.
- CSRF token is in the session; verified on every POST. See `AUTH-SECURITY.md`.
- `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: same-origin` set globally in `.htaccess` or `index.php`.

---

## 5. SQL conventions

- Table names: lowercase plural (`articles` would be the convention, *except* we already chose `content` as the umbrella table — keep what `CMS-STRUCTURE.md` §9 specifies).
- Column names: snake_case.
- Booleans: `BOOLEAN NOT NULL DEFAULT FALSE`.
- Timestamps: `TIMESTAMP DEFAULT CURRENT_TIMESTAMP`. Add `ON UPDATE CURRENT_TIMESTAMP` only when needed.
- Indexes named `ix_<table>_<columns>`.
- Unique keys named `uk_<table>_<columns>`.
- Foreign keys named `fk_<table>_<column>_<reftable>` when used.

---

## 6. CSS architecture

This is where sloppy AI output causes the worst damage. The rules below are strict.

### 6.1 The seven layers (file order)

Per `CMS-STRUCTURE.md` §3, the CSS is organized in seven layers. Each layer is its own file in `site/_design-system/css/`. Layers are loaded in this order — *every* page (CMS, public site, design system) loads them in this order:

1. `tokens.css` — `:root` custom properties only. Colours, spacing, type scale, durations, radii, rules. **No selectors other than `:root` are allowed in this file.**
2. `base.css` — `*` reset, body, dot-grid background, link defaults.
3. `typography.css` — font-face declarations, heading scale, body type, the muted-word treatment, the Instrument Serif rules.
4. `shell.css` — topbar, sidebar, layout frame, main canvas.
5. `components.css` — buttons, filter bar, view header, form fields, pills, badges.
6. `tables.css` — `.table-card`, `.cms-table`, td variants. Tables are common enough to be their own layer.
7. `status.css` — pill stage variants, type badges, live-dot animation, category labels.

CMS-specific additions live in `cms/assets/css/`:
- `cms-views.css` — view-specific overrides (pipeline kanban, ideation, published grid, editor, etc.).
- `tiptap.css` — editor chrome (loaded only inside the editor view).

Public-site additions live in `templates/assets/css/`:
- `article.css` — article-family rendering (replaces the existing `site/_templates/style-articles.css` at Phase 8).
- `index.css` — Editorial Page / Basic Listing layouts.

### 6.2 The hard CSS rules

- **No raw hex outside `tokens.css`.** Not in any other CSS file, not inline, not in PHP. Everything resolves through `var(--token-name)`.
- **No raw pixel values outside `tokens.css` for spacing, font-size, line-height, or radii.** Use the spacing/type-scale tokens (`var(--space-md)`, `var(--text-body)`, etc.). Exception: 1px / 2px structural rules; these are still pulled through `--rule`, `--rule-30`, `--rule-faint` per `system.css`.
- **No `!important`.** If you need `!important`, the cascade is wrong; fix the order or the specificity.
- **No deeply nested selectors.** Maximum two levels (`.parent .child`). Three is a smell; four is forbidden.
- **No element-name selectors for app styling** (`div.foo`, `button.btn-pri`). Class selectors only, except for resets (`*`, `body`, semantic HTML).
- **No inline styles.** Exception: a single inline `style="--c-current: var(--c-…)"` for category tinting, because the token *is* the data. All other styling is in CSS files.
- **No utility classes** in the Tailwind sense. We are not building an atomic CSS system. Components own their own styles.

### 6.3 Naming (component-style, BEM-lite)

We're already following this pattern in the mockup; document and enforce it.

- **Block:** the component root. `.cat-block`, `.pill`, `.cms-table`, `.idea-card`.
- **Element:** child of a block, joined by `__` *or* by a single hyphen if the mockup already uses hyphens (the mockup uses hyphens — keep consistent). E.g. `.cat-block .cat-table` rather than `.cat-block__table`. We pick **hyphen** for this repo.
- **Modifier:** suffix preceded by `-`. `.pill-live`, `.pill-hidden`, `.btn-pri`, `.btn-sec`.
- **State:** `.is-active`, `.is-open`, `.is-loading`. Prefixed with `is-`. State classes are toggled by JS; component classes are static.

### 6.4 Category colour pattern

Categories tint their containers by setting `--c-current` on the container, then components reference `--c-current`. Don't redefine `--c-current` per component. Don't reach into the palette inside a component — always `var(--c-current, var(--primary))`.

```html
<article class="card" style="--c-current: var(--c-denim)">
  …
</article>
```

```css
.card .cat-label { color: var(--c-current); }
.card .pill-active { background: var(--c-current); }
```

This is already the pattern from `DESIGN.md` — don't introduce alternates.

### 6.5 No CSS regressions

When a phase ends, the design system showcase (`/_ds/`) must look identical to the previous phase. If a token's *value* changes, the visual change must be intentional and noted in the phase writeup. If a value changes without intent, that's the bug.

---

## 7. JavaScript conventions

- Vanilla ES modules. No bundler. No jQuery. No framework.
- Files in `cms/assets/js/` and `templates/assets/js/`.
- One IIFE or one default-exported module per file.
- DOM lookups are scoped (`form.querySelector(…)`, not `document.querySelector(…)`) inside component code.
- Event listeners use `addEventListener`. No inline `onclick`.
- Forms POST via standard form submission unless we have a real reason to JS it. Progressive enhancement: the form must work with JS disabled.
- The Tiptap editor is the one big JS surface. Configure once in `tiptap-setup.js`; reuse everywhere.

---

## 8. HTML / template conventions

- 2-space indentation.
- One `<section>` per logical region.
- `data-*` attributes for JS hooks (`data-action="delete"`, `data-id="42"`). Never use class names as JS selectors — classes are for CSS.
- ARIA labels on every interactive element that doesn't have visible text (icon buttons especially).
- All forms have a hidden CSRF input. Provided by a helper: `<?= csrf_field() ?>`.
- All user-supplied output is escaped: `<?= e($title) ?>` where `e()` is `htmlspecialchars($_, ENT_QUOTES, 'UTF-8')`. Tiptap-sanitized HTML is the only string output un-escaped — and it goes through `lib/sanitize.php` on save, not on render.

---

## 9. Form submissions

The standard pattern:

1. View renders a form. CSRF field, all inputs, named action attribute.
2. Form POSTs to the same URL (or a sibling).
3. Controller validates: CSRF first; then required fields; then business-rule validation. Each failure adds to an `$errors` array.
4. If `$errors` is empty, controller calls `lib/<entity>::save_…`, gets back the id, redirects (POST-then-redirect) to the view's success URL.
5. If `$errors` is non-empty, controller re-renders the form with errors and the previously-submitted values.

No AJAX form submission in v1 unless explicitly called for in `BUILD-PLAN.md`.

---

## 10. Image & file uploads

Per `CMS-STRUCTURE.md` §13:

- All uploads pass through `lib/uploads.php::accept_upload()`.
- That function validates MIME (against an allowlist), validates size (max 10 MB unless overridden), generates a slug-safe filename, ensures the destination folder exists, and moves the file.
- Original filename is preserved if safe; otherwise replaced with a slug.
- No upload bypasses this helper. No upload writes outside `/uploads/` or `/content/`.

---

## 11. Configuration

- `config/config.php` is the only entry point. It detects the environment from `$_SERVER['HTTP_HOST']` and requires the appropriate `config.<env>.php`.
- The env-specific files contain: DB DSN, DB user, DB password, `BASE_URL`, `IS_PRODUCTION` boolean, `LOG_LEVEL`.
- **None of them are in git.** `config.example.php` is in git as a template.
- Secrets never leave `config/`. No secret appears in a template, a view, or a comment.

---

## 12. Logging

- One log file: `logs/error.log`. Gitignored.
- Format: `[ISO-8601 timestamp] [LEVEL] [request-id] message {context-json}`.
- `lib/log.php` provides `log_error($message, $context = [])` and `log_info(…)`. Use these, never `error_log` directly.
- Rotate weekly via the cron (`cron/rotate-logs.php`, added in Phase 10 if log volume warrants).

---

## 13. Migrations

- `db/migrations/NNNN_description.sql` — append-only, never edited after applied.
- The migration runner (`db/migrate.php`) keeps a `_migrations` table: `filename`, `applied_at`. Idempotent: only applies files not in the table.
- Local + staging + production all migrate via the same runner. Same SQL on every environment.
- A migration that changes a column or drops data must be reviewed twice — once when written, once before running on production.
- No PHP in migration files. SQL only. (If we need PHP-driven data fixup, add it as a one-off script in `db/scripts/` and document.)

---

## 14. Comments & docstrings

- File header: 3-line block at the top of every PHP/CSS/JS file:
  ```php
  /**
   * lib/content.php — Article + journal + live-session + experiment data access.
   * @see ENGINEERING.md §4.3 for the one-function rule.
   */
  ```
- Function-level: a one-line docblock if the function name is non-obvious. Don't paraphrase the function name.
- Inline comments: rare. Comment *why*, never *what*.

---

## 15. Testing approach (v1, lightweight)

We are not setting up PHPUnit for v1 — single-author site, dev tested by exercising. But:

- Every phase has a Verification list in `BUILD-PLAN.md`. Walk through it manually before declaring a phase done.
- `tests/manual/` folder holds plain-text walkthroughs for hard-to-remember flows ("how to verify scheduled publish actually works"). Update as we discover gotchas.
- If a bug is found and fixed, add a one-line entry to `tests/manual/regression-checks.md` describing what to verify so we don't reintroduce it.

If the site grows beyond v1, add PHPUnit and proper integration tests then.

---

## 16. The Claude operating manual

This section is written specifically for a Claude instance working in this repo (via Claude Code in VS Code, or otherwise).

### 16.1 Before writing any code

1. Read `CLAUDE.md`.
2. Read `BUILD-PLAN.md` and identify the current phase from §3.
3. Skim this file (`ENGINEERING.md`) for any rules touching the area you're about to work in.
4. Confirm the work you're about to do is *listed in the current phase's Deliverables*. If it isn't, stop and ask.

### 16.2 While writing code

- Match existing style. If five files in a folder use a pattern, the sixth uses it too. Don't introduce a new pattern silently.
- Use the helpers that exist (`e()`, `csrf_field()`, `log_error()`, `accept_upload()`, the `lib/*.php` data-access functions). If a helper doesn't exist but should, create it and document it — don't inline the logic in three places.
- Touch only the files the task needs. Don't drive-by-refactor. Refactors get their own phase or commit.
- No new dependencies. No `composer require`. No `<script src="https://cdn…">` other than Tiptap and ProseMirror in Phase 6.

### 16.3 When unsure

- If the spec is silent, **ask**. Don't invent.
- If you find a contradiction between `CMS-STRUCTURE.md`, `BLOCKS.md`, and `cms-ui.html` (the three coupled files per `CLAUDE.md`), stop and surface it. Don't pick a side silently.
- If a rule here seems wrong for a specific case, raise it as a proposed exception in this file rather than just breaking the rule.

### 16.4 What "done" looks like

A unit of work is done when:
1. The deliverable listed in `BUILD-PLAN.md` exists at the expected path.
2. It follows every rule in this document.
3. The phase's verification step for that deliverable passes.
4. `CLAUDE.md` / `BUILD-PLAN.md` / `CMS-STRUCTURE.md` are updated if the work changed anything documented there.

### 16.5 What to never do

- Never edit a past migration.
- Never put SQL outside `lib/`.
- Never put raw hex outside `tokens.css`.
- Never add an external dependency without an explicit instruction in `BUILD-PLAN.md`.
- Never silently rename a slug, a CSS class, a token, an endpoint, a function, a table, or a column.
- Never commit `config/config.*.php` (except `config.example.php`).
- Never commit anything in `uploads/`, `content/`, `logs/`, or `backups/`.

---

## 17. The .gitignore

For reference, the minimum .gitignore at repo root:

```
# Local config — secrets live here
config/config.local.php
config/config.staging.php
config/config.production.php

# Runtime artifacts
uploads/
content/
logs/
backups/

# OS / editor noise
.DS_Store
.vscode/
.idea/
*.swp
*.swo
node_modules/  # in case anything ever gets installed locally
```

---

## 18. Changelog

- **2026-05-11** — Initial draft. Captures the rules established during Phase 0 planning.
