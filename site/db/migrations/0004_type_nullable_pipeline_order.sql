-- ============================================================================
-- 0004_type_nullable_pipeline_order.sql — Phase 7.6 schema for Ideation and
-- drag-drop reordering.
-- ----------------------------------------------------------------------------
-- Two changes:
--   1. `type` becomes nullable. Quick-capture from Ideation now creates rows
--      without a predetermined type — the author assigns it by dragging the
--      card into the matching column. Existing rows already have a type and
--      keep it; the modification only widens the column's domain.
--   2. `pipeline_order` is the per-lane ordinal (1..N within the lane in
--      Pipeline = stage; within the lane in Ideation = type). New rows
--      default to 0 so they sort to the top of their lane (sort key:
--      pipeline_order ASC, updated_at DESC). A drag-reorder rewrites the
--      whole lane to 1..N, pushing the new captures (still 0) above them.
-- ============================================================================

ALTER TABLE content
  MODIFY COLUMN type ENUM('article','journal','live-session','experiment') DEFAULT NULL;

ALTER TABLE content
  ADD COLUMN pipeline_order INT NOT NULL DEFAULT 0 AFTER notes;
