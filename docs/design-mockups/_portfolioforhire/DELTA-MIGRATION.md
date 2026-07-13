# Delta Migration Guide

When Alex updates content in Webflow, use this guide to identify what changed and apply it to `index.html`. The focus is **content changes** — copy, images, structure. Style changes in Webflow are unlikely to matter since we're maintaining our own CSS.

---

## Workflow: pulling a Webflow delta

```bash
# 1. Fetch the latest Webflow HTML
curl -sL "https://alexmchong-portfolio.webflow.io/" -o /tmp/webflow-latest.html

# 2. Diff against the snapshot (only shows what Webflow changed — not your local edits)
diff /tmp/webflow-latest.html docs/design-mockups/_portfolioforhire/webflow-snapshot.html

# 3. Apply content changes to index.html by hand (see sections below)

# 4. If new images were added, download them to assets/ and give them clean names (see naming table)

# 5. Once applied, update the snapshot so the next diff is clean
cp /tmp/webflow-latest.html docs/design-mockups/_portfolioforhire/webflow-snapshot.html
```

---

## What to look for in the diff

Webflow diffs are noisy. Focus on these content areas — they're what changes:

| Section | What to watch |
|---|---|
| **Hello / hero** | Heading copy, role title, status line ("Open to conversations!") |
| **Profile** | Bio paragraphs, current role/title, company name |
| **Summary** | Year counts (12+, 8, 6), section copy |
| **Specialties** | Titles and descriptions of each specialty card |
| **Brands** | Added or removed brand logos (image src changes) |
| **Case studies** | New cases, updated titles/descriptions, Notion links, thumbnail images |
| **History** | New timeline entries, updated copy for existing entries |
| **Q&A** | New questions, updated answers |
| **Contact** | Email, phone, social links |

Ignore: `data-w-id` attributes, `class` changes, Webflow metadata, `<style>` blocks.

---

## Asset naming: Webflow ID → local name

Webflow serves assets with cryptic IDs (e.g. `637291c71dc615842d9ef90d_profile_copy.jpg`). Our `assets/` folder uses clean names. When a new image appears in the diff, use this table to identify it and give it the right local name.

### Icons

| Webflow filename | Local name |
|---|---|
| `6369cf36535e222ee588f297_smartphone.svg` | `ico-phone.svg` |
| `6369cf36535e2240bc88f2a6_mail.svg` | `ico-mail.svg` |
| `6369cf36535e22afd888f294_map.svg` | `ico-map.svg` |
| `6369cf36535e22b41e88f28f_instagram.svg` | `ico-instagram.svg` |
| `6369cf36535e22fc5a88f299_sunrise.svg` | `ico-sunrise.svg` |
| `637cfbf9eaebe164678252a0_linkedin.svg` | `ico-linkedin.svg` |
| `637564b3e3eef2db0e799cce_menu-icon.svg` | `ico-menu.svg` |
| `637565f901757e048205988e_close-menu.svg` | `ico-close.svg` |

### Brand / identity

| Webflow filename | Local name |
|---|---|
| `637405fce60cea4b8834d7be_signature-short.svg` | `id-signature.svg` |
| `63756c6988df5c275974921b_id-favicon.png` | `id-favicon.png` |
| `6375731356340c3eebb8d727_id-appicon.png` | `id-appicon.png` |

### Client placeholder logos (Clients section)

| Webflow filename | Local name |
|---|---|
| `6369cf36535e22fda288f29e_logo-02.svg` | `DELETED.svg` |
| `6369cf36535e2237e588f2a1_logo-03.svg` | `DELETED.svg` |
| `6369cf36535e224e0788f2a3_logo-01.svg` | `DELETED.svg` |
| `6369cf36535e22d16988f288_logo-04.svg` | `DELETED.svg` |

### Photos

