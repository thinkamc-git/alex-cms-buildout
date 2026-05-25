<?php
/**
 * cron/backup.php — daily mysqldump → /backups/ (Phase 13).
 *
 * Dumps the current environment's database to
 *   ../backups/backup-YYYY-MM-DD.sql.gz
 *
 * Retention: 7 days. Anything older is unlinked at the end of the run so
 * /backups/ stays bounded.
 *
 * Invocation (DreamHost, daily at 03:30 server time):
 *   APP_ENV=production php /home/alexmchong/alexmchong.ca/cron/backup.php
 *
 * Web access blocked by /cron/.htaccess. CLI only.
 *
 * Credentials are read from $CONFIG['db'] and passed to mysqldump via a
 * temporary defaults-file so the password never appears in `ps`.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

require_once __DIR__ . '/../config/config.php';

$log_dir  = __DIR__ . '/../logs';
$log_file = $log_dir . '/backup.log';
@mkdir($log_dir, 0755, true);

$log = static function (string $msg) use ($log_file): void {
    $ts = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$ts] $msg\n", FILE_APPEND);
    echo "[$ts] $msg\n";
};

$backup_dir = __DIR__ . '/../backups';
@mkdir($backup_dir, 0755, true);
if (!is_dir($backup_dir) || !is_writable($backup_dir)) {
    $log("ERROR: backup dir not writable: $backup_dir");
    exit(1);
}

global $CONFIG;
$db = $CONFIG['db'] ?? [];
$host    = (string)($db['host'] ?? 'localhost');
$name    = (string)($db['name'] ?? '');
$user    = (string)($db['user'] ?? '');
$pass    = (string)($db['pass'] ?? '');

if ($name === '' || $user === '') {
    $log('ERROR: missing db config (name/user)');
    exit(1);
}

// Defaults file (chmod 600) keeps the password off the process list and out
// of any shell history. unlink() in the finally block.
$defaults = tempnam(sys_get_temp_dir(), 'mysqldump-');
if ($defaults === false) {
    $log('ERROR: could not create temp defaults file');
    exit(1);
}
chmod($defaults, 0600);
file_put_contents(
    $defaults,
    "[client]\n"
    . 'host=' . $host . "\n"
    . 'user=' . $user . "\n"
    . 'password="' . str_replace('"', '\\"', $pass) . "\"\n"
);

$date     = date('Y-m-d');
$out_file = $backup_dir . '/backup-' . $date . '.sql.gz';

try {
    // mysqldump | gzip > out.sql.gz
    $cmd = sprintf(
        'mysqldump --defaults-extra-file=%s --single-transaction --skip-lock-tables --no-tablespaces %s | gzip -9 > %s',
        escapeshellarg($defaults),
        escapeshellarg($name),
        escapeshellarg($out_file)
    );

    $rc = 0;
    $out = [];
    exec($cmd . ' 2>&1', $out, $rc);

    if ($rc !== 0) {
        $log('ERROR: mysqldump exit=' . $rc . ' :: ' . implode(' | ', $out));
        // Don't leave a half-written .sql.gz behind.
        @unlink($out_file);
        exit(1);
    }

    $size = is_file($out_file) ? filesize($out_file) : 0;
    if ($size <= 0) {
        $log("ERROR: backup file is empty: $out_file");
        @unlink($out_file);
        exit(1);
    }

    $log(sprintf('OK %s (%s bytes)', $out_file, number_format($size)));

    // Retention: keep 7 most-recent backup-YYYY-MM-DD.sql.gz files.
    $files = glob($backup_dir . '/backup-*.sql.gz') ?: [];
    rsort($files, SORT_STRING);
    foreach (array_slice($files, 7) as $old) {
        if (@unlink($old)) {
            $log("removed $old");
        }
    }
} finally {
    @unlink($defaults);
}
