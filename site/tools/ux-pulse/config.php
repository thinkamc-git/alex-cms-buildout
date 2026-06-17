<?php
// UX Pulse — shared config
// Included by all endpoint scripts

define('UXP_TOKEN',   'uxp_7k2mN9qR4vWxL8hT');
define('UXP_BASE_URL','https://alexmchong.ca/tools/ux-pulse');

function uxp_auth() {
    $token = isset($_GET['token']) ? $_GET['token'] : (isset($_SERVER['HTTP_X_UXP_TOKEN']) ? $_SERVER['HTTP_X_UXP_TOKEN'] : '');
    if ($token !== UXP_TOKEN) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}
