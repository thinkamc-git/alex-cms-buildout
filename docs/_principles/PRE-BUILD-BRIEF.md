# Pre-Build Brief

> **Status:** universal · required gate before any UI implementation starts.
>
> **Purpose:** makes the pre-flight scan a visible, confirmable output — not a
> private step that can be skipped. No implementation is written until this brief
> is confirmed by Alex.

---

## When to use this

Any time UI work is requested — a new component, a layout change, a new pattern,
a style update to an existing surface. Even small changes. If pixels are moving,
a brief is produced first.

The brief is short. For a small change it takes two minutes. For a larger one it
surfaces decisions that would otherwise be caught after the build — which costs
far more.

---

## The format

Produce this inline in the conversation before writing any code.

```
## Pre-Build Brief: [task name]

### What's being built
[One sentence. The component or change, in plain terms.]

### Component scan
[Does a similar element already exist in the CMS or DS?
Name it exactly. If yes: reuse it. If no: note it as new.]

### Token scan
[What tokens cover the visual properties needed?
List them: --space-X, --text-X, --c-X, --rule-X, etc.
If a raw value would be needed: flag it as new — needs sign-off.]

### Pattern scan
[How does the closest existing interaction work?
e.g. "dirty-flip.js handles save button promotion"
     "filter-pill handles multi-select toggles"
     "form-actions-sticky is the always-present save bar"
Name the file and line if relevant.]

### Proposed approach
[One short paragraph: what will be built, what existing elements it
reuses, what (if anything) is genuinely new. No code yet.]

### New things requiring sign-off
[List any pattern, component, or raw value not in the system.
If the list is empty: state "None — fully covered by existing system."]
```

---

## The gate

**Alex confirms the brief before any code is written.**

If Alex redirects, the brief is updated — not the implementation. The brief is
what gets steered. Code is what gets built once direction is locked.

If the brief reveals something genuinely new is needed, that's a §4 exception
(BUILD-DISCIPLINE.md) — it needs an assessment and explicit permission before
it appears in any file.

---

## What this prevents

- Building the wrong thing and having Alex discover it in the output
- Inventing components that already exist in the system
- Using raw values when tokens are available
- Alex acting as quality gate instead of direction-setter
