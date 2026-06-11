-- Redirects "last touched" log column. The view shows a date so the author
-- can see when each rule was created/last changed. created_at already exists
-- (immutable insert time); updated_at auto-bumps on any row UPDATE via MySQL's
-- ON UPDATE CURRENT_TIMESTAMP, so editing a redirect refreshes the displayed
-- date with no PHP change. Existing rows are backfilled to their created_at so
-- the column reads truthfully (not the migration run time).
ALTER TABLE redirects
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
             ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

UPDATE redirects SET updated_at = created_at;
