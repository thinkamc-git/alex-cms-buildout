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
 * Canonical (article) pipeline order. Forward transitions are limited to
 * the next neighbor; backward transitions can skip across (e.g. Published
 * → Draft) because the author sometimes wants to retract a live post
 * without stepping through Outline/Concept on the way.
 *
 * Per CMS-STRUCTURE.md §15 (Pipeline Stage Matrix), some content types
 * skip the Concept/Outline middle stages — see stages_for_type() below.
 */
const ARTICLE_STAGES = ['idea', 'concept', 'outline', 'draft', 'published'];

/**
 * Returns the stage progression valid for $type. Used by transition_stage
 * to allow "Idea → Draft" for types that skip Concept/Outline, and by the
 * editor views to render the right stage bar.
 *
 * NULL type uses the article progression — Idea-stage rows are untyped
 * by default; we keep the full bar visible while they're still untyped
 * so the author sees where the row could end up. Once typed, the bar
 * narrows.
 */
function stages_for_type(?string $type): array
{
    if ($type === 'journal' || $type === 'live-session' || $type === 'experiment') {
        return ['idea', 'draft', 'published'];
    }
    return ARTICLE_STAGES;
}

/**
 * Index of a stage in ARTICLE_STAGES (the article progression). Returns
 * -1 if not a known stage. Used by the article editor.
 */
function stage_index(string $stage): int
{
    $i = array_search($stage, ARTICLE_STAGES, true);
    return $i === false ? -1 : (int)$i;
}

/**
 * Index of a stage in the per-type progression.
 */
function stage_index_for_type(?string $type, string $stage): int
{
    $stages = stages_for_type($type);
    $i = array_search($stage, $stages, true);
    return $i === false ? -1 : (int)$i;
}

/**
 * Short relative-time string for pipeline kanban cards. Falls back to
 * "Mon DD" once the gap is more than a week — past that, exact-ish dates
 * are easier to scan than "37d ago".
 */
function relative_time(string $datetime): string
{
    if ($datetime === '') return '';
    $ts = strtotime($datetime);
    if ($ts === false) return '';
    $diff = time() - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return (int)floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return (int)floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return (int)floor($diff / 86400) . 'd ago';
    return date('M j', $ts);
}

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
    $sql = "SELECT id, slug, title, status, updated_at, published_at, special_tag, pipeline_order
            FROM content
            WHERE type = 'article'";
    $params = [];

    if (isset($filters['status']) && $filters['status'] !== '') {
        $sql .= " AND status = :status";
        $params[':status'] = (string)$filters['status'];
    }

    // Sort: pipeline_order ASC (0 first → unordered new captures at top,
    // dragged items get 1..N below), then recency as tiebreaker.
    $sql .= " ORDER BY pipeline_order ASC, updated_at DESC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Fetch all Idea-stage rows across every type (and untyped). Used by the
 * Ideation board. list_articles() can't return untyped rows because it
 * filters to type='article'.
 */
function list_ideation_rows(): array
{
    $sql = "SELECT id, slug, title, type, notes, updated_at, pipeline_order
              FROM content
             WHERE status = 'idea'
             ORDER BY pipeline_order ASC, updated_at DESC";
    return db()->query($sql)->fetchAll();
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
        'notes', 'concept_text', 'outline_text',
        'type',
        'published_at', 'published_status',
    ];

    if ($id === 0) {
        // INSERT — type defaults to 'article' if not explicitly passed
        // (preserves Phase 6a behaviour for /cms/articles/new "Draft from
        // scratch"). Quick-capture from Ideation passes type=null to land
        // in the "No type" lane.
        $data['status'] = (string)($data['status'] ?? 'draft');
        if (!array_key_exists('type', $data)) {
            $data['type'] = 'article';
        }
        $fields = [];
        $place  = [];
        $params = [];
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
    // No type filter: rows at Idea stage may be untyped (Phase 7.6 Ideation
    // captures) or of any future type. The caller is responsible for not
    // routing non-article writes through this function once the journal/
    // session/experiment editors land in later phases.
    $sql = "UPDATE content SET " . implode(', ', $set)
         . " WHERE id = :id";
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
    // Type-agnostic for the same reason save_article is: Idea-stage rows may
    // be untyped or of types whose dedicated editors aren't built yet.
    $stmt = db()->prepare("DELETE FROM content WHERE id = :id");
    $stmt->execute([':id' => $id]);
}

