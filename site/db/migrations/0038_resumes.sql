-- 0038_resumes.sql — Library → Resumes feature (MVP).
--
-- Three tables:
--   resumes         — single-record store (one row, id=1). Holds draft + published HTML/CSS.
--   resume_snapshots — archive of each publish (body + style stored separately for faithful restore).
--   resume_pdfs     — uploaded PDF export history per resume.
--
-- Public route: /resume/ serves published_html directly (full document, no shell).
-- CMS editor:   /cms/library/resumes — Edit · Configure · PDF Exports tabs.

CREATE TABLE resumes (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  draft_html      LONGTEXT,
  draft_css       LONGTEXT,
  published_html  LONGTEXT,
  is_published    BOOLEAN NOT NULL DEFAULT FALSE,
  last_published  TIMESTAMP NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE resume_snapshots (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  resume_id   INT NOT NULL,
  name        VARCHAR(255) NOT NULL,
  html_body   LONGTEXT,
  style_css   LONGTEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_resume_snapshots_resume (resume_id),
  FOREIGN KEY (resume_id) REFERENCES resumes (id) ON DELETE CASCADE
);

CREATE TABLE resume_pdfs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  resume_id     INT NOT NULL,
  filename      VARCHAR(500) NOT NULL,
  original_name VARCHAR(500),
  pdf_date      VARCHAR(7)  NOT NULL DEFAULT '',
  note          TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX ix_resume_pdfs_resume (resume_id, created_at),
  FOREIGN KEY (resume_id) REFERENCES resumes (id) ON DELETE CASCADE
);

-- Seed the single resume record. Draft content is set via the CMS editor
-- (paste cleaned-up html-cv/ content after merging CSS and swapping asset URLs).
INSERT INTO resumes (id, draft_html, draft_css, is_published) VALUES (1, '', NULL, FALSE);
