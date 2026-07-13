# _cv — CV / résumé source archive (working area)

Recovered from the old pre-CMS site backup (`_backups/alexmchong.ca-2026-05-13/`)
on 2026-06-30. This is the raw material for the future **Library → Resumes**
CMS feature — every generation of the résumé, gathered in one place.

> **Working/sandbox material — never deployed.** Everything under `docs/` is
> reference material and doesn't ship. When we build the CMS feature, the real
> assets move into `site/` deliberately.

## The generations (oldest → newest)

| Folder / file | Date | Format | Notes |
|---|---|---|---|
| `pdf-cv/AlexanderChong-cvweb_20090227.pdf` | Feb 2009 | PDF | Earliest recovered CV |
| `pdf-cv/AlexanderChong-cvweb_20091125.pdf` | Nov 2009 | PDF | |
| `pdf-cv/AlexanderChong-cvweb_20120217.pdf` | Feb 2012 | PDF | |
| `pdf-cv/AlexanderChong-cvweb_20120404.pdf` | Apr 2012 | PDF | Latest of the PDF era |
| `pdf-cv/AlexanderChong-cvweb_20240813.pdf` | Aug 2024 | PDF | Current résumé, printed from `html-cv/` (generated 2026-06-30) |
| `html-cv/` | **Aug 2024** | Hand-cleaned HTML/CSS | **The current résumé — the source to carry forward.** `index.html` + `assets/`. |

### `html-cv/` structure

```
html-cv/
  index.html          semantic HTML · cv-* classes · minimal <head> · css2 Barlow
  assets/
    resume.css        ~8 KB — screen styles (0 comments, no vendor cruft)
    print.css         print/PDF rules — @page margins + page-break control
    favicon.png
    background2x.png
```

**Modernized from a Webflow export (2026-06-30 → 07-01).** It arrived as ~109 KB
of CSS across 3 files plus a full Webflow JS runtime; now it's hand-clean:
- **De-Webflowed** — removed the IX2 runtime (`resume.js`, ~180 KB), jQuery, the
  `w-node` grid ids, `data-w-*` attributes, the `generator` meta, and WebFont.js.
- **CSS** purged → merged → renamed to a semantic `cv-` system: **~109 KB → 8 KB**,
  0 comments, no `-ms-`/old-flexbox prefixes; grid placement folded into classes.
- **Fonts** load via a modern `css2` `<link>` (Barlow); the **fade-in** is pure CSS.
- **Print** lives in a dedicated `print.css` — `@page` margins, forced white
  background, `break-inside: avoid` so entries don't split across pages (verified
  by generating the archived 2024 PDF).
- Content verified **1:1** against the pristine original — zero loss.

> **`index.html` (2024) vs `index0.html` (2021):** same Webflow template; the 2024
> file is a content refresh — adds the **Director of UX, Hudson's Bay / Saks Global
> (Dec 2022–present)** role, reframes as "design leader & product strategist",
> 12+ yrs, completed M.Des, expanded consulting. `index0.html` was the prior state.
>
> Removed as outdated: `hand-built/` (~2012), `webflow-resume2/` (Jul 2019),
> `index0.html` (2021), the `cx/` variant (the 2019 CX-optimized cut — same
> career content, reordered). All remain in the server backup at
> `_backups/alexmchong.ca-2026-05-13/cv/_archive/` (and `/cv/`) — re-pullable.

## How to read this

- **The PDFs are the actual output** — historically Alex authored the résumé and
  printed/exported to PDF; that PDF *is* the CV. So the print view is a
  first-class concern, not an afterthought.
- **`html-cv/index.html` (2024)** is the current version — the starting point for
  the résumé work.

## Looking at the files

Open them directly from the repo — `docs/_cv/…`. The PDFs open in any viewer;
the HTML pages open in a browser (Cmd+P to check their print layout). If we later
want them served with live styling, run `python3 -m http.server 8765 --bind
127.0.0.1` from the repo root and browse `/docs/_cv/…` — but that isn't needed
just to read them.

This lives under `docs/` (reference/working, never deployed) for now; when we
build the CMS feature the real assets move into `site/` deliberately.

## Next

Design the **Library → Resumes** CMS feature (versions, drafts, print view,
downloadable PDF links) as a spec — same way `docs/PAGES-SYSTEM.md` was written —
before any build.
