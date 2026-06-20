<?php
declare(strict_types=1);

/**
 * lib/sanitize.php — HTML allowlist for Tiptap-authored body content.
 *
 * Phase 6a Decision: the allowlist must match the Tiptap toolbar EXACTLY,
 * with no extras. Toolbar surface area:
 *   bold (strong), italic (em), H2, H3, ul/li, ol/li, link (a[href]),
 *   blockquote, inline code (code), muted-word (span.m), image (img).
 *
 * The function uses DOMDocument so we work on a real parse tree rather
 * than regexes. Anything not on the allowlist is unwrapped (kept as text
 * children) or stripped depending on the tag. The result is a fragment
 * of well-formed HTML, ready to store in content.body as LONGTEXT.
 *
 * Output is trusted — see ENGINEERING.md §8: Tiptap-sanitized HTML is
 * the only string output un-escaped at render time, and it goes through
 * THIS function on save.
 *
 * One deliberate exception to the "matches the toolbar exactly" rule:
 * `div.html-embed` (the HTML Embed block). The author pastes raw markup
 * there on purpose — e.g. an exported SVG that the strict allowlist below
 * would otherwise strip — so its subtree is exempt from this function
 * entirely. Single-author site; see the HtmlEmbed node in
 * cms/_assets/tiptap-setup.js for the full reasoning.
 */

/**
 * Allowed tag → allowed attributes map. Empty array = tag allowed with
 * NO attributes (typical for prose tags). For <span>, only class="m" is
 * permitted — see filter logic below.
 */
const SANITIZE_ALLOWED = [
    'p'          => [],
    'strong'     => [],
    'em'         => [],
    'h2'         => [],
    'h3'         => [],
    'ul'         => [],
    'ol'         => [],
    'li'         => [],
    'a'          => ['href'],
    'blockquote' => [],
    'code'       => [],
    'span'       => ['class'],   // restricted to class="m" below
    'img'        => ['src', 'alt'],
    'br'         => [],
    // Phase 21.x — figure-wrapped images with captions + size preset.
    // data-size accepts: default | wide | full (validated below). The
    // default value is dropped from output to keep stored HTML lean.
    // data-rounded / data-border are independent on/off toggles — the only
    // legal value is "0" (off); "on" is the implicit default (no attribute).
    'figure'     => ['data-size', 'data-rounded', 'data-border'],
    'figcaption' => [],
    // HTML Embed — restricted to class="html-embed" below (same pattern as
    // span/class="m"). Its subtree is deliberately exempt from sanitization;
    // see the SANITIZE_HTML_EMBED_CLASS note in sanitize_walk().
    'div'        => ['class', 'data-size'],
];

/**
 * The only legal value for div[class] — anything else makes the div an
 * unrecognized tag (unwrapped, like any other non-allowlisted element).
 */
const SANITIZE_HTML_EMBED_CLASS = 'html-embed';

/**
 * Valid values for figure[data-size]. Anything else is treated as the
 * default (and the attribute is removed entirely on save).
 */
const SANITIZE_FIGURE_SIZES = ['wide', 'full'];

/**
 * Tags whose contents should be DROPPED entirely (not unwrapped).
 * Tiptap shouldn't ever emit these, but be paranoid about pasted content.
 */
const SANITIZE_STRIP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'form'];

/**
 * Clean a Tiptap-authored HTML fragment. Returns sanitized HTML or
 * an empty string if input is empty after sanitization.
 */
function sanitize_html(string $html): string
{
    $trimmed = trim($html);
    if ($trimmed === '') return '';

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    // loadHTML on a fragment needs a wrapper + UTF-8 declaration so
    // accented characters survive. The two flags below stop DOMDocument
    // from injecting <html>/<body>/<!DOCTYPE>.
    $wrapped = '<?xml encoding="UTF-8"?><div id="__sanitize_root">' . $trimmed . '</div>';
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $dom->getElementById('__sanitize_root');
    if ($root === null) return '';

    sanitize_walk($root);

    // Serialize children of the root wrapper.
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return trim($out);
}

/**
 * Recursively walk $node's children. For each element:
 *   - if in SANITIZE_STRIP: remove subtree
 *   - if not in SANITIZE_ALLOWED: unwrap (replace with children)
 *   - if allowed: strip non-allowlisted attributes, recurse
 */
