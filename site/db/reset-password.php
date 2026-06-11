<?php
/**
 * db/reset-password.php — CLI-only password reset for the single CMS user.
 *
 * The primary recovery path (AUTH-SECURITY.md §13): if the password is lost,
 * run this over SSH on the server. There is NO web-facing "forgot password"
 * form — keeping recovery server-side means a remote attacker has no path to
 * it. Refuses to run from a browser.
 *
 * Usage (on the server):
 *   php ~/alexmchong.ca/db/reset-password.php
 *       → generates a strong temp password, prints it, signs out all sessions
 *   php ~/alexmchong.ca/db/reset-password.php --password='SomeStrongPass1'
 *       → sets a specific password (must pass the strength rules)
 *   php ~/alexmchong.ca/db/reset-password.php --email=login@alexmchong.ca
 *       → target a specific email (defaults to the single user row)
 *
 * Always bumps session_epoch, so every existing session on every device is
 * signed out — important if the reset is because of a suspected compromise.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("db/reset-password.php is a CLI tool. Run with `php db/reset-password.php`.\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';

$opts     = getopt('', ['email::', 'password::']);
$email    = isset($opts['email']) && $opts['email'] !== false ? trim((string)$opts['email']) : null;
$password = isset($opts['password']) && $opts['password'] !== false ? (string)$opts['password'] : null;

// Resolve the target user.
try {
    if ($email !== null && $email !== '') {
        $stmt = db()->prepare('SELECT id, email FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
    } else {
        $stmt = db()->query('SELECT id, email FROM users ORDER BY id ASC LIMIT 1');
    }
    $user = $stmt->fetch();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB error: ' . $e->getMessage() . "\n");
    exit(2);
}

if (!$user) {
    fwrite(STDERR, "No matching user found. (Has the database been seeded via setup.php?)\n");
    exit(1);
}

// Determine the new password.
$generated = false;
if ($password === null) {
    $password  = generate_temp_password();
    $generated = true;
} else {
    $err = Auth::validate_password_strength($password);
    if ($err !== null) {
        fwrite(STDERR, "Rejected: $err\n");
        exit(1);
    }
}

$hash = password_hash($password, PASSWORD_DEFAULT);
db()->prepare(
    'UPDATE users
        SET password_hash       = :h,
            password_changed_at = NOW(),
            failed_attempts     = 0,
            locked_until        = NULL,
            session_epoch       = session_epoch + 1
      WHERE id = :id'
)->execute([':h' => $hash, ':id' => (int)$user['id']]);

echo "Password reset for: " . (string)$user['email'] . "\n";
echo "All existing sessions have been signed out (session_epoch bumped).\n";
if ($generated) {
    echo "\n  Temporary password:  " . $password . "\n\n";
    echo "Sign in at /cms/login with this, then change it in Settings → Account.\n";
} else {
    echo "New password set as supplied.\n";
}

/**
 * Strong 16-char temp password guaranteed to satisfy the §8 rules
 * (≥1 upper, ≥1 lower, ≥1 digit). Ambiguous characters omitted.
 */
function generate_temp_password(): string
{
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnpqrstuvwxyz';
    $digit = '23456789';
    $all   = $upper . $lower . $digit;

    $chars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digit[random_int(0, strlen($digit) - 1)],
    ];
    for ($i = count($chars); $i < 16; $i++) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }
    // Fisher–Yates shuffle so the guaranteed chars aren't always first.
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}
