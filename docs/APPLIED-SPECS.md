# Applied — concept statements + grounded design (2026-06-06)

> Order of work: **concept statement → design (from substance) → build.** The
> concept statements are seeds, not specs. The grounded design (below) was
> derived from them + the DS aesthetic. Build from the design. Future components
> stay page-scoped + `fc-` per BUILD-DISCIPLINE §6.1, footnoted on each page.

## Concept statements (the seeds)

1. **Command Center** *(Alex, verbatim — expanded).* A personal command center that holds the entire rhythm of your life in one calm, always-open surface — anchored by the current season's six intentions, the month you're progressing through, and the week you're actively shaping. The **week is the primary working surface**, where two distinct kinds of thing live side by side: **could-dos** (gentle, chosen invitations toward your intentions — never obligations) and ordinary **to-dos** (plain life-admin), which look and feel different because completing them means different things. The center is built around **rituals, not dashboards-of-data**: opening and closing weeks, months, and seasons are deliberate, performed moments, and the heaviest of these (the seasonal turn) deserves room to breathe rather than being compressed into widgets. Critically, **nothing is ever shown as a count, percentage, or completion bar** — progress is reflected back qualitatively, as authored accomplishment, because the system exists to make progress visible while **keeping failure quiet**. The tone is warm, unhurried, non-judgmental: it answers "what am I oriented toward, and how am I moving" — never "what's overdue."
2. **Couples Board** *(Alex, verbatim — expanded).* A private shared dashboard for two people to stay meaningfully connected across the rhythms of daily life and longer-term ambitions — surfacing each other's **energy levels, weekly capacity, and personal focus without requiring a conversation**. Every element is oriented around **relational awareness**: tasks are **noticed and volunteered rather than assigned**, plans are negotiated through **blind enthusiasm scoring** (so both people show up genuinely), and the **partner card leads** the experience so you always open the board seeing your person first. The visual language is warm and considered rather than clinical — information-dense enough to be useful, with enough breathing room and human language that it never feels like a work dashboard. The **sidebar is a personal layer within the shared space**, distinguishing actions that need your attention from quiet notices of what your partner has been doing — the latter an **ambient feed of relational moments**. Grounded in the idea that staying aligned should generate connection, not overhead.
3. **Analytics.** 30-second weekly editorial check — is the writing landing, is the audience growing, what to write next. Content-first, not vanity metrics.
4. **Coaching.** Hold the handful of people being coached as *people, not tickets* — focus (their words), momentum, last thread, next touchpoint.
5. **Mobile.** The public DS carried whole to the phone — *real components* at touch width; proof of parity.
6. **CMS Panel.** The parity anchor — the real CMS shell, not re-composed.

## Grounded design — per concept

**Shared failure of the first pass:** every page was the *same* page (920px centered column, eyebrow → serif statement → uniform grid). Each concept must earn a *different* skeleton from its own substance.

**1. Command Center — left cadence spine + scale-down-the-page; the WEEK is the hero.** *(revised to expanded statement)*
- Substance: season name + thesis; cadence as **prose** ("Week three of June" — never a fraction). Six intentions as standing orientations with a **state word** (Not begun / Underway / Carried / Closed) + hue, **scale-asymmetric** (the 1–2 this month is "for" render large; the rest are chips; resting ones stated plainly, not greyed-disabled). Month-as-chapter = one sentence. **THE WEEK (primary surface):** an open-sentence (serif, the human voice) + two visually *distinct species* side by side — **could-dos** (spacious, body-weight, hued to the intention they serve, an *invitation*; done = an authored "did" line, no checkbox/strikethrough) vs **to-dos** (compact, grey, small, plain admin; done = quiet dim + collapse). Rituals (open done / close Fri / close-season near boundary). Progress = **authored prose + a state-word legend in hues — NO count, %, or bar anywhere**.
- Idea: 180px sticky **Season›Month›Week spine** (a real structural vertical rule) + fluid right field whose **scale shifts down the page** — season airy, month a quiet line, **the WEEK densest/most-inked** (2-col could-do | to-do split), progress small and calm. Eye always knows its altitude.
- fc-: `fc-cadence-spine`, `fc-intention` (state, 2 sizes), `fc-could-do` *(invitation)*, `fc-todo` *(admin)*, `fc-ritual`, `fc-seasonal-turn` *(full takeover — replaces the field with near-empty space + one serif prompt + an authored field; "room to breathe," not a card)*, `fc-accomplishment` *(prose + state words, contractually no track/%)*.
- Trap: the current page's 3×2 intention grid + `width:64%` bar + "Wk 23/26" — all three forbidden. Could-dos and to-dos must be different species; incomplete stays quiet (no red / "overdue").

