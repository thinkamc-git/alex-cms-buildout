-- 0040_rename_index_slugs.sql
-- Rename public index slugs: journal → field-notes, experiments → field-work.
-- Updates the indexes table only — 301 redirects handled in index.php.
-- CMS routes (/cms/journals, /cms/experiments) are unchanged.

UPDATE indexes SET
  slug     = 'field-notes',
  title    = 'Field Notes',
  subtitle = 'Short observations, open questions, and principles in progress.'
WHERE slug = 'journal';

UPDATE indexes SET
  slug     = 'field-work',
  title    = 'Field Work',
  subtitle = 'Prototypes, case studies, and experiments in making.'
WHERE slug = 'experiments';
