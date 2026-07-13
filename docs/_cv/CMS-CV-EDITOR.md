# Résumé Editor — context & spec

Covers two things: **(1)** the `docs/_cv/` source folder, and **(2)** the built
CMS editor at `/cms/resumes`. Written 2026-07-02 to replace the pre-build vision
doc. **The editor is live on staging** — this is the current state, not a vision.

---

## Part 1 — `docs/_cv/` source folder

```
docs/_cv/
├── CMS-CV-EDITOR.md   ← this file
├── README.md          full inventory + modernization record
├── html-cv/           ← authoritative résumé source (Aug 2024 content)
│   ├── index.html     semantic HTML · cv-* classes · minimal <head> · Barlow Google Font · CSS fade-in
│   └── assets/
│       ├── resume.css        ~8 KB screen styles
│       ├── print.css         @page margins, white bg, break-inside: avoid
│       ├── favicon.png
│       └── background2x.png
├── pdf-cv/            printed CV archive: 2009 · 2009 · 2012 · 2012 · 2024
└── pdf-exports/       working folder for PDFs to upload into the CMS PDF Exports tab
```

`docs/` is never deployed. When styling or copy changes, edit the source here,
then push to staging via the update script pattern (see §Production migration below).

**The one principle:** a résumé *is* its print output. `print.css` is first-class.
Any change to the editor or public view must keep `Cmd+P → Save as PDF` faithful.

---

## Part 2 — CMS editor (built, live on staging)

### Routes

| Method | Path | Handler |
|--------|------|---------|
| GET/POST | `/cms/resumes` | `site/cms/views/resume-edit.php` |
| POST | `/cms/resumes/autosave` | `resume-autosave.php` |
| POST | `/cms/resumes/preview` | `resume-preview.php` |
| POST | `/cms/resumes/pdf/upload` | `resume-pdf-upload.php` |
| POST | `/cms/resumes/pdf/delete` | `resume-pdf-delete.php` |
| POST | `/cms/resumes/pdf/save` | `resume-pdf-save.php` |
| GET | `/cms/resumes/pdf/:id/view` | `resume-pdf-view.php` |
| GET | `/cms/resumes/pdf/:id/download` | `resume-pdf-download.php` |
| GET | `/resume/` | `resume-public.php` (public, no CMS shell) |

CMS sidebar: **Résumé** (singular) under the Library section.

### Tab structure

```
[ Page ] [ Edit ] [ Configure ] [ PDF Exports ]
```

- **Page** — live iframe at `/resume/`. Only visible when published; empty state otherwise.
- **Edit** — draft editor. Sub-pills: `HTML` · `Style` · `Preview` · `Live version`.
  Defaults to HTML pane. Save button autosaves draft_html + draft_css.
- **Configure** — meta title, public URL display (`/resume/`), publish action.
  Subtitle: "Draft → Publish → /resume/ · Deploy pushes to production."
- **PDF Exports** — drop zone + PDF table (date `YYYY-MM`, note, View ↗, Download, delete).

### Data model

Table `resumes` (single row, id = 1):

| Column | Type | Notes |
|--------|------|-------|
| `id` | int | always 1 |
| `title` | varchar | display title |
| `meta_title` | varchar | `<title>` tag |
| `draft_html` | longtext | full HTML document (CSS stored separately) |
| `draft_css` | longtext | screen + print CSS combined |
| `published_html` | longtext | folded document (draft_css injected into `<head>`) |
| `is_published` | tinyint | 0 / 1 |
| `last_published` | datetime | |
| `updated_at` | datetime | |

Table `resume_snapshots` — every publish archives the outgoing version (html_body + style_css separately).

Table `resume_pdfs` — uploaded PDFs with `pdf_date` (`YYYY-MM`), `note`, `filename`. Ordered `pdf_date DESC`.

### Publish flow

1. Autosave writes `draft_html` + `draft_css` to `resumes` row.
2. Publish (POST `/cms/resumes`) calls `publish_resume()`:
   - Snapshots current `published_html` into `resume_snapshots`.
   - Calls `_resume_inject_style(draft_html, draft_css)` — inserts `<style>` block before `</head>`.
   - Writes folded result to `published_html`, sets `is_published = TRUE`.
