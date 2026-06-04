-- 0022_index_sections_header_style.sql
-- Phase 21.7 — add header_style picker to editorial sections.
-- 'small' = .group-header eyebrow + view-all link (default).
-- 'big'   = full serif title + view-all (e.g. "Latest *thinking*").

ALTER TABLE index_sections
  ADD COLUMN header_style ENUM('small','big') NOT NULL DEFAULT 'small' AFTER title;
