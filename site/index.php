<?php
/**
 * index.php — front controller for alexmchong.ca.
 *
 * Reached only for URLs that don't match a real file or directory in the
 * webroot — the .htaccess fallback rewrites everything else to index.php.
 *
 * Phase 3 ships one route (/hello) as a DB-connectivity smoke test. Content
 * routes (/writing/[slug] etc.) are added in Phase 6b onward.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/router.php';

$router = new Router();

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

$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI']    ?? '/'
);
