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

-- Seed the seven v1 keys with the values currently hardcoded in the shell
-- and footer partial. og_image left blank — author uploads via Settings view.
INSERT INTO settings (`key`, `value`) VALUES
  ('site_title',           'Alex M. Chong'),
  ('site_tagline',         ''),
  ('default_og_image',     ''),
  ('default_og_type',      'website'),
  ('default_twitter_card', 'summary_large_image'),
  ('footer_copyright',     'alex m. chong'),
  ('analytics_script',     '');
