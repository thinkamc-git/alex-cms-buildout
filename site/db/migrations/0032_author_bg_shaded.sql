-- Author Info background options, revised. The four choices are now:
--   transparent — no fill; framed by full-width rules above + below
--   shaded      — subtle 10% black fill (new); framed by full-width rules
--   white       — solid surface fill; framed by full-width rules
--   black        — solid dark fill; NO framing rules (the fill is the frame)
-- 'plain' is dropped (it duplicated 'transparent') and collapsed into it. The
-- full-width framing lines + the shaded fill are pure CSS (blocks.css); only the
-- enum + default change here.
UPDATE index_sections SET author_background = 'transparent' WHERE author_background = 'plain';

ALTER TABLE index_sections
  MODIFY COLUMN author_background ENUM('transparent','shaded','white','black') NOT NULL DEFAULT 'transparent';
