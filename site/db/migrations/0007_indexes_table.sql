-- ============================================================================
-- 0007_indexes_table.sql — Editorial Index System (Phase 12)
-- ----------------------------------------------------------------------------
-- Mirrors CMS-STRUCTURE.md §16. Each row is one index page on the public site
-- (e.g. /writing/, /journal/, /digital-garden/). The `layout` column picks
-- between two render templates:
--   editorial — page-title + hero feature + featured articles + content feed
--   listing   — page-title + content feed only
--
-- Slug is the URL path (without leading or trailing slashes). The router
-- matches the bare path and dispatches via render_index($slug).
--
-- Featured + feed config live in JSON to keep the schema flat — these are
-- small (a few content IDs, a handful of type/sort/rows knobs) and only ever
-- read by render_index() or the admin builder. No JOINs against them.
--
-- Seeded rows: one Basic Listing per content type so /writing/, /journal/,
-- /live-sessions/, /experiments/ all resolve out of the box. Author can flip
-- a type to Editorial via the builder; series indexes are auto-derived and
-- do NOT live in this table.
-- ============================================================================

CREATE TABLE indexes (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(255) NOT NULL UNIQUE,            -- e.g. 'writing', 'digital-garden'
  layout            ENUM('editorial','listing') NOT NULL,
  title             VARCHAR(500),
  subtitle          VARCHAR(500),
  show_title        BOOLEAN NOT NULL DEFAULT TRUE,           -- Editorial can hide; Listing must show
  hero_content_id   INT NULL,                                -- Editorial only — FK content.id (loose, no constraint)
  featured_ids      JSON NULL,                               -- Editorial only — ordered array of content.id
  feed_types        JSON NULL,                               -- ['article','journal',...] — NULL or empty = all
  feed_sort         ENUM('newest','oldest','manual') NOT NULL DEFAULT 'newest',
  feed_rows_shown   VARCHAR(10) NOT NULL DEFAULT 'all',      -- '1' | '2' | '3' | '4' | 'all'
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed the four built-in type indexes. INSERT IGNORE keeps re-runs safe.
INSERT IGNORE INTO indexes (slug, layout, title, subtitle, feed_types, feed_sort, feed_rows_shown) VALUES
  ('writing',       'listing', 'Writing',       'Long-form thinking on design, leadership, and the practice.', JSON_ARRAY('article'),      'newest', 'all'),
  ('journal',       'listing', 'Journal',       'Shorter, more personal notes.',                                JSON_ARRAY('journal'),      'newest', 'all'),
  ('live-sessions', 'listing', 'Live Sessions', 'Talks, workshops, and masterclasses.',                          JSON_ARRAY('live-session'), 'newest', 'all'),
  ('experiments',   'listing', 'Experiments',   'Prototypes, concepts, and one-offs.',                           JSON_ARRAY('experiment'),   'newest', 'all');
