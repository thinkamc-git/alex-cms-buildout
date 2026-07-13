-- 0041_rename_writing_index.sql
-- Rename the built-in writing index slug: writing → essays.
-- /writing/ will be repurposed as a custom multi-type index by the author.
-- No redirect needed — /writing/ stays live as a new custom index.

UPDATE indexes SET
  slug     = 'essays',
  title    = 'Essays',
  subtitle = 'Long-form thinking on design, systems, and practice.'
WHERE slug = 'writing';