function sanitize_walk(DOMNode $node): void
{
    // Iterate over a snapshot — we're mutating $node->childNodes.
    $children = [];
    foreach ($node->childNodes as $c) $children[] = $c;

    foreach ($children as $child) {
        if (!($child instanceof DOMElement)) continue;

        $tag = strtolower($child->nodeName);

        if (in_array($tag, SANITIZE_STRIP, true)) {
            $node->removeChild($child);
            continue;
        }

        // A <div> is only a recognized tag when it's the HTML Embed
        // wrapper; any other div (Tiptap shouldn't emit one) falls through
        // to the unwrap branch below like any unrecognized tag.
        $isHtmlEmbed = $tag === 'div' && trim($child->getAttribute('class')) === SANITIZE_HTML_EMBED_CLASS;

        if (!isset(SANITIZE_ALLOWED[$tag]) || ($tag === 'div' && !$isHtmlEmbed)) {
            // Unwrap: recurse first so descendants are clean, then
            // move children up and drop the wrapper.
            sanitize_walk($child);
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }

        // Allowed tag — filter attributes.
        $allowedAttrs = SANITIZE_ALLOWED[$tag];
        $existing = [];
        foreach ($child->attributes as $attr) $existing[] = $attr->nodeName;

        foreach ($existing as $name) {
            if (!in_array($name, $allowedAttrs, true)) {
                $child->removeAttribute($name);
                continue;
            }
            // Per-attribute hardening:
            if ($name === 'href') {
                $href = $child->getAttribute('href');
                if (!sanitize_safe_url($href)) $child->removeAttribute('href');
            } elseif ($name === 'src') {
                $src = $child->getAttribute('src');
                // Images may be relative (/uploads/...) or absolute http(s).
                if (!sanitize_safe_url($src, true)) $child->removeAttribute('src');
            } elseif ($name === 'class' && $tag === 'span') {
                // The only legal span class is "m" (muted-word).
                if (trim($child->getAttribute('class')) !== 'm') {
                    $child->removeAttribute('class');
                }
            } elseif ($name === 'data-size' && ($tag === 'figure' || $isHtmlEmbed)) {
                // Only allow whitelisted size presets; "default" is implicit
                // (no attribute) so we drop it too.
                $v = trim($child->getAttribute('data-size'));
                if (!in_array($v, SANITIZE_FIGURE_SIZES, true)) {
                    $child->removeAttribute('data-size');
                }
            } elseif (($name === 'data-rounded' || $name === 'data-border') && $tag === 'figure') {
                // "0" (off) is the only legal value; "on" is implicit (no
                // attribute), same convention as data-size's default.
                if (trim($child->getAttribute($name)) !== '0') {
                    $child->removeAttribute($name);
                }
            }
        }

        // HTML Embed: deliberate exception to the rest of this function.
        // The author pastes raw markup (e.g. an SVG) here on purpose — see
        // the HtmlEmbed node comment in tiptap-setup.js. Skip recursing
        // into its subtree so that markup ships untouched; only the
        // wrapper's own class/data-size were validated above.
        if ($isHtmlEmbed) {
            continue;
        }

        // Drop empty <figcaption> so blank captions don't ship — saves a
        // few bytes and prevents an empty CSS box from rendering publicly.
        if ($tag === 'figcaption' && trim($child->textContent ?? '') === '') {
            $node->removeChild($child);
            continue;
        }

        // <span> with no class survives parsing — unwrap empty spans so
        // the editor's runtime output equals the stored output.
        if ($tag === 'span' && !$child->hasAttribute('class')) {
            sanitize_walk($child);
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }

        sanitize_walk($child);
    }
}

/**
 * Permit http(s), mailto, and relative URLs. Reject javascript:, data:,
 * vbscript:, file:, and anything with control chars. When $imageContext
 * is true, also reject any URL that contains a hash (#) to keep image
 * paths boring.
 */
function sanitize_safe_url(string $url, bool $imageContext = false): bool
{
    $u = trim($url);
    if ($u === '') return false;
    if (preg_match('/[\x00-\x1F]/', $u)) return false;

    // Block dangerous schemes.
    if (preg_match('/^\s*(javascript|vbscript|data|file):/i', $u)) return false;

    // Mailto only for links (not images).
    if (stripos($u, 'mailto:') === 0) return !$imageContext;

    // Relative URLs OK (start with / or are bare paths/queries).
    if ($u[0] === '/' || $u[0] === '?' || $u[0] === '#') return !($imageContext && $u[0] === '#');

    // Absolute http(s) only.
    if (preg_match('#^https?://#i', $u)) return true;

    return false;
}
