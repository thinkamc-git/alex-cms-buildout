-- 0025_indexes_feed_categories.sql
-- Phase 21.7 follow-up — author-controlled category narrowing on Basic Listing.
--
-- Adds feed_categories (JSON array of category value_slugs) to the indexes
-- table so a Basic Listing page can pre-filter its feed to specific
-- categories the same way feed_types already pre-filters by type.
-- NULL or empty = all categories. Mirrors index_sections.feed_categories,
-- which already exists for editorial Feed sections.

ALTER TABLE indexes
  ADD COLUMN feed_categories JSON NULL AFTER feed_types;
