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
];

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

        if (!isset(SANITIZE_ALLOWED[$tag])) {
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
            }
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