**2. Couples Board — partner-leads asymmetric stage + right personal sidebar (NOT mirrored).** *(revised to expanded statement)*
- Substance: **PARTNER HERO** first and largest (avatar + **energy *word* leads** + pips in their hue + a capacity phrase + their focus in **serif = their voice**); your own presence a quiet **compact self-strip** (subordinate — you glance at yourself, you *attend to* them). Shared middle: **volunteered tasks** (noticed/offered, signed "— A/S", never assigned; + unclaimed "I'll take this" affordance) and **shared ambitions** (real content `card` + `pill-*` state + a "holding: Sam" chip). **Blind enthusiasm scoring** on plans (rate privately → revealed together; 3 states: awaiting-you / awaiting-them / revealed). **Sidebar = a personal layer in two parts:** "For you" (actions waiting on you) + an **ambient partner feed** (gentle past-tense — "Sam set energy to Steady · volunteered the vet · rated the trip" — no badges/unread/counts/actions; a window, not an inbox).
- Idea: an **asymmetric stage** — partner hero dominates the top, a quiet self-strip beneath, and a persistent **300px right sticky sidebar** (your private channel within the shared space). No spine, no symmetry (symmetry reads as a clinical work dashboard — the thing to avoid).
- fc-: `fc-partner-lead` *(hero, the signature)*, `fc-energy` (word + pips, shared with CC), `fc-self-strip`, `fc-volunteered`, `fc-blind-enthusiasm` *(quiet `--ink-18` concealed lozenge → warm two-score reveal under one rule, no winner)*, `fc-sidebar` (2-part), `fc-ambient-feed`.
- Trap: mirrored equal columns + energy-as-bar = work dashboard. Partner must be larger/first; energy = felt word first; tasks offered not assigned; ambient feed never a notification list; blind reveal quiet/warm, not gamified (no locks/"?"/slot-machine).

**3. Analytics — verdict-first masthead + evidence.**
- Substance: a one-sentence **verdict** ("This week landed — *Discipline Reset* carried it…"); four *signals* (Reads, New subscribers, **Avg finish %** not visitors, Shares) with 7-day deltas; top pieces by reads+finish (real `cms-table` with inline finish bar); categories by resonance (`fc-bar`); a derived **"what to write next"** prompt.
- Idea: lead with the conclusion (verdict at scale), numbers/tables are *evidence* below. Numbers subordinate to the sentence.
- Zones: `view-header` + **verdict line** (h2, serif on the load-bearing word) · `fc-stat` row (lead signal larger, not 4 equal) · asymmetric 62/38: wide pieces table (finish bar in-cell) + narrow category bars · `fc-prompt` footer ("write next").
- fc-: `fc-stat` (weightable), `fc-bar` (inline-capable), `fc-prompt`.
- Trap: four equal stat boxes + "visitors." Lead with verdict; swap visitors→finish; must include the write-next nudge.

**4. Coaching — full-measure folios + nudge rises to top.**
- Substance: 3–5 people; each: focus quote (their words), momentum (Climbing/Steady/Stuck, NOT %), last-session thread (one sentence), next touchpoint (date or *unscheduled*), between-session note; unscheduled/stuck rise to top.
- Idea: single-column stack of wide **folio rows** (dossiers), each person given full measure; nudge person rises with a quiet `--c-terracotta` left edge. Space expresses "people, not tickets."
- Zones: `view-header` + roster read ("3 active · *Priya needs a nudge*") · `fc-folio` rows: identity / serif focus-quote (the human voice) + thread / touchpoint rail (`fc-momentum` + mono date + pill), `--rule-30` separated. Serif cap = only the lead two folios render the quote in serif (cap → hierarchy).
- fc-: `fc-folio`, `fc-momentum` (felt state).
- Trap: 3-up equal cards + %-momentum = CRM. Folios + felt momentum + quiet edge = notebook.

**5. Mobile — specimen plate + manual captions (REAL components only).**
- Substance: the *same* public content at 360px — article reader, an index (real `card--*` stacked), an editorial hero collapsing to mobile, a `filter-pill` row. Each frame proves "same component, narrow."
- Idea: 3–4 `fc-phone` frames on `dot-surface` plates laid out like a technical-manual exhibit, each captioned with the parity claim + the real classes it shows.
- Zones: plate header ("Carried whole to the phone" + mono subline) · specimen row of phones (REAL public CSS inside; frame is the only fc-) · `fc-spec-caption` under each (mono class list) · one frame shows a real responsive collapse.
- fc-: `fc-phone` (minimal), `fc-spec-caption`. **No content lookalike classes** — that defeats parity.
- Trap: the first pass faked content with `fc-mtitle/mhero/mbody`. Every pixel inside must be a real public component.

**6. CMS Panel — deliberately un-recomposed (zero fc-).**
- The real `/cms` Articles shell (topbar/sidebar/view-header/filter-bar/cms-table) isolated in an iframe. Show stage variety in the table (live/draft/outline/concept/published). Add ONE mono footnote: "Composed entirely of production components · zero future components."
- Trap: over-designing it. No hero/eyebrow/serif. Its restraint is the statement.

## Shared Applied visual language
1. One canvas/chrome: `--canvas-bg`, `--primary` ink, the four font roles, near-square radii, flat; dot-grid marks *preview* surfaces only.
2. Parity is baseline; every `fc-` is page-scoped, prefixed, tokens-only, and named in a shared mono footnote on each page.
3. Serif rationed identically: ≤2/page, ≥22px, italic, primary/secondary, always the concept's *human voice*; CMS Panel/Mobile use it 0–1×.
4. Colour only in content (`--c-*`/`--c-current`); sage `--accent` the only chrome chromatic note; no new hues/shadows/gradients.
5. **Each page commits to ONE structural idea top-to-bottom** — no two share a skeleton. Interesting = composition, never ornament.
6. Shared `--space-*` rhythm and up to the 1400px `--max`; column logic differs per page.
