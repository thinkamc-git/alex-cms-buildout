---
version: 1.0
name: Alex M. Chong
url: alexmchong.ca
description: >
  Personal brand system for a Toronto-based Director of UX, design leader,
  coach, and educator. The visual register is craft-forward editorial minimalism
  with a late 80s/early 90s technical document influence — warm dot-grid paper,
  near-black ink, a single muted sage accent, and a grotesque/classical-italic
  type pairing. Structure and proportion do the expressive work. Decoration is
  earned, not assumed.
tokens:
  # ── Base palette ──
  --primary:    "#191715"   # near-black, all ink (slightly more neutral than original #1A1714)
  --secondary:  "#494846"   # body copy
  --muted:      "#818080"   # metadata, de-emphasis, muted-word
  --neutral:    "#E8E8E8"   # body / page background
  --canvas-bg:  "#F3F2F1"   # mid-gutter surface inside canvas
  --surface:    "#F8F8F8"   # card / elevated container background
  --accent:     "#6B7F6E"   # sage green, only chromatic note
  --white:      "#FFFFFF"   # text on dark surfaces

  # ── Mid-grey ramp (between secondary and muted) ──
  --ink-mid:        "#646361"
  --ink-faint:      "#716F6E"
  --primary-hover:  "#333333"  # btn-dark hover

  # ── Rule weights ──
  --rule:       "1px solid #1A1714"
  --rule-30:    "1px solid rgba(26,23,20,0.3)"
  --rule-faint: "1px solid rgba(26,23,20,0.18)"

  # ── Category colour pool — 18 hues at one saturation/darkness level ──
  # Names describe the colour, never the category. Categories pick from this
  # pool via .card[data-category=…] { --c-current: var(--c-…); } mapping.
  --c-rust:       "#765150"   # hue 5°
  --c-terracotta: "#7D4631"   # hue 15°
  --c-clay:       "#765E44"   # hue 35°
  --c-amber:      "#81642A"   # hue 38°
  --c-ochre:      "#786E4A"   # hue 50°
  --c-olive:      "#6E7448"   # hue 75°
  --c-moss:       "#607549"   # hue 100°
  --c-forest:     "#49634B"   # hue 125°
  --c-sage:       "#4D705A"   # hue 145°
  --c-teal:       "#4A716E"   # hue 175°
  --c-ocean:      "#4A6677"   # hue 200°
  --c-denim:      "#46556A"   # hue 213°
  --c-indigo:     "#4D567A"   # hue 235°
  --c-purple:     "#5D5376"   # hue 257°
  --c-violet:     "#6C4D7A"   # hue 280°
  --c-plum:       "#785071"   # hue 305°
  --c-mauve:      "#6F4B61"   # hue 320°
  --c-rose:       "#7A5160"   # hue 345°

  # ── Experiment card backgrounds (deep tonal solids) ──
  --c-experiment-prototype: "#1C1028"
  --c-experiment-concept:   "#0A1F1A"
typography:
  display:
    fontFamily: Barlow
    fontSize: 54px
    fontWeight: 500
    lineHeight: 66px
    letterSpacing: -0.02em
    note: Weight 500 not 700. Power comes from scale, not stroke weight.
  h1:
    fontFamily: Barlow
    fontSize: 40px
    fontWeight: 600
    letterSpacing: -0.015em
    lineHeight: 1.15
  h2:
    fontFamily: Barlow
    fontSize: 32px
    fontWeight: 600
    letterSpacing: -0.01em
    lineHeight: 1.2
  h3:
    fontFamily: Barlow
    fontSize: 24px
    fontWeight: 600
    lineHeight: 1.3
  body-lg:
    fontFamily: Barlow
    fontSize: 20px
    fontWeight: 500
    lineHeight: 1.7
  body-md:
    fontFamily: Barlow
    fontSize: 16px
    fontWeight: 500
    lineHeight: 1.65
  body-sm:
    fontFamily: Barlow
    fontSize: 15px
    fontWeight: 500
    lineHeight: 1.5
  nav-button:
    fontFamily: Barlow
    fontSize: 13px
    fontWeight: 600
    letterSpacing: 0.02em
  label-condensed:
    fontFamily: Barlow Condensed
    fontSize: 12-13px
    fontWeight: 700
    letterSpacing: 0.12-0.18em
    textTransform: uppercase
    note: Pills only. Never use for section headers or body text.
  serif-hero:
    fontFamily: Instrument Serif
    fontStyle: italic
    fontWeight: 400
    fontSize: 1.15em relative to context
    note: >
      Inline within Barlow display copy only. Use display:inline-block to
      prevent descender clipping. Never below 22px. Never bold. Never upright.
      Never light-coloured (muted is off-limits). Maximum 2 instances per page.
  serif-section-intro:
    fontFamily: Instrument Serif
    fontStyle: italic
    fontWeight: 400
    fontSize: 24-28px
    color: secondary
    note: First sentence of a major section. Minimum 22px.
  serif-pull-quote:
    fontFamily: Instrument Serif
    fontStyle: italic
    fontWeight: 400
    fontSize: 22px
    color: primary
  serif-journal-title:
    fontFamily: Instrument Serif
    fontStyle: italic
    fontWeight: 400
    fontSize: 21px
    color: primary
    note: Journal card titles only. Must be visibly larger than surrounding body text.
  muted-word:
    note: >
      Same font, size, and weight as surrounding text. Color #81807f only.
      Used to create interior rhythm within hero/display statements — the
      technique that gives depth without adding hierarchy. Not decoration.