| Webflow filename | Local name |
|---|---|
| `637291c71dc615842d9ef90d_profile_copy.jpg` | `img-profile.jpg` |
| `637291c71dc615842d9ef90d_profile_copy-p-500.jpg` | `img-profile-500.jpg` |
| `637291c71dc615842d9ef90d_profile_copy-p-800.jpg` | `img-profile-800.jpg` |
| `637291c71dc615842d9ef90d_profile_copy-p-1080.jpg` | `img-profile-1080.jpg` |
| `637571a68e20f563229c841d_0N5A3651_copy.jpg` | `img-speaking.jpg` |
| `637571a68e20f563229c841d_0N5A3651_copy-p-500.jpg` | `img-speaking-500.jpg` |
| `637571a68e20f563229c841d_0N5A3651_copy-p-800.jpg` | `img-speaking-800.jpg` |
| `637571a68e20f563229c841d_0N5A3651_copy-p-1080.jpg` | `img-speaking-1080.jpg` |
| `637571a68e20f563229c841d_0N5A3651_copy-p-1600.jpg` | `img-speaking-1600.jpg` |
| `637571a68e20f563229c841d_0N5A3651_copy-p-2000.jpg` | `img-speaking-2000.jpg` |
| `637571a68e20f563229c841d_0N5A3651_copy-p-2600.jpg` | `img-speaking-2600.jpg` |
| `6376ca53f300e3001bf29351_IMG_3509.JPG` | `img-sitting.jpg` |
| `6376ca53f300e3001bf29351_IMG_3509-p-500.jpg` | `img-sitting-500.jpg` |
| `6376ca53f300e3001bf29351_IMG_3509-p-800.jpg` | `img-sitting-800.jpg` |
| `637ae657e002809da3689553_alegs.jpg` | `img-standing.jpg` |
| `637ae657e002809da3689553_alegs-p-500.jpg` | `img-standing-500.jpg` |
| `637ae657e002809da3689553_alegs-p-800.jpg` | `img-standing-800.jpg` |
| `637ae657e002809da3689553_alegs-p-1080.jpg` | `img-standing-1080.jpg` |
| `637ae657e002809da3689553_alegs-p-1600.jpg` | `img-standing-1600.jpg` |

### Case studies

| Webflow filename | Local name |
|---|---|
| `6375b2a67684b4f76d4a9ef9_thumb.png` | `prj-marketplaces.png` |
| `6375b2a67684b4f76d4a9ef9_thumb-p-500.png` | `prj-marketplaces-500.png` |
| `6375b2a67684b4f76d4a9ef9_thumb-p-800.png` | `prj-marketplaces-800.png` |
| `6375b2a67684b4f76d4a9ef9_thumb-p-1080.png` | `prj-marketplaces-1080.png` |
| `6375b2a67684b4f76d4a9ef9_thumb-p-1600.png` | `prj-marketplaces-1600.png` |
| `6375b2a67684b4f76d4a9ef9_thumb-p-2000.png` | `prj-marketplaces-2000.png` |
| `6375b2a67684b4f76d4a9ef9_thumb-p-2600.png` | `prj-marketplaces-2600.png` |
| `6375b71e7684b44df34ad7e8_autodesk.jpg` | `prj-autodesk.jpg` |
| `6375b71e7684b44df34ad7e8_autodesk-p-500.jpg` | `prj-autodesk-500.jpg` |
| `6375b71e7684b44df34ad7e8_autodesk-p-800.jpg` | `prj-autodesk-800.jpg` |
| `6375b71e7684b44df34ad7e8_autodesk-p-1080.jpg` | `prj-autodesk-1080.jpg` |
| `6375b71e7684b44df34ad7e8_autodesk-p-1600.jpg` | `prj-autodesk-1600.jpg` |
| `6375b71e7684b44df34ad7e8_autodesk-p-2000.jpg` | `prj-autodesk-2000.jpg` |

### Brand logos (Brands section)

