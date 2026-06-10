-- 0026_nav_items_hide_mobile.sql
-- Phase 23.x — per-nav-item "hide on mobile" toggle. When set, the item is
-- hidden at phone widths (≤767px) only; it still shows in the tablet drawer
-- and the desktop bar. Rendered as the `is-hide-mobile` class by render_nav.

ALTER TABLE nav_items
  ADD COLUMN hide_mobile TINYINT(1) NOT NULL DEFAULT 0 AFTER highlight_color;
