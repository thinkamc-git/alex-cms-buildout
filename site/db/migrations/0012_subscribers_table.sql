-- ============================================================================
-- 0012_subscribers_table.sql — newsletter subscribers (Phase 14)
-- ----------------------------------------------------------------------------
-- Mirrors CMS-STRUCTURE.md §20. Captures signups from the public newsletter
-- form into a flat administrative table. Not a content type: no slug, no
-- status, no template — the CMS just lists/exports/marks rows here.
--
-- Columns reserved for the Phase 18 double-opt-in flow:
--   confirm_token, confirmed_at  — NULL today, populated when email confirm
--                                  lands. The CMS view treats every row with
--                                  unsubscribed_at IS NULL as "subscribed".
--
-- Duplicate emails are not an error per §20: the handler updates
-- subscribed_at and clears unsubscribed_at when an existing email re-subs.
-- The UNIQUE key on email enforces single-row-per-address.
-- ============================================================================

CREATE TABLE subscribers (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(255) NOT NULL,
  name            VARCHAR(255),
  subscribed_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at TIMESTAMP    NULL,
  source          VARCHAR(100),
  confirm_token   VARCHAR(64)  NULL,
  confirmed_at    TIMESTAMP    NULL,
  ip_address      VARCHAR(45),
  user_agent      VARCHAR(500),
  UNIQUE KEY uk_subscribers_email (email),
  INDEX ix_subscribers_subscribed_at (subscribed_at)
);
