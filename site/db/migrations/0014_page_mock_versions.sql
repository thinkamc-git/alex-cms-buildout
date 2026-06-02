-- ============================================================================
-- 0014_page_mock_versions.sql — Pages CMS mock-version store (Phase 20)
-- ----------------------------------------------------------------------------
-- Holds named mock versions for the marketing pages under _pages/ and for the
-- layout partials header.php / footer.php. Mocks are a sandbox: the CMS never
-- writes the on-disk files. By default mocks are previewable only (via
-- ?_preview=<id> with a valid CMS session); when is_published=1 the runtime
-- prefers the mock body over the file content at request time.
--
-- The publish capability is scoped via app logic to layout partials
-- (slug='header.php' or 'footer.php') for Phase 20. Marketing-page mocks stay
-- preview-only — files remain canonical for them.
--
-- Token substitution at render time understands one form per partial:
--   <?php render_nav('header'); ?>
--   <?php render_nav('footer'); ?>
-- All other PHP in the body is treated as inert text (not eval'd).
-- ============================================================================

CREATE TABLE page_mock_versions (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(120) NOT NULL,
  name              VARCHAR(255) NOT NULL,
  body_html         LONGTEXT NOT NULL,
  meta_title        VARCHAR(255) NULL,
  meta_description  TEXT NULL,
  og_image          VARCHAR(500) NULL,
  og_type           VARCHAR(40) NULL DEFAULT 'website',
  twitter_card      VARCHAR(40) NULL DEFAULT 'summary_large_image',
  is_published      TINYINT(1) NOT NULL DEFAULT 0,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX ix_pmv_slug_updated   (slug, updated_at),
  INDEX ix_pmv_slug_published (slug, is_published)
);