| Webflow filename | Local name |
|---|---|
| `64b2d14461b743779930d03b_HudsonsBay-logo.png` | `logo-hudsons-bay.png` |
| `637bd39c1702c551f385af31_Walmart.png` | `logo-walmart.png` |
| `637bd39c1702c551f385af31_Walmart-p-500.png` | `logo-walmart-500.png` |
| `637be63cad95f23585eea23a_AutoDesk.png` | `logo-autodesk.png` |
| `637be63cad95f23585eea23a_AutoDesk-p-500.png` | `logo-autodesk-500.png` |
| `637be63cad95f23585eea23a_AutoDesk-p-800.png` | `logo-autodesk-800.png` |
| `637be63cad95f23585eea23a_AutoDesk-p-1080.png` | `logo-autodesk-1080.png` |
| `637be63cad95f23585eea23a_AutoDesk-p-1600.png` | `logo-autodesk-1600.png` |
| `637be63cad95f23585eea23a_AutoDesk-p-2000.png` | `logo-autodesk-2000.png` |
| `637be37880dfa82eee7240e2_CIBC.png` | `logo-cibc.png` |
| `637be37880dfa82eee7240e2_CIBC-p-500.png` | `logo-cibc-500.png` |
| `637be37880dfa82eee7240e2_CIBC-p-800.png` | `logo-cibc-800.png` |
| `637be37880dfa82eee7240e2_CIBC-p-1080.png` | `logo-cibc-1080.png` |
| `637be37880dfa82eee7240e2_CIBC-p-1600.png` | `logo-cibc-1600.png` |
| `637be37880dfa82eee7240e2_CIBC-p-2000.png` | `logo-cibc-2000.png` |
| `637bd39cbab43b64ac8deb59_Live_Nation.png` | `logo-live-nation.png` |
| `637bd39cbab43b64ac8deb59_Live_Nation-p-500.png` | `logo-live-nation-500.png` |
| `637bd39a324386aa0d03a54d_Aldo.png` | `logo-aldo.png` |
| `637bd39a324386aa0d03a54d_Aldo-p-500.png` | `logo-aldo-500.png` |
| `637bd39cd0491b434c0c3945_Rogers.png` | `logo-rogers.png` |
| `637bd39cd0491b434c0c3945_Rogers-p-500.png` | `logo-rogers-500.png` |
| `637bd39a1d20f77323aace6d_Crayola.png` | `logo-crayola.png` |
| `637bd39a1d20f77323aace6d_Crayola-p-500.png` | `logo-crayola-500.png` |
| `637bd39a324386082b03a55f_Cineplex.png` | `logo-cineplex.png` |
| `637bd39a324386082b03a55f_Cineplex-p-500.png` | `logo-cineplex-500.png` |
| `637bd39a324386082b03a55f_Cineplex-p-800.png` | `logo-cineplex-800.png` |
| `637bd39a324386082b03a55f_Cineplex-p-1080.png` | `logo-cineplex-1080.png` |
| `637bd39a324386082b03a55f_Cineplex-p-1600.png` | `logo-cineplex-1600.png` |
| `637bd39a324386082b03a55f_Cineplex-p-2000.png` | `logo-cineplex-2000.png` |
| `637bd39ba522dc6e6b9ff852_AccuWeather.png` | `logo-accuweather.png` |
| `637bd39ba522dc6e6b9ff852_AccuWeather-p-500.png` | `logo-accuweather-500.png` |
| `637bd39a9fdb10a312af045a_LCBO.png` | `logo-lcbo.png` |
| `637bd39a9fdb10a312af045a_LCBO-p-500.png` | `logo-lcbo-500.png` |
| `637be32a42ab970d96f9c1d7_IvanhoeCambridge.png` | `logo-ivanhoe-cambridge.png` |
| `637be32a42ab970d96f9c1d7_IvanhoeCambridge-p-500.png` | `logo-ivanhoe-cambridge-500.png` |
| `637be32a42ab970d96f9c1d7_IvanhoeCambridge-p-800.png` | `logo-ivanhoe-cambridge-800.png` |
| `637be32a42ab970d96f9c1d7_IvanhoeCambridge-p-1080.png` | `logo-ivanhoe-cambridge-1080.png` |
| `637be32a42ab970d96f9c1d7_IvanhoeCambridge-p-1600.png` | `logo-ivanhoe-cambridge-1600.png` |
| `637be32a42ab970d96f9c1d7_IvanhoeCambridge-p-2000.png` | `logo-ivanhoe-cambridge-2000.png` |