rounded:
  card: 4px
  button: 4px
  pill: 3px
  note: Near-square throughout. Technical manual aesthetic. No pill-radius on buttons.
spacing:
  base: 8px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 32px
  xl: 64px
  section: 96px
  card-pad: 24px
  max-width: 1400px
background:
  color: "#E8E8E8"
  texture: SVG dot grid inline
  dot-color: "#b8b6b2"
  dot-opacity: 0.15
  grid-size: 4px
  dot-radius: 1.5px
  note: >
    Dot grid is subtle — texture not pattern. The warmth comes from the paper,
    not from rounded corners or decorative elements. Apply to body, topbar,
    sidebar, and CMS sidebar.
components:
  button-primary:
    background: "#1A1714"
    color: "#F8F8F8"
    border: "1px solid #1A1714"
    borderRadius: 4px
    padding: "11px 24px"
    font: Barlow 14px 600
    letterSpacing: 0.02em
    hover: background #333
  button-secondary:
    background: "#F8F8F8"
    color: "#1A1714"
    border: "1px solid #1A1714"
    borderRadius: 4px
    padding: "11px 24px"
    font: Barlow 14px 600
    hover: background primary, color surface
  button-underline:
    background: transparent
    border: none
    borderBottom: "1.5px solid #1A1714"
    paddingBottom: 2px
    font: Barlow 14px 600
    letterSpacing: 0.01em
    note: Tertiary CTA. No box. Bold weight maintains authority.
  nav-pill:
    background: "#F8F8F8"
    border: "1px solid #1A1714"
    borderRadius: 4px
    padding: "8px 18px"
    font: Barlow 13px 600
    letterSpacing: 0.02em
    hover: background primary, color surface
  filter-pill:
    background: surface
    border: "1px solid #1A1714"
    borderRadius: 3px
    font: Barlow Condensed 12px 700 uppercase
    letterSpacing: 0.14em
    hover: border and text turn to category colour
    active: filled category colour background, white text, no border change needed
    note: OR multi-select logic. Selecting All deselects everything else.
  card:
    background: "#F8F8F8"
    borderRadius: 4px
    border: "1px solid rgba(26,23,20,0.18)"
    boxShadow: none
    hover: border-color #1A1714
    padding: 24px
    note: >
      No shadow. Border defines the edge. Hover darkens border to full primary.
      This is a document entry, not a floating card.
  card-category-label:
    font: Barlow Condensed 12px 700 uppercase
    letterSpacing: 0.14em
    color: semantic category colour
    background: none
    note: Text only. No pill box. Colour carries the identity.
  card-meta-strip:
    borderTop: "1px solid rgba(26,23,20,0.18)"
    padding: "11px 24px"
    font: Barlow Condensed 13px 600 uppercase
    color: muted
    layout: date/info left, arrow right
    note: >
      Unified across articles, journal, and experiments. Same position,
      same reading pattern. Article: "Mar 4, 2026 · 14 min". Journal: date.
      Experiment: dark surface version with action label.
  card-footer-pattern:
    note: >
      Tags anchor to bottom of card-body via push-divider (spacer, no line).
      Date in meta-strip. Arrow always in meta-strip at right. No arrow inside
      card-body. This is consistent across all card types.
  event-format-tags:
    font: Barlow Condensed 13px 700 uppercase
    borderRadius: 3px
    padding: "3px 10px"
    display: inline-flex align-items center
    lineHeight: 1.4
    variants:
      free:      "color #246636, bg rgba(36,102,54,0.08), border rgba(36,102,54,0.24)"
      paid:      "color primary, bg rgba(26,23,20,0.06), border rgba(26,23,20,0.2)"
      in-person: "color #6B4010, bg rgba(107,64,16,0.07), border rgba(107,64,16,0.2)"
      remote:    "color secondary, bg rgba(26,23,20,0.04), border rgba(26,23,20,0.14)"
      countdown: "color white, bg #223D88, no border"
      past:      "color rgba(255,255,255,0.65), bg rgba(26,23,20,0.45), border rgba(255,255,255,0.15)"
  series-pill:
    font: Barlow Condensed 13px 600 uppercase
    borderRadius: 4px
    border: category colour at 35-40% opacity
    color: category colour
    background: transparent
  badge-principle-framework:
    font: Barlow Condensed 13px 600 uppercase
    borderRadius: 4px
    border: category colour at 40% opacity
    color: category colour
    background: transparent
    alignSelf: flex-start
  journal-ruled-quote:
    borderLeft: "2px solid category-colour"
    paddingLeft: 16px
    font: Instrument Serif italic 21px
    color: primary
    note: The left rule colour is driven by journal category (introspection/contemplation/insight).
  topbar-brandtitle:
    composition: "Brand wordmark + document type, separated by a vertical rule from the tab list."
    wordmark: Barlow 14px 500 (lowercase 'alex m. chong')
    docType: Instrument Serif italic, 1.18× wordmark size (lowercase 'design system')
    separator: rule-30 vertical, 32px right padding
    note: >
      Sits at the leftmost edge of the topbar. The Instrument Serif italic
      counts as one of the two allowed serif instances on the page.
  group-header:
    composition: "Condensed eyebrow on the left, optional 'View all →' link on the right."
    layout: flex, space-between, align-items center, margin-bottom var(--space-20)
    eyebrow: Barlow Condensed 11px 700 uppercase, letter-spacing 0.2em, color var(--c-current, var(--primary))
    link: Barlow Condensed 11px 700 uppercase, letter-spacing 0.14em, color var(--primary), opacity-fade hover
    tinting: "style=\"--c-current: var(--c-denim)\" tints the eyebrow to a category colour."
    note: >
      Used inside index/listing layouts (Editorial Index) to title a row of
      cards with an optional view-all action. Replaces inline-styled custom
      eyebrows. Documented in Components > Sections, Headers & Dividers.
  layout-nav-links:
    hover: background var(--primary), text var(--surface), 0.15s ease fade
    note: Top-of-page nav link state for layout chrome.
  layout-nav-logo:
    minWidth: 100px
    note: Prevents wordmark collapse in narrow nav bars.
