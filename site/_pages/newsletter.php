<?php
// Phase 14 time-trap: stamp the page-load time in a short-lived cookie.
// The POST /subscribe handler rejects any submission that arrives less
// than 2 seconds after this cookie was set. Cookies are universal (no
// JS required) and the cookie is HttpOnly so the bot can't easily fake
// it client-side without parsing the response. Missing cookie = falls
// through (don't break users who block cookies — honeypot + MX-check +
// rate-limit still apply).
setcookie('newsletter_form_loaded', (string)time(), [
    'expires'  => time() + 3600,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict',
]);

$title       = 'Subscribe — Alex M. Chong';
$description = 'Occasional writing on design leadership, coaching, and the craft of design practice. Subscribe for new pieces, live sessions, and the rare experiment.';
$body        = 'newsletter';
require __DIR__ . '/_layout/_page-shell.php';
