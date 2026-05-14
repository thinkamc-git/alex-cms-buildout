-- ============================================================================
-- 0002_users_table.sql — single-user authentication table.
-- ----------------------------------------------------------------------------
-- Per AUTH-SECURITY.md §2. Single-author CMS: this table will hold exactly
-- one row in practice. The UNIQUE constraint on email is belt-and-braces.
-- password_hash holds the full password_hash(PASSWORD_DEFAULT) output (Argon2id
-- on PHP 8.2; algorithm may rotate over time, so 255 bytes is the ceiling PHP
-- guarantees for future-proofing).
-- ============================================================================

CREATE TABLE users (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  email                VARCHAR(255) NOT NULL UNIQUE,
  password_hash        VARCHAR(255) NOT NULL,
  last_login           TIMESTAMP NULL,
  failed_attempts      INT NOT NULL DEFAULT 0,
  locked_until         TIMESTAMP NULL,
  password_changed_at  TIMESTAMP NULL,
  created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
