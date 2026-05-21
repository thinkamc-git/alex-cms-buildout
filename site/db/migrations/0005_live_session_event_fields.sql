-- 0005_live_session_event_fields.sql
--
-- Phase 9 refinement: split the original `event_start DATETIME` into three
-- discrete fields so a session can be:
--   • date-only  (event_date set, both times NULL)
--   • date + start (event_date + event_time set, event_end_time NULL)
--   • date + start + end (all three set; duration is end − start)
--
-- Publish Date (published_at) remains separate — it's when the row went
-- live, not when the session happens.
--
-- Safe to run on prod: Phase 9 hasn't shipped to prod yet, so no rows
-- depend on event_start. On staging the column is empty for the same
-- reason (no rows of type='live-session' exist).

ALTER TABLE content
  DROP COLUMN event_start,
  ADD COLUMN event_date     DATE  NULL AFTER hero_size,
  ADD COLUMN event_time     TIME  NULL AFTER event_date,
  ADD COLUMN event_end_time TIME  NULL AFTER event_time;
