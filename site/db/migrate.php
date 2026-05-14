<?php
/**
 * db/migrate.php — apply pending migrations.
 *
 * Usage (run on the server, never via the web):
 *   php db/migrate.php             # apply any pending migrations
 *   php db/migrate.php --status    # list all migrations, applied + pending
 *
 * Behaviour (per Phase 3 Decisions):
 *   - Tracker table:  `_migrations` (filename + applied_at).
 *   - Error policy:   roll back on first error — stop applying further
 *                     migrations once one fails. Within a single .sql
 *                     file the runner cannot roll back DDL (MySQL DDL
 *                     is non-transactional); a partial failure leaves
 *                     partial state and the operator must clean up.
 *   - Order:          files in db/migrations/ applied in alphabetical
 *                     order (the 0001_… prefix enforces this).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("db/migrate.php is a CLI tool. Run with `php db/migrate.php`.\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';

$status_only = in_array('--status', $argv ?? [], true);

try {
    $pdo = db();
} catch (Throwable $e) {
    fwrite(STDERR, "DB connect failed: " . $e->getMessage() . "\n");
    exit(2);
}

// Tracker table — created on first run, idempotent thereafter.
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS _migrations (
        filename   VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$migrations_dir = __DIR__ . '/migrations';
$files = glob($migrations_dir . '/*.sql') ?: [];
sort($files);

$applied = array_flip(
    $pdo->query('SELECT filename FROM _migrations')->fetchAll(PDO::FETCH_COLUMN) ?: []
);

if ($status_only) {
    if (!$files) {
        echo "No migration files found in $migrations_dir\n";
        exit(0);
    }
    foreach ($files as $path) {
        $name = basename($path);
        $mark = isset($applied[$name]) ? '[x]' : '[ ]';
        echo "  $mark $name\n";
    }
    exit(0);
}

$pending = [];
foreach ($files as $path) {
    if (!isset($applied[basename($path)])) {
        $pending[] = $path;
    }
}

if (!$pending) {
    echo "No pending migrations.\n";
    exit(0);
}

echo "Applying " . count($pending) . " migration(s):\n";

foreach ($pending as $path) {
    $name = basename($path);
    $sql  = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "  $name — empty or unreadable. Aborting.\n");
        exit(1);
    }
    echo "  $name … ";
    try {
        $pdo->exec($sql);
        $pdo->prepare('INSERT INTO _migrations (filename) VALUES (?)')->execute([$name]);
        echo "ok\n";
    } catch (Throwable $e) {
        echo "FAILED\n";
        fwrite(STDERR, "    " . $e->getMessage() . "\n");
        fwrite(STDERR, "Aborting per 'roll back on first error' policy.\n");
        fwrite(STDERR, "Note: MySQL DDL is non-transactional. If this migration\n");
        fwrite(STDERR, "      contained mixed DDL/DML, state may be partial.\n");
        exit(1);
    }
}

echo "Done.\n";
