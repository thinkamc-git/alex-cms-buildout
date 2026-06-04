<?php
declare(strict_types=1);

/**
 * lib/indexes.php — Editorial Index data layer (Phase 12).
 *
 * One row per public index page (e.g. /writing/, /digital-garden/). The
 * `layout` column drives the public template choice — editorial vs listing.
 *
 * Series indexes live at /series/[slug]/ and are NOT rows in this table —
 * they're derived on the fly from the `series` row + its parts via
 * series_auto_index(). Adding a row in this table for a series-slug would
 * just collide with the auto-render.
 *
 * Returns plain arrays. Mirrors the conventions in lib/content.php
 * (see ENGINEERING.md §4.2 — lib functions return data, never render).
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/content.php';

/**
 * The four content types that can populate an index feed. Used by the
 * builder UI to render the type-chip group.
 */
const INDEX_FEED_TYPES = ['article', 'journal', 'live-session', 'experiment'];

/**
 * Layouts the builder offers. 'editorial' shows hero + featured + feed;
 * 'listing' is feed-only.
 */
const INDEX_LAYOUTS = ['editorial', 'listing'];

/**
 * Filter-pill mode for the row above the card grid.
 *   'none'       — no pill row.
 *   'categories' — pills are the distinct categories that appear in the
 *                  feed's content types, deduped.
 *   'types'      — pills are the feed_types themselves (Articles, …).
 */
const INDEX_FILTER_MODES = ['none', 'categories', 'types'];

/**
 * Pretty labels for INDEX_FEED_TYPES — used by the type-pill renderer
 * and by the admin builder dropdown.
 */
const INDEX_TYPE_LABELS = [
    'article'      => 'Articles',
    'journal'      => 'Journals',
    'live-session' => 'Talks',
    'experiment'   => 'Experiments',
];

/**
 * List every index, oldest-first by slug so the seeded ones group at the
 * top. The CMS view re-groups visually.
 */
function list_indexes(): array
{
    $sql = 'SELECT id, slug, layout, title, subtitle, show_title,
                   hero_content_id, featured_ids, feed_types, feed_sort,
                   feed_rows_shown, created_at, updated_at
              FROM indexes
          ORDER BY slug ASC';
    return db()->query($sql)->fetchAll() ?: [];
}

/**
 * Fetch one index by id, or NULL if missing.
 */
