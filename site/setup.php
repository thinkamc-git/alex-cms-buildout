<?php
/**
 * setup.php — one-shot bootstrap for the very first deploy.
 *
 * Per AUTH-SECURITY.md §10. Visit ONCE on a fresh database:
 *   1. First visit: seeds the user row with email login@alexmchong.ca and a
 *      random 16-char temp password, which it prints in plain HTML.
 *   2. Log in at /cms/login with that temp password.
 *   3. Visit /cms/account and change the password. That POST handler calls
 *      unlink() on this file as a side-effect, deleting it.
 *   4. If for any reason the unlink in step 3 didn't run, visiting this file
 *      again after a successful password change will detect the completed
 *      state and self-delete here too.
 *
 * Sits at the webroot (not inside /cms/) so it can be reached BEFORE any user
 * exists. Deletes itself after first successful password change, leaving no
 * permanent install footgun.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/lib/db.php';

header('Content-Type: text/html; charset=utf-8');

$bootstrap_email = 'login@alexmchong.ca';

try {
    $row = db()->query('SELECT id, password_changed_at FROM users LIMIT 1')->fetch();
} catch (Throwable $e) {
    setup_render('Database error', '<p>Cannot read the <code>users</code> table. Has the 0002 migration been applied?</p><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>');
    exit;
}

// State 3: setup complete — self-delete and 404.
if ($row && !empty($row['password_changed_at'])) {
    @unlink(__FILE__);
    http_response_code(404);
    echo "Not found.\n";
    exit;
}

// State 2: row exists but password not yet changed — bootstrap is mid-flight.
if ($row && empty($row['password_changed_at'])) {
    setup_render('Setup in progress',
        '<p>A user row exists but the password has not been changed yet. ' .
        'Log in at <a href="/cms/login">/cms/login</a> with the temp password from your previous setup visit, ' .
        'then change the password on the account page. This file will self-delete once that completes.</p>'
    );
    exit;
}

// State 1: no user row — generate temp password and insert.
$temp = setup_generate_temp_password(16);
$hash = password_hash($temp, PASSWORD_DEFAULT);
$stmt = db()->prepare('INSERT INTO users (email, password_hash) VALUES (:e, :h)');
$stmt->execute([':e' => $bootstrap_email, ':h' => $hash]);

setup_render('Setup — temp password',
    '<p>A user row was created for <strong>' . htmlspecialchars($bootstrap_email, ENT_QUOTES, 'UTF-8') . '</strong>.</p>' .
    '<p><strong>Temp password:</strong> <code style="background:#ffe; padding:0.25rem 0.5rem; border:1px solid #cc0;">' .
    htmlspecialchars($temp, ENT_QUOTES, 'UTF-8') .
    '</code></p>' .
    '<p>Next steps:</p>' .
    '<ol>' .
    '<li>Copy the temp password above.</li>' .
    '<li>Go to <a href="/cms/login">/cms/login</a> and sign in.</li>' .
    '<li>Visit <a href="/cms/account">/cms/account</a> and change the password.</li>' .
    '<li>This file will self-delete after the password change.</li>' .
    '</ol>' .
    '<p style="color:#a00;"><strong>Save the temp password before closing this page.</strong> It will not be shown again.</p>'
);

function setup_render(string $title, string $body): void
{
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html>\n";
    echo "<html lang=\"en\"><head>\n";
    echo "<meta charset=\"utf-8\"><title>$t — setup</title>\n";
    echo "<meta name=\"robots\" content=\"noindex,nofollow\">\n";
    echo "<style>body{max-width:36rem;margin:3rem auto;padding:0 1.5rem;font-family:system-ui,sans-serif;line-height:1.5;} code{font-family:ui-monospace,monospace;}</style>\n";
    echo "</head><body>\n";
    echo "<h1>$t</h1>\n";
    echo $body;
    echo "\n</body></html>\n";
}

function setup_generate_temp_password(int $len): string
{
    // Mixed alphanumeric, no ambiguous chars (0/O, 1/l/I).
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    // Ensure at least one upper, one lower, one digit by drawing from each set first.
    $out .= substr('ABCDEFGHJKLMNPQRSTUVWXYZ', random_int(0, 23), 1);
    $out .= substr('abcdefghijkmnpqrstuvwxyz', random_int(0, 23), 1);
    $out .= substr('23456789', random_int(0, 7), 1);
    for ($i = 3; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    // Shuffle so the guaranteed chars aren't always at the front.
    $chars = str_split($out);
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}