---

## Overview

This is a practitioner's personal brand system — not a product UI kit and not a portfolio template. The visual language is precise and restrained in the Bauhaus tradition: every element earns its place through function, not decoration.

The key tension in the system is **Barlow (grotesque muscle) vs Instrument Serif (classical whisper)**. Barlow carries everything structural. Instrument Serif appears only where the human voice needs to break through — hero statements, section intros, journal entries, pull quotes. These are the only moments of softness in an otherwise disciplined document.

The late 80s/early 90s technical manual influence means: near-square corners, rules as structural elements not decoration, monospace for system identifiers, and a dot-grid paper texture that makes the surface feel tactile rather than screened.

## Colors

**The palette is warm neutrals with one chromatic note.**

**Always reference colours by token (`var(--primary)`), never by hex literal.** The Components > Colors tab is the canonical reference for all tokens.

- `--primary` `#191715` — Near-black, slightly more neutral than the original warm cast. Ink, not screen. Used for all headlines, primary text, buttons, rules, and borders. Never substitute with pure `#000000`.
- `--secondary` `#494846` — Mid-dark gray. Body copy, descriptions, secondary text.
- `--muted` `#818080` — The muted-word colour. Used for de-emphasised text within a statement, metadata, and labels. Not as light as a disabled state — it's still readable, just stepped back.
- `--neutral` `#E8E8E8` — Body / page background.
- `--canvas-bg` `#F3F2F1` — Mid-gutter surface inside the canvas (around preview frames).
- `--surface` `#F8F8F8` — Card and elevated container background. Slightly lighter than neutral.
- `--accent` `#6B7F6E` — Muted sage green. The only chromatic colour in the base UI. Used for inline text highlights and link emphasis only. Never for buttons, backgrounds, or decorative elements.
- `--white` `#FFFFFF` — Text on dark surfaces (event card headers, masterclass gradient, experiment cards).
- `--ink-mid` `#646361`, `--ink-faint` `#716F6E` — Mid-grey ramp between secondary and muted; for text inside cards, ghost numerals, fine de-emphasis.
- `--primary-hover` `#333333` — Lifted primary for the dark button hover state.

