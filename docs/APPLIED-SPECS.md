# Applied tab — build plan (open items)

> The Applied tab shows the design system used in contexts beyond the website.
> **Command Center is finished** (the polished two-mode exemplar — see
> `site/_design-system/showcase/applied-command-center.html`); its planning docs
> were removed. This doc carries the **remaining** concepts and the process that
> worked, so the rest can be brought to the same bar. Build under the loosened
> Applied creative license in `docs/BUILD-DISCIPLINE.md` §6.1.

## The process that worked (apply to each remaining concept)
1. **Concept statement** (the seed — what it's *for*). For the personal ones, Alex authors it; don't invent his vision.
2. **Product-design pass** (subagent, "act as a product designer"): reason about the *interaction model and system* — content/data, key moments, state transitions — not element-placement.
3. **Senior-UI craft pass** (subagent, "act as a senior UI designer"): treat the build as a wireframe to finish — visual hierarchy (loudness maps to attention, never chrome), IA legibility, composed rhythm (≈64/32/16 section/group/item), graphic structure, restraint.
4. **Implement**, deploy to staging, iterate with Alex.

**Anchors / guardrails (all concepts):** functional & usable FIRST; anchor to the brand *language* (colour pool, Barlow + rationed Instrument Serif italic, warm dot-grid paper, hairline rules, near-square 4px, flat/no-shadow); **two colour jobs** — category hues `--c-*` = identity, sage `--accent` = action/alive (don't mix); **one serif moment per screen** (the human line); **no counts/%/streaks/bars**; future components page-scoped + `fc-` + tokens-only, footnoted.

## NEXT — Couples Board (the couple dashboard)
**Concept (Alex, verbatim — expanded).** A private shared dashboard for two people to stay meaningfully connected across the rhythms of daily life and longer-term ambitions — surfacing each other's **energy levels, weekly capacity, and personal focus without requiring a conversation**. Every element is oriented around **relational awareness**: tasks are **noticed and volunteered rather than assigned**, plans are negotiated through **blind enthusiasm scoring** (both rate privately, revealed together, so both show up genuinely), and the **partner card leads** the experience so you always open the board seeing your person first. Warm and considered, not clinical — information-dense but with breathing room and human language; never a work dashboard. The **sidebar is a personal layer within the shared space**, distinguishing actions that need your attention from quiet notices of what your partner has been doing — the latter an **ambient feed of relational moments**. Grounded in: staying aligned should generate connection, not overhead.

**Design direction (from the product pass — to refine in build):**
- **Partner-leads asymmetric stage** (NOT mirrored — symmetry reads clinical): the partner's presence is the hero (avatar + energy *word* leads + pips + capacity phrase + focus in serif = their voice); your own presence is a quiet secondary self-strip.
- **Volunteered tasks** read as offered/signed ("— A/S"), never assigned; + noticed-but-unclaimed "I'll take this".
- **Shared ambitions** = real content cards + state pill + a "who's holding it" chip.
- **Blind enthusiasm scoring** on plans: states awaiting-you / awaiting-them / revealed (quiet concealed lozenge → warm two-score reveal under one rule, no winner).
- **Personal sidebar, two parts:** "For you" (actions) + an **ambient feed** of the partner's recent moments (gentle, past-tense, no badges/unread/counts).
- New components: `fc-partner-lead`, `fc-energy` (word + pips), `fc-self-strip`, `fc-volunteered`, `fc-blind-enthusiasm`, `fc-sidebar`, `fc-ambient-feed`.

## Remaining concepts (first-pass exists; elevate via the process)
- **Analytics** — *verdict-first masthead + evidence.* One human verdict line leads ("This week landed — *X* carried it"); four signals (Reads, New subscribers, **Avg finish %** not visitors, Shares) demoted; top pieces (real `cms-table` + inline finish bar); category bars (`fc-bar`); a derived **"what to write next"** prompt. Content-first, 30-sec read, no vanity boxes. fc-: `fc-stat`, `fc-bar`, `fc-prompt`.
- **Coaching** — *full-measure folios, not a CRM grid.* People-as-people: single-column folio rows (identity / serif focus-quote in their words / momentum as a felt word Climbing·Steady·Stuck — NOT % / last thread / next touchpoint); unscheduled/stuck rise to top with a quiet terracotta edge. fc-: `fc-folio`, `fc-momentum`.
- **Mobile** — *specimen plate of REAL components.* The public DS carried to 360px — every pixel inside the phone is a real public component (`card--article`, `article-hero`, `cat-label`, `meta-strip`), one frame shows a real responsive collapse. fc-: `fc-phone`, `fc-spec-caption` only (no content lookalike classes — that defeats parity).
- **CMS Panel** — *the parity anchor, deliberately NOT re-composed.* The real CMS shell (topbar/sidebar/view-header/filter-bar/cms-table), zero `fc-`. Show stage variety; one mono footnote asserting "composed of production components." Don't add heroes/serif.

## Shared Applied visual language (the family rules)
1. Loudness maps to attention, never to chrome.
2. Demote the reference rail behind a ruled gutter; the work surface leads.
3. Two colours, two jobs (identity hues vs sage-for-action) — and thread a domain's hue consistently across views.
4. Composed rhythm (≈64/32/16), not even wireframe spacing; eyebrows tucked close to their content.
5. One serif moment per screen, on the human/intent line; no shadows, no new hues, near-square 4px.
6. Each concept earns ONE structural idea and commits to it; no two share a skeleton; interesting = composition.

## Status
✅ Command Center (finished exemplar). ⏳ Couples Board (next) · Analytics · Coaching · Mobile · CMS Panel (first-pass on staging, to elevate). ⏳ Gated prod ship of `/_ds/` — Alex's decision once Applied is done.
