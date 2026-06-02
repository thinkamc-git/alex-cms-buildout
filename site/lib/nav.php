<?php
/**
 * lib/nav.php — Navigation items data layer + render (Phase 20).
 *
 * Drives the header and footer link lists. Each nav row has a label and a
 * polymorphic target (index / category / series / content / page / custom).
 *
 * The runtime render is render_nav('header'|'footer'), called from
 * _pages/_layout/header.php / footer.php (the file-layer renderer) and
 * also from render_partial_body() in lib/pages.php (when a published
 * mock substitutes the token).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

const NAV_ZONES        = ['header', 'footer'];
const NAV_TARGET_TYPES = ['index', 'category', 'series', 'content', 'page', 'custom'];
const NAV_HIGHLIGHTS   = ['none', 'dot', 'pill'];
// Default colour for dot / pill when no per-item override is set. The
// CSS-variable fallback handles marketing pages, which load style-pages.css
// (no design-system tokens) — there --c-terracotta is undefined, so the
// literal #d63031 (the original hardcoded header red) wins.
const NAV_DEFAULT_HIGHLIGHT_COLOR = 'var(--c-terracotta, #d63031)';
// Plain hex fallback when we need a real colour value (not a CSS var) —
// e.g. for color-mix() tints, and the contrast helper which needs to parse.
const NAV_DEFAULT_HIGHLIGHT_HEX   = '#d63031';

/**
 * Auto-contrast text colour for a given background. Returns '#000' for
 * light bg, '#fff' for dark, '#fff' if we can't parse (default red reads
 * fine on white). Mirrored in cms/views/navigation.php's navContrast().
 */
function nav_contrast_text(string $bg): string
{
    $bg = trim($bg);
    if ($bg === '' || $bg[0] !== '#') return '#fff';
    $hex = substr($bg, 1);
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '#fff';
    $r = (int)hexdec(substr($hex, 0, 2));
    $g = (int)hexdec(substr($hex, 2, 2));
    $b = (int)hexdec(substr($hex, 4, 2));
    return ((299 * $r + 587 * $g + 114 * $b) / 1000) > 128 ? '#000' : '#fff';
}

// ── CRUD ──────────────────────────────────────────────────────────────

