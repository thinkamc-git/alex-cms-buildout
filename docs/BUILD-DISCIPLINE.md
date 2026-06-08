# Build & Maintenance Discipline

> **Status:** canonical · **Added:** 2026-06-06 · **Scope:** EVERYTHING built or
> written in this repo — code, styles, content, docs, CMS, future tools. Not just
> the design system.
>
> **The goal:** this software stays organized and lean, built systematically
> against the existing system — never "vibed" into sloppiness that forces
> repeated refactors. This discipline is honoured and managed **explicitly** on
> every change, past the Phase 22.6b close and onward.

---

## 1. The principle

**Default to reuse and convention.** Every new thing — a function, a component, a
style, a template, a query, a doc — is built by **referencing and fitting the
existing system**, lean and organized. You do not invent a new pattern, a one-off,
or a raw value when the system already expresses it.

Lean means: no dead code, no duplication, no speculative abstraction; the smallest
change that fits the surrounding conventions.

---

## 2. Two paths for any change

1. **Reuse / extend in-system** *(default — the large majority of work)*: build
   from existing elements (tokens, components, modules, helpers, conventions) by
   reference. No new raw values, no duplicate patterns.
2. **Introduce something new**: allowed **only** when the system genuinely cannot
   express it, and **only** with an explicit assessment + explicit permission
   (§4). New things are never finished as one-offs.

---

## 3. Prototyping is allowed — but it is not "done"

You may prototype quickly to validate an idea — an inline style, a stub, a
throwaway preview. This is **provisional and quarantined**: never merged into
production modules as-is.

To **finish**, the work must be **promoted into the system properly** — named,
tokenized, placed in the correct module/slice, documented or shown where its kind
lives — and the provisional version **removed**.

> **Preview ≠ done.** A task that leaves a prototype, inline style, or one-off in
> place is **incomplete**, however good it looks.

---

## 4. Exceptions require assessment + explicit permission

Any deviation — a new pattern, a one-off, a quick hack, a raw value, breaking a
convention, leaving a provisional style — requires, **before it lands as done**:

- a brief **assessment**: what is needed, why the system can't already do it, the
  cost, and the proper home for it;
- **explicit permission from Alex.**

**No silent deviations.** When in doubt, surface it and ask.

---

## 5. Definition of Done — anything

- [ ] Built from / fits the existing system; no un-referenced new patterns or raw
      values.
- [ ] Lean and organized — no dead code, no duplication, matches surrounding
      conventions.
- [ ] Any reusable addition is documented where the system documents its kind
      (styles → the `/_ds/` showcase, **rendered from the real CSS**).
- [ ] Any block/schema change propagates to every linked surface
      (`docs/BLOCKS.md` + templates + CMS views — see `CLAUDE.md`).
- [ ] Any exception was assessed and **explicitly approved**.

---

## 6. Design-system application (the specific case)

For visual/style changes, the principle above takes this exact form:

**A style change may do one of two things, and nothing else:**

1. **Reuse** existing design-system elements (tokens, components, patterns) by
   token/class reference. No new raw values. *Default.*
2. **Extend** with a genuinely new element, via two stages:
   - **Stage 1 · Preview:** prototype with inline HTML/CSS, scoped to the one
     example, to validate. *Not done.* Never added to a production CSS slice
     (`tokens.css`, `public/pages.css`, `public/blocks.css`, CMS modules).
   - **Stage 2 · Promote** *(required to finish)*: move it into the correct CSS
     slice using tokens, give it a documented class, reconcile with conventions
     (no near-duplicates), add it to the `/_ds/` showcase rendered from the real
     CSS, and **delete the inline preview.**

This mirrors the Applied-tab "future components" quarantine in `docs/DS-IA.md` §4.

---

## 6.1 Future components (Applied tab)

The Applied tab is a sandbox for using the design system in contexts beyond the
website. These explorations may introduce **new components that are not in the
production design system** — "future components." They are allowed, but governed
so they sit *adjacent to* the system, never *in* it:

- **They live in their concept's isolated iframe page, and nowhere else.** Each
  Applied concept is its own `showcase/applied-*.html`, loading the real DS CSS
  plus a **page-scoped `<style>` block** (or a co-located `concept.css` linked
  only by that page) for its future components. This CSS **never** enters a
  production slice (`tokens.css`, `public/pages.css`, `public/blocks.css`, the
  CMS modules).
- **Prefix every future component `fc-`** (e.g. `fc-progress`, `fc-intention-card`)
  so the whole un-promoted surface is greppable for audit.
- **Tokens only** — no raw values — so promotion is mechanical.
- **Parity first.** Reuse real components wherever the concept needs something the
  system already has; only genuinely-new things become `fc-` components.
