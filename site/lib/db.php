<?php
/**
 * lib/db.php — single shared PDO connection.
 *
 * Requires $CONFIG to be populated (i.e. config/config.php has been loaded).
 *
 * Usage:
 *   $rows = db()->query('SELECT 1 AS one')->fetchAll();
 *
 * The connection is created on first call and reused for the rest of the
 * request. Default fetch mode is associative arrays; prepares are NOT emulated
 * (so parameter binding actually uses the server-side protocol).
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    global $CONFIG;
    if (!isset($CONFIG['db'])) {
        throw new RuntimeException(
            'lib/db.php called before config/config.php was loaded ($CONFIG[db] missing).'
        );
    }

    $db = $CONFIG['db'];
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}
