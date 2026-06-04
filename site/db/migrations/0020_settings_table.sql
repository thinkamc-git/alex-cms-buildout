-- ============================================================================
-- 0020_settings_table.sql — site-wide settings (Phase 21)
-- ----------------------------------------------------------------------------
-- A flat key/value bag for site-level settings — title, taglines, social-
-- preview defaults, footer copyright, analytics snippet. Read at render time
-- by _pages/_layout/_page-shell.php as the second tier in the metadata
-- cascade: per-page page_metadata → settings default → hardcoded shell.
--
-- One row per key. Editable through cms/views/settings.php — the table is
-- never mutated outside the CMS view or migration seeds.
-- ============================================================================

CREATE TABLE settings (
  `key`       VARCHAR(120) NOT NULL PRIMARY KEY,
  `value`     TEXT NULL,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the seven v1 keys with the values currently hardcoded in the shell,
-- footer partial, and analytics.js loader. og_image left blank — author
-- uploads via Settings view. analytics_script seeded with the GA4 snippet
-- that previously lived in /_layout/analytics.js; the page-shell now reads
-- this value and injects it before </body> instead of script-loading the
-- file. Other entry points (master-layout for articles, 404, ux2.0) still
-- use /_layout/analytics.js for now — same GA_ID, single fire per page.
INSERT INTO settings (`key`, `value`) VALUES
  ('site_title',           'Alex M. Chong'),
  ('site_tagline',         ''),
  ('default_og_image',     ''),
  ('default_og_type',      'website'),
  ('default_twitter_card', 'summary_large_image'),
  ('footer_copyright',     'alex m. chong'),
  ('analytics_script',
   '<script async src="https://www.googletagmanager.com/gtag/js?id=G-J6443HD1JY"></script>\n<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag(''js'',new Date());gtag(''config'',''G-J6443HD1JY'');</script>');
