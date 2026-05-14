<?php
declare(strict_types=1);

/**
 * lib/content.php — article CRUD (Phase 6a).
 *
 * One file owns the `content` table. Other content types (journal,
 * live-session, experiment) land in Phases 8 / 9 / 10 — when they do,
 * extend this file rather than creating siblings.
 *
 * Functions return plain associative arrays (or void / int). No HTML,
 * no headers, no superglobals — see ENGINEERING.md §4.2.
 */

require_once __DIR__ . '/db.php';

/**
 * Fetch a single article by id. Returns null if not found.
 *
 * Lookups are by id (auto-increment PK) for CMS edit URLs. The public
 * site (Phase 6b) will add a `get_article_by_slug` sibling — kept
 * separate so each query path is explicit and indexable.
 */
function get_article(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM content WHERE id = :id AND type = 'article' LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * List articles. Supports an optional 'status' filter
 * (e.g. ['status' => 'draft']). Ordered newest-first by updated_at.
 *
 * Phase 6a only renders the full list — filter UI is wired in Phase 7.
 * Keep the function open to filters now to avoid breaking callers later.
 */
function list_articles(array $filters = []): array
{
    $sql = "SELECT id, slug, title, status, updated_at, published_at, special_tag
            FROM content
            WHERE type = 'article'";
    $params = [];

    if (isset($filters['status']) && $filters['status'] !== '') {
        $sql .= " AND status = :status";
        $params[':status'] = (string)$filters['status'];
    }

    $sql .= " ORDER BY updated_at DESC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Upsert an article. Returns the row id.
 *
 * Accepts a plain array. Missing keys are treated as "don't update" on
 * UPDATE, and as NULL on INSERT (the column defaults handle the rest).
 * Body is expected to have already been run through sanitize_html()
 * before reaching this function — see ENGINEERING.md §10.
 */
function save_article(array $data): int
{
    $id = isset($data['id']) ? (int)$data['id'] : 0;

    // Columns this function knows about. Phase 6a writes Draft-stage
    // fields; later phases extend the column list (hero_image, special_tag
    // values like 'principle'/'framework', etc. are already accepted).
    $cols = [
        'slug', 'status', 'template', 'title', 'summary', 'body',
        'hero_image', 'hero_caption', 'hero_size',
        'show_author', 'show_author_bio',
        'special_tag', 'series_id', 'series_order',
        'read_time', 'tags',
        'concept_text', 'outline_text',
        'published_at', 'published_status',
    ];

    if ($id === 0) {
        // INSERT — type is hardcoded; status defaults to draft if not given.
        $data['status'] = (string)($data['status'] ?? 'draft');
        $fields = ['type'];
        $place  = [':type'];
        $params = [':type' => 'article'];
        foreach ($cols as $c) {
            if (array_key_exists($c, $data)) {
                $fields[] = $c;
                $place[]  = ':' . $c;
                $params[':' . $c] = $data[$c];
            }
        }
        $sql = 'INSERT INTO content (' . implode(',', $fields) . ') VALUES ('
             . implode(',', $place) . ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return (int)db()->lastInsertId();
    }

    // UPDATE — only the keys present in $data are touched.
    $set = [];
    $params = [':id' => $id];
    foreach ($cols as $c) {
        if (array_key_exists($c, $data)) {
            $set[] = $c . ' = :' . $c;
            $params[':' . $c] = $data[$c];
        }
    }
    if (count($set) === 0) {
        return $id;
    }
    $sql = "UPDATE content SET " . implode(', ', $set)
         . " WHERE id = :id AND type = 'article'";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $id;
}

/**
 * Hard-delete an article. Phase 6a Decisions: confirmation modal +
 * hard delete (no soft delete in v1). content_categories rows cascade
 * via FK ON DELETE CASCADE.
 */
function delete_article(int $id): void
{
    $stmt = db()->prepare(
        "DELETE FROM content WHERE id = :id AND type = 'article'"
    );
    $stmt->execute([':id' => $id]);
}

/**
 * Generate a URL-safe slug from a string. Lowercase, ASCII-ish, hyphenated.
 *
 * Phase 6a Decision: slugs auto-generate from title on first save and
 * stay editable while a content row is unpublished. Publication is the
 * point at which slugs become permanent (enforced in Phase 11).
 */
function slugify(string $input): string
{
    $s = trim($input);
    if ($s === '') return '';
    // Replace any non-alphanumeric run with a single hyphen, lowercase.
    $s = preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    $s = strtolower($s);
    // Cap at 200 chars so we stay well under the column's 255.
    if (strlen($s) > 200) $s = substr($s, 0, 200);
    return $s;
}

/**
 * Ensure a slug is unique within the content table. If the candidate
 * already exists (excluding $excludeId if given), append -2, -3, etc.
 */
function unique_slug(string $candidate, int $excludeId = 0): string
{
    if ($candidate === '') return '';
    $base = $candidate;
    $n = 1;
    while (true) {
        $try = $n === 1 ? $base : ($base . '-' . $n);
        $stmt = db()->prepare(
            'SELECT id FROM content WHERE slug = :slug AND id <> :id LIMIT 1'
        );
        $stmt->execute([':slug' => $try, ':id' => $excludeId]);
        if ($stmt->fetch() === false) return $try;
        $n++;
        if ($n > 1000) {
            // Defensive — should never happen in single-author usage.
            return $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }
    }
}
