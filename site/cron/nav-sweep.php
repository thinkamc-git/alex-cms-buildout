<?php
/**
 * cron/nav-sweep.php — nightly broken-target sweep for nav_items (Phase 20).
 *
 * Iterates every active nav item; sets is_active=0 on any whose target
 * row no longer resolves (resolver returns NULL). The CMS Navigation
 * editor surfaces a BROKEN badge for those items.
 *
 * Invocation (DreamHost crontab):
 *   APP_ENV=production php /home/alexmchong/alexmchong.ca/cron/nav-sweep.php
 *   APP_ENV=staging    php /home/alexmchong/staging.alexmchong.ca/cron/nav-sweep.php
 *
 * Suggested cadence: nightly at 3:10am Vancouver (offset from the 3:00
 * backup so neither blocks the other). Web access blocked by /cron/.htaccess.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/nav.php';
require_once __DIR__ . '/../lib/pages.php';

$log_dir  = __DIR__ . '/../logs';
$log_file = $log_dir . '/nav-sweep.log';
@mkdir($log_dir, 0755, true);

$log = static function (string $msg) use ($log_file): void {
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$ts] $msg\n", FILE_APPEND);
    echo "[$ts] $msg\n";
};

try {
    $marked = sweep_broken_nav_targets();
    $log("Sweep complete — $marked nav item(s) marked broken.");
} catch (Throwable $e) {
    $log('ERROR: ' . $e->getMessage());
    exit(1);
}