// ═════════════════════════════════════════════════════════════════════
// Journals (Phase 8)
// ═════════════════════════════════════════════════════════════════════

/**
 * Fetch a single journal row by id. Returns null if not found or not
 * of type='journal'. Idea-stage rows captured as journals only reach
 * type='journal' after the author types them — until then they live
 * in the type-agnostic Idea editor.
 */
function get_journal(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM content WHERE id = :id AND type = 'journal' LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch a published journal by slug for the public render path.
 */
function get_journal_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM content
          WHERE slug = :slug
            AND type = 'journal'
            AND status = 'published'
            AND (published_status IS NULL OR published_status = 'live')
          LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * List journals. Mirrors list_articles' filter shape.
 */
function list_journals(array $filters = []): array
{
    $sql = "SELECT id, slug, key_statement, title, status, updated_at, published_at,
                   journal_number, pipeline_order
              FROM content
             WHERE type = 'journal'";
    $params = [];

    if (isset($filters['status']) && $filters['status'] !== '') {
        $sql .= " AND status = :status";
        $params[':status'] = (string)$filters['status'];
    }

    $sql .= " ORDER BY pipeline_order ASC, updated_at DESC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Upsert a journal. Delegates to save_article with type forced to
 * 'journal' — the column whitelist and writeback logic are shared.
 */
function save_journal(array $data): int
{
    $data['type'] = 'journal';
    return save_article($data);
}

/**
 * Hard-delete a journal. delete_article is already type-agnostic;
 * named separately for callsite clarity.
 */
function delete_journal(int $id): void
{
    delete_article($id);
}

/**
 * Assign the per-category Journal entry number. Called once when a
 * journal transitions Draft → Published; subsequent re-publishes do
 * not reassign (the number is an archival identity, never reclaimed).
 *
 * Returns the assigned number, or null if the row isn't a journal.
 *
 * Counter logic per CMS-STRUCTURE.md §17:
 *   max(journal_number) WHERE type='journal' AND <same primary category>
 *
 * Categories admin hasn't shipped, so journals currently share a single
 * "uncategorized" bucket; the LEFT JOIN below handles that case. Once
 * categories land, the JOIN naturally partitions counters per category.
 */
function assign_journal_number(int $id): ?int
{
    $row = get_journal($id);
    if ($row === null) return null;

    $catStmt = db()->prepare(
        "SELECT category FROM content_categories
          WHERE content_id = :id AND is_primary = 1 LIMIT 1"
    );
    $catStmt->execute([':id' => $id]);
    $catRow   = $catStmt->fetch();
    $category = ($catRow === false || ($catRow['category'] ?? '') === '')
        ? null
        : (string)$catRow['category'];

    if ($category === null) {
        $q = db()->query(
            "SELECT COALESCE(MAX(c.journal_number), 0) + 1 AS n
               FROM content c
          LEFT JOIN content_categories cc ON cc.content_id = c.id AND cc.is_primary = 1
              WHERE c.type = 'journal' AND cc.category IS NULL"
        );
    } else {
        $q = db()->prepare(
            "SELECT COALESCE(MAX(c.journal_number), 0) + 1 AS n
               FROM content c
               JOIN content_categories cc ON cc.content_id = c.id AND cc.is_primary = 1
              WHERE c.type = 'journal' AND cc.category = :cat"
        );
        $q->execute([':cat' => $category]);
    }
    $next = (int)(($q->fetch()['n'] ?? 1));

    $upd = db()->prepare("UPDATE content SET journal_number = :n WHERE id = :id");
    $upd->execute([':n' => $next, ':id' => $id]);
    return $next;
}

/**
 * Move an article between pipeline stages.
 *
 * Forward transitions are limited to the immediate next stage. Backward
 * transitions are unrestricted — the author can pull a Published row
 * straight back to Draft without stepping through Outline.
 *
 * Returns ['ok' => bool, 'error' => string]. The caller is responsible
 * for any UI confirmation (Published → Draft requires a modal per
 * Phase 7 Decisions; this layer just performs the write).
 *
 * Side-effects:
 *   - Entering 'published' stamps `published_at` (if NULL) and sets
 *     `published_status` = 'live'.
 *   - Leaving 'published' clears `published_status` back to NULL.
 *     `published_at` is intentionally preserved so re-publishing later
 *     keeps the original go-live timestamp.
 */
function transition_stage(int $id, string $to): array
{
    // get_article() filters to type='article'. At Idea stage the row may
    // be untyped, so we use a raw fetch here to retrieve regardless of type.
    $stmt = db()->prepare("SELECT * FROM content WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return ['ok' => false, 'error' => 'Article not found.'];
    }
    $type = $row['type'] ?? null;
    if (is_string($type) && $type === '') $type = null;

    // Use the type-specific progression so journals (and other
    // shortened-pipeline types) can step Idea → Draft directly.
    $stages  = stages_for_type($type);
    $toIdx   = array_search($to, $stages, true);
    if ($toIdx === false) {
        return ['ok' => false, 'error' => 'Unknown stage for this type: ' . $to];
    }
    $from    = (string)($row['status'] ?? 'idea');
    $fromIdx = array_search($from, $stages, true);
    if ($fromIdx === false) {
        return ['ok' => false, 'error' => 'Row is in a stage invalid for its type.'];
    }
    if ($toIdx === $fromIdx) {
        return ['ok' => true, 'error' => ''];
    }
    if ($toIdx > $fromIdx + 1) {
        return [
            'ok'    => false,
            'error' => 'Cannot skip stages — advance one at a time (currently ' . ucfirst($from) . ').',
        ];
    }

    // Type guard: leaving Idea requires a type. Quick-capture creates rows
    // untyped; the author types them by dragging into a column in Ideation
    // or via the Type dropdown in the Idea editor.
    if ($from === 'idea' && $toIdx > $fromIdx) {
        if ($type === null) {
            return [
                'ok'    => false,
                'error' => 'Set a type before advancing — drag into a column in Ideation or pick one in the Type dropdown.',
            ];
        }
        if (!in_array($type, ['article', 'journal'], true)) {
            return [
                'ok'    => false,
                'error' => ucfirst((string)$type) . ' editor isn\'t available yet — that lands in a later phase.',
            ];
        }
    }

    $patch = ['status' => $to];
    if ($to === 'published') {
        $patch['published_status'] = 'live';
        if (empty($row['published_at'])) {
            $patch['published_at'] = date('Y-m-d H:i:s');
        }
    } elseif ($from === 'published') {
        $patch['published_status'] = null;
    }

    $set = [];
    $params = [':id' => $id];
    foreach ($patch as $k => $v) {
        $set[] = $k . ' = :' . $k;
        $params[':' . $k] = $v;
    }
    // No type filter: rows leaving Idea may have been untyped before the
    // type-guard above accepted them (i.e. they have a type now). Rows
    // moving backward have a type by definition.
    $sql = "UPDATE content SET " . implode(', ', $set)
         . " WHERE id = :id";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    // Side-effect: first publish of a journal earns it a per-category
    // entry number. Never reassigned on re-publish (numbers are archival).
    if ($to === 'published' && $type === 'journal' && empty($row['journal_number'])) {
        assign_journal_number($id);
    }

    return ['ok' => true, 'error' => ''];
}

/**
 * Whitelist of content types. Mirrors the schema enum. NULL = untyped
 * (Idea stage only).
 */
const CONTENT_TYPES = ['article', 'journal', 'live-session', 'experiment'];

/**
 * Assign a type to an Idea-stage row. Returns ['ok' => bool, 'error' => string].
 *
 * Constrained to status='idea' rows — once advanced past Idea the type is
 * locked. (Type changes after Idea would invalidate slug semantics, public
 * routes, and any indexes that have already adopted the row.)
 *
 * Accepts null to clear the type (back to "No type" lane in Ideation).
 */
function set_article_type(int $id, ?string $type): array
{
    if ($type !== null && !in_array($type, CONTENT_TYPES, true)) {
        return ['ok' => false, 'error' => 'Unknown type: ' . $type];
    }
    $stmt = db()->prepare("SELECT status FROM content WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return ['ok' => false, 'error' => 'Row not found.'];
    }
    if ((string)($row['status'] ?? '') !== 'idea') {
        return ['ok' => false, 'error' => 'Type is locked once an idea advances past Idea.'];
    }
    $upd = db()->prepare("UPDATE content SET type = :type WHERE id = :id");
    $upd->execute([':type' => $type, ':id' => $id]);
    return ['ok' => true, 'error' => ''];
}

/**
 * Rewrite pipeline_order for every row matching $criteria, in the order
 * given by $ids. Returns ['ok' => bool, 'error' => string].
 *
 * $criteria is a column => value map, e.g.
 *   ['type' => 'article', 'status' => 'idea']    (Pipeline lane)
 *   ['status' => 'idea', 'type' => 'article']    (Ideation lane)
 *   ['status' => 'idea', 'type' => null]         (Ideation "No type")
 *
 * Ids not matching the criteria are rejected — that's the safety net
 * against tampered POSTs trying to reorder rows in other lanes.
 *
 * Position 0 in $ids becomes pipeline_order = 1 (top of lane).
 */
function reorder_lane(array $criteria, array $ids): array
{
    if (count($ids) === 0) {
        return ['ok' => true, 'error' => ''];
    }
    // Build a WHERE that matches the lane. NULL values use IS NULL.
    $where  = [];
    $params = [];
    foreach ($criteria as $col => $val) {
        if (!preg_match('/^[a-z_]+$/', (string)$col)) {
            return ['ok' => false, 'error' => 'Invalid criteria key.'];
        }
        if ($val === null) {
            $where[] = "$col IS NULL";
        } else {
            $where[] = "$col = :crit_$col";
            $params[":crit_$col"] = $val;
        }
    }
    $whereSql = implode(' AND ', $where);

    // Fetch all current ids in the lane to validate membership.
    $stmt = db()->prepare("SELECT id FROM content WHERE $whereSql");
    $stmt->execute($params);
    $valid = [];
    foreach ($stmt->fetchAll() as $r) {
        $valid[(int)$r['id']] = true;
    }
    foreach ($ids as $id) {
        if (!isset($valid[(int)$id])) {
            return [
                'ok'    => false,
                'error' => 'Row #' . (int)$id . ' is not in this lane.',
            ];
        }
    }

    // Apply 1..N in the supplied order. Other rows in the lane that weren't
    // submitted (e.g. created concurrently) keep their existing values.
    $pos = 0;
    $upd = db()->prepare("UPDATE content SET pipeline_order = :pos WHERE id = :id");
    foreach ($ids as $id) {
        $pos++;
        $upd->execute([':pos' => $pos, ':id' => (int)$id]);
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * Stage histogram for the pipeline header. Returns an assoc map keyed
 * by every stage in ARTICLE_STAGES, with zero defaults so the caller
 * doesn't have to null-coalesce.
 */
function count_articles_by_stage(): array
{
    $out = array_fill_keys(ARTICLE_STAGES, 0);
    $rows = db()->query(
        "SELECT status, COUNT(*) AS n FROM content
         WHERE type = 'article'
         GROUP BY status"
    )->fetchAll();
    foreach ($rows as $r) {
        $s = (string)($r['status'] ?? '');
        if (isset($out[$s])) $out[$s] = (int)$r['n'];
    }
    return $out;
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