3. `/resume/` serves `published_html` verbatim (no CMS shell).

### PDF Exports UX

- Drop zone: `2px dotted var(--ink-30)`, always visible. Dragover → 12% forest tint.
- On select/drop: filename shown in label. Manual "Upload" button confirms (not auto-upload).
- *(Item C — in progress):* staged upload flow with explicit Upload button, success indicator, table refresh.
- Date input: `YYYY-MM` format, auto-formatted on input. Blur-saves row inline.
- Buttons: **View ↗** (new tab) · **Download** · trash icon (delete with confirm).
- "Saved" tag appears briefly after a successful inline blur-save.

### Button & styling conventions

- External/new-tab links: `btn-sec btn-tiny` + label + `↗` (e.g. "View ↗").
- Delete: `btn-icon btn-icon-danger` + trash SVG.
- Borders: `var(--ink-30)` for visible, `var(--ink-18)` for subtle. `var(--border)` does not exist.

---

## Part 3 — marketing page at `/cv/`

The old `/resume/` marketing page moved to `/cv/` to free the slug for the CMS
résumé builder.

| Path | Serves |
|------|--------|
| `/cv/` | `cv.php` → `_pages/_bodies/cv.html` (marketing page) |
| `/resume/` | `resume-public.php` (CMS-served résumé) |

`.htaccess` maps `^cv/?$` → `/cv.php` via `[END]`. The front-controller fallback
catches `/resume/` and hands it to `index.php` → `resume-public.php`.

---

## Part 4 — production migration (when ready)

Sequence is order-sensitive:

1. **Run migration** `site/db/migrations/0038_resumes.sql` on prod DB.
   Creates `resumes`, `resume_snapshots`, `resume_pdfs` tables + seeds row id=1.

2. **Deploy** `bin/deploy.sh prod --confirm`.
   Pushes `.htaccess` (new `/cv/` + `/resume/` rules), all lib/view/config files.

3. **Remove stale `resume.php`** from prod webroot — `protect /*.php` rsync filter
   prevents `--delete` from catching it. Must be deleted manually via SSH:
   ```
   ssh alexmchong-ca "rm ~/alexmchong.ca/resume.php"
   ```
   Without this step, the CMS-created-pages rule serves the old marketing page at
   `/resume/` instead of the front controller.

4. **Seed prod DB** with résumé content — same update-script pattern used on staging:
   write a one-shot PHP file locally, rsync it, run via
   `APP_ENV=production /usr/local/bin/php-8.2 script.php < /dev/null`, delete it.
   Source of truth: `docs/_cv/html-cv/` (current edited version).

5. **Upload PDFs** to `uploads/resumes/` on prod + register in `resume_pdfs` table
   via the `/cms/resumes?tab=pdfs` UI (or a seed script for the dates/notes).

6. **Verify**: `/cv/` (marketing page) · `/resume/` (built résumé) · `/cms/resumes` (editor).

> **Done 2026-07-02.** All six steps completed. The old `/cv/ → /resume/` redirect in the
> `redirects` table was manually removed via `/cms/redirects` before cutover to avoid a loop.
>
> **Cleanup pending:** 5 orphaned PDF files in `alexmchong.ca/uploads/resumes/` — filenames
> starting with `alexanderchong-cvweb-*`. These were pre-uploaded via SSH and superseded by
> the PDFs uploaded through the CMS UI (which have their own generated names + DB rows).
> Delete via DreamHost File Manager when convenient. Safe to ignore indefinitely.

### SSH pattern (DreamHost)

DreamHost SSH requires `-T` and `< /dev/null` for non-interactive PHP execution:
```bash
ssh -T -o ConnectTimeout=120 alexmchong-ca "cd staging.alexmchong.ca && APP_ENV=staging /usr/local/bin/php-8.2 script.php < /dev/null"
```
Without `< /dev/null`, PHP waits for stdin and the connection times out.
