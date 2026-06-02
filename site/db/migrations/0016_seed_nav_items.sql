-- ============================================================================
-- 0016_seed_nav_items.sql — Seed header + footer with the current static items
-- ----------------------------------------------------------------------------
-- Mirrors what _pages/_layout/header.html and footer.html render today, so
-- the moment _page-shell.php switches to the DB-driven render the staging
-- nav is visually identical. All seeds use target_type='custom' for safety
-- (avoids depending on indexes.id values that vary by environment) — the
-- author can convert any row to a typed target via the CMS later.
--
-- Header item 1 (What's UX 2.0) carries highlight='dot' with NULL colour,
-- which resolves to --c-terracotta (the current red dot in the static
-- header).
-- ============================================================================

INSERT INTO nav_items
  (zone,    label,            nav_key,  target_type, custom_url,                       highlight, highlight_text, position) VALUES
  ('header','What''s UX 2.0', 'ux2',    'custom',    '/ux2.0/how-we-got-here/',        'dot',     NULL,           0),
  ('header','Thoughts',       'writing','custom',    '/writing/',                      'none',    NULL,           1),
  ('header','Talks',          'talks',  'custom',    '/live-sessions/',                'none',    NULL,           2),
  ('header','Work with me',   'work',   'custom',    '/work-with-me/',                 'none',    NULL,           3);

INSERT INTO nav_items
  (zone,    label,      nav_key, target_type, custom_url,         highlight, highlight_text, position) VALUES
  ('footer','About',    NULL,    'custom',    '/about/',          'none',    NULL,           0),
  ('footer','Coaching', NULL,    'custom',    '/coaching/',       'none',    NULL,           1),
  ('footer','Services', NULL,    'custom',    '/work-with-me/',   'none',    NULL,           2),
  ('footer','Resume',   NULL,    'custom',    '/resume/',         'none',    NULL,           3);
