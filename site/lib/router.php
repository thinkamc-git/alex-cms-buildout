<?php
/**
 * lib/router.php — minimal front-controller router.
 *
 * Exact-match routes only. No path parameters yet — added when a phase
 * actually needs them (Phase 6b's article slugs, etc.). Keep this file
 * small and predictable until then.
 *
 * Usage:
 *   $r = new Router();
 *   $r->get('/hello', function () { echo 'hi'; });
 *   $r->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
 */

class Router
{
    /** @var array<int,array{method:string,path:string,handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $path,
            'handler' => $handler,
        ];
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        // Normalize trailing slash: treat /hello and /hello/ as the same route,
        // except for the root path which stays '/'.
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        $method = strtoupper($method);

        foreach ($this->routes as $r) {
            if ($r['method'] === $method && $r['path'] === $path) {
                ($r['handler'])();
                return;
            }
        }

        // Apache's `ErrorDocument 404` only fires for Apache-level 404s, not
        // application-level ones. Serve the themed 404 page ourselves so the
        // public 404 experience matches Phase 1 (when missing URLs went
        // straight to Apache → ErrorDocument).
        http_response_code(404);
        $page = dirname(__DIR__) . '/404.html';
        if (is_file($page)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($page);
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo "404 — no route for $method $path\n";
    }
}
