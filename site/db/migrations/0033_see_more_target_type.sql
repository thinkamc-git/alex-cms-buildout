-- "See more / Read more" target picker — nav parity. The link target can now be
-- an index, category, series, content item, marketing page, or a custom URL
-- (previously only index | custom). see_more_target keeps storing the resolved
-- URL (what the public render uses as the href); this column records WHICH
-- dimension produced it so the CMS editor can round-trip the choice on reload
-- (a bare URL can't tell index from page). NULL on legacy rows = inferred.
ALTER TABLE index_sections
  ADD COLUMN see_more_target_type VARCHAR(16) NULL AFTER see_more_target;
