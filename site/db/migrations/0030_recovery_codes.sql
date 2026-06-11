-- Offline recovery codes. One-time codes the single author can use to sign in
-- if the password is lost and SSH isn't handy. Stored only as hashes (never
-- plaintext) — shown once at generation. Generating a fresh set deletes the
-- old. A successful recovery-code login forces a password change. See
-- AUTH-SECURITY.md §13.
CREATE TABLE IF NOT EXISTS recovery_codes (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  code_hash  VARCHAR(255) NOT NULL,
  used_at    TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_unused (user_id, used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
