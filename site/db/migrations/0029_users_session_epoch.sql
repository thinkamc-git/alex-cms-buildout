-- Force-All-Logout support. Each session is stamped with the user's
-- session_epoch at login; every CMS request re-checks it. "Sign out
-- everywhere" increments this value, instantly invalidating every existing
-- session (all devices) on its next request. See AUTH-SECURITY.md §7.
ALTER TABLE users
  ADD COLUMN session_epoch INT NOT NULL DEFAULT 0 AFTER password_changed_at;
