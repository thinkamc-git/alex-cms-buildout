# Archived mockups

Design-mockup HTML files that have served their purpose. Once a mockup's design
is built into the real product (or otherwise retired), it moves here so it's
preserved as history but no longer read as a live reference.

**Convention**
- Active mockups live in `docs/design-mockups/`.
- Retired mockups live in `docs/design-mockups/_completed/`.
- Archived files should be self-contained where possible (inlined CSS) so they
  render forever without depending on — or coupling to — live stylesheets.

**Contents**
- `cms-ui.html` — the start-of-project CMS admin mockup (pristine initial-commit
  version, fully self-contained). Superseded by the real CMS at `site/cms/`.
- `eyebrow-options.html` — comparison of four eyebrow/section-header treatments
  (reused vs. new) built during the thinking-system article migration. Option B
  (`.editorial-hero-eyebrow`-style) was picked and promoted to `.article-prose
  .kicker` in `site/_design-system/css/public/blocks.css`, with a Tiptap "H2^"
  toolbar toggle in `site/cms/_assets/tiptap-setup.js`.