- **Document them** in a short "Future Components" note inside that concept's
  section, so they are tracked, not hidden.
- **Promotion = §3 Stage 2.** When a future component proves out (or two concepts
  need it), graduate it: move the rule into the correct slice, drop the `fc-`
  prefix, add it to the `/_ds/` showcase, and delete the page-scoped copy.
- **Inline styles** are for trivial one-off nudges only — never for a component
  (they can't express states/pseudo-elements/media and don't promote cleanly).

A single shared "future components" stylesheet is **not** used — it becomes a
second, ungoverned design system. Scope stays per-concept.

**Applied creative license (Applied tab only).** Parity-first is *relaxed inside
Applied* — it's a sandbox, and forcing concepts to mostly reuse existing
components makes them flat and same-y. Within Applied, a concept may be **~50%
new `fc-` elements**: use the DS as a **general aesthetic foundation** (tokens,
the four type roles, restraint, hairlines, near-square, one accent + content
hues, no shadow-soup) rather than a component straitjacket. Invent where it makes
the concept sing. The quarantine rules still hold (page-scoped, `fc-` prefixed,
tokens-only, footnoted) and **promotion to production still requires full Stage-2
+ sign-off**. This license is Applied-only; production code stays parity-first.

## 7. Monitoring — preventing drift

- Styles: the `/_ds/` showcase renders **from the real CSS**, so divergence is
  visible by design. Run a **periodic site-vs-showcase audit** (inventory real
  CSS/markup → flag anything not represented → reconcile). The 2026-06-06 audit
  (`docs/DS-IA.md` §7) is the template.
- Code/structure: review for the §5 Definition of Done; reconcile drift before it
  compounds.
- **Track every prototype/provisional** to promotion or removal — never let them
  accumulate.

---

## 8. Decision flow — apply to every request

```
1. Can the existing system express it (tokens/components/patterns/helpers/conventions)?
   → YES: reuse by reference. Done.
   → NO:  go to 2.

2. Is this a tweak to something that exists, or a genuinely new thing?
   → TWEAK: adjust via tokens / extend the existing element. Done.
   → NEW:   go to 3.

3. New thing:
   a. Assess it + get explicit permission (§4).            (gate)
   b. Stage 1 — prototype, quarantined, to validate.        (not done)
   c. Stage 2 — promote into the system properly,
      document/show it, remove the prototype.               (done)
```

---

## 9. Working workflow — plan-first, no "vibe loop"

> Alex is a professional and expects to work like a professional development team.
> The default is **plan → confirm → build → verify**, NOT "build something, then have
> Alex react to it." Reacting to a finished artifact is "vibing." It is amateur, it
> wastes resources, and it pushes the burden of direction onto Alex's gut reaction
> instead of onto a deliberate plan. **Do not slip into it.**

### 9.1 The two modes

- **Plan-first (DEFAULT for any non-trivial design/build request).**
  1. **Intake** — restate the request and the intent/constraints; surface assumptions.
  2. **Design pass** — reason from first principles (use the design subagent to produce a *plan/spec*: the approach, options with trade-offs, the usability/IA principles applied, a sketch if useful). The subagent must work *critically*, not lay out elements or pattern-match.
  3. **Confirm the PLAN with Alex** — he reviews and steers the *plan*, before any code. This is the check-in. Lead with a clear recommendation, not a menu.
  4. **Build** the agreed plan in one pass — to spec, lean, token-clean.
  5. **Verify** — lint custom props vs `tokens.css`, deploy, confirm live (md5), present what was built *against the plan*.

- **Direct-execute (ONLY when explicitly invoked).** When Alex says "build it", "just do it", "let's see where it is", "prototype this", or similar, skip the plan and implement directly so we can look at it. This is a deliberate, Alex-initiated exception — never the default.

### 9.2 Anti-patterns observed (do not repeat)

- **Build → Alex vibes → rebuild → repeat.** (coaching went cards→list→table→roster→cards; the launcher went grid→table→board — all reactive, no upfront plan.)
- **Pattern-matching the wrong model** — shoving an existing element (e.g. the editorial article card) into a different context (a software launcher) because it was handy.
- **Chasing symptoms, not root cause** — guessing the same fix repeatedly (the "scrollbar" width bug was actually a flex-body box-model issue; many turns wasted before diagnosing methodically).
- **Over-correcting** when a slight, judged change was asked for.
- **Shipping unverified** (undefined `--space-28` tokens broke CSS; inconsistent one-page-only fixes). Always lint + verify before handing back.

### 9.3 The rule

For substantive design/build work, **produce and confirm a plan before building.** Bugs: **diagnose the root cause before applying a fix** (don't guess-and-redeploy). The only time to build-first is when Alex explicitly asks to. Default to structured and planning-oriented; protect Alex from the vibe loop.
