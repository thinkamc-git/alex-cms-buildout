-- ============================================================================
-- 0017_page_metadata.sql — per-page metadata (Phase 20 refactor)
-- ----------------------------------------------------------------------------
-- Metadata (title, description, og:image, og:type, twitter:card) lives at the
-- page slug level, not per-mock. Editable from the Pages CMS independent of
-- the mock-versioning workflow — saves directly, no mock required.
--
-- Read at render time by _pages/_layout/_page-shell.php to supplement /
-- override the $title / $description set by the per-page assembler.
-- ============================================================================

CREATE TABLE page_metadata (
  slug              VARCHAR(120) NOT NULL PRIMARY KEY,
  meta_title        VARCHAR(255) NULL,
  meta_description  TEXT NULL,
  og_image          VARCHAR(500) NULL,
  og_type           VARCHAR(40) NULL DEFAULT 'website',
  twitter_card      VARCHAR(40) NULL DEFAULT 'summary_large_image',
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