### Specialty icons

| Webflow filename | Local name |
|---|---|
| `63765e692033291f70ba0b62_ico-rc.png` | `ico-retail-commerce.png` |
| `63765d2fd5c230c33dbfbfdd_ico-pd.png` | `ico-platform-design.png` |
| `63765d2fb50c951324ca1aae_ico-mc.png` | `ico-multichannel.png` |
| `638523630490b734912a7426_ico-dt.png` | `ico-design-leadership.png` |
| `637661d6b0fb5366f3bfecb2_ico-ps.png` | `ico-product-strategy.png` |
| `63765ea32033297a3bba0eba_ico-ia.png` | `ico-info-architecture.png` |

---

## Pending delta — assessed 2026-07-09

Diffed live Webflow (`https://alexmchong-portfolio.webflow.io/`) against `site/portfolioforhire/`. Changes detected and categorised below. **Not yet applied.**

### Alex's intentional changes (pull in when ready)

| What | Webflow value | Current local value |
|---|---|---|
| Current role (Hello section) | `Director of User Experience, UX Practice & Advisory, Outwitly Inc.` | `Director of User Experience, Hudson's Bay` |
| Years of design production | `13+` | `12+` |
| Profile photo (`img-profile`) | `6a502185136917032769a387_crop2.png` | old photo |
| Hello sitting photo | `6a5026370225de1a17073b98_alex_webflow7.jpg` | old `img-sitting.jpg` |
| History sitting photo | `6a50256253f68f28bc4185ef_alex_webflow5.jpg` | old `img-sitting.jpg` in history section |

For the 3 new images: download from Webflow CDN, give clean local names (`img-profile.jpg`, `img-sitting.jpg`, `img-sitting-history.jpg` or similar), drop into `assets/`, update `index.html` src references, update this table.

### Webflow-injected noise (do NOT pull in)

- **"Clients" nav item + section** — Webflow auto-added a new CMS collection section with placeholder rows ("AE Motion", "Orange llc", "meow", "trace original") and `placeholder.svg` images. This is a Webflow template feature, not real content. Ignore entirely.

### Our local copy formatting gaps (fix alongside next delta)

These are not new Webflow changes — they were pre-existing in the original export but our HTML formatter stripped the whitespace:

- `&` in compound phrases lacks surrounding spaces: `Retail&Commerce`, `Design&building`, `mentorship&coaching`, `strategy&research`, `retail&finance`, `interfaces&assets`, `Architecture&Urbanism`, `trial&error`, `launch&optimization`, `consumer&enterprise-grade`, `leadership&strategy`, `strategy&research` (in history)
- Smart-quoted words rendered without surrounding spaces: `"way"`, `"ways"`, `"digital chalkboard"`, `"Connected Mall"`, `"discovery"`, `"user"` (in Q&A)
- Nav items `Q & A` and `CV & PDF` split across lines (harmless — browser collapses whitespace — but clean up anyway)

---

## Adding a brand new image from Webflow

When the diff shows an image that doesn't exist locally yet:

1. Download it from Webflow's CDN (the src URL in the diff)
2. Give it a clean local name following the naming convention above (`category-description[-size].ext`)
3. Download any srcset variants (`-p-500`, `-p-800`, `-p-1080`, etc.) with matching clean names
4. Add the `assets/` reference to `index.html` using the local name
5. Add a row to this table under the relevant section

---

## Naming convention for new files

```
[category]-[description][-size].[ext]

Examples:
  icon-arrow.svg
  img-profile-500.jpg
  brand-shopify.png
  case-new-project-1080.png
  ico-ux-research.png
```

Sizes follow Webflow's `-p-500`, `-p-800`, `-p-1080`, `-p-1600`, `-p-2000`, `-p-2600` pattern — strip the `-p-` prefix locally and just use the number.
