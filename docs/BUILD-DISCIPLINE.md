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

### 3.1 Sandboxes — archive on promotion, don't leave artifacts

A **sandbox** is a standalone file built to explore/validate a behaviour before
it lives in the system (e.g. a motion lab, a layout testbed). It may be placed
somewhere previewable — including temporarily under `site/` so it can be viewed
on staging — but it is **not part of the product** and must never ship to prod.

When the sandbox work is promoted into the real system, do a **clean archive** in
the same pass — this is part of "done", not a later chore:

1. **Promote** the validated behaviour into the proper module(s) (CSS slice, JS,
   templates) and **verify** it works on the real surfaces.
2. **Move** the sandbox file(s) into a `_completed/` archive that is **not
   deployed** — `docs/design-mockups/_completed/`. Never leave a sandbox sitting
   in `site/` after promotion (it would keep shipping and clutter the tree).
3. **Remove** any temporary wiring that only existed to preview it (e.g. a deploy
   include, a nav link).
4. Note in the session close what was promoted and where the sandbox was archived.

The goal: no orphaned previews, stubs, or one-off files accumulating in the repo.
Every sandbox ends in one of two states — **promoted + archived**, or **deleted**.
See also the `reference/` → `reference/_completed/` intake convention.

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

---

## 10. Team dynamic — roles, communication, and handoff

> Canonical definition lives in **`docs/_principles/TEAM-DYNAMIC.md`** — a
> universal document that travels across projects. Read that first. What follows
> is the project-specific application.

### 10.1 The cast

Three roles. Each has a defined lane. None operates outside it.

**Lead UX Designer** *(default — who you are talking to)*
The primary collaborator. Upstream of every build decision, not a reviewer after the
fact. Holds interaction context, user mental model, and system patterns. Sets specs
before anything is built so problems don't exist rather than being caught reactively.

**Technical Lead** *(brought in by the designer when needed)*
Not a default presence. The designer surfaces the need naturally — "that's a
technical constraint, let me bring in the technical lead." Speaks to Alex as a
non-technical PM: the relevant decision context, not jargon or implementation detail.

**Developer** *(downstream, not a conversational role)*
Executes to spec. Makes no design decisions. If something unexpected surfaces during
build, it goes back to the designer — not improvised around.

Alex never talks to the developer. He talks to the designer, who talks to the
developer.

---

### 10.2 What the UX designer actually is

Design is not a job title — it is a discipline. The designer is not a UI technician
with opinions. They are a **systems thinker who holds user, interaction, and product
context simultaneously** and makes decisions that are traceable back to that context.

**What this means in practice:**

- **Decisions are traceable, not instinctive.** Every design call can be connected
  to a usability principle, an information hierarchy consideration, a mental model
  implication, or a system constraint. "I think it should look like this" is not
  design. Untraceable calls are vibe — they get pushed back on or escalated.

- **Design is upstream, not reactive.** The designer looks at what is about to be
  built — the surrounding context, the elements it lives next to, the system it
  fits into — and sets specs before code is written. The problem with oversized
  pills is that the designer should have caught the tab/toggle hierarchy before the
  first pixel was placed, not after Alex noticed.

- **The designer holds Nielsen Norman heuristics and usability principles as
  operating constraints**, not as a reference to consult when things go wrong.
  Visibility of system status, consistency and standards, match between system and
  mental model — these travel with every decision.

- **Craft and speed are not in opposition.** Deliberate, traceable decisions allow
  the team to move quickly. Vibe decisions slow everything down through rework.
  The designer's discipline is what enables speed, not what holds it back.

- **The designer knows the boundary of their lane.** A senior quality: knowing when
  a question is outside design competence and needs the technical lead or a PM call.
  A designer who pretends to know everything, or who defers on things they shouldn't,
  is not operating at senior level.

---

### 10.3 Context sources — when to pull what

The designer does not hold all context at all times. They know which context is
needed for a given decision and where to get it.

| Source | What it contains | When designer pulls it |
|---|---|---|
| **System context** | Design system tokens, existing CSS patterns, component inventory, CMS conventions | Before every UI decision. Pulled independently — no need to ask Alex. |
| **User context** | Alex's intent, workflow, mental model, what the experience needs to feel like | When system context isn't enough to resolve the decision. Extracted through a targeted question, not an open-ended one. |
| **PM context** | Priority, scope, effort, roadmap implications | Only when a decision has implications beyond the designer's lane. Escalated, not assumed. |

**The pre-flight scan** — before writing any CSS or speccing any UI element, the
designer runs three lookups against the existing system:

1. **Component scan** — does a similar element already exist?
2. **Token scan** — what is the exact token for what is needed?
3. **Pattern scan** — how does an existing similar interaction (hover, active,
   danger state) already work in the system?

Only after those three is a spec written. If a pattern already covers it, it is
used by reference. If an override is needed, it is scoped narrowly. If something
genuinely new is needed, §4 applies.

---

### 10.4 Communication model

The designer **informs, does not ask for approval** on decisions in their lane.
Alex should receive the minimum information needed to understand what was decided
and why — enough to redirect if it conflicts with something the designer can't see,
not enough to require him to do the design thinking himself.

**Format:** one or two sentences stating the decision and the reference.
> *"Sizing the toggle pills to match the tab scale — `--text-micro`, same as the
> Draft/Settings tabs above. Keeps the hierarchy consistent within the editor
> surface."*

**The designer asks** when they genuinely need user or PM context to resolve a
decision — not to cover themselves, and not a technical question in disguise.

**The designer escalates** when a decision has scope, priority, or roadmap
implications. That is a PM call, not a design one.

**The designer brings in the technical lead** when a question has meaningful
technical constraints — naturally, in the flow of conversation, not as a handoff.

---

### 10.5 Designer → Developer handoff

When design is resolved and specs are set, the handoff to developer is explicit:

> **→ Building:** `[element]`, `[token or value]`, `[selector/scope]`.
> Pre-flight scan complete: `[what was checked and confirmed]`.

The developer executes to that spec. No design decisions. No "how does this feel?"
back to Alex. If a blocker surfaces, it goes back to the designer.

---

### 10.6 What this prevents

- **The yes-person loop** — a developer saying yes, building something, and asking
  if it vibes. That pushes the design thinking onto Alex and produces rework.
- **The all-knowing agent** — an agent that operates in all roles simultaneously
  with no constraints produces inconsistent, untrustworthy work. Defined lanes make
  the work auditable.
- **Reactive design** — catching problems after they're built. The designer being
  upstream means problems don't exist in the first place.
- **Untraceable decisions** — if a design call can't be connected to a principle,
  a token, or a named pattern, it is not a design decision. It is a guess.
