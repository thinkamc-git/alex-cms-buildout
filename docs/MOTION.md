# Motion — CMS load-reveal spec

Implementation contract for the CMS load animation — content fades-and-rises into
place on page load instead of snapping in fully formed. Tuned against the real
admin chrome in the `docs/motion-mock.html` sandbox (archived once this spec is
built).

**Status:** locked, ready to implement. **Scope:** CMS admin (`site/cms/`) only.

**Every page animates — there is no "dead" page.** The axis is not animated-vs-not,
it's *which* motion:

- **Cascade** — elements arrive staggered, one-by-one. For surfaces you **scan**
  (boards, tables, lists). The sequence guides the eye.
- **Unified rise** — the whole panel lifts in **together**, no stagger. For
  surfaces you **work on** (editors, Settings, any form). Alive, but instantly
  usable — staggering form fields would make the first field move/wait while
  you're trying to click it.

---

## 1. The locked animation

These numbers were dialed in by eye against the live CMS. Do not re-tune without
re-opening the sandbox.

**Shared across all archetypes:**

| Property        | Value                              | Note                          |
|-----------------|------------------------------------|-------------------------------|
| Duration        | `580ms`                            | per element                   |
| Stagger step    | `60ms`                             | delay between siblings        |
| Easing          | `cubic-bezier(0.22, 1, 0.36, 1)`   | easeOutQuint — soft landing   |
| Stagger cap     | `6` (index 0–5, then flat)         | tail siblings share last beat |
| Scope           | per-group (`nth-child` resets)     | **no JavaScript required**    |

**Per-archetype — motion + rise distance are NOT universal:**

| Archetype                  | Motion         | Rise distance        | Why                                   |
|----------------------------|----------------|----------------------|---------------------------------------|
| Cards / boards / stats     | Cascade        | `6px` (`--rise: 6px`)| chunky blocks — bigger lift reads well |
| Data tables (rows)         | Cascade        | `3px` (`--rise: 3px`)| thin, dense rows — more would feel floaty |
| Row-forms (Categories, Nav, Redirects) | Cascade | `3px` (`--rise: 3px`)| table-like; rows cascade despite being editable/draggable |
| Forms / editors            | Unified rise   | `8px` (whole panel)  | one calm motion, no field-by-field stagger |

The cascade distance is carried by a `--rise` custom property set on the container
and inherited by the animating children, so a single keyframe serves every
cascade archetype. Adding a new cascade archetype = one `--rise` override.

### The CSS (drop in verbatim)

```css
/* ─────────────────────────────────────────────────────────────
   LAYER 8 — Load reveal (see docs/MOTION.md)
   Progressive cascade: content blocks fade + rise on page load.
   Per-group scope → nth-child resets per container → no JS.
   Rise distance varies per archetype via --rise (cards 6px, rows 3px).
   ───────────────────────────────────────────────────────────── */
@keyframes rise-in {
  from { opacity: 0; transform: translateY(var(--rise)); }
  to   { opacity: 1; transform: translateY(0); }
}

/* per-archetype lift — set on the container, inherited by children */
.reveal                 { --rise: 6px; }   /* cards / boards / dashboard stats */
.cms-table tbody.reveal,
.rowform-list.reveal    { --rise: 3px; }   /* data tables + row-forms — shorter */

.reveal > * {
  opacity: 0;
  animation: rise-in 580ms cubic-bezier(0.22, 1, 0.36, 1) both;
}
.reveal > *:nth-child(1)   { animation-delay:   0ms; }
.reveal > *:nth-child(2)   { animation-delay:  60ms; }
.reveal > *:nth-child(3)   { animation-delay: 120ms; }
.reveal > *:nth-child(4)   { animation-delay: 180ms; }
.reveal > *:nth-child(5)   { animation-delay: 240ms; }
.reveal > *:nth-child(n+6) { animation-delay: 300ms; }

/* UNIFIED RISE — forms / editors: the whole panel lifts in together, no stagger.
   Apply .reveal-page to the form/content wrapper (one element, one motion). */
@keyframes rise-in-block {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.reveal-page {
  opacity: 0;
  animation: rise-in-block 580ms cubic-bezier(0.22, 1, 0.36, 1) both;
}

@media (prefers-reduced-motion: reduce) {
  .reveal > *, .reveal-page { animation: none; opacity: 1; transform: none; }
}
```

**Home:** append as `LAYER 8` to
[site/cms/_assets/style-cms.css](../site/cms/_assets/style-cms.css) (after the
`.cms-live-dot` block, ~line 1759). That file is already linked from every CMS
`<head>`, so one edit ships it everywhere — no per-view `<link>` changes.

**Why `both` matters:** `animation-fill-mode: both` holds the `from` state
(`opacity:0`) during the delay, so a staggered element stays invisible until its
turn instead of flashing visible-then-hidden. Required for the cascade to read
cleanly.

---

## 2. Where `.reveal` goes

A container opts in by gaining the class `reveal`; its **direct children** are
what cascade. Pick the container whose direct children are the repeating content
units (cards, rows, stat cells) — not a wrapper one level too high (you'd
animate one big block) or too low (nothing to stagger).

### Highest-leverage application — shared partials (do these first)