### Rule hierarchy
Three line weights, all from the same primary colour:
- `--rule` (`1px solid #1A1714`) — Full ink. Structural: doc-labels, CMS borders, swatch borders, table headers.
- `--rule-30` (`rgba(26,23,20,0.3)`) — 30% primary. Tabs bar, page dividers.
- `--rule-faint` (`rgba(26,23,20,0.18)`) — 18% primary. Card borders, table row separators, type row dividers.

**Line rule:** Choose either line-weight (thin vs thick) OR line-darkness (full vs faint) to communicate. Never both simultaneously — thick + grey is the worst of both worlds.

### Category colour pool

A pool of **18 chromatic colours**, all at the same restrained saturation/darkness level. Tokens are named by the colour itself, not by any specific category. Categories pick from this pool via the `.card[data-category="…"] { --c-current: var(--c-…); }` mapping at the bottom of the cards block — so adding a new category is one line of CSS, and the design system has no opinion about which category gets which hue.

These appear as text colour on category labels inside cards, as background fill on filter pills (active state), and as border/text colour on series pills, framework badges, journal rules, and event-format chips. Use `color-mix(in srgb, var(--c-…) 35%, transparent)` at the call site for tinted variants — don't define new tokens for every category × alpha pair.

```
--c-rust       #765150    --c-sage       #4D705A
--c-terracotta #7D4631    --c-teal       #4A716E
--c-clay       #765E44    --c-ocean      #4A6677
--c-amber      #81642A    --c-denim      #46556A
--c-ochre      #786E4A    --c-indigo     #4D567A
--c-olive      #6E7448    --c-purple     #5D5376
--c-moss       #607549    --c-violet     #6C4D7A
--c-forest     #49634B    --c-plum       #785071
                          --c-mauve      #6F4B61
                          --c-rose       #7A5160
```

**Experiment card backgrounds** (deep tonal solids behind dark previews — not part of the category pool, used only as full-bleed surface):
- `--c-experiment-prototype` `#1C1028`
- `--c-experiment-concept` `#0A1F1A`

## Typography

**Two families, strictly bounded roles.**

### Barlow
The system font. Geometric grotesque, weights 400–700. Available in regular and Condensed. Carries all structural type: headings, body, nav, buttons, labels.

