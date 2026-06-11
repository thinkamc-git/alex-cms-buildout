# alexmchong.ca CMS — Build Record

**State: maintenance.** The CMS is built and live in production. This file is the lean record of what shipped plus the forward backlog. The full 24-phase plan, audits, and phase notes are archived in [`_completed/`](_completed/) for history.

---

## What shipped

All active phases (0–15) and the post-v1 work are live in production.

| Milestone | Phases | Shipped |
|---|---|---|
| Static marketing site (first public ship) | 1 | — |
| CMS foundation — PHP/DB plumbing, auth, admin shell | 3–5 | — |
| All four content types end-to-end (Articles · Journals · Live Sessions · Experiments) | 6–10 | — |
| Categories, Series, Indexes, Redirects, Subscribers, Scheduling | 11–14.6 | — |
| Nav reorg · Pages + Navigation editor · Settings · Editorial Index · load motion | 19–21.8 | — |
| Design-system reorg (v2.1) | 22.x | prod 2026-06-05 / 06-09 |
| Mobile review + implementation | 23.x | — |
| **Public cutover — site fully live** | 24 | **prod 2026-06-10** |
| CMS polish — defect sweep, login env-switcher, recovery-codes redesign, unified drop-line reorder, CSS cache-bust | — | prod 2026-06-11 |

Release history: [`RELEASES.md`](RELEASES.md). Full plan + audits: [`_completed/`](_completed/).

---

## How we work now (maintenance loop)

The project is in maintenance. The working loop — and the one to follow when picking work back up:

1. **Intake / explore in a sandbox** under `docs/design-mockups/` — a throwaway HTML mock or spike. Play until the direction is right.
2. **Promote** the validated work into the real system — tokens, components, views — per [`BUILD-DISCIPLINE.md`](BUILD-DISCIPLINE.md) (default to reuse; new patterns need sign-off; preview ≠ done).
3. **Archive the sandbox** into `docs/design-mockups/_completed/` so the working area stays clean. Promoted sandboxes never linger.

Same rule for docs: when an audit or spec is finished, it moves to `docs/_completed/`.

Canonical references (the maintenance source of truth) live in `docs/` — see [`../CLAUDE.md`](../CLAUDE.md) for the map. `docs/CMS-STRUCTURE.md` is the system spec; check it first for any system-level question.

---

## Backlog / future ideas

Nothing here is committed work — it's the parking lot.

### Next up — marketing-pages optimization pass

Work through the standalone marketing pages (`site/_pages/`) **one at a time, in a sandbox**, the same way the **coaching** page was done (mock in a sandbox → iterate → promote → archive — see `docs/design-mockups/_completed/coaching-sandbox/`).

**Positioning goal:** optimize for *potential hiring managers* visiting the site. The copy should **not** address them directly, but the site must position Alex strongly as a **design-discipline leader who is AI-ready**. (More to dig into when the phase starts — audience, narrative, proof points, page-by-page priorities.)

### Smaller items

- **`design-system.php`** — remove the in-CMS Design System view and link out to the public `/_ds/` showcase (the canonical, CSS-rendered reference), rather than duplicating it in the admin. *(from the CMS defect sweep)*
- **Editor consistency** — the four content editors trigger "Idea Notes" visibility and body-HTML sanitization at different pipeline stages; align them to one explicit rule. *(needs a decision on intended behaviour first)*
- **Minor DRY** — repeated inline pill styles across list views → one `.pill` class; per-view date formatters → a shared helper. *(cosmetic debt)*

### Deferred (was Phase 18)

- **Transactional email** (email-based password reset). Deferred from the original plan and **superseded by recovery codes** (Settings → Account), which now cover password loss. Revisit only if email becomes necessary for another reason.
