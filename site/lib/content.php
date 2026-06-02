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
    $sql = "SELECT c.id, c.slug, c.title, c.status, c.updated_at, c.published_at,
                   c.published_status,
                   c.special_tag, c.pipeline_order,
                   c.series_id, c.series_order,
                   s.name AS series_name, s.slug AS series_slug
              FROM content c
         LEFT JOIN series s ON s.id = c.series_id
             WHERE c.type = 'article'";
    $params = [];

    if (isset($filters['status']) && $filters['status'] !== '') {
        $sql .= " AND c.status = :status";
        $params[':status'] = (string)$filters['status'];
    }

    // Sort: pipeline_order ASC (0 first → unordered new captures at top,
    // dragged items get 1..N below), then recency as tiebreaker.
    $sql .= " ORDER BY c.pipeline_order ASC, c.updated_at DESC";

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
        'show_updated', 'updated_display',
        'special_tag', 'series_id', 'series_order',
        'read_time', 'tags',
        'notes', 'concept_text', 'outline_text',
        'key_statement',
        // Live-session fields (Phase 9). NULL each to hide the matching pill.
        // event_date is required at save-time; event_time + event_end_time
        // are independently optional (see live-session-edit.php).
        'event_date', 'event_time', 'event_end_time',
        'location', 'venue', 'cost_pill', 'attendance', 'custom_pill',
        // Experiment fields (Phase 10). source_file is just the filename
        // inside /content/experiment/<slug>/ — the full path is derived.
        'source_file',
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
                   published_status,
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
        if (!in_array($type, ['article', 'journal', 'live-session', 'experiment'], true)) {
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
 * Schedule a content row for future publish. Sets status='published' +
 * published_status='scheduled' + published_at=<future datetime>. The cron
 * (cron/scheduled-publish.php, Phase 13) sweeps every 5 minutes and flips
 * 'scheduled' → 'live' once the date arrives.
 *
 * Phase 14.6 — added to complete the CMS-side scheduling UX. The
 * infrastructure (schema column, cron, public-route gate) all shipped
 * earlier; only this helper + the edit-view UI were missing.
 *
 * Allowed source states: draft, or already-scheduled (re-schedule with
 * a new date). Other stages (idea/concept/outline) must Advance to Draft
 * first — guarded here as a defence-in-depth.
 *
 * Date must be strictly in the future. Validation rejects past + present
 * (within 60s of NOW). Returns ['ok' => bool, 'error' => string,
 * 'published_at' => string?].
 */
function schedule_content(int $id, string $datetime): array
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return ['ok' => false, 'error' => 'Invalid date/time format.'];
    }
    // Require at least 60s in the future — guards against accidental
    // immediate-publish-via-schedule from clock skew or fast-clicks.
    if ($ts <= time() + 60) {
        return ['ok' => false, 'error' => 'Schedule time must be at least a minute in the future.'];
    }
    $normalized = date('Y-m-d H:i:s', $ts);

    $stmt = db()->prepare("SELECT id, status, type, journal_number FROM content WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row === false) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }

    $from = (string)($row['status'] ?? 'idea');
    if ($from !== 'draft' && $from !== 'published') {
        return ['ok' => false, 'error' => 'Schedule is only available from the Draft stage.'];
    }

    $type = (string)($row['type'] ?? '');
    if (!in_array($type, CONTENT_TYPES, true)) {
        return ['ok' => false, 'error' => 'Cannot schedule untyped content.'];
    }

    $stmt = db()->prepare(
        "UPDATE content
            SET status = 'published',
                published_status = 'scheduled',
                published_at = :pat
          WHERE id = :id"
    );
    $stmt->execute([':pat' => $normalized, ':id' => $id]);

    // Side-effect: first publish (including scheduled first-publish) of a
    // journal earns it a per-category entry number. Mirrors the rule in
    // transition_stage() — entry numbers are archival, never reassigned.
    if ($type === 'journal' && empty($row['journal_number'])) {
        assign_journal_number($id);
    }

    return ['ok' => true, 'error' => '', 'published_at' => $normalized];
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

// ═════════════════════════════════════════════════════════════════════
// Live Sessions (Phase 9)
// ═════════════════════════════════════════════════════════════════════

/**
 * Fetch a single live-session row by id. Returns null when not of
 * type='live-session'. Idea-stage rows captured as live-sessions only
 * earn type='live-session' once typed in Ideation or via the editor's
 * Type dropdown — until then they live in the type-agnostic Idea editor.
 */
function get_live_session(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM content WHERE id = :id AND type = 'live-session' LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch a published live-session by slug for the public render path.
 */
function get_live_session_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM content
          WHERE slug = :slug
            AND type = 'live-session'
            AND status = 'published'
            AND (published_status IS NULL OR published_status = 'live')
          LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * List live-sessions. Mirrors list_articles' filter shape.
 */
function list_live_sessions(array $filters = []): array
{
    $sql = "SELECT id, slug, title, status, updated_at, published_at,
                   published_status,
                   event_date, event_time, event_end_time,
                   location, venue, cost_pill, attendance, custom_pill,
                   pipeline_order
              FROM content
             WHERE type = 'live-session'";
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
 * Upsert a live-session. Delegates to save_article with type forced to
 * 'live-session'.
 */
function save_live_session(array $data): int
{
    $data['type'] = 'live-session';
    return save_article($data);
}

/**
 * Hard-delete a live-session. delete_article is already type-agnostic;
 * named separately for callsite clarity.
 */
function delete_live_session(int $id): void
{
    delete_article($id);
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

/**
 * Estimate read-time in whole minutes from sanitized body HTML. Strips tags
 * and entities, counts words, divides by an average reading speed of 225 wpm
 * (rounded up). Returns 0 for empty/whitespace-only bodies; otherwise at
 * least 1 so a non-empty body never reads as "0 min".
 *
 * Mirrored client-side in article-edit.php so the sidebar field can show a
 * live estimate as the user types. Kept here as a server-side fallback for
 * the JS-disabled / submit-time path.
 */
function estimate_read_minutes(string $html): int
{
    if ($html === '') return 0;
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = trim($text);
    if ($text === '') return 0;
    // str_word_count is ASCII-only; for prose this is close enough and avoids
    // adding intl/mbstring deps. Falls back gracefully on non-Latin text.
    $count = str_word_count($text);
    if ($count === 0) return 0;
    return max(1, (int)ceil($count / 225));
}

// ═════════════════════════════════════════════════════════════════════
// Experiments (Phase 10)
// ═════════════════════════════════════════════════════════════════════

/**
 * Fetch a single experiment row by id. Returns null when not of
 * type='experiment'. As with the other types, Idea-stage captures only
 * earn type='experiment' once typed — until then they live in the
 * type-agnostic Idea editor.
 */
function get_experiment(int $id): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM content WHERE id = :id AND type = 'experiment' LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch a published experiment by slug for the public render path.
 * Used by render_content() — same shape as get_journal_by_slug etc.
 */
function get_experiment_by_slug(string $slug): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM content
          WHERE slug = :slug
            AND type = 'experiment'
            AND status = 'published'
            AND (published_status IS NULL OR published_status = 'live')
          LIMIT 1"
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * List experiments. Mirrors list_articles' filter shape, plus includes
 * source_file so the list view can flag rows whose folder picker is empty.
 */
function list_experiments(array $filters = []): array
{
    $sql = "SELECT id, slug, title, status, updated_at, published_at,
                   published_status,
                   template, source_file, pipeline_order
              FROM content
             WHERE type = 'experiment'";
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
 * Upsert an experiment. Delegates to save_article with type forced to
 * 'experiment'. The shared column whitelist already accepts `template`
 * and `source_file` (defined since the initial schema), so no extra
 * plumbing is needed here — just the type override.
 */
function save_experiment(array $data): int
{
    $data['type'] = 'experiment';
    return save_article($data);
}

/**
 * Hard-delete an experiment. delete_article is type-agnostic; named
 * separately for callsite clarity. Note: the on-disk content folder
 * (if any) is removed independently by the delete view, which calls
 * folder_delete() *before* this — see experiment-delete.php.
 */
function delete_experiment(int $id): void
{
    delete_article($id);
}

// ═════════════════════════════════════════════════════════════════════
// Categories (Phase 11)
// ═════════════════════════════════════════════════════════════════════

/**
 * The 18 design-system palette tokens (sans the `--c-` prefix).
 * The categories admin dropdown enumerates this list; the colour cell
 * renders as `background:var(--c-<token>)`. New categories must pick
 * from this set — no raw hex.
 */
const PALETTE_COLORS = [
    'rust', 'terracotta', 'clay',  'amber',  'ochre',  'olive',
    'moss', 'forest',     'sage',  'teal',   'ocean',  'denim',
    'indigo','purple',    'violet','plum',   'mauve',  'rose',
];

/**
 * Content types that carry categories. Mirrors the seed in
 * db/migrations/0006_seed_initial_categories.sql.
 */
const CATEGORY_TYPES = ['article', 'journal', 'live-session', 'experiment'];

/**
 * Set the primary category for a content row. Idempotent — drops any
 * existing primary on this row, then inserts the new one if a non-empty
 * value_slug is provided. Passing '' clears the primary.
 *
 * Validates against the categories table: an unknown value_slug for the
 * given type is silently ignored (returns false). The caller can decide
 * whether that warrants a user-visible error.
 */
function assign_primary_category(int $contentId, string $type, string $valueSlug): bool
{
    if ($contentId <= 0 || $type === '') return false;

    // Drop any existing primary on this row (also picks up legacy duplicates).
    db()->prepare(
        'DELETE FROM content_categories WHERE content_id = :id AND is_primary = 1'
    )->execute([':id' => $contentId]);

    if ($valueSlug === '') return true;

    // Validate the slug against the categories table for this type.
    $check = db()->prepare(
        'SELECT 1 FROM categories WHERE type = :t AND value_slug = :s LIMIT 1'
    );
    $check->execute([':t' => $type, ':s' => $valueSlug]);
    if ($check->fetchColumn() === false) return false;

    db()->prepare(
        'INSERT INTO content_categories (content_id, type, category, is_primary)
         VALUES (:id, :t, :s, 1)'
    )->execute([':id' => $contentId, ':t' => $type, ':s' => $valueSlug]);
    return true;
}

/**
 * Fetch the primary-category value_slug for a content row, or '' if none.
 * Used by edit forms to preselect the current value in the dropdown.
 */
function get_primary_category(int $contentId): string
{
    if ($contentId <= 0) return '';
    $stmt = db()->prepare(
        'SELECT category FROM content_categories
          WHERE content_id = :id AND is_primary = 1
          LIMIT 1'
    );
    $stmt->execute([':id' => $contentId]);
    $val = $stmt->fetchColumn();
    return $val === false ? '' : (string)$val;
}

/**
 * List categories. If $type is given, scopes to that content type
 * (article / journal / live-session / experiment). Returns rows with
 * a `usage_count` integer derived from content_categories.
 *
 * Ordered by sort_order ASC then label ASC so the admin and dropdowns
 * agree on display order.
 */
function list_categories(?string $type = null): array
{
    $sql = "SELECT c.*, (
              SELECT COUNT(*) FROM content_categories cc
               WHERE cc.type = c.type AND cc.category = c.value_slug
            ) AS usage_count
              FROM categories c";
    $params = [];
    if ($type !== null) {
        $sql .= " WHERE c.type = :t";
        $params[':t'] = $type;
    }
    $sql .= " ORDER BY c.type ASC, c.sort_order ASC, c.label ASC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Fetch a single category row by id.
 */
function get_category(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * True if a (type, value_slug) already exists. Used by save_category to
 * block duplicate adds. $excludeId lets updates skip their own row.
 */
function category_value_slug_exists(string $type, string $slug, int $excludeId = 0): bool
{
    $stmt = db()->prepare(
        'SELECT id FROM categories
          WHERE type = :t AND value_slug = :s AND id <> :id
          LIMIT 1'
    );
    $stmt->execute([':t' => $type, ':s' => $slug, ':id' => $excludeId]);
    return $stmt->fetch() !== false;
}

/**
 * Upsert a category. Returns ['ok' => bool, 'error' => string, 'id' => int].
 *
 * INSERT path requires (type, label, colour). value_slug is derived
 * from the label via slugify() and must be unique within the type.
 *
 * UPDATE path requires id and changes only label + colour. type and
 * value_slug are permanent (per CMS-STRUCTURE.md §10) — renaming the
 * slug would orphan every content_categories row referencing it.
 */
function save_category(array $data): array
{
    $id     = (int)($data['id'] ?? 0);
    $type   = (string)($data['type'] ?? '');
    $label  = trim((string)($data['label'] ?? ''));
    $colour = (string)($data['colour'] ?? '');

    if (!in_array($colour, PALETTE_COLORS, true)) {
        return ['ok' => false, 'error' => 'Colour must be one of the design-system palette tokens.', 'id' => 0];
    }
    if ($label === '') {
        return ['ok' => false, 'error' => 'Label is required.', 'id' => 0];
    }

    if ($id === 0) {
        if (!in_array($type, CATEGORY_TYPES, true)) {
            return ['ok' => false, 'error' => 'Type must be article, journal, live-session, or experiment.', 'id' => 0];
        }
        $slug = slugify($label);
        if ($slug === '') {
            return ['ok' => false, 'error' => 'Label must contain letters or numbers.', 'id' => 0];
        }
        if (category_value_slug_exists($type, $slug)) {
            return ['ok' => false, 'error' => 'Slug "' . $slug . '" is already used in ' . $type . ' categories.', 'id' => 0];
        }

        // Append at end — leave gaps of 10 so future manual reorders have headroom.
        $maxStmt = db()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) + 10 AS n FROM categories WHERE type = :t'
        );
        $maxStmt->execute([':t' => $type]);
        $sortOrder = (int)($maxStmt->fetch()['n'] ?? 10);

        $stmt = db()->prepare(
            'INSERT INTO categories (type, value_slug, label, colour, sort_order)
             VALUES (:t, :s, :l, :c, :o)'
        );
        $stmt->execute([
            ':t' => $type, ':s' => $slug, ':l' => $label,
            ':c' => $colour, ':o' => $sortOrder,
        ]);
        return ['ok' => true, 'error' => '', 'id' => (int)db()->lastInsertId()];
    }

    // UPDATE — label and colour only. type + value_slug stay locked.
    $existing = get_category($id);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Category not found.', 'id' => 0];
    }
    $stmt = db()->prepare(
        'UPDATE categories SET label = :l, colour = :c WHERE id = :id'
    );
    $stmt->execute([':l' => $label, ':c' => $colour, ':id' => $id]);
    return ['ok' => true, 'error' => '', 'id' => $id];
}

/**
 * Hard-delete a category. Blocked when any content row references it
 * (usage_count > 0) — the admin disables the trash icon in that case;
 * this guard is the server-side enforcement.
 */
function delete_category(int $id): array
{
    $cat = get_category($id);
    if ($cat === null) {
        return ['ok' => false, 'error' => 'Category not found.'];
    }
    $cnt = db()->prepare(
        'SELECT COUNT(*) AS n FROM content_categories
          WHERE type = :t AND category = :s'
    );
    $cnt->execute([':t' => (string)$cat['type'], ':s' => (string)$cat['value_slug']]);
    $n = (int)($cnt->fetch()['n'] ?? 0);
    if ($n > 0) {
        return ['ok' => false, 'error' => 'In use by ' . $n . ' piece(s) of content — unassign first.'];
    }
    $stmt = db()->prepare('DELETE FROM categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return ['ok' => true, 'error' => ''];
}

// ═════════════════════════════════════════════════════════════════════
// Series (Phase 11)
// ═════════════════════════════════════════════════════════════════════

/**
 * List every series with a derived `parts_count` (rows on `content`
 * with matching series_id, regardless of stage — drafts count toward
 * "n parts" so the author sees the in-progress weight of each series).
 */
function list_series(): array
{
    return db()->query(
        "SELECT s.*, (
            SELECT COUNT(*) FROM content c WHERE c.series_id = s.id
         ) AS parts_count
           FROM series s
          ORDER BY s.name ASC"
    )->fetchAll();
}

/**
 * Fetch one series by id.
 */
function get_series(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM series WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Ensure a series slug is unique. Mirrors unique_slug for content but
 * scoped to the `series` table.
 */
function unique_series_slug(string $candidate, int $excludeId = 0): string
{
    if ($candidate === '') return '';
    $base = $candidate;
    $n = 1;
    while (true) {
        $try = $n === 1 ? $base : ($base . '-' . $n);
        $stmt = db()->prepare(
            'SELECT id FROM series WHERE slug = :s AND id <> :id LIMIT 1'
        );
        $stmt->execute([':s' => $try, ':id' => $excludeId]);
        if ($stmt->fetch() === false) return $try;
        $n++;
        if ($n > 1000) {
            return $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }
    }
}

/**
 * Upsert a series. Returns ['ok' => bool, 'error' => string, 'id' => int].
 *
 * INSERT generates the slug from name (or accepts an explicit override)
 * and uniqifies it. UPDATE leaves the slug permanent (it's part of the
 * /series/[slug]/ public URL) — only name and description change.
 *
 * Phase 12 will iterate over `series` to auto-create matching Editorial
 * Page indexes at /series/[slug]/. Until then, creating a series here
 * just stores the row — no index side-effect.
 */
function save_series(array $data): array
{
    $id          = (int)($data['id'] ?? 0);
    $name        = trim((string)($data['name'] ?? ''));
    $slug        = trim((string)($data['slug'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));

    if ($name === '') {
        return ['ok' => false, 'error' => 'Name is required.', 'id' => 0];
    }

    if ($id === 0) {
        $slug = $slug !== '' ? slugify($slug) : slugify($name);
        if ($slug === '') {
            return ['ok' => false, 'error' => 'Slug could not be generated — provide a name or slug containing letters or numbers.', 'id' => 0];
        }
        $slug = unique_series_slug($slug);
        $stmt = db()->prepare(
            'INSERT INTO series (name, slug, description) VALUES (:n, :s, :d)'
        );
        $stmt->execute([':n' => $name, ':s' => $slug, ':d' => $description]);
        return ['ok' => true, 'error' => '', 'id' => (int)db()->lastInsertId()];
    }

    // UPDATE — name + description only.
    $existing = get_series($id);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Series not found.', 'id' => 0];
    }
    $stmt = db()->prepare(
        'UPDATE series SET name = :n, description = :d WHERE id = :id'
    );
    $stmt->execute([':n' => $name, ':d' => $description, ':id' => $id]);
    return ['ok' => true, 'error' => '', 'id' => $id];
}

/**
 * Hard-delete a series. Blocked when any content row still references it
 * via series_id — the author has to unassign every part first.
 */
function delete_series(int $id): array
{
    $cnt = db()->prepare('SELECT COUNT(*) AS n FROM content WHERE series_id = :id');
    $cnt->execute([':id' => $id]);
    $n = (int)($cnt->fetch()['n'] ?? 0);
    if ($n > 0) {
        return ['ok' => false, 'error' => 'In use by ' . $n . ' piece(s) of content — unassign first.'];
    }
    $stmt = db()->prepare('DELETE FROM series WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return ['ok' => true, 'error' => ''];
}

/**
 * List articles that aren't currently assigned to any series. Used by
 * the series-card "+ Add article" picker. type='article' only — the
 * series flow is article-centric per CMS-STRUCTURE.md §16. Ordered by
 * title so the dropdown is easy to scan.
 */
function list_unassigned_articles(): array
{
    return db()->query(
        "SELECT id, slug, title, status
           FROM content
          WHERE type = 'article' AND series_id IS NULL
          ORDER BY title ASC"
    )->fetchAll();
}

/**
 * Renumber every part of a series to a contiguous 1..N sequence based
 * on the current series_order (NULLs and gaps fall to the end via the
 * deterministic id tiebreaker). Called after add_article_to_series and
 * remove_article_from_series so the visible "01 / 02 / 03…" never
 * skips. Pure side-effect — no return value.
 */
function compact_series_order(int $seriesId): void
{
    if ($seriesId <= 0) return;
    $stmt = db()->prepare(
        "SELECT id FROM content
          WHERE series_id = :s AND type = 'article'
          ORDER BY series_order IS NULL, series_order ASC, id ASC"
    );
    $stmt->execute([':s' => $seriesId]);
    $i = 0;
    $upd = db()->prepare('UPDATE content SET series_order = :o WHERE id = :id');
    foreach ($stmt->fetchAll() as $row) {
        $i++;
        $upd->execute([':o' => $i, ':id' => (int)$row['id']]);
    }
}

/**
 * Rewrite series_order for $seriesId using the explicit $articleIds
 * order (position 0 → series_order = 1, etc.). Ids not currently in
 * this series are rejected — defends against tampered POSTs.
 *
 * Returns ['ok' => bool, 'error' => str]. Used by the drag-drop endpoint.
 */
function reorder_series_parts(int $seriesId, array $articleIds): array
{
    if ($seriesId <= 0) {
        return ['ok' => false, 'error' => 'Bad series id.'];
    }
    if (count($articleIds) === 0) {
        return ['ok' => true, 'error' => ''];
    }
    $stmt = db()->prepare(
        "SELECT id FROM content WHERE series_id = :s AND type = 'article'"
    );
    $stmt->execute([':s' => $seriesId]);
    $valid = [];
    foreach ($stmt->fetchAll() as $r) {
        $valid[(int)$r['id']] = true;
    }
    foreach ($articleIds as $aid) {
        if (!isset($valid[(int)$aid])) {
            return ['ok' => false, 'error' => 'Article #' . (int)$aid . ' is not in this series.'];
        }
    }

    $pos = 0;
    $upd = db()->prepare('UPDATE content SET series_order = :o WHERE id = :id');
    foreach ($articleIds as $aid) {
        $pos++;
        $upd->execute([':o' => $pos, ':id' => (int)$aid]);
    }
    return ['ok' => true, 'error' => ''];
}

/**
 * Attach an article to a series. series_order is appended (max + 1)
 * so the new part lands at the end. Returns ['ok' => bool, 'error' => str].
 *
 * Limited to type='article' rows — the series flow is article-centric
 * and other types don't currently get series UI on their edit screens.
 */
function add_article_to_series(int $articleId, int $seriesId): array
{
    if ($articleId <= 0 || $seriesId <= 0) {
        return ['ok' => false, 'error' => 'Bad ids.'];
    }
    $s = get_series($seriesId);
    if ($s === null) {
        return ['ok' => false, 'error' => 'Series not found.'];
    }
    // Confirm the article exists and is type='article' before writing.
    $check = db()->prepare(
        "SELECT id, series_id FROM content WHERE id = :id AND type = 'article' LIMIT 1"
    );
    $check->execute([':id' => $articleId]);
    $row = $check->fetch();
    if ($row === false) {
        return ['ok' => false, 'error' => 'Article not found.'];
    }

    // Append at the end of the series.
    $maxStmt = db()->prepare(
        'SELECT COALESCE(MAX(series_order), 0) + 1 AS n FROM content WHERE series_id = :s'
    );
    $maxStmt->execute([':s' => $seriesId]);
    $nextOrder = (int)($maxStmt->fetch()['n'] ?? 1);

    $upd = db()->prepare(
        'UPDATE content SET series_id = :s, series_order = :o WHERE id = :id'
    );
    $upd->execute([':s' => $seriesId, ':o' => $nextOrder, ':id' => $articleId]);
    compact_series_order($seriesId);
    return ['ok' => true, 'error' => ''];
}

/**
 * Detach an article from its series. Clears both series_id and
 * series_order. The remaining parts keep their existing orders — a
 * gap in the sequence is harmless (the public index sorts by ASC).
 */
function remove_article_from_series(int $articleId): array
{
    if ($articleId <= 0) {
        return ['ok' => false, 'error' => 'Bad id.'];
    }
    // Capture the source series before clearing so we can compact it.
    $look = db()->prepare("SELECT series_id FROM content WHERE id = :id AND type = 'article' LIMIT 1");
    $look->execute([':id' => $articleId]);
    $prevRow  = $look->fetch();
    $prevSid  = $prevRow !== false ? (int)($prevRow['series_id'] ?? 0) : 0;

    $upd = db()->prepare(
        "UPDATE content SET series_id = NULL, series_order = NULL
          WHERE id = :id AND type = 'article'"
    );
    $upd->execute([':id' => $articleId]);
    if ($prevSid > 0) compact_series_order($prevSid);
    return ['ok' => true, 'error' => ''];
}

// ═════════════════════════════════════════════════════════════════════
// Slug guard (Phase 11)
// ═════════════════════════════════════════════════════════════════════

/**
 * Look up a path in the redirects table. Returns the new target path,
 * or null if no row matches.
 *
 * Callers expected to pass the bare path with query string stripped
 * (e.g. `/writing/old-slug`). Phase 13 extends this with a status_code
 * column; for now every redirect is treated as 301 by the render layer.
 */
function lookup_redirect(string $path): ?string
{
    if ($path === '') return null;
    $stmt = db()->prepare(
        'SELECT new_slug FROM redirects WHERE old_slug = :p LIMIT 1'
    );
    $stmt->execute([':p' => $path]);
    $row = $stmt->fetch();
    return $row === false ? null : (string)$row['new_slug'];
}

// ═════════════════════════════════════════════════════════════════════
// Cross-type list helpers (Phase 19 — Writer's Desk)
// ═════════════════════════════════════════════════════════════════════

/**
 * List every scheduled row across all 4 content types, grouped by
 * calendar week in the author's timezone. Week boundary is Monday 00:00
 * through Sunday 23:59 (ISO-style). Returns three buckets:
 *   ['this_week' => [...], 'next_week' => [...], 'future' => [...]]
 * Each row carries `type` ('article'|'journal'|'live-session'|'experiment')
 * so callers can route edit URLs without an extra query.
 *
 * Used by Draft Writing's Scheduled column. Sort within each bucket is
 * published_at ASC (soonest-next first).
 */
function list_scheduled_content(string $tz = 'America/Vancouver'): array
{
    $stmt = db()->prepare(
        "SELECT id, slug, type, title, key_statement, status,
                published_status, published_at, updated_at
           FROM content
          WHERE status = 'published'
            AND published_status = 'scheduled'
            AND published_at IS NOT NULL
       ORDER BY published_at ASC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $zone        = new DateTimeZone($tz);
    $now         = new DateTimeImmutable('now', $zone);
    // Monday-of-this-week 00:00 in author tz.
    $weekStart   = $now->modify('monday this week')->setTime(0, 0, 0);
    // Inclusive boundaries: this_week_end = Sunday 23:59:59 of this week.
    $thisWeekEnd = $weekStart->modify('+7 days')->modify('-1 second');
    $nextWeekEnd = $weekStart->modify('+14 days')->modify('-1 second');

    $buckets = ['this_week' => [], 'next_week' => [], 'future' => []];
    foreach ($rows as $r) {
        $when = new DateTimeImmutable((string)$r['published_at'], new DateTimeZone('UTC'));
        $whenLocal = $when->setTimezone($zone);
        if ($whenLocal <= $thisWeekEnd) {
            $buckets['this_week'][] = $r;
        } elseif ($whenLocal <= $nextWeekEnd) {
            $buckets['next_week'][] = $r;
        } else {
            $buckets['future'][] = $r;
        }
    }
    return $buckets;
}

/**
 * List the N most recently published rows across all 4 content types.
 * Filters out scheduled rows (only `published_status='live'` or NULL).
 * Returns rows already merged + ordered by published_at DESC.
 *
 * Used by Draft Writing's Recently Published column.
 */
function list_recently_published(int $n = 5): array
{
    $n = max(1, min(50, $n));
    $stmt = db()->prepare(
        "SELECT id, slug, type, title, key_statement, status,
                published_status, published_at, updated_at
           FROM content
          WHERE status = 'published'
            AND (published_status IS NULL OR published_status = 'live')
            AND published_at IS NOT NULL
            AND published_at <= NOW()
       ORDER BY published_at DESC
          LIMIT $n"
    );
    $stmt->execute();
    return $stmt->fetchAll();
}
