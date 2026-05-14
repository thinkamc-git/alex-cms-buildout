<?php
/**
 * Local preview router for the alexmchong.ca static site.
 *
 * Used by HTML PHP Preview Plus (VS Code extension) and any other PHP
 * dev server. It mirrors the production .htaccess rules:
 *
 *   - /portfolio*, /research/, /talks/, /meet/, /linkedin/ → external 302s
 *   - /cv/, /community/, /consulting/, /landing.html       → internal 302s
 *   - /foo.html → /foo/ (302 canonicalization)
 *   - /foo/    → silent rewrite to /foo.html (URL bar unchanged)
 *   - Files served from site/_pages/ (or site/_design-system/ for /_ds/*)
 *   - Unknown paths render site/_pages/404.html with a 404 status
 *
 * If you ever need to restore .php-preview-router.php (HTML PHP Preview
 * Plus regenerates it on install), copy this file's contents over it.
 */

// CORS preflight (some preview extensions probe with OPTIONS)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$workspaceRoot = realpath(__DIR__ . '/..');
$pagesRoot     = $workspaceRoot . '/site/_pages';
$dsRoot        = $workspaceRoot . '/site/_design-system';

$rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri    = '/' . ltrim(str_replace('..', '', $rawUri), '/');

// ── External 302 redirects ───────────────────────────────────────────
$external = [
    '#^/portfolioforhire/?$#' => 'https://alexmchong-portfolio.webflow.io/',
    '#^/portfolio/?$#'        => 'https://alexmchong-portfolio.webflow.io/',
    '#^/research/?$#'         => 'https://alexmchong.notion.site/alexmchong/Alex-s-Master-s-Thesis-NTUT-3ef25dfbb1e145bb8ed9176171828f73',
    '#^/talks/?$#'            => 'https://alexmchong.notion.site/alexmchong/Alex-M-Chong-Design-Talks-455f32067df04918a18875321c3cc9fa',
    '#^/meet/?$#'             => 'https://calendly.com/alexmchong/meet',
    '#^/linkedin/?$#'         => 'https://linkedin.com/in/alexmchong/',
];
foreach ($external as $pattern => $target) {
    if (preg_match($pattern, $uri)) {
        header("Location: $target", true, 302);
        exit;
    }
}

// ── Internal 302 redirects ────────────────────────────────────────────
$internal = [
    '#^/cv/?$#'                         => '/resume/',
    '#^/community/?$#'                  => '/',
    '#^/consulting/?$#'                 => '/',
    '#^/landing\.html$#'                => '/',
    '#^/about\.html$#'                  => '/about/',
    '#^/coaching\.html$#'               => '/coaching/',
    '#^/work-with-me\.html$#'           => '/work-with-me/',
    '#^/resume\.html$#'                 => '/resume/',
    '#^/newsletter\.html$#'             => '/newsletter/',
    '#^/newsletter-confirmed\.html$#'   => '/newsletter-confirmed/',
];
foreach ($internal as $pattern => $target) {
    if (preg_match($pattern, $uri)) {
        header("Location: $target", true, 302);
        exit;
    }
}

// ── Silent rewrites: canonical bare-path → underlying .html ──────────
$silent = [
    '#^/about/?$#'                => '/about.html',
    '#^/coaching/?$#'             => '/coaching.html',
    '#^/work-with-me/?$#'         => '/work-with-me.html',
    '#^/resume/?$#'               => '/resume.html',
    '#^/newsletter/?$#'           => '/newsletter.html',
    '#^/newsletter-confirmed/?$#' => '/newsletter-confirmed.html',
];
foreach ($silent as $pattern => $target) {
    if (preg_match($pattern, $uri)) {
        $uri = $target;
        break;
    }
}

// ── File lookup ──────────────────────────────────────────────────────
// /_ds/* → site/_design-system/*
// everything else → site/_pages/*
if (preg_match('#^/_ds(/.*)?$#', $uri, $m)) {
    $path = $dsRoot . ($m[1] ?? '/');
} else {
    $path = $pagesRoot . $uri;
}

// Default-document handling: when URI ends in /, try index.html
if (substr($path, -1) === '/') {
    $path .= 'index.html';
}

if (is_file($path)) {
    serve_file($path);
    exit;
}

// ── 404 ──────────────────────────────────────────────────────────────
http_response_code(404);
$notFound = $pagesRoot . '/404.html';
if (is_file($notFound)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($notFound);
} else {
    echo "404 Not Found — $uri";
}
exit;

// ── Helpers ─────────────────────────────────────────────────────────
function serve_file(string $path): void
{
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $types = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'ico'  => 'image/x-icon',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
    ];
    if (isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
    }
    readfile($path);
}
