-- 0039_rename_category_slugs.sql
--
-- Renames category value_slugs to reflect the updated content taxonomy.
-- Works against the original type identifiers (journal, experiment) —
-- DB type values are unchanged.
--
-- Field Notes (type = 'journal'):
--   introspection → observation
--   contemplation → inquiry
--   insight       → principle
--
-- Field Work (type = 'experiment'):
--   prototype     → experiment
--   concept       → case-study
--
-- Both the canonical slug (categories.value_slug) and the denormalized
-- reference (content_categories.category) are updated atomically.
-- ============================================================

-- ── Field Notes ───────────────────────────────────────────────────────────────

UPDATE categories        SET value_slug = 'observation', label = 'Observation' WHERE type = 'journal'    AND value_slug = 'introspection';
UPDATE content_categories SET category  = 'observation'                         WHERE type = 'journal'    AND category  = 'introspection';

UPDATE categories        SET value_slug = 'inquiry',     label = 'Inquiry'     WHERE type = 'journal'    AND value_slug = 'contemplation';
UPDATE content_categories SET category  = 'inquiry'                             WHERE type = 'journal'    AND category  = 'contemplation';

UPDATE categories        SET value_slug = 'principle',   label = 'Principle'   WHERE type = 'journal'    AND value_slug = 'insight';
UPDATE content_categories SET category  = 'principle'                           WHERE type = 'journal'    AND category  = 'insight';

-- ── Field Work ────────────────────────────────────────────────────────────────

UPDATE categories        SET value_slug = 'experiment',  label = 'Experiment'  WHERE type = 'experiment' AND value_slug = 'prototype';
UPDATE content_categories SET category  = 'experiment'                          WHERE type = 'experiment' AND category  = 'prototype';

UPDATE categories        SET value_slug = 'case-study',  label = 'Case Study'  WHERE type = 'experiment' AND value_slug = 'concept';
UPDATE content_categories SET category  = 'case-study'                          WHERE type = 'experiment' AND category  = 'concept';
