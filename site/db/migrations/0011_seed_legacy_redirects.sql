-- ============================================================================
-- 0011_seed_legacy_redirects.sql — port legacy .htaccess redirects (Phase 13)
-- ----------------------------------------------------------------------------
-- Phase 1 ran 10 redirect rules in site/.htaccess to preserve inbound links
-- from the pre-CMS site. Phase 13 moves them into the `redirects` table so
-- the CMS can manage them and the .htaccess block can be deleted.
--
-- Source of truth for the list: docs/LEGACY-ROUTES.md §2 plus the
-- `/landing.html` rule that was added in Phase 1 to keep old bookmarks
-- alive after the homepage moved from landing.html to landing.php.
--
-- All ten ship as 302 (temporary) for the same reason LEGACY-ROUTES.md §2
-- documents: most destinations are third-party (Webflow, Notion, Calendly,
-- LinkedIn) and could move. Author can promote individual rows to 301 from
-- the CMS once a destination has been stable for 6+ months.
--
-- INSERT IGNORE so re-running the migration on a DB that already has these
-- rows (via the CMS or a prior partial migration) is a no-op.
-- ============================================================================

INSERT IGNORE INTO redirects (old_slug, new_slug, status_code) VALUES
  ('/portfolioforhire', 'https://alexmchong-portfolio.webflow.io/', 302),
  ('/portfolio',        'https://alexmchong-portfolio.webflow.io/', 302),
  ('/research',         'https://alexmchong.notion.site/alexmchong/Alex-s-Master-s-Thesis-NTUT-3ef25dfbb1e145bb8ed9176171828f73', 302),
  ('/talks',            'https://alexmchong.notion.site/alexmchong/Alex-M-Chong-Design-Talks-455f32067df04918a18875321c3cc9fa', 302),
  ('/meet',             'https://calendly.com/alexmchong/meet', 302),
  ('/linkedin',         'https://linkedin.com/in/alexmchong/', 302),
  ('/cv',               '/resume/', 302),
  ('/community',        '/', 302),
  ('/consulting',       '/', 302),
  ('/landing.html',     '/', 302);
