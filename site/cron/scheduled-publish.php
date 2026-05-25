<?php
/**
 * cron/scheduled-publish.php — promote scheduled rows to live (Phase 13).
 *
 * Every 5 minutes (via DreamHost cron) we sweep `content` for rows where
 * published_status='scheduled' AND published_at <= NOW() and flip them
 * to 'live'. Each flip is logged to /logs/scheduled-publish.log.
 *
 * Invocation (DreamHost):
 *   APP_ENV=production php /home/alexmchong/alexmchong.ca/cron/scheduled-publish.php
 *
 * Web access to this file is blocked by /cron/.htaccess (Require all denied).
 * The script only does anything when run from the CLI.
 *
 * See docs/DEPLOYMENT.md for the cron-installation steps.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';

$log_dir  = __DIR__ . '/../logs';
$log_file = $log_dir . '/scheduled-publish.log';
@mkdir($log_dir, 0755, true);

$log = static function (string $msg) use ($log_file): void {
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$ts] $msg\n", FILE_APPEND);
    echo "[$ts] $msg\n";
};

try {
    $sql = "SELECT id, slug, type, title, published_at
              FROM content
             WHERE published_status = 'scheduled'
               AND published_at IS NOT NULL
               AND published_at <= NOW()";
    $rows = db()->query($sql)->fetchAll() ?: [];

    if (count($rows) === 0) {
        // Silent no-op so the log file doesn't churn every 5 minutes.
        exit(0);
    }

    $upd = db()->prepare(
        "UPDATE content
            SET published_status = 'live',
                updated_at = CURRENT_TIMESTAMP
          WHERE id = :id
            AND published_status = 'scheduled'"
    );

    foreach ($rows as $r) {
        $ok = $upd->execute([':id' => (int)$r['id']]);
        if ($ok && $upd->rowCount() > 0) {
            $log(sprintf(
                'PUBLISHED #%d (%s) %s — scheduled_at=%s',
                (int)$r['id'],
                (string)$r['type'],
                (string)$r['slug'],
                (string)$r['published_at']
            ));
        }
    }
} catch (Throwable $e) {
    $log('ERROR: ' . $e->getMessage());
    exit(1);
}
