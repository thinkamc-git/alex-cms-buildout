-- ============================================================================
-- 0008_indexes_filter_mode.sql — index filter pill configuration (Phase 12)
-- ----------------------------------------------------------------------------
-- Adds a per-index choice of which filter pill row (if any) renders above
-- the card grid. Three values:
--   none        — no pill row.
--   categories  — pills are the distinct categories from the feed's types.
--                 Best for type-locked indexes (the four built-ins).
--   types       — pills are the configured feed_types (Articles / Journals
--                 / Talks / Experiments). Best for multi-type custom indexes.
--
-- Default 'categories' suits every seeded built-in (they're single-type,
-- so the pill row collapses to that one type's categories). Author can
-- flip to 'none' or 'types' from the CMS builder.
--
-- Series indexes don't read this column — series_auto_index() hardcodes
-- the pill row to 'none'.
-- ============================================================================

ALTER TABLE indexes
  ADD COLUMN filter_mode ENUM('none','categories','types')
             NOT NULL DEFAULT 'categories' AFTER feed_rows_shown;
