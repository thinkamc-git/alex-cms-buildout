-- 0019_journal_number_cleanup.sql — reset mock journal_number assignments.
--
-- Phase 20.3 tightened the rule: journal_number is assigned only when a
-- row enters status='published', and cleared when the row leaves
-- Published (per transition_stage in lib/content.php). Existing mock data
-- predates that rule and has stray numbers on drafts; clear them so the
-- per-category MAX+1 counter reads off a clean slate.
--
-- Gaps left by deletion are intentionally NOT refilled — a deleted
-- Entry #3 stays a hole; new entries continue from the current MAX.
-- This migration only nulls drafts; published rows keep their numbers.

UPDATE content
   SET journal_number = NULL
 WHERE type = 'journal'
   AND status <> 'published';
