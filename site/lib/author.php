<?php
declare(strict_types=1);

/**
 * lib/author.php — single-row author config + render helpers.
 *
 * The site is single-author today; the `author` table holds one row,
 * inserted with all NULLs by 0001_initial_schema.sql. Phase 11 ships
 * the CMS Author tab to edit it; until then the row is all-NULL and
 * the page renders placeholders ({no author name}, etc.).
 *
 * Empty-state behaviour per CMS-STRUCTURE.md §11:
 *   - Empty name             → "{no author name}"
 *   - Empty short_description → "{no short description}"
 *   - Empty extended_description → "{no extended description}"
 *   - Empty image            → initials circle from name; blank if no name.
 */

require_once __DIR__ . '/db.php';

/**
 * Read the single author row. Returns the array shape with NULLs intact —
 * the render helpers below convert NULL into placeholders. Falling back
 * to an all-NULL shape if the table is empty keeps the public render
 * resilient on a fresh install.
 */
function get_author(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;

    $row = db()->query('SELECT id, image, name, short_description, extended_description FROM author LIMIT 1')->fetch();
    if ($row === false) {
        $row = ['id' => null, 'image' => null, 'name' => null, 'short_description' => null, 'extended_description' => null];
    }
    $cached = $row;
    return $row;
}

/**
 * Update the single author row. Phase 14.5 — Content Template's Author info tab
 * writes through this function.
 *
 * Empty strings are coerced to NULL so the schema's nullable fields stay
 * accurate (and `author_display()` falls back to its placeholders). The
 * static cache in `get_author()` is not cleared here — the CMS uses a
 * POST → 302 redirect pattern, so the next request reads fresh.
 */
function save_author(
    ?string $name,
    ?string $short_description,
    ?string $extended_description,
    ?string $image = null
): void {
    $norm = static function (?string $v): ?string {
        if ($v === null) return null;
        $t = trim($v);
        return $t === '' ? null : $t;
    };

    // UPDATE without WHERE relies on the table holding a single row
    // (seeded in 0001_initial_schema.sql). LIMIT 1 is a guard.
    $stmt = db()->prepare(
        'UPDATE author SET image = :image, name = :name,
                short_description = :short, extended_description = :ext
         LIMIT 1'
    );
    $stmt->execute([
        ':image' => $norm($image),
        ':name'  => $norm($name),
        ':short' => $norm($short_description),
        ':ext'   => $norm($extended_description),
    ]);
}

/**
 * Initials derived from the author's name. Used as the avatar fallback
 * when no image is set. "Alex M. Chong" → "AC"; "Madonna" → "M";
 * empty/whitespace → ''. The renderer treats '' as "blank circle".
 */
function author_initials(?string $name): string
{
    if ($name === null) return '';
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));
    if (count($parts) === 0) return '';
    if (count($parts) === 1) return strtoupper(substr($parts[0], 0, 1));
    $first = strtoupper(substr($parts[0], 0, 1));
    $last  = strtoupper(substr($parts[count($parts) - 1], 0, 1));
    return $first . $last;
}

/**
 * Resolve display strings for the author block. The CMS UI may save
 * empty strings or NULLs interchangeably; both collapse to the same
 * placeholder set here.
 */
function author_display(array $author): array
{
    $blank = static fn($v) => $v === null || trim((string)$v) === '';
    return [
        'image'                => $blank($author['image'] ?? null) ? null : (string)$author['image'],
        'name'                 => $blank($author['name'] ?? null) ? '{no author name}' : (string)$author['name'],
        'short_description'    => $blank($author['short_description'] ?? null) ? '{no short description}' : (string)$author['short_description'],
        'extended_description' => $blank($author['extended_description'] ?? null) ? '{no extended description}' : (string)$author['extended_description'],
        'initials'             => author_initials($author['name'] ?? null),
    ];
}
