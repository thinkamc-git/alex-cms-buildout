-- "Author Info" editorial-index section type. A singleton section that renders
-- the site author (Settings → Author Info) as a banner. Modelled on Hero and
-- reuse-first: the photo reuses hero_image_mode/hero_image_url (auto/custom/
-- none), the trailing "Read More" link reuses see_more_label/see_more_target,
-- and the heading reuses `title`. Only two new columns are needed:
--   author_background — the 4 background options (plain / transparent / white /
--                       black; black flips text to white). Config only — the
--                       styling uses existing DS tokens.
--   author_body       — the author bio text; defaults to the Settings bio when
--                       blank.
-- See CMS-STRUCTURE.md §16 + AUTH-SECURITY is unrelated.
ALTER TABLE index_sections
  MODIFY COLUMN section_type ENUM('hero','curated','feed','author-info') NOT NULL;

ALTER TABLE index_sections
  ADD COLUMN author_background ENUM('plain','transparent','white','black') NOT NULL DEFAULT 'plain' AFTER hero_blur,
  ADD COLUMN author_body       TEXT NULL AFTER author_background;