function get_index(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM indexes WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Fetch one index by slug. Used by the public router (/writing/, etc.).
 */
function get_index_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM indexes WHERE slug = :slug LIMIT 1');
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Ensure an index slug is unique. Append -2, -3, … if the candidate is
 * taken by another row.
 */
function unique_index_slug(string $candidate, int $excludeId = 0): string
{
    if ($candidate === '') return '';
    $base = $candidate;
    $n = 1;
    while (true) {
        $try = $n === 1 ? $base : ($base . '-' . $n);
        $stmt = db()->prepare(
            'SELECT id FROM indexes WHERE slug = :s AND id <> :id LIMIT 1'
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
 * Upsert an index row. $data accepts:
 *   id, slug, layout, title, subtitle, show_title (bool),
 *   hero_content_id (int|null), featured_ids (array of int),
 *   feed_types (array of string), feed_sort, feed_rows_shown.
 *
 * Returns ['ok' => bool, 'error' => string, 'id' => int].
 */
function save_index(array $data): array
{
    $id = (int)($data['id'] ?? 0);

    $title    = trim((string)($data['title']    ?? ''));
    $subtitle = trim((string)($data['subtitle'] ?? ''));
    $layout   = (string)($data['layout'] ?? 'listing');
    if (!in_array($layout, INDEX_LAYOUTS, true)) {
        return ['ok' => false, 'error' => 'Invalid layout.', 'id' => 0];
    }

    // Slug: only generate / re-check on create. Once an index exists, the
    // slug is the URL contract — editing it would break links. Author can
    // delete and re-create if they really need a new slug.
    if ($id === 0) {
        $slugIn = trim((string)($data['slug'] ?? ''));
        if ($slugIn === '') {
            $slugIn = $title !== '' ? slugify($title) : '';
        } else {
            $slugIn = slugify($slugIn);
        }
        if ($slugIn === '') {
            return ['ok' => false, 'error' => 'Slug is required.', 'id' => 0];
        }
        $slug = unique_index_slug($slugIn);
    } else {
        // Re-fetch existing slug; ignore incoming.
        $existing = get_index($id);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Index not found.', 'id' => 0];
        }
        $slug = (string)$existing['slug'];
    }

    $showTitle = !empty($data['show_title']) ? 1 : 0;

    // Hero + featured only make sense for editorial; null them otherwise.
    $heroId      = null;
    $featuredIds = null;
    if ($layout === 'editorial') {
        $h = (int)($data['hero_content_id'] ?? 0);
        $heroId = $h > 0 ? $h : null;

        $f = $data['featured_ids'] ?? [];
        if (is_array($f)) {
            $clean = [];
            foreach ($f as $v) {
                $vi = (int)$v;
                if ($vi > 0 && !in_array($vi, $clean, true)) $clean[] = $vi;
            }
            $featuredIds = $clean === [] ? null : json_encode(array_values($clean));
        }
    }

    // Feed config. Empty types array = all types.
    $types = $data['feed_types'] ?? [];
    $cleanTypes = [];
    if (is_array($types)) {
        foreach ($types as $t) {
            $ts = (string)$t;
            if (in_array($ts, INDEX_FEED_TYPES, true) && !in_array($ts, $cleanTypes, true)) {
                $cleanTypes[] = $ts;
            }
        }
    }
    $feedTypesJson = $cleanTypes === [] ? null : json_encode($cleanTypes);

    $sort = (string)($data['feed_sort'] ?? 'newest');
    if (!in_array($sort, ['newest', 'oldest', 'manual'], true)) $sort = 'newest';

    $rows = (string)($data['feed_rows_shown'] ?? 'all');
    if (!in_array($rows, ['1', '2', '3', '4', 'all'], true)) $rows = 'all';

    $filterMode = (string)($data['filter_mode'] ?? 'categories');
    if (!in_array($filterMode, INDEX_FILTER_MODES, true)) $filterMode = 'categories';

    $params = [
        ':slug'     => $slug,
        ':layout'   => $layout,
        ':title'    => $title,
        ':subtitle' => $subtitle,
        ':show'     => $showTitle,
        ':hero'     => $heroId,
        ':feat'     => $featuredIds,
        ':ftypes'   => $feedTypesJson,
        ':fsort'    => $sort,
        ':frows'    => $rows,
        ':fmode'    => $filterMode,
    ];

    if ($id === 0) {
        $sql = 'INSERT INTO indexes
                  (slug, layout, title, subtitle, show_title,
                   hero_content_id, featured_ids,
                   feed_types, feed_sort, feed_rows_shown, filter_mode)
                VALUES
                  (:slug, :layout, :title, :subtitle, :show,
                   :hero, :feat,
                   :ftypes, :fsort, :frows, :fmode)';
        db()->prepare($sql)->execute($params);
        return ['ok' => true, 'error' => '', 'id' => (int)db()->lastInsertId()];
    }

    $sql = 'UPDATE indexes
               SET layout = :layout,
                   title = :title,
                   subtitle = :subtitle,
                   show_title = :show,
                   hero_content_id = :hero,
                   featured_ids = :feat,
                   feed_types = :ftypes,
                   feed_sort = :fsort,
                   feed_rows_shown = :frows,
                   filter_mode = :fmode
             WHERE id = :id LIMIT 1';
    $params[':id'] = $id;
    // Slug isn't editable post-create — drop it from $params so PDO doesn't
    // throw HY093 ("invalid parameter number") for an unbound placeholder.
    unset($params[':slug']);
    db()->prepare($sql)->execute($params);
    return ['ok' => true, 'error' => '', 'id' => $id];
}

/**
 * Build the filter pill row for an index. Returns an array shaped like:
 *   [
 *     'mode'  => 'categories' | 'types' | 'none',
 *     'pills' => [
 *       ['key' => 'ux-industry', 'label' => 'UX Industry', 'data_attr' => 'category', 'colour' => 'terracotta'],
 *       …
 *     ],
 *   ]
 * Empty 'pills' (or mode='none') means render nothing.
 *
 * Categories pull from the categories table, scoped to the feed's types.
 * Types pull from the index's feed_types. Pills hide cards via the
 * matching data attribute (data-category / data-type) on the card.
 */
function build_index_pills(array $idx): array
{
    $mode = (string)($idx['filter_mode'] ?? 'categories');
    if (!in_array($mode, INDEX_FILTER_MODES, true)) $mode = 'categories';
    if ($mode === 'none') return ['mode' => 'none', 'pills' => []];

    $types = $idx['feed_types'] ?? null;
    if (is_string($types)) $types = json_decode($types, true);
    if (!is_array($types) || $types === []) $types = INDEX_FEED_TYPES;

    if ($mode === 'types') {
        $pills = [];
        foreach ($types as $t) {
            if (!in_array($t, INDEX_FEED_TYPES, true)) continue;
            $pills[] = [
                'key'       => $t,
                'label'     => INDEX_TYPE_LABELS[$t] ?? ucfirst($t),
                'data_attr' => 'type',
                'colour'    => '', // type pills inherit primary colour
            ];
        }
        return ['mode' => 'types', 'pills' => $pills];
    }

    // mode === 'categories'
    $placeholders = [];
    $params = [];
    foreach ($types as $i => $t) {
        $key = ":t{$i}";
        $placeholders[] = $key;
        $params[$key] = $t;
    }
    $in = implode(',', $placeholders);
    $sql = "SELECT value_slug, label, colour
              FROM categories
             WHERE type IN ($in)
          ORDER BY type ASC, sort_order ASC, label ASC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $pills = [];
    foreach ($rows as $r) {
        $pills[] = [
            'key'       => (string)$r['value_slug'],
            'label'     => (string)$r['label'],
            'data_attr' => 'category',
            'colour'    => (string)$r['colour'],
        ];
    }
    return ['mode' => 'categories', 'pills' => $pills];
}

/**
 * Convert `Latest *thinking*` → `Latest <em class="serif">thinking</em>`.
 * Matches the DS Full Page Index showcase title treatment — Barlow base
 * with one (or more) Instrument Serif italic emphasis spans. The author
 * wraps the emphasized word(s) in asterisks; everything else escapes.
 *
 * Returns ready-to-print HTML — escaping is handled inside this function.
 */
function render_title_emphasis(string $title): string
{
    $title = trim($title);
    if ($title === '') return '';
    // Allow <em>…</em> as an alias for *…* so authors can use either
    // syntax in the Title field. Everything else stays escaped.
    $title = preg_replace('/<em>([^<]+)<\/em>/i', '*$1*', $title) ?? $title;
    // Split into segments: odd indexes are inside *…* markers.
    $parts = preg_split('/\*([^*]+)\*/', $title, -1, PREG_SPLIT_DELIM_CAPTURE);
    $out = '';
    foreach ($parts as $i => $part) {
        $esc = htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
        if ($i % 2 === 1) {
            $out .= '<em class="serif-em">' . $esc . '</em>';
        } else {
            $out .= $esc;
        }
    }
    return $out;
}

/**
 * Capitalise a slug into a display string. `digital-garden` → `Digital Garden`.
 * Used for the index page eyebrow when no explicit eyebrow exists.
 */
function humanize_slug(string $slug): string
{
    return ucwords(str_replace('-', ' ', trim($slug)));
}

/**
 * Delete an index. Seeded built-ins (slug in /writing/, /journal/, etc.)
 * can be deleted — author can recreate from /cms/indexes/new if they want.
 * No FK fanout; the table stands alone.
 */
function delete_index(int $id): array
{
    if ($id <= 0) return ['ok' => false, 'error' => 'Missing id.'];
    db()->prepare('DELETE FROM indexes WHERE id = :id LIMIT 1')
        ->execute([':id' => $id]);
    return ['ok' => true, 'error' => ''];
}

/**
 * Build the content feed for an index. $config carries feed_types (JSON
 * string or array or null), feed_sort, feed_rows_shown.
 *
 * - Always restricted to status='published' and published_status='live'.
 * - Empty/NULL feed_types → all four content types.
 * - 'manual' sort falls back to newest for v1 (manual ordering UI isn't
 *   wired yet — explicit cut documented in the Phase 12 brief).
 * - Returns rows with the common card fields (slug, title, summary,
 *   thumbnail, published_at, type) plus type-specific extras as available.
 */
function list_index_feed(array $config, array $excludeIds = []): array
{
    $types = $config['feed_types'] ?? null;
    if (is_string($types)) {
        $decoded = json_decode($types, true);
        $types = is_array($decoded) ? $decoded : null;
    }
    if (!is_array($types) || $types === []) {
        $types = INDEX_FEED_TYPES;
    }
    // Sanitize against the enum.
    $types = array_values(array_filter(
        $types,
        static fn($t) => in_array($t, INDEX_FEED_TYPES, true)
    ));
    if ($types === []) return [];

    $placeholders = [];
    $params = [];
    foreach ($types as $i => $t) {
        $key = ":t{$i}";
        $placeholders[] = $key;
        $params[$key] = $t;
    }
    $inTypes = implode(',', $placeholders);

    $sort = (string)($config['feed_sort'] ?? 'newest');
    $order = $sort === 'oldest' ? 'c.published_at ASC' : 'c.published_at DESC';

    $sql = "SELECT c.id, c.slug, c.type, c.title, c.summary, c.thumbnail, c.hero_image,
                   c.published_at, c.read_time, c.special_tag,
                   c.series_id, c.series_order,
                   c.journal_number,
                   c.event_date, c.event_time, c.event_end_time,
                   c.location, c.venue, c.cost_pill, c.attendance,
                   s.name AS series_name, s.slug AS series_slug
              FROM content c
         LEFT JOIN series s ON s.id = c.series_id
             WHERE c.type IN ($inTypes)
               AND c.status = 'published'
               AND (c.published_status IS NULL OR c.published_status = 'live')
          ORDER BY $order, c.id DESC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    if ($excludeIds !== []) {
        $rows = array_values(array_filter(
            $rows,
            static fn($r) => !in_array((int)$r['id'], $excludeIds, true)
        ));
    }

    $rows_shown = (string)($config['feed_rows_shown'] ?? 'all');
    if ($rows_shown !== 'all' && ctype_digit($rows_shown)) {
        $limit = (int)$rows_shown * 4; // ~4 cards per row in the card grid; v1 approximation.
        $rows  = array_slice($rows, 0, max(1, $limit));
    }

    return $rows;
}

/**
 * Fetch a single published content row by id, returning the card fields.
 * Used to materialize the hero feature and the featured-articles list
 * inside the editorial template.
 */
function get_index_content_card(int $id): ?array
{
    if ($id <= 0) return null;
    $sql = "SELECT c.id, c.slug, c.type, c.title, c.summary, c.thumbnail, c.hero_image,
                   c.published_at, c.read_time, c.special_tag,
                   c.series_id, c.series_order,
                   c.journal_number,
                   c.event_date, c.event_time, c.event_end_time,
                   c.location, c.venue, c.cost_pill, c.attendance,
                   s.name AS series_name, s.slug AS series_slug,
                   cat.label  AS category_label,
                   cat.value_slug AS category_slug,
                   cat.colour AS category_colour
              FROM content c
         LEFT JOIN series s ON s.id = c.series_id
         LEFT JOIN content_categories cc ON cc.content_id = c.id AND cc.is_primary = 1
         LEFT JOIN categories cat ON cat.type = cc.type AND cat.value_slug = cc.category
             WHERE c.id = :id
               AND c.status = 'published'
               AND (c.published_status IS NULL OR c.published_status = 'live')
             LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Count published live parts in a series — used by the editorial hero's
 * series side card to render "Part X of N".
 */
function count_series_published(int $series_id): int
{
    if ($series_id <= 0) return 0;
    $stmt = db()->prepare(
        "SELECT COUNT(*) AS n FROM content
          WHERE series_id = :sid
            AND status = 'published'
            AND (published_status IS NULL OR published_status = 'live')"
    );
    $stmt->execute([':sid' => $series_id]);
    return (int)($stmt->fetch()['n'] ?? 0);
}

// ─────────────────────────────────────────────────────────────────────────
// Phase 21.7 — Editorial Page section stack (CMS-STRUCTURE.md §16)
// ─────────────────────────────────────────────────────────────────────────
//
// Editorial pages render an ordered, typed section stack (hero / curated /
// feed). Basic Listing still uses the flat feed_* columns on `indexes` —
// none of the functions below apply to it.
//
// Sections are stored one-to-many in `index_sections`; per-type fields are
// NULL-able and ignored when not applicable. See migration 0021 for the
// full schema.

const INDEX_SECTION_TYPES   = ['hero', 'curated', 'feed'];
const INDEX_SECTION_FORMATS = ['grid', 'carousel'];
const INDEX_SECTION_ROWS    = ['1', '2', '3', '4', 'all'];
const INDEX_SECTION_SORTS   = ['newest', 'oldest'];
const INDEX_SECTION_FILTERS = ['types', 'categories'];
const INDEX_SECTION_HEADERS = ['small', 'big'];
const INDEX_SECTION_HERO_IMG = ['auto', 'custom', 'none'];
const INDEX_SECTION_HERO_LAYOUTS = ['plain', 'within', 'bleed-dark', 'bleed-light'];
const INDEX_SECTION_HERO_BGS     = ['transparent', 'surface'];

/**
 * Decode a JSON column that may arrive as either a string (raw from
 * PDO) or null. Returns an array (possibly empty).
 */
function _index_section_json_decode($value): array
{
    if (is_array($value)) return $value;
    if (is_string($value) && $value !== '') {
        $d = json_decode($value, true);
        return is_array($d) ? $d : [];
    }
    return [];
}

/**
 * Normalize a section row from the DB: decode JSON columns into arrays
 * so the caller doesn't have to re-parse them. The original raw values
 * stay accessible via the `*_raw` keys for debugging.
 */
function _index_section_normalize(array $row): array
{
    $row['item_ids']        = _index_section_json_decode($row['item_ids']        ?? null);
    $row['feed_types']      = _index_section_json_decode($row['feed_types']      ?? null);
    $row['feed_categories'] = _index_section_json_decode($row['feed_categories'] ?? null);
    $row['filter_options']  = _index_section_json_decode($row['filter_options']  ?? null);
    $row['filter_show']     = (bool)($row['filter_show'] ?? false);
    $row['position']        = (int)($row['position']     ?? 0);
    $row['header_style']    = (string)($row['header_style'] ?? 'small');
    $row['hero_image_mode'] = (string)($row['hero_image_mode'] ?? 'auto');
    $row['hero_image_url']  = (string)($row['hero_image_url']  ?? '');
    $row['hero_layout']     = (string)($row['hero_layout']     ?? 'within');
    $row['hero_background'] = (string)($row['hero_background'] ?? 'transparent');
    $row['item_limit']      = isset($row['item_limit']) && $row['item_limit'] !== null
                              ? (int)$row['item_limit'] : null;
    return $row;
}

/**
 * Return every section attached to an index, in render order.
 */
function list_index_sections(int $index_id): array
{
    if ($index_id <= 0) return [];
    $stmt = db()->prepare(
        'SELECT * FROM index_sections WHERE index_id = :id ORDER BY position ASC, id ASC'
    );
    $stmt->execute([':id' => $index_id]);
    $rows = $stmt->fetchAll() ?: [];
    return array_map('_index_section_normalize', $rows);
}

function get_index_section(int $id): ?array
{
    if ($id <= 0) return null;
    $stmt = db()->prepare('SELECT * FROM index_sections WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : _index_section_normalize($row);
}

/**
 * Upsert a section. $data accepts (all optional except index_id +
 * section_type on create):
 *   id, index_id, position, section_type, title,
 *   display_format, item_limit, grid_rows, see_more_label, see_more_target,
 *   feed_types[], feed_categories[], feed_sort,
 *   filter_show, filter_by, filter_options[],
 *   item_ids[]
 *
 * Returns ['ok' => bool, 'error' => string, 'id' => int].
 */
function save_index_section(array $data): array
{
    $id       = (int)($data['id'] ?? 0);
    $index_id = (int)($data['index_id'] ?? 0);

    if ($id === 0 && $index_id <= 0) {
        return ['ok' => false, 'error' => 'index_id required.', 'id' => 0];
    }

    // Existing row dictates index_id + position on update unless caller overrides.
    $existing = $id > 0 ? get_index_section($id) : null;
    if ($id > 0 && $existing === null) {
        return ['ok' => false, 'error' => 'Section not found.', 'id' => 0];
    }
    if ($id > 0) {
        $index_id = (int)$existing['index_id'];
    }

    $type = (string)($data['section_type'] ?? ($existing['section_type'] ?? ''));
    if (!in_array($type, INDEX_SECTION_TYPES, true)) {
        return ['ok' => false, 'error' => 'Invalid section_type.', 'id' => 0];
    }

    // Position: explicit override, else preserve existing, else append.
    if (array_key_exists('position', $data)) {
        $position = (int)$data['position'];
    } elseif ($existing !== null) {
        $position = (int)$existing['position'];
    } else {
        $stmt = db()->prepare('SELECT COALESCE(MAX(position),-1)+1 AS n FROM index_sections WHERE index_id = :id');
        $stmt->execute([':id' => $index_id]);
        $position = (int)($stmt->fetch()['n'] ?? 0);
    }

    $title = trim((string)($data['title'] ?? ($existing['title'] ?? '')));

    $headerStyle = (string)($data['header_style'] ?? ($existing['header_style'] ?? 'small'));
    if (!in_array($headerStyle, INDEX_SECTION_HEADERS, true)) $headerStyle = 'small';

    $heroImgMode = (string)($data['hero_image_mode'] ?? ($existing['hero_image_mode'] ?? 'auto'));
    if (!in_array($heroImgMode, INDEX_SECTION_HERO_IMG, true)) $heroImgMode = 'auto';
    $heroImgUrl  = trim((string)($data['hero_image_url'] ?? ($existing['hero_image_url'] ?? ''))) ?: null;

    $heroLayout = (string)($data['hero_layout'] ?? ($existing['hero_layout'] ?? 'within'));
    if (!in_array($heroLayout, INDEX_SECTION_HERO_LAYOUTS, true)) $heroLayout = 'within';
    $heroBg = (string)($data['hero_background'] ?? ($existing['hero_background'] ?? 'transparent'));
    if (!in_array($heroBg, INDEX_SECTION_HERO_BGS, true)) $heroBg = 'transparent';

    // Display layer — only meaningful for curated/feed. Hero ignores it
    // but we still store sane defaults so the row is consistent.
    $format = (string)($data['display_format'] ?? ($existing['display_format'] ?? 'grid'));
    if (!in_array($format, INDEX_SECTION_FORMATS, true)) $format = 'grid';
    $item_limit = $data['item_limit'] ?? ($existing['item_limit'] ?? null);
    $item_limit = ($item_limit === '' || $item_limit === null) ? null : max(1, (int)$item_limit);
    $grid_rows = (string)($data['grid_rows'] ?? ($existing['grid_rows'] ?? 'all'));
    if (!in_array($grid_rows, INDEX_SECTION_ROWS, true)) $grid_rows = 'all';
    $see_label  = trim((string)($data['see_more_label']  ?? ($existing['see_more_label']  ?? ''))) ?: null;
    $see_target = trim((string)($data['see_more_target'] ?? ($existing['see_more_target'] ?? ''))) ?: null;

    // Content query — only feed uses it; hero/curated store NULL.
    $feed_types_json = null;
    $feed_cats_json  = null;
    $feed_sort       = (string)($data['feed_sort'] ?? ($existing['feed_sort'] ?? 'newest'));
    if (!in_array($feed_sort, INDEX_SECTION_SORTS, true)) $feed_sort = 'newest';

    if ($type === 'feed') {
        $types = $data['feed_types'] ?? ($existing['feed_types'] ?? []);
        if (is_string($types)) $types = _index_section_json_decode($types);
        $types = is_array($types)
            ? array_values(array_unique(array_filter($types, static fn($t) => in_array($t, INDEX_FEED_TYPES, true))))
            : [];
        $feed_types_json = $types === [] ? null : json_encode($types);

        $cats = $data['feed_categories'] ?? ($existing['feed_categories'] ?? []);
        if (is_string($cats)) $cats = _index_section_json_decode($cats);
        $cats = is_array($cats)
            ? array_values(array_unique(array_filter(array_map('strval', $cats), static fn($c) => $c !== '')))
            : [];
        $feed_cats_json = $cats === [] ? null : json_encode($cats);
    }

    // Visitor filter — only feed uses it.
    $filter_show    = $type === 'feed' && !empty($data['filter_show']);
    $filter_by      = null;
    $filter_opts_js = null;
    if ($type === 'feed' && $filter_show) {
        $fb = (string)($data['filter_by'] ?? ($existing['filter_by'] ?? ''));
        $filter_by = in_array($fb, INDEX_SECTION_FILTERS, true) ? $fb : null;

        $opts = $data['filter_options'] ?? ($existing['filter_options'] ?? []);
        if (is_string($opts)) $opts = _index_section_json_decode($opts);
        $opts = is_array($opts)
            ? array_values(array_unique(array_filter(array_map('strval', $opts), static fn($o) => $o !== '')))
            : [];
        $filter_opts_js = $opts === [] ? null : json_encode($opts);
    }

    // Picks: hero = JSON_ARRAY(single id); curated = JSON array.
    $item_ids_json = null;
    if ($type === 'hero' || $type === 'curated') {
        $ids = $data['item_ids'] ?? ($existing['item_ids'] ?? []);
        if (is_string($ids)) $ids = _index_section_json_decode($ids);
        $clean = [];
        if (is_array($ids)) {
            foreach ($ids as $v) {
                $vi = (int)$v;
                if ($vi > 0 && !in_array($vi, $clean, true)) $clean[] = $vi;
            }
        }
        if ($type === 'hero' && count($clean) > 1) $clean = [$clean[0]];
        $item_ids_json = $clean === [] ? null : json_encode($clean);
    }

    $params = [
        ':iid'         => $index_id,
        ':pos'         => $position,
        ':type'        => $type,
        ':title'       => $title !== '' ? $title : null,
        ':hstyle'      => $headerStyle,
        ':himode'      => $heroImgMode,
        ':himg'        => $heroImgUrl,
        ':hlayout'     => $heroLayout,
        ':hbg'         => $heroBg,
        ':fmt'         => $format,
        ':limit'       => $item_limit,
        ':rows'        => $grid_rows,
        ':see_label'   => $see_label,
        ':see_target'  => $see_target,
        ':ftypes'      => $feed_types_json,
        ':fcats'       => $feed_cats_json,
        ':fsort'       => $feed_sort,
        ':fshow'       => $filter_show ? 1 : 0,
        ':fby'         => $filter_by,
        ':fopts'       => $filter_opts_js,
        ':items'       => $item_ids_json,
    ];

    if ($id === 0) {
        $sql = 'INSERT INTO index_sections
                  (index_id, position, section_type, title, header_style,
                   hero_image_mode, hero_image_url, hero_layout, hero_background,
                   display_format, item_limit, grid_rows, see_more_label, see_more_target,
                   feed_types, feed_categories, feed_sort,
                   filter_show, filter_by, filter_options,
                   item_ids)
                VALUES
                  (:iid, :pos, :type, :title, :hstyle,
                   :himode, :himg, :hlayout, :hbg,
                   :fmt, :limit, :rows, :see_label, :see_target,
                   :ftypes, :fcats, :fsort,
                   :fshow, :fby, :fopts,
                   :items)';
        db()->prepare($sql)->execute($params);
        return ['ok' => true, 'error' => '', 'id' => (int)db()->lastInsertId()];
    }

    $sql = 'UPDATE index_sections SET
              position = :pos,
              section_type = :type,
              title = :title,
              header_style = :hstyle,
              hero_image_mode = :himode,
              hero_image_url = :himg,
              hero_layout = :hlayout,
              hero_background = :hbg,
              display_format = :fmt,
              item_limit = :limit,
              grid_rows = :rows,
              see_more_label = :see_label,
              see_more_target = :see_target,
              feed_types = :ftypes,
              feed_categories = :fcats,
              feed_sort = :fsort,
              filter_show = :fshow,
              filter_by = :fby,
              filter_options = :fopts,
              item_ids = :items
            WHERE id = :id LIMIT 1';
    $params[':id'] = $id;
    unset($params[':iid']); // index_id is immutable post-create
    db()->prepare($sql)->execute($params);
    return ['ok' => true, 'error' => '', 'id' => $id];
}

function delete_index_section(int $id): array
{
    if ($id <= 0) return ['ok' => false, 'error' => 'Missing id.'];
    db()->prepare('DELETE FROM index_sections WHERE id = :id LIMIT 1')->execute([':id' => $id]);
    return ['ok' => true, 'error' => ''];
}

/**
 * Persist a new ordering for an index's sections. $ordered_ids is the
 * new sequence of section ids, top to bottom. Sections not in the list
 * are left untouched (callers that re-order from a complete drag list
 * should always pass every id).
 */
function reorder_index_sections(int $index_id, array $ordered_ids): void
{
    if ($index_id <= 0 || $ordered_ids === []) return;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE index_sections SET position = :pos WHERE id = :id AND index_id = :iid'
        );
        foreach (array_values($ordered_ids) as $i => $sid) {
            $stmt->execute([':pos' => $i, ':id' => (int)$sid, ':iid' => $index_id]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
    }
}

// ─── Per-section data resolution ─────────────────────────────────────────

/**
 * Resolve a hero/curated section's hand-picked items into card rows, in
 * the order specified by item_ids. Items that no longer publish-resolve
 * are silently dropped — the render side just shows fewer cards.
 */
function list_section_items(array $section): array
{
    $ids = is_array($section['item_ids'] ?? null) ? $section['item_ids'] : [];
    if ($ids === []) return [];

    $out = [];
    foreach ($ids as $id) {
        $card = get_index_content_card((int)$id);
        if ($card !== null) $out[] = $card;
    }
    return $out;
}

/**
 * Run a feed section's content query and return the resulting card rows.
 * Combines feed_types (OR), feed_categories (OR), feed_sort, and applies
 * the display-layer limit (grid_rows for grids, item_limit for carousels).
 *
 * $excludeIds lets the caller suppress items shown by an earlier section
 * on the same page (a hero pick shouldn't reappear in a feed below it).
 */
function list_section_feed(array $section, array $excludeIds = []): array
{
    $types = is_array($section['feed_types'] ?? null) ? $section['feed_types'] : [];
    $types = array_values(array_filter($types, static fn($t) => in_array($t, INDEX_FEED_TYPES, true)));
    if ($types === []) $types = INDEX_FEED_TYPES;

    $cats = is_array($section['feed_categories'] ?? null) ? $section['feed_categories'] : [];

    $params = [];
    $typePlaceholders = [];
    foreach ($types as $i => $t) {
        $k = ":t{$i}";
        $typePlaceholders[] = $k;
        $params[$k] = $t;
    }
    $where = 'c.type IN (' . implode(',', $typePlaceholders) . ')';

    $catJoin = '';
    if ($cats !== []) {
        $catPlaceholders = [];
        foreach ($cats as $i => $c) {
            $k = ":c{$i}";
            $catPlaceholders[] = $k;
            $params[$k] = $c;
        }
        // Match the same pattern list_articles uses: join the primary
        // category row and filter on its slug.
        $catJoin = 'LEFT JOIN content_categories cc ON cc.content_id = c.id AND cc.is_primary = 1';
        $where  .= ' AND cc.category IN (' . implode(',', $catPlaceholders) . ')';
    }

    $sort = (string)($section['feed_sort'] ?? 'newest');
    $order = $sort === 'oldest' ? 'c.published_at ASC' : 'c.published_at DESC';

    $sql = "SELECT c.id, c.slug, c.type, c.title, c.summary, c.thumbnail,
                   c.published_at, c.read_time, c.special_tag,
                   c.series_id, c.series_order,
                   c.journal_number,
                   c.event_date, c.event_time, c.event_end_time,
                   c.location, c.venue, c.cost_pill, c.attendance,
                   s.name AS series_name, s.slug AS series_slug
              FROM content c
         LEFT JOIN series s ON s.id = c.series_id
         $catJoin
             WHERE $where
               AND c.status = 'published'
               AND (c.published_status IS NULL OR c.published_status = 'live')
          ORDER BY $order, c.id DESC";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    if ($excludeIds !== []) {
        $rows = array_values(array_filter(
            $rows,
            static fn($r) => !in_array((int)$r['id'], $excludeIds, true)
        ));
    }

    // Display limit. Grid: rows × 4 cards per row (matches list_index_feed's
    // v1 approximation). Carousel: item_limit directly.
    $format = (string)($section['display_format'] ?? 'grid');
    if ($format === 'carousel') {
        $limit = $section['item_limit'] ?? null;
        if ($limit !== null && (int)$limit > 0) {
            $rows = array_slice($rows, 0, (int)$limit);
        }
    } else {
        $grid_rows = (string)($section['grid_rows'] ?? 'all');
        if ($grid_rows !== 'all' && ctype_digit($grid_rows)) {
            $rows = array_slice($rows, 0, max(1, (int)$grid_rows * 4));
        }
    }

    return $rows;
}

/**
 * Build the visitor-filter pills for a feed section. Returns:
 *   [
 *     'show'         => bool,
 *     'by'           => 'types' | 'categories' | null,
 *     'pills'        => [['key','label','data_attr','colour'], …],
 *     'preselected'  => ['key1', 'key2', …],
 *   ]
 *
 * Pill set: filter_options if explicit, else auto-derived from the
 * section's content query (feed_types for 'types'; the categories that
 * actually appear in the feed for 'categories').
 *
 * Pre-selection: reflects the content query — feed_types for 'types',
 * feed_categories for 'categories'. Empty arrays = nothing pre-selected
 * (visitor sees the "All" state by default).
 */
function build_section_pills(array $section): array
{
    $off = ['show' => false, 'by' => null, 'pills' => [], 'preselected' => []];
    if (empty($section['filter_show'])) return $off;

    $by = (string)($section['filter_by'] ?? '');
    if (!in_array($by, INDEX_SECTION_FILTERS, true)) return $off;

    $opts = is_array($section['filter_options'] ?? null) ? $section['filter_options'] : [];

    $feed_types = is_array($section['feed_types'] ?? null) ? $section['feed_types'] : [];
    $feed_cats  = is_array($section['feed_categories'] ?? null) ? $section['feed_categories'] : [];

    if ($by === 'types') {
        // Pill set: explicit subset if given, else feed_types (or all four if open).
        $set = $opts !== [] ? $opts : ($feed_types !== [] ? $feed_types : INDEX_FEED_TYPES);
        $pills = [];
        foreach ($set as $t) {
            if (!in_array($t, INDEX_FEED_TYPES, true)) continue;
            $pills[] = [
                'key'       => $t,
                'label'     => INDEX_TYPE_LABELS[$t] ?? ucfirst($t),
                'data_attr' => 'type',
                'colour'    => '',
            ];
        }
        return [
            'show'        => true,
            'by'          => 'types',
            'pills'       => $pills,
            'preselected' => $feed_types,
        ];
    }

    // by === 'categories'
    // Auto-derive: categories that exist for the section's content types
    // (or all categories if types is open).
    $types = $feed_types !== [] ? $feed_types : INDEX_FEED_TYPES;
    $placeholders = [];
    $params = [];
    foreach ($types as $i => $t) {
        $k = ":t{$i}";
        $placeholders[] = $k;
        $params[$k] = $t;
    }
    $sql = 'SELECT value_slug, label, colour
              FROM categories
             WHERE type IN (' . implode(',', $placeholders) . ')
          ORDER BY type ASC, sort_order ASC, label ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $allRows = $stmt->fetchAll() ?: [];

    // If author hand-picked a subset, intersect with the auto-derived
    // list (filter_options is the allow-list).
    if ($opts !== []) {
        $allow = array_flip($opts);
        $allRows = array_values(array_filter(
            $allRows,
            static fn($r) => isset($allow[(string)$r['value_slug']])
        ));
    }

    $pills = [];
    foreach ($allRows as $r) {
        $pills[] = [
            'key'       => (string)$r['value_slug'],
            'label'     => (string)$r['label'],
            'data_attr' => 'category',
            'colour'    => (string)$r['colour'],
        ];
    }

    return [
        'show'        => true,
        'by'          => 'categories',
        'pills'       => $pills,
        'preselected' => $feed_cats,
    ];
}

// ─────────────────────────────────────────────────────────────────────────

/**
 * Resolve the auto-generated index for /series/[slug]/. Returns a
 * synthetic "index" array shaped like a normal index row plus a `parts`
 * key listing every published part in series_order, and `hero_card` /
 * `featured_cards` derived from the latest part (Decisions block:
 * "Editorial — hero = latest part, feed = remaining parts").
 *
 * Phase 21.7 (stage 2): the return shape now ALSO includes a `sections`
 * key carrying the synthesized section stack (§16.5). The legacy
 * hero_card/featured_cards/feed_rows keys are still emitted so the
 * pre-rework editorial template keeps working until Stage 4 swaps it
 * for a section-iterating render. After that, the legacy keys can be
 * dropped from this function.
 *
 * NULL if no such series exists.
 */
function series_auto_index(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM series WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $slug]);
    $series = $stmt->fetch();
    if ($series === false) return null;

    $sid = (int)$series['id'];

    $partsSql = "SELECT c.id, c.slug, c.type, c.title, c.summary, c.thumbnail,
                        c.published_at, c.read_time, c.special_tag,
                        c.series_id, c.series_order,
                        s.name AS series_name, s.slug AS series_slug
                   FROM content c
              LEFT JOIN series s ON s.id = c.series_id
                  WHERE c.series_id = :sid
                    AND c.status = 'published'
                    AND (c.published_status IS NULL OR c.published_status = 'live')
               ORDER BY c.series_order ASC";
    $stmt = db()->prepare($partsSql);
    $stmt->execute([':sid' => $sid]);
    $parts = $stmt->fetchAll() ?: [];

    // Series index: no hero, no featured. Every part renders as a uniform
    // card. Order is series_order DESC so the latest part appears first
    // (03, 02, 01). Each card carries `_series_number` so the partial can
    // render the faint italic watermark over the card.
    //
    // The watermark uses *published-only position* — never the raw
    // series_order. Drafts in the middle of a series should not punch
    // holes in the numbering. With A(order=1, published), B(order=2, draft),
    // C(order=3, published): the public series shows "01" + "02" (not
    // "01" + "03"). When B publishes, it slots in and the cards renumber
    // to "01" + "02" + "03" automatically.
    $totalPublished = count($parts);
    $feed = array_reverse($parts);
    foreach ($feed as $i => &$row) {
        // Latest (feed[0]) gets the highest number; oldest gets 1.
        $row['_series_number'] = $totalPublished - $i;
    }
    unset($row);

    // Synthesized section stack: a single curated section containing
    // every published part in display order (latest first). Series
    // pages no longer surface a hero — every part renders as a
    // uniform card with the faint italic _series_number watermark.
    $sections = [];
    if ($feed !== []) {
        $sections[] = _index_section_normalize([
            'id'             => 0,
            'index_id'       => 0,
            'position'       => 0,
            'section_type'   => 'curated',
            'title'          => null,
            'display_format' => 'grid',
            'item_limit'     => null,
            'grid_rows'      => 'all',
            'item_ids'       => json_encode(array_map(static fn($r) => (int)$r['id'], $feed)),
            'feed_types'     => null,
            'feed_categories'=> null,
            'feed_sort'      => 'newest',
            'filter_show'    => 0,
            'filter_by'      => null,
            'filter_options' => null,
            // Pre-resolved cards travel alongside so render doesn't
            // re-hit the DB. The section partial prefers `_items`.
            '_items'         => $feed,
        ]);
    }

    return [
        'id'              => 0, // Synthetic — not in indexes table.
        'slug'            => 'series/' . $series['slug'],
        'layout'          => 'editorial',
        'title'           => (string)$series['name'],
        'subtitle'        => (string)($series['description'] ?? ''),
        'show_title'      => 1,
        'hero_content_id' => null,
        'feed_sort'       => 'manual',
        'feed_rows_shown' => 'all',
        // Legacy keys — pre-rework editorial template reads these.
        'hero_card'       => null,
        'featured_cards'  => [],
        'feed_rows'       => $feed,
        // New section-stack form (§16.5) — stage 4 render will iterate this.
        'sections'        => $sections,
        'is_series'       => true,
        'series_row'      => $series,
    ];
}
