<?php
/**
 * Mombasa Mall Basement Parking - Remote Cloud Management Configuration
 * Standard PHP + MySQL configuration for cPanel shared hosting.
 * Shares the application's database settings unless a dedicated cloud override exists.
 */

require_once __DIR__ . '/../config/database.php';
$parkingConfig = get_config();

$config = [
    'db' => $parkingConfig['db'],
    'security' => [
        'api_key'     => $parkingConfig['cloud_sync']['api_key'] ?? '',
        'hmac_secret' => $parkingConfig['cloud_sync']['hmac_secret'] ?? '',
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
