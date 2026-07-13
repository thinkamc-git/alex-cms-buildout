<?php
/**
 * lib/resumes.php — Résumé CMS data layer (Library → Resumes feature).
 *
 * Single-record model in MVP (id = 1). Draft + published HTML/CSS stored
 * separately; publish folds style into head and writes published_html.
 * Snapshots archived on every publish for version history + restore.
 * resume_pdfs stores uploaded PDF export history.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ── Resume record ─────────────────────────────────────────────────────

/** Fetch the single resume record, or null if the table is empty. */
function get_resume(): ?array
{
    $stmt = db()->query('SELECT * FROM resumes WHERE id = 1 LIMIT 1');
    $row  = $stmt->fetch();
    return $row ?: null;
}

/** Save draft HTML + CSS. Creates the record if it doesn't exist yet. */
function save_resume_draft(string $html, string $css): void
{
    db()->prepare(
        'INSERT INTO resumes (id, draft_html, draft_css)
         VALUES (1, ?, ?)
         ON DUPLICATE KEY UPDATE draft_html = VALUES(draft_html),
                                  draft_css  = VALUES(draft_css)'
    )->execute([$html, $css]);
}

/**
 * Publish the current draft.
 * 1. Snapshot the outgoing published version (body + style separately).
 * 2. Fold draft_css into draft_html's <head> as a <style> block.
 * 3. Store the folded document in published_html, mark is_published = TRUE.
 */
function publish_resume(): bool
{
    $row = get_resume();
    if ($row === null) return false;

    $pdo = db();

    // Snapshot outgoing published version (if any) before overwriting.
    if ((bool)$row['is_published'] && trim((string)$row['published_html']) !== '') {
        $pdo->prepare(
            'INSERT INTO resume_snapshots (resume_id, name, html_body, style_css, created_at)
             VALUES (1, ?, ?, ?, NOW())'
        )->execute([
            'Published ' . date('Y-m-d'),
            (string)$row['draft_html'],   // separate body (pre-fold)
            (string)($row['draft_css'] ?? ''),
        ]);
    }

    // Fold draft_css into the document head.
    $html  = (string)$row['draft_html'];
    $css   = trim((string)($row['draft_css'] ?? ''));
    $folded = $css !== ''
        ? _resume_inject_style($html, $css)
        : $html;

    $pdo->prepare(
        'UPDATE resumes
            SET published_html = ?, is_published = TRUE, last_published = NOW()
          WHERE id = 1'
    )->execute([$folded]);

    // Also save a snapshot of the newly-published version.
    $pdo->prepare(
        'INSERT INTO resume_snapshots (resume_id, name, html_body, style_css, created_at)
         VALUES (1, ?, ?, ?, NOW())'
    )->execute([
        'Published ' . date('Y-m-d'),
        $html,
        $css,
    ]);

    return true;
}

/**
 * Inject <style> block before </head>. Falls back to prepending if no
 * </head> found (unlikely for a well-formed document).
 */
function _resume_inject_style(string $html, string $css): string
{
    $tag = "<style>\n" . $css . "\n</style>";
    $pos = stripos($html, '</head>');
    if ($pos !== false) {
        return substr($html, 0, $pos) . $tag . "\n" . substr($html, $pos);
    }
    return $tag . "\n" . $html;
}

// ── Snapshots ─────────────────────────────────────────────────────────

