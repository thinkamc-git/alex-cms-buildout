# Portfolio-for-Hire Sandbox

**Source:** https://alexmchong-portfolio.webflow.io/  
**Local entry:** `index.html` (served via `python3 -m http.server 8765` at repo root)  
**Status:** Active sandbox — content will diverge from Webflow as local modifications are made.

---

## What this is

A fully localised snapshot of the Webflow portfolio site, pulled for use as a design reference and content base. The Webflow source remains live and will continue to be updated. This local copy is where we apply our own structural/design modifications — it is **not** a passive mirror.

---

## Asset localisation (what was done on pull)

Images are downloaded and served locally. Framework scripts load from CDN (same as the live site):
- CSS → `assets/alexmchong-portfolio.webflow.*.css` (local)
- All images including srcset variants → `assets/` (local)
- jQuery → CloudFront CDN (framework, not content — loads from CDN for correctness)
- Webflow engine + IX2 animations → Webflow CDN (same reason)
- WebFont loader → Google APIs CDN (same reason)

**Intentionally external** (do not localise):
- Inter font via Google Fonts (loaded by `WebFont.load()`)
- jQuery, webflow.js, webfont.js — framework scripts must load from their CDN URLs to behave correctly (Webflow's CDN serves a site-specific build; local copies break animations and accordions)
- Notion case study links (`alexmchong.notion.site/…`)
- Social/outbound links: Instagram, LinkedIn, Wemakeshift

---

## Re-pulling when Webflow content changes

`webflow-snapshot.html` is the unmodified Webflow HTML from the last pull. Diff against it to see only what Webflow changed — not your local edits mixed in.

**Full workflow and Webflow ID → local asset name mapping: [DELTA-MIGRATION.md](DELTA-MIGRATION.md)**

Quick reference:
```bash
curl -sL "https://alexmchong-portfolio.webflow.io/" -o /tmp/webflow-latest.html
diff /tmp/webflow-latest.html docs/design-mockups/_portfolioforhire/webflow-snapshot.html
# apply changes to index.html, then:
cp /tmp/webflow-latest.html docs/design-mockups/_portfolioforhire/webflow-snapshot.html
```

---

## Local modifications log

Document every deliberate change made to `index.html` or `assets/` here so they survive re-pulls.

| Date | What changed | Why |
|---|---|---|
| 2026-07-06 | All external asset URLs rewritten to `assets/` | Full localisation |
| 2026-07-06 | `webfont.js` restored before `WebFont.load()` call | Animations require WebFont global |
| 2026-07-06 | Webflow badge anchor removed | Not needed locally |
| 2026-07-06 | CDN `preconnect` hints removed | Not needed locally |
| 2026-07-06 | jQuery, webflow.js, webfont.js reverted to CDN URLs | Local copies broke IX2 animations, FAQ accordions, and font loading |
| *(add rows as changes are made)* | | |

---

## Content areas to track for changes

These are the sections most likely to change in Webflow when Alex updates content:

- **Hero / header** — name, role, tagline
- **About / bio** — description copy, profile photo
- **Case studies** — titles, descriptions, Notion links, thumbnail images
- **Client logos** — additions or removals
- **Contact / social links** — email, LinkedIn, Instagram

When re-pulling, prioritise checking these sections in the diff first.
