-- ============================================================================
-- 0003_notes_column.sql — private notes scratchpad for the pipeline.
-- ----------------------------------------------------------------------------
-- Phase 7.5 introduces a "Notes" field that persists across every pipeline
-- stage (Idea → Concept → Outline → Draft → Published). It's the author's
-- private scratchpad — never rendered on the public site — and surfaces in
-- the editor at every stage with a "not visible" hint.
--
-- Before this migration, Phase 7's quick-capture wrote Idea-stage notes into
-- `concept_text`, conflating the private scratchpad with the editable Concept
-- text. Phase 7.5 separates them:
--   - `notes`         — private, across all stages
--   - `concept_text`  — public-facing concept, written at Concept stage
--
-- The data move below shifts any existing Idea-stage notes from `concept_text`
-- into `notes`, then clears `concept_text` for those rows so the Concept
-- editor starts empty when the row eventually advances.
-- ============================================================================

ALTER TABLE content
  ADD COLUMN notes TEXT DEFAULT NULL AFTER concept_text;

UPDATE content
   SET notes        = concept_text,
       concept_text = NULL
 WHERE status       = 'idea'
   AND concept_text IS NOT NULL;
