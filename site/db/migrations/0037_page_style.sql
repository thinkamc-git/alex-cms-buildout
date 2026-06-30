-- Migration 0037: per-page Style override (Pages system, P2)
-- Adds a page-scoped CSS override column to page_mock_versions. The HTML body
-- (body_html) and this style (style_css) are authored as separate editor panes
-- and folded into one self-contained file on publish. Source of truth stays
-- separated; the folded file is generated output. See docs/PAGES-SYSTEM.md §4–5.
ALTER TABLE page_mock_versions
  ADD COLUMN style_css MEDIUMTEXT NULL DEFAULT NULL AFTER body_html;
