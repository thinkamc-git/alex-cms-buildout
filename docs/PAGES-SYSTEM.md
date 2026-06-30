# Pages — how they work, and what we're building

Plain-English reference for the CMS **Pages** system. This is the shared source of
truth: how the standalone site pages (about, coaching, landing, resume, error pages,
header/footer) are managed, and the plan for turning today's special-cased plumbing
into one coherent system. Read this before changing Pages.

Status: **design agreed, not yet built.** Decisions are recorded here; open ones are
flagged at the end.

---

## 1. What "Pages" covers

The hand-built, standalone pages of alexmchong.ca — the ones that aren't CMS *content*
(articles, journals, etc.). Today: `about`, `coaching`, `landing`, `resume`,
`work-with-me`, `newsletter`(+confirmed), the `404` error page, and the `header` /
`footer` partials. Each is a file on disk; the CMS edits a **draft** of it and can
**publish** it back to the file.

---

## 2. Three kinds of things — grouped by what you *do* with them

The organizing principle is the **verb**, not how unusual something is:

1. **Pages** — things you *write & publish*. Marketing pages **plus Home (landing)**.
   Same lifecycle for all of them: draft → preview → publish.
2. **Error pages** — things you *configure*. One shared template + a short message per
   error code. No per-page draft/publish lifecycle. (See §6.)
3. **Partials** — header / footer. Embedded into every page; published at runtime
   (a flag, not a file write). Left as-is for now.

Landing is **not** pulled out with error pages, even though both are "special": landing
is *written and published like a page*, so it belongs with Pages. Error pages are
*configured*, so they get their own surface.

---

## 3. Every page has a **type**, and the type decides its address

Instead of hard-coding exceptions in the code (today `landing → /` is special-cased in
one place and wrong in two others), each page declares a **type** in one registry, and
everything reads its address from there.

| Type | Address | Editable? | Deletable? |
|---|---|---|---|
| **Standard** (about, coaching…) | `/{slug}/` | yes (see below) | yes |
| **Home** (landing) | `/` | no — locked, shown disabled | **no** |
| **Error** (404…) | none — *shown on* an HTTP code | n/a | **no** |
| **Partial** (header, footer) | none — embedded | n/a | **no** |

**Creating pages:** the CMS "New page" button only ever makes a **Standard** page
(`/slug/`). **Home and Error pages are special and are created only by agent work** (us,
together) — never through the UI — and they **cannot be deleted or archived.**

