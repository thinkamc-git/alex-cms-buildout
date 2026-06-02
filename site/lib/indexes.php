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
    if ($layout === 'listing') {
        $showTitle = 1; // Basic Listing always shows title per §16.
    }

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

    $sql = "SELECT c.id, c.slug, c.type, c.title, c.summary, c.thumbnail,
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
    $sql = "SELECT c.id, c.slug, c.type, c.title, c.summary, c.thumbnail,
                   c.published_at, c.read_time, c.special_tag,
                   c.series_id, c.series_order,
                   c.journal_number,
                   c.event_date, c.event_time, c.event_end_time,
                   c.location, c.venue, c.cost_pill, c.attendance,
                   s.name AS series_name, s.slug AS series_slug
              FROM content c
         LEFT JOIN series s ON s.id = c.series_id
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
 * Resolve the auto-generated index for /series/[slug]/. Returns a
 * synthetic "index" array shaped like a normal index row plus a `parts`
 * key listing every published part in series_order, and `hero_card` /
 * `featured_cards` derived from the latest part (Decisions block:
 * "Editorial — hero = latest part, feed = remaining parts").
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
        'hero_card'       => null,
        'featured_cards'  => [],
        'feed_rows'       => $feed,
        'is_series'       => true,
        'series_row'      => $series,
    ];
}
