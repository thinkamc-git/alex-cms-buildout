-- ============================================================================
-- 0001_initial_schema.sql — alexmchong.ca CMS, initial schema
-- ----------------------------------------------------------------------------
-- Mirrors CMS-STRUCTURE.md §9 (schema source of truth). The `users` and
-- `subscribers` tables are intentionally NOT included here:
--   - `users` lands in a Phase 4 migration alongside AUTH-SECURITY.md.
--   - `subscribers` lands in a Phase 14 migration alongside the newsletter
--     flow (§20 of CMS-STRUCTURE.md).
--
-- Charset / collation: utf8mb4_unicode_ci (per Phase 3 Decisions).
-- Engine: InnoDB (transactions + FK support — content_categories FKs content).
-- ============================================================================

CREATE TABLE content (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(255) NOT NULL UNIQUE,
  type              ENUM('article','journal','live-session','experiment') NOT NULL,
  status            ENUM('idea','concept','outline','draft','published') NOT NULL,
  published_status  ENUM('live','hidden','scheduled') DEFAULT NULL,
  template          ENUM('article-standard','article-series','journal-entry',
                         'live-session','experiment','experiment-html'),
  -- Editorial
  title             VARCHAR(500),
  key_statement     TEXT,
  summary           VARCHAR(500),
  body              LONGTEXT,
  source_file       VARCHAR(255),
  thumbnail         VARCHAR(255),
  -- Hero Image block
  hero_image        VARCHAR(500),
  hero_caption      TEXT,
  hero_size         ENUM('default','wide','full') NOT NULL DEFAULT 'default',
  -- Live session fields
  event_start       DATETIME,
  location          VARCHAR(255),
  cost_pill         VARCHAR(50),
  attendance        ENUM('in-person','remote'),
  custom_pill       VARCHAR(50),
  -- Author block toggles (per content)
  show_author       BOOLEAN NOT NULL DEFAULT TRUE,
  show_author_bio   BOOLEAN NOT NULL DEFAULT TRUE,
  -- Article-specific
  special_tag       ENUM('principle','framework'),
  series_id         INT,
  series_order      INT,
  -- Journal-specific
  journal_number    INT,
  -- Common metadata
  read_time         INT,
  tags              VARCHAR(500),
  published_at      TIMESTAMP NULL,
  updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- Pipeline progressive disclosure
  concept_text      TEXT,
  outline_text      TEXT,
  -- Indexes
  INDEX ix_type_status (type, status),
  INDEX ix_published_at (published_at),
  INDEX ix_series_id (series_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  content_id  INT NOT NULL,
  type        VARCHAR(50),
  category    VARCHAR(100),
  is_primary  BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE series (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(255),
  slug        VARCHAR(255) UNIQUE,
  description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE redirects (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  old_slug    VARCHAR(500) UNIQUE,
  new_slug    VARCHAR(500),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  type        VARCHAR(50)  NOT NULL,
  value_slug  VARCHAR(100) NOT NULL,
  label       VARCHAR(255) NOT NULL,
  colour      VARCHAR(50)  NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_type_slug (type, value_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE author (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  image                VARCHAR(500),
  name                 VARCHAR(255),
  short_description    TEXT,
  extended_description TEXT,
  updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- §9: "INSERT one row at install with all NULLs."
INSERT INTO author (image, name, short_description, extended_description)
VALUES (NULL, NULL, NULL, NULL);
