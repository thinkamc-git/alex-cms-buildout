-- 0024_index_sections_hero_layout.sql
-- Phase 21.7 — four hero layout variants:
--   plain        Side panel omitted, optional solid bg
--   within       Side image (existing default), optional solid bg
--   bleed-dark   Full-bleed image, dark gradient overlay, white text
--   bleed-light  Full-bleed image, light gradient overlay, dark text
-- hero_background picks the surface tone for plain/within only —
-- 'transparent' lets the page canvas show; 'surface' is solid white.

ALTER TABLE index_sections
  ADD COLUMN hero_layout ENUM('plain','within','bleed-dark','bleed-light') NOT NULL DEFAULT 'within' AFTER hero_image_url,
  ADD COLUMN hero_background ENUM('transparent','surface') NOT NULL DEFAULT 'transparent' AFTER hero_layout;
