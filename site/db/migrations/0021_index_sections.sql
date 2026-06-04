-- ============================================================================
-- 0021_index_sections.sql — Editorial Page section stack (Phase 21.7, stage 1)
-- ----------------------------------------------------------------------------
-- Editorial Pages are reworked from a fixed hero + featured + single-feed
-- form into an ordered, typed section stack per CMS-STRUCTURE.md §16.4.
-- Basic Listing indexes are NOT touched — the flat feed_*/filter_mode
-- columns on `indexes` stay authoritative for them.
--
-- This migration:
--   1. CREATE TABLE index_sections (one row per section, ordered by position)
--   2. Backfill existing Editorial rows into their equivalent section stacks:
--        hero_content_id  → a hero section at position 0
--        featured_ids[]   → a curated section at the next position
--        feed_types[]     → a feed section at the next position
--
-- Pre-existing `indexes` columns are LEFT IN PLACE:
--   - Basic Listing rows still read them as authoritative.
--   - Editorial rows ignore them once index_sections is the source of truth.
-- A future cleanup phase can drop them; doing so now would break Basic
-- Listing rendering and conflate two concerns.
-- ============================================================================

CREATE TABLE index_sections (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  index_id        INT NOT NULL,
  position        INT NOT NULL DEFAULT 0,
  section_type    ENUM('hero','curated','feed') NOT NULL,
  title           VARCHAR(500) NULL,

  -- Display layer (curated + feed; ignored for hero)
  display_format  ENUM('grid','carousel') NOT NULL DEFAULT 'grid',
  item_limit      INT NULL,                              -- carousel: # posts to show
  grid_rows       VARCHAR(10) NULL,                      -- grid: '1'|'2'|'3'|'4'|'all'
  see_more_label  VARCHAR(120) NULL,
  see_more_target VARCHAR(255) NULL,                     -- index slug or absolute URL; NULL = no card

  -- Content query (feed only)
  feed_types      JSON NULL,                             -- ['article','journal',…]; NULL/empty = all
  feed_categories JSON NULL,                             -- category value_slugs (OR); NULL = any
  feed_sort       ENUM('newest','oldest') NOT NULL DEFAULT 'newest',

  -- Visitor filter (feed only — layer 3)
  filter_show     BOOLEAN NOT NULL DEFAULT FALSE,
  filter_by       ENUM('types','categories') NULL,
  filter_options  JSON NULL,                             -- explicit pill subset; NULL = auto-derive

  -- Picks (hero = single id; curated = ordered array)
  item_ids        JSON NULL,

  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX ix_index_sections_index (index_id, position),
  FOREIGN KEY (index_id) REFERENCES indexes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Backfill from existing Editorial rows ─────────────────────────────────
-- Position math: each section type takes one slot. Hero is 0 (if present);
-- Curated is 1 slot after hero (or 0 if no hero); Feed is the slot after
-- whichever of the prior two existed. CASE expressions compute the offset
-- inline so we don't need a temp table.

-- HERO sections: from hero_content_id when set.
INSERT INTO index_sections (index_id, position, section_type, item_ids)
SELECT id,
       0,
       'hero',
       JSON_ARRAY(hero_content_id)
  FROM indexes
 WHERE layout = 'editorial'
   AND hero_content_id IS NOT NULL;

-- CURATED sections: from featured_ids when non-empty array. Display
-- inherits the page-level feed_rows_shown so existing pages keep their
-- visual density.
INSERT INTO index_sections
       (index_id, position, section_type, item_ids, display_format, grid_rows)
SELECT id,
       CASE WHEN hero_content_id IS NOT NULL THEN 1 ELSE 0 END,
       'curated',
       featured_ids,
       'grid',
       feed_rows_shown
  FROM indexes
 WHERE layout = 'editorial'
   AND featured_ids IS NOT NULL
   AND JSON_LENGTH(featured_ids) > 0;

-- FEED sections: from feed_types when non-empty. The legacy feed_sort
-- includes 'manual', which doesn't exist on sections — map it to 'newest'.
-- Visitor filter: filter_mode='none' maps to filter_show=FALSE; anything
-- else turns the pill row on with the legacy mode preserved.
INSERT INTO index_sections
       (index_id, position, section_type,
        feed_types, feed_sort,
        display_format, grid_rows,
        filter_show, filter_by)
SELECT id,
       (CASE WHEN hero_content_id IS NOT NULL THEN 1 ELSE 0 END
        + CASE WHEN featured_ids IS NOT NULL AND JSON_LENGTH(featured_ids) > 0 THEN 1 ELSE 0 END),
       'feed',
       feed_types,
       CASE WHEN feed_sort = 'manual' THEN 'newest' ELSE feed_sort END,
       'grid',
       feed_rows_shown,
       CASE WHEN filter_mode = 'none' THEN FALSE ELSE TRUE END,
       CASE WHEN filter_mode IN ('types','categories') THEN filter_mode ELSE NULL END
  FROM indexes
 WHERE layout = 'editorial'
   AND feed_types IS NOT NULL
   AND JSON_LENGTH(feed_types) > 0;