function list_resume_snapshots(): array
{
    $stmt = db()->prepare(
        'SELECT id, name, LENGTH(html_body) AS body_len, created_at
           FROM resume_snapshots
          WHERE resume_id = 1
          ORDER BY id DESC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_resume_snapshot(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT id, name, html_body, style_css, created_at
           FROM resume_snapshots WHERE id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Restore a snapshot to draft (overwrites current draft). */
function restore_resume_snapshot(int $snapshot_id): bool
{
    $snap = get_resume_snapshot($snapshot_id);
    if ($snap === null) return false;
    save_resume_draft((string)$snap['html_body'], (string)($snap['style_css'] ?? ''));
    return true;
}

// ── PDF exports ───────────────────────────────────────────────────────

/** All PDFs for the resume, newest-upload first. */
function list_resume_pdfs(): array
{
    $stmt = db()->prepare(
        'SELECT id, filename, original_name, pdf_date, note, created_at, updated_at
           FROM resume_pdfs
          WHERE resume_id = 1
          ORDER BY pdf_date DESC, created_at DESC, id DESC'
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Insert a new PDF row. Returns the new id. */
function create_resume_pdf(string $filename, string $original_name, string $pdf_date, string $note = ''): int
{
    $stmt = db()->prepare(
        'INSERT INTO resume_pdfs (resume_id, filename, original_name, pdf_date, note)
         VALUES (1, ?, ?, ?, ?)'
    );
    $stmt->execute([$filename, $original_name, $pdf_date, $note]);
    return (int)db()->lastInsertId();
}

/** Update the editable fields (pdf_date and/or note) on an existing PDF row. */
function update_resume_pdf(int $id, string $pdf_date, string $note): void
{
    db()->prepare(
        'UPDATE resume_pdfs SET pdf_date = ?, note = ? WHERE id = ? AND resume_id = 1'
    )->execute([$pdf_date, $note, $id]);
}

/** Delete a PDF row and remove the file from disk. */
function delete_resume_pdf(int $id): void
{
    $stmt = db()->prepare('SELECT filename FROM resume_pdfs WHERE id = ? AND resume_id = 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $path = _resume_pdf_path((string)$row['filename']);
        if (is_file($path)) { @unlink($path); }
    }
    db()->prepare('DELETE FROM resume_pdfs WHERE id = ? AND resume_id = 1')->execute([$id]);
}

// ── PDF file helpers ──────────────────────────────────────────────────

/** Absolute server path to the resume PDF upload folder. */
function _resume_pdf_dir(): string
{
    return dirname(__DIR__) . '/uploads/resumes';
}

/** Absolute path to a stored PDF file by filename. */
function _resume_pdf_path(string $filename): string
{
    return _resume_pdf_dir() . '/' . $filename;
}

/**
 * Accept a PDF upload from $_FILES['pdf_file']. Validates MIME + size,
 * generates a safe filename, stores under uploads/resumes/.
 * Returns the stored filename on success, or throws on failure.
 */
function accept_resume_pdf_upload(array $file_entry): string
{
    $tmp  = (string)($file_entry['tmp_name'] ?? '');
    $name = (string)($file_entry['name']     ?? '');
    $err  = (int)   ($file_entry['error']    ?? UPLOAD_ERR_NO_FILE);

    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error code ' . $err);
    }
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Not an uploaded file.');
    }

    // MIME validation — must be application/pdf.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);
    if ($mime !== 'application/pdf') {
        throw new RuntimeException('File must be a PDF (got ' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') . ').');
    }

    // Size cap: 20 MB.
    if (filesize($tmp) > 20 * 1024 * 1024) {
        throw new RuntimeException('File exceeds the 20 MB limit.');
    }

    // Generate a safe, unique filename.
    $ext      = 'pdf';
    $base     = preg_replace('/[^a-z0-9-]/', '-', strtolower(pathinfo($name, PATHINFO_FILENAME)));
    $base     = trim(preg_replace('/-+/', '-', (string)$base), '-') ?: 'resume';
    $filename = $base . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;

    $dir = _resume_pdf_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save uploaded file.');
    }

    return $filename;
}

/**
 * Derive YYYY-MM from a Unix timestamp (milliseconds, as supplied by
 * JS's File.lastModified). Falls back to current month/year.
 */
function resume_pdf_date_from_ms(int $ms): string
{
    $ts = $ms > 0 ? (int)($ms / 1000) : time();
    return date('Y-m', $ts);
}
