-- 0018_body_mode.sql — split body-source from chrome.
--
-- Phase 20.3 introduces three body-source modes:
--   rtf       — body lives in content.body (TipTap, existing default).
--   html-body — body lives in a file under /content/<type>/<slug>/;
--               the surrounding chrome (breadcrumb, byline, etc.) stays.
--   html-swap — body lives in a file; the entire page is the file (no chrome).
--
-- Pre-Phase-20.3 schema encoded mode 'html-swap' as template='experiment-html'.
-- We split it into a separate column so the chrome (template) and body-source
-- (body_mode) become orthogonal — and so adding 'html-body' doesn't require
-- a new chrome template per type.
--
-- Migration steps:
--   1. Add the body_mode column with a safe default ('rtf').
--   2. Backfill existing 'experiment-html' rows to template='experiment'
--      + body_mode='html-swap' (preserving their source_file references).
--   3. Tighten the template enum to drop the now-redundant 'experiment-html'.
--
-- After this migration the dispatcher routes on (template, body_mode):
--   * body_mode='html-swap'   → readfile() the file directly, no chrome.
--   * body_mode='html-body'   → render chrome via `template`; body block
--                                readfile()s instead of echoing content.body.
--   * body_mode='rtf'         → render chrome via `template`; body block
--                                echoes content.body (TipTap output).

ALTER TABLE content
  ADD COLUMN body_mode ENUM('rtf','html-body','html-swap')
    NOT NULL DEFAULT 'rtf'
  AFTER template;

UPDATE content SET body_mode = 'html-swap' WHERE template = 'experiment-html';
UPDATE content SET template  = 'experiment' WHERE template = 'experiment-html';

ALTER TABLE content
  MODIFY COLUMN template ENUM(
    'article-standard',
    'article-series',
    'journal-entry',
    'live-session',
    'experiment'
  );
