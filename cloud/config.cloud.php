<?php
/**
 * Mombasa Mall Basement Parking - Remote Cloud Management Configuration
 * Standard PHP + MySQL configuration for cPanel shared hosting.
 * Supports local development override via config.cloud.local.php
 */

$config = [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'dbname'   => 'mombasam_parking',
        'username' => 'mombasam_admin',
        'password' => 'basement2026.',
        'charset'  => 'utf8mb4',
    ],
    'security' => [
        'api_key'     => 'MOMBASA_CLOUD_SYNC_KEY_987654321',
        'hmac_secret' => 'SEC_HMAC_MOMBASA_MALL_CLOUD_SIGNING_SECRET_2026',
    ],
    'mall' => [
        'name'     => 'Mombasa Mall Basement Parking',
        'capacity' => 60,
    ],
];

if (file_exists(__DIR__ . '/config.cloud.local.php')) {
    $local = require __DIR__ . '/config.cloud.local.php';
    if (is_array($local)) {
        $config = array_replace_recursive($config, $local);
    }
}

return $config;
