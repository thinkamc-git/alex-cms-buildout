<?php
/**
 * config.example.php — template for per-environment config.
 *
 * Copy this to one of:
 *   - config.local.php       (your dev machine)
 *   - config.staging.php     (staging server, hand-placed once)
 *   - config.production.php  (production server, hand-placed once)
 *
 * All three are listed in .gitignore — never commit a file with real credentials.
 *
 * The right file is auto-loaded by config.php based on hostname (or APP_ENV).
 */

$CONFIG = [
    'db' => [
        'host'    => 'localhost',
        'name'    => 'CHANGE_ME_db_name',
        'user'    => 'CHANGE_ME_db_user',
        'pass'    => 'CHANGE_ME_db_password',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'env' => 'local',                       // 'local' | 'staging' | 'production'
        'url' => 'http://localhost:8000',       // canonical base URL for this env
    ],
];
