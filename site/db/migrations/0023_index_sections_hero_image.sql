-- 0023_index_sections_hero_image.sql
-- Phase 21.7 — editorial hero side-image controls.
-- hero_image_mode picks where the hero image comes from:
--   'auto'   → use the linked article's hero_image (default)
--   'custom' → use hero_image_url verbatim
--   'none'   → omit the side panel; hero left column spans full width
-- hero_image_url is only meaningful when mode = 'custom'.

ALTER TABLE index_sections
  ADD COLUMN hero_image_mode ENUM('auto','custom','none') NOT NULL DEFAULT 'auto' AFTER header_style,
  ADD COLUMN hero_image_url  VARCHAR(500) NULL AFTER hero_image_mode;
