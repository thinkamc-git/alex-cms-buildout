<?php
/**
 * index.php — front controller for alexmchong.ca.
 *
 * Reached only for URLs that don't match a real file or directory in the
 * webroot — the .htaccess fallback rewrites everything else to index.php.
 *
 * Phase 3 shipped /hello as a DB-connectivity smoke test.
 * Phase 4 adds /cms/* (login, account, logout, dashboard).
 * Content routes (/writing/[slug] etc.) arrive in Phase 6b.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/router.php';

$router = new Router();

// ── Smoke test (Phase 3) ────────────────────────────────────────────
$router->get('/hello', function (): void {
    header('Content-Type: text/html; charset=utf-8');
    try {
        $row = db()->query('SELECT NOW() AS now')->fetch();
        $now = htmlspecialchars((string)($row['now'] ?? ''), ENT_QUOTES, 'UTF-8');
        echo "<!doctype html>\n";
        echo "<meta charset=\"utf-8\">\n";
        echo "<title>hello</title>\n";
        echo "<p>Database connected. Current time: $now</p>\n";
    } catch (Throwable $e) {
        http_response_code(500);
        echo "<!doctype html>\n";
        echo "<meta charset=\"utf-8\">\n";
        echo "<title>hello — DB error</title>\n";
        echo "<p>Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>\n";
    }
});

// ── CMS routes (Phase 4) ─────────────────────────────────────────────
// Each handler `require`s the matching cms/*.php file. The cms/ directory's
// own .htaccess (Require all denied) blocks direct HTTP access — everything
// has to come through this front controller.

$cms = static function (string $file): callable {
    return static function () use ($file): void {
        require __DIR__ . '/cms/' . $file;
    };
};

$router->get ('/cms',         $cms('index.php'));
$router->get ('/cms/login',   $cms('login.php'));
$router->post('/cms/login',   $cms('login.php'));
$router->get ('/cms/logout',  $cms('logout.php'));   // returns 405 internally
$router->post('/cms/logout',  $cms('logout.php'));
$router->get ('/cms/account', $cms('account.php'));
$router->post('/cms/account', $cms('account.php'));

// ── Articles (Phase 6a) ──────────────────────────────────────────────
// Edit / new / delete / upload — admin-only, gated by Auth::require_login()
// inside each view. URLs use query strings for record ids because the
// router is exact-match only; pretty paths land alongside the public
// /writing/[slug] route in Phase 6b.
$router->get ('/cms/articles',              $cms('views/articles.php'));
$router->get ('/cms/articles/new',          $cms('views/article-new.php'));
$router->post('/cms/articles/new',          $cms('views/article-new.php'));
$router->get ('/cms/articles/edit',         $cms('views/article-edit.php'));
$router->post('/cms/articles/edit',         $cms('views/article-edit.php'));
$router->post('/cms/articles/delete',       $cms('views/article-delete.php'));
$router->post('/cms/articles/upload-image', $cms('views/article-upload-image.php'));

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI']    ?? '/'
);
