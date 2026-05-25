-- ============================================================================
-- 0010_redirects_status_code.sql — per-row HTTP status on redirects (Phase 13)
-- ----------------------------------------------------------------------------
-- The Phase 1 schema modeled `redirects` as old_slug → new_slug only. Phase 13
-- migrates the legacy redirects out of .htaccess and into the DB, and the
-- router serves them. Some legacy entries are 302 (third-party destinations
-- that could move — Webflow, Notion, Calendly), others are 301 (stable
-- canonical renames). We need a per-row status so the CMS can express that.
--
-- 301 is the CMS-new-redirect default (slug renames within the site, where
-- the new URL is the permanent home). Legacy seeds override to 302 where
-- LEGACY-ROUTES.md §2 calls for it.
-- ============================================================================

ALTER TABLE redirects
  ADD COLUMN status_code SMALLINT NOT NULL DEFAULT 301 AFTER new_slug;