function list_nav_items(?string $zone = null, bool $only_active = false): array
{
    $sql = 'SELECT id, zone, label, nav_key, target_type, target_id, target_slug,
                   custom_url, highlight, highlight_text, highlight_color,
                   position, is_active, created_at, updated_at
              FROM nav_items';
    $where = [];
    $args  = [];
    if ($zone !== null) {
        $where[] = 'zone = ?';
        $args[]  = $zone;
    }
    if ($only_active) {
        $where[] = 'is_active = 1';
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY zone, position, id';
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return $stmt->fetchAll();
}

function get_nav_item(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM nav_items WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Create or update a nav item. Returns the row id.
 *
 * Expected keys (everything optional except zone, label, target_type):
 *   id, zone, label, nav_key, target_type, target_id, target_slug,
 *   custom_url, highlight, highlight_text, highlight_color, position,
 *   is_active
 */
function save_nav_item(array $data): int
{
    $id          = (int)($data['id'] ?? 0);
    $zone        = in_array($data['zone'] ?? '', NAV_ZONES, true) ? $data['zone'] : 'header';
    $label       = trim((string)($data['label'] ?? ''));
    $nav_key     = trim((string)($data['nav_key'] ?? '')) ?: null;
    $target_type = in_array($data['target_type'] ?? '', NAV_TARGET_TYPES, true) ? $data['target_type'] : 'custom';
    $target_id   = isset($data['target_id']) && $data['target_id'] !== '' ? (int)$data['target_id'] : null;
    $target_slug = trim((string)($data['target_slug'] ?? '')) ?: null;
    $custom_url  = trim((string)($data['custom_url'] ?? '')) ?: null;
    $highlight   = in_array($data['highlight'] ?? '', NAV_HIGHLIGHTS, true) ? $data['highlight'] : 'none';
    $highlight_text  = trim((string)($data['highlight_text'] ?? '')) ?: null;
    $highlight_color = trim((string)($data['highlight_color'] ?? '')) ?: null;
    $is_active   = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;
    $position    = isset($data['position']) ? (int)$data['position'] : 0;

    if ($id > 0) {
        $stmt = db()->prepare(
            'UPDATE nav_items
                SET zone=?, label=?, nav_key=?, target_type=?, target_id=?,
                    target_slug=?, custom_url=?, highlight=?, highlight_text=?,
                    highlight_color=?, position=?, is_active=?
              WHERE id=?'
        );
        $stmt->execute([
            $zone, $label, $nav_key, $target_type, $target_id, $target_slug,
            $custom_url, $highlight, $highlight_text, $highlight_color,
            $position, $is_active, $id,
        ]);
        return $id;
    }

    // New row — append at the end of its zone if no explicit position.
    if ($position === 0) {
        $stmt = db()->prepare('SELECT COALESCE(MAX(position),-1)+1 AS n FROM nav_items WHERE zone=?');
        $stmt->execute([$zone]);
        $position = (int)($stmt->fetch()['n'] ?? 0);
    }
    $stmt = db()->prepare(
        'INSERT INTO nav_items
           (zone, label, nav_key, target_type, target_id, target_slug,
            custom_url, highlight, highlight_text, highlight_color,
            position, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $zone, $label, $nav_key, $target_type, $target_id, $target_slug,
        $custom_url, $highlight, $highlight_text, $highlight_color,
        $position, $is_active,
    ]);
    return (int)db()->lastInsertId();
}

function delete_nav_item(int $id): void
{
    db()->prepare('DELETE FROM nav_items WHERE id = ?')->execute([$id]);
}

/**
 * Persist a new ordering for a zone. $ordered_ids is the new sequence of
 * item ids, top to bottom.
 */
function reorder_nav_items(string $zone, array $ordered_ids): void
{
    if (!in_array($zone, NAV_ZONES, true)) return;
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE nav_items SET position = ? WHERE id = ? AND zone = ?');
        foreach (array_values($ordered_ids) as $i => $id) {
            $stmt->execute([$i, (int)$id, $zone]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
    }
}

// ── Target resolver ───────────────────────────────────────────────────

/**
 * Resolve a nav item to a public URL, or NULL if the target row no longer
 * exists. Used by render_nav() and by the nightly broken-target sweep.
 */
function resolve_nav_target(array $item): ?string
{
    $type = $item['target_type'] ?? 'custom';
    $tid  = isset($item['target_id'])   && $item['target_id']   !== '' ? (int)$item['target_id'] : null;
    $tsl  = isset($item['target_slug']) && $item['target_slug'] !== '' ? (string)$item['target_slug'] : null;

    switch ($type) {
        case 'custom':
            $url = (string)($item['custom_url'] ?? '');
            return $url !== '' ? $url : null;

        case 'page':
            // page targets reference a marketing-page slug (about, coaching, …)
            if ($tsl === null) return null;
            if (function_exists('find_page_file')) {
                $found = find_page_file($tsl);
                if ($found === null || !$found['exists']) return null;
            }
            return '/' . ltrim($tsl, '/') . '/';

        case 'index':
            if ($tid === null) return null;
            $stmt = db()->prepare('SELECT slug FROM indexes WHERE id = ?');
            $stmt->execute([$tid]);
            $row = $stmt->fetch();
            if (!$row) return null;
            return '/' . (string)$row['slug'] . '/';

        case 'category':
            if ($tid === null) return null;
            $stmt = db()->prepare('SELECT value_slug FROM categories WHERE id = ?');
            $stmt->execute([$tid]);
            $row = $stmt->fetch();
            if (!$row) return null;
            // Categories don't have their own public route in v1; fall back
            // to the writing index filtered by category once that lands.
            // For now, return /writing/?category=<slug> as a best-effort
            // pointer so the link doesn't 404.
            return '/writing/?category=' . rawurlencode((string)$row['value_slug']);

        case 'series':
            if ($tid === null) return null;
            $stmt = db()->prepare('SELECT slug FROM series WHERE id = ?');
            $stmt->execute([$tid]);
            $row = $stmt->fetch();
            if (!$row) return null;
            return '/series/' . (string)$row['slug'] . '/';

        case 'content':
            if ($tid === null) return null;
            $stmt = db()->prepare('SELECT type, slug FROM content WHERE id = ?');
            $stmt->execute([$tid]);
            $row = $stmt->fetch();
            if (!$row) return null;
            $type_slug = (string)$row['type'];
            $prefix = match ($type_slug) {
                'article'      => '/writing/',
                'journal'      => '/journal/',
                'live-session' => '/live-sessions/',
                'experiment'   => '/experiments/',
                default        => '/' . $type_slug . '/',
            };
            return $prefix . (string)$row['slug'] . '/';
    }
    return null;
}

// ── Render ────────────────────────────────────────────────────────────

/**
 * Echo the rendered nav for the given zone (header / footer).
 *
 * Header emits one <a> per item with data-nav-key for the prefix-match
 * active-link script. Footer emits the same minus the script integration
 * (the layout-footer doesn't highlight the current page).
 *
 * Items with is_active=0 are skipped. Items whose target no longer
 * resolves are skipped (defensive — the cron sweep should have caught them
 * but the runtime check is a belt-and-suspenders).
 */
function render_nav(string $zone): void
{
    if (!in_array($zone, NAV_ZONES, true)) return;
    foreach (list_nav_items($zone, true) as $item) {
        $url = resolve_nav_target($item);
        if ($url === null) continue;

        $label_html = htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8');
        $href_attr  = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $key_attr   = '';
        if (!empty($item['nav_key'])) {
            $key_attr = ' data-nav-key="' . htmlspecialchars((string)$item['nav_key'], ENT_QUOTES, 'UTF-8') . '"';
        }

        $extra_style = '';
        $highlight_html = '';
        $color = (string)($item['highlight_color'] ?? '') ?: NAV_DEFAULT_HIGHLIGHT_COLOR;
        $color_attr = htmlspecialchars($color, ENT_QUOTES, 'UTF-8');

        if ($item['highlight'] === 'dot') {
            $extra_style = ' style="position:relative"';
            $highlight_html = '<span aria-hidden="true" style="position:absolute;top:-5px;right:-4px;width:10px;height:10px;background:' . $color_attr . ';border-radius:50%"></span>';
        } elseif ($item['highlight'] === 'pill') {
            $pill_text = htmlspecialchars((string)($item['highlight_text'] ?? 'NEW'), ENT_QUOTES, 'UTF-8');
            // Auto-contrast pill text: parse hex; CSS var or anything else
            // falls back to white (the default red reads fine that way).
            $fg = nav_contrast_text((string)($item['highlight_color'] ?? ''));
            $extra_style = ' style="position:relative"';
            $highlight_html = '<span class="layout-nav-pill" style="position:absolute;top:-7px;right:-4px;background:' . $color_attr . ';color:' . $fg . ';font-family:var(--font-cond,sans-serif);font-size:9.5px;letter-spacing:0.08em;text-transform:uppercase;font-weight:700;padding:1px 6px;border-radius:4px;z-index:1;white-space:nowrap;pointer-events:none">' . $pill_text . '</span>';
        }

        echo "<a href=\"$href_attr\"$key_attr$extra_style>$label_html$highlight_html</a>\n";
    }
}

// ── Broken-target sweep ───────────────────────────────────────────────

/**
 * Iterate every active nav item; deactivate any whose target no longer
 * resolves. Run nightly by cron/nav-sweep.php. Returns the count of items
 * that were marked broken (for logging).
 */
function sweep_broken_nav_targets(): int
{
    $marked = 0;
    foreach (list_nav_items(null, true) as $item) {
        if (resolve_nav_target($item) === null) {
            db()->prepare('UPDATE nav_items SET is_active = 0 WHERE id = ?')
                ->execute([(int)$item['id']]);
            $marked++;
        }
    }
    return $marked;
}

/**
 * Items whose target doesn't resolve right now — surfaces the BROKEN badge
 * in the navigation editor.
 */
function list_broken_nav_items(): array
{
    $out = [];
    foreach (list_nav_items() as $item) {
        if (resolve_nav_target($item) === null) {
            $out[] = $item;
        }
    }
    return $out;
}
