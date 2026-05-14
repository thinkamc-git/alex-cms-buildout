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
