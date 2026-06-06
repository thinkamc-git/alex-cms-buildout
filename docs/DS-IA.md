# Design System Showcase — Information Architecture

**Status:** proposal for review (2026-06-05). Grounds the `/_ds/` showcase IA in
how established design systems are actually organized, rather than ad-hoc.

---

## 1. How real design systems are organized (research)

Looked at Shopify Polaris, IBM Carbon, Atlassian, and Material Design 3.

**Top-level spine is near-universal** — the same 3 sections appear everywhere,
in this order, getting more composed as you go:

| Real systems | Top-level sections |
|---|---|
| **Polaris** | Foundations · Content · Components · Patterns |
| **Carbon** | Foundations · Components · Patterns · Guidelines |
| **Atlassian** | Foundations · Components · Patterns · Content |
| **Material 3** | Foundations/Styles · Components · Guidelines |

→ The convention: **Foundations → Components → Patterns → (Content/Guidelines) → Resources.**
Foundations is *always its own top section* (it's the tokens reference everyone
opens first); it is never nested under Components.

**Components are sub-grouped by FUNCTION, not by file or by visual type.**
Material 3's canonical groups (echoed across systems):

> **Actions · Communication (feedback) · Containment · Navigation · Selection · Text inputs**

The guiding principle from every source: **group by what a user looks for / by
use-case**, with clear labels — not by implementation detail.

Sources: [Polaris](https://polaris-react.shopify.com/), [Material 3 Components](https://m3.material.io/components), [Atlassian](https://atlassian.design/components), [Carbon](https://carbondesignsystem.com/), [Sparkbox — layers of a design system](https://sparkbox.com/foundry/design_system_makeup_design_system_layers_parts_of_a_design_system), [UXPin — documentation guide](https://www.uxpin.com/studio/blog/design-system-documentation-guide/).

---

## 2. Audit of our current IA

Current tabs: **Components (Foundations + Elements + Cards) · Sections · Templates · CMS · CSS Library**.

| Item | Verdict |
|---|---|
| "Patterns"/"Pages" as names | ✅ on-convention (we're renaming Sections→Patterns, Templates→Pages) |
| **Foundations merged into Components** | ⚠️ **off-convention** — every major system keeps Foundations its own top section |
| Components grouped as "Elements + Cards" | ⚠️ ad-hoc — convention groups components **by function** (Actions / Inputs / Status / Containment / Navigation); Cards = a Containment group |
| **CMS** as its own top section | ➕ non-standard but **justified** — we have two products (public site + admin); keeping the admin DS separate is a deliberate, defensible split |
| **CSS Library** (by CSS file) | ➕ non-standard — real systems expose code via a per-component "Develop" tab or a "Resources/Code" area, not a file-indexed tab. Keep it as our dev lens, but know it's an addition, not a convention |

---

## 3. Recommended IA (6 tabs — at the cap, convention-grounded)

| # | Tab | Sub-nav (left) | Basis |
|---|---|---|---|
| 1 | **Foundations** | Colour · Typography · Spacing · Radii & Elevation · Motion | universal |
| 2 | **Components** | **Actions** (Buttons & CTAs) · **Inputs** (Form Fields · Filter Controls) · **Status & Feedback** (Pills/Badges · Tags · Category Labels) · **Navigation** (Nav · Top Bar) · **Containment** (Content Cards · Dividers & Headers) | Material-style functional grouping |
| 3 | **Patterns** | Heroes & Statements · Content Blocks · Page Chrome (Top Bar/Footer) · Dividers | composed page sections (was "Sections") |
| 4 | **Pages** | Marketing (Landing · Services · About · Resume) · Content (Article · Index · Editorial) | full page templates (was "Templates") |
| 5 | **CMS** | Actions · Inputs · Status & Stage · Containment (Tables · Cards) · Assembled Layout | the admin mini-DS, by function; one shared source w/ the in-CMS viewer |
| 6 | **CSS Library** | Root · Pages · Blocks · CMS | the technical/by-file lens (our addition) |

**Key decision:** keep **Foundations** as its own top tab (convention) vs. the
current merge into Components. Recommendation: **separate** — it's what every
real system does and it's the most-referenced page.

**Open naming choices:** Patterns vs "Page Blocks"; whether "Content Cards" sit
under Components/Containment (recommended) or get their own tab.