**The address field behaves by state:**
- **Before a page is published:** the address is freely editable (you're just naming it).
- **After it's published:** changing the address becomes a deliberate "Change address"
  action that **auto-creates a 301 redirect** from the old URL — because a published
  URL is a promise (someone may have linked it).
- **Home** always shows `/`, disabled, with a note that the home page lives at the root.

---

## 4. Styling: design system first, with a small, visible escape hatch

**The rule:** anything substantial or reusable lives in the **design system**
(`/_ds/css/public/pages.css`) and ships via a deploy. The CMS publishes *content*; the
design system owns *styling*.

**The escape hatch:** a genuine one-off flourish (e.g. a spinning profile photo) that
should *never* pollute the design system goes in that page's **Style** field —
a per-page CSS override that:
- is a **separate field** (never CSS jammed into the body, never auto-parsed from HTML),
- is **scoped to that one page** (only loads when that page renders),
- is **flagged in the Pages list** ("custom CSS") so overrides are always visible,
- **travels with the page on publish** (no deploy needed).

**The editor** reflects this: the Draft editor's toggle row becomes **HTML · Style ·
Preview · Live**, where HTML and Style are two editable panes you can open side by side
(same multi-pane logic the preview panes already use).

**The DS-coverage check:** the editor scans the HTML for any class **not defined in the
design system** and flags them — e.g. *"3 classes aren't in the DS: `.xp-theme`, … —
promote to the DS, or move to Page styles."* This forces a conscious choice every time,
so undefined styling never ships silently.

**Important distinction:** "not in the DS yet" does **not** mean "belongs in the Style
override." A reusable pattern that simply hasn't been promoted yet belongs **in the DS**
— go promote it. The Style field is only for things you'd *never* want shared. As the
Style field fills up over time, that's the **signal to sit down together and promote**
(and rename — e.g. `.xp-theme` is a placeholder name to fix at promotion time).

---

## 5. How a page is made and changed (the working loop)

**Lifecycle:** **Draft → Preview (full assembled page) → Publish.**

- **Agent boundary:** when Alex and the assistant work a page together, the assistant
  touches **draft + style only — never publish.** Alex publishes.
- **Full-page assembled preview:** a draft can be viewed rendered through the real shell
  — header, footer, body, **and its scoped Style override** — at a preview URL, *without
  publishing*. This is the shared sandbox: you're looking at the real assembled page,
  constructed properly, not a throwaway file. (Half-built already: the editor Preview
  pane + the staging `?_preview=<id>` route; extend it to include the Style override.)
- **Draft contract** (so the assistant can construct drafts correctly, every time):
  - **body** = a *fragment* only — starts at `<main…>`, no `<!doctype>/<html>/<head>`.
  - **style** = the Style override field (its own CSS), not inline in the body.
  - plus **slug / type / metadata**.
- **A clean write path:** a documented CLI/endpoint to set a draft's body + style for a
  slug on a given environment — so agent-assisted drafting is safe and repeatable, not
  ad-hoc one-off scripts.

---

## 6. Error pages (the configure surface)

- **One branded template** (the design, the "← back home" button, the dot-grid) edited
  with the full page-editing experience — same editor, components, preview.
- **A Messages panel** beside it: one row per HTTP code (**404, 403, 500**), each with a
  **default** message (a **headline + optional sub-line**) you can **override**, plus a
  plain note on when that code fires, and a preview-with-code toggle.
- **Fragment + shell** from day one (no standalone full-document `404.php`) — which also
  removes the "the 404 shows the whole page doubled" bug at the root.
- Served by Apache `ErrorDocument` pointing at one small handler that renders the
  template with the right message for the code.
- Lives in its **own sidebar surface** (it's a distinct place you visit), not inside the
  Pages list.

---

## 7. What we're building (phases — each shippable on its own)

- **P1 — Page-type registry + one address function.** A single source of truth for each
  page's type and public URL (`page_public_url($slug)`), read everywhere. Fixes the
  `/landing/` bug in all three spots (editor, list "Live" link, preview URL).
- **P2 — Editor: HTML + Style panes + DS-coverage flag.** Scoped per-page style
  rendering; the undefined-class check.
- **P3 — Pages list + address field.** Home pinned at top, locked, non-deletable; the
  state-dependent Address control.
- **P4 — Error pages surface.** Template editor + Messages config + `ErrorDocument`
  wiring; convert `404` to a fragment, add `500` and `403`.

**About + landing are the validation pages** — held as drafts and run *through* this
system (P2 onward) rather than finished the old way and re-migrated. About's themed CSS
lands in the Style override, then we promote + rename it properly.

---

## 8. Decided

- Error messages = **headline + optional sub-line** (both defaulted).
- Error codes to ship = **404 / 403 / 500** (built so adding one later is one row).
- "Error pages" = **its own left-menu item** (not buried in Settings).
- Page types are **fixed** — no reassigning in the UI. New pages are always Standard;
  Home / Error are agent-created and protected from delete/archive.

## 9. Resolved (design review)

- **Style override = fold on publish.** The HTML field and the Style field are the
  *source of truth* — stored separately, edited in separate panes. **Publish** glues them
  into one self-contained file (fast to serve, no extra lookups). **Re-editing reopens
  the two separate fields** — the folded file is regenerated output, never parsed back
  apart. (Separated = canonical; folded = generated.)
- **Address/slug = editable, but guarded.** Changing a page's address pops a confirmation
  ("this may affect links pointing here") and **auto-creates a 301 redirect** from the old
  address to the new one, so existing links don't break.
- **Error pages = baked to static.** On save, each error page is written out as plain
  static HTML; the server points the codes (especially **500**) at those static files, so
  they still render even if the database/PHP is down. (Built in P4.)
- About's themed styles **stay with About for now**; promote + rename into the design
  system later — not a blocker.

---

## Appendix — current state & cleanup (Phase A)

- **Snapshot taken** (2026-06-28): full `page_mock_versions` (JSON + restorable SQL) +
  a `_bodies`/`_layout` tar, under `/home/alexmchong/_backups/pages-snapshot-<env>-<ts>/`,
  both environments. Everything below is reversible.
- **Prod drafts audited:** all *page* drafts are clean fragments; integrity is fine. To
  remove: the `404` full-document draft (the doubled-HTML culprit, can't be published)
  and a redundant duplicate `about` draft. Keeping: the v8 about draft (to publish), the
  landing snapshot, and the header/footer/landing leftovers (harmless).
- **To retire:** the temporary staging `about-preview.php` (+ its `deploy.sh` line),
  superseded by the CMS draft; and archive the about/landing sandboxes once their work
  has landed through the system.
