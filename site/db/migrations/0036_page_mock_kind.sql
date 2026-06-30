-- Migration 0036: page_mock_kind
-- Adds a `kind` column to page_mocks to distinguish editable drafts from
-- publish-point snapshots (captured automatically when Publish → is hit).
-- Existing rows are all drafts, so DEFAULT 'draft' covers them.

ALTER TABLE page_mock_versions
  ADD COLUMN kind ENUM('draft','snapshot') NOT NULL DEFAULT 'draft' AFTER slug;
