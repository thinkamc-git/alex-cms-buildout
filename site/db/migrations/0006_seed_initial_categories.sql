-- 0006_seed_initial_categories.sql
--
-- Phase 11: seed the initial 12 categories per CMS-STRUCTURE.md §10.
--
-- Colour values are design-system token names (no `--c-` prefix). The CMS
-- admin reads from this table to populate every dropdown and renders the
-- chip background via `var(--c-<colour>)`.
--
-- `value_slug` is permanent — `label` and `colour` are freely editable in
-- the admin view. INSERT IGNORE keeps re-running the migration safe (the
-- (type, value_slug) UNIQUE key blocks dupes; later edits to labels or
-- colours via the admin are not overwritten).

INSERT IGNORE INTO categories (type, value_slug, label, colour, sort_order) VALUES
  ('article',       'ux-industry',    'UX Industry',    'terracotta', 10),
  ('article',       'leading-design', 'Leading Design', 'forest',     20),
  ('article',       'for-designers',  'For Designers',  'denim',      30),
  ('journal',       'introspection',  'Introspection',  'purple',     10),
  ('journal',       'contemplation',  'Contemplation',  'teal',       20),
  ('journal',       'insight',        'Insight',        'amber',      30),
  ('live-session',  'talk',           'Talk',           'amber',      10),
  ('live-session',  'workshop',       'Workshop',       'mauve',      20),
  ('live-session',  'masterclass',    'Masterclass',    'purple',     30),
  ('experiment',    'prototype',      'Prototype',      'violet',     10),
  ('experiment',    'concept',        'Concept',        'teal',       20);
