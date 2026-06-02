-- ============================================================================
-- 0013_updated_display.sql — explicit "Updated" date control (Phase 14.6 followup)
-- ----------------------------------------------------------------------------
-- The Updated Date block was previously auto-conditional in BLOCKS.md §5
-- (rendered when updated_at differed from published_at by > 24h). That
-- gave authors no control — sometimes a small typo fix would surface a
-- prominent "Updated" line they didn't want.
--
-- Replaced with explicit per-content control:
--   show_updated     BOOLEAN — true = render the "Updated" line publicly
--   updated_display  TIMESTAMP NULL — override of the displayed date.
--                                     When NULL, the public render falls
--                                     back to updated_at (the actual last
--                                     save). When set, the override wins.
--
-- Default is show_updated = FALSE — existing rows don't suddenly start
-- showing an "Updated" line. Authors opt in per row from the edit view.
-- ============================================================================

ALTER TABLE content
  ADD COLUMN show_updated BOOLEAN NOT NULL DEFAULT FALSE AFTER show_author_bio,
  ADD COLUMN updated_display TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
