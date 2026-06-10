-- 0027_index_sections_hero_blur.sql
-- Phase 23.x — optional bottom-blur on bleed heroes (bleed-dark / bleed-light).
-- A frosted blur that's full at the bottom and fades to clear toward the top.
-- Default OFF for both variants; the editor only exposes it for bleed layouts.
-- Rendered as the `editorial-hero--blur` class by index-editorial.php.

ALTER TABLE index_sections
  ADD COLUMN hero_blur TINYINT(1) NOT NULL DEFAULT 0 AFTER hero_background;
