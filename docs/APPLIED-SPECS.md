# Applied — design briefs + wireframe specs (2026-06-06)

> Order of work: **brief → wireframe → build.** The briefs (below) define what
> each concept *is* and must do; the wireframes serve the brief. Future
> components stay page-scoped + `fc-` per BUILD-DISCIPLINE §6.1.

## Design briefs (the grounding layer)

**1. Command Center** *(Alex, verbatim).* A personal command center that holds the whole rhythm of your life in one calm surface — your current season's six intentions, the month you're in, and the week you're actively shaping. It's where the deliberate rituals happen (opening and closing weeks, months, and seasons) and where progress is reflected back as accomplishment rather than tallied as tasks, so the view always answers "what am I oriented toward, and how am I moving" rather than "what's overdue."

**2. Together** *(Alex, verbatim).* A private shared dashboard for two people to stay meaningfully connected — surfacing each other's energy, coordinating the week, and keeping life's projects and plans visible without the noise of generic tools. Built around the idea that staying aligned should feel like an act of love, not a productivity chore.

**3. Analytics.** *Purpose:* let Alex see in one glance whether the writing is landing and the audience is growing — and what to write next. *User/context:* Alex, weekly, a 30-second check, not a data console. *Core content:* one headline metric that matters most + its trend, then which categories/pieces are resonating — content-first, not vanity pageviews. *Communicate:* momentum and direction, calmly. *Good =* reads in 30s, knows what to write next; never feels like SaaS.

**4. Coaching.** *Purpose:* hold the handful of people Alex is actively coaching as *people, not tickets* — their focus, momentum, and the next touchpoint — so he walks into each session prepared and notices when someone needs a nudge. *User/context:* Alex, before/between sessions; a small roster (3–6); reflective, not a CRM. *Core content:* per client — who they are, the focus they're working on (their words), momentum, last session's thread, next session. *Communicate:* care and continuity. *Good =* never walks in cold; a stalled client surfaces gently.

**5. Mobile.** *Purpose:* show the public design system carried whole to the phone — proof the system holds at touch width, not a separate mobile style. *User/context:* a showcase viewer seeing the system live small. *Core content:* real public components (article reader, index, a reading view) at phone width. *Communicate:* same measure, same rules, same restraint — adapted, not redrawn. *Good =* reads as the real site on a phone; parity obvious.

**6. CMS Panel.** *Purpose:* the parity anchor — prove every Applied concept is built from real, shipping admin components by showing the actual CMS shell. *User/context:* showcase viewer / reference. *Core content:* the real topbar / sidebar / view-header / filter-bar / table assembled. *Communicate:* "these explorations are built from real parts." *Good =* indistinguishable from the real `/cms`; no editorial liberties.

---

## Wireframe specs (serve the briefs above)

> The first-pass pages were technically correct but visually flat (same compact
> header + uniform `repeat(N,1fr)` grid every time; serif and rules barely used).
> These specs elevate them through **composition** — asymmetry, scale, negative
> space, and hairline-rules-as-structure — with zero new colour/shadow/ornament.

## Shared "Applied visual language" (the family rules)
1. **Every page opens with a register-setting band, not a `view-header`** — condensed sage eyebrow → a statement at *scale* → a full-bleed closing hairline. (Only exception: CMS Panel, the parity anchor.) Dashboards lead with a number/statement; life-surfaces lead with a serif statement.
2. **Section headers = condensed eyebrow on a hairline, never boxed.** Retire the boxed `fc-panel` look; zones are defined by rule + label.
3. **Serif rationed to 1–2 scaled moments per page, always carrying the human voice** — never decorative, small, or grey.
4. **Grids are measured, never uniform — one element wins per zone.** Asymmetric splits (`2fr/1fr`, `1fr/320px`, `140px/1fr`) are the house grammar; symmetry only where it *means* something (Together).
5. **Hairlines are the structural skeleton; colour is content-only.** Rules carve zones/spines/gutters; the 18-hue pool appears only as small content signals (left-tick, meter fill, day dot). The `fc-` footnote is demoted into a true footer.

## Per concept — the one big idea + structure

**1. Coaching Dashboard** — *two-column editorial ledger.* Full-bleed hero band; `1fr / 320px` split with a full-height hairline spine. Clients are **horizontal ledger rows** (avatar · name/role · serif focus-quote with category left-tick · right-aligned momentum meter + pill + next date), hairline-separated — not tiles. Right rail: "Next session" focus card (serif recall note) + condensed week agenda. New: `fc-roster-entry`, `fc-momentum` (shared), `fc-next-card`. Avoid the tile wall.

**2. Analytics View** — *hero metric + ledger (newspaper front page).* One number as the headline at display-XL (~96px+) with a hairline sparkline beside it; the other 3 KPIs demoted to a condensed hairline-divided strip. Below: `2fr/1fr` — category bars (keep `fc-bar`) beside the real `cms-table`. New: `fc-stat-hero`, `fc-stat-inline`, `fc-sparkline`, keep `fc-bar`. Avoid the 4-up equal stat-card grid; no chart chrome.

**3. Command Center** — *vertical cadence spine.* `140px / 1fr` split; left 140px is a sticky SEASON/MONTH/WEEK spine (active inked). Season banner hero = Instrument Serif italic at ~36–40px. Six intentions as a **numbered manifesto list with hanging ghost numerals** (not a 3×2 grid). Rituals kept. Progress led by a serif sentence, bar secondary. New: `fc-cadence` (→ spine), `fc-intention` (→ list row), `fc-ritual`, `fc-meter` (narrative-led). Avoid the 3×2 tile grid and over-quietness.

**4. Together** — *mirrored book-spread for two.* Centered measure; paired presence as `1fr | hairline gutter | 1fr` mirror (real full-height center gutter). Shared zones go **full-width and cross the gutter** (week band, projects). Week = one hairline-ruled band, not 7 empty boxes. New: `fc-paired/fc-person` (with gutter), `fc-energy` (shared), `fc-week-band`, `fc-project`. Avoid 7 equal boxes / floating cards; no second accent to distinguish people.

**5. Mobile App** — *editorial device showcase.* `1fr/1fr` split: one **hero phone large** (article reader, real public components) + a serif statement beside it; the other two phones smaller and **vertically offset**, captioned in condensed. New: `fc-phone` + a `fc-phone--lg` size. Avoid three equal phones on one baseline.

**6. CMS Panel** — *parity anchor, deliberately NOT re-composed.* Stays the true CMS shell (topbar / dot-grid sidebar / view-header / cms-table) so it proves the others are built from real parts. Light touch only; no `fc-`. Avoid adding editorial heroes here.