**Weight discipline:**
- 500 for body text (never 400 — it's too thin against the warm background)
- 600 for headings below H1
- 700 for display and nav/button labels only
- Condensed variant reserved for pills only — never section headers, never body

**The muted-word technique:** Key words within hero/display copy are set in the same font, size, and weight as surrounding text — only the color changes to `#81807f`. This creates interior rhythm and depth within a statement without adding hierarchy. It's the primary expressive gesture of the display type system.

### Instrument Serif (italic only)
A classical whisper inside a grotesque document. Appears in:

1. **Hero inline emphasis** — `font-size: 1.15em`, `display: inline-block` to prevent clipping
2. **Section intro sentence** — 24–28px, secondary color, first sentence of a major section
3. **Pull quotes** — 22px, primary color, with left border rule
4. **Journal card titles** — 21px, primary color, with left border rule in category color
5. **About page statements** — large display, primary color

**Hard rules:**
- Never below 22px
- Never `color: var(--muted)` or any light color
- Never bold
- Never upright (italic only)
- Maximum two instances per page
- Always `display: inline-block` when inline to prevent descender clipping

> **Known gap:** Instrument Serif has limited weight support. A more capable serif companion (Fraunces italic is the leading candidate — more optical presence, holds weight at smaller sizes) is a planned future upgrade.

## Layout

**Fixed max-width grid, 1400px centered.**

The 1400px max-width comes from the actual source CSS and creates the edge-to-edge display type effect seen in the reference — text fills the viewport confidently without being cramped.

- Columns: 3-column card grid at desktop, 20px gaps
- Margins: 48px desktop, 20px mobile
- Card padding: 24px (3-col)
- Section padding: 96px vertical
- Base unit: 8px

## Background Texture

SVG dot grid applied inline — no external file dependency.

```
bg: #e8e8e8
dot: #b8b6b2
grid: 4px
radius: 1.5px
opacity: 0.15
```

Applied to: body, topbar, sidebar, `.preview-frame` (the dot-grid container for any rendered preview), and CMS sidebar. The texture is barely-there — it should read as paper, not pattern. Mid-gutter surfaces stay solid `#F3F2F1` so the eye separates preview-content (textured) from documentation chrome (untextured).

## Card System

Four card types, each with distinct structure but unified shell and footer logic.

### Shell (all types)
```css
background: #F8F8F8;
border-radius: 4px;
border: 1px solid rgba(26,23,20,0.18);
box-shadow: none;
```
Hover: `border-color: #1A1714`. No shadow — depth through border contrast only.

### Footer pattern (articles, journal)
Cards follow a strict bottom reading order:
1. Tags (anchored to bottom via spacer — no line above them)
2. Meta strip: `date · readtime` left, `→` right

The spacer (`push-divider`) is height:0 with no background — it's a flex spacer, not a visual element.

### Article cards
- Category label (text only, coloured)
- Title (Instrument Serif italic, 30px)
- Optional: series pill + progress dots, or Principle/Framework badge
- Excerpt
- Tags (bottom-anchored)
- Meta strip: `Mar 4, 2026 · 14 min` → `→`

### Journal cards
- Category icon + label + entry number
- Instrument Serif italic quote with left rule in category color
- Excerpt
- Meta strip: date → `→`

### Event cards
- Dark header (`#1A1714`) with event type label + countdown/past pill
- Location zone (dark, with ghost city text at 68px opacity 5%)
- Date row (Barlow Condensed 20px), time
- Instrument Serif italic title (28px)
- Description, format tags
- Footer: Register / View Recording

**Past events:** Header and location zone at `rgba(26,23,20,0.72)`. Card background `rgba(250,250,248,0.7)`.

**Masterclass:** Gradient header (`#000 → #5b1377 → #a1480c`) with SVG ellipse decoration.

### Experiment cards
- Dark background (`#1C1028` for prototype, `#0A1F1A` for concept)
- SVG geometric illustration (full bleed, behind content)
- Gradient scrim
- Type label + status dot
- Instrument Serif italic title (28px, white)
- Excerpt, tags, date
- CTA bar: `Launch →` or `Read →`

## Filter Pills

**OR multi-select logic.**

Three states:
- **Default:** `surface` background, `1px solid primary` border, primary text
- **Hover:** Border and text turn to category colour
- **Active:** Full category colour background, white text, no border visible

Selecting "All" deselects everything else. Deselecting the last active category re-selects "All".

## CMS Panel

The CMS uses the same design language as the marketing site — not a separate aesthetic.

**Sidebar:** Dot-grid background (same as page), primary text and icons at full opacity, same rule language.

**Table calibrated values:**
- Table header: 11px Barlow Condensed
- Title column: 13px, max-width 216px
- Body cells: 13px
- Category pills: 10px
- Status badges: 9px
- Date column: 12px
- Row action buttons: 11px
- Stat values: 20px
- Stat labels: 11px

## Do's and Don'ts

### Do
- Use `#E8E8E8` as the page background — never pure white
- Use `4px` border-radius on cards and buttons — near-square, not pill
- Let border contrast carry card depth — no shadows on cards
- Use Instrument Serif italic only at 22px+ in primary or secondary color
- Use the muted-word technique for interior rhythm in display copy
- Use category colours in text and as filter pill fills — nowhere else in chrome
- Keep photography black and white or desaturated
- Use `--rule-30` for structural dividers (tabs, page rules) and `--rule-faint` for content separators
- Keep the dot grid subtle — texture registers subconsciously

### Don't
- Don't use Barlow Condensed outside of pills (filter pills, category pills, series pills, format tags)
- Don't use Instrument Serif upright, bold, small, or light-coloured
- Don't add shadows to cards — the system is flat with border contrast
- Don't use pill-radius buttons — everything is near-square
- Don't use `#000` for primary — `#1A1714` has a warmth that matters
- Don't use thick + grey rules simultaneously — pick one axis
- Don't introduce new chromatic colors — the system has one (sage `#6B7F6E`)
- Don't use uppercase tracking on anything outside of condensed pills and functional labels
- Don't exceed two Instrument Serif italic instances per page
