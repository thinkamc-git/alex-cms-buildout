-- Migration 0035: page_registry
-- Tracks the status of marketing pages managed through the CMS Pages section.
-- status: 'active' (default) | 'archived'
-- When archived, the page URL redirects to /archive/<slug>/ and a banner is shown.

CREATE TABLE IF NOT EXISTS page_registry (
  slug        VARCHAR(120) NOT NULL,
  status      ENUM('active','archived') NOT NULL DEFAULT 'active',
  archived_at DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
