<?php
/**
 * config/config.php — environment resolver.
 *
 * Loads the right per-environment file (config.local.php, config.staging.php,
 * or config.production.php) and exposes its $CONFIG array. Those three files
 * are .gitignored — each lives on its own machine/server with real credentials.
 *
 * Environment selection priority:
 *   1. APP_ENV environment variable (explicit override, useful for CLI).
 *   2. HTTP_HOST heuristic (web requests).
 *   3. Fallback to 'local'.
 *
 * Add this file to any entry point before reading $CONFIG:
 *
 *     require_once __DIR__ . '/config/config.php';
 *     // $CONFIG is now populated.
 */

if (!isset($CONFIG)) {
    $env = getenv('APP_ENV') ?: '';

    if ($env === '') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host !== '') {
            if (str_contains($host, 'staging.alexmchong.ca')) {
                $env = 'staging';
            } elseif (str_contains($host, 'alexmchong.ca')) {
                $env = 'production';
            }
        }
    }

    if ($env === '') {
        $env = 'local';
    }

    $env_file = __DIR__ . '/config.' . $env . '.php';

    if (!file_exists($env_file)) {
        $msg = "Missing config file: config.$env.php\n"
             . "Copy config.example.php to config.$env.php and fill in credentials.\n";
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg);
            exit(2);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit($msg);
    }

    require $env_file;

    if (!isset($CONFIG) || !is_array($CONFIG)) {
        $msg = "config.$env.php did not define a \$CONFIG array.\n";
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $msg);
            exit(2);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit($msg);
    }
}
