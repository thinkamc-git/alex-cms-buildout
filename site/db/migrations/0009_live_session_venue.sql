-- ============================================================================
-- 0009_live_session_venue.sql — separate venue from location (Phase 12)
-- ----------------------------------------------------------------------------
-- Phase 9 stored the entire location string in `location` and Phase 12's
-- initial public-card rewrite split it client-side on a " · " separator.
-- That's a brittle convention — the cards now need two clearly distinct
-- fields: a city/region line and a venue/sub line.
--
-- Schema change: add a sibling `venue` column. `location` keeps its
-- existing semantic — the prominent city/region line (e.g. "Toronto, ON").
-- `venue` is the smaller subline (e.g. "Centre for Social Innovation",
-- "16 seats", "Join from anywhere").
--
-- Both nullable; existing rows keep their `location` value untouched and
-- start with NULL `venue`. The CMS edit form gets a second input.
-- ============================================================================

ALTER TABLE content
  ADD COLUMN venue VARCHAR(255) NULL AFTER location;
