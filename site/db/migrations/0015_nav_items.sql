-- ============================================================================
-- 0015_nav_items.sql — Header + footer nav items (Phase 20)
-- ----------------------------------------------------------------------------
-- Replaces the hardcoded <a> lists in _pages/_layout/header.html and
-- footer.html with a CMS-managed table. Two zones (header / footer), one row
-- per link, ordered by `position`.
--
-- target_type drives the resolver in lib/nav.php:
--   index    → uses target_id  → /<index_slug>/
--   category → uses target_id  → resolves to a category's index URL
--   series   → uses target_id  → /series/<series_slug>/
--   content  → uses target_id  → /<type>/<slug>/
--   page     → uses target_slug → /<page_slug>/   (e.g. 'about', 'coaching')
--   custom   → uses custom_url  → verbatim (internal /foo/ or https://…)
--
-- nav_key is emitted as data-nav-key="…" on the <a> tag; the JS in header.php
-- uses it for prefix-match active-link highlighting. NULL = no key emitted.
--
-- highlight:
--   none → plain link
--   dot  → small coloured circle next to label (used today for "What's UX 2.0")
--   pill → small coloured pill with `highlight_text` ("NEW", etc.)
-- highlight_color is a CSS colour value (hex or var name) — NULL = default
-- (--c-terracotta, the current red dot).
--
-- Nightly cron sweep flips is_active=0 when a referenced target_id/target_slug
-- no longer resolves. The CMS surfaces a BROKEN badge for review.
-- ============================================================================

CREATE TABLE nav_items (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  zone            ENUM('header','footer') NOT NULL,
  label           VARCHAR(120) NOT NULL,
  nav_key         VARCHAR(40)  NULL,
  target_type     ENUM('index','category','series','content','page','custom') NOT NULL,
  target_id       INT          NULL,
  target_slug     VARCHAR(120) NULL,
  custom_url      VARCHAR(500) NULL,
  highlight       ENUM('none','dot','pill') NOT NULL DEFAULT 'none',
  highlight_text  VARCHAR(40)  NULL,
  highlight_color VARCHAR(40)  NULL,
  position        INT          NOT NULL DEFAULT 0,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX ix_nav_zone_position (zone, position)
);