| Apply to | File | Covers |
|---|---|---|
| `class="reveal"` on `<tbody>` | [site/cms/partials/table.php](../site/cms/partials/table.php) | **Every table-partial list view** — Articles, Journals, Live Sessions, Experiments, Subscribers, Categories, Series, Indexes, Pages. One edit, rows cascade everywhere. |

Add it to the `<tbody>` open tag in `table.php`. The `tr` rows become the
staggered children automatically.

> **Navigation and Redirects don't use the table partial** — they hand-roll
> `.rowform-list` markup. Add `class="reveal"` to the `.rowform-list` element in
> [navigation.php](../site/cms/views/navigation.php) and
> [redirects.php](../site/cms/views/redirects.php) directly. The `.rowform-row`
> children cascade. They're editable/draggable, but the reveal is a one-time load
> animation (same as the draggable kanban cards) — it doesn't touch the drag hook.

> **Editors don't cascade — they get the unified rise.** Forms (`*-edit.php`,
> `*-new.php`, Settings) must NOT stagger field-by-field. Instead add
> `class="reveal-page"` to the form/content wrapper (e.g. the `.cms-form` element,
> or the `.content-area`) so the whole panel lifts in as one motion. Alive and
> consistent with the rest of the CMS, but instantly usable. Never put `.reveal`
> (the cascade) on a form.

### Per-view application (board + dashboard views)

| View | File | Containers to mark `reveal` |
|---|---|---|
| Draft Writing (kanban) | [site/cms/views/pipeline.php](../site/cms/views/pipeline.php) | `.dash-meta` **and** each `.lane-cards` (cascade) |
| Ideation Board | [site/cms/views/ideation.php](../site/cms/views/ideation.php) | each lane's card container (`.lane-cards` / equivalent) (cascade) |
| All editors / Settings | `*-edit.php`, `*-new.php`, `settings.php` | `class="reveal-page"` on the `.cms-form` wrapper (unified rise) |

Each `.lane-cards` is its own reveal group, so all lanes cascade their cards in
parallel on load — the whole board "assembles." `.dash-meta`'s stat cells cascade
left-to-right.

> **One caveat for the kanban:** the drag-and-drop hook
> ([dragdrop.js](../site/cms/_assets/dragdrop.js)) re-parents cards. The reveal is
> a pure CSS load animation — it runs once on page render and never re-fires, so
> it does not interfere with dragging. Verify a card dropped into a new lane does
> NOT replay the animation (it shouldn't — no class is re-added).

---

## 3. Companion pieces (recommended, not yet tuned)

The cascade handles *content arriving*. Two foundations handle the other half of
"not jittery" — they're `<head>`/server-level, judged by correctness not by eye,
so they weren't in the sandbox. **Recommendation: ship §3.1 with the cascade;
treat §3.2 as a fast follow.**

### 3.1 — Anti-layout-shift (foundation — do alongside §1)

Half of perceived "jitter" is content jumping as fonts/images load, not missing
animation. Without this, the cascade animates *into* a shifting layout.

- **Fonts:** the CMS `<head>` already uses Google Fonts with `display=swap`.
  Keep it; optionally add `<link rel="preload">` for the two primary faces
  (Barlow 400/600) to cut the swap reflow.
- **Images** (experiment thumbnails, hero previews): ensure every `<img>` carries
  explicit `width`/`height` or an `aspect-ratio`, so rows don't reflow as images
  decode.
- **Reserve async regions:** any container whose height depends on data should
  carry a `min-height` so the reveal doesn't animate into a collapsing box.

### 3.2 — Navigation crossfade (polish — fast follow)

Every CMS nav click is a full PHP page reload → a white flash. The MPA View
Transitions API crossfades between page loads with three lines of CSS, no JS:

```css
@view-transition { navigation: auto; }
```

**Test interaction with the cascade before shipping:** the crossfade animates the
old→new page swap; the reveal then plays on the new page. Stacked, it may feel
like too much motion. Options, in order of preference:
1. Keep both, but verify the combined feel on a slow nav.
2. If it's too busy, suppress the reveal on view-transition navigations and let
   the crossfade carry the page swap (reveal still fires on a cold load / refresh).

Unsupported browsers fall back to the current instant reload — graceful, no
polyfill.

---

## 4. Acceptance checklist

- [ ] LAYER 8 appended to `style-cms.css`; cascade visible on a cold load of
      Draft Writing and any table view.
- [ ] `.reveal` on `table.php` `<tbody>` — rows cascade in every list view.
- [ ] `.reveal` on `.dash-meta` + each `.lane-cards` in pipeline.php (+ ideation).
- [ ] `.reveal-page` on every editor / Settings form — whole panel lifts in
      together, no field-by-field stagger; first field clickable immediately.
- [ ] No form ever carries `.reveal` (the cascade).
- [ ] Dropping a kanban card does not replay the animation.
- [ ] `prefers-reduced-motion: reduce` (OS setting) → content appears instantly,
      no transform, no flash.
- [ ] No layout shift: rows/cards animate into a stable layout (§3.1).
- [ ] (If §3.2 shipped) navigation crossfade reads smoothly with the cascade.

---

## 5. Provenance

Tuned in `docs/motion-mock.html` (temporary sandbox, references live CMS
stylesheets). Archive or delete that file once this spec is implemented — it is
not deployed (`docs/` never ships).

