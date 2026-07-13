# Résumé — how it works, and what we're building

Plain-English reference for the CMS **Library → Resumes** system. Read this before
building or changing anything in the resume editor.

Status: **spec agreed, not yet built.** Written 2026-07-01.

---

## 1. What this covers

A single-author résumé managed inside the CMS. The résumé has its own HTML + isolated
stylesheet (no design system dependency), a publish lifecycle identical to Pages, a
snapshot archive on every publish, and a PDF Exports tab for storing exported PDFs.

**MVP = one résumé record.** V1 extends this to a list of named résumés (see §7).

---

## 2. Where it lives

**Sidebar:** a new **Library** section at the bottom of the CMS nav, with **Resumes**
as the first item. Library is the container for V1 growth (other asset types TBD).

Clicking **Resumes** goes directly into the editor — no list screen in MVP.

---

## 3. Editor structure

Three tabs across the top: **Edit · Config · PDF Exports**.

### Edit tab (default)

Default landing pane is **Live** (the published résumé). Pane switcher within the tab:
**HTML · Style · Draft Preview · Live** — identical behaviour to the Pages editor.

- **HTML pane** — the résumé body. Full document (not a fragment — résumé is a
  standalone page, not assembled through a shell).
- **Style pane** — the résumé's own stylesheet. Completely isolated; no design-system
  tokens, no shared CSS. Every version of the résumé carries its own styles.
- **Draft Preview** — renders the current draft (HTML + Style) in an iframe.
- **Live pane** — renders the published version. **Empty state:** "No published version
  yet — edit and publish to see a live preview here."

**Publish flow:** same as Pages — save draft (autosave + explicit save), then publish.
Every publish creates a snapshot in the archive (see §4).

### Config tab

Copied from Pages config. No customization required in MVP; present for structural
parity and future use.

### PDF Exports tab

See §5.

---

## 4. Snapshot archive

Every publish saves a full snapshot (HTML + Style) as a restorable archive entry.
Behaviour and UI mirrors the Pages archive exactly — same pattern, no new conventions.

---

## 5. PDF Exports tab

The résumé's PDF history. Intended workflow: author exports the résumé to PDF via
Cmd+P → Save as PDF in the browser, then uploads the file here.

### Upload

- Drag-and-drop zone at the top of the tab.
- On drop: reads the **file's created date** from metadata and saves it alongside the
  file.

### Table

Sorted by **date**, newest first. Columns:

| Column | Notes |
|---|---|
| Date | Displayed as `MM/YY`. Pre-populated from file metadata on upload. Inline-editable — clicking prompts for `MM/YY` input. Autosaves on blur with a "Saved" indicator. |
| Note | Inline-editable; autosaves on blur with a "Saved" indicator (same pattern as Redirects) |
| Download | Link to the stored PDF file |
| Delete | Confirmation required before deletion |

No reordering — date is the canonical sort.

### Storage

PDFs stored under `site/cms/uploads/resumes/` on the server. Also committed to the
repo under `docs/_cv/pdf-exports/` — synced back from the server as part of the
deploy process (see §6).

### Seed (setup task)

The five existing PDFs in `docs/_cv/pdf-cv/` are manually uploaded to the PDF Exports
tab on both **staging and prod** as part of the initial build. File created dates are
read from metadata.

---

## 6. Deploy additions

`bin/deploy.sh` gets one additional step: after the rsync push, pull the server's PDF
folder back down to the local mirror:

```
rsync -a alexmchong@server:<webroot>/cms/uploads/resumes/ docs/_cv/pdf-exports/
```

This keeps a local copy in the repo. PDFs are committed normally (scale is ~20 files,
repo impact is negligible).

---

## 7. Public routes

- `/resume/` — serves the published résumé (latest publish).
- No per-version public URLs in MVP.

---

## 8. Seed record

`docs/_cv/html-cv/index.html` becomes the initial HTML draft. Before promoting:

- `resume.css` and `print.css` are **merged into a single CSS file** — screen styles
  and `@media print` rules together. This is the Style field content.
- **Background image** — replaced with a reference to the DS/site's background asset
  (served from the live site, no local copy needed).
- **Favicon** — replaced with the site favicon URL (same reason).

The sandbox (`docs/_cv/html-cv/`) remains split during editing and cleanup. Merging
happens at promotion time, just before building the CMS editor.

---

## 9. V1 additions (noted, not specced)

- **Multiple named résumés** — editor becomes a list view (like Pages), each résumé
  is its own record.
- **Date override on upload** — drag-upload prompts with the inferred date before
  saving (less pressing in V1 since the date is already inline-editable after upload).
- **Reordering** — if multiple résumés or PDF ordering becomes meaningful.
- **Automated PDF generation** — server-side or headless, replacing the manual
  Cmd+P workflow.

---

## 10. What to archive when done

Once the résumé editor is confirmed live in prod:

- Move `docs/_cv/` → `docs/design-mockups/_completed/` (sandbox material, no longer
  the working reference).
- This spec (`docs/RESUME-SYSTEM.md`) becomes the canonical reference going forward.
