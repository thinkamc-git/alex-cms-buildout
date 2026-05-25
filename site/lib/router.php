<?php
/**
 * lib/router.php — minimal front-controller router.
 *
 * Supports exact-match routes plus single-segment `:name` parameters
 * (e.g. `/writing/:slug`). Extracted segments are passed to the handler
 * as a single associative array. Multi-segment captures (`*`) are not
 * supported yet — add when a phase actually needs them.
 *
 * Usage:
 *   $r = new Router();
 *   $r->get('/hello', function () { echo 'hi'; });
 *   $r->get('/writing/:slug', function (array $p) { echo $p['slug']; });
 *   $r->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
 */

class Router
{
    /** @var array<int,array{method:string,path:string,handler:callable}> */
    private array $routes = [];

    /** @var callable|null */
    private $notFound = null;

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $path,
            'handler' => $handler,
        ];
    }

    /**
     * Register a callable invoked after every route misses. The handler
     * receives ($method, $path) and is free to emit any response —
     * common uses: resolve a DB-backed redirect, render a themed 404,
     * or fall through to the legacy static page. Phase 13 wires this
     * in site/index.php to call resolve_redirect() then templates/404.php.
     */
    public function set_not_found(callable $handler): void
    {
        $this->notFound = $handler;
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * Try to match a route pattern against the request path. Returns the
     * captured parameters as an assoc array on match, or null on miss.
     * Static segments must match literally; `:name` captures one segment
     * (no slashes). Pure exact-match patterns (no `:`) short-circuit to
     * a string compare to keep the hot path cheap.
     */
    private function matchRoute(string $pattern, string $path): ?array
    {
        if (strpos($pattern, ':') === false) {
            return $pattern === $path ? [] : null;
        }
        $pp = explode('/', $pattern);
        $sp = explode('/', $path);
        if (count($pp) !== count($sp)) return null;
        $params = [];
        $n = count($pp);
        for ($i = 0; $i < $n; $i++) {
            $segP = $pp[$i];
            $segR = $sp[$i];
            if ($segP !== '' && $segP[0] === ':') {
                if ($segR === '') return null;
                $params[substr($segP, 1)] = $segR;
            } elseif ($segP !== $segR) {
                return null;
            }
        }
        return $params;
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
            if ($r['method'] !== $method) continue;
            $params = $this->matchRoute($r['path'], $path);
            if ($params !== null) {
                ($r['handler'])($params);
                return;
            }
        }

        // No route matched. Defer to the registered not-found handler if
        // one was installed (Phase 13: resolves DB-backed redirects, then
        // renders the themed 404). Otherwise fall back to the legacy
        // static 404.html so old behavior is preserved.
        if ($this->notFound !== null) {
            ($this->notFound)($method, $path);
            return;
        }

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
