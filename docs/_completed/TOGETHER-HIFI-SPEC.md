# Together Dashboard — Hi-Fi Resolution Spec

> ## 0. POST-COMPACT STATUS & HOW TO PROCEED (read first)
>
> **What this is:** `site/_design-system/showcase/applied-together.html` — the "Together Dashboard" Applied concept in the **public** `/_ds/` design-system showcase. It currently reads as a solid wireframe but **looks amateur next to the other resolved Applied examples** (Command Center is the quality bar). This doc is the blueprint to bring it to a beautiful, functional hi-fi standard.
>
> **The core problem (user's verdict): VISUAL INFORMATION HIERARCHY.** Specifics the user named:
> - Too much uniform **bold Barlow** — flattens everything (e.g. the **week-planner day labels are far too heavy/prominent**; days should NOT be the loudest thing).
> - Opportunities for **icons**; needs **simplification**; reduce visual noise.
> - The **Partner card (R1) is the only resolved section** — bring every other section up to its bar.
>
> **CRITICAL METHOD:** this must be assessed **VISUALLY, not from code.** Claude cannot screenshot staging (Basic Auth). **NEXT STEP after compaction:** ask Alex to paste screenshots (full dashboard · the "week, together" card · the right sidebar), do a real visual-hierarchy critique, update §1–§3 of this spec to target the actual on-screen problems, THEN execute the §5 order.
>
> **Already live (`?v=8`):** Claim = action button (not status), at-home pills, dropped redundant "the".
> **Deploy:** `bash bin/deploy.sh staging`; verify `ssh alexmchong-ca 'md5sum …'` vs local `md5 -q`; append `?v=N` to bust cache.
> **Foundations (non-negotiable):** notice + claim only (NEVER assign between partners); terse functional copy (system text = data/labels, NOT AI natural-language; only user-authored content is natural & short); tokens-only; fixed spacing scale (--space-4..96); no text below 11px; sage `--accent` = action, `--c-*` = identity, `--c-terracotta` = dormant-project only; flat/near-square; reuse `.dash-card/--callout`, pills, avatars.


**Target file:** `site/_design-system/showcase/applied-together.html`
**Quality bar:** `site/_design-system/showcase/applied-command-center.html` (a resolved Applied dashboard)
**Reused CSS:** `site/_design-system/css/cards.css` (`.dash-card` / `.dash-card--callout`), `tokens.css`
**Status of this doc:** standalone execution blueprint. It assumes no memory of the conversation that produced it. Read it top to bottom, then execute Section 5 in order.

---

## 0. The verdict we are executing against

Alex's words: *"this looks like a good wireframe — but it's not really a hi-fidelity resolved UI. The only section that looks thought through is the Partner card."*

So the job is precise and bounded: **the R1 Partner card is the quality bar. Bring every other section up to it.** Nothing in the page's *information architecture* is wrong — the asymmetry (partner = hero stage, you = quiet strip), the row order (R1 hero → R2 plans → R3 corkboard → R4 projects → R5 week), the foundation (notice + claim, never assign). What's wrong is *resolution*: type weight is flat, text floats where a component should carry it, card internals are inconsistent, empty/stale states are unresolved, and the spacing rhythm drifts. This doc fixes resolution, not architecture.

---

## 1. Why the Partner card (R1) is the bar

Study `.fc-partner` (lines 51–80, 244–275 of the current file). Replicate these four qualities **everywhere**:

1. **Four-level hierarchy that's legible at a glance.** Name (`text-h4`/600) → section header (`fc-psec-h`, `font-cond` label, muted) → primary line (`text-sm`/primary) → meta (mono/muted). Every text role has a *distinct* token + weight + colour. You can rank any piece of text instantly. The rest of the page collapses to ~two levels (everything is `text-sm` primary), which reads flat = wireframe.

2. **Internal structure carried by dividers, not gaps.** The card is a 4-column grid with `border-left: var(--rule-faint)` between sections and a `border-bottom` under the head. Structure is *drawn*, not just spaced. Other sections lean on whitespace alone, so groups don't feel like groups.

3. **Real data shapes, not labels-as-content.** Meters are pips with a name + a directional value (`Low ↓`, `High ↑`). Calendar is `time + event`. A stale meter is explicitly faded with a mono `set 2 days ago` note. The data has *form*. Elsewhere (corkboard, tasklist) content is undifferentiated text strings.

4. **Restraint — exactly one loud thing.** It's the only `--callout` hero, the only place the serif italic human line appears (`fc-partner-sub`), the only L1 element. Everything else recedes. Hi-fi is not "more decoration everywhere" — it's *deciding what's loud and making everything else quiet and exact.*

**The transferable rule:** every card gets (a) a real internal header pattern, (b) ranked type roles, (c) drawn structure where groups meet, (d) data rendered as shaped components, not floating strings.

---

## 2. Global gaps — what currently reads "wireframe, not hi-fi"

Concrete and critical:

- **Flat type weight.** Outside R1, almost everything is `text-sm`/`primary` or `text-md`. No card establishes its own internal hierarchy. The corkboard, the projects, the tasklist, and the sidebar all read as one tonal plane.
- **Floating text where a component belongs.** `fc-offer` ("+ offer support", "+ file") is a bare clickable span. `ow-tag` "Takeout in" is plain text in a cell that otherwise holds pills. `you-item` rows are undifferentiated text. The constraint is explicit: *use the component vocabulary (pills, avatars, icons, segmented control, tags) rather than floating text.* The page violates this in ~6 places.
- **Inconsistent card internals.** R1, R4 projects, R5 week, and the sidebar each invent their own header treatment: R1 uses `fc-psec-h`, R5 left uses `ourweek-h` (no rule), R5 right uses `coord-h` (rule + padding), the sidebar uses `rail-sec-h`. Four header patterns for the same job. Hi-fi needs **one** card-internal header pattern (see §4).
- **Empty / stale / placeholder states unresolved.** `ow-none` "Open" and "—" are bare muted text — not a designed calm-empty. The dormant project warning is a raw sentence. The blind-enthusiasm "awaiting" state and the corkboard "New" flag are one-offs, not a system. There is no consistent empty-state pattern.
- **Spacing rhythm drift.** Cards mix `--space-16`, `--space-20`, `--space-24` padding with no rule for which applies. Several internal margins use raw `5px / 6px / 7px / 9px` (lines 23,25,31,37–38,44, etc.) — *off the fixed scale.* Hi-fi requires the spacing to be deliberate and on-token (one allowed exception, see §4).
- **Decorative / inconsistent dots & icons.** The `wk-pill` dinner icon (a fork glyph) appears only on some rows; icons aren't anchored consistently. The constraint: *meaningful dots only; icons anchored consistently (top-left).*
- **Microcopy is half functional, half chatty.** Good: "Low till Thursday." Bad: dormant note *"Quiet for 5 days — needs some love."* (narrated, motivator fluff), `fc-pin-seen` serif "seen" lines, `groc-trip` "Set next grocery trip →". The rule: **system text = terse data/labels; only genuinely user-authored content is natural (and still short).**

---

## 3. Per-section resolution

Format per section: **Keep / Layout & grid / Components / Type roles / Spacing / Colour / States / Interaction / Microcopy.** Class names are the current ones unless a new `fc-` is named.

---

### 3.0 Top bar (`.td-bar`)

- **Keep:** the two-zone split (you on the left as greeting+context, partner heartbeat + your meters on the right), the `fc-pulse` "here now" dot, the logout affordance.
- **Layout & grid:** unchanged flex. But align the right cluster to a single baseline grid: presence block · partner pulse · logout, each separated by `--space-32`. Add a thin `border-left: var(--rule-faint)` + `padding-left: var(--space-24)` before the partner-pulse block so the heartbeat reads as its own zone, not loose text.
- **Components:** your avatar = `.fc-av` ring-hue (forest = your identity hue, fixed). Meters = `.fc-meter-mini` pips. Partner pulse = `.fc-pulse`. These are right.
- **Type roles:** `td-mark` (eyebrow, `text-label`/cond 700/muted) → `td-greet` (`text-h5`/500/primary) → `td-ctx` (`text-meta`/cond 600/muted). Good as-is. **Fix:** `td-ctx` currently mixes weather into the same mono-ish run — keep it, but render the date with the Command Center's *designed two-tier date* treatment is overkill here; instead keep one condensed line but drop the raw "9:41" clock (a static mockup showing a live-looking time reads as fake data). Use `Mon · Jun 8 · 14° light cloud`.
- **Spacing:** replace raw `5px / 6px` margins (lines 23,25,44) with `--space-4`. Bar bottom padding `--space-16`, margin-bottom `--space-24` — keep.
- **Colour:** your hue = `--c-forest` throughout (presence ring, pips). Partner pulse dot = `--c-forest` is wrong — it should read as *presence* not your identity; keep it forest (it's the "alive/here" signal and forest is fine as the calm live colour, NOT sage `--accent` which is reserved for *action*). Do not introduce sage here.
- **States:** presence has two states — **here now** (`fc-pulse` solid dot + "here now") and **away** (dot hollow, `last seen 4m ago` in mono). Render the "here now" state; the away markup should exist commented or as a documented variant.
- **Interaction:** none beyond logout. Static.
- **Microcopy:** `Together` (mark), `Good morning, Alex` (greet), `Mon · Jun 8 · 14° light cloud` (context). Partner pulse: `Partner · here now`. Drop the live clock.

---

### 3.1 R1 · Partner hero (`.fc-partner`) — the bar; refine, don't rebuild

- **Keep:** everything structural. This is the reference.
- **Refinements only:**
  - The third meter "Recovery" `is-faded` + mono `set 2 days ago` — this is the model **stale-state** pattern. Promote its visual language (opacity `0.72` + mono "set Nd ago" note) into the cross-cutting stale pattern (§4) and reuse it for stale corkboard pins and dormant project freshness.
  - `fc-highfive` and `fc-offer` (the goal row): `fc-highfive` is a proper pill button — good. `fc-offer` "+ offer support" is **floating text** — convert it to the same outlined micro-action pill shape as `fc-highfive` (border `--ink-18`, `r-pill`, `font-cond` label, hover → `--accent`). Two peer actions, two peer pills.
  - The `fc-partner-sub` serif italic line is the **rationed human line for this region.** Correct. It is the partner's *own authored* status, so natural language is allowed — keep it short.
- **Type roles / spacing / colour:** already exemplary; leave. Hue = `--c-plum` (partner identity).
- **States:** meters have **fresh** (full opacity, hue pips) and **stale** (`is-faded`). The high-five has **default** and **given** (`.given`, sage tint). Keep both.
- **Interaction:** high-five toggles `.given`; offer-support opens a composer (static: toggle a "offered" pill state).
- **Microcopy:** all good. The directional `↓ / ↑` on meter values is a meaningful glyph, not decoration — keep.

---

### 3.2 R2 · To look forward to (`.fc-plans`, blind enthusiasm)

The blind-enthusiasm mechanic (each partner scores anticipation privately; scores reveal once both are in) is the soul of this section and its states are currently under-resolved.

- **Keep:** 3-up grid, the icon + when + type header, the reveal-on-both-scored concept.
- **Layout & grid:** `repeat(3, 1fr)`, gap `--space-16`. Each `.fc-plan` is a flex column so the enthusiasm block pins to the bottom (`margin-top:auto`) — keep. **Anchor the icon top-left consistently** (it already is; ensure every plan has one).
- **Components:**
  - Icon tile `.fc-plan-icon` — keep (rounded square, hue fill, white stroke icon). **Fix:** it currently uses raw `9px` radius and `--c-current` fill. Use `--radius-md` (6px) for the tile radius (on-token) and keep hue fill.
  - `.fc-plan-type` ("Date" / "Friends") — this is an **identity/status pill**. Keep as outlined pill but move it into the header row next to `fc-plan-when` (currently the type sits in `fc-plan-meta` text AND there's a separate `.fc-plan-type` rule that's unused in markup — reconcile to ONE: a small outlined pill in the header).
  - The enthusiasm block needs **three explicit, designed states** (this is the key fix):
- **Type roles:** `fc-plan-when` (`text-label`/cond 700, hue `--c-ochre` for time-warmth) → `fc-plan-name` (`text-body`/600/primary) → enthusiasm sub-block. Good ranking; keep.
- **Spacing:** card padding `--space-16`; enthusiasm block separated by `border-top: var(--rule-faint)` + `padding-top: var(--space-12)`. Keep. Remove the stray empty line in the third card's markup (line 298).
- **Colour:** each plan carries a hue on its icon tile only (rose/amber/teal as identity). The enthusiasm numbers stay neutral mono — do NOT colour the scores by sentiment (no green-good/red-bad; this is anticipation, not performance).
- **States — resolve all three explicitly (the core deliverable here):**
  1. **Awaiting your score** (`.fc-enth-await.you`): dashed-border pill, `--accent` colour, label **"Score this"**. This is *your* action → sage is correct (action/alive). Render one card in this state.
  2. **Awaiting partner** (`.fc-enth-await`, no `.you`): dashed-border pill, muted, label **"Yours in · theirs pending"** — your number shown small + mono, partner slot shows a `mini-av` ghost (hollow `P` avatar) instead of a number. This state is currently MISSING and must be added; it's what makes it "blind."
  3. **Both in / revealed** (`.fc-enth-scores`): the two-column `+N You | +N Partner` with the `fc-enth-div`. Add a one-word **read label** above (`fc-enth-label`): `High anticipation` (both high), `Mixed` (gap ≥4), `Quiet` (both low). This label is *derived data*, terse, not chatty. Keep.
- **Interaction:** clicking the "Score this" pill (state 1) reveals state 3 (existing JS at lines 442–445 does this — keep, but update the revealed markup to include the read label).
- **Microcopy:** `Score this` · `Yours in · theirs pending` · read labels `High anticipation / Mixed / Quiet`. **Remove** any serif "note" under scores unless it's a genuine user comment; the current `fc-enth-note` rule (line 101) is defined but the chatty use should be dropped — reserve it for an actual one-line user reaction, rationed.

---

### 3.3 R3 · Look at this (`.fc-cork`, corkboard)

Currently the weakest "real-data shapes" section: link pins and thought pins look almost identical, and the link-unfurl is a grey placeholder box.

- **Keep:** 4-up grid, the two pin kinds (Link / Thought), the "New" flag, the `add-card` to share.
- **Layout & grid:** `repeat(4, 1fr)`, `align-items: stretch`, gap `--space-16`. Keep. Make all pins equal height (stretch already set).
- **Components — differentiate the two kinds so they read at a glance:**
  - **Link pin:** kind label `LINK` (cond, muted) top-left + a small **link/chain icon** anchored top-left beside it (13px, 1.5 stroke). Title in `--c-denim` (link colour). Then the **unfurl**: replace the generic grey image box with a *resolved* unfurl row — a 1-line favicon-dot + domain in mono + (optional) a thin thumbnail strip only if it carries info. The current `fc-unfurl-img` 60px grey box with a placeholder svg reads as a wireframe; reduce to a tight `fc-unfurl` row: `[favicon dot] every.to` in mono, with the title doing the work. If a thumbnail is shown, it must be a real demo image gradient/tone, not the placeholder icon.
  - **Thought pin:** kind label `THOUGHT` + a small **quote/spark icon** top-left. Title in `--primary` (this is user-authored, natural, short). No unfurl. To fill the height difference vs link pins, a thought pin may carry a tiny mono attribution line (`— you · 2d`) at the bottom so heights resolve.
- **Type roles:** `fc-pin-kind` (`text-label`/cond 700/muted) → `fc-pin-t` (`text-md`/primary; `.link` → `--c-denim`) → unfurl domain / attribution (`text-meta`/mono/muted). Add weight: thought titles can be `text-md`/500 to lift them from plain body.
- **Spacing:** pin padding `--space-16`. "New" flag absolutely positioned top-right at `--space-12`. Keep.
- **Colour:** links = `--c-denim` title; thoughts = `--primary`. "New" flag = `--accent` (it's a *fresh/alive* signal — sage is acceptable as the "newness" marker; this is the one ambient-alive use, consistent with presence). Do not hue-tint whole pins.
- **States:**
  - **New / unseen:** `.fc-pin-new` "New" flag (sage). 
  - **Seen:** no flag; optionally a faint mono `seen 2d` only if it adds info — otherwise nothing (calm).
  - **Empty board:** if no pins, the grid collapses to a single full-width calm-empty (`fc-empty` pattern, §4): icon + "Nothing shared yet · drop a link or a thought." 
- **Interaction:** clicking a link pin → opens (static). The `add-card` "+ Share something" → composer (static).
- **Microcopy:** kinds `LINK` / `THOUGHT`. Flag `New`. **Remove** the serif `fc-pin-seen` chatty line; if attribution is shown use terse mono `— you · 2d`. Add-card: `+ Share something` (keep — it's a genuine invite, short).

---

### 3.4 R4 · Building together (`.fc-projects`, checkpoint arcs)

The arc is a good idea executed roughly: the connector line geometry is fragile (`left:-50%` negative-margin trick), labels wrap unevenly, and the dormant warning is chatty.

- **Keep:** 3-up grid, `--callout` cards with project hue, the checkpoint arc (Design→Demo→Tiling→Paint), the horizon meta, the single dormant case.
- **Layout & grid:** `repeat(3, 1fr)`, gap `--space-16`, equal height. The 4th cell = `add-card` "+ Start something".
- **Components — polish the arc (`.fc-arc`):**
  - Keep the dot-and-connector model but make it robust: each step is `flex:1`, dot centered, connector drawn as a `::before` spanning to the previous dot. The current negative-`left:-50%` works but is brittle; specify it precisely: connector `position:absolute; top:6px; right:50%; width:100%; height:2px` anchored so it always meets the previous dot regardless of label width. Set a fixed dot size (`13px`) and a fixed connector top so they align across cards.
  - **Done** step: filled dot in the project hue (`--c-current`), connector behind it in hue. **Active** step: filled dot in `--accent` (sage = the live/current checkpoint = "alive"), connector up to it in sage, label `--primary`/weight. **Upcoming:** hollow dot `--ink-30`, connector `--ink-12`, label muted.
  - **Meaningful dots only:** these checkpoint dots ARE meaningful (state markers) — correct use.
- **Type roles:** `fc-proj-name` (`text-body`/600/primary) → `fc-proj-horizon` (`text-meta`/mono/muted, e.g. `~10 mo`, `this month`, `slow burn`) → arc labels (`text-label`/cond 700, muted→primary by state).
- **Spacing:** card is `.dash-card--callout` (padding `--space-20`). Arc top margin `--space-20`. Keep.
- **Colour:** each project a distinct hue on its callout edge + done-dots (`--c-clay` kitchen, `--c-denim` taiwan, `--c-amber` studio). Active dot = sage (shared "live" semantics). Dormant warning = `--c-terracotta` — **this is the one reserved terracotta use in the whole page.** Do not use terracotta anywhere else.
- **States:**
  - **Active project:** full opacity, one `.active` (sage) dot.
  - **Dormant project** (`.fc-proj.dormant`): `opacity:0.78` + a terracotta freshness note. **Resolve the note as data, not prose:** replace `"Quiet for 5 days — needs some love."` with a terse stale marker reusing the §4 stale pattern + terracotta: a small terracotta dot + `Dormant · 5d` in `font-cond`/label. No motivator language.
  - **Complete project:** all dots done in hue, no active sage dot, horizon shows `done` — render none here but document the variant.
- **Interaction:** clicking a checkpoint dot could advance (static toggle of `.active`/`.done`); add-card → new project (static).
- **Microcopy:** names + horizons as data. Dormant marker `Dormant · 5d`. Add-card `+ Start something`.

---

### 3.5 R5 · The week, together — left: the week table (`table.ow`)

The table is the most "spreadsheet wireframe" element. Hi-fi means pills do the talking and empties are calm, not dashes.

- **Keep:** the day-rows table, the `wk-pill` system, the energy-forecast header, the "Fill together" action.
- **Layout & grid:** the `.td-coord` 2-col split (`1.2fr 1fr`) — keep; table left, tasklist right. Table inside a plain `.dash-card`.
- **Header (`ourweek-h`):** **normalize to the §4 card-internal header pattern** (eyebrow + bottom rule). Currently it's a bespoke flex row with no rule. Make it: a `coord-h`-style header reading the section name is redundant (the `row-eyebrow` above already names it) — instead the in-card header is the **forecast strip**: `You [Steady]` `Partner [Heavy]` as energy pills + a right-aligned `Fill together` action pill. Give it the standard `padding-bottom:--space-8; border-bottom:rule-faint; margin-bottom:--space-16`.
- **Components:**
  - **Energy pills** (`wk-pill.ec-steady`, `.ec-heavy`): keep — these are *status* pills (forest=steady, amber=heavy). Good colour semantics. Add a third `.ec-low` (teal) and `.ec-light` variant so the vocabulary is complete.
  - **Dinner-together cell:** a `wk-pill` carrying a **mini-avatar** of who's cooking (`A` / `P` / `Both`) — NOT a fork icon on only some rows. Drop the inconsistent fork glyph. The pill = `[mini-av] Alex` / `[mini-av] Partner` / `Both`. "Takeout in" becomes a neutral pill `Takeout` (not floating `ow-tag` text). This makes the column a uniform pill column.
  - **At-home cell:** `wk-pill` `Alex` / `Partner` / `Both`. Uniform pills.
  - **Energy column (NEW, per constraint "pills for dinner/at-home/energy"):** add a per-day energy read as a small pip-meter or `ec-*` pill if data warrants. If it bloats the table, fold energy into the header forecast only (recommended — keep the table to Day · Dinner · At home for calm).
- **Type roles:** `th` (`text-label`/cond 700/muted) · `ow-day` (`text-md`/600/primary) · pills as above · empties (see states).
- **Spacing:** cells `9px 6px` → move to `--space-8 var(--space-4)` range on-token; row rule `1px solid var(--ink-08)` (faint zebra divider) — keep.
- **Colour:** dinner/at-home pills neutral (`--neutral` bg, `--ink-18` border) — they're logistics, they recede. Energy pills carry forest/amber/teal status hue. **No sage in this table** (nothing here is an "action" except the header's Fill-together pill, which is sage — correct).
- **States — the key fix is calm empties:**
  - **Open / unset cell:** NOT a bare "Open" or "—". Use the §4 calm-empty inline treatment: a faint dotted-underline `ow-open` reading `—` is too terse and reads unfinished; instead render `Open` in `--ink-30` `font-cond`/label (quiet, intentional, clearly a settable slot), with a subtle hover that reveals it's clickable (`border-bottom: 1px dashed --ink-18` on hover). Consistency: every empty in the table uses this one treatment.
  - **Set cell:** the pill.
  - **Today's row:** subtle emphasis — `ow-day` for today gets `--accent` left tick or bolder weight (one meaningful marker), so the eye finds "now."
- **Interaction:** clicking an Open cell → assign-to-self/partner picker (static: cycles Open → Alex → Partner → Both → Open). "Fill together" → opens the week composer (static).
- **Microcopy:** days abbreviated (`Mon`…`Sun`), dinner/home as names, empties `Open`. Forecast `Steady / Heavy / Low`. Action `Fill together`.

---

### 3.6 R5 · The week, together — right: the joint tasklist (`.tasks`)

This is where the **notice + claim** foundation lives. Currently the Claim action is small and the claim/done avatar logic is half-built. Make Claim prominent and the lifecycle explicit.

- **Keep:** 2-col task grid, the `ring` checkbox, the `claim-btn`, the `mini-av` for claimed/done, the project pill (`wk-pill.hue`), the `add-card--inline`.
- **Layout & grid:** `.tasks` = `grid-template-columns: 1fr 1fr` gap `--space-8 --space-24`. Each `.task` = ring + body, body holds title + meta row. Keep.
- **Components — make the lifecycle a clear component set:**
  - **Ring** (`.task .ring`): unclaimed = hollow `--ink-30`. Keep. On done = filled forest with check. Keep.
  - **Claim action** (`.claim-btn`): **make it the prominent thing on an unclaimed task.** It's the page's core verb. Keep the outlined sage pill but ensure it reads as the primary affordance in that row: `Claim →`. Sage = action (correct). It is a button, never a status.
  - **Claimed state:** Claim button is **replaced by a `mini-av`** (the claimer's initial) + a status word in `font-cond`/label, e.g. `[A] Doing`. The avatar is who claimed it — *they chose it, it was not assigned.* No sage here anymore (action consumed).
  - **Done state** (`.task.done`): ring filled forest+check, title muted, `mini-av` of who did it + optional `[+ file]` micro-action (convert the current floating `fc-offer` span "+ file" into a proper outlined micro-pill — it's an action). The "file" action = file this completed task into a project.
  - **Project tag:** `wk-pill.hue` carrying the project hue (e.g. Kitchen = `--c-clay`) — keep. This is an *identity* pill.
- **Type roles:** `task-t` (`text-sm`/primary; done→muted) → `task-meta` (`text-label`/cond 700/muted) holding the avatar/claim/tag cluster.
- **Spacing:** task padding `8px 0` → `var(--space-8) 0`; row rule `--ink-08`. Keep.
- **Colour:** Claim = sage. Claimed/done avatars neutral. Done ring forest. Project tag = project hue. **The only sage in the tasklist is the unclaimed Claim button** — once claimed, sage disappears (action resolved). This is the cleanest expression of the notice→claim→done arc.
- **States (render at least one of each):** **unclaimed** (ring hollow + `Claim →`), **claimed** (`[A] Doing`, no claim btn), **done** (`[P]` + muted title + `+ file`), and an **empty** "+ Notice something" inline add-card. Document a **stale-unclaimed** variant (noticed >Nd, nobody claimed): the row gets a faint mono `noticed 3d` — uses the §4 stale pattern, NOT terracotta (terracotta is reserved for the dormant project only).
- **Interaction:** click `Claim →` → row becomes claimed-by-you (`[A] Doing`, button removed). Click ring → toggles done. Both static-JS.
- **Microcopy:** `Claim →` · claimed status `Doing` · `+ file` · header **"Things that need doing"** is fine (a real label) OR tighten to `To do, together`. Add-card `+ Notice something` (genuine invite — keep). **Remove** any chatty status.

---

### 3.7 Right sidebar (`.td-rail`) — the quietest column; raise density & componentry

Three cards: **For you** (open loops + worth-noticing), **Private notes**, **Groceries**, under a `rail-tabs` segmented control. Currently the densest wireframe-feel: open loops are undifferentiated text rows.

- **Keep:** the column as the personal/quietest layer, the `rail-tabs` segmented control (Your panel / Calendar), the three cards, the `amb` worth-noticing rows with avatar+icon+ago, the groceries checklist.
- **Segmented control:** `rail-tabs` should reuse the **Command Center's `.status-sel` / `.status-opt`** pattern exactly (it's the canonical segmented control in the system) rather than a bespoke `rail-tab`. Same look, one component. This is a direct hi-fi consistency win.
- **For you — Open loops:** currently bare `you-item` text rows + an inconsistent `you-item-m` meta. Resolve each loop as a **structured row**: `[type icon, top-left, 13px] title` + optional meta pill (`by Fri`, `only you`). The "only you" / "by Fri" become small outlined meta pills, not floating mono text. Rank: title `text-sm`/primary, meta as pill. This is the §4 component-row pattern.
  - **Empty:** "No open loops" calm-empty.
- **For you — Worth noticing (`.amb`):** keep the `[mini-av] [icon] text … [ago]` row — it's already a good component row (avatar = who, icon = kind, mono ago = when). **Fix:** anchor the kind-icon consistently and ensure every row has avatar+icon+ago in the same columns (currently consistent — preserve it). This row is actually close to hi-fi; use it as the template for the open-loops rows above.
- **Private notes:** `note` rows are user-authored, natural, short — correct. Add a faint mono `· just you` privacy marker is already in the header (`Private notes · just you`) — keep there, not per-row. Each note row keeps `border-bottom: --ink-08`.
- **Groceries:** checklist with `box` checkboxes — keep. **Fix microcopy:** `groc-trip` "Set next grocery trip →" → terser `+ Next trip` (or `Plan a run`). Drop the arrow if it's not navigation.
- **Type roles (whole rail):** card header = `rail-sec-h` → normalize to §4 header pattern (eyebrow + rule). Sub-headers `sub-h` (cond label, muted) → keep but ensure consistent `--space-20 0 --space-8` rhythm. Row titles `text-sm`/primary; meta mono/muted or meta-pills.
- **Spacing:** rail gap `--space-16` between cards. Card padding `--space-20`. Row padding `8px 0` → `var(--space-8) 0`. Remove raw `7px / 9px` (lines 193,202) → `--space-8`.
- **Colour:** the rail is the quietest column — mostly neutral. Avatars = neutral. The only colour: worth-noticing kind-icons may take a faint hue matching their source (a corkboard-link notice → denim icon), but default to `--muted`. No sage except a genuine action (the add affordances). Keep it calm.
- **States:** each card has a **filled** and a **calm-empty** (§4). Groceries items: **unchecked / checked** (`.done`, strikethrough) — keep.
- **Interaction:** tabs switch panel/calendar (static). Grocery rows toggle done. Open-loop rows are navigational (static).
- **Microcopy:** `For you` · `Your open loops` · `Worth noticing` · `Private notes · just you` · `Groceries` · `+ Next trip`. Open-loop meta pills: `by Fri`, `only you`. All terse.

---

## 4. Cross-cutting hi-fi standards (apply everywhere)

These are the reusable decisions. Define once; reuse across all sections. New page-scoped widgets are `fc-` prefixed and tokens-only.

### 4.1 The pill system — three semantic classes (resolve the current ad-hoc pills)
| Role | Looks like | Colour | Examples |
|---|---|---|---|
| **Action pill** | outlined, hover→fill | `--accent` (sage) ONLY | `Claim →`, `Score this`, `Fill together`, `+ file`, high-five, offer-support |
| **Status pill** | tinted bg + matching border | semantic hue (forest=steady/good, amber=heavy, teal=low) | energy `Steady/Heavy/Low`, plan read-label, `Doing` |
| **Identity pill** | neutral bg, neutral border, OR hue text if tagging | `--neutral`/`--ink-18`, or project hue | project tags (`Kitchen` clay), plan type (`Date`), dinner/at-home names |

Rule: **sage means action, full stop.** A pill is never sage unless clicking it does something. Status/identity pills never use sage. Radius `--r-pill` (4px). Font `--font-cond` / `--text-label` / 700 / `0.06–0.1em` tracking / uppercase.

### 4.2 Avatar spec
- **Sizes:** `fc-av` 40px (presence), `fc-av.lg` 48px (partner hero), `mini-av` 20px (inline: claimed/done/notice rows).
- **Form:** circle, `--neutral` bg, `--ink-18` border, initial in `font-cond`/700/`--secondary`.
- **Ring-hue variant** (`ring-hue`): identity ring in the person's hue (you=forest, partner=plum) — only on the two top-level presence/hero avatars, not inline ones.
- **Ghost avatar** (NEW, for blind-enthusiasm "pending"): hollow circle, dashed `--ink-18` border, muted initial — means "they haven't acted yet."
- **Semantics:** an avatar always answers *who* (claimed/did/noticed/here). Avatars never imply assignment — they appear only after a person *acts*.

### 4.3 Icon spec
- **Size:** 13px inline (rows, pills), 19–22px in icon tiles (plan/app tiles).
- **Stroke:** `fill:none; stroke:currentColor; stroke-width:1.5; stroke-linecap/linejoin:round`.
- **Anchor:** **top-left of its container, always.** Same baseline as the text it labels.
- **Use only meaningful icons:** kind markers (link/thought), plan-type tiles, notice-kind icons. No decorative icons. No icon if a label already carries the meaning.

### 4.4 Meaningful-dots rule
Dots are only: meter pips (`fc-pips`), checkpoint dots (`fc-arc-dot`), presence/pulse dot (`fc-pulse::before`), the one terracotta dormant dot. **No decorative dots, no bullet dots before list items** (the Command Center `nx::before "→ "` is its idiom; here use clean rows with rules instead).

### 4.5 The empty-state pattern (`fc-empty`)
One pattern for all calm-empties: centered or inline, `--ink-30` text, `font-cond`/label or `text-sm`, optional 16px muted icon, a terse settable invite. Examples: table `Open` cells, empty corkboard, empty open-loops. **Never** a bare `—` or a chatty sentence. An empty state is a quiet invitation, on-token, clearly intentional.

### 4.6 The stale-state pattern
Reuse R1's `is-faded` model everywhere staleness matters: `opacity:0.72` (or just a marker on dense rows) + a mono `set/noticed/quiet Nd` note in `--text-meta`/muted. **Terracotta is NOT part of this pattern** — terracotta is reserved for the single dormant-project warning. Everything else stale = faded + mono note, neutral.

### 4.7 Type-role table (role → token → weight → colour) — the spine of hi-fi
| Role | Token | Weight | Colour | Family |
|---|---|---|---|---|
| Hero name | `--text-h4` | 600 | `--primary` | Barlow |
| Card/object title | `--text-body` | 600 | `--primary` | Barlow |
| Primary line / item | `--text-sm` | 400 | `--primary` | Barlow |
| Secondary line | `--text-sm` | 400 | `--secondary` | Barlow |
| Section/card header (eyebrow) | `--text-label` | 700 | `--muted` | Barlow Condensed, 0.12–0.16em, uppercase |
| Pill / label | `--text-label` | 700 | per §4.1 | Barlow Condensed |
| Meta / date / id / count | `--text-meta` | 400/500 | `--muted` | JetBrains Mono |
| The one human line per region | `--text-h5`–`--text-h3` | italic | `--secondary`/`--primary` | Instrument Serif |

**Rule:** every text node maps to exactly one row. No text uses `--text-tiny`/`--text-micro` (10/9px) — even though the Command Center uses `--text-tiny` in a couple places, the stated constraint is **no 9/10 for text**; the smallest text token here is `--text-label` (11px). Replace any inherited `--text-tiny`/`--text-micro` usage in the Together file with `--text-label`. Mono = identifiers/dates/numbers only. Serif italic = one rationed human line per region (partner sub, optionally one elsewhere) — never for labels or system text.

### 4.8 Card-internal header pattern (ONE pattern, replaces the current four)
Every in-card section header is: an **eyebrow** (`--font-cond` / `--text-label` / 700 / 0.12em / uppercase / `--muted`) + `padding-bottom: var(--space-8)` + `border-bottom: var(--rule-faint)` + `margin-bottom: var(--space-16)`. Model it on Command Center's `.cc-card-h`. Retire the bespoke `ourweek-h` (no rule), and align `coord-h`, `rail-sec-h`, `fc-psec-h` to this one shape (R1's `fc-psec-h` may keep its rule-less inline form inside the multi-column hero, since dividers there are the column borders — that's its documented exception).

### 4.9 Spacing rhythm
- **Card padding:** `--space-20` default (matches `.dash-card`); `--space-16` for dense object cards (plans, pins); `--space-24` only for the hero head.
- **Gaps:** section-to-section in main = `--space-32`; within-row card gaps = `--space-16`; intra-card row gaps = `--space-8` / `--space-12`.
- **Eliminate raw px** (`5/6/7/9px` at lines 23,25,31,37,38,44,70,73,166,167,193,202, etc.): map to `--space-4` / `--space-8` / `--space-12`. **One sanctioned sub-token exception:** pip/dot internal sizes and 2–3px gaps inside meters/arcs may stay raw (they're geometric primitives, not layout spacing) — document them as such in a comment.

---

## 5. Execution order (wireframe → hi-fi checklist)

Do these top-down; each is one pass. Earlier items unblock later ones.

1. **Lock the cross-cutting standards (§4) as the first edit** — add/normalize the pill classes (action/status/identity), the `fc-empty` and stale patterns, the single card-header pattern, and purge `--text-tiny/micro` + raw-px spacing. Everything below depends on these.
2. **Sidebar segmented control + Command Center parity** — swap `rail-tab` for `.status-sel/.status-opt`; this proves the system-reuse standard and is low-risk.
3. **Tasklist (R5 right)** — make Claim prominent (action pill), build the unclaimed→claimed(avatar+Doing)→done(avatar+file) lifecycle, convert floating `+ file` to a micro-pill, add the stale-unclaimed variant. (Core foundation; highest semantic value.)
4. **Week table (R5 left)** — uniform pill columns (dinner/at-home with mini-avatars, `Takeout` pill), drop the inconsistent fork icon, calm `Open` empties, today-row marker, normalized header forecast strip.
5. **Blind enthusiasm (R2)** — build all three explicit states (Score this / Yours-in-theirs-pending with ghost avatar / revealed + read-label); fix icon-tile radius to `--radius-md`; reconcile the duplicate plan-type into one header pill.
6. **Corkboard (R3)** — differentiate Link vs Thought pins (kind icon top-left, denim vs primary title), replace the grey unfurl placeholder with a tight favicon+domain row (real demo tone if thumbnail), add thought-pin attribution, calm-empty board, drop serif "seen" line.
7. **Projects (R4)** — robustify the arc connector geometry, lock dot/connector tokens, sage active dot, convert the dormant prose to the `Dormant · 5d` terracotta marker (the one reserved terracotta use).
8. **Sidebar For-you open loops** — restructure as component rows (kind icon + title + meta-pills) matching the worth-noticing row template; calm-empties; tighten `groc-trip` microcopy.
9. **Top bar** — add the heartbeat-zone divider, drop the fake live clock, on-token the raw margins, render here-now state (document away variant).
10. **R1 Partner hero — final pass (refine only)** — convert `+ offer support` to a peer action-pill; confirm it remains the only `--callout` hero, the only rationed serif line, the only L1 element; verify it still out-ranks everything after the rest came up to level.
11. **Microcopy sweep (whole page)** — terse/functional pass: remove "…yet"/motivator fluff/narrated prose/redundant "the"; confirm only genuinely user-authored content (corkboard thought, partner status, private notes) is natural and short.
12. **Final hierarchy audit** — squint test: exactly one loud thing (R1); each card has a drawn header + ranked type; no floating text; sage only on actions; one terracotta use; all empties calm; spacing on-token. If any card still reads as one flat plane, it's not done.

---

## 6. Guardrails (do not violate)

- **Tokens only.** No raw hex; spacing on the fixed scale (one geometric-primitive exception, §4.9); type tokens only, none below `--text-label` (11px) for text.
- **Sage `--accent` = action/alive; identity hues `--c-*` = identity. Never mix.** Sage appears only on actionable affordances and the ambient "alive/new/here" markers (presence pulse, "New" flag, active checkpoint) — and nowhere else.
- **`--c-terracotta` is reserved** for the single dormant-project warning. One use in the whole page.
- **Notice + claim only — nothing is ever assigned between partners.** Claim is an action (button); claimed/done show the actor's avatar (they chose it).
- **Reuse first:** `.dash-card` / `--callout`, `.status-sel/.status-opt` segmented control, avatars, the pill/icon/empty/stale patterns. New widgets are page-scoped `fc-`, tokens-only, and must be justifiable as not-expressible by an existing component.
- **Static mockup:** light demo JS only (toggles/reveals as the current file does). Document-flow in a 1200px centered container; no fixed/sticky.
- **One human line per region, rationed.** Instrument Serif italic for genuinely-authored content only.
- **Three-way linkage does NOT apply here** — this is an Applied *concept* showcase page (`site/_design-system/showcase/`), not a CMS block. It touches no `docs/BLOCKS.md` contract, no `site/_templates/`, no `site/cms/` editor view. Resolve it in-file.
