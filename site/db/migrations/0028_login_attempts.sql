-- Adaptive per-IP login throttle (replaces the per-account lockout in
-- AUTH-SECURITY.md §6). Records every login attempt outcome keyed by client
-- IP so the throttle can (a) compute a decaying per-IP attempt budget within
-- a rolling window, (b) recognise IPs that have previously authenticated
-- successfully ("trusted" → always full budget), and (c) serve as the login
-- audit log (the open item in AUTH-SECURITY.md §12).
--
-- ip_address is the raw REMOTE_ADDR (the real TCP peer on DreamHost — not a
-- spoofable X-Forwarded-For). Rows are pruned after PRUNE_DAYS by the
-- throttle itself; no cron required.
CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  ip_address   VARCHAR(45) NOT NULL,
  outcome      ENUM('fail','success') NOT NULL,
  email        VARCHAR(255) NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time      (ip_address, attempted_at),
  KEY idx_outcome_time (outcome, attempted_at),
  KEY idx_time         (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
